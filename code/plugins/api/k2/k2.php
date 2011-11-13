<?php
/**
 * @package	JM
 * @version 1.5
 * @author 	Rafael Corral
 * @link 	http://www.rafaelcorral.com/
 * @copyright Copyright (C) 2011 Edge Web Works, LLC. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

class plgJMK2 extends ApiPlugin
{
	public function __construct( &$subject, $config )
	{
		parent::__construct( $subject, $config );
		$this->loadLanguage();

		ApiResource::addIncludePath( JPATH_PLUGINS . '/jm/k2/resources' );
	}

	public function register_api_plugin()
	{
		return parent::register_api_plugin( array(
			// Title as you want it to appear on the extensions list
			'title' => 'K2'
			));
	}
}