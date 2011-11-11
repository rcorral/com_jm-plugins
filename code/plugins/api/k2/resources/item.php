<?php
/**
 * @package	K2 API plugin
 * @version 1.0
 * @author 	Rafael Corral
 * @link 	http://www.rafaelcorral.com
 * @copyright Copyright (C) 2011 Rafael Corral. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class K2ApiResourceItem extends ApiResource
{
	public function get()
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_k2/models/item.php';
		JTable::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/tables' );

		$model = JModel::getInstance( 'item', 'K2Model' );
		$item = (object) $model->getData()->getProperties();

		if ( JRequest::getVar( 'formready', 1 ) ) {
			jimport('joomla.html.parameter');
			$meta = new JParameter( $item->metadata );
			$item->metadata = $meta->toObject();

			$item->params = json_decode( $item->params );

			$item->tags = $model->getCurrentTags( $item->id );
		}

		$this->plugin->setResponse( $item );
	}

}