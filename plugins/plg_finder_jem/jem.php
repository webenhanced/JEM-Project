<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseQuery;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;														 
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

//defined('JPATH_BASE') or die;
defined('_JEXEC') or die;

jimport('joomla.application.component.helper');
require_once JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/adapter.php';

class plgFinderJem extends Adapter
//Phocacartproduct,Phocacartcategory,Phocadownloadcategory,Phocadownload
{
	protected $context 		= 'Jemevent';
	//Phocacartproduct,Phocacartcategory,Phocadownloadcategory,Phocadownload
	protected $extension 	= 'com_jem';
//	protected $layout 		= 'category';
	protected $layout = 'event';	
	//category, category,category,category
	protected $type_title 	= 'Jem Event';
	//Phoca Cart, Phoca Cart Category, Phoca Download Category,Phoca Download
	protected $table 		= '#__jem_events';
	//#__TABLE_products,#__TABLE_categories,#__TABLE_categories, #__TABLE_categories
    protected $state_field = 'published';
	protected $autoloadLanguage = true;
	//ja,nein,nein,ja,nein,ja
	
/*	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}
*/
	public function onFinderBeforeSave($context, $row, $isNew)																			 
	{
		// We only want to handle web links here
		if ($context == 'com_jem.event' || $context == 'com_jem.editevent' )
		//phocacartproduct,phocacartcat,phocadownloadcat,phocadownloadfile,phocagallerycat, phocagalleryimg
		//product,category,category,file,category,img
		{
			// Query the database for the old access level if the item isn't new
			if (!$isNew)
			{	
				$this->checkItemAccess($row);
			}
		}

		// Check for access levels from the category
		if ($context == 'com_jem.category')
		//phocacartcategory,phocacartcat,phocadownloadcat,phocadownloadcat,phocagallerycat,phocagallerycat
		{
			// Query the database for the old access level if the item isn't new
			if (!$isNew)
			{
				$this->checkCategoryAccess($row);
			}
		}
		return true;
	}


	public function onFinderAfterSave($context, $row, $isNew)
	{
		// We only want to handle web links here. We need to handle front end and back end editing.
		if ($context == 'com_jem.event' || $context == 'com_jem.editevent' )
		//phocacartcatproduct,phocacartcat,phocadownloadcat,phocadownloadfile,phocagallerycat
		//product,category,category,file,category.img
		{
			// Check if the access levels are different
			if (!$isNew && $this->old_access != $row->access)
			//if (isset($row->access) && !$isNew && $this->old_access != $row->access)
	
			{
				// Process the change.
				$this->itemAccessChange($row);
			}
			// Reindex the item
			$this->reindex($row->id);
		}

		// Check for access changes in the category
		if ($context == 'com_jem.category')
		//phocacartcategory,phocacartcat,phocadownloadcat,phocadownloadcat,phocagallerycat,phocagallerycat
		{
			// Check if the access levels are different
			if (!$isNew && $this->old_cataccess != $row->access)
			{
				$this->categoryAccessChange($row);
			}
		}
		return true;
	}

	public function onFinderAfterDelete($context, $table)
	{
		if ($context == 'com_jem.event')
		//phocacartproduct,phocacartcat,phocadownloadcat,phocadownloadfile,phocagallerycat,phocagalleryimg
		{										  
			$id = $table->id;
		}
		elseif ($context == 'com_finder.index')
		{
			$id = $table->link_id;
		}
		else
		{
			return true;
		}	
		// Remove the items.
		return $this->remove($id);
	}

	public function onFinderChangeState($context, $pks, $value)
	{
		// We only want to handle web links here
		if ($context == 'com_jem.event' || $context == 'com_jem.editevent' )
		//phocacartproduct,phocacartcat,phocadownloadcat,phocadownloadfile,phocagallerycat,phocagalleryimg
		//product,category,category,file,category,img
		{
			$this->itemStateChange($pks, $value);
			
		// Handle when the plugin is disabled
		if ($context == 'com_plugins.plugin' && $value === 0)
		{
			$this->pluginDisable($pks);
		}
	}
														  
	public function onFinderCategoryChangeState($extension, $pks, $value)
	{

		if ($extension == 'com_jem')
		{
			$this->categoryStateChange($pks, $value);
		}
	}

	protected function index(Result $item, $format = 'html')								  
	{
		// Check if the extension is enabled
		if (ComponentHelper::isEnabled($this->extension) == false)
		
			return;
		}

		$item->setLanguage();

		// Initialize the item parameters.
		$registry = new Registry;
		$registry->loadString($item->params);
		$item->params = ComponentHelper::getParams('com_jem', true);
		$item->params->merge($registry);

		$registry = new Registry;
		$registry->loadString($item->metadata);
		$item->metadata = $registry;

        // Trigger the onContentPrepare event.
        $item->summary = Helper::prepareContent($item->summary, $item->params);
        $item->body    = Helper::prepareContent($item->fulltext, $item->params);																				 

		// Build the necessary route and path information.
		$item->url = $this->getURL($item->id, $this->extension, $this->layout);
		//$item->route = JemHelperRoute::getEventRoute($item->id, $item->alias, $item->language);
        $item->route = JEMHelperRoute::getEventRoute($item->slug, $item->catslug);
		//getItemRoute,getCategoryRoute,getCategoryRoute,getFileRoute,getImageRoute
		//$item->id,$item->categoryid, $item->categoryalias
		//--
		//$item->path = Helper::getContentPath($item->route);
		/*
		 * Add the meta-data processing instructions based on the newsfeeds
		 * configuration parameters.
		 */

		// Add the meta-author.
		$item->metaauthor = $item->metadata->get('author');

		// Handle the link to the meta-data.

		$item->addInstruction(Indexer::META_CONTEXT, 'link');
		$item->addInstruction(Indexer::META_CONTEXT, 'metakey');
		$item->addInstruction(Indexer::META_CONTEXT, 'metadesc');
		$item->addInstruction(Indexer::META_CONTEXT, 'metaauthor');
		$item->addInstruction(Indexer::META_CONTEXT, 'author');
		$item->addInstruction(Indexer::META_CONTEXT, 'created_by_alias');

        // Translate the state. Articles should only be published if the category is published.
        $item->state = $this->translateState($item->state, $item->cat_state);



		// Add the type taxonomy data.
		$item->addTaxonomy('Type', '[Jem Event]');
		//Phoca Cart,Phoca Cart Category,Phoca Download Category,Phoca Download
        // Add the author taxonomy data.
        if (!empty($item->author) || !empty($item->created_by_alias)) 
		{
            $item->addTaxonomy('Author', !empty($item->created_by_alias) ? $item->created_by_alias : $item->author);
        }

        if (!$item->Category) 
		{
            return true;
        }																	   
		// Add the category taxonomy data.
		//if (isset($item->category) && $item->category != '') {
		//nur category und images
            $item->addTaxonomy('Category', $item->category, $item->cat_state, $item->cat_access);
        /*}*/

		// Add the language taxonomy data.
		$item->addTaxonomy('Language', $item->language);

        // Add the venue taxonomy data.
        if (!empty($item->venue)) 
		{
            $item->addTaxonomy('Venue', $item->venue, $item->loc_published);
        }

		// Get content extras.
		Helper::getContentExtras($item);

		// Index the item.
		$this->indexer->index($item);
	}


	protected function setup()
	{

        // Load dependent classes.
        include_once JPATH_SITE . '/components/com_jem/helpers/route.php';

        return true;
    }																	   


	protected function getListQuery($sql = null)
	{
		//$db = Factory::getContainer()->get('DatabaseDriver');
		$db = Factory::getDbo();
        // Check if we can use the supplied SQL query.
        $sql = $sql instanceof DatabaseQuery ? $sql : $db->getQuery(true);

// 		$sql->select('a.id, a.title, a.alias, a.introtext AS summary, a.fulltext AS body');
// 		$sql->select('a.state, a.catid, a.created AS start_date, a.created_by');
// 		$sql->select('a.created_by_alias, a.modified, a.modified_by, a.attribs AS params');
// 		$sql->select('a.metakey, a.metadesc, a.metadata, a.language, a.access, a.version, a.ordering');
// 		$sql->select('a.publish_up AS publish_start_date, a.publish_down AS publish_end_date');
// 		$sql->select('c.title AS category, c.published AS cat_state, c.access AS cat_access');

        $sql->select('a.id, a.access, a.title, a.alias, a.dates, a.enddates, a.times, a.endtimes, a.datimage');
        $sql->select('a.created AS publish_start_date, a.dates AS start_date, a.enddates AS end_date');
        $sql->select('a.created_by, a.modified, a.version, a.published AS state');
        $sql->select('a.fulltext AS body, a.introtext AS summary');
        $sql->select('l.venue, l.city, l.state as loc_state, l.url, l.street');
        $sql->select('l.published AS loc_published');
        $sql->select('ct.name AS countryname');
        $sql->select('c.catname AS category, c.published AS cat_state, c.access AS cat_access');

        // Handle the alias CASE WHEN portion of the query
        $case_when_item_alias = ' CASE WHEN ';
        $case_when_item_alias .= $sql->charLength('a.alias');
        $case_when_item_alias .= ' THEN ';
        $a_id                 = $sql->castAsChar('a.id');
        $case_when_item_alias .= $sql->concatenate(array($a_id, 'a.alias'), ':');
        $case_when_item_alias .= ' ELSE ';
        $case_when_item_alias .= $a_id . ' END as slug';
        $sql->select($case_when_item_alias);

        $case_when_category_alias = ' CASE WHEN ';
        $case_when_category_alias .= $sql->charLength('c.alias');
        $case_when_category_alias .= ' THEN ';
        $c_id                     = $sql->castAsChar('c.id');
        $case_when_category_alias .= $sql->concatenate(array($c_id, 'c.alias'), ':');
        $case_when_category_alias .= ' ELSE ';
        $case_when_category_alias .= $c_id . ' END as catslug';
        $sql->select($case_when_category_alias);

        $case_when_venue_alias = ' CASE WHEN ';
        $case_when_venue_alias .= $sql->charLength('l.alias');
        $case_when_venue_alias .= ' THEN ';
        $l_id                  = $sql->castAsChar('l.id');
        $case_when_venue_alias .= $sql->concatenate(array($l_id, 'l.alias'), ':');
        $case_when_venue_alias .= ' ELSE ';
        $case_when_venue_alias .= $l_id . ' END as venueslug';
        $sql->select($case_when_venue_alias);


        $sql->from($this->table . ' AS a');
        $sql->join('LEFT', '#__jem_venues AS l ON l.id = a.locid');
        $sql->join('LEFT', '#__jem_countries AS ct ON ct.iso2 = l.country');
        $sql->join('LEFT', '#__jem_cats_event_relations AS cer ON cer.itemid = a.id');
			  

        $sql->join('LEFT', '#__jem_categories AS c ON cer.catid = c.id');

        return $sql;
    }																							
    protected function getStateQuery()
    {
		$db = Factory::getDbo();
        // Check if we can use the supplied SQL query.
        $sql = $db->getQuery(true);

        // Item ID
        $sql->select('a.id');
        // Item and category published state
        $sql->select($db->quoteName('a.' . $this->state_field, 'state'));
        $sql->select('c.published AS cat_state');
        // Item and category access levels
        $sql->select('1 AS access, c.access AS cat_access');
        $sql->from($db->quoteName($this->table, 'a'));
        $sql->join('LEFT', '#__jem_cats_event_relations AS cer ON cer.itemid = a.id');
        $sql->join('LEFT', '#__jem_categories AS c ON cer.catid = c.id');

        return $sql;
    }
    protected function checkCategoryAccess($row)
    {
        $query = $this->db->getQuery(true);
        $query->select($this->db->quoteName('access'));
        $query->from($this->db->quoteName('#__jem_categories'));
        $query->where($this->db->quoteName('id') . ' = ' . (int)$row->id);
        $this->db->setQuery($query);

        // Store the access level to determine if it changes
        $this->old_cataccess = $this->db->loadResult();
    }																						   

}
