<?php
/**
 * @package	K2 JM plugin
 * @version 1.0
 * @author 	Rafael Corral
 * @link 	http://jommobile.com
 * @copyright Copyright (C) 2012 Rafael Corral. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class K2JMResourceCategory extends JMResource
{
	public function get()
	{
		JModel::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/models' );
		JTable::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/tables' );

		$model = JModel::getInstance( 'category', 'K2Model' );
		$category = (object) $model->getData()->getProperties();

		if ( JRequest::getVar( 'formready', 1 ) ) {
			$category->params = json_decode( $category->params );
		}

		$this->plugin->setResponse( $category );
	}

	/**
	 * This is not the best example to follow
	 * Please see the category plugin for a better example
	 */
	public function post()
	{
		// Set variables to be used
		JMHelper::setSessionUser();

		// Include dependencies
		jimport('joomla.database.table');
		$language = JFactory::getLanguage();
		$language->load('joomla', JPATH_ADMINISTRATOR);
		$language->load('com_k2', JPATH_ADMINISTRATOR);

		JTable::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/tables' );

		// Clear userstate just in case
		$row = $this->save();

		if ( $this->getError() ) {
			$response = $this->getErrorResponse( 400, $this->getError() );
		} elseif ( !$row->id ) {
			$response = $this->getErrorResponse( 400, JText::_('COM_JM_ERROR_OCURRED') );
		} else {
			$response = $this->getSuccessResponse( 201, JText::_('COM_JM_SUCCESS') );
			// Get the ID of the category that was modified or inserted
			$response->id = $row->id;
		}

		$this->plugin->setResponse( $response );
	}

	public function put()
	{
		// Simply call post as K2 will just save an item with an id
		$this->post();

		$response = $this->plugin->get( 'response' );
		if ( isset( $response->success ) && $response->success ) {
			JResponse::setHeader( 'status', 200, true );
			$response->code = 200;
			$this->plugin->setResponse( $response );
		}
	}

	/**
	 * This method is copied from admin/com_k2/models/category.php 
	 * Modifications have been made
	 * Changed all $mainframe redirects
	 */
	function save() {

		$mainframe = &JFactory::getApplication();
		jimport('joomla.filesystem.file');
		require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_k2'.DS.'lib'.DS.'class.upload.php');
		$row = & JTable::getInstance('K2Category', 'Table');
		$params = & JComponentHelper::getParams('com_k2');
		if (!$row->bind(JRequest::get('post'))) {
			$this->setError( $row->getError() );
			return false;
		}

		$row->description = JRequest::getVar('description', '', 'post', 'string', 2);
		if($params->get('xssFiltering')){
			$filter = new JFilterInput(array(), array(), 1, 1, 0);
			$row->description = $filter->clean( $row->description );
		}

		if (!$row->id) {
			$row->ordering = $row->getNextOrder('parent = '.$row->parent.' AND trash=0');
		}

		if (!$row->check()) {
			$this->setError( $row->getError() );
			return false;
		}

		if (!$row->store()) {
			$this->setError( $row->getError() );
			return false;
		}

		if(!$params->get('disableCompactOrdering'))
		$row->reorder('parent = '.$row->parent.' AND trash=0');

		
		if((int)$params->get('imageMemoryLimit')) {
			ini_set('memory_limit', (int)$params->get('imageMemoryLimit').'M');
		}
		
		$files = JRequest::get('files');

		$savepath = JPATH_ROOT.DS.'media'.DS.'k2'.DS.'categories'.DS;

		$existingImage = JRequest::getVar('existingImage');
		if ( (@$files['image']['error'] === 0 || $existingImage) && !JRequest::getBool('del_image')) {
			if($files['image']['error'] === 0){
				$image = $files['image'];
			}
			else{
				$image = JPATH_SITE.DS.JPath::clean($existingImage);
			}
				
			$handle = new Upload($image);
			if ($handle->uploaded) {
				$handle->file_auto_rename = false;
				$handle->jpeg_quality = $params->get('imagesQuality', '85');
				$handle->file_overwrite = true;
				$handle->file_new_name_body = $row->id;
				$handle->image_resize = true;
				$handle->image_ratio_y = true;
				$handle->image_x = $params->get('catImageWidth', '100');
				$handle->Process($savepath);
				$handle->Clean();
			}
			else {
				$this->setError( $handle->error );
				return false;
			}
			$row->image = $handle->file_dst_name;
		}


		if (JRequest::getBool('del_image')) {
			$currentRow = & JTable::getInstance('K2Category', 'Table');
			$currentRow->load($row->id);
			if (JFile::exists(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'categories'.DS.$currentRow->image)) {
				JFile::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'categories'.DS.$currentRow->image);
			}
			$row->image = '';
		}

		if (!$row->store()) {
			$this->setError( $row->getError() );
			return false;
		}

		$cache = & JFactory::getCache('com_k2');
		$cache->clean();

		return $row;
	}
}