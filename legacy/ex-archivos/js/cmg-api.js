/**
 * CaMaGaRe - Helper para URLs de API
 * Centraliza la base URL y construcción de endpoints.
 * 
 * Uso:
 *   cmgApi.url('cliente', { action: 'buscar_clientes', page: 1, q: 'texto' })
 *   cmgApi.base()  // '/sistema'
 */
(function(global) {
	'use strict';
	var BASE = (typeof window !== 'undefined' && window.cmgBase) ? window.cmgBase : '/sistema';

	function getBase() {
		return (BASE || '/sistema').replace(/\/$/, '');
	}

	function url(endpoint, params) {
		var base = getBase();
		var path = endpoint.indexOf('/') === 0 ? endpoint : '/api/' + endpoint + '.php';
		var sep = path.indexOf('?') >= 0 ? '&' : '?';
		if (params && typeof params === 'object') {
			var parts = [];
			for (var k in params) {
				if (params.hasOwnProperty(k) && params[k] !== '' && params[k] !== null && params[k] !== undefined) {
					parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(params[k])));
				}
			}
			if (parts.length) path += sep + parts.join('&');
		}
		return base + path;
	}

	global.cmgApi = {
		base: getBase,
		url: url,
		setBase: function(b) { BASE = b || '/sistema'; }
	};
})(typeof window !== 'undefined' ? window : this);
