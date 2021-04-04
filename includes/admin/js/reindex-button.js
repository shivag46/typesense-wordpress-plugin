(function($) {

	$(
		function() {
			var $buttons = $( '.typesense-reindex-button' );
			$buttons.on( 'click', handleButtonClick );
		}
	);

	function handleButtonClick(e) {
        $clickedButton = $( e.currentTarget );
		disableButton( $clickedButton );

		pushSettings( $clickedButton);
		//alert('wefwerfwfer');
	}

	function disableButton($button) {
		$button.prop( 'disabled', true );
	}

	function enableButton($button) {
		$button.prop( 'disabled', false );
	}

	function pushSettings($clickedButton) {

		var data = {
			'action': 'typesense_re_index',
            'whatever': 123
		};

		$.post(
			ajaxurl, data, function(response) {
				alert(response);
                enableButton( $clickedButton );
			}
		).fail(
			function(response) {
				alert( 'An error occurred: ' + response.responseText );
				enableButton( $clickedButton );
			}
		);
	}

})( jQuery );
