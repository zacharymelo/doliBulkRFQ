<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    bulkrfq_wizard.php
 * \ingroup bulkrfq
 * \brief   Bulk Price Request wizard — select products and create a draft Supplier Proposal.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/supplier_proposal/class/supplier_proposal.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
dol_include_once('/bulkrfq/lib/bulkrfq.lib.php');

$langs->loadLangs(array('products', 'supplier_proposal', 'bulkrfq@bulkrfq'));

// Permission check — read access to view the page; create checked on POST action
restrictedArea($user, 'supplier_proposal');

// Parameters
$action      = GETPOST('action', 'aZ09');
$socid       = GETPOSTINT('socid');
$sortfield   = GETPOST('sortfield', 'aZ09');
$sortorder   = GETPOST('sortorder', 'aZ09');
$page        = GETPOSTINT('page');
$limit       = GETPOSTINT('limit') ? GETPOSTINT('limit') : (getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT') ? getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT') : 25);
$search_ref   = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');

if (empty($sortfield)) {
	$sortfield = 'p.ref';
}
if (empty($sortorder)) {
	$sortorder = 'ASC';
}
$offset = $limit * $page;

$form = new Form($db);

/*
 * Actions
 */
if ($action == 'create_proposal' && $user->hasRight('supplier_proposal', 'creer')) {
	$error = 0;

	// Validate vendor
	if ($socid <= 0) {
		setEventMessages($langs->trans('ErrorNoVendorSelected'), null, 'errors');
		$error++;
	} else {
		$vendor = new Societe($db);
		$vendor->fetch($socid);
		if (empty($vendor->fournisseur)) {
			setEventMessages($langs->trans('ErrorVendorNotSupplier'), null, 'errors');
			$error++;
		}
	}

	// Options
	$include_prices = GETPOSTINT('include_prices');

	// Decode selected products
	$selected_json = GETPOST('selected_products', 'restricthtml');
	$selections = array();
	if (!empty($selected_json)) {
		$selections = json_decode($selected_json, true);
	}
	if (empty($selections) || !is_array($selections)) {
		setEventMessages($langs->trans('ErrorNoProductsSelected'), null, 'errors');
		$error++;
	}

	if (!$error) {
		$db->begin();

		$proposal = new SupplierProposal($db);
		$proposal->socid = $socid;

		$result = $proposal->create($user);
		if ($result <= 0) {
			setEventMessages($langs->trans('ErrorCreatingProposal'), null, 'errors');
			setEventMessages($proposal->error, $proposal->errors, 'errors');
			$error++;
		}

		if (!$error) {
			$product_obj = new Product($db);
			foreach ($selections as $sel) {
				$product_id = (int) $sel['id'];
				$qty = (float) $sel['qty'];
				if ($qty <= 0) {
					$qty = 1;
				}

				$product_obj->fetch($product_id);

				// Verify purchasable
				if (empty($product_obj->status_buy)) {
					setEventMessages($langs->trans('ErrorProductNotPurchasable', $product_obj->ref), null, 'warnings');
					continue;
				}

				// Get default VAT rate for this vendor/product
				$txtva = get_default_tva($vendor, $mysoc, $product_id, 0);

				$desc = $product_obj->ref.' - '.$product_obj->label;

				// Use product buy price if checkbox checked, otherwise 0
				// Priority order is configurable via admin setup
				$pu_ht = 0;
				if ($include_prices) {
					$default_priority = 'vendor_price,any_supplier,cost_price,pmp';
					$price_sources = explode(',', getDolGlobalString('BULKRFQ_PRICE_PRIORITY', $default_priority));
					$prodfourn = new ProductFournisseur($db);

					foreach ($price_sources as $source) {
						if ($pu_ht > 0) {
							break;
						}
						switch (trim($source)) {
							case 'vendor_price':
								$result_pf = $prodfourn->find_min_price_product_fournisseur($product_id, 0, $socid);
								if ($result_pf > 0 && !empty($prodfourn->fourn_unitprice)) {
									$pu_ht = (float) $prodfourn->fourn_unitprice;
								}
								break;
							case 'any_supplier':
								$result_pf = $prodfourn->find_min_price_product_fournisseur($product_id, 0, 0);
								if ($result_pf > 0 && !empty($prodfourn->fourn_unitprice)) {
									$pu_ht = (float) $prodfourn->fourn_unitprice;
								}
								break;
							case 'cost_price':
								if (!empty($product_obj->cost_price)) {
									$pu_ht = (float) $product_obj->cost_price;
								}
								break;
							case 'pmp':
								if (!empty($product_obj->pmp)) {
									$pu_ht = (float) $product_obj->pmp;
								}
								break;
						}
					}
				}

				$addline_result = $proposal->addline(
					$desc,          // description
					$pu_ht,         // pu_ht
					$qty,           // qty
					$txtva,         // txtva
					0,              // txlocaltax1
					0,              // txlocaltax2
					$product_id,    // fk_product
					0,              // remise_percent
					'HT'            // price_base_type
				);

				if ($addline_result < 0) {
					setEventMessages($langs->trans('ErrorAddingLine', $product_obj->ref), null, 'errors');
					setEventMessages($proposal->error, $proposal->errors, 'errors');
					$error++;
					break;
				}
			}
		}

		if (!$error) {
			$db->commit();
			$line_count = count($selections);
			setEventMessages($langs->trans('ProposalCreatedSuccess', $proposal->ref, $line_count), null, 'mesgs');
			header('Location: '.DOL_URL_ROOT.'/supplier_proposal/card.php?id='.$proposal->id);
			exit;
		} else {
			$db->rollback();
		}
	}

	// On error, fall through to re-render page
	$action = '';
}

