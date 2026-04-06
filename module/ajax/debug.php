<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/debug.php
 * \ingroup bulkrfq
 * \brief   Debug diagnostic endpoint for bulkrfq module.
 *
 * Usage: /custom/bulkrfq/ajax/debug.php[?mode=overview|products|sql&q=SELECT...]
 *
 * Gated by: admin permission + BULKRFQ_DEBUG_MODE setting.
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
	http_response_code(500);
	exit;
}

// Double gate: admin + debug mode
if (!$user->admin) {
	http_response_code(403);
	print "Access denied: admin only\n";
	exit;
}
if (!getDolGlobalString('BULKRFQ_DEBUG_MODE')) {
	http_response_code(403);
	print "Debug mode is disabled. Enable it in module setup.\n";
	exit;
}

dol_include_once('/bulkrfq/lib/bulkrfq.lib.php');

header('Content-Type: text/plain; charset=utf-8');

$mode = GETPOST('mode', 'aZ09') ?: 'overview';

print "=== Bulk RFQ Debug Diagnostic ===\n";
print "Timestamp: ".date('Y-m-d H:i:s')."\n";
print "Dolibarr version: ".DOL_VERSION."\n";
print "Entity: ".$conf->entity."\n";
print "Mode: ".$mode."\n\n";

if ($mode == 'overview' || $mode == 'all') {
	print "--- Module Status ---\n";
	print "bulkrfq: ".(isModEnabled('bulkrfq') ? 'ENABLED' : 'DISABLED')."\n";
	print "supplier_proposal: ".(isModEnabled('supplier_proposal') ? 'ENABLED' : 'DISABLED')."\n";
	print "product: ".(isModEnabled('product') ? 'ENABLED' : 'DISABLED')."\n";
	print "fournisseur: ".(isModEnabled('fournisseur') ? 'ENABLED' : 'DISABLED')."\n";
	print "societe: ".(isModEnabled('societe') ? 'ENABLED' : 'DISABLED')."\n\n";

	// File paths
	print "--- File Paths ---\n";
	$module_dir = dol_buildpath('/bulkrfq/', 0);
	print "Module directory: ".$module_dir."\n";
	$files = array(
		'bulkrfq_wizard.php',
		'core/modules/modBulkrfq.class.php',
		'lib/bulkrfq.lib.php',
		'ajax/products.php',
		'ajax/debug.php',
		'admin/setup.php',
		'js/bulkrfq.js',
		'css/bulkrfq.css',
	);
	foreach ($files as $f) {
		$path = $module_dir.$f;
		print "  ".$f.": ".(file_exists($path) ? 'OK' : 'MISSING')."\n";
	}
	print "\n";

	// DB tables
	print "--- DB Tables ---\n";
	$tables = array('product', 'product_fournisseur_price', 'supplier_proposal', 'supplier_proposaldet', 'societe');
	foreach ($tables as $t) {
		$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX.$t;
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			print "  llx_".$t.": ".$obj->cnt." rows\n";
			$db->free($resql);
		} else {
			print "  llx_".$t.": ERROR querying\n";
		}
	}
	print "\n";

	// SupplierProposal class check
	print "--- SupplierProposal Class ---\n";
	require_once DOL_DOCUMENT_ROOT.'/supplier_proposal/class/supplier_proposal.class.php';
	$methods = array('create', 'fetch', 'addline', 'update', 'delete', 'getNomUrl');
	foreach ($methods as $m) {
		print "  ".$m."(): ".(method_exists('SupplierProposal', $m) ? 'OK' : 'MISSING')."\n";
	}
	print "\n";

	// Permissions
	print "--- Current User Permissions ---\n";
	print "  supplier_proposal lire: ".($user->hasRight('supplier_proposal', 'lire') ? 'YES' : 'NO')."\n";
	print "  supplier_proposal creer: ".($user->hasRight('supplier_proposal', 'creer') ? 'YES' : 'NO')."\n";
	print "  supplier_proposal supprimer: ".($user->hasRight('supplier_proposal', 'supprimer') ? 'YES' : 'NO')."\n";
	print "\n";
}

