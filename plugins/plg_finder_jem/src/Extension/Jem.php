<?php

/**
 * @version    4.2.1
 * @package    Joomla.Plugin
 * @subpackage Finder.jem
 *
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

namespace Jem\Plugin\Finder\Jem\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Component\Newsfeeds\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseQuery;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Smart Search adapter for com_jem.
 */
final class Jem extends Adapter
{
    use DatabaseAwareTrait;

    /**
     * The plugin identifier.
     *
     * @var    string
     *
     */
    protected $context = 'Jem';

    /**
     * The extension name.
     *
     * @var    string
     *
     */
    protected $extension = 'com_jem';

    /**
     * The sublayout to use when rendering the results.
     *
     * @var    string
     *
     */
    protected $layout = 'jem';

    /**
     * The type of content that the adapter indexes.
     *
     * @var    string
     *
     */
    protected $type_title = 'Event';

    /**
     * The table name.
     *
     * @var    string
     *
     */
    protected $table = '#__jem_events';

    /**
     * The state field.
     *
     * @var    string
     *
     */
    protected $state_field = 'published';

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * 
     */
    protected $autoloadLanguage = true;

    /**
     * Method to update the item link information when the item category is
     * changed. This is fired when the item category is published or unpublished
     * from the list view.
     *
     * @param   string   $extension  The extension whose category has been updated.
     * @param   array    $pks        An array of primary key ids of the content that has changed state.
     * @param   integer  $value      The value of the state that the content has been changed to.
     *
     * @return  void
     *
     * 
     */
    public function onFinderCategoryChangeState($extension, $pks, $value)
    {
        // Make sure we're handling com_jem categories
        if ($extension == 'com_jem') {
            $this->categoryStateChange($pks, $value);
        }
    }

    /**
     * Method to remove the link information for items that have been deleted.
     *
     * @param   string  $context  The context of the action being performed.
     * @param   Table   $table    A Table object containing the record to be deleted
     *
     * @return  void
     *
     * 
     * @throws  \Exception on database error.
     */
    public function onFinderAfterDelete($context, $table): void
    {
        if ($context == 'com_jem.event') {
            $id = $table->id;
        } elseif ($context === 'com_finder.index') {
            $id = $table->link_id;
        } else {
            return;
        }

        // Remove the item from the index.
        $this->remove($id);
    }

    /**
     * Smart Search after save content method.
     * Reindexes the link information for a event that has been saved.
     * It also makes adjustments if the access level of a newsfeed item or
     * the category to which it belongs has changed.
     *
     * @param   string   $context  The context of the content passed to the plugin.
     * @param   Table    $row      A Table object.
     * @param   boolean  $isNew    True if the content has just been created.
     *
     * @return  void
     *
     * 
     * @throws  \Exception on database error.
     */
    public function onFinderAfterSave($context, $row, $isNew): void
    {
        // We only want to handle event here.
        if ($context == 'com_jem.event' || $context == 'com_jem.editevent') {
            // Check if the access levels are different.
            if (!$isNew && $this->old_access != $row->access) {
                // Process the change.
                $this->itemAccessChange($row);
            }

            // Reindex the item.
            $this->reindex($row->id);
        }

        // Check for access changes in the category.
        if ($context == 'com_jem.category') {
            // Check if the access levels are different.
            if (!$isNew && $this->old_cataccess != $row->access) {
                $this->categoryAccessChange($row);
            }
        }
    }

    /**
     * Smart Search before content save method.
     * This event is fired before the data is actually saved.
     *
     * @param   string   $context  The context of the content passed to the plugin.
     * @param   Table    $row      A Table object.
     * @param   boolean  $isNew    True if the content is just about to be created.
     *
     * @return  boolean  True on success.
     *
     * 
     * @throws  \Exception on database error.
     */
    public function onFinderBeforeSave($context, $row, $isNew)
    {
        // We only want to handle jem here.
        if ($context == 'com_jem.event' || $context == 'com_jem.editevent') {
            // Query the database for the old access level if the item isn't new.
            if (!$isNew) {
                $this->checkItemAccess($row);
            }
        }

        // Check for access levels from the category.
        if ($context == 'com_jem.category') {
            // Query the database for the old access level if the item isn't new.
            if (!$isNew) {
                $this->checkCategoryAccess($row);
            }
        }

        return true;
    }

