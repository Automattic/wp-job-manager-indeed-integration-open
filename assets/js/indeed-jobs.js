jQuery( document ).ready( function ( $ ) {

	var xhr;

	$( '.job_listings' ).on( 'update_indeed_results', function ( event, page, append ) {

		if ( xhr ) {
			xhr.abort();
		}

		var target   = $( this );
		var results  = target.find( '.job_listings' );
		var api_args = target.data( 'api_args' );
		var data     = {
			action: 'job_manager_get_indeed_listings',
			api_args: api_args,
			page: page
		};

		$( '.load_more_indeed_jobs', target ).addClass( 'loading' );

		xhr = $.ajax( {
			type: 'GET',
			url: job_manager_indeed_jobs.ajax_url,
			data: data,
			success: function ( response ) {
				if ( response ) {
					try {

						// Get the valid JSON only from the returned string
						if ( response.indexOf( "<!--WPJM-->" ) >= 0 ) {
							response = response.split( "<!--WPJM-->" )[ 1 ]; // Strip off before WPJM
						}

						if ( response.indexOf( "<!--WPJM_END-->" ) >= 0 ) {
							response = response.split( "<!--WPJM_END-->" )[ 0 ]; // Strip off anything after WPJM_END
						}

						var result = $.parseJSON( response );

						if ( result.html ) {
							$( results ).append( result.html );
						}

						if ( !result.found_jobs || result.max_num_pages === page ) {
							$( '.load_more_indeed_jobs', target ).hide();
						} else {
							$( '.load_more_indeed_jobs', target ).show().data( 'page', page );
						}

						$( results ).removeClass( 'loading' );
						$( '.load_more_indeed_jobs', target ).removeClass( 'loading' );
						$( 'li.job_listing', results ).css( 'visibility', 'visible' );

					} catch ( err ) {
						//console.log( err );
					}
				}
			}
		} );
	} );

	$( '.load_more_indeed_jobs' ).unbind('click').click( function () {
		var target = $( this ).closest( 'div.job_listings' );
		var page = $( this ).data( 'page' );

		if ( !page ) {
			page = 1;
		} else {
			page = parseInt( page );
		}

		$( this ).data( 'page', ( page + 1 ) );

		target.triggerHandler( 'update_indeed_results', [ page + 1, true ] );

		return false;
	} );

} );