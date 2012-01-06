/* Cache types */
// jomMobile uses cache to avoid roundtrips to the server, you can categorise cache types
// to easily delete all the types of caches when an action is performed. For example when an item 
// is added or updated, you would want to delete that items cache and the items list cache.
japp.add_cache_type('k2_item', {
	0: 'k2.item.{0}', // Will delete a cache that starts with k2.item. and the {0} will be replaced by a parameter passed to the function.
	1: 'k2.tags', // Will only delete the k2.tags cache.
	2: 'k2.items.*' // Will search and match any cache that begins with k2.items.
});

/* Declare methods to load/add/edit/delete */

/**
 * Load K2 items into list for items.html
 * Uses pagination.
 * When deleting an item cache, it will match for 'k2.items.*'
 * It is easier if you just add new methods to the 'japp' object rather than creating your own
 */
japp.load_k2_items = function( limitstart, limit, fresh ) {
	// Check if we are already loading the items, to avoid loading while already loading ;)
	if ( this._is_loading('k2_items') ) {
		return false;
	}

	var el = jQuery('#k2-items-list ul');
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
		// This is the correct way to do ajax calls,
		// More values will get appended to the data that is passed
		this._ajax(
			{
				app: 'k2',
				resource: 'items',
				limitstart: limitstart,
				limit: limit
			}, func );
	}
};

/**	
 * Loads JForm fields from the server
 * It avoid writing a lot of HTML
 */
japp.load_k2_form_fields = function( form, fieldset, fresh ){
	// Set the context for caching purposes
	var context = 'k2.item.fields.' + fieldset;

	// Check cache
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		 data = jcache.get( context );
	} else { // else, get data from server
		this._ajax(
			{
				app: 'k2',
				resource: 'formfields',
				form: form,
				fieldset: fieldset
			}, function( data ) {
				// If there is no data, and we are expecting data, then try request again
				if ( japp._object_empty( data ) ) {
					// This function will only try to get data 3 times
					// First two parameters are filters for the request, and the last is a callback
					japp._try_server_request_again( 'load_k2_item_fields_' + form, fieldset,
						function(){ japp.load_k2_item_fields( fieldset, fresh ); });
				} else {
					// Add response to cache
					jcache.set( context, data );
				}
			}, { async: false });
		data = jcache.get( context );
	}

	// Append the new HTML fields to the correct collapsible div
	if ( data.html ) {
		jQuery('.fieldset-' + fieldset).append(data.html);
	}
};

/**
 * Returns a list of K2 categories that is formatted for display in HTML dropdown
 */
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
				japp._try_server_request_again( 'get_k2_categories', '',
					function(){ japp.get_k2_categories( fresh ) } );
			} else {
				jcache.set( context, data );
			}
		}, { async: false });

	return jcache.get( context );
}

/**
 * Loads an array of available K2 tags
 */
