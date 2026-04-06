/**
 * Bulk RFQ — client-side selection persistence, staging area, and
 * AJAX-driven vendor product filtering.
 *
 * Uses sessionStorage (tab-scoped, auto-clears on tab close) to persist
 * product selections across pagination reloads and AJAX table refreshes.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'bulkrfq_selections';
	var vendorFilterActive = false;

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
				var qtyInput = document.querySelector('.bulkrfq-qty[data-product-id="' + pid + '"]');
				if (qtyInput) {
					qtyInput.value = store[pid].qty;
				}
			} else {
				cb.checked = false;
			}
		}
		updateSelectAllState();
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

	/* ---- AJAX product table rebuild ---- */

	function getSelectedVendorId() {
		// Use jQuery .val() when available — Select2 may not update the
		// native DOM value synchronously on all Dolibarr versions.
		if (typeof jQuery !== 'undefined') {
			var val = jQuery('[name=socid]').val();
			if (val) {
				return parseInt(val, 10) || 0;
			}
		}
		var sel = document.querySelector('select[name="socid"], input[name="socid"]');
		return sel ? (parseInt(sel.value, 10) || 0) : 0;
	}

	function fetchAndRebuildTable(vendorId) {
		var cfg = window.bulkrfqConfig || {};
		if (!cfg.ajaxUrl) {
			return;
		}

		var searchRef = document.querySelector('input[name="search_ref"]');
		var searchLabel = document.querySelector('input[name="search_label"]');

		var params = '?limit=' + (cfg.limit || 25) + '&page=0';
		if (searchRef && searchRef.value) {
			params += '&search_ref=' + encodeURIComponent(searchRef.value);
		}
		if (searchLabel && searchLabel.value) {
			params += '&search_label=' + encodeURIComponent(searchLabel.value);
		}
		if (vendorId > 0) {
			params += '&vendor_id=' + vendorId;
		}

		fetch(cfg.ajaxUrl + params, {credentials: 'same-origin'})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				rebuildTableBody(data.products || [], vendorId);
				updateFilterInfo(vendorId);
				var store = loadStore();
				syncCheckboxesFromStore(store);
			})
			.catch(function () {
				// On error, keep existing table
			});
	}

	function rebuildTableBody(products, vendorId) {
		var cfg = window.bulkrfqConfig || {};
		var tbody = document.getElementById('bulkrfq-tbody');
		var showVendorCols = vendorId > 0;
		var colspan = showVendorCols ? 9 : 7;

		if (products.length === 0) {
			tbody.innerHTML = '<tr class="oddeven"><td colspan="' + colspan + '" class="opacitymedium">' + escapeHtml(cfg.labelNoRecords || 'No records found') + '</td></tr>';
			rebuildTableHeader(showVendorCols);
			return;
		}

		var html = '';
		for (var i = 0; i < products.length; i++) {
			var p = products[i];
			var typeLabel = p.fk_product_type === 1 ? (cfg.labelService || 'Service') : (cfg.labelProduct || 'Product');
			var rowClass = (i % 2 === 0) ? 'oddeven' : 'oddeven';

			html += '<tr class="' + rowClass + '">';
			html += '<td class="center"><input type="checkbox" class="bulkrfq-select" data-product-id="' + p.rowid + '" data-product-ref="' + escapeHtml(p.ref) + '" data-product-label="' + escapeHtml(p.label) + '"></td>';
			html += '<td><a href="' + escapeHtml(cfg.productCardUrl || '') + p.rowid + '" target="_blank">' + escapeHtml(p.ref) + '</a></td>';
			html += '<td>' + escapeHtml(p.label) + '</td>';
			html += '<td>' + escapeHtml(typeLabel) + '</td>';
			html += '<td>' + escapeHtml(p.barcode || '') + '</td>';
			if (showVendorCols) {
				html += '<td>' + escapeHtml(p.supplier_ref || '') + '</td>';
				html += '<td class="right">' + formatPrice(p.supplier_price) + '</td>';
			}
			html += '<td class="right">' + formatPrice(p.price) + '</td>';
			html += '<td class="right"><input type="number" class="bulkrfq-qty flat right" data-product-id="' + p.rowid + '" value="1" step="any" min="0.01" style="width:70px;"></td>';
			html += '</tr>';
		}

		rebuildTableHeader(showVendorCols);
		tbody.innerHTML = html;
	}

	function rebuildTableHeader(showVendorCols) {
		var thead = document.getElementById('bulkrfq-thead');
		if (!thead) {
			return;
		}
		var rows = thead.querySelectorAll('tr');
		// Remove old vendor columns if they exist
		var oldVendorThs = thead.querySelectorAll('.bulkrfq-vendor-col');
		for (var i = 0; i < oldVendorThs.length; i++) {
			oldVendorThs[i].remove();
		}

		if (!showVendorCols) {
			return;
		}

		// Insert vendor columns before Buy Price in the header row (first tr)
		var headerRow = rows[0];
		var buyPriceTh = null;
		var ths = headerRow.querySelectorAll('th');
		// Buy Price is the second-to-last th
		if (ths.length >= 2) {
			buyPriceTh = ths[ths.length - 2];
		}

		if (buyPriceTh) {
			var suppRefTh = document.createElement('th');
			suppRefTh.className = 'bulkrfq-vendor-col';
			suppRefTh.textContent = 'Supplier Ref';
			headerRow.insertBefore(suppRefTh, buyPriceTh);

			var suppPriceTh = document.createElement('th');
			suppPriceTh.className = 'bulkrfq-vendor-col right';
			suppPriceTh.textContent = 'Supplier Price';
			headerRow.insertBefore(suppPriceTh, buyPriceTh);
		}

		// Insert empty tds in the filter row (second tr)
		if (rows.length >= 2) {
			var filterRow = rows[1];
			var filterTds = filterRow.querySelectorAll('td');
			// Insert before the second-to-last td (Buy Price filter cell)
			if (filterTds.length >= 2) {
				var buyPriceTd = filterTds[filterTds.length - 2];

				var td1 = document.createElement('td');
				td1.className = 'bulkrfq-vendor-col';
				filterRow.insertBefore(td1, buyPriceTd);

				var td2 = document.createElement('td');
				td2.className = 'bulkrfq-vendor-col';
				filterRow.insertBefore(td2, buyPriceTd);
			}
		}
	}

	function updateFilterInfo(vendorId) {
		var infoDiv = document.getElementById('bulkrfq-filter-info');
		if (!infoDiv) {
			return;
		}
		var cfg = window.bulkrfqConfig || {};
		if (vendorId > 0) {
			infoDiv.className = 'info bulkrfq-filter-info';
			infoDiv.textContent = cfg.labelVendorInfo || 'Showing vendor products only';
			infoDiv.style.display = '';
		} else {
			infoDiv.style.display = 'none';
		}
	}

	function formatPrice(val) {
		if (val === undefined || val === null || val === '') {
			return '';
		}
		var num = parseFloat(val);
		if (isNaN(num)) {
			return '';
		}
		return num.toFixed(2);
	}

	/* ---- Toggle button state ---- */

	function setToggleState(isVendor) {
		var btnAll = document.getElementById('bulkrfq-show-all');
		var btnVendor = document.getElementById('bulkrfq-show-vendor');
		if (!btnAll || !btnVendor) {
			return;
		}

		vendorFilterActive = isVendor;

		if (isVendor) {
			btnAll.className = btnAll.className.replace('butActionActive', 'butAction');
			btnVendor.className = btnVendor.className.replace('butAction', 'butActionActive');
			if (btnVendor.className.indexOf('butActionActive') === -1) {
				btnVendor.className += ' butActionActive';
			}
		} else {
			btnVendor.className = btnVendor.className.replace('butActionActive', 'butAction');
			btnAll.className = btnAll.className.replace('butAction', 'butActionActive');
			if (btnAll.className.indexOf('butActionActive') === -1) {
				btnAll.className += ' butActionActive';
			}
		}
	}

	function updateVendorButtonState() {
		var btnVendor = document.getElementById('bulkrfq-show-vendor');
		if (!btnVendor) {
			return;
		}
		var socid = getSelectedVendorId();
		btnVendor.disabled = (socid <= 0);
		if (socid <= 0 && vendorFilterActive) {
			// Vendor was deselected while filter was active — switch back
			setToggleState(false);
			fetchAndRebuildTable(0);
		}
	}

	/* ---- Event binding ---- */

	function init() {
		var store = loadStore();

		// Restore state on page load
		syncCheckboxesFromStore(store);
		renderStagingArea(store);
		updateVendorButtonState();

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

		// -- Vendor filter toggle buttons --
		var btnAll = document.getElementById('bulkrfq-show-all');
		var btnVendor = document.getElementById('bulkrfq-show-vendor');

		if (btnAll) {
			btnAll.addEventListener('click', function () {
				if (!vendorFilterActive) {
					return; // already active
				}
				setToggleState(false);
				fetchAndRebuildTable(0);
			});
		}

		if (btnVendor) {
			btnVendor.addEventListener('click', function () {
				var socid = getSelectedVendorId();
				if (socid <= 0) {
					return;
				}
				if (vendorFilterActive) {
					return; // already active
				}
				setToggleState(true);
				fetchAndRebuildTable(socid);
			});
		}

	}

	// Run on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
