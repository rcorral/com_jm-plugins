<?php
/**
 * @package	JM
 * @version 0.2
 * @author 	Rafael Corral
 * @link 	http://jommobile.com/
 * @copyright Copyright (C) 2012 Rafael Corral. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

class plgJMK2 extends JMPlugin
{
	public function __construct( &$subject, $config )
	{
		parent::__construct( $subject, $config );
		$this->loadLanguage();

		JMResource::addIncludePath( JPATH_PLUGINS . '/jm/k2/resources' );
	}

	public function register_api_plugin()
	{
		return parent::register_api_plugin( array(
			// Title as you want it to appear on the extensions list
			'title' => 'K2'
			));
	}
}