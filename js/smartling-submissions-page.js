/**
 * Created by sergey@slepokurov.com on 23.02.2015.
 */
(function($) {

	var list = {
		data:  {
			ids : []
		},
		timer: null,
		delay: 100000,
		init: function() {
			$('#submissions-filter').on('click', '.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a', function(e) {
				e.preventDefault();
				var query = this.search.substring( 1 );
				var action = list.parseQuery("sort", query);
				list.update(action);
			});

			$('#submissions-filter' ).on('click', '.pagination-links a', function(e) {
				e.preventDefault();
				var query = this.search.substring( 1 );
				var action = list.parseQuery("sort", query);
				list.update(action);
			});

			$('#submissions-filter').on('keyup', 'input[name=paged]',  function(e) {
				if ( 13 == e.which ) {
					e.preventDefault();
				}
				var action = list.parseQuery();
				list.update(action);
			});
			this.autoUpdate();
		},
		parseQuery: function(type, query) {
			list.data = {};
			var searchEl = $("#s-search-input" ).val();
			if(searchEl) {
				list.data.s = searchEl;
			}
			var action = null;
			var actionEl = $("#bulk-action-selector-top" ).val();
			if(actionEl != "-1") {
				action = actionEl;
			}
			list.data['smartling-submissions-page-status'] = $('#smartling-submissions-page-status' ).val();
			list.data['smartling-submissions-page-content-type'] = $('#smartling-submissions-page-content-type' ).val();
			switch (type) {
				case "action" :
				if(query) {
					action = list.__query( query, 'action' ) || list.data.action;
					list.data['smartling-submissions-page-submission'] =  list.__query( query, 'smartling-submissions-page-submission' ) || null;
				}
				break;
			case "sort" :
				if(query) {
				//	list.data.page = list.__query( query , 'page' );
					list.data.paged = list.__query( query , 'paged' ) || '1';
					list.data.order = list.__query( query , 'order' ) || 'asc';
					list.data.orderby = list.__query( query , 'orderby' ) || 'title';
				}
				break;
			}
			return action;
		},
		getIds : function() {
			this.data.ids = [];
			$('.wp-list-table td.id').each(function() {
				var el = $(this);
				list.data.ids.push(el.text());
			});
		},
		autoUpdate: function() {
			this.timer = setTimeout( function() {
				this.getIds();
				list.update('ajax_submissions_update_status');
			}, this.delay);
		},
		update: function(action) {
			$.ajax({
				url: ajaxurl,
				data: $.extend(
					{
						ajax_submissions_update_status_nonce: $('#ajax_submissions_update_status_nonce').val(),
						action: action || 'ajax_submissions'
					},
					this.data
				),
				success: $.proxy(this.onSuccess, this)
			});
		},
		onSuccess: function(response) {
			clearTimeout(this.timer);

			var result = $.parseJSON( response );

			if ( result.rows.length ) {
				$( '#the-list' ).html( result.rows );
			}
			if ( result.column_headers.length ) {
				$( 'thead tr, tfoot tr' ).html( result.column_headers );
			}
			if ( result.pagination.bottom.length ) {
				$( '.tablenav.top .tablenav-pages' ).html( $( result.pagination.top ).html() );
			}
			if ( result.pagination.top.length ) {
				$( '.tablenav.bottom .tablenav-pages' ).html( $( result.pagination.bottom ).html() );
			}

			this.autoUpdate();
		},
		__query: function( query, variable ) {

			var vars = query.split("&");
			for ( var i = 0; i <vars.length; i++ ) {
				var pair = vars[ i ].split("=");
				if ( pair[0] == variable )
					return pair[1];
			}
			return false;
		}
	};
	$(function() {
		list.init();
	});

})(jQuery);