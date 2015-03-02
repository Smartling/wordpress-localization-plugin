/**
 * Created by sergey@slepokurov.com on 23.02.2015.
 */
(function($) {

	var list = {
		types: {
			widget: 0,
			page: 1
		},
		type: null,
		data:  {
			ids : []
		},
		timer: null,
		delay: 10000,
		init: function() {
			this.type = this.detect();
			if(this.type != null) {
				this.getIds();
				this.update();
			}
		},
		detect: function() {
			if($('#smartling-post-widget' ).length > 0) {
				return this.types.widget;
			}

			if($('#submissions-filter' ).length > 0) {
				return this.types.page;
			}
			return null;
		},
		getIds : function() {
			this.data.ids = [];
			switch (this.type) {
				case this.types.widget:
					$('.submission-id').each(function(){
						var el = $(this);
						list.data.ids.push(el.val());
					});
				break;
				case this.types.page:
					$('.wp-list-table td.id').each(function() {
						var el = $(this);
						list.data.ids.push(el.text());
					});
				break;
			}
		},
		update: function(action) {
			if(this.data.ids.length > 0) {
				$.ajax( {
					url     : ajaxurl ,
					data    : $.extend(
						{
							action : 'ajax_submissions_update_status'
						} ,
						this.data
					) ,
					success : $.proxy( this.onSuccess , this )
				} );
			}
		},
		onSuccess: function(response) {
			var result = $.parseJSON( response );
			switch(this.type) {
				case this.types.widget:
					this.renderWidget(result);
					break;
				case this.types.page:
					this.renderPage(result);
					break;
			}
		},
		renderWidget: function(data) {
			for(var i = 0; i < data.length; i++) {
				var item = data[i];
				var hidden = $('#submission-id-' + item["id"]);
				if(hidden.length > 0) {
					var span = hidden.prev().attr({
						'class' : 'widget-btn ' + item["color"],
						'title' : item["status"]
					});
					span.children('span' ).text(item["percentage"] + '%');
				}
			}
		},
		renderPage: function(data) {
			for(var i = 0; i < data.length; i++) {
				var item = data[i];
				var row = $('#submission-id-' + item["id"] ).closest('tr');
				if(row.length > 0) {
					row.children( '.column-progress' ).text(item["percentage"] + '%');
					row.children( '.column-status' ).text(item["status"]);
				}
			}
		}
	};
	$(function() {
		list.init();
	});

})(jQuery);