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

class K2ApiResourceItemForm extends ApiResource
{
	public function get()
	{
		jimport( 'joomla.form.form' );

		JFactory::getLanguage()->load( 'com_k2', JPATH_ADMINISTRATOR );

		$form = JForm::getInstance( 'itemForm', JPATH_ADMINISTRATOR
			. '/components/com_k2/models/item.xml' );

		$html = '';
		foreach ( $form->getFieldset( JRequest::getVar('fieldset') ) as $field ) {
			// These two types are K2 specific, so I am skipping these two types
			if( in_array( $field->type, array( 'header', 'Spacer' ) ) ){
				continue;
			} else {
				$html .= '<div data-role="fieldcontain">'
					. $field->label
					. $field->input
					. '</div>';
			}
		}

		$this->plugin->setResponse( array( 'html' => $html ) );
	}
}