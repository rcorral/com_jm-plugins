<?php
/**
 * @package	JM
 * @version 1.5
 * @author 	Brian Edgerton
 * @link 	http://www.edgewebworks.com
 * @copyright Copyright (C) 2011 Edge Web Works, LLC. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
*/

defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

class CoreJMResourceTimezone extends JMResource
{
	public function get()
	{
		JMHelper::setSessionUser();

		$options = array();
		if ( JRequest::getVar( 'default', false ) ) {
			$options = array(
				(object) array( 'value' => '', 'text' => JText::_('JOPTION_USE_DEFAULT') ) );
		}

		$sites = JMHelper::getField( 'timezone', array(
			'name' => JRequest::getVar('field_name', ''),
			'id' => JRequest::getVar('field_id', ''),
			'_options' => $options
			));

		$this->plugin->setResponse( array( 'html' => $sites->input ) );
	}

	public function post()
	{
		$this->plugin->setResponse( 'here is a post request' );
	}
}