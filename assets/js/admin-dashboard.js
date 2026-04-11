/**
 * ScoreFix dashboard: advance render queue via AJAX and update progress until complete.
 */
(function () {
	var cfg = typeof window.scorefixDashboard === 'undefined' ? null : window.scorefixDashboard;
	if (!cfg || !cfg.renderScan || !cfg.renderScan.running) {
		return;
	}

	// Space out admin-ajax traffic: each tick still processes one URL server-side.
	var initialDelayMs = 2000;
	var pollDelayMs = 4500;
	var busy = false;

	function elBar() {
		return document.querySelector('[data-scorefix-render-bar]');
	}
	function elPct() {
		return document.querySelector('[data-scorefix-render-pct]');
	}
	function elCount() {
		return document.querySelector('[data-scorefix-render-count]');
	}

	function updateUI(st) {
		var bar = elBar();
		var pct = elPct();
		var count = elCount();
		var p = typeof st.pct === 'number' ? st.pct : parseInt(st.pct, 10) || 0;
		if (bar) {
			bar.style.width = Math.max(0, Math.min(100, p)) + '%';
		}
		if (pct) {
			pct.textContent = p + '%';
		}
		if (count && typeof st.done === 'number' && typeof st.total === 'number') {
			var t = Math.max(1, st.total);
			var tpl = cfg.renderCountTpl || '%1$d / %2$d';
			count.textContent = tpl.replace('%1$d', String(st.done)).replace('%2$d', String(t));
		}
	}

	function scheduleNext() {
		setTimeout(tick, pollDelayMs);
	}

	function tick() {
		if (busy) {
			scheduleNext();
			return;
		}
		busy = true;

		var body = new URLSearchParams();
		body.append('action', 'scorefix_render_scan_status');
		body.append('nonce', cfg.nonce);

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
			credentials: 'same-origin',
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				if (!data || !data.success || !data.data) {
					scheduleNext();
					return;
				}
				var st = data.data;
				updateUI(st);
				if (!st.running) {
					window.location.reload();
					return;
				}
				scheduleNext();
			})
			.catch(function () {
				scheduleNext();
			})
			.finally(function () {
				busy = false;
			});
	}

	setTimeout(tick, initialDelayMs);
})();