if ($mode == 'products' || $mode == 'all') {
	print "--- Product Query Test ---\n";
	$vendor_id = GETPOSTINT('vendor_id');

	$filters = array('vendor_id' => $vendor_id > 0 ? $vendor_id : 0);
	$total = bulkrfqCountProducts($db, $filters);
	print "Total purchasable products (tobuy=1): ".$total."\n";

	if ($vendor_id > 0) {
		print "Vendor ID filter: ".$vendor_id."\n";
		$filters_vendor = array('vendor_id' => $vendor_id);
		$vendor_total = bulkrfqCountProducts($db, $filters_vendor);
		print "Products with vendor price: ".$vendor_total."\n";
	}

	$products = bulkrfqFetchProducts($db, 'p.ref', 'ASC', 10, 0, $filters);
	print "First 10 products:\n";
	foreach ($products as $p) {
		print "  ID=".$p->rowid." ref=".$p->ref." label=".$p->label;
		print " type=".($p->fk_product_type == 1 ? 'service' : 'product');
		print " price=".$p->price;
		if (isset($p->supplier_ref)) {
			print " supplier_ref=".$p->supplier_ref." supplier_price=".$p->supplier_price;
		}
		print "\n";
	}
	print "\n";

	// Supplier price table stats
	print "--- Supplier Price Table ---\n";
	$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."product_fournisseur_price";
	$sql .= " WHERE entity IN (".getEntity('productsupplierprice').")";
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		print "Total supplier price records: ".$obj->cnt."\n";
		$db->free($resql);
	}

	if ($vendor_id > 0) {
		$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."product_fournisseur_price";
		$sql .= " WHERE fk_soc = ".$vendor_id;
		$sql .= " AND entity IN (".getEntity('productsupplierprice').")";
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			print "Supplier price records for vendor ".$vendor_id.": ".$obj->cnt."\n";
			$db->free($resql);
		}
	}
	print "\n";
}

if ($mode == 'settings' || $mode == 'all') {
	print "--- Module Settings ---\n";
	$sql = "SELECT name, value FROM ".MAIN_DB_PREFIX."const";
	$sql .= " WHERE name LIKE 'BULKRFQ_%'";
	$sql .= " AND entity IN (0, ".$conf->entity.")";
	$sql .= " ORDER BY name";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			print "  ".$obj->name." = ".$obj->value."\n";
		}
		$db->free($resql);
	}
	print "\n";
}

if ($mode == 'sql') {
	print "--- SQL Query ---\n";
	$query = GETPOST('q', 'restricthtml');
	if (empty($query)) {
		print "Pass ?mode=sql&q=SELECT... to run a read-only query.\n";
	} else {
		// Safety: only SELECT
		$blocked = array('INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', 'GRANT', 'REVOKE');
		$first_word = strtoupper(trim(strtok($query, " \t\n")));
		if (in_array($first_word, $blocked)) {
			print "ERROR: Only SELECT queries are allowed.\n";
		} else {
			// Auto-prefix
			$query = str_replace('llx_', MAIN_DB_PREFIX, $query);
			// Auto-limit
			if (stripos($query, 'LIMIT') === false) {
				$query .= ' LIMIT 50';
			}
			print "Query: ".$query."\n\n";
			$resql = $db->query($query);
			if ($resql) {
				$first = true;
				while ($row = $db->fetch_array($resql)) {
					if ($first) {
						print implode("\t", array_keys($row))."\n";
						$first = false;
					}
					print implode("\t", $row)."\n";
				}
				$db->free($resql);
				if ($first) {
					print "(no rows)\n";
				}
			} else {
				print "ERROR: ".$db->lasterror()."\n";
			}
		}
	}
	print "\n";
}

print "=== End of Diagnostic ===\n";
