/**
 * Bulk RFQ — client-side selection persistence and staging area.
 *
 * Uses sessionStorage (tab-scoped, auto-clears on tab close) to persist
 * product selections across pagination reloads.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'bulkrfq_selections';

	/* ---- Storage helpers ---- */

	function loadStore() {
		try {
			var raw = sessionStorage.getItem(STORAGE_KEY);
			return raw ? JSON.parse(raw) : {};
		} catch (e) {
			return {};
		}
	}

	function saveStore(store) {
		sessionStorage.setItem(STORAGE_KEY, JSON.stringify(store));
	}

	function storeCount(store) {
		return Object.keys(store).length;
	}

	/* ---- Staging area rendering ---- */

	function renderStagingArea(store) {
		var emptyEl = document.getElementById('bulkrfq-staging-empty');
		var contentEl = document.getElementById('bulkrfq-staging-content');
		var countEl = document.getElementById('bulkrfq-staging-count');
		var bodyEl = document.getElementById('bulkrfq-staging-body');
		var createWrapper = document.getElementById('bulkrfq-create-wrapper');
		var count = storeCount(store);

		if (count === 0) {
			emptyEl.style.display = '';
			contentEl.style.display = 'none';
			if (createWrapper) {
				createWrapper.style.display = 'none';
			}
			return;
		}

		emptyEl.style.display = 'none';
		contentEl.style.display = '';
		if (createWrapper) {
			createWrapper.style.display = '';
		}
		countEl.textContent = count + ' product(s) selected';

		// Build table body
		var html = '';
		var ids = Object.keys(store);
		ids.sort(function (a, b) {
			return (store[a].ref || '').localeCompare(store[b].ref || '');
		});
		for (var i = 0; i < ids.length; i++) {
			var item = store[ids[i]];
			html += '<tr data-staging-id="' + item.id + '">';
			html += '<td>' + escapeHtml(item.ref) + '</td>';
			html += '<td>' + escapeHtml(item.label) + '</td>';
			html += '<td class="right"><input type="number" class="bulkrfq-staging-qty flat right" data-product-id="' + item.id + '" value="' + item.qty + '" step="any" min="0.01" style="width:70px;"></td>';
			html += '<td class="center"><a href="#" class="bulkrfq-staging-remove" data-product-id="' + item.id + '" title="Remove"><span class="fa fa-trash" style="color:#bc3434;"></span></a></td>';
			html += '</tr>';
		}
		bodyEl.innerHTML = html;
	}

	function escapeHtml(str) {
		if (!str) {
			return '';
		}
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	/* ---- Sync page checkboxes with store ---- */

	function syncCheckboxesFromStore(store) {
		var checkboxes = document.querySelectorAll('.bulkrfq-select');
		for (var i = 0; i < checkboxes.length; i++) {
			var cb = checkboxes[i];
			var pid = cb.getAttribute('data-product-id');
			if (store[pid]) {
				cb.checked = true;
				// Sync qty input too
				var qtyInput = document.querySelector('.bulkrfq-qty[data-product-id="' + pid + '"]');
				if (qtyInput) {
					qtyInput.value = store[pid].qty;
				}
			} else {
				cb.checked = false;
			}
		}
		updateSelectAllState(store);
	}

	function updateSelectAllState() {
		var selectAll = document.getElementById('bulkrfq-select-all');
		if (!selectAll) {
			return;
		}
		var checkboxes = document.querySelectorAll('.bulkrfq-select');
		if (checkboxes.length === 0) {
			selectAll.checked = false;
			return;
		}
		var allChecked = true;
		for (var i = 0; i < checkboxes.length; i++) {
			if (!checkboxes[i].checked) {
				allChecked = false;
				break;
			}
		}
		selectAll.checked = allChecked;
	}

	/* ---- Event binding ---- */

	function init() {
		var store = loadStore();

		// Restore state on page load
		syncCheckboxesFromStore(store);
		renderStagingArea(store);

		// Checkbox change
		document.addEventListener('change', function (e) {
			if (!e.target.classList.contains('bulkrfq-select')) {
				return;
			}
			var cb = e.target;
			var pid = cb.getAttribute('data-product-id');
			store = loadStore();

			if (cb.checked) {
				var qtyInput = document.querySelector('.bulkrfq-qty[data-product-id="' + pid + '"]');
				var qty = qtyInput ? parseFloat(qtyInput.value) : 1;
				if (isNaN(qty) || qty <= 0) {
					qty = 1;
				}
				store[pid] = {
					id: parseInt(pid, 10),
					ref: cb.getAttribute('data-product-ref') || '',
					label: cb.getAttribute('data-product-label') || '',
					qty: qty
				};
			} else {
				delete store[pid];
			}

			saveStore(store);
			renderStagingArea(store);
			updateSelectAllState();
		});

		// Qty input change in product list
		document.addEventListener('input', function (e) {
			if (!e.target.classList.contains('bulkrfq-qty')) {
				return;
			}
			var pid = e.target.getAttribute('data-product-id');
			store = loadStore();
			if (store[pid]) {
				var qty = parseFloat(e.target.value);
				if (isNaN(qty) || qty <= 0) {
					qty = 1;
				}
				store[pid].qty = qty;
				saveStore(store);
				renderStagingArea(store);
			}
		});

		// Select All checkbox
		var selectAll = document.getElementById('bulkrfq-select-all');
		if (selectAll) {
			selectAll.addEventListener('change', function () {
				var checkboxes = document.querySelectorAll('.bulkrfq-select');
				store = loadStore();
				for (var i = 0; i < checkboxes.length; i++) {
					var cb = checkboxes[i];
					var pid = cb.getAttribute('data-product-id');
					cb.checked = selectAll.checked;
					if (selectAll.checked) {
						if (!store[pid]) {
							var qtyInput = document.querySelector('.bulkrfq-qty[data-product-id="' + pid + '"]');
							var qty = qtyInput ? parseFloat(qtyInput.value) : 1;
							if (isNaN(qty) || qty <= 0) {
								qty = 1;
							}
							store[pid] = {
								id: parseInt(pid, 10),
								ref: cb.getAttribute('data-product-ref') || '',
								label: cb.getAttribute('data-product-label') || '',
								qty: qty
							};
						}
					} else {
						delete store[pid];
					}
				}
				saveStore(store);
				renderStagingArea(store);
			});
		}

		// Staging area events (delegated)
		document.addEventListener('click', function (e) {
			// Remove button
			var removeBtn = e.target.closest('.bulkrfq-staging-remove');
			if (removeBtn) {
				e.preventDefault();
				var pid = removeBtn.getAttribute('data-product-id');
				store = loadStore();
				delete store[pid];
				saveStore(store);
				renderStagingArea(store);
				syncCheckboxesFromStore(store);
				return;
			}

			// Clear All
			if (e.target.id === 'bulkrfq-clear-all' || e.target.closest('#bulkrfq-clear-all')) {
				e.preventDefault();
				store = {};
				saveStore(store);
				renderStagingArea(store);
				syncCheckboxesFromStore(store);
				return;
			}

			// Create button
			if (e.target.id === 'bulkrfq-create-btn' || e.target.closest('#bulkrfq-create-btn')) {
				e.preventDefault();
				store = loadStore();
				if (storeCount(store) === 0) {
					alert('No products selected.');
					return;
				}

				// Serialize selections into hidden input
				var selections = [];
				var ids = Object.keys(store);
				for (var i = 0; i < ids.length; i++) {
					selections.push({
						id: store[ids[i]].id,
						qty: store[ids[i]].qty
					});
				}

				var form = document.getElementById('bulkrfq-form');
				document.getElementById('bulkrfq-selected-input').value = JSON.stringify(selections);
				form.querySelector('input[name="action"]').value = 'create_proposal';

				// Clear sessionStorage so it doesn't persist after successful creation
				sessionStorage.removeItem(STORAGE_KEY);

				form.submit();
				return;
			}
		});

		// Staging area qty change (delegated)
		document.addEventListener('input', function (e) {
			if (!e.target.classList.contains('bulkrfq-staging-qty')) {
				return;
			}
			var pid = e.target.getAttribute('data-product-id');
			store = loadStore();
			if (store[pid]) {
				var qty = parseFloat(e.target.value);
				if (isNaN(qty) || qty <= 0) {
					qty = 1;
				}
				store[pid].qty = qty;
				saveStore(store);
				// Also sync the product list qty input if visible
				var listQty = document.querySelector('.bulkrfq-qty[data-product-id="' + pid + '"]');
				if (listQty) {
					listQty.value = qty;
				}
			}
		});

		// Handle search reset button — clear search fields
		document.addEventListener('click', function (e) {
			if (e.target.closest('.button_removefilter')) {
				var searchRef = document.querySelector('input[name="search_ref"]');
				var searchLabel = document.querySelector('input[name="search_label"]');
				if (searchRef) {
					searchRef.value = '';
				}
				if (searchLabel) {
					searchLabel.value = '';
				}
			}
		});
	}

	// Run on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