/*
 * View
 */
$title = $langs->trans('BulkPriceRequestTitle');
// Cache-bust JS/CSS with module version to prevent stale browser caches
dol_include_once('/bulkrfq/core/modules/modBulkrfq.class.php');
$bulkrfq_mod = new modBulkrfq($db);
$bulkrfq_ver = $bulkrfq_mod->version;
$morejs = array('/bulkrfq/js/bulkrfq.js?v='.$bulkrfq_ver);
$morecss = array('/bulkrfq/css/bulkrfq.css?v='.$bulkrfq_ver);

llxHeader('', $title, '', '', 0, 0, $morejs, $morecss);

print load_fiche_titre($title, '', 'supplier_proposal');

// Main form wrapper for POST submission
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="bulkrfq-form">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="">';
print '<input type="hidden" name="selected_products" value="" id="bulkrfq-selected-input">';
// -- Vendor selector and options --
print '<div class="fichecenter">';
print '<table class="border centpercent tableforfieldcreate">';
print '<tr>';
print '<td class="titlefieldcreate fieldrequired">'.$langs->trans('SelectVendor').'</td>';
print '<td>';
print $form->select_company($socid, 'socid', 's.fournisseur=1', 'SelectThirdParty', 0, 0, array(), 0, 'minwidth300 maxwidth500');
print '</td>';
print '</tr>';
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans('IncludeBuyPrices').'</td>';
print '<td>';
print '<input type="checkbox" name="include_prices" id="bulkrfq-include-prices" value="1"> ';
print '<span class="opacitymedium small">'.$langs->trans('IncludeBuyPricesDesc').'</span>';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';

// Inline Select2 binding for vendor selector — self-contained, no external JS dependency.
// Matches the pattern from doli-returns/customerreturn_card.php:622-644
print '<script>(function() {
	function onVendorChange() {
		var socEl = document.querySelector("[name=socid]");
		var socid = socEl ? parseInt(socEl.value, 10) || 0 : 0;
		var btn = document.getElementById("bulkrfq-show-vendor");
		if (!btn) return;
		if (socid > 0) {
			btn.className = btn.className.replace("butActionRefused", "butAction");
			btn.title = "";
			// If vendor filter is active, auto-refresh table for the new vendor
			if (typeof window.bulkrfqRefreshVendorFilter === "function") {
				window.bulkrfqRefreshVendorFilter(socid);
			}
		} else {
			btn.className = btn.className.replace(/butAction(Active)?/g, "butActionRefused");
			btn.title = "'.dol_escape_js($langs->trans('SelectVendorFirst')).'";
			// Vendor cleared — switch back to all products if filter was active
			if (typeof window.bulkrfqResetToAll === "function") {
				window.bulkrfqResetToAll();
			}
		}
	}

	if (typeof jQuery !== "undefined") {
		jQuery(document).on("select2:select select2:clear", "[name=socid]", onVendorChange);
	}
	document.addEventListener("change", function(e) {
		if (e.target && e.target.name === "socid") { onVendorChange(); }
	});

	// Enable vendor button on load if a vendor is already selected (e.g. from URL param)
	// Only update the button state — do NOT trigger a filter refresh
	var initEl = document.querySelector("[name=socid]");
	if (initEl && parseInt(initEl.value, 10) > 0) {
		var btn = document.getElementById("bulkrfq-show-vendor");
		if (btn) {
			btn.className = btn.className.replace("butActionRefused", "butAction");
			btn.title = "";
		}
	}
})();</script>';

