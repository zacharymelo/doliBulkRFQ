<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/setup.php
 * \ingroup bulkrfq
 * \brief   Bulk RFQ module setup page.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array('admin', 'bulkrfq@bulkrfq'));

if (!$user->admin) {
	accessforbidden();
}

// Valid price source keys
$valid_sources = array('vendor_price', 'any_supplier', 'cost_price', 'pmp');

$action = GETPOST('action', 'aZ09');

// Save settings
if ($action == 'update') {
	// Checkbox settings
	$val = GETPOST('BULKRFQ_DEBUG_MODE', 'alpha');
	dolibarr_set_const($db, 'BULKRFQ_DEBUG_MODE', $val, 'chaine', 0, '', $conf->entity);

	// Price priority — read comma-separated order from hidden input
	$raw_order = GETPOST('price_priority_order', 'alpha');
	$priority = array();
	if (!empty($raw_order)) {
		foreach (explode(',', $raw_order) as $src) {
			$src = trim($src);
			if (in_array($src, $valid_sources) && !in_array($src, $priority)) {
				$priority[] = $src;
			}
		}
	}
	// Append any sources the user omitted (safety net)
	foreach ($valid_sources as $src) {
		if (!in_array($src, $priority)) {
			$priority[] = $src;
		}
	}
	dolibarr_set_const($db, 'BULKRFQ_PRICE_PRIORITY', implode(',', $priority), 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
}

// Load current priority
$default_priority = 'vendor_price,any_supplier,cost_price,pmp';
$current_priority = explode(',', getDolGlobalString('BULKRFQ_PRICE_PRIORITY', $default_priority));
// Sanitize
$current_priority = array_values(array_intersect($current_priority, $valid_sources));
// Fill missing
foreach ($valid_sources as $src) {
	if (!in_array($src, $current_priority)) {
		$current_priority[] = $src;
	}
}

// Labels for price sources
$source_labels = array(
	'vendor_price'  => $langs->trans('PriceSrcVendor'),
	'any_supplier'  => $langs->trans('PriceSrcAnySupplier'),
	'cost_price'    => $langs->trans('PriceSrcCostPrice'),
	'pmp'           => $langs->trans('PriceSrcPMP'),
);

// View
llxHeader('', $langs->trans('BulkRfqSetup'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('BulkRfqSetup'), $linkback, 'title_setup');

print '<div class="opacitymedium">'.$langs->trans('BulkRfqAbout').'</div>';
print '<br>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td><td class="opacitymedium">'.$langs->trans('Description').'</td></tr>';

// Supplier Proposal module status
print '<tr class="oddeven"><td>'.$langs->trans('SupplierProposalModuleStatus').'</td><td>';
if (isModEnabled('supplier_proposal')) {
	print '<span class="badge badge-status4">'.$langs->trans('Enabled').'</span>';
} else {
	print '<span class="badge badge-status8">'.$langs->trans('Disabled').'</span>';
}
print '</td><td></td></tr>';

// Price priority — drag-and-drop sortable list
print '<tr class="oddeven"><td>'.$langs->trans('PricePriority').'</td>';
print '<td>';
print '<input type="hidden" name="price_priority_order" id="bulkrfq-priority-order" value="'.dol_escape_htmltag(implode(',', $current_priority)).'">';
print '<ul id="bulkrfq-priority-list" class="bulkrfq-sortable">';
foreach ($current_priority as $i => $src) {
	$label = isset($source_labels[$src]) ? $source_labels[$src] : $src;
	print '<li data-source="'.dol_escape_htmltag($src).'" class="bulkrfq-sortable-item">';
	print '<span class="fa fa-arrows-v bulkrfq-drag-handle opacitymedium"></span> ';
	print '<span class="bulkrfq-priority-num">'.($i + 1).'.</span> ';
	print dol_escape_htmltag($label);
	print '</li>';
}
print '</ul>';
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('PricePriorityDesc').'</td></tr>';

// Debug mode
print '<tr class="oddeven"><td>'.$langs->trans('DebugMode').'</td>';
print '<td>';
$chk_debug = getDolGlobalString('BULKRFQ_DEBUG_MODE') ? ' checked' : '';
print '<input type="checkbox" name="BULKRFQ_DEBUG_MODE" value="1"'.$chk_debug.'>';
print '</td>';
print '<td class="opacitymedium">'.$langs->trans('DebugModeDesc').'</td></tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</div>';

print '</form>';

// Inline JS for sortable list (jQuery UI is bundled with Dolibarr)
print '<script>
jQuery(function() {
	jQuery("#bulkrfq-priority-list").sortable({
		handle: ".bulkrfq-drag-handle",
		axis: "y",
		containment: "parent",
		tolerance: "pointer",
		update: function() {
			var order = [];
			jQuery("#bulkrfq-priority-list li").each(function(i) {
				order.push(jQuery(this).data("source"));
				jQuery(this).find(".bulkrfq-priority-num").text((i + 1) + ".");
			});
			jQuery("#bulkrfq-priority-order").val(order.join(","));
		}
	});
});
</script>';

// Inline CSS for sortable list
print '<style>
.bulkrfq-sortable { list-style: none; padding: 0; margin: 0; max-width: 340px; }
.bulkrfq-sortable-item {
	padding: 8px 12px;
	margin: 4px 0;
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 3px;
	cursor: grab;
	font-size: 0.95em;
	user-select: none;
}
.bulkrfq-sortable-item:active { cursor: grabbing; }
.bulkrfq-sortable-item.ui-sortable-helper {
	box-shadow: 0 2px 8px rgba(0,0,0,0.15);
	background: #f8f8ff;
}
.bulkrfq-sortable .ui-sortable-placeholder {
	border: 2px dashed #aaa;
	background: #f5f5f5;
	visibility: visible !important;
	height: 36px;
	margin: 4px 0;
}
.bulkrfq-drag-handle { cursor: grab; margin-right: 6px; }
.bulkrfq-priority-num { font-weight: bold; min-width: 20px; display: inline-block; }
</style>';

llxFooter();
$db->close();
