<?php
/**
 * @package	JM
 * @version 0.2
 * @author 	Rafael Corral
 * @link 	http://jommobile.com
 * @copyright Copyright (C) 2012 Rafael Corral. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class APIJMResourceExtensions extends JMResource
{
	public function get()
	{
		$app = JFactory::getApplication();

		jimport('joomla.filesystem.folder');
		JPluginHelper::importPlugin('jm');

		$result = $app->triggerEvent( 'register_api_plugin' );

		$plugins = array();
		foreach ( $result as $plugin ) {
			if ( !isset( $plugin->title ) || !isset( $plugin->plugin ) ) {
				continue;
			}

			$path = JFolder::makeSafe( JPATH_ROOT . "/plugins/jm/{$plugin->plugin}/html" );
			if ( !JFolder::exists( $path ) ) {
				continue;
			}

			$files = JFolder::files( $path, '.', true, true );

			// Remove absolute path
			foreach ( $files as &$file ) {
				$file = str_replace( JPATH_ROOT.'/', '', $file );
			}

			$plugins[$plugin->plugin] = array(
				'title' => $plugin->title,
				'type' => $plugin->plugin,
				'version' => $plugin->version,
				'files' => $files
				);
		}

		ksort( $plugins );

		$this->plugin->setResponse( $plugins );
	}
}