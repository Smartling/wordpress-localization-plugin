(function ($) {
	'use strict';

	var localizationOptions = {

		selectors : {
			form               : '#smartling-form' ,
			post_widget        : '#smartling-post-widget' ,
			submit             : '#submit' ,
			errors_container   : '.display-errors' ,
			errors             : '.display-errors .error' ,
			set_default_locale : '#change-default-locale' ,
			default_locales    : '#default-locales'
		} ,

		fields : {
			api_key_real : 'smartling_settings[apiKey]' ,
			project_id   : 'smartling_settings[projectId]' ,
			api_key      : 'apiKey' ,
			mandatory    : [
				'smartling_settings[apiUrl]' ,
				'smartling_settings[projectId]' ,
				'smartling_settings[retrievalType]' ,
				'smartling_settings[apiKey]'
			]
		} ,

		patterns : {
			project_id : /^[\w]+$/ ,
			api_key    : /^[\w.\-]+$/
		} ,

		errorsMsg : {
			api_key    : 'Key length must be n chars.' ,
			project_id : 'Project ID length must be n chars.' ,
			default    : function (name) {
				if ( name !== undefined ) {
					return name + ' field is mandatory.';
				} else {
					return 'The field is mandatory.';
				}
			}
		} ,


		init : function () {
			$( this.selectors.form ).on( 'click' , this.selectors.submit , $.proxy( this.onSubmit , this ) );
			$( this.selectors.form + ',' + this.selectors.post_widget ).on( 'click' , 'input:checkbox' , $.proxy( this.setCheckboxValue , this ) );
			$( this.selectors.set_default_locale ).on( 'click' , $.proxy( this.onChangeDefaultLocale , this ) );

		} ,

		createErrorTemplate : function (msg) {
			return '<div class="error settings-error"><p><strong>' + msg + '</strong></p></div>';
		} ,

		displayError : function (msg) {
			var tmpl = this.createErrorTemplate( msg );

			this.renderTo( this.selectors.errors_container , tmpl );
		} ,

		getFieldValue : function (name) {
			var input = this.getInputByName( name );

			return input.val();
		} ,

		getFieldName   : function (name) {
			var input = this.getInputByName( name );

			return input.closest( 'tr' ).find( 'th' ).text();
		} ,
		getInputByName : function (name) {
			var selector = 'input[name="' + name + '"]';

			return $( selector );
		} ,
		hideErrors     : function () {
			$( this.selectors.errors ).remove();
		} ,
		onSubmit       : function () {

			this.hideErrors();

			var
				form_data = $( this.selectors.form ).serializeArray() ,
				is_valid = this.validateFields( form_data );


			if ( is_valid ) {
				return true;
			}
			;

			return false;
		} ,

		onTranslationSend : function (e) {
			e.preventDefault();
			var
				data = $( this.selectors.post_widget ).serializeArray();

			console.log( e );
		} ,

		onChangeDefaultLocale : function (e) {
			e.preventDefault();

			$( this.selectors.default_locales ).slideToggle( 'fast' );
		} ,

		setFieldValue : function (name , val) {
			var input = this.getInputByName( name );

			input.val( val );
		} ,

		setCheckboxValue : function (e) {
			var
				checkbox = $( e.target ) ,
				checkbox_real = $( checkbox ).siblings( 'input:hidden' ).get( 0 );

			if ( checkbox.is( ':checked' ) ) {
				$( checkbox_real ).val( 'true' );
			} else {
				$( checkbox_real ).val( 'false' );
			}
		} ,

		renderTo : function (place , template) {

			$( template ).appendTo( place );
		} ,

		validateFields : function (fields) {
			var
				new_key = this.getFieldValue( this.fields.api_key ) ,
				real_key = this.getFieldValue( this.fields.api_key_real ) ,
				project_id = this.getFieldValue( this.fields.project_id ) ,
				valid = true ,
				self = this;

			$.each( fields , function (index , val) {

				if ( val[ 'name' ] == self.fields.api_key_real ) {

					if ( self.patterns.api_key.test( new_key ) ) {

						self.setFieldValue( self.fields.api_key_real , new_key );

					} else if ( ! self.patterns.api_key.test( new_key ) && new_key !== '' ) {

						self.displayError( self.errorsMsg.api_key );
						valid = false;

					} else if ( new_key == '' && ! real_key.length ) {

						self.displayError( self.errorsMsg.api_key );
						valid = false;
					}

				} else if ( val[ 'name' ] == self.fields.project_id ) {

					if ( self.patterns.project_id.test( project_id ) ) {

						self.setFieldValue( self.fields.project_id , project_id );

					} else if ( project_id !== '' || ! self.patterns.project_id.test( project_id ) ) {

						self.displayError( self.errorsMsg.project_id );
						valid = false;

					} else if ( project_id == '' ) {
						var name = self.getFieldName( self.fields.project_id );

						self.displayError( self.errorsMsg.default( name ) );
					}

				} else if ( $.inArray( val[ 'name' ] , self.fields.mandatory ) > - 1 ) {

					var
						name = self.getFieldName( val[ 'name' ] ) ,
						input = self.getInputByName( val[ 'name' ] ) ,
						type = input.attr( 'type' );

					if ( type == 'checkbox' && ! input.is( ':checked' ) ) {

						valid = false;
						self.displayError( self.errorsMsg.default( name ) );

					} else if ( input.val() == '' || input.val() == 'false' ) {

						valid = false;
						self.displayError( self.errorsMsg.default( name ) );
					}

				}
			} );

			return valid;
		}
	};

	$( function () {
		var content = $( '#smartling-form' );
		if ( content.length > 0 ) {
			localizationOptions.init();
		}
	} );

})( jQuery );


function bulkCheck(className , action) {
	var elements = document.getElementsByClassName( className );
	switch (action) {
	case 'check':
	{
		for ( var i = 0 ; i < elements.length ; i ++ ) {
			elements[ i ].setAttribute( 'checked' , 'checked' );
		}
		break;
	}
	case 'uncheck':
	{
		for ( var i = 0 ; i < elements.length ; i ++ ) {
			elements[ i ].removeAttribute( 'checked' );
		}
		break;
	}
	}
}


jQuery( document ).ready( function () {
	jQuery( '.checkall' ).on( 'click' , function (e) {
		e.stopPropagation();
		var checked = jQuery( this ).is( ':checked' );

		if ( checked ) {
			jQuery( '.bulkaction' ).attr( "checked" , "checked" );
		}
		else {
			jQuery( '.bulkaction' ).removeAttr( "checked" );
		}
	} );

	jQuery('#sent-to-smartling-bulk' ).on('click', function(e){
		jQuery('#ct' ).val(jQuery('#smartling-bulk-submit-page-content-type' ).val());
		return;
	});
} );

