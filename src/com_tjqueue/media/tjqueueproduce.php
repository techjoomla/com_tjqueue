<?php
/**
 * @package    Techjoomla.Tjqueue
 * @author     Techjoomla <extensions@techjoomla.com>
 * @copyright  Copyright (c) 2009-2019 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2 or later.
 */

namespace Media\TJQueue;

// Doctrine
use Enqueue\Dbal\DbalConnectionFactory;
use Enqueue\Dbal\DbalMessage;

// SQS
use Enqueue\Sqs\SqsConnectionFactory;

// Joomla component helper to get params
use Joomla\CMS\Component\ComponentHelper;

defined('JPATH_PLATFORM') or die;
jimport('joomla.filesystem.folder');
jimport('joomla.application.component.helper');

/**
 * TJQueue handler
 *
 * @package     Techjoomla.Tjqueue
 * @subpackage  TJQueue
 * @since       1.0
 */
class TJQueueProduce
{
	private $jconfig;

	private $context;

	private $params;

	public  $message;

	/**
	 * TJQueue Class  constructor.
	 *
	 * @since   2.1
	 */
	public function __construct()
	{
		$file = JPATH_SITE . '/media/tjqueue/lib/vendor/autoload.php';

		if (file_exists($file))
		{
			require_once $file;
		}
		else
		{
			throw new \LogicException('Composer autoload was not found');
		}

		$this->params  = ComponentHelper::getParams('com_tjqueue');
		$this->jconfig = \JFactory::getConfig();
		$transport     = $this->params->get('transport');

		switch ($transport)
		{
			case "mysql":
				$this->doctrineDbal();
			break;

			case "aws_sqs":
				$this->awsSqs();
			break;
		}
	}

	/**
	 * Init SQS
	 *
	 * @return  void.
	 *
	 * @since 0.0.1
	 */
	private function awsSqs()
	{
		$config = [
			'key' => $this->params->get("aws_key"),
			'secret' => $this->params->get("aws_secret"),
			'region' => $this->params->get("aws_region"),
		];

		$factory = new SqsConnectionFactory($config);
		$this->context = $factory->createContext();
		$this->queue = $this->context->createQueue($this->params->get("topic"));
		$this->message = $this->context->createMessage();
		$this->context->declareQueue($this->queue);
	}

	/**
	 * Init doctrine
	 *
	 * @return  void.
	 *
	 * @since 0.0.1
	 */
	private function doctrineDbal()
	{
		$user     = $this->jconfig->get("user");
		$password = $this->jconfig->get("password");
		$host     = $this->jconfig->get("host");
		$db       = $this->jconfig->get("db");
		$url      = "mysql://" . $user . ":" . $password . "@" . $host . "/" . $db;

		$config = [
			'connection' => [
				'url' => $url,
				'driver' => 'pdo_mysql',
			],
		];

		$factory = new DbalConnectionFactory($config);
		$this->context = $factory->createContext();

		// @Todo set table name
		// $dbprefix = $this->jconfig->get("dbprefix");
		$this->context->createDataBaseTable();
		$this->queue = $this->context->createTopic($this->params->get('topic'));
		$this->message = $this->context->createMessage();
	}

	/**
	 * Method to add the message in queue
	 *
	 * @return  void.
	 *
	 * @since 1.0
	 */
	public function produce()
	{
		$this->context->createProducer()->send($this->queue, $this->message);
	}
}
