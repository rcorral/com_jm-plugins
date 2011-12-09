<?php
/**
 * @package	K2 JM plugin
 * @version 1.0
 * @author 	Rafael Corral
 * @link 	http://www.rafaelcorral.com
 * @copyright Copyright (C) 2011 Rafael Corral. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class K2JMResourceCategories extends JMResource
{
	public function get()
	{
		jimport('joomla.html.parameter');

		if ( JRequest::getVar('tree') ) {
			JModel::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/models' );
			$categoriesModel = &JModel::getInstance( 'Categories', 'K2Model' );
			$response = $categoriesModel->categoriesTree(null, true, true);
		} else {
			if ( JRequest::getVar('all') ) {
				$rows = $this->getCategories();
			} else {
				$rows = array();
				foreach ( $this->getCategories() as $key => $_row ) {
					$rows[] = (object) array(
						'id' => $_row->id,
						'title' => $_row->title,
						'published' => $_row->published,
						'access' => $_row->access,
						'treename' => str_replace( '.&#160;&#160;&#160;&#160;&#160;&#160;',
							'<span class="gi">|&mdash;</span>',
							str_replace( '<sup>|_</sup>&#160;', '', $_row->treename ) )
						);
				}
			}


			$response = $rows;
		}

		$this->plugin->setResponse( $response );
	}

	/**
	 * This is a modified method from the trash() method in:
	 * /admin/com_k2/models/categories.php
	 */
	public function delete()
	{
		$mainframe = &JFactory::getApplication();
		jimport('joomla.filesystem.file');

		JTable::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/tables' );

		$db = & JFactory::getDBO();
		$cid = JRequest::getVar('cid');
		JArrayHelper::toInteger($cid);
		$row = & JTable::getInstance('K2Category', 'Table');

		$warningItems = false;
		$warningChildren = false;
		$cid = array_reverse($cid);
		for ($i = 0; $i < sizeof($cid); $i++) {
			$row->load($cid[$i]);

			$query = "SELECT COUNT(*) FROM #__k2_items WHERE catid={$cid[$i]}";
			$db->setQuery($query);
			$num = $db->loadResult();

			if ($num > 0 ){
				$warningItems = true;
			}

			$query = "SELECT COUNT(*) FROM #__k2_categories WHERE parent={$cid[$i]}";
			$db->setQuery($query);
			$children = $db->loadResult();

			if ($children > 0) {
				$warningChildren = true;
			}

			if ($children==0 && $num==0){

				if ($row->image) {
					JFile::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'categories'.DS.$row->image);
				}
				$row->delete($cid[$i]);

			}
		}
		$cache = & JFactory::getCache('com_k2');
		$cache->clean();

		if ( $warningItems ){
			$response = $this->getErrorResponse( 400, JText::_(
				'PLG_JM_K2_CATEGORY_FAIL_DELETE_ITEMS') );
		} elseif ( $warningChildren ) {
			$response = $this->getErrorResponse( 400, JText::_(
				'PLG_JM_K2_CATEGORY_FAIL_DELETE_CATEGORIES') );
		} else {
			$response = $this->getSuccessResponse( 200, JText::_('PLG_JM_K2_CATEGORY_DELETED') );
		}

		$this->plugin->setResponse( $response );
	}

	/**
	 * A copy of the getData method on this file:
	 * /administrator/components/com_k2/models/categories.php
	 * Some modifications have been made
	 */
	function getCategories()
	{

		$mainframe = &JFactory::getApplication();
		$option = JRequest::getCmd('option');
		$view = JRequest::getCmd('view');
		$db = & JFactory::getDBO();
		$limit = $mainframe->getUserStateFromRequest('global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
		$limitstart = $mainframe->getUserStateFromRequest($option.$view.'.limitstart', 'limitstart', 0, 'int');
		$search = $mainframe->getUserStateFromRequest($option.$view.'search', 'search', '', 'string');
		$search = JString::strtolower($search);
		$filter_order = $mainframe->getUserStateFromRequest($option.$view.'filter_order', 'filter_order', 'c.ordering', 'cmd');
		$filter_order_Dir = $mainframe->getUserStateFromRequest($option.$view.'filter_order_Dir', 'filter_order_Dir', '', 'word');
		$filter_trash = $mainframe->getUserStateFromRequest($option.$view.'filter_trash', 'filter_trash', 0, 'int');
		$filter_state = $mainframe->getUserStateFromRequest($option.$view.'filter_state', 'filter_state', -1, 'int');
		$language = $mainframe->getUserStateFromRequest($option.$view.'language', 'language', '', 'string');

		$query = "SELECT c.*, g.name AS groupname, exfg.name as extra_fields_group FROM #__k2_categories as c LEFT JOIN #__groups AS g ON g.id = c.access LEFT JOIN #__k2_extra_fields_groups AS exfg ON exfg.id = c.extraFieldsGroup WHERE c.id>0";

		if (!$filter_trash){
			$query .= " AND c.trash=0";
		}

		if ($search) {
			$query .= " AND LOWER( c.name ) LIKE ".$db->Quote('%'.$db->getEscaped($search, true).'%', false);
		}

		if ($filter_state > -1) {
			$query .= " AND c.published={$filter_state}";
		}
		if ($language) {
			$query .= " AND c.language = ".$db->Quote($language);
		}

		$query .= " ORDER BY {$filter_order} {$filter_order_Dir}";

		if(K2_JVERSION=='16'){
			$query = JString::str_ireplace('#__groups', '#__viewlevels', $query);
			$query = JString::str_ireplace('g.name AS groupname', 'g.title AS groupname', $query);
		}

		$db->setQuery($query);
		$rows = $db->loadObjectList();
		if(K2_JVERSION=='16'){
			foreach($rows as $row){
				$row->parent_id = $row->parent;
				$row->title = $row->name;
			}
		}
		$categories = array();

		if ($search) {
			foreach ($rows as $row) {
				$row->treename = $row->name;
				$categories[]=$row;
			}

		}
		else {
			$categories = $this->indentRows($rows);
		}
		if (isset($categories)){
			$total = count($categories);
		}
		else {
			$total = 0;
		}
		$categories = @array_slice($categories, $limitstart, $limit);
		foreach($categories as $category) {
			$category->parameters = new JParameter($category->params);
			if($category->parameters->get('inheritFrom')) {
				$db->setQuery("SELECT name FROM #__k2_categories WHERE id = ".(int)$category->parameters->get('inheritFrom'));
				$category->inheritFrom = $db->loadResult();
			}
			else {
				$category->inheritFrom = '';
			}
		}
		return $categories;
	}

	function indentRows( & $rows) {
		$children = array ();
		if(count($rows)){
			foreach ($rows as $v) {
				$pt = $v->parent;
				$list = @$children[$pt]?$children[$pt]: array ();
				array_push($list, $v);
				$children[$pt] = $list;
			}
		}
				
		$categories = JHTML::_('menu.treerecurse', 0, '', array (), $children);
		return $categories;
	}
}