// -- Product list (initial load: always all products) --
$filters = array(
	'search_ref'   => $search_ref,
	'search_label' => $search_label,
);

$nbtotalofrecords = bulkrfqCountProducts($db, $filters);
$products = bulkrfqFetchProducts($db, $sortfield, $sortorder, $limit, $offset, $filters);

$param = '';
if (!empty($search_ref)) {
	$param .= '&search_ref='.urlencode($search_ref);
}
if (!empty($search_label)) {
	$param .= '&search_label='.urlencode($search_label);
}
if ($socid > 0) {
	$param .= '&socid='.$socid;
}

// Pass config to JS for AJAX product fetching
print '<script>var bulkrfqConfig = {';
print 'ajaxUrl: "'.dol_escape_js(dol_buildpath('/bulkrfq/ajax/products.php', 1)).'",';
print 'productCardUrl: "'.dol_escape_js(DOL_URL_ROOT.'/product/card.php?id=').'",';
print 'limit: '.(int) $limit.',';
print 'labelProduct: "'.dol_escape_js($langs->trans('Product')).'",';
print 'labelService: "'.dol_escape_js($langs->trans('Service')).'",';
print 'labelNoRecords: "'.dol_escape_js($langs->trans('NoRecordFound')).'",';
print 'labelVendorInfo: "'.dol_escape_js($langs->trans('VendorFilterInfo')).'",';
print 'labelAllInfo: "'.dol_escape_js($langs->trans('AllProductsInfo')).'"';
print '};</script>';

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" id="bulkrfq-search-form">';
if ($socid > 0) {
	print '<input type="hidden" name="socid" value="'.$socid.'">';
}

// -- View toggle: All Products vs Vendor's Products (JS-driven, no reload) --
// Use <a> tags with butAction/butActionRefused — Dolibarr styles these correctly.
// <button> elements inherit browser defaults that break Dolibarr's CSS.
print '<div class="bulkrfq-view-toggle marginbottomonly">';
print '<a href="#" id="bulkrfq-show-all" class="butAction bulkrfq-toggle-btn bulkrfq-active">';
print '<span class="fa fa-list paddingright"></span>'.$langs->trans('ShowAllProducts');
print '</a> ';
print '<a href="#" id="bulkrfq-show-vendor" class="butActionRefused classfortooltip bulkrfq-toggle-btn" title="'.$langs->trans('SelectVendorFirst').'">';
print '<span class="fa fa-filter paddingright"></span>'.$langs->trans('ShowVendorProducts');
print '</a>';
print '</div>';
print '<div id="bulkrfq-filter-info"></div>';

print_barre_liste(
	$langs->trans('Products'),
	$page,
	$_SERVER['PHP_SELF'],
	$param,
	$sortfield,
	$sortorder,
	'',
	count($products),
	$nbtotalofrecords,
	'product',
	0,
	'',
	'',
	$limit,
	0,
	0,
	1
);

print '<table class="noborder centpercent" id="bulkrfq-product-table">';

