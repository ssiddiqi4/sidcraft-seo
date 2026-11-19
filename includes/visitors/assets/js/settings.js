(function () {
	'use strict';
	var b = document.getElementById('svs-add-ip'), t = document.getElementById('svs-ips'), m = document.getElementById('svs-add-ip-msg'), L = window.sidcraftVSSettings || {};
	if (!b || !t) { return; }
	b.addEventListener('click', function () {
		var ip = b.getAttribute('data-ip'), lines = t.value.split(/\r?\n/).map(function (x) { return x.trim(); }).filter(Boolean);
		if (lines.indexOf(ip) > -1) { m.textContent = L.already || ''; return; }
		lines.push(ip); t.value = lines.join('\n'); m.textContent = (L.added || '') + ': ' + ip;
	});
})();
