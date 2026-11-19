(function () {
	'use strict';
	var C = window.sidcraftVSAdmin, T = C.i18n, app = document.getElementById('svs-app');
	if (!app) { return; }
	var range = { from: '', to: '' }, timer = 0;

	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text != null) { e.textContent = text; } return e; }
	function num(n) { return Number(n || 0).toLocaleString(); }
	function dur(s) { s = Math.round(s || 0); return s >= 60 ? Math.floor(s / 60) + 'm ' + (s % 60) + 's' : s + 's'; }
	function addDays(ymd, n) { var d = new Date(ymd + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); }
	function presets() {
		var t = C.today;
		return [['today', t, t], ['yesterday', addDays(t, -1), addDays(t, -1)], ['d7', addDays(t, -6), t], ['d30', addDays(t, -29), t], ['d90', addDays(t, -89), t], ['month', t.slice(0, 8) + '01', t]];
	}
	function delta(cur, prev, invert) {
		var s = el('span', 'svs-delta');
		if (!prev) { s.textContent = cur ? '▲ new' : '–'; s.className += ' svs-up'; return s; }
		var p = Math.round(100 * (cur - prev) / prev);
		if (p === 0) { s.textContent = '= 0%'; return s; }
		s.textContent = (p > 0 ? '▲ ' : '▼ ') + Math.abs(p) + '%';
		s.className += ((p > 0) !== !!invert) ? ' svs-up' : ' svs-down';
		return s;
	}

	function controls() {
		var bar = el('div', 'svs-bar'), g = el('div', 'svs-presets');
		presets().forEach(function (p) {
			var b = el('button', 'button' + (range.from === p[1] && range.to === p[2] ? ' button-primary' : ''), T[p[0]]); b.type = 'button';
			b.addEventListener('click', function () { range = { from: p[1], to: p[2] }; load(); }); g.appendChild(b);
		});
		bar.appendChild(g);
		var f = el('div', 'svs-custom'), a = el('input'), b2 = el('input'), go = el('button', 'button', T.apply);
		a.type = 'date'; b2.type = 'date'; a.value = range.from; b2.value = range.to; go.type = 'button';
		go.addEventListener('click', function () { if (a.value && b2.value) { range = { from: a.value, to: b2.value }; load(); } });
		f.appendChild(a); f.appendChild(el('span', '', '→')); f.appendChild(b2); f.appendChild(go); bar.appendChild(f);
		var ex = el('a', 'button svs-export', T.export);
		ex.href = C.export + '?action=sidcraft_vs_export&nonce=' + encodeURIComponent(C.exportNonce) + '&_wpnonce=' + encodeURIComponent(C.exportNonce) + '&from=' + range.from + '&to=' + range.to;
		bar.appendChild(ex);
		return bar;
	}

	function cards(d) {
		var w = el('div', 'svs-cards'), s = d.summary, p = d.prev;
		var items = [[T.visitors, num(s.visitors), delta(s.visitors, p.visitors)], [T.views, num(s.views), delta(s.views, p.views)], [T.pps, s.pps, delta(s.pps, p.pps)], [T.bounce, s.bounce + '%', delta(s.bounce, p.bounce, true)], [T.duration, dur(s.duration), delta(s.duration, p.duration)]];
		items.forEach(function (i) {
			var c = el('div', 'svs-card'); c.appendChild(el('div', 'svs-card-l', i[0])); c.appendChild(el('div', 'svs-card-v', i[1])); var x = el('div', 'svs-card-d'); x.appendChild(i[2]); x.appendChild(el('span', 'svs-vs', ' ' + T.vs)); c.appendChild(x); w.appendChild(c);
		});
		var on = el('div', 'svs-card svs-live'); on.appendChild(el('div', 'svs-card-l', '● ' + T.online)); on.appendChild(el('div', 'svs-card-v', num(d.online))); if (d.bots != null) { on.appendChild(el('div', 'svs-card-d', num(d.bots) + ' ' + T.bots)); } w.appendChild(on);
		return w;
	}

	var NS = 'http://www.w3.org/2000/svg';
	function sv(tag, attrs) { var e = document.createElementNS(NS, tag); for (var k in attrs) { e.setAttribute(k, attrs[k]); } return e; }
	function chart(series) {
		var box = el('div', 'svs-panel svs-chart'), W = 900, H = 270, L = 44, R = 12, Tp = 14, B = 28, max = 1;
		series.forEach(function (r) { max = Math.max(max, r.p, r.v); });
		var raw = Math.max(max, 4) / 4, mag = Math.pow(10, Math.floor(Math.log10(raw))), nice = [1, 2, 2.5, 5, 10].filter(function (m) { return m * mag >= raw; })[0] * mag, top = nice * 4;
		var svg = sv('svg', { viewBox: '0 0 ' + W + ' ' + H, class: 'svs-svg', role: 'img' }), n = series.length, iw = W - L - R, ih = H - Tp - B;
		function x(i) { return L + (n === 1 ? iw / 2 : iw * i / (n - 1)); } function y(v) { return Tp + ih - ih * v / top; }
		for (var g = 0; g <= 4; g++) {
			var gy = Tp + ih * g / 4; svg.appendChild(sv('line', { x1: L, x2: W - R, y1: gy, y2: gy, class: 'svs-grid' }));
			var lab = sv('text', { x: L - 8, y: gy + 4, class: 'svs-axis', 'text-anchor': 'end' }); lab.textContent = num(Math.round(top * (4 - g) / 4)); svg.appendChild(lab);
		}
		function line(key, cls) {
			var pts = series.map(function (r, i) { return x(i) + ',' + y(r[key]); });
			svg.appendChild(sv('polygon', { points: x(0) + ',' + (Tp + ih) + ' ' + pts.join(' ') + ' ' + x(n - 1) + ',' + (Tp + ih), class: 'svs-area ' + cls }));
			svg.appendChild(sv('polyline', { points: pts.join(' '), class: 'svs-line ' + cls, fill: 'none' }));
		}
		line('p', 'svs-c1'); line('v', 'svs-c2');
		var every = Math.max(1, Math.ceil(n / 8));
		series.forEach(function (r, i) {
			if (i % every === 0 || i === n - 1) { var t = sv('text', { x: x(i), y: H - 8, class: 'svs-axis', 'text-anchor': i === 0 ? 'start' : (i === n - 1 ? 'end' : 'middle') }); t.textContent = r.d.slice(5); svg.appendChild(t); }
			var hit = sv('rect', { x: x(i) - iw / (2 * Math.max(1, n - 1)), y: Tp, width: iw / Math.max(1, n - 1), height: ih, fill: 'transparent', class: 'svs-hit' });
			var tt = sv('title', {}); tt.textContent = r.d + '\n' + T.views + ': ' + num(r.p) + '\n' + T.visitors + ': ' + num(r.v); hit.appendChild(tt); svg.appendChild(hit);
			if (n <= 45) { svg.appendChild(sv('circle', { cx: x(i), cy: y(r.p), r: 3, class: 'svs-dot svs-c1' })); }
		});
		var lg = el('div', 'svs-legend'); [['svs-c1', T.views], ['svs-c2', T.visitors]].forEach(function (l) { var s = el('span'); s.appendChild(el('i', l[0])); s.appendChild(document.createTextNode(l[1])); lg.appendChild(s); });
		box.appendChild(lg); box.appendChild(svg); return box;
	}

	function list(title, rows, opt) {
		opt = opt || {};
		var box = el('div', 'svs-panel'); box.appendChild(el('h3', '', title));
		if (!rows || !rows.length) { box.appendChild(el('p', 'svs-empty', T.none)); return box; }
		var total = 0; rows.forEach(function (r) { total += r.views; });
		var head = el('div', 'svs-row svs-head'); head.appendChild(el('span', 'svs-name', '')); head.appendChild(el('span', 'svs-num', T.colviews)); head.appendChild(el('span', 'svs-num', T.colvisitors)); box.appendChild(head);
		rows.forEach(function (r) {
			var row = el('div', 'svs-row'), name = el('span', 'svs-name'), label = r.v;
			if (opt.map) { label = opt.map(r.v); }
			if (opt.link) { var a = el('a', '', r.title || label); a.href = opt.link(r.v); a.target = '_blank'; a.rel = 'noopener'; name.appendChild(a); if (r.title) { name.appendChild(el('small', '', r.v)); } }
			else { name.textContent = label; }
			var bar = el('i', 'svs-fill'); bar.style.width = Math.max(2, Math.round(100 * r.views / Math.max(1, total))) + '%';
			row.appendChild(bar); row.appendChild(name); row.appendChild(el('span', 'svs-num', num(r.views))); row.appendChild(el('span', 'svs-num', num(r.visitors))); box.appendChild(row);
		});
		return box;
	}

	function hours(h) {
		var box = el('div', 'svs-panel'); box.appendChild(el('h3', '', T.hours));
		var w = el('div', 'svs-hours'), max = Math.max.apply(null, h.concat([1]));
		h.forEach(function (v, i) { var c = el('div', 'svs-hcol'), b = el('i'); b.style.height = Math.max(2, Math.round(100 * v / max)) + '%'; b.title = i + ':00 – ' + num(v); c.appendChild(b); c.appendChild(el('span', '', i % 3 === 0 ? String(i) : '')); w.appendChild(c); });
		box.appendChild(w); return box;
	}

	var region; try { region = new Intl.DisplayNames(['en'], { type: 'region' }); } catch (e) { region = null; }
	function render(d) {
		app.textContent = '';
		app.appendChild(controls()); app.appendChild(cards(d)); app.appendChild(chart(d.series));
		var g = el('div', 'svs-grid2');
		g.appendChild(list(T.pages, d.pages, { link: function (p) { return p; } }));
		g.appendChild(list(T.sources, d.sources, { map: function (v) { return T[v] || v; } }));
		g.appendChild(list(T.referrers, d.referrers));
		g.appendChild(list(T.countries, d.countries, { map: function (c) { try { return region ? region.of(c) : c; } catch (e) { return c; } } }));
		g.appendChild(list(T.devices, d.devices, { map: function (v) { return T[v] || v; } }));
		g.appendChild(list(T.browsers, d.browsers));
		g.appendChild(list(T.os, d.os));
		g.appendChild(hours(d.hours));
		if (d.campaigns && d.campaigns.length) { g.appendChild(list(T.campaigns, d.campaigns)); }
		if (d.notfound && d.notfound.length) { g.appendChild(list(T.notfound, d.notfound)); }
		app.appendChild(g);
	}

	function load() {
		var q = new URLSearchParams({ action: 'sidcraft_vs_data', nonce: C.nonce, from: range.from, to: range.to });
		fetch(C.ajax + '?' + q.toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
			if (!j || !j.success) { throw new Error('x'); }
			range = { from: j.data.from, to: j.data.to }; render(j.data);
			clearTimeout(timer); if (range.to >= C.today) { timer = setTimeout(load, 60000); }
		}).catch(function () { app.textContent = ''; app.appendChild(el('div', 'notice notice-error inline', T.error)); });
	}
	range = { from: addDays(C.today, -6), to: C.today }; load();
})();
