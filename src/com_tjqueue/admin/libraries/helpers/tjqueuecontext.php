<?php
/**
 * @package     Techjoomla.Tjqueue
 * @subpackage  TJQueue
 * @author      Techjoomla <extensions@techjoomla.com>
 * @copyright   Copyright (c) 2009-2019 TechJoomla. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

namespace TJQueue\Admin\Helpers;

// Joomla component helper to get params
use Joomla\CMS\Component\ComponentHelper;

// Doctrine
use Enqueue\Dbal\DbalConnectionFactory;
use Enqueue\Dbal\DbalMessage;

// SQS
use Enqueue\Sqs\SqsConnectionFactory;

defined('JPATH_PLATFORM') or die;
jimport('joomla.filesystem.folder');
jimport('joomla.application.component.helper');

/**
 * TJQueue Config
 *
 * @package     Techjoomla.Tjqueue
 * @subpackage  TJQueue
 * @since       1.0
 */
class TJQueueContext
{
	private $params;

	private $jconfig;
	/**
	 * TJQueue Class  constructor.
	 *
	 * @since   2.1
	 */
	protected function __construct()
	{
		$this->params  = ComponentHelper::getParams('com_tjqueue');
		$this->jconfig = \JFactory::getConfig();
	}

	/**
	 * Init SQS
	 *
	 * @return  void.
	 *
	 * @since 0.0.1
	 */
	protected function getSqsContext()
	{
		$config = [
			'key' => $this->params->get("aws_key"),
			'secret' => $this->params->get("aws_secret"),
			'region' => $this->params->get("aws_region"),
		];

		$factory = new SqsConnectionFactory($config);

		return $factory->createContext();
	}

	/**
	 * Init DBAL connection
	 *
	 * @return  void.
	 *
	 * @since 0.0.1
	 */
	protected function getDbalContext()
	{
		$user     = $this->jconfig->get("user");
		$password = $this->jconfig->get("password");
		$host     = $this->jconfig->get("host");
		$db       = $this->jconfig->get("db");
		$url      = "mysql://" . $user . ":" . urlencode($password) . "@" . $host . "/" . $db;

		$config = [
			'connection' => [
				'url' => $url,
				'driver' => 'pdo_mysql',
			],
			'table_name' => $this->jconfig->get("dbprefix") . "enqueue",
		];

		$factory = new DbalConnectionFactory($config);

		return $factory->createContext();
	}
}
