<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Createsitemap
 *
 * @copyright   (C) 2022 Otterly Useless
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Plugin\System\Createsitemap\Console;

\defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreatesitemapCommand extends AbstractCommand
{
	/**
	 * The default command name
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	protected static $defaultName = 'createsitemap:domain';

	/**
	 * @var InputInterface
	 * @since 4.0.0
	 */
	private $cliInput;

	/**
	 * SymfonyStyle Object
	 *
	 * @var SymfonyStyle
	 * @since 4.0.0
	 */
	private $ioStyle;

	/**
	 * Instantiate the command
	 *
	 * @since 4.0.0
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Configures the IO
	 *
	 * @param InputInterface   $input   Console Input
	 * @param OutputInterface  $output  Console Output
	 * @return void
	 * @since 4.0.0
	 *
	 */
	public function configureIO(InputInterface $input, OutputInterface $output)
	{
		$this->cliInput = $input;
		$this->ioStyle  = new SymfonyStyle($input, $output);
	}

	/**
	 * Initialise the command.
	 *
	 * @return void
	 * @since 4.0.0
	 */
	public function configure(): void
	{
		$this->addArgument('domain',
			InputArgument::REQUIRED,
			'website domain name');

		$help = "<info>%command.name%</info> Creates a sitemap from enabled Joomla menus
			\nUsage: <info>php %command.full_name% domain_name
			\nwhere domain_name is the live domain name for your website (e.g. www.example.com).</info>";

		$this->setDescription('Called by cron to set the enabled state of a module.');
		$this->setHelp($help);
	}

	/**
	 * Internal function to execute the command.
	 *
	 * @param InputInterface   $input   The input to inject into the command.
	 * @param OutputInterface  $output  The output to inject into the command.
	 *
	 * @return  integer  The command exit code
	 *
	 * @since   4.0.0
	 */
	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		$this->configureIO($input, $output);

		$domain = $this->cliInput->getArgument('domain');
		$result = $this->buildMap($domain);

		return 1;
	}

	protected function buildMap(string $livesite)
	{
		$app                  = Factory::getApplication();
		$sitename             = $app->get('sitename');
		$forceSsl             = (int) $app->get('force_ssl', 0);
		$website              = ($forceSsl === 2 ? 'https' : 'http') . '://' . $livesite . '/';
		$_SERVER['HTTP_HOST'] = $website;
		$base_uri             = Uri::base();

		try
		{
			$today     = new \DateTime(null, new \DateTimeZone('UTC'));

			$textFile  = fopen(JPATH_BASE . '/sitemap.txt', 'w+');
			$menuitems = $this->getMenu();
			$links     = array();
			$n         = 0;

			foreach($menuitems as $row)
			{
				// Write URL
				$links[$n]['uri']      = Route::link('site', $row->link . '&Itemid=' . $row->id, true, Route::TLS_IGNORE, true);
				$links[$n]['uri']      = str_ireplace($base_uri, '', $links[$n]['uri']);
				$links[$n]['modified'] = '0000-00-00 00:00:00';

				// Write URLs for child links, if applicable
				if(stripos($row->link, 'categor') !== false)
				{
					foreach($this->getChildItems($row->link, $row->id) as $child)
					{
						if(!array_key_exists('link', $child))
						{
							continue;
						}
						elseif(array_search($child['link'], array_column($links, 'uri')) !== false)
						{
							// Link already exists as a menu item
							continue;
						}

						$links[$n]['uri'] = str_ireplace($base_uri, '', $child['link']);

						if(array_key_exists('modified', $child) && $child['modified'] != '0000-00-00')
						{
							$links[$n]['modified'] = $child['modified'];
						}
						else
						{
							$links[$n]['modified'] = '0000-00-00 00:00:00';
						}

						// Increase links array counter
						$n++;
					}
				}
				// Increase links array counter
				$n++;
			}

			// Output sitemap files
			// Prepare XML output
			$xml = new \XMLWriter;
			$xml->openUri(JPATH_BASE . '/sitemap.xml');
			$xml->setIndent(true);
			$xml->startDocument($version = '1.0', $encoding = 'utf-8');
			$xml->startElementNS($prefix = null, $name = 'urlset', $uri = 'http://www.sitemaps.org/schemas/sitemap/0.9');

			foreach($links as $link)
			{
				// Write URL
				$xml->startElement('url');
				$xml->writeElement('loc', $website . $link['uri']);
				$xml->writeElement('changefreq', 'weekly');
				$xml->writeElement('priority', '0.8');
				if(array_key_exists('modified', $link) && $link['modified'] != '0000-00-00 00:00:00')
				{
					$xml->writeElement('lastmod', $link['modified']);
				}

				$xml->fullEndElement();
				fwrite($textFile, $website . $link['uri'] . "\n");
			}

			// End urlset
			$xml->fullEndElement();
			$xml->outputMemory();
			fclose($textFile);

			// $this->ioStyle->info($base_uri);
			$this->ioStyle->success('Sitemap has been generated - ' . $today->format('Y-m-d H:i:s T') . '.');
		}
		catch(Exception $e)
		{
			$msg = $e->getCode() . ': ' . $e->getMessage();

			$this->ioStyle->error("An error occurred\n{$msg}");
		}
	}

	/**
	 * Fetches published menu items that are not external URLs or aliases
	 */
	protected function getMenu()
	{
		$jfours = array(4,5);
		$db     = Factory::getDbo();
		$query  = $db->getQuery(true);
		$query
			->select('`m`.`id`,`m`.`menutype`,`m`.`title`,`m`.`alias`')
			->select('`m`.`path` AS route, `m`.`link`, `m`.`type`, `m`.`published`')
			->select('`m`.`parent_id`, `m`.`level`, `m`.`component_id`, `m`.`access`')
			->select('`m`.`client_id`, `e`.`element` AS component')
			->from($db->qn('#__menu', 'm'))
			->join('left', $db->qn('#__extensions', 'e') . ' on `e`.`extension_id` = `m`.`component_id`')
			->where('`m`.`client_id` = 0')
			->where('NOT FIND_IN_SET(`m`.`menutype`, '.$db->q('hiddenmenu,hidden-menu,shopmenu').')')
			->where('NOT FIND_IN_SET(`m`.`type`, '.$db->q('alias,heading,separator,url').')')
			->where('`e`.`element` <> ' . $db->q('com_users'))
			->where('`m`.`published` = 1')
			->where('`m`.`access` IN(1,5)');
		$db->setQuery($query);

		try
		{
			if(in_array(Version::MAJOR_VERSION, $jfours))
			{
				// Joomla.CMS.Menu.SiteMenu
				$rows = $db->loadObjectList('id');
			}
			else
			{
				$rows = $db->loadObjectList('id', 'JMenuSite');
			}
		}
		catch(Exception $e)
		{
			$rows = false;
		}

		return $rows;
	}

	/**
	 * Looks for individual elements of a category view
	 *
	 * @param link The link string from the category's menu item
	 * @param itemId The menu item's ID
	 *
	 * @return return Array of child links
	 */
	protected function getChildItems($categoryLink = '', $itemId = 0)
	{
		$db        = Factory::getDbo();
		$inflector = \Joomla\String\Inflector::getInstance();
		$query     = $db->getQuery(true);
		$return    = array();

		try
		{
			// Parse menu link for component name
			$link = parse_url($categoryLink);

			parse_str($link['query'], $q);
			$router = JPATH_BASE . '/components/' . $q['option'] . '/helpers/route.php';

			if(File::exists($router))
			{
				include_once($router);
			}

			$componentName    = substr($q['option'], 4);
			$uc_componentName = ucwords($componentName);
			$helperRoute      = $uc_componentName.'HelperRoute';
			$options          = array();
			$categories       = Categories::getInstance($uc_componentName, $options);
			$catid            = (int) $q['id'];
			$catids           = array($catid);
			$parent_cat       = $categories->get($catid);

			if($parent_cat)
			{
				foreach($parent_cat->getChildren(true) as $tmpChild)
				{
					$catids[]             = $tmpChild->id;
					$return[]['link']     = Route::link('site', $helperRoute::getCategoryRoute($tmpChild->id), true, Route::TLS_IGNORE, true);
					// $return[]['link']     = Route::link('site', 'index.php?option='.$q['option'].'&view=category&id='.$tmpChild->id.'&Itemid='.$itemId);
					$return[]['modified'] = '0000-00-00 00:00:00';
				}
			}
			// Set view name
			switch($componentName)
			{
				case 'blog' :
				case 'content' :
					$view = 'article';
					break;
				default :
					$view = $inflector->toSingular($componentName);
					break;
			}

			switch($componentName)
			{
				case 'contact' :
					$tableName = 'contact_details';
					break;
				default :
					$tableName = $componentName;
					break;
			}

			$query->clear();
			$query->select('a.id,a.alias,concat(a.id,\':\',a.alias) as slug,a.catid,DATE(a.modified) as modified')
				->from($db->qn('#__'.$tableName, 'a'))
				->where('a.catid IN('.implode(',', $catids).')')
				->where('a.state = 1')->where('a.access IN(1,5)');
			$db->setQuery($query);

			foreach($db->loadObjectList() as $item)
			{
				$itemRoute            = 'get'.ucwords($view).'Route';
				$tmpLink              = Route::link('site', $helperRoute::$itemRoute($item->slug, $item->catid), true, Route::TLS_IGNORE, true);
				$return[]['link']     = $tmpLink;
				$return[]['modified'] = $item->modified;
			}
		}
		catch(Exception $e)
		{
			echo $e->getMessage() . PHP_EOL;

			// $return = false;
		}

		return $return;
	}
}
