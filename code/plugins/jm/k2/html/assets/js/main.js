/**
 * jomMobile uses jQuery Mobile: http://jquerymobile.com/
 * To load page data you must hae events that trigger functions, to read more on events:
 * http://jquerymobile.com/demos/1.0rc3/docs/api/events.html
 * http://jquerymobile.com/demos/1.0rc3/docs/pages/
 */

/* Page Events */
jQuery('#page-k2-items').live('pageshow',function(event){
	try { // Wrap in try/catch in case there is a JS error, the app won't crash
	el = jQuery('#k2-items-list ul');
	if ( !el.html() ) {
		japp._start_loader();
		japp.load_k2_items();
	}
	} catch(e){japp._stop_loader();}
});

/**
 * This page has two listeners
 * 'pagebeforecreate' loads HTML fields before jQuery Mobile initializes all HTML fields
 * 'pageshow' gets triggered after the page has been initialized
 */
jQuery('#page-k2-item').live('pagebeforecreate',function(event){
	japp.load_k2_item_fields('item-view-options-listings');
	japp.load_k2_item_fields('item-view-options');
}).live('pageshow',function(event){
	try {
	// This only needs to be done for item pages, where you have a lot of form fields
	// Wrap everything in a function and trigger it later
	func = function() {
		// Get the item id
		id = _gup( 'id' );

		// Load data for dynamic dropdowns and other html elements
		categories = japp.get_k2_categories();
		access = japp.get_joomla_accesslevels();
		languages = japp.get_content_language();
		users = japp.get_joomla_users_list();

		// Populate dynamic html elements
		_populate_select( '#item-catid', categories, 'row.value', 'row.text' );
		_populate_select( '#item-access', access, 'row.value', 'row.text' );
		_populate_select( '#item-language', languages, 'row.value', 'row.text' );
		_populate_select( '#item-created-by', users, 'key', 'row.name',
			{ select_option: true, selected_value: japp.api_user.id });

		// New item
		if ( !id ) {
			// Stop loader and initialize all select menus
			japp._stop_loader();
			jQuery('#item-catid,#item-access,#item-language,#item-created-by').selectmenu();

			// Initialize the tags
			tags = japp.get_k2_tags();
		    jQuery("#item-tags").tokenInput(tags, {allowNewTokens: true, theme: "facebook",
		    	preventDuplicates: true, tokenValue: 'name',
		    	hintText: 'Type in a tag', tokenDelimiter: '|*|',
		    	extraclasses: 'token-input-list-facebook ui-input-text ui-body-c ui-corner-all ui-shadow-inset'});

			// Set page title
			jQuery('.page-title').html( 'New item' );

			return;
		}

		japp.load_k2_item( id );

		// Set page title
		jQuery('.page-title').html( 'Edit item' );

		jQuery('#item-delete').css('display', 'block');
	}

	// Start the loader
	japp._start_loader();
	// This needs to be triggered this way, otherwise the device doesn't like it
	setTimeout('func();', 250);
	} catch(e){japp._stop_loader();}
});

jQuery('#page-k2-tags').live('pageshow',function(event){
	try { // Wrap in try/catch in case there is a JS error, the app won't crash
	el = jQuery('#k2-tags-list ul');
	if ( !el.html() ) {
		japp._start_loader();
		japp.load_k2_tags();
	}
	} catch(e){japp._stop_loader();}
});

jQuery('#page-k2-tag').live('pageshow',function(event){
	try {
	func = function() {
		id = _gup( 'id' );

		// New tag
		if ( !id ) {
			// Stop loader
			japp._stop_loader();

			// Set page title
			jQuery('.page-title').html( 'New tag' );

			return;
		}

		japp.load_k2_tag( id );

		// Set page title
		jQuery('.page-title').html( 'Edit tag' );

		jQuery('#tag-delete').css('display', 'block');
	}

	// Start the loader
	japp._start_loader();
	// This needs to be triggered this way, otherwise the device doesn't like it
	setTimeout('func();', 250);
	} catch(e){japp._stop_loader();}
});
