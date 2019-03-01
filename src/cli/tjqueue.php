<?php
/**
 * @package    Techjoomla.CLI
 * @author     Techjoomla <extensions@techjoomla.com>
 * @copyright  Copyright (c) 2009-2019 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2 or later.
 */

use Media\TJQueue\TJQueueConsume;
use Joomla\CMS\Log\Log;

define('_JEXEC', 1);
define('JPATH_BASE', dirname(__DIR__));

// Load system defines
if (file_exists(JPATH_BASE . '/defines.php'))
{
	require_once JPATH_BASE . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	require_once JPATH_BASE . '/includes/defines.php';
}

// Get the framework.
require_once JPATH_LIBRARIES . '/import.legacy.php';

// Bootstrap the CMS libraries.
require_once JPATH_LIBRARIES . '/cms.php';

// Load the configuration
require_once JPATH_CONFIGURATION . '/configuration.php';

jimport('tjqueue.tjqueueconsume', JPATH_SITE . '/media');

/**
 * TjQueue
 *
 * @package     Techjoomla.CLI
 * @subpackage  Tjqueue
 * @since       0.0.1
 */
class TJQueue extends JApplicationCli
{
	private $message = null;

	/**
	 * Method to execute script
	 *
	 * @return  void.
	 *
	 * @since 1.0
	 */
	public function execute()
	{
		$log['success'] = 1;
		$log['message'] = 'Started: Running queue cron';
		self::writeLog($log);

		// If topic name not set in first argument
		if (!isset($this->config[1]))
		{
			$log['success'] = 0;
			$log['message'] = 'Error-Topic name not found to process.';

			self::writeLog($log);
			exit;
		}

		$topic = $this->config[1];
		$TJQueueConsume = new TJQueueConsume($topic);

		$i = 0;

		// If second parameter value is integer and greater than 0 otherwise set default limit to 50;
		$limit = (is_numeric($this->config[2]) && $this->config[2] > 0) ? $this->config[2] : 50;

		while ($i++ < $limit)
		{
			$this->message = $TJQueueConsume->receive();

			if ($this->message == null)
			{
				$log['success'] = 1;
				$log['message'] = 'Done: No message available in queue to process';
				self::writeLog($log);
				exit;
			}

			$client  = $this->message->getProperty('client');

			if (empty($client))
			{
				$log['success'] = 0;
				$log['message'] = 'Error- Invalid client- client value should not be blank';
				self::writeLog($log);
				continue;
			}

			$res     = explode('.', $client);

			// Get plugin name to call
			$plugin  = $res[0];

			// Get class name
			$class   = $res[1];

			$filePath = JPATH_SITE . '/plugins/tjqueue/' . $plugin . '/consumers/' . $class . '.php';

			if (!file_exists($filePath))
			{
				$log['success'] = 0;
				$log['message'] = "Error 404- Consumer class file doesn't exist:" . $filePath;
				self::writeLog($log);
				continue;
			}

			try
			{
				require_once $filePath;

				// Prepare class Name
				$className = 'plgTjqueue' . ucfirst($class);

				if (!class_exists($className))
				{
					$log['success'] = 0;
					$log['message'] = $className . ' class not found';
					self::writeLog($log);

					continue;
				}

				$obj    = new $className;
				$result = $obj->consume($this->message);
				$TJQueueConsume->acknowledge($result);

				if ($result)
				{
					$log['success'] = 1;
					$log['message'] = 'Message consumed successfully';
				}
				else
				{
					$log['success'] = 0;
					$log['message'] = 'Message consumption failed';
				}

				self::writeLog($log);
			}
			catch (Exception $e)
			{
				$log['success'] = 0;
				$log['message'] = $e->getMessage();
				self::writeLog($log);
			}
		}
	}

	/**
	 * Plugin method with the same name as the event will be called automatically.
	 *
	 * @param   array  $data  Result data
	 *
	 * @return  array.
	 *
	 * @since 0.0.1
	 */
	public function writeLog($data)
	{
		$topic  = $this->config[1];
		$client = $this->message ? $this->message->getProperty('client') : null;
		$messageId = $this->message ? $this->message->getMessageId() : null;

		$this->out($data['message']);

		// Add to log
		$logFields = [
			"messageId" => $messageId,
			"message" => $data['message'],
			"topic" => $topic,
			"client" => $client
		];

		// Convert logFields to string implode by pipe(|)
		$logMessage = implode(' | ', array_map(
				function ($v, $k)
				{
					if (is_array($v))
					{
						return $k . '[]: ' . implode('&' . $k . '[]: ', $v);
					}
					else
					{
						return $k . ': ' . $v;
					}
				},
				$logFields,
				array_keys($logFields)
			)
		);

		Log::addLogger(
			array (
			'text_file'         => 'tjqueue_log_' . date("j.n.Y") . '.log.php',
			'text_entry_format' => '{DATETIME} | {PRIORITY} | {MESSAGE}'
			),
			Log::ALL,
			array($category = 'tjlogs')
		);

		$priority = $data['success'] == 1 ? JLog::INFO : JLog::ERROR;
		Log::add($logMessage, $priority, $category = 'tjlogs');
	}
}

JApplicationCli::getInstance('TJQueue')->loadConfiguration($argv);
JApplicationCli::getInstance('TJQueue')->execute();
