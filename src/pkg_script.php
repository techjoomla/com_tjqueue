<?php
/**
 * @package    Tjqueue
 * @author     Techjoomla <extensions@techjoomla.com>
 * @copyright  Copyright (c) 2009-2019 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die();
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

/**
 * TjQueue
 *
 * @package     Tjqueue
 * @subpackage  Tjqueue
 * @since       0.0.1
 */
class Pkg_TjqueueInstallerScript
{
	/**
	 * Plugin method with the same name as the event will be called automatically.
	 */
	public function __construct()
	{
	}

	/**
	 * Runs after install, update or discover_update
	 * @param string $type install, update or discover_update
	 * @param JInstaller $parent
	 */
	public function postflight($type, $parent)
	{
	}
}
