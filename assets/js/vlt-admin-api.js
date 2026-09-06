/* Video Lead Tracker — Admin API client */
( function ( window ) {
	'use strict';

	var cfg = window.vltAdminData || {};

	function buildUrl( path, params ) {
		var base = ( cfg.restBase || '' ).replace( /\/?$/, '/' );
		var url  = base + String( path || '' ).replace( /^\//, '' );
		if ( params && typeof params === 'object' ) {
			var qs = Object.keys( params )
				.filter( function ( k ) { return params[ k ] !== undefined && params[ k ] !== null && params[ k ] !== ''; } )
				.map( function ( k ) { return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] ); } )
				.join( '&' );
			if ( qs ) url += ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + qs;
		}
		return url;
	}

	function request( method, path, opts ) {
		opts = opts || {};
		var headers = {
			'X-WP-Nonce': cfg.nonce || '',
		};
		var init = {
			method: method,
			headers: headers,
			credentials: 'same-origin',
		};
		if ( opts.signal ) init.signal = opts.signal;
		if ( opts.body !== undefined ) {
			headers['Content-Type'] = 'application/json';
			init.body = JSON.stringify( opts.body );
		}
		return fetch( buildUrl( path, opts.params ), init ).then( function ( res ) {
			return res.json().then( function ( data ) {
				if ( ! res.ok ) {
					var err = new Error( ( data && ( data.message || ( data.data && data.data.message ) ) ) || 'Request failed' );
					err.status = res.status;
					err.payload = data;
					throw err;
				}
				return data;
			} );
		} );
	}

	window.vltApi = {
		get: function ( path, params, opts ) {
			return request( 'GET', path, Object.assign( {}, opts || {}, { params: params } ) );
		},
		post: function ( path, body, opts ) {
			return request( 'POST', path, Object.assign( {}, opts || {}, { body: body || {} } ) );
		},
		cfg: cfg,
	};
} )( window );
