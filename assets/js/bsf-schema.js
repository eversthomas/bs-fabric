/**
 * bs-fabric — Schema UI (Fields sheet, filters, template builder menu)
 */
(function () {
	'use strict';

	function slugify(value) {
		return (value || '')
			.toLowerCase()
			.replace(/ä/g, 'ae')
			.replace(/ö/g, 'oe')
			.replace(/ü/g, 'ue')
			.replace(/ß/g, 'ss')
			.replace(/[^a-z0-9]+/g, '_')
			.replace(/^_+|_+$/g, '');
	}

	function closeSheetNavigate(url) {
		if (url) {
			window.location.href = url;
		}
	}

	function initFieldSheet() {
		var sheet = document.getElementById('bsf-field-sheet');
		if (!sheet) {
			return;
		}

		var scrim = document.querySelector('[data-bsf-field-scrim]');
		var listUrl = sheet.getAttribute('data-list-url') || '';
		var labelInput = sheet.querySelector('[data-bsf-field-label]');
		var nameInput = sheet.querySelector('[data-bsf-field-name]');
		var typeInput = sheet.querySelector('[data-bsf-field-type]');
		var isNew = sheet.getAttribute('data-is-new') === '1';
		var keyTouched = !isNew;

		function close() {
			closeSheetNavigate(listUrl);
		}

		if (scrim) {
			scrim.classList.add('is-open');
			scrim.addEventListener('click', close);
		}

		sheet.classList.add('is-open');

		sheet.querySelectorAll('[data-bsf-sheet-close]').forEach(function (el) {
			el.addEventListener('click', close);
		});

		document.addEventListener('keydown', function onKey(event) {
			if (event.key === 'Escape') {
				close();
				document.removeEventListener('keydown', onKey);
			}
		});

		if (labelInput && nameInput && isNew) {
			labelInput.addEventListener('input', function () {
				if (!keyTouched) {
					nameInput.value = slugify(labelInput.value);
				}
			});

			nameInput.addEventListener('input', function () {
				keyTouched = true;
				nameInput.value = slugify(nameInput.value);
			});
		}

		sheet.querySelectorAll('.bsf-type-option').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (btn.disabled || !typeInput) {
					return;
				}

				var type = btn.getAttribute('data-type');
				typeInput.value = type;

				sheet.querySelectorAll('.bsf-type-option').forEach(function (other) {
					other.classList.toggle('is-selected', other.getAttribute('data-type') === type);
				});
			});
		});
	}

	function initTemplateSheet() {
		var sheet = document.getElementById('bsf-template-sheet');
		if (!sheet) {
			return;
		}

		var scrim = document.querySelector('[data-bsf-template-scrim]');
		var listUrl = sheet.getAttribute('data-list-url') || '';

		function close() {
			closeSheetNavigate(listUrl);
		}

		if (scrim) {
			scrim.classList.add('is-open');
			scrim.addEventListener('click', close);
		}

		sheet.classList.add('is-open');

		sheet.querySelectorAll('[data-bsf-sheet-close]').forEach(function (el) {
			el.addEventListener('click', close);
		});

		var labelInput = sheet.querySelector('[data-bsf-template-label]');
		var nameInput = sheet.querySelector('[data-bsf-template-name]');
		var keyTouched = false;

		if (labelInput && nameInput) {
			labelInput.addEventListener('input', function () {
				if (!keyTouched) {
					nameInput.value = slugify(labelInput.value);
				}
			});

			nameInput.addEventListener('input', function () {
				keyTouched = true;
				nameInput.value = slugify(nameInput.value);
			});
		}
	}

	function initFieldFilters() {
		var root = document.getElementById('bsf-fields-table-root');
		if (!root) {
			return;
		}

		var searchInput = document.getElementById('bsf-fields-search');
		var typeSelect = document.getElementById('bsf-fields-type-filter');
		var rows = root.querySelectorAll('[data-bsf-field-row]');

		function apply() {
			var q = searchInput ? searchInput.value.toLowerCase().trim() : '';
			var type = typeSelect ? typeSelect.value : '';

			rows.forEach(function (row) {
				var label = (row.getAttribute('data-label') || '').toLowerCase();
				var name = (row.getAttribute('data-name') || '').toLowerCase();
				var rowType = row.getAttribute('data-type') || '';
				var matchQ = !q || label.indexOf(q) !== -1 || name.indexOf(q) !== -1;
				var matchType = !type || rowType === type;
				var show = matchQ && matchType;

				if (show) {
					row.removeAttribute('data-bsf-filter-hidden');
				} else {
					row.setAttribute('data-bsf-filter-hidden', 'true');
				}
			});
		}

		if (searchInput) {
			searchInput.addEventListener('input', apply);
		}

		if (typeSelect) {
			typeSelect.addEventListener('change', apply);
		}
	}

	function initAddFieldMenu() {
		var wrap = document.querySelector('.bsf-add-field-wrap');
		if (!wrap) {
			return;
		}

		var toggle = wrap.querySelector('[data-bsf-add-field-toggle]');
		var menu = wrap.querySelector('.bsf-add-field-menu');
		var search = wrap.querySelector('[data-bsf-add-field-search]');

		if (!toggle || !menu) {
			return;
		}

		function closeMenu() {
			menu.setAttribute('hidden', 'hidden');
		}

		toggle.addEventListener('click', function (event) {
			event.stopPropagation();
			if (menu.hasAttribute('hidden')) {
				menu.removeAttribute('hidden');
				if (search) {
					search.value = '';
					menu.querySelectorAll('[data-bsf-add-field-item]').forEach(function (item) {
						item.removeAttribute('data-bsf-filter-hidden');
					});
					search.focus();
				}
			} else {
				closeMenu();
			}
		});

		document.addEventListener('click', function (event) {
			if (!(event.target instanceof Node) || !wrap.contains(event.target)) {
				closeMenu();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeMenu();
			}
		});

		if (search) {
			search.addEventListener('input', function () {
				var q = search.value.toLowerCase().trim();
				menu.querySelectorAll('[data-bsf-add-field-item]').forEach(function (item) {
					var label = (item.getAttribute('data-label') || '').toLowerCase();
					var name = (item.getAttribute('data-name') || '').toLowerCase();
					var show = !q || label.indexOf(q) !== -1 || name.indexOf(q) !== -1;

					if (show) {
						item.removeAttribute('data-bsf-filter-hidden');
					} else {
						item.setAttribute('data-bsf-filter-hidden', 'true');
					}
				});
			});
		}

		menu.querySelectorAll('[data-bsf-add-field-item]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var fieldId = btn.getAttribute('data-field-id');
				var select = document.getElementById('bsf-available-field-hidden');
				if (select && fieldId) {
					select.value = fieldId;
				}
				var assignBtn = document.querySelector('.bs-fabric-assign-field');
				if (assignBtn) {
					assignBtn.click();
				}
				closeMenu();
			});
		});
	}

	function initSwitches(root) {
		var scope = root || document;

		scope.querySelectorAll('.bsf-switch[data-for]:not([data-bsf-switch-bound])').forEach(function (sw) {
			var targetId = sw.getAttribute('data-for');
			var checkbox = targetId ? document.getElementById(targetId) : null;

			if (!checkbox) {
				return;
			}

			sw.setAttribute('data-bsf-switch-bound', '1');

			function sync() {
				sw.classList.toggle('is-on', checkbox.checked);
				sw.setAttribute('aria-pressed', checkbox.checked ? 'true' : 'false');
			}

			// <label for="…"> toggles natively — only sync classes (no second toggle).
			if (sw.tagName === 'LABEL') {
				checkbox.addEventListener('change', sync);
				sync();
				return;
			}

			sw.addEventListener('click', function () {
				checkbox.checked = !checkbox.checked;
				sync();
				checkbox.dispatchEvent(new Event('change', { bubbles: true }));
			});

			checkbox.addEventListener('change', sync);
			sync();
		});

		initFabricMenuDependency();
	}

	/**
	 * WP-Hauptmenü nur sinnvoll, wenn der Fabric-Unterpunkt aktiv ist.
	 */
	function initFabricMenuDependency() {
		var fabricMenu = document.getElementById('bs-fabric-show-in-menu');
		var wpMenu = document.getElementById('bs-fabric-show-in-wp-menu');
		var wpSwitch = document.querySelector('.bsf-switch[data-for="bs-fabric-show-in-wp-menu"]');

		if (!fabricMenu || !wpMenu || !wpSwitch) {
			return;
		}

		function apply() {
			var enabled = fabricMenu.checked;
			wpSwitch.classList.toggle('is-disabled', !enabled);
			wpSwitch.setAttribute('aria-disabled', enabled ? 'false' : 'true');

			if (!enabled && wpMenu.checked) {
				wpMenu.checked = false;
				wpSwitch.classList.remove('is-on');
				wpSwitch.setAttribute('aria-pressed', 'false');
				wpSwitch.setAttribute('aria-checked', 'false');
			}
		}

		// Klick auf deaktivierten Switch abfangen (label würde sonst trotzdem toggeln).
		wpSwitch.addEventListener('click', function (event) {
			if (!fabricMenu.checked) {
				event.preventDefault();
			}
		});

		fabricMenu.addEventListener('change', apply);
		apply();
	}

	document.addEventListener('DOMContentLoaded', function () {
		initFieldSheet();
		initTemplateSheet();
		initFieldFilters();
		initAddFieldMenu();
		initSwitches();
	});
})();
