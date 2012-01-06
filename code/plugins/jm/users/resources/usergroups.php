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

class UsersJMResourceUserGroups extends JMResource
{
	public function get()
	{
		// Set variables to be used
		JMHelper::setSessionUser();

		JModel::addIncludePath( JPATH_ADMINISTRATOR.'/components/com_users/models' );

		$model = JModel::getInstance('User', 'UsersModel');
		$grouplist = $model->getGroups();
		$groups = $model->getAssignedGroups( JRequest::getInt('user_id') );

		if ( !$grouplist ) {
			$html = '';
		} else {
			JHtml::addIncludePath( JPATH_ROOT . '/components/com_users/helpers/html' );
			$html = JHtml::_( 'access.usergroups', 'jform[groups]', $groups, true );
		}

		$this->plugin->setResponse( array( 'html' => $html ) );
	}
}