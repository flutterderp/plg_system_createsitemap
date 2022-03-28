<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Createsitemap
 *
 * @copyright   (C) 2022 Otterly Useless
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\System\Createsitemap\Extension\Createsitemap;

return new class implements ServiceProviderInterface
{
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 * @return  void
	 * @since   4.0.0
	 */

	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,

			function (Container $container) {
				$subject = $container->get(DispatcherInterface::class);
				$config  = (array) PluginHelper::getPlugin('system', 'createsitemap');

				return new Createsitemap($subject, $config);
			}
		);
	}
};
