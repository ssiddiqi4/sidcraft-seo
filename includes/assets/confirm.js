/* Sidcraft: a confirmation box that cannot be switched off by "Don't allow this page to prompt you again". */
(function () {
	'use strict';
	var open = null;
	function ask(message, onYes) {
		if (open) { open.remove(); }
		var ov = document.createElement('div'), box = document.createElement('div'), p = document.createElement('p'), row = document.createElement('div'), yes = document.createElement('button'), no = document.createElement('button');
		ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200000;display:flex;align-items:center;justify-content:center;padding:16px';
		box.style.cssText = 'background:#fff;border-radius:8px;max-width:440px;width:100%;padding:22px 24px;box-shadow:0 12px 40px rgba(0,0,0,.35)';
		p.style.cssText = 'margin:0 0 18px;font-size:15px;line-height:1.5;color:#1d2327'; p.textContent = message;
		row.style.cssText = 'display:flex;gap:10px;justify-content:flex-end';
		yes.type = 'button'; yes.className = 'button button-primary'; yes.textContent = (window.sidcraftConfirmText && window.sidcraftConfirmText.yes) || 'Yes, continue';
		no.type = 'button'; no.className = 'button'; no.textContent = (window.sidcraftConfirmText && window.sidcraftConfirmText.no) || 'Cancel';
		function close() { ov.remove(); open = null; document.removeEventListener('keydown', key, true); }
		function key(e) { if (e.key === 'Escape') { e.preventDefault(); close(); } }
		yes.addEventListener('click', function () { close(); onYes(); }); no.addEventListener('click', close);
		ov.addEventListener('click', function (e) { if (e.target === ov) { close(); } });
		document.addEventListener('keydown', key, true);
		row.appendChild(no); row.appendChild(yes); box.appendChild(p); box.appendChild(row); ov.appendChild(box); document.body.appendChild(ov); open = ov; no.focus();
	}
	window.sidcraftConfirm = ask;

	document.addEventListener('click', function (e) {
		var b = e.target.closest('button[data-sidcraft-confirm],input[type=submit][data-sidcraft-confirm]');
		if (!b) { return; }
		if (b.dataset.ok === '1') { delete b.dataset.ok; return; }
		e.preventDefault(); e.stopPropagation();
		ask(b.getAttribute('data-sidcraft-confirm'), function () { b.dataset.ok = '1'; b.click(); });
	}, true);
	document.addEventListener('submit', function (e) {
		var f = e.target;
		if (!f || !f.getAttribute || !f.getAttribute('data-sidcraft-confirm')) { return; }
		if (f.dataset.ok === '1') { delete f.dataset.ok; return; }
		e.preventDefault();
		var s = e.submitter;
		ask(f.getAttribute('data-sidcraft-confirm'), function () { f.dataset.ok = '1'; if (f.requestSubmit) { f.requestSubmit(s || undefined); } else { f.submit(); } });
	}, true);
})();
