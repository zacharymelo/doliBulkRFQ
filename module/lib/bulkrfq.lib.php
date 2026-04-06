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
 *
 * @param  DoliDB $db        Database handler
 * @param  string $sortfield Sort column (whitelisted)
 * @param  string $sortorder Sort direction ASC or DESC
 * @param  int    $limit     Max rows
 * @param  int    $offset    Row offset
 * @param  array  $filters   Associative array with optional keys: search_ref, search_label
 * @return array             Array of stdClass product rows
 */
function bulkrfqFetchProducts($db, $sortfield = 'p.ref', $sortorder = 'ASC', $limit = 25, $offset = 0, $filters = array())
{
	$allowed_sort = array(
		'p.ref'              => 'p.ref',
		'p.label'            => 'p.label',
		'p.fk_product_type'  => 'p.fk_product_type',
		'p.price'            => 'p.price',
	);

	$safe_sortfield = isset($allowed_sort[$sortfield]) ? $allowed_sort[$sortfield] : 'p.ref';
	$safe_sortorder = strtoupper($sortorder) === 'DESC' ? 'DESC' : 'ASC';

	$sql = "SELECT p.rowid, p.ref, p.label, p.fk_product_type, p.price, p.barcode, p.tva_tx";
	$sql .= " FROM ".MAIN_DB_PREFIX."product p";
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
 * @param  array  $filters Associative array with optional keys: search_ref, search_label
 * @return int             Total count
 */
function bulkrfqCountProducts($db, $filters = array())
{
	$sql = "SELECT COUNT(*) AS total";
	$sql .= " FROM ".MAIN_DB_PREFIX."product p";
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
