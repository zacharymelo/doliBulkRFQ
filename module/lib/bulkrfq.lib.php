<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/bulkrfq.lib.php
 * \ingroup bulkrfq
 * \brief   Product query helpers for the Bulk RFQ wizard.
 */

/**
 * Fetch purchasable products with pagination, sorting, and search filters.
 * Optionally filter to only products with a known supplier price for a given vendor.
 *
 * @param  DoliDB $db        Database handler
 * @param  string $sortfield Sort column (whitelisted)
 * @param  string $sortorder Sort direction ASC or DESC
 * @param  int    $limit     Max rows
 * @param  int    $offset    Row offset
 * @param  array  $filters   Associative array with optional keys:
 *                           search_ref, search_label, vendor_id (int, filter by vendor)
 * @return array             Array of stdClass product rows
 */
function bulkrfqFetchProducts($db, $sortfield = 'p.ref', $sortorder = 'ASC', $limit = 25, $offset = 0, $filters = array())
{
	$allowed_sort = array(
		'p.ref'              => 'p.ref',
		'p.label'            => 'p.label',
		'p.fk_product_type'  => 'p.fk_product_type',
		'p.price'            => 'p.price',
		'pfp.ref_fourn'      => 'pfp.ref_fourn',
		'pfp.unitprice'      => 'pfp.unitprice',
	);

	$safe_sortfield = isset($allowed_sort[$sortfield]) ? $allowed_sort[$sortfield] : 'p.ref';
	$safe_sortorder = strtoupper($sortorder) === 'DESC' ? 'DESC' : 'ASC';

	$vendor_id = !empty($filters['vendor_id']) ? (int) $filters['vendor_id'] : 0;
	$vendor_show_all = !empty($filters['vendor_show_all']) ? true : false;

	$sql = "SELECT p.rowid, p.ref, p.label, p.fk_product_type, p.price, p.barcode, p.tva_tx";
	if ($vendor_id > 0) {
		$sql .= ", pfp.ref_fourn AS supplier_ref, pfp.unitprice AS supplier_price";
	}
	$sql .= " FROM ".MAIN_DB_PREFIX."product p";
	if ($vendor_id > 0) {
		// LEFT JOIN shows all products with vendor columns; INNER JOIN filters to vendor's products only
		$join_type = $vendor_show_all ? "LEFT JOIN" : "INNER JOIN";
		$sql .= " ".$join_type." ".MAIN_DB_PREFIX."product_fournisseur_price pfp ON pfp.fk_product = p.rowid";
		$sql .= " AND pfp.fk_soc = ".$vendor_id;
		$sql .= " AND pfp.entity IN (".getEntity('productsupplierprice').")";
	}
	$sql .= " WHERE p.tobuy = 1";
	$sql .= " AND p.entity IN (".getEntity('product').")";

	if (!empty($filters['search_ref'])) {
		$sql .= " AND p.ref LIKE '%".$db->escape($filters['search_ref'])."%'";
	}
	if (!empty($filters['search_label'])) {
		$sql .= " AND p.label LIKE '%".$db->escape($filters['search_label'])."%'";
	}

	$sql .= " ORDER BY ".$safe_sortfield." ".$safe_sortorder;
	$sql .= $db->plimit((int) $limit, (int) $offset);

	$products = array();
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$products[] = $obj;
		}
		$db->free($resql);
	}
	return $products;
}

/**
 * Count total purchasable products matching the given filters.
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $filters Associative array with optional keys:
 *                         search_ref, search_label, vendor_id (int)
 * @return int             Total count
 */
function bulkrfqCountProducts($db, $filters = array())
{
	$vendor_id = !empty($filters['vendor_id']) ? (int) $filters['vendor_id'] : 0;
	$vendor_show_all = !empty($filters['vendor_show_all']) ? true : false;

	$sql = "SELECT COUNT(*) AS total";
	$sql .= " FROM ".MAIN_DB_PREFIX."product p";
	if ($vendor_id > 0) {
		$join_type = $vendor_show_all ? "LEFT JOIN" : "INNER JOIN";
		$sql .= " ".$join_type." ".MAIN_DB_PREFIX."product_fournisseur_price pfp ON pfp.fk_product = p.rowid";
		$sql .= " AND pfp.fk_soc = ".$vendor_id;
		$sql .= " AND pfp.entity IN (".getEntity('productsupplierprice').")";
	}
	$sql .= " WHERE p.tobuy = 1";
	$sql .= " AND p.entity IN (".getEntity('product').")";

	if (!empty($filters['search_ref'])) {
		$sql .= " AND p.ref LIKE '%".$db->escape($filters['search_ref'])."%'";
	}
	if (!empty($filters['search_label'])) {
		$sql .= " AND p.label LIKE '%".$db->escape($filters['search_label'])."%'";
	}

	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		return (int) $obj->total;
	}
	return 0;
}
