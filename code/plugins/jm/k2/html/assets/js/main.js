/* Cache types */
// jomMobile uses cache to avoid roundtrips to the server, you can categorise cache types
// to easily delete all the types of caches when an action is performed. For example when an item 
// is added or updated, you would want to delete that items cache and the items list cache.
japp.add_cache_type('k2_item', {
	0: 'k2.item.{0}', // Will delete a cache that starts with k2.item. and the {0} will be replaced by a parameter passed to the function.
	1: 'k2.tags', // Will only delete the k2.tags cache.
	2: 'k2.items.*' // Will search and match any cache that begins with k2.items.
});

/* Page listeners */
jQuery('#page-k2-items').live('pageshow',function(event){
	try { // Wrap in try/catch in case there is a JS error, the app won't crash
	el = jQuery('#k2-items-list ul');
	if ( !el.html() ) {
		japp._start_loader();
		japp.load_k2_items();
	}
	} catch(e){japp._stop_loader();}
});
jQuery('#page-k2-item').live('pagebeforecreate',function(event){
	japp.load_k2_item_fields('item-view-options-listings');
	japp.load_k2_item_fields('item-view-options');
}).live('pageshow',function(event){
	try {
	func = function() {
		// Get the item id
		id = _gup( 'id' );

		// Load dynamic dropdowns and other html elements
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
		item = japp.get_k2_item( id );

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

/* Declare methods to load/add/edit/delete */

// Load k2 items into list for items.html
japp.load_k2_items = function( limitstart, limit, fresh ) {
	// Check if we are already loading the items, to avoid loading while already loading ;)
	if ( this._is_loading('k2_items') ) {
		return false;
	}

	var el = jQuery('#k2-item-list ul');
	this._started_loading('k2_items'); // Start jquerymobile loader

	// With this we can set a limit to how many items we load till the user scrolls to the bottom of the page
	if ( typeof limitstart == 'undefined' ) { limitstart = jQuery(el).attr('g:limitstart') || 0; }
	if ( typeof limit == 'undefined' ) { limit = 20; }

	var func = function( data ) {
		// Remove jquery mobile loader
		jQuery('#ajax-loading-img').remove();
		// Add fetched data from server to cache, and set for how long we want it to live
		jcache.set( context, data, {expiry: date_times.seconds( date_times.hour/2 )} );

		// We reached the end of the items
		if ( !data.length ) {
			japp.unbind_scroll_listener(); // Remove listener for image at the bottom of page
			japp._stopped_loading('k2_items', true);
			return;
		}

		el = jQuery(el);

		// Add each item to lsit
		jQuery(data).each(function(){
			state = japp.get_item_state( this.published );
			date = _datetime_to_date( this.created );
			jQuery(el).append('<li><a href="item.html?id=' + this.id + '">'
				+ '<h3>' + this.title + '</h3>'
				+ '<p><span class="item-author">' + this.author
				+ '</span> / <span class="item-' + state.toLowerCase() + '">'
				+ state + '</span> / <span class="item-'
				+ this.groupname.toLowerCase() + '">'
				+ this.groupname + '</span></p>'
				+ '<p class="ui-li-aside"><strong>' + date.toLocaleDateString() + '</strong></p>'
				+ '</a></li>');
		});

		// Refresh list view with new items
		jQuery(el).listview('refresh').attr('g:limitstart',
			parseInt( limitstart ) + parseInt( limit ) );

		// Add loading animation for when the user scrolls to the bottom of page
		jQuery(el).append('<li id="ajax-loading-img"><img src="'
			+ japp.ajax_loader + '" /></li>');

		// Add scroll listener to trigger this function again when user reaches bottom
		if ( 0 == limitstart ) {
			japp.scroll_bottom_listener( '#ajax-loading-img',
				function(){ japp.load_k2_items(); } );
		}

		japp._stopped_loading('k2_items', true);
	};

	// Set context for caching purposes
	var context = 'k2.items.' + limitstart + '.' + limit;
	// Check to see if we have a cache
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		func( jcache.get( context ) );
	} else { // If no cache, then load items list from server
		this._ajax(
			{
				app: 'k2',
				resource: 'items',
				limitstart: limitstart,
				limit: limit
			}, func );
	}
};

japp.load_k2_item_fields = function( fieldset, fresh ){
	// Set the context for caching purposes
	var context = 'k2.item.fields.' + fieldset;

	// Check cache
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		 data = jcache.get( context );
	} else { // else, get data from server
		this._ajax(
			{
				app: 'k2',
				resource: 'itemform',
				fieldset: fieldset
			}, function( data ) {
				// If there is no data, and we are expecting data, then try request again
				if ( japp._object_empty( data ) ) {
					// This function will only try to get data 3 times
					// First two parameters are filters for the request, and the last is a callback
					japp._try_server_request_again( 'load_k2_item_fields', fieldset,
						function(){ japp.load_k2_item_fields( fieldset, fresh ); });
				} else {
					// Add response to cache
					jcache.set( context, data );
				}
			}, { async: false });
		data = jcache.get( context );
	}

	if ( data.html ) {
		jQuery('.fieldset-' + fieldset).append(data.html);
	}
};

japp.get_k2_categories = function( fresh ){
	var context = 'k2.categories';
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		return jcache.get( context );
	}

	this._ajax(
		{
			app: 'k2',
			resource: 'categories',
			tree: 1
		},
	 	function( data ) {
			// Try again?
			if ( japp._object_empty( data ) ) {
				japp._try_server_request_again( 'get_k2_kategories', '',
					function(){ japp.get_k2_kategories( fresh ) } );
			} else {
				jcache.set( context, data );
			}
		}, { async: false });

	return jcache.get( context );
}

japp.get_k2_tags = function( fresh ){
	var context = 'k2.tags';
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		return jcache.get( context );
	}

	this._ajax(
		{
			app: 'k2',
			resource: 'tags'
		},
	 	function( data ) {
			// Try again?
			if ( japp._object_empty( data ) ) {
				japp._try_server_request_again( 'get_k2_tags', '',
					function(){ japp.get_k2_tags( fresh ) } );
			} else {
				jcache.set( context, data );
			}
		}, { async: false });

	return jcache.get( context );
}

