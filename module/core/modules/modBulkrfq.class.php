<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modBulkrfq
 *
 * Module descriptor for the Bulk RFQ (Bulk Price Request) module
 */
class modBulkrfq extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 510300;
		$this->family = 'srm';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Bulk product selection wizard for creating Supplier Price Requests';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'supplier_proposal';

		$this->module_parts = array();

		$this->dirs = array();
		$this->config_page_url = array('setup.php@bulkrfq');

		$this->depends = array('modSupplierProposal', 'modProduct');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array('bulkrfq@bulkrfq');

		$this->phpmin = array(7, 0);
		$this->need_dolibarr_version = array(16, 0);

		// No custom constants
		$this->const = array();

		// No custom permissions — reuses supplier_proposal creer
		$this->rights = array();
		$this->rights_class = 'bulkrfq';

		// Menus — inject under Supplier Proposals in Commercial sidebar
		$this->menu = array();
		$r = 0;

		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=commercial,fk_leftmenu=propals_supplier',
			'type'     => 'left',
			'titre'    => 'BulkPriceRequest',
			'mainmenu' => 'commercial',
			'leftmenu' => 'bulkrfq',
			'url'      => '/bulkrfq/bulkrfq_wizard.php',
			'langs'    => 'bulkrfq@bulkrfq',
			'position' => 301,
			'enabled'  => 'isModEnabled("supplier_proposal")',
			'perms'    => '$user->hasRight("supplier_proposal", "creer")',
			'target'   => '',
			'user'     => 0,
		);
	}

	/**
	 * Function called when module is enabled
	 *
	 * @param  string $options Options when enabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		// Clean old menus before _init() calls insert_menus() to avoid duplicates on re-enable
		$this->delete_menus();

		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled
	 *
	 * @param  string $options Options when disabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
