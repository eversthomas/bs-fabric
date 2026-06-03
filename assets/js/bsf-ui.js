/**
 * bs-fabric — Toast & Sheet (Vanilla JS)
 */
(function () {
	'use strict';

	var toastRoot = null;

	function getToastRoot() {
		if (toastRoot) {
			return toastRoot;
		}
		toastRoot = document.getElementById('bsf-toast-root');
		return toastRoot;
	}

	function iconCheck() {
		return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7"/></svg>';
	}

	function pushToast(message, durationMs) {
		var root = getToastRoot();
		if (!root || !message) {
			return;
		}

		var el = document.createElement('div');
		el.className = 'bsf-toast';
		el.setAttribute('role', 'status');
		el.innerHTML = iconCheck() + document.createTextNode(message).textContent;
		el.textContent = '';
		el.insertAdjacentHTML('afterbegin', iconCheck());
		el.appendChild(document.createTextNode(message));
		root.appendChild(el);

		window.setTimeout(function () {
			if (el.parentNode) {
				el.parentNode.removeChild(el);
			}
		}, durationMs || 2600);
	}

	function closeSheet(scrim, sheet, onClose) {
		if (scrim && scrim.parentNode) {
			scrim.parentNode.removeChild(scrim);
		}
		if (sheet && sheet.parentNode) {
			sheet.parentNode.removeChild(sheet);
		}
		document.removeEventListener('keydown', onClose);
	}

	function openSheet(options) {
		options = options || {};
		var title = options.title || '';
		var subtitle = options.subtitle || '';
		var bodyHtml = options.bodyHtml || '';
		var footerHtml = options.footerHtml || '';
		var onClose = options.onClose || function () {};

		var scrim = document.createElement('div');
		scrim.className = 'bsf-scrim';

		var sheet = document.createElement('aside');
		sheet.className = 'bsf-sheet';
		sheet.setAttribute('role', 'dialog');
		sheet.setAttribute('aria-modal', 'true');
		if (title) {
			sheet.setAttribute('aria-label', title);
		}

		var head = document.createElement('div');
		head.className = 'bsf-sheet-head';
		head.innerHTML =
			'<div class="bsf-col"><h2></h2><span class="bsf-muted" style="font-size:12.5px"></span></div>' +
			'<button type="button" class="bsf-btn bsf-btn-subtle bsf-btn-sm" data-bsf-sheet-close aria-label="Schließen">×</button>';
		head.querySelector('h2').textContent = title;
		var subEl = head.querySelector('.bsf-muted');
		if (subtitle) {
			subEl.textContent = subtitle;
		} else {
			subEl.remove();
		}

		var body = document.createElement('div');
		body.className = 'bsf-sheet-body';
		body.innerHTML = bodyHtml;

		sheet.appendChild(head);
		sheet.appendChild(body);

		if (footerHtml) {
			var foot = document.createElement('div');
			foot.className = 'bsf-sheet-foot';
			foot.innerHTML = footerHtml;
			sheet.appendChild(foot);
		}

		var app = document.querySelector('.bsf-app');
		if (!app) {
			return null;
		}

		app.appendChild(scrim);
		app.appendChild(sheet);

		function handleClose() {
			closeSheet(scrim, sheet, handleEscape);
			onClose();
		}

		function handleEscape(event) {
			if (event.key === 'Escape') {
				handleClose();
			}
		}

		scrim.addEventListener('click', handleClose);
		head.querySelector('[data-bsf-sheet-close]').addEventListener('click', handleClose);
		document.addEventListener('keydown', handleEscape);

		return { close: handleClose, sheet: sheet, body: body };
	}

	window.bsFabricUi = {
		toast: pushToast,
		openSheet: openSheet,
	};
})();