    /**
     * Method to update the link information for items that have been changed
     * from outside the edit screen. This is fired when the item is published,
     * unpublished, archived, or unarchived from the list view.
     *
     * @param   string   $context  The context for the content passed to the plugin.
     * @param   array    $pks      An array of primary key ids of the content that has changed state.
     * @param   integer  $value    The value of the state that the content has been changed to.
     *
     * @return  void
     *
     * 
     */
    public function onFinderChangeState($context, $pks, $value)
    {
        // We only want to handle event here.
        if ($context === 'com_jem.event' || $context === 'com_jem.editevent') {
            $this->itemStateChange($pks, $value);
        }

        // Handle when the plugin is disabled.
        if ($context === 'com_plugins.plugin' && $value === 0) {
            $this->pluginDisable($pks);
        }
    }

    /**
     * Method to index an item. The item must be a Result object.
     *
     * @param   Result  $item  The item to index as a Result object.
     *
     * @return  void
     *
     * 
     * @throws  \Exception on database error.
     */
    protected function index(Result $item)
    {
        // Check if the extension is enabled.
        if (ComponentHelper::isEnabled($this->extension) === false) {
            return;
        }

        $item->setLanguage();

        // Initialize the item parameters.
        $registry     = new Registry($item->params);
        $item->params = clone ComponentHelper::getParams('com_jem', true);
        $item->params->merge($registry);

        $item->metadata = new Registry($item->metadata);

        // Create a URL as identifier to recognise items again.
        $item->url = $this->getUrl($item->id, $this->extension, $this->layout);

        // Build the necessary route and path information.
        $item->route = RouteHelper::getJemRoute($item->slug, $item->catslug, $item->language);

        // Get the menu title if it exists.
        $title = $this->getItemMenuTitle($item->url);

        // Adjust the title if necessary.
        if (!empty($title) && $this->params->get('use_menu_title', true)) {
            $item->title = $title;
        }

        // Add the meta-author.
        $item->metaauthor = !isset($item->metaauthor) ? '' : $item->metaauthor;
        $item->metaauthor = $item->metadata->get('author');

        // Add the meta-data processing instructions.
        $item->addInstruction(Indexer::META_CONTEXT, 'link');
        $item->addInstruction(Indexer::META_CONTEXT, 'metakey');
        $item->addInstruction(Indexer::META_CONTEXT, 'metadesc');
        $item->addInstruction(Indexer::META_CONTEXT, 'metaauthor');
        $item->addInstruction(Indexer::META_CONTEXT, 'author');
        $item->addInstruction(Indexer::META_CONTEXT, 'created_by_alias');

		// $item->addInstruction(FinderIndexer::META_CONTEXT, 'meta_description');

        // Translate the state. Articles should only be published if the category is published.
        $item->state = $this->translateState($item->state, $item->cat_state);

        // Get taxonomies to display
        $taxonomies = $this->params->get('taxonomies', ['author', 'category', 'language', 'venues']);

        // Add the type taxonomy data.
        if (\in_array('type', $taxonomies)) {
            $item->addTaxonomy('Type', 'Event');
        }

        // Add the author taxonomy data.
        if (!empty($item->author) || !empty($item->created_by_alias)) {
            $item->addTaxonomy('Author', !empty($item->created_by_alias) ? $item->created_by_alias : $item->author);
        }

        // Add the category taxonomy data.
        $categories = $this->getApplication()->bootComponent('com_jem')->getCategory(['published' => false, 'access' => false]);
        $category   = $categories->get($item->catid);

        if (!$category) {
            return;
        }

        // Add the category taxonomy data.
        if (\in_array('category', $taxonomies)) {
        	$item->addNestedTaxonomy('Category', $item->category, $item->cat_state, $item->cat_access);
        }

        // Add the language taxonomy data.
        if (\in_array('language', $taxonomies)) {
        	$item->addTaxonomy('Language', $item->language);
        }

        // Add the venue taxonomy data.
        if (!empty($item->venue)) {
            $item->addTaxonomy('Venue', $item->venue, $item->loc_published);
        }

        // Get content extras.
        Helper::getContentExtras($item);

        // Index the item.
        $this->indexer->index($item);
    }