japp.get_k2_tags = function( fresh ){
	var context = 'k2.tags';
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		return jcache.get( context );
	}

	this._ajax(
		{
			app: 'k2',
			resource: 'tags',
			limit: 999999,
			limitstart: 0
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

/**
 * Loads K2 article into form fields
 */
japp.load_k2_item = function( id ) {
	item = this.get_k2_item( id );

	// Populate all article fields
	jQuery('#item-title').val(item.title);
	jQuery('#item-alias').val(item.alias);
	jQuery('#item-catid').val(item.catid).selectmenu();
	jQuery('#item-featured').val(item.featured).slider('refresh');
	jQuery('#item-published').val(item.published).slider('refresh');
	jQuery('#item-text').val( item.introtext + ( item.fulltext ? '<hr id="system-readmore" />'
		+ item.fulltext : '' ) );
	jQuery('#item-language').val(item.language).selectmenu();
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

/**
 * Loads a K2 and all of its contents from the server
 */
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

/**
 * This method sends the contents of a form to the server for saving
 */
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

/**
 * Deletes a K2 item
 */
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
				jQuery('#k2-items-list ul').html('');

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

/* Tags */

japp.add_cache_type('k2_tag', {
	0: 'k2.tag.{0}',
	1: 'k2.tags.*'
});

japp.load_k2_tags = function( limitstart, limit, fresh ) {
	if ( this._is_loading('k2_tags') ) {
		return false;
	}

	var el = jQuery('#k2-tags-list ul');
	this._started_loading('k2_tags');

	if ( typeof limitstart == 'undefined' ) { limitstart = jQuery(el).attr('g:limitstart') || 0; }
	if ( typeof limit == 'undefined' ) { limit = 20; }

	var func = function( data ) {
		jQuery('#ajax-loading-img').remove();
		jcache.set( context, data, {expiry: date_times.seconds( date_times.hour/2 )} );

		// We reached the end of the tags
		if ( !data.length ) {
			japp.unbind_scroll_listener();
			japp._stopped_loading('k2_tags', true);
			return;
		}

		el = jQuery(el);

		// Add each item to lsit
		jQuery(data).each(function(){
			state = japp.get_item_state( this.published );
			jQuery(el).append('<li><a href="tag.html?id=' + this.id + '">'
				+ '<h3>' + this.name + '</h3>'
				+ '<p><span class="item-' + state.toLowerCase() + '">' + state + '</span></p>'
				+ '</a></li>');
		});

		jQuery(el).listview('refresh').attr('g:limitstart',
			parseInt( limitstart ) + parseInt( limit ) );

		jQuery(el).append('<li id="ajax-loading-img"><img src="'
			+ japp.ajax_loader + '" /></li>');

		if ( 0 == limitstart ) {
			japp.scroll_bottom_listener( '#ajax-loading-img',
				function(){ japp.load_k2_tags(); } );
		}

		japp._stopped_loading('k2_tags', true);
	};

	var context = 'k2.tags.' + limitstart + '.' + limit;
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		func( jcache.get( context ) );
	} else {
		this._ajax(
			{
				app: 'k2',
				resource: 'tags',
				limitstart: limitstart,
				limit: limit
			}, func );
	}
};

japp.load_k2_tag = function( id ) {
	tag = this.get_k2_tag( id );
	this._stop_loader();

	// Populate all article fields
	jQuery('#tag-name').val(tag.name);
	jQuery('#tag-published').val(tag.published);
	jQuery('#tag-id').val(tag.id);

	try {
		jQuery('#tag-published').slider('refresh');
	} catch(e) {
		try {
		jQuery('#tag-published').slider();
		} catch(e) {}
	}
}

japp.get_k2_tag = function( id, fresh ) {
	var context = 'k2.tag.' + id;
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		return jcache.get( context );
	}

	this._ajax(
		{
			app: 'k2',
			resource: 'tag',
			cid: id
		},
	 	function( data ) {
			// Try again?
			if ( japp._object_empty( data ) ) {
				japp._try_server_request_again( 'get_k2_tag', id,
				function(){ japp.get_k2_tag( id, fresh ); });
			} else {
				jcache.set( context, data );
			}
		}, { async: false });

	return jcache.get( context );
}

japp.save_k2_tag = function( postdata ) {
	if ( typeof postdata == 'undefined' ) {
		_data = jQuery('#k2-tag-form').serialize();
		postdata = jQuery.deparam( _data );
	}

	// Add defaults
	postdata.app = 'k2';
	postdata.resource = 'tag';

	this._ajax(
		postdata,
		function( data ) {
			japp._stop_loader();
			if ( data.success ) {
				_alert( data.message, null, 'Success' );
				jQuery('#tag-id').val(data.id);
				jQuery('#tag-delete').css('display', 'block');

				japp.clear_cache( 'k2_tag', data.id );
			} else {
				_alert( data.message, null, 'Error' );
			}
		// Either send the data as POST or PUT use the correct header for creating or updating
		}, { async: false, type: ( ( postdata.id ) ? 'PUT' : 'POST' ) });
}

japp.delete_k2_tag = function() {
	id = jQuery('#tag-id').val();

	if ( !id ) {
		japp._stop_loader();
		_alert( 'Tag not found' );
		return false;
	}

	var answer = confirm( 'Are you sure you want to delete this tag?' );
	if ( !answer ) {
		japp._stop_loader();
		return false;
	}

	this._ajax(
		{
			app: 'k2',
			resource: 'tags',
			task: 'trash',
			cid: { 0: id }
		},
	 	function( data ) {
			japp._stop_loader();
			if ( data.success ) {
				jQuery('#k2-tags-list ul').html('');

				if ( data.message ) {
					_alert( data.message, null, 'Success' );
				}

				japp.clear_cache( 'k2_tag', id );
				jQuery('#page-k2-tag .ui-header a:first').trigger('click');
			} else {
				_alert( data.message, null, 'Error' );
			}
		}, { async: false, type: 'DELETE' });
}

/* Categories */

japp.add_cache_type('k2_category', {
	0: 'k2.category.{0}',
	1: 'k2.categories.*'
});

japp.get_k2_extrafieldsgroups = function( fresh ){
	var context = 'k2.extrafieldgroups';
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		return jcache.get( context );
	}

	this._ajax(
		{
			app: 'k2',
			resource: 'extrafieldsgroups'
		},
	 	function( data ) {
			// Try again?
			if ( japp._object_empty( data ) ) {
				jcache.set( context, data, {expiry: date_times.seconds( date_times.hour )} );
			} else {
				jcache.set( context, data );
			}
		}, { async: false });

	return jcache.get( context );
}

