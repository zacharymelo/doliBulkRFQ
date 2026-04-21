# Changelog

All notable changes to the Bulk RFQ module are documented here.

## [1.2.6] - 2026-04-21

### Fixed
- Namespace/remove lang keys colliding with Dolibarr core:
  - Removed duplicates `Qty`, `SelectAll` (values were effectively equivalent to core)
  - Renamed `ProductRef`, `ProductLabel`, `ProductType`, `SupplierRef` → `Rfq*` variants so our custom wording is preserved without overriding core (esp. `SupplierRef` where core means "Vendor SKU")

## [1.2.1] - 2026-04-06

### Fixed
- Include buy prices checkbox now reliably submits with the form (moved inside POST form boundary)
- Removed fragile JS sync approach for the checkbox

## [1.2.0] - 2026-04-06

### Added
- **Include buy prices** checkbox to pre-fill line prices from known supplier data
- **Configurable price source priority** in admin setup with drag-and-drop reordering
- Price lookup chain: selected vendor's price, best supplier price (any vendor), cost price, PMP/WAP
- Debug diagnostic endpoint (`ajax/debug.php`) with overview, products, settings, and SQL modes
- Debug mode toggle in admin setup

### Fixed
- Module ID changed from 510300 to 510400 (conflict with svcrecord module)

## [1.1.3] - 2026-04-06

### Fixed
- Vendor filter toggle buttons now use Dolibarr's `<a>` tag pattern with `butAction`/`butActionRefused` classes (fixes invisible buttons caused by `<button>` elements)
- Select2 vendor selector binding uses inline script matching the doli-returns pattern
- Vendor filter auto-refreshes when switching vendors while filter is active
- Multi-vendor workflow: selections persist across vendor changes via sessionStorage
- Admin setup page uses POST form with Save button (replaces broken `ajax_constantonoff`)

## [1.1.0] - 2026-04-06

### Added
- **Vendor product filter** — toggle between all products and vendor-specific products via AJAX
- Supplier Ref and Supplier Price columns shown when vendor filter is active
- `ajax/products.php` endpoint for AJAX product fetching with vendor filtering

### Fixed
- Staging area moved below product list to prevent page jumping
- Permission check uses read access for page view, create only on submit

## [1.0.0] - 2026-04-06

### Added
- Initial release
- Bulk product selection wizard with paginated, searchable, sortable product list
- Checkbox selection with sessionStorage persistence across pagination
- Staging area showing selected products with editable quantities
- Vendor (supplier) selector with Select2 autocomplete
- Creates draft Supplier Proposal with all selected lines via core `SupplierProposal::addline()` API
- Menu entry under Commerce > Supplier Proposals
- Admin setup page
- English language file