    /**
     * Method to setup the indexer to be run.
     *
     * @return  boolean  True on success.
     *
     * @since   3.1
     */
    protected function setup()
    {
        return true;
    }

    /**
     * Method to get the SQL query used to retrieve the list of jem items.
     *
     * @param   mixed  $query  A DatabaseQuery object or null.
     *
     * @return  DatabaseQuery  A database object.
     *
     * 
     */
    protected function getListQuery($query = null)
    {
        $db = $this->getDatabase();

        // Check if we can use the supplied SQL query.
        $query = $query instanceof DatabaseQuery ? $query : $db->getQuery(true);
        $query->select('a.id, a.access, a.title, a.alias, a.dates, a.enddates, a.times, a.endtimes, a.datimage');
        $query->select('a.created AS publish_start_date, a.dates AS start_date, a.enddates AS end_date');
        $query->select('a.created_by, a.modified, a.version, a.published AS state');
        $query->select('a.fulltext AS body, a.introtext AS summary');
        $query->select('l.venue, l.city, l.state as loc_state, l.url, l.street');
        $query->select('l.published AS loc_published');
        $query->select('ct.name AS countryname');
        $query->select('c.catname AS category, c.published AS cat_state, c.access AS cat_access');

        // Handle the alias CASE WHEN portion of the query
        $case_when_item_alias = ' CASE WHEN ';
        $case_when_item_alias .= $query->charLength('a.alias', '!=', '0');
        $case_when_item_alias .= ' THEN ';
        $a_id = $query->castAsChar('a.id');
        $case_when_item_alias .= $query->concatenate([$a_id, 'a.alias'], ':');
        $case_when_item_alias .= ' ELSE ';
        $case_when_item_alias .= $a_id . ' END as slug';
        $query->select($case_when_item_alias);

        $case_when_category_alias = ' CASE WHEN ';
        $case_when_category_alias .= $query->charLength('c.alias', '!=', '0');
        $case_when_category_alias .= ' THEN ';
        $c_id = $query->castAsChar('c.id');
        $case_when_category_alias .= $query->concatenate([$c_id, 'c.alias'], ':');
        $case_when_category_alias .= ' ELSE ';
        $case_when_category_alias .= $c_id . ' END as catslug';
        $query->select($case_when_category_alias);

        $case_when_venue_alias = ' CASE WHEN ';
        $case_when_venue_alias .= $query->charLength('l.alias');
        $case_when_venue_alias .= ' THEN ';
        $l_id                  = $query->castAsChar('l.id');
        $case_when_venue_alias .= $query->concatenate(array($l_id, 'l.alias'), ':');
        $case_when_venue_alias .= ' ELSE ';
        $case_when_venue_alias .= $l_id . ' END as venueslug';
        $query->select($case_when_venue_alias);

        $query->from($this->table . ' AS a');
        $query->join('LEFT', '#__jem_venues AS l ON l.id = a.locid');
        $query->join('LEFT', '#__jem_countries AS ct ON ct.iso2 = l.country');
        $query->join('LEFT', '#__jem_cats_event_relations AS cer ON cer.itemid = a.id');
        $query->join('LEFT', '#__jem_categories AS c ON cer.catid = c.id');

        return $query;
    }
}