japp.load_k2_categories = function( limitstart, limit, fresh ) {
	if ( this._is_loading('k2_categories') ) {
		return false;
	}

	var el = jQuery('#k2-categories-list ul');
	this._started_loading('k2_categories');

	if ( typeof limitstart == 'undefined' ) { limitstart = jQuery(el).attr('g:limitstart') || 0; }
	if ( typeof limit == 'undefined' ) { limit = 20; }

	var func = function( data ) {
		jQuery('#ajax-loading-img').remove();
		jcache.set( context, data, {expiry: date_times.seconds( date_times.hour/2 )} );

		if ( !data.length ) {
			japp.unbind_scroll_listener(); // Remove listener for image at the bottom of page
			japp._stopped_loading('k2_categories', true);
			return;
		}

		el = jQuery(el);
		acl = japp.get_joomla_accesslevels();

		// Add each category to lsit
		jQuery(data).each(function(){
			state = japp.get_item_state( 1 /* this.published */ );
			access = '';
			for (var i = 0; i < acl.length; i++) {
				if ( acl[i].value == this.access ) {
					access = acl[i].text;
				};
			};
			jQuery(el).append('<li><a href="category.html?id=' + this.id + '">'
				+ '<h3>' + this.treename + '</h3>'
				+ '<p><span class="item-' + state.toLowerCase() + '">'
				+ state + '</span> / <span class="item-'
				+ access.toLowerCase() + '">'
				+ access + '</span></p>'
				+ '</a></li>');
		});

		jQuery(el).listview('refresh').attr('g:limitstart',
			parseInt( limitstart ) + parseInt( limit ) );

		jQuery(el).append('<li id="ajax-loading-img"><img src="'
			+ japp.ajax_loader + '" /></li>');

		if ( 0 == limitstart ) {
			japp.scroll_bottom_listener( '#ajax-loading-img',
				function(){ japp.load_k2_categories(); } );
		}

		japp._stopped_loading('k2_categories', true);
	};

	var context = 'k2.categories.' + limitstart + '.' + limit;
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		func( jcache.get( context ) );
	} else {
		this._ajax(
			{
				app: 'k2',
				resource: 'categories',
				limitstart: limitstart,
				limit: limit
			}, func );
	}
};

