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

class K2JMResourceTags extends JMResource
{
	public function get()
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_k2/models/tags.php';

		$model = JModel::getInstance( 'tags', 'K2Model' );
		$tags = $model->getData();

		$this->plugin->setResponse( $tags );
	}

	/**
	 * This is a modified method from the remove() method in:
	 * /admin/com_k2/models/tags.php
	 */
	public function delete()
	{
		$db = &JFactory::getDBO();
		$cid = JRequest::getVar('cid');
		JArrayHelper::toInteger($cid);

		JTable::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/tables' );
		$row = & JTable::getInstance('K2Tag', 'Table');

		foreach ($cid as $id) {
			$row->load($id);
			$row->delete($id);
		}
		$cache = & JFactory::getCache('com_k2');
		$cache->clean();

		$response = $this->getSuccessResponse( 200, JText::_('COM_JM_SUCCESS') );

		$this->plugin->setResponse( $response );
	}
}