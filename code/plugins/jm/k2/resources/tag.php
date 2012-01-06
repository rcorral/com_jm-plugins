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

class K2JMResourceTag extends JMResource
{
	public function get()
	{
		JModel::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/models' );
		JTable::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/tables' );

		$model = JModel::getInstance( 'tag', 'K2Model' );
		$tag = (object) $model->getData()->getProperties();

		$this->plugin->setResponse( $tag );
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

		$row = & JTable::getInstance('K2Tag', 'Table');

		if ( !$row->bind( JRequest::get('post') ) || !$row->check() || !$row->store() ) {
			$response = $this->getErrorResponse( 400, $row->getError() );
		} else {
			$response = $this->getSuccessResponse( 201, JText::_('COM_JM_SUCCESS') );
			$response->id = $row->id;

			$cache = & JFactory::getCache('com_k2');
			$cache->clean();
		}

		$this->plugin->setResponse( $response );
	}

	public function put()
	{
		// Simply call post as K2 will just save a tag with an id
		$this->post();

		$response = $this->plugin->get( 'response' );
		if ( isset( $response->success ) && $response->success ) {
			JResponse::setHeader( 'status', 200, true );
			$response->code = 200;
			$this->plugin->setResponse( $response );
		}
	}
}