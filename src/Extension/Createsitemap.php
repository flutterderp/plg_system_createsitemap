<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Createsitemap
 *
 * @copyright   (C) 2022 Otterly Useless
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Plugin\System\Createsitemap\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Plugin\System\Createsitemap\Console\CreatesitemapCommand;

class Createsitemap extends CMSPlugin
{
	protected $app;

	public function __construct(&$subject, $config = [])
	{
		parent::__construct($subject, $config);

		if(!$this->app->isClient('cli'))
		{
			return;
		}

		$this->registerCLICommands();
	}

	public static function getSubscribedEvents(): array
	{
		if($this->app->isClient('cli'))
		{
			return [
				Joomla\Application\ApplicationEvents\ApplicationEvents::BEFORE_EXECUTE => 'registerCLICommands',
			];
		}
	}

	public function registerCLICommands()
	{
		$commandObject = new CreatesitemapCommand;
		$this->app->addCommand($commandObject);
	}
}
