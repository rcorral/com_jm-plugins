<?php
/**
 * @package	K2 JM plugin
 * @version 1.0
 * @author 	Rafael Corral
 * @link 	http://www.rafaelcorral.com
 * @copyright Copyright (C) 2011 Rafael Corral. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

class K2JMResourceItems extends JMResource
{
	public function get()
	{
		// Set the user doing the request as if they were authenticated in Joomla
		JMHelper::setSessionUser();

		if ( !defined('K2_JVERSION') ) {
			define( 'K2_JVERSION', '16' );
		}

		// Get the list of items
		$items = $this->getData();

		if ( !$items && $this->getError() ) {
			$response = $this->getErrorResponse( 400, $this->getError() );
		} else {
			$response = $items;
		}

		$this->plugin->setResponse( $response );
	}

	/**
	 * This is a modified method from the trash() method in:
	 * /admin/com_k2/models/items.php
	 */
	public function delete()
	{
		$db = &JFactory::getDBO();
		$cid = JRequest::getVar('cid');
		JArrayHelper::toInteger($cid);

		JTable::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/tables' );
		$row = &JTable::getInstance('K2Item', 'Table');

		foreach ( $cid as $id ) {
			$row->load( $id );
			$row->trash = 1;
			$row->store();
		}

		$cache = &JFactory::getCache('com_k2');
		$cache->clean();

		$response = $this->getSuccessResponse( 200, JText::_('COM_JM_SUCCESS') );

		$this->plugin->setResponse( $response );
	}

	/**
	 * This function is copied from:
	 * /administrator/components/com_k2/models/items.php method getData()
	 * The only changes to the function is the select statement, only grabbing the data that is needed
	 * also added check for database error.
	 */
	function getData()
	{
		$mainframe = &JFactory::getApplication();
		$params = &JComponentHelper::getParams('com_k2');
		$option = JRequest::getCmd('option');
		$view = JRequest::getCmd('view');
		$db = &JFactory::getDBO();
		$limit = $mainframe->getUserStateFromRequest('global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
		$limitstart = $mainframe->getUserStateFromRequest($option.$view.'.limitstart', 'limitstart', 0, 'int');
		$filter_order = $mainframe->getUserStateFromRequest($option.$view.'filter_order', 'filter_order', 'i.id', 'cmd');
		$filter_order_Dir = $mainframe->getUserStateFromRequest($option.$view.'filter_order_Dir', 'filter_order_Dir', 'DESC', 'word');
		$filter_trash = $mainframe->getUserStateFromRequest($option.$view.'filter_trash', 'filter_trash', 0, 'int');
		$filter_featured = $mainframe->getUserStateFromRequest($option.$view.'filter_featured', 'filter_featured', -1, 'int');
		$filter_category = $mainframe->getUserStateFromRequest($option.$view.'filter_category', 'filter_category', 0, 'int');
		$filter_author = $mainframe->getUserStateFromRequest($option.$view.'filter_author', 'filter_author', 0, 'int');
		$filter_state = $mainframe->getUserStateFromRequest($option.$view.'filter_state', 'filter_state', -1, 'int');
		$search = $mainframe->getUserStateFromRequest($option.$view.'search', 'search', '', 'string');
		$search = JString::strtolower($search);
		$tag = $mainframe->getUserStateFromRequest($option.$view.'tag', 'tag', 0, 'int');
		$language = $mainframe->getUserStateFromRequest($option.$view.'language', 'language', '', 'string');

		$query = "SELECT i.id, i.title, i.published, i.created, i.access, g.name AS groupname, c.name AS category, v.name AS author FROM #__k2_items as i";

		$query .= " LEFT JOIN #__k2_categories AS c ON c.id = i.catid"." LEFT JOIN #__groups AS g ON g.id = i.access"." LEFT JOIN #__users AS u ON u.id = i.checked_out"." LEFT JOIN #__users AS v ON v.id = i.created_by"." LEFT JOIN #__users AS w ON w.id = i.modified_by";

		if($params->get('showTagFilter') && $tag){
			$query .= " LEFT JOIN #__k2_tags_xref AS tags_xref ON tags_xref.itemID = i.id";
		}

		$query .= " WHERE i.trash={$filter_trash}";

		if ($search) {

			$search = JString::str_ireplace('*', '', $search);
			$words = explode(' ', $search);
			for($i=0; $i<count($words); $i++){
				$words[$i]= '+'.$words[$i];
				$words[$i].= '*';
			}
			$search = implode(' ', $words);
			$search = $db->Quote($db->getEscaped($search, true), false);

			if($params->get('adminSearch')=='full')
			$query .= " AND MATCH(i.title, i.introtext, i.`fulltext`, i.extra_fields_search, i.image_caption,i.image_credits,i.video_caption,i.video_credits,i.metadesc,i.metakey)";
			else
			$query .= " AND MATCH( i.title )";

			$query.= " AGAINST ({$search} IN BOOLEAN MODE)";
		}

		if ($filter_state > - 1) {
			$query .= " AND i.published={$filter_state}";
		}

		if ($filter_featured > - 1) {
			$query .= " AND i.featured={$filter_featured}";
		}

		if ($filter_category > 0) {
			if ($params->get('showChildCatItems')) {
				require_once (JPATH_SITE.DS.'components'.DS.'com_k2'.DS.'models'.DS.'itemlist.php');
				$categories = K2ModelItemlist::getCategoryTree($filter_category);
				$sql = @implode(',', $categories);
				$query .= " AND i.catid IN ({$sql})";
			} else {
				$query .= " AND i.catid={$filter_category}";
			}

		}

		if ($filter_author > 0) {
			$query .= " AND i.created_by={$filter_author}";
		}

		if($params->get('showTagFilter') && $tag){
			$query .= " AND tags_xref.tagID = {$tag}";
		}
		
		if ($language) {
			$query .= " AND i.language = ".$db->Quote($language);
		}

		if ($filter_order == 'i.ordering') {
			$query .= " ORDER BY i.catid, i.ordering {$filter_order_Dir}";
		} else {
			$query .= " ORDER BY {$filter_order} {$filter_order_Dir} ";
		}

		if(K2_JVERSION=='16'){
			$query = JString::str_ireplace('#__groups', '#__viewlevels', $query);
			$query = JString::str_ireplace('g.name', 'g.title', $query);
		}

		$db->setQuery($query, $limitstart, $limit);
		$rows = $db->loadObjectList();

		if ( $db->getErrorNum() ) {
			$this->setError( $db->getErrorMsg() );
			return false;
		}

		return $rows;

	}
}