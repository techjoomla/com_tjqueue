<?php
/**
 * @package    Techjoomla.Tjqueue
 * @author     Techjoomla <extensions@techjoomla.com>
 * @copyright  Copyright (c) 2009-2019 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2 or later.
 */

namespace TJQueue\Admin;

// Doctrine
use Enqueue\Dbal\DbalConnectionFactory;
use Enqueue\Dbal\DbalMessage;

// SQS
use Enqueue\Sqs\SqsConnectionFactory;

// Joomla component helper to get params
use Joomla\CMS\Component\ComponentHelper;

use TJQueue\Admin\Helpers\TJQueueContext;

defined('JPATH_PLATFORM') or die;
jimport('joomla.filesystem.folder');
jimport('joomla.application.component.helper');
jimport('helpers.tjqueuecontext', JPATH_SITE . '/administrator/components/com_tjqueue/libraries');

/**
 * TJQueue handler
 *
 * @package     Techjoomla.Tjqueue
 * @subpackage  TJQueue
 * @since       1.0
 */
class TJQueueProduce extends TJQueueContext
{
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
		parent::__construct();

		$file = JPATH_SITE . '/administrator/components/com_tjqueue/libraries/libs/vendor/autoload.php';

		if (file_exists($file))
		{
			require_once $file;
		}
		else
		{
			throw new \LogicException('Composer autoload was not found');
		}

		$this->params  = ComponentHelper::getParams('com_tjqueue');
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
		$this->context = $this->getSqsContext();
		$this->queue   = $this->context->createQueue($this->params->get("topic"));
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
		$this->context = $this->getDbalContext();
		$this->context->createDataBaseTable();
		$this->queue   = $this->context->createTopic($this->params->get('topic'));
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
