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

class K2ApiResourceCategories extends ApiResource
{
	public function get()
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_k2/models/categories.php';

		$categoriesModel = &JModel::getInstance( 'Categories', 'K2Model' );

		if ( JRequest::getVar('tree') ) {
			$response = $categoriesModel->categoriesTree(null, true, true);
		}

		$this->plugin->setResponse( $response );
	}
}