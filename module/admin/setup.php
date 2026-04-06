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

$action = GETPOST('action', 'aZ09');

// Save settings
if ($action == 'update') {
	$settings = array(
		'BULKRFQ_DEBUG_MODE',
	);

	foreach ($settings as $key) {
		$val = GETPOST($key, 'alpha');
		dolibarr_set_const($db, $key, $val, 'chaine', 0, '', $conf->entity);
	}

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
}

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

llxFooter();
$db->close();
