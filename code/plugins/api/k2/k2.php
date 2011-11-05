<?php
/**
 * @package	API
 * @version 1.5
 * @author 	Rafael Corral
 * @link 	http://www.rafaelcorral.com/
 * @copyright Copyright (C) 2011 Edge Web Works, LLC. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

class plgAPIK2 extends ApiPlugin
{
	public function __construct()
	{
		parent::__construct();
		$this->loadLanguage();

		ApiResource::addIncludePath( JPATH_PLUGINS . '/api/k2/resources' );
	}
}