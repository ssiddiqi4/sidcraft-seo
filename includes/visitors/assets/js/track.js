(function () {
	'use strict';
	var c = window.sidcraftVS;
	if (!c || !c.url || !window.fetch) { return; }
	// wp_localize_script sends every value as a string, so compare with '1' (a '0' string is truthy in JavaScript).
	var respectDnt = String(c.dnt) === '1', isNotFound = String(c.nf) === '1';
	if (respectDnt && (navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl)) { return; }
	var id = 0, key = '', shown = 0, last = Date.now(), sent = 0;
	function post(path, data, keep) {
		return fetch(c.url + path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data), keepalive: !!keep, credentials: 'omit' });
	}
	function tick() { var n = Date.now(); if (document.visibilityState === 'visible') { shown += n - last; } last = n; }
	function leave() {
		tick(); var s = Math.round(shown / 1000);
		if (!id || s < 1 || s <= sent) { return; }
		sent = s; post('/time', { id: id, k: key, s: s }, true).catch(function () {});
	}
	document.addEventListener('visibilitychange', function () { tick(); if (document.visibilityState === 'hidden') { leave(); } });
	window.addEventListener('pagehide', leave);
	post('/hit', { p: location.pathname, t: (document.title || '').slice(0, 150), r: document.referrer || '', q: location.search.slice(0, 300), n: isNotFound ? 1 : 0 })
		.then(function (r) { return r.json(); })
		.then(function (d) { if (d && d.ok) { id = d.id; key = d.k; last = Date.now(); } })
		.catch(function () {});
})();
