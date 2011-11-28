<?php
/**
 * @package	JM
 * @version 1.5
 * @author 	Rafael Corral
 * @link 	http://www.rafaelcorral.com
 * @copyright Copyright (C) 2011 Rafael Corral. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
*/

defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

class UsersJMResourceUser extends JMResource
{
	public function get()
	{
		require_once JPATH_ADMINISTRATOR.'/components/com_users/models/user.php';

		$model = JModel::getInstance('User', 'UsersModel');
		$user = $model->getItem( JRequest::getInt('id') );

		if ( false === $user || ( empty( $user ) && $model->getError() ) ) {
			$response = $this->getErrorResponse( 400, $model->getError() );
		} else {
			// We don't care about the password, and for security reasons, don't send
			$user->password = '';
			$response = $user;
		}

		$this->plugin->setResponse( $response );
	}

	public function post()
	{
		$this->plugin->setResponse( 'here is a post request' );
	}
}