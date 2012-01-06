<?php
/**
 * @package	JM
 * @version 0.2
 * @author 	Rafael Corral
 * @link 	http://jommobile.com
 * @copyright Copyright (C) 2012 Rafael Corral. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
*/

defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

class UsersJMResourceUsers extends JMResource
{
	public function get()
	{
		require_once JPATH_ADMINISTRATOR.'/components/com_users/models/users.php';
		require_once JPATH_PLUGINS.'/jm/users/resources/helper.php';

		$model = JModel::getInstance('JMHelperModel', 'UsersModel');
		$model->_setCache('getstart', $model->getState('list.start'));
		$users = $model->getItems();

		if ( false === $users || ( empty( $users ) && $model->getError() ) ) {
			$response = $this->getErrorResponse( 400, $model->getError() );
		} else {
			$response = $users;
		}

		$this->plugin->setResponse( $response );
	}

	public function post()
	{
		$this->plugin->setResponse( 'here is a post request' );
	}

	public function delete( $id = null )
	{
		// Include dependencies
		jimport('joomla.database.table');

		JModel::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_users/models' );

		JFactory::getLanguage()->load('com_users', JPATH_ADMINISTRATOR);
		$model = JModel::getInstance('User', 'UsersModel');
		try {
			$success = $model->delete( JRequest::getVar( 'cid', array(), 'post', 'array' ) );
		} catch ( JException $e ) {
			$success = false;
			$model->setError( $e->getMessage() );
		}

		if ( $model->getError() ) {
			$response = $this->getErrorResponse( 400, $model->getError() );
		} elseif ( false === $success ) {
			$response = $this->getErrorResponse( 400, JText::_('COM_JM_ERROR_OCURRED') );
		} else {
			$response = $this->getSuccessResponse( 200, JText::_('COM_JM_SUCCESS') );
		}

		$this->plugin->setResponse( $response );
	}
}