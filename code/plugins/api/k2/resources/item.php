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

class K2JMResourceItem extends JMResource
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

	/**
	 * This is not the best example to follow
	 * Please see the category plugin for a better example
	 */
	public function post()
	{
		// Set variables to be used
		JMHelper::setSessionUser();

		// Include dependencies
		jimport('joomla.database.table');
		$language = JFactory::getLanguage();
		$language->load('joomla', JPATH_ADMINISTRATOR);
		$language->load('com_k2', JPATH_ADMINISTRATOR);

		require_once JPATH_ADMINISTRATOR . '/components/com_k2/models/item.php';
		JTable::addIncludePath( JPATH_ADMINISTRATOR . '/components/com_k2/tables' );

		// Fake parameters
		$_REQUEST[JUtility::getToken()] = 1;
		$_POST[JUtility::getToken()] = 1;

		// Clear userstate just in case
		$row = $this->save();

		if ( $this->getError() ) {
			$response = $this->getErrorResponse( 400, $this->getError() );
		} elseif ( !$row->id ) {
			$response = $this->getErrorResponse( 400, JText::_('COM_JM_ERROR_OCURRED') );
		} else {
			$response = $this->getSuccessResponse( 201, JText::_('COM_JM_SUCCESS') );
			// Get the ID of the category that was modified or inserted
			$response->id = $row->id;
		}

		$this->plugin->setResponse( $response );
	}

	public function put()
	{
		// Simply call post as K2 will just save an item with an id
		$this->post();

		$response = $this->plugin->get( 'response' );
		if ( isset( $response->success ) && $response->success ) {
			JResponse::setHeader( 'status', 200, true );
			$response->code = 200;
			$this->plugin->setResponse( $response );
		}
	}

	/**
	 * This method is copied from admin/com_k2/models/item.php 
	 * Modifications have been made
	 * Changed all $mainframe redirects
	 * Removed the check for extra fields, we don't want to override them
	 */
	function save($front = false) {

		$mainframe = &JFactory::getApplication();
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.archive');
		require_once (JPATH_ADMINISTRATOR.'/components/com_k2'.DS.'lib'.DS.'class.upload.php');
		$db = &JFactory::getDBO();
		$user = &JFactory::getUser();
		$row = &JTable::getInstance('K2Item', 'Table');
		$params = &JComponentHelper::getParams('com_k2');
		$nullDate = $db->getNullDate();

		if (!$row->bind(JRequest::get('post'))) {
			$this->setError( $row->getError() );
			return false;
		}

		if ($front && $row->id == NULL) {
			JLoader::register('K2HelperPermissions', JPATH_SITE.DS.'components'.DS.'com_k2'.DS.'helpers'.DS.'permissions.php');
			if (!K2HelperPermissions::canAddItem($row->catid)) {
				$this->setError( JText::_('K2_YOU_ARE_NOT_ALLOWED_TO_POST_TO_THIS_CATEGORY_SAVE_FAILED') );
				return false;
			}
		}

		($row->id) ? $isNew = false : $isNew = true;


		if ($params->get('mergeEditors')) {
			$text = JRequest::getVar('text', '', 'post', 'string', 2);
			if($params->get('xssFiltering')){
				$filter = new JFilterInput(array(), array(), 1, 1, 0);
				$text = $filter->clean( $text );
			}
			$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
			$tagPos = preg_match($pattern, $text);
			if ($tagPos == 0) {
				$row->introtext = $text;
				$row->fulltext = '';
			} else
			list($row->introtext, $row->fulltext) = preg_split($pattern, $text, 2);
		} else {
			$row->introtext = JRequest::getVar('introtext', '', 'post', 'string', 2);
			$row->fulltext = JRequest::getVar('fulltext', '', 'post', 'string', 2);
			if($params->get('xssFiltering')){
				$filter = new JFilterInput(array(), array(), 1, 1, 0);
				$row->introtext = $filter->clean( $row->introtext );
				$row->fulltext = $filter->clean( $row->fulltext );
			}
		}

		if ($row->id) {
			$datenow = &JFactory::getDate();
			$row->modified = $datenow->toMySQL();
			$row->modified_by = $user->get('id');
		} else {
			$row->ordering = $row->getNextOrder("catid = {$row->catid} AND trash = 0");
			if ($row->featured)
			$row->featured_ordering = $row->getNextOrder("featured = 1 AND trash = 0", 'featured_ordering');
		}
		
		$row->created_by = $row->created_by ? $row->created_by : $user->get('id');

		if ($front) {
			$K2Permissions = &K2Permissions::getInstance();
	        if (!$K2Permissions->permissions->get('editAll')) {
	    		$row->created_by = $user->get('id');
	    	}
		} 
		
		if ($row->created && strlen(trim($row->created)) <= 10) {
			$row->created .= ' 00:00:00';
		}

		$config = &JFactory::getConfig();
		$tzoffset = $config->getValue('config.offset');
		$date = &JFactory::getDate($row->created, $tzoffset);
		$row->created = $date->toMySQL();

		if (strlen(trim($row->publish_up)) <= 10) {
			$row->publish_up .= ' 00:00:00';
		}

		$date = &JFactory::getDate($row->publish_up, $tzoffset);
		$row->publish_up = $date->toMySQL();

		if (trim($row->publish_down) == JText::_('K2_NEVER') || trim($row->publish_down) == '') {
			$row->publish_down = $nullDate;
		} else {
			if (strlen(trim($row->publish_down)) <= 10) {
				$row->publish_down .= ' 00:00:00';
			}
			$date = &JFactory::getDate($row->publish_down, $tzoffset);
			$row->publish_down = $date->toMySQL();
		}

		$metadata = JRequest::getVar('meta', null, 'post', 'array');
		if (is_array($metadata)) {
			$txt = array();
			foreach ($metadata as $k=>$v) {
				if ($k == 'description') {
					$row->metadesc = $v;
				} elseif ($k == 'keywords') {
					$row->metakey = $v;
				} else {
					$txt[] = "$k=$v";
				}
			}
			$row->metadata = implode("\n", $txt);
		}

		if (!$row->check()) {
			$mainframe->redirect('index.php?option=com_k2&view=item&cid='.$row->id, $row->getError(), 'error');
		}

		$dispatcher = &JDispatcher::getInstance();
		JPluginHelper::importPlugin('k2');
		$result = $dispatcher->trigger('onBeforeK2Save', array(&$row, $isNew));
		if (in_array(false, $result, true)) {
			$this->setError( $row->getError() );
			return false;
		}

		// JoomFish! Front-end editing compatibility
		if($mainframe->isSite() && JFolder::exists(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_joomfish'.DS.'contentelements')) {
			if (version_compare(phpversion(), '5.0') < 0) {
				$tmpRow = $row;
			}
			else {
				$tmpRow = clone($row);
			}
		}

		if (!$row->store()) {
			$this->setError( $row->getError() );
			return false;
		}
		
		// JoomFish! Front-end editing compatibility
		if($mainframe->isSite() && JFolder::exists(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_joomfish'.DS.'contentelements')) {
			$itemID = $row->id;
			$row = $tmpRow;
			$row->id = $itemID;
		}

		if(!$params->get('disableCompactOrdering')) {
			$row->reorder("catid = {$row->catid} AND trash = 0");
		}
		if ($row->featured && !$params->get('disableCompactOrdering')) {
			$row->reorder("featured = 1 AND trash = 0", 'featured_ordering');
		}
		$files = JRequest::get('files');

		//Image
		if((int)$params->get('imageMemoryLimit')) {
			ini_set('memory_limit', (int)$params->get('imageMemoryLimit').'M');
		}
		$existingImage = JRequest::getVar('existingImage');
		if ( (@$files['image']['error'] === 0 || $existingImage) && !JRequest::getBool('del_image')) {

			if($files['image']['error'] === 0){
				$image = $files['image'];
			}
			else{
				$image = JPATH_SITE.DS.JPath::clean($existingImage);
			}


			$handle = new Upload($image);
			$handle->allowed = array('image/*');

			if ($handle->uploaded) {

				//Image params
				$category = &JTable::getInstance('K2Category', 'Table');
				$category->load($row->catid);
				$cparams = new JParameter($category->params);

				if ($cparams->get('inheritFrom')) {
					$masterCategoryID = $cparams->get('inheritFrom');
					$query = "SELECT * FROM #__k2_categories WHERE id=".(int)$masterCategoryID;
					$db->setQuery($query, 0, 1);
					$masterCategory = $db->loadObject();
					$cparams = new JParameter($masterCategory->params);
				}

				$params->merge($cparams);

				//Original image
				$savepath = JPATH_SITE.DS.'media'.DS.'k2'.DS.'items'.DS.'src';
				$handle->image_convert = 'jpg';
				$handle->jpeg_quality = 100;
				$handle->file_auto_rename = false;
				$handle->file_overwrite = true;
				$handle->file_new_name_body = md5("Image".$row->id);
				$handle->Process($savepath);

				$filename = $handle->file_dst_name_body;
				$savepath = JPATH_SITE.DS.'media'.DS.'k2'.DS.'items'.DS.'cache';

				//XLarge image
				$handle->image_resize = true;
				$handle->image_ratio_y = true;
				$handle->image_convert = 'jpg';
				$handle->jpeg_quality = $params->get('imagesQuality');
				$handle->file_auto_rename = false;
				$handle->file_overwrite = true;
				$handle->file_new_name_body = $filename.'_XL';
				if (JRequest::getInt('itemImageXL')) {
					$imageWidth = JRequest::getInt('itemImageXL');
				} else {
					$imageWidth = $params->get('itemImageXL', '800');
				}
				$handle->image_x = $imageWidth;
				$handle->Process($savepath);

				//Large image
				$handle->image_resize = true;
				$handle->image_ratio_y = true;
				$handle->image_convert = 'jpg';
				$handle->jpeg_quality = $params->get('imagesQuality');
				$handle->file_auto_rename = false;
				$handle->file_overwrite = true;
				$handle->file_new_name_body = $filename.'_L';
				if (JRequest::getInt('itemImageL')) {
					$imageWidth = JRequest::getInt('itemImageL');
				} else {
					$imageWidth = $params->get('itemImageL', '600');
				}
				$handle->image_x = $imageWidth;
				$handle->Process($savepath);

				//Medium image
				$handle->image_resize = true;
				$handle->image_ratio_y = true;
				$handle->image_convert = 'jpg';
				$handle->jpeg_quality = $params->get('imagesQuality');
				$handle->file_auto_rename = false;
				$handle->file_overwrite = true;
				$handle->file_new_name_body = $filename.'_M';
				if (JRequest::getInt('itemImageM')) {
					$imageWidth = JRequest::getInt('itemImageM');
				} else {
					$imageWidth = $params->get('itemImageM', '400');
				}
				$handle->image_x = $imageWidth;
				$handle->Process($savepath);

				//Small image
				$handle->image_resize = true;
				$handle->image_ratio_y = true;
				$handle->image_convert = 'jpg';
				$handle->jpeg_quality = $params->get('imagesQuality');
				$handle->file_auto_rename = false;
				$handle->file_overwrite = true;
				$handle->file_new_name_body = $filename.'_S';
				if (JRequest::getInt('itemImageS')) {
					$imageWidth = JRequest::getInt('itemImageS');
				} else {
					$imageWidth = $params->get('itemImageS', '200');
				}
				$handle->image_x = $imageWidth;
				$handle->Process($savepath);

				//XSmall image
				$handle->image_resize = true;
				$handle->image_ratio_y = true;
				$handle->image_convert = 'jpg';
				$handle->jpeg_quality = $params->get('imagesQuality');
				$handle->file_auto_rename = false;
				$handle->file_overwrite = true;
				$handle->file_new_name_body = $filename.'_XS';
				if (JRequest::getInt('itemImageXS')) {
					$imageWidth = JRequest::getInt('itemImageXS');
				} else {
					$imageWidth = $params->get('itemImageXS', '100');
				}
				$handle->image_x = $imageWidth;
				$handle->Process($savepath);

				//Generic image
				$handle->image_resize = true;
				$handle->image_ratio_y = true;
				$handle->image_convert = 'jpg';
				$handle->jpeg_quality = $params->get('imagesQuality');
				$handle->file_auto_rename = false;
				$handle->file_overwrite = true;
				$handle->file_new_name_body = $filename.'_Generic';
				$imageWidth = $params->get('itemImageGeneric', '300');
				$handle->image_x = $imageWidth;
				$handle->Process($savepath);

				if($files['image']['error'] === 0)
				$handle->Clean();

			} else {
				$this->setError( $handle->error );
				return false;
			}

		}

		if (JRequest::getBool('del_image')) {

			$current = &JTable::getInstance('K2Item', 'Table');
			$current->load($row->id);
			$filename = md5("Image".$current->id);

			if (JFile::exists(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'src'.DS.$filename.'.jpg')) {
				JFile::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'src'.DS.$filename.'.jpg');
			}

			if (JFile::exists(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_XS.jpg')) {
				JFile::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_XS.jpg');
			}

			if (JFile::exists(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_S.jpg')) {
				JFile::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_S.jpg');
			}

			if (JFile::exists(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_M.jpg')) {
				JFile::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_M.jpg');
			}

			if (JFile::exists(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_L.jpg')) {
				JFile::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_L.jpg');
			}

			if (JFile::exists(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_XL.jpg')) {
				JFile::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_XL.jpg');
			}

			if (JFile::exists(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_Generic.jpg')) {
				JFile::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.$filename.'_Generic.jpg');
			}

			$row->image_caption = '';
			$row->image_credits = '';

		}

		//Attachments
		$attachments = JRequest::getVar('attachment_file', NULL, 'FILES', 'array');
		$attachments_names = JRequest::getVar('attachment_name', '', 'POST', 'array');
		$attachments_titles = JRequest::getVar('attachment_title', '', 'POST', 'array');
		$attachments_title_attributes = JRequest::getVar('attachment_title_attribute', '', 'POST', 'array');
		$attachments_existing_files = JRequest::getVar('attachment_existing_file', '', 'POST', 'array');

		$attachmentFiles = array();

		if (count($attachments)) {

			foreach ($attachments as $k=>$l) {
				foreach ($l as $i=>$v) {
					if (!array_key_exists($i, $attachmentFiles))
					$attachmentFiles[$i] = array();
					$attachmentFiles[$i][$k] = $v;
				}

			}

			$path = $params->get('attachmentsFolder', NULL);
			if (is_null($path)) {
				$savepath = JPATH_ROOT.DS.'media'.DS.'k2'.DS.'attachments';
			} else {
				$savepath = $path;
			}

			$counter = 0;

			foreach ($attachmentFiles as $key=>$file) {
				 
				if($file["tmp_name"] || $attachments_existing_files[$key]){
					 
					if($attachments_existing_files[$key]){
						$file = JPATH_SITE.DS.JPath::clean($attachments_existing_files[$key]);
					}

					$handle = new Upload($file);

					if ($handle->uploaded) {
						$handle->file_auto_rename = true;
						$handle->allowed[] = 'application/x-zip';
						$handle->allowed[] = 'application/download';
						$handle->Process($savepath);
						$filename = $handle->file_dst_name;
						$handle->Clean();
						$attachment = &JTable::getInstance('K2Attachment', 'Table');
						$attachment->itemID = $row->id;
						$attachment->filename = $filename;
						$attachment->title = ( empty($attachments_titles[$counter])) ? $filename : $attachments_titles[$counter];
						$attachment->titleAttribute = ( empty($attachments_title_attributes[$counter])) ? $filename : $attachments_title_attributes[$counter];
						$attachment->store();
					} else {
						$this->setError( $handle->error );
						return false;
					}
				}


				$counter++;
			}

		}

		//Gallery
		$flickrGallery = JRequest::getVar('flickrGallery');
		if($flickrGallery) {
			$row->gallery = '{gallery}'.$flickrGallery.'{/gallery}';
		}

		if (isset($files['gallery']) && $files['gallery']['error'] == 0 && !JRequest::getBool('del_gallery')) {
			$handle = new Upload($files['gallery']);
			$handle->file_auto_rename = true;
			$savepath = JPATH_ROOT.DS.'media'.DS.'k2'.DS.'galleries';
			$handle->allowed = array("application/download", "application/rar", "application/x-rar-compressed", "application/arj", "application/gnutar", "application/x-bzip", "application/x-bzip2", "application/x-compressed", "application/x-gzip", "application/x-zip-compressed", "application/zip", "multipart/x-zip", "multipart/x-gzip", "application/x-unknown", "application/x-zip");

			if ($handle->uploaded) {

				$handle->Process($savepath);
				$handle->Clean();

				if (JFolder::exists($savepath.DS.$row->id)) {
					JFolder::delete($savepath.DS.$row->id);
				}

				if (!JArchive::extract($savepath.DS.$handle->file_dst_name, $savepath.DS.$row->id)) {
					$this->setError( JText::_('K2_GALLERY_UPLOAD_ERROR_CANNOT_EXTRACT_ARCHIVE') );
					return false;
				} else {
					$row->gallery = '{gallery}'.$row->id.'{/gallery}';
				}
				JFile::delete($savepath.DS.$handle->file_dst_name);
				$handle->Clean();

			} else {
				$this->setError( $handle->error );
				return false;
			}
		}


		if (JRequest::getBool('del_gallery')) {

			$current = &JTable::getInstance('K2Item', 'Table');
			$current->load($row->id);

			if (JFolder::exists(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'galleries'.DS.$current->id)) {
				JFolder::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'galleries'.DS.$current->id);
			}
			$row->gallery = '';
		}



		//Video
		if (!JRequest::getBool('del_video')) {
			if (isset($files['video']) && $files['video']['error'] == 0) {

				$videoExtensions = array("flv", "mp4", "ogv", "webm", "f4v", "m4v", "3gp", "3g2", "mov", "mpeg", "mpg", "avi", "wmv", "divx");
				$audioExtensions = array("mp3", "aac", "m4a", "ogg", "wma");
				$validExtensions = array_merge($videoExtensions, $audioExtensions);
				$filetype = JFile::getExt($files['video']['name']);

				if (!in_array($filetype, $validExtensions)) {
					$this->setError( JText::_('K2_INVALID_VIDEO_FILE') );
					return false;
				}

				if (in_array($filetype, $videoExtensions)) {
					$savepath = JPATH_ROOT.DS.'media'.DS.'k2'.DS.'videos';
				}
				else {
					$savepath = JPATH_ROOT.DS.'media'.DS.'k2'.DS.'audio';
				}
				
				$filename = JFile::stripExt($files['video']['name']);

				JFile::upload($files['video']['tmp_name'], $savepath.DS.$row->id.'.'.$filetype);
				$filetype = JFile::getExt($files['video']['name']);
				$row->video = '{'.$filetype.'}'.$row->id.'{/'.$filetype.'}';

			} else {

				if (JRequest::getVar('remoteVideo')) {
					$fileurl = JRequest::getVar('remoteVideo');
					$filetype = JFile::getExt($fileurl);
					$row->video = '{'.$filetype.'remote}'.$fileurl.'{/'.$filetype.'remote}';
				}

				if (JRequest::getVar('videoID')) {
					$provider = JRequest::getWord('videoProvider');
					$videoID = JRequest::getVar('videoID');
					$row->video = '{'.$provider.'}'.$videoID.'{/'.$provider.'}';
				}

				if (JRequest::getVar('embedVideo', '', 'post', 'string', JREQUEST_ALLOWRAW)) {
					$row->video = JRequest::getVar('embedVideo', '', 'post', 'string', JREQUEST_ALLOWRAW);
				}

			}

		} else {

			$current = &JTable::getInstance('K2Item', 'Table');
			$current->load($row->id);

			preg_match_all("#^{(.*?)}(.*?){#", $current->video, $matches, PREG_PATTERN_ORDER);
			$videotype = $matches[1][0];
			$videofile = $matches[2][0];

			if (in_array($videotype, $videoExtensions)) {
				if (JFile::exists(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'videos'.DS.$videofile.'.'.$videotype))
				JFile::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'videos'.DS.$videofile.'.'.$videotype);
			}
			
			if (in_array($videotype, $audioExtensions)) {
				if (JFile::exists(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'audio'.DS.$videofile.'.'.$videotype))
				JFile::delete(JPATH_ROOT.DS.'media'.DS.'k2'.DS.'audio'.DS.$videofile.'.'.$videotype);
			}

			$row->video = '';
			$row->video_caption = '';
			$row->video_credits = '';
		}

		//Tags
		if(@$user->gid<24 && $params->get('lockTags'))
		$params->set('taggingSystem',0);
		$db = &JFactory::getDBO();
		$query = "DELETE FROM #__k2_tags_xref WHERE itemID={intval($row->id)}";
		$db->setQuery($query);
		$db->query();

		if($params->get('taggingSystem')){

			if(@$user->gid<24 && $params->get('lockTags'))
			JError::raiseError(403, JText::_('K2_ALERTNOTAUTH'));

			$tags = JRequest::getVar('tags', NULL, 'POST', 'array');
			if (count($tags)) {
				$tags = array_unique($tags);
				foreach ($tags as $tag) {
					$tag = str_replace('-','',$tag);
					$query = "SELECT id FROM #__k2_tags WHERE name=".$db->Quote($tag);
					$db->setQuery($query);
					$tagID = $db->loadResult();
					if($tagID){
						$query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, {intval($tagID)}, {intval($row->id)})";
						$db->setQuery($query);
						$db->query();
					}
					else {
						$K2Tag = &JTable::getInstance('K2Tag', 'Table');
						$K2Tag->name = $tag;
						$K2Tag->published = 1;
						$K2Tag->check();
						$K2Tag->store();
						$query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, {intval($K2Tag->id)}, {intval($row->id)})";
						$db->setQuery($query);
						$db->query();
					}
				}
			}

		}
		else {
			$tags = JRequest::getVar('selectedTags', NULL, 'POST', 'array');
			if (count($tags)) {
				foreach ($tags as $tagID) {
					$query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, {intval($tagID)}, {intval($row->id)})";
					$db->setQuery($query);
					$db->query();
				}
			}

		}

		if ($front) {
			if (!K2HelperPermissions::canPublishItem($row->catid) && $row->published) {
				$row->published = 0;
				$mainframe->enqueueMessage(JText::_('K2_YOU_DONT_HAVE_THE_PERMISSION_TO_PUBLISH_ITEMS'), 'notice');
			}
		}

		if (!$row->store()) {
			$this->setError( $row->getError() );
			return false;
		}

		$cache = &JFactory::getCache('com_k2');
		$cache->clean();

		$dispatcher->trigger('onAfterK2Save', array(&$row, $isNew));

		return $row;
	}
}