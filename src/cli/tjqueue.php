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
	/**
	 * Method to execute script
	 *
	 * @return  void.
	 *
	 * @since 1.0
	 */
	public function execute()
	{
		$this->out('Started: Running queue cron');

		// If topic name not set in first argument
		if (!isset($this->config[1]))
		{
			$this->out('Error 404- Topic name not found to process. Please add the topic name in cron parameter');

			$result['success'] = 0;
			$result['message'] = 'Error 404- Topic name not found to process. Please add the topic name in cron parameter';

			self::writeLog($result, null, null);
			exit;
		}

		$topic = $this->config[1];
		$TJQueueConsume = new TJQueueConsume($topic);

		$i = 0;

		// If second parameter value is integer and greater than 0 otherwise set default limit to 50;
		$limit = (is_numeric($this->config[2]) && $this->config[2] > 0) ? $this->config[2] : 50;

		while ($i++ < $limit)
		{
			$message = $TJQueueConsume->receive();

			if ($message == null)
			{
				$this->out('Done: No message available in queue to process');
				$result['success'] = 1;
				$result['message'] = 'Done: No message available in queue to process';
				self::writeLog($result, null, null);
				exit;
			}

			$client  = $message->getProperty('client');

			if (empty($client))
			{
				$this->out('Invalid client');
				$result['success'] = 0;
				$result['message'] = 'Error- Invalid client- client value should not be blank';
				self::writeLog($result, null, null);
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
				$this->out("Error 404: Consumer class file doesn't exist:" . $filePath);

				$result['success'] = 0;
				$result['message'] = "Error 404- Consumer class file doesn't exist:" . $filePath;
				self::writeLog($result, $client, $topic);
				continue;
			}

			try
			{
				require_once $filePath;

				// Prepare class Name
				$className = 'plgTjqueue' . ucfirst($class);
				$obj       = new $className;
				$result    = $obj->consume($message);

				$TJQueueConsume->acknowledge($result);
				self::writeLog($result, $client, $topic);
			}
			catch (Exception $e)
			{
				$result['success'] = 0;
				$result['message'] = $e->getMessage();
				self::writeLog($result, $client, $topic);
			}
		}
	}

	/**
	 * Plugin method with the same name as the event will be called automatically.
	 *
	 * @param   array  $data    Result data
	 * @param   array  $client  Consumer client
	 * @param   array  $topic   Queue topic name
	 *
	 * @return  array.
	 *
	 * @since 0.0.1
	 */
	public function writeLog($data, $client, $topic)
	{
		$log  = ($data['success'] == 1 ? 'success - ' . $data['message'] : 'failed - ' . $data['message']) . " | " . $topic . " | " . $client;

		Log::addLogger(
			array (
			'text_file'         => 'tjqueue_log_' . date("j.n.Y") . '.log.php',
			'text_entry_format' => '{DATETIME} | {MESSAGE}'
			),
			Log::ALL,
			array($category = 'tjlogs')
		);

		Log::add($log, $priority = 'JLog::MINOR', $category = 'tjlogs');
	}
}

JApplicationCli::getInstance('TJQueue')->loadConfiguration($argv);
JApplicationCli::getInstance('TJQueue')->execute();
