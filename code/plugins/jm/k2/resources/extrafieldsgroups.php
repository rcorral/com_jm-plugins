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

class K2JMResourceExtraFieldsGroups extends JMResource
{
	public function get()
	{
		JModel::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/models' );
		$extraFieldsModel = JModel::getInstance( 'ExtraFields', 'K2Model' );
		$groups = $extraFieldsModel->getGroups();

		$this->plugin->setResponse( $groups );
	}
}