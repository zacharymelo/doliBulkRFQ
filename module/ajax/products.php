<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/products.php
 * \ingroup bulkrfq
 * \brief   Returns JSON list of purchasable products with pagination,
 *          sorting, search filters, and optional vendor filtering.
 *
 * GET params:
 *   sortfield    (string)  — sort column (whitelisted)
 *   sortorder    (string)  — ASC or DESC
 *   limit        (int)     — page size
 *   page         (int)     — page number (0-based)
 *   search_ref   (string)  — product ref filter
 *   search_label (string)  — product label filter
 *   vendor_id    (int)     — if > 0, INNER JOIN to vendor prices
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

dol_include_once('/bulkrfq/lib/bulkrfq.lib.php');

if (!$user->id || !$user->hasRight('supplier_proposal', 'lire')) {
	http_response_code(403);
	exit;
}

$sortfield   = GETPOST('sortfield', 'aZ09');
$sortorder   = GETPOST('sortorder', 'aZ09');
$page        = GETPOSTINT('page');
$limit       = GETPOSTINT('limit');
$search_ref  = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$vendor_id   = GETPOSTINT('vendor_id');

if (empty($sortfield)) {
	$sortfield = 'p.ref';
}
if (empty($sortorder)) {
	$sortorder = 'ASC';
}
if ($limit <= 0) {
	$limit = getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT') ? getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT') : 25;
}
$offset = $limit * max(0, $page);

$filters = array(
	'search_ref'   => $search_ref,
	'search_label' => $search_label,
	'vendor_id'    => $vendor_id > 0 ? $vendor_id : 0,
);

$total = bulkrfqCountProducts($db, $filters);
$rows = bulkrfqFetchProducts($db, $sortfield, $sortorder, $limit, $offset, $filters);

$products = array();
foreach ($rows as $row) {
	$item = array(
		'rowid'            => (int) $row->rowid,
		'ref'              => $row->ref,
		'label'            => $row->label,
		'fk_product_type'  => (int) $row->fk_product_type,
		'price'            => (float) $row->price,
		'barcode'          => $row->barcode,
	);
	if ($vendor_id > 0 && isset($row->supplier_ref)) {
		$item['supplier_ref']   = $row->supplier_ref;
		$item['supplier_price'] = (float) $row->supplier_price;
	}
	$products[] = $item;
}

header('Content-Type: application/json');
print json_encode(array(
	'total'    => $total,
	'products' => $products,
));