japp.load_k2_category = function( id ) {
	category = this.get_k2_category( id );

	// Populate all category fields
	jQuery('#category-name').val(category.name);
	jQuery('#category-alias').val(category.alias);
	jQuery('#category-parent').val(category.parent).selectmenu();
	jQuery('#params_inheritFrom').selectmenu();
	jQuery('#category-extraFieldsGroup').val(category.extraFieldsGroup).selectmenu();
	jQuery('#category-published').val(category.published).slider('refresh');
	jQuery('#category-access').val(category.access).selectmenu();
	jQuery('#category-language').val(category.language).selectmenu();
	jQuery('#category-description').val(category.description);
	for ( param in category.params ) {
		if ( 'theme' == param ) {
			_el = jQuery('#params' + param);
			_el.val(category.params[param]);
			if ( typeof _el[0] != 'undefined' ) {
				if ( 'SELECT' == _el[0].tagName ) {
					_el.selectmenu('refresh');
				}
			}
		} else {
			jQuery('[name="params[' +param+ ']"]' ).each(function(){
				if ( 'radio' != jQuery(this).attr('type') ) {
					_el = jQuery(this);
					_el.val(category.params[param]);
					if ( typeof _el[0] != 'undefined' ) {
						if ( 'SELECT' == _el[0].tagName ) {
							_el.selectmenu('refresh');
						}
					}
				} else if ( jQuery(this).val() == category.params[param] ) {
					_el = jQuery(this);
					_el.attr('checked', true).checkboxradio('refresh');
				} else {
					jQuery(this).attr('checked', false).checkboxradio('refresh');
					return;
				}

				_el.val(category.params[param]);
			});
		}
	};
	jQuery('#category-id').val(category.id);

	this._stop_loader();
}

japp.get_k2_category = function( id, fresh ) {
	var context = 'k2.category.' + id;
	if ( japp.cache && !fresh && jcache.get( context ) ) {
		return jcache.get( context );
	}

	this._ajax(
		{
			app: 'k2',
			resource: 'category',
			cid: id
		},
	 	function( data ) {
			// Try again?
			if ( japp._object_empty( data ) ) {
				japp._try_server_request_again( 'get_k2_category', id,
				function(){ japp.get_k2_category( id, fresh ); });
			} else {
				jcache.set( context, data );
			}
		}, { async: false });

	return jcache.get( context );
}

japp.save_k2_category = function( postdata ) {
	if ( typeof postdata == 'undefined' ) {
		_data = jQuery('#k2-category-form').serialize();
		postdata = jQuery.deparam( _data );
	}

	// Add defaults
	postdata.app = 'k2';
	postdata.resource = 'category';

	this._ajax(
		postdata,
		function( data ) {
			japp._stop_loader();
			if ( data.success ) {
				_alert( data.message, null, 'Success' );
				jQuery('#category-id').val(data.id);
				jQuery('#category-delete').css('display', 'block');

				japp.clear_cache( 'k2_category', data.id );
			} else {
				_alert( data.message, null, 'Error' );
			}
		// Either send the data as POST or PUT use the correct header for creating or updating
		}, { async: false, type: ( ( postdata.id ) ? 'PUT' : 'POST' ) });
}

japp.delete_k2_category = function() {
	id = jQuery('#category-id').val();

	if ( !id ) {
		japp._stop_loader();
		_alert( 'Category not found' );
		return false;
	}

	var answer = confirm( 'Are you sure you want to delete this category?' );
	if ( !answer ) {
		japp._stop_loader();
		return false;
	}

	this._ajax(
		{
			app: 'k2',
			resource: 'categories',
			task: 'trash',
			cid: { 0: id }
		},
	 	function( data ) {
			japp._stop_loader();
			if ( data.success ) {
				jQuery('#k2-categories-list ul').html('');

				if ( data.message ) {
					_alert( data.message, null, 'Success' );
				}

				japp.clear_cache( 'k2_category', id );
				jQuery('#page-k2-category .ui-header a:first').trigger('click');
			} else {
				_alert( data.message, null, 'Error' );
			}
		}, { async: false, type: 'DELETE' });
}