japp.load_k2_item = function( id ) {
	item = this.get_k2_item( id );

	// Populate all article fields
	jQuery('#item-title').val(item.title);
	jQuery('#item-alias').val(item.alias);
	jQuery('#item-catid').val(item.catid).selectmenu();
	jQuery('#item-featured').val(item.featured).slider('refresh');
	jQuery('#item-published').val(item.published).slider('refresh');
	jQuery('#item-text').val( item.introtext + ( item.fulltext ? '<hr id="system-readmore" />' + item.fulltext : '' ) );
	jQuery('#item-language').val(item.langugae).selectmenu();
	jQuery('#item-created-by').val(item.created_by).selectmenu();
	jQuery('#item-created-by-alias').val(item.created_by_alias);
	jQuery('#item-access').val(item.access).selectmenu();
	jQuery('#item-created').val(item.created);
	jQuery('#item-publish-up').val(item.publish_up);
	if ( item.publish_down.toString() != '0000-00-00 00:00:00' ) {
		jQuery('#item-publish-down').val(item.publish_down);
	}
	jQuery('#item-metadesc').val(item.metadesc);
	jQuery('#item-metakey').val(item.metakey);
	jQuery('#item-meta-robots').val(item.metadata.robots);
	jQuery('#item-meta-author').val(item.metadata.author);
	for ( param in item.params ) {
		_el = jQuery('#params_' + param)
		_el.val(item.params[param]);
		if ( 'SELECT' == _el[0].tagName ) {
			_el.selectmenu('refresh');
		}
	};
	jQuery('#item-id').val(item.id);

	tags = japp.get_k2_tags();
	prepupulate = new Array;
	for (var i = 0; i < item.tags.length; i++) {
		prepupulate[i] = {
			name: item.tags[i].name
		}
	};
    jQuery("#item-tags").tokenInput(tags, {allowNewTokens: true, theme: "facebook",
    	preventDuplicates: true, prePopulate: prepupulate, tokenValue: 'name',
    	hintText: 'Type in a tag', tokenDelimiter: '|*|',
		extraclasses: 'token-input-list-facebook ui-input-text ui-body-c ui-corner-all ui-shadow-inset'});

	this._stop_loader();
}

japp.get_k2_item = function( id, fresh ) {
	var context = 'k2.item.' + id;
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		return jcache.get( context );
	}

	this._ajax(
		{
			app: 'k2',
			resource: 'item',
			cid: id
		},
	 	function( data ) {
			// Try again?
			if ( japp._object_empty( data ) ) {
				japp._try_server_request_again( 'get_k2_item', id,
				function(){ japp.get_k2_item( id, fresh ); });
			} else {
				jcache.set( context, data );
			}
		}, { async: false });

	return jcache.get( context );
}

japp.save_k2_item = function( postdata ) {
	if ( typeof postdata == 'undefined' ) {
		_data = jQuery('#k2-item-form').serialize();
		postdata = jQuery.deparam( _data );
		postdata['tags'] = postdata['tmp-tags'].split('|*|');
	}

	// Add defaults
	postdata.app = 'k2';
	postdata.resource = 'item';

	this._ajax(
		postdata,
		function( data ) {
			japp._stop_loader();
			if ( data.success ) {
				_alert( data.message, null, 'Success' );
				jQuery('#item-id').val(data.id);
				jQuery('#item-delete').css('display', 'block');

				japp.clear_cache( 'k2_item', data.id );
			} else {
				_alert( data.message, null, 'Error' );
			}
		// Either send the data as POST or PUT use the correct header for creating or updating
		}, { async: false, type: ( ( postdata.id ) ? 'PUT' : 'POST' ) });
}

japp.delete_k2_item = function() {
	id = jQuery('#item-id').val();

	if ( !id ) {
		japp._stop_loader();
		_alert( 'Item not found' );
		return false;
	}

	var answer = confirm( 'Are you sure you want to delete this item?' );
	if ( !answer ) {
		japp._stop_loader();
		return false;
	}

	this._ajax(
		{
			app: 'k2',
			resource: 'items',
			task: 'trash',
			cid: { 0: id }
		},
	 	function( data ) {
			japp._stop_loader();
			if ( data.success ) {
				jQuery('#k2-item-list ul').html('');

				if ( data.message ) {
					_alert( data.message, null, 'Success' );
				}

				japp.clear_cache( 'k2_item', id );
				jQuery('#page-k2-item .ui-header a:first').trigger('click');
			} else {
				_alert( data.message, null, 'Error' );
			}
		}, { async: false, type: 'DELETE' });
}
