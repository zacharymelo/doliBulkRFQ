# Bulk RFQ for Dolibarr

Bulk product selection wizard for creating Supplier Price Requests (RFQs) in Dolibarr ERP.

Instead of adding products one line at a time to a Supplier Proposal, this module lets buyers select multiple products from a paginated list, specify quantities, and create a draft Supplier Proposal with all lines in one action.

## Features

- **Bulk product selection** with checkboxes across a paginated, searchable, sortable product list
- **Vendor product filter** — toggle between all purchasable products and only products with a known price from a specific vendor. AJAX-driven, no page reloads
- **Multi-vendor workflow** — browse Vendor A's products, select some, switch to Vendor B, add more, then pick the target vendor and create the RFQ. Selections persist across all changes via browser sessionStorage
- **Include buy prices** — optional checkbox to pre-fill line prices from known supplier prices, cost price, or PMP/WAP instead of leaving them blank
- **Configurable price priority** — drag-and-drop sortable admin setting to control which price source is used first (selected vendor's price, best supplier price, cost price, or PMP)
- **Staging area** — selected products appear in a summary table below the product list with editable quantities and remove buttons
- **Debug diagnostics** — admin-only debug endpoint with module status, product queries, SQL console, and settings inspection

## Requirements

- Dolibarr 16.0+ (tested on 22.0.4 and 23.0.0)
- PHP 7.0+
- **Supplier Proposal** module enabled (`modSupplierProposal`)
- **Products** module enabled (`modProduct`)

## Installation

1. Download the latest `bulkrfq-X.Y.Z.zip` from the [Releases](https://github.com/zacharymelo/doliBulkRFQ/releases) page, or build from source (see below)
2. In Dolibarr, go to **Setup > Modules/Applications**
3. Click **Deploy/install an external module** and upload the zip
4. Enable **Bulk RFQ** in the module list (found under the SRM family)

### Manual installation

Extract the zip so that `bulkrfq/` is placed inside your Dolibarr `htdocs/custom/` directory:

```
htdocs/custom/bulkrfq/
    admin/setup.php
    ajax/debug.php
    ajax/products.php
    bulkrfq_wizard.php
    core/modules/modBulkrfq.class.php
    css/bulkrfq.css
    js/bulkrfq.js
    langs/en_US/bulkrfq.lang
    lib/bulkrfq.lib.php
```

Then enable the module in **Setup > Modules/Applications**.

## Usage

### Creating a Bulk Price Request

1. Navigate to **Commerce > Supplier Proposals > Bulk Price Request** (left sidebar menu)
2. **Select a vendor** from the supplier dropdown
3. Optionally check **Include buy prices** to pre-fill line prices
4. Optionally click **Vendor's Products Only** to filter the list to products with a known price from the selected vendor
5. **Check products** you want to include. Use the search filters and pagination freely — selections persist across pages
6. Adjust **quantities** in the Qty column (decimals accepted)
7. Review selected products in the **staging area** below the product list
8. Click **Create Price Request**
9. You'll be redirected to the new draft Supplier Proposal with all selected lines

### Multi-Vendor Workflow

The wizard supports building an RFQ from products across multiple vendors:

1. Select Vendor A, click "Vendor's Products Only", check their products
2. Switch to Vendor B in the dropdown — the table auto-refreshes to show Vendor B's products
3. Switch to "All Products" to browse the full catalog and add more
4. When ready, ensure the correct target vendor is selected, then click Create

All selections persist across vendor changes and filter toggles.

### Configuring Price Sources

Go to **Setup > Modules > Bulk RFQ** (click the gear icon) to configure:

- **Price Source Priority** — drag and drop to reorder which price source the system checks first when "Include buy prices" is checked:
  - *Selected vendor's price* — from the supplier price table for the vendor on the RFQ
  - *Best supplier price (any vendor)* — cheapest known supplier price across all vendors
  - *Product cost price* — the cost_price field on the product
  - *PMP / Weighted average price* — the weighted average purchase price

The system tries each source in order and uses the first non-zero price found.

- **Debug Mode** — enables the diagnostic endpoint at `/custom/bulkrfq/ajax/debug.php` (admin only)

## Debug Endpoint

When debug mode is enabled in the admin setup, the diagnostic endpoint is available at:

```
/custom/bulkrfq/ajax/debug.php
```

Requires admin access. Supported modes:

| Mode | URL | Description |
|------|-----|-------------|
| Overview | `?mode=overview` | Module status, file paths, DB table counts, class checks, permissions |
| Products | `?mode=products&vendor_id=X` | Product query test with optional vendor filter |
| Settings | `?mode=settings` | All BULKRFQ_* constants |
| SQL | `?mode=sql&q=SELECT...` | Read-only SQL console (SELECT only, 50 row limit) |

## Building from Source

```bash
git clone https://github.com/zacharymelo/doliBulkRFQ.git
cd doliBulkRFQ
bash build.sh
```

This produces `bulkrfq-X.Y.Z.zip` with the version number extracted from the module descriptor.

### Pre-commit Hooks

The repository includes pre-commit hooks for PHP linting and CodeSniffer checks. Install with:

```bash
pre-commit install
```

## Module Structure

```
module/
    admin/setup.php                      # Configuration page
    ajax/debug.php                       # Debug diagnostic endpoint
    ajax/products.php                    # AJAX product list endpoint
    bulkrfq_wizard.php                   # Main wizard page
    core/modules/modBulkrfq.class.php    # Module descriptor
    css/bulkrfq.css                      # Styles
    js/bulkrfq.js                        # Selection persistence and UI logic
    langs/en_US/bulkrfq.lang             # English translations
    lib/bulkrfq.lib.php                  # Product query helpers
```

No custom database tables. The module creates standard Dolibarr `SupplierProposal` objects via the core API.

## License

GNU General Public License v3.0 — see [LICENSE](LICENSE) for details.

## Author

Zachary Melo
