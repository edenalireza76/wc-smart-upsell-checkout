# Complement management

Source: product behavior documented in README.md; the existing WooCommerce product-editor flow and `_wc_suc_complement_ids` metadata provide the shared editing contract.

| Capability | Owner | Behavior and verification |
| --- | --- | --- |
| Select/Listbox | WooCommerce `wc-product-search` / enhanced select | Search by product name/SKU, multiple ordered selections, removable chips; use host keyboard/search behavior. Verify open popup in staging. |
| Form | Standard WooCommerce product Update; AJAX row save in manager | Save only on explicit action; retain selection and inline error on failure. Empty replacement clears links. |
| CRUD | `WC_SUC_Admin` and WooCommerce product metadata CRUD | Directional links, per-product edit permission and management capability, admin nonce, revision check before saving. Isolated tests in `tests/admin.php`. |
| Table Selection | `assets/admin.js` | Page-only selection, select-all indeterminate state; unsuccessful bulk rows remain selected. |
| Navigation | `assets/admin.js` | Twenty products per page. Filters and page persisted in URL. Dirty-page changes require inline discard choice; tab close uses browser lifecycle warning. |
| Feedback | Local `role=status` elements | Row results, request errors/retry, sequential bulk progress and partial failure count. |
| Scrollbar | Scoped `.suc-table-scroll` | Visible, stable table scroll owner; system colors in forced-color mode. |

Bulk add preserves existing order; replace discards previous links only after inline confirmation; remove deletes selected links only after confirmation. Duplicate/self links and a source product's own variations are excluded. No automatic reciprocal relation or inferred complements. Bulk operations are per-product writes, not a single transaction. Revision checks detect intervening saves but do not promise database-level locking.

Runtime validation still required in a WordPress/WooCommerce/WoodMart staging installation: search and chip order, save/reload/clear, empty and failure paths, shop-manager permissions, two-tab conflict, partial bulk failure, keyboard and narrow-screen behavior. Isolated PHP tests and static audits do not establish this coverage.