// Column headers
print '<thead id="bulkrfq-thead">';
print '<tr class="liste_titre">';
print '<th class="center" width="30"><input type="checkbox" id="bulkrfq-select-all" title="'.$langs->trans('SelectAll').'"></th>';
print_liste_field_titre($langs->trans('ProductRef'), $_SERVER['PHP_SELF'], 'p.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('ProductLabel'), $_SERVER['PHP_SELF'], 'p.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('ProductType'), $_SERVER['PHP_SELF'], 'p.fk_product_type', '', $param, '', $sortfield, $sortorder);
print '<th>'.$langs->trans('Barcode').'</th>';
print_liste_field_titre($langs->trans('BuyPrice'), $_SERVER['PHP_SELF'], 'p.price', '', $param, 'class="right"', $sortfield, $sortorder);
print '<th class="right">'.$langs->trans('Qty').'</th>';
print '</tr>';

// Search filter row
print '<tr class="liste_titre">';
print '<td></td>';
print '<td><input type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" class="maxwidth100" placeholder="..."></td>';
print '<td><input type="text" name="search_label" value="'.dol_escape_htmltag($search_label).'" class="maxwidth150" placeholder="..."></td>';
print '<td></td>';
print '<td></td>';
print '<td></td>';
print '<td class="right"><button type="submit" class="liste_titre button_search" name="button_search" value="x"><span class="fa fa-search"></span></button>';
print ' <button type="submit" class="liste_titre button_removefilter" name="button_removefilter" value="x"><span class="fa fa-remove"></span></button></td>';
print '</tr>';
print '</thead>';

// Product rows
print '<tbody id="bulkrfq-tbody">';
if (!empty($products)) {
	foreach ($products as $prod) {
		$product_type_label = ($prod->fk_product_type == 1) ? $langs->trans('Service') : $langs->trans('Product');
		$product_url = DOL_URL_ROOT.'/product/card.php?id='.$prod->rowid;

		print '<tr class="oddeven">';
		print '<td class="center">';
		print '<input type="checkbox" class="bulkrfq-select" data-product-id="'.$prod->rowid.'" data-product-ref="'.dol_escape_htmltag($prod->ref).'" data-product-label="'.dol_escape_htmltag($prod->label).'">';
		print '</td>';
		print '<td><a href="'.$product_url.'" target="_blank">'.dol_escape_htmltag($prod->ref).'</a></td>';
		print '<td>'.dol_escape_htmltag($prod->label).'</td>';
		print '<td>'.$product_type_label.'</td>';
		print '<td>'.dol_escape_htmltag($prod->barcode).'</td>';
		print '<td class="right">'.price($prod->price).'</td>';
		print '<td class="right"><input type="number" class="bulkrfq-qty flat right" data-product-id="'.$prod->rowid.'" value="1" step="any" min="0.01" style="width:70px;"></td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="7" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}
print '</tbody>';

print '</table>';
print '</form>';

// -- Staging area --
print '<div id="bulkrfq-staging" class="bulkrfq-staging">';
print '<div id="bulkrfq-staging-empty" class="bulkrfq-staging-empty opacitymedium">';
print $langs->trans('NoProductsSelected');
print '</div>';
print '<div id="bulkrfq-staging-content" style="display:none;">';
print '<div class="bulkrfq-staging-header">';
print '<span id="bulkrfq-staging-count" class="badge badge-info"></span>';
print ' <a href="#" id="bulkrfq-clear-all" class="bulkrfq-clear-link">'.$langs->trans('ClearAll').'</a>';
print '</div>';
print '<table class="noborder centpercent" id="bulkrfq-staging-table">';
print '<thead><tr class="liste_titre">';
print '<th>'.$langs->trans('ProductRef').'</th>';
print '<th>'.$langs->trans('ProductLabel').'</th>';
print '<th class="right">'.$langs->trans('Qty').'</th>';
print '<th class="center" width="40"></th>';
print '</tr></thead>';
print '<tbody id="bulkrfq-staging-body"></tbody>';
print '</table>';
print '</div>';
print '</div>';

// -- Create button --
print '<div id="bulkrfq-create-wrapper" class="bulkrfq-create-wrapper" style="display:none;">';
print '<a href="#" id="bulkrfq-create-btn" class="butAction">'.$langs->trans('CreatePriceRequest').'</a>';
print '</div>';

print '</form>';

llxFooter();
$db->close();
