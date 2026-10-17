# Design context

## Overview
This plugin extends the existing WooCommerce product editor for Persian-speaking store managers. Use WooCommerce controls and the host admin styling rather than introducing a separate visual system.

## Components
The complementary-products field belongs to Product data → Linked products. WooCommerce owns the searchable, sortable multi-select (`wc-product-search`), its search responses, keyboard behavior and selected-product chips. The standard product Update action owns saving and feedback. No custom admin CSS or typography is introduced.

## Do's and Don'ts
Use a visible Persian label and associated help text. Explain directional relations and replacement versus complementary products. An empty selection is valid and means no recommendations. Do not apply storefront modal styling to admin controls.

## Management page
The WooCommerce submenu “محصولات مکمل” owns the paginated overview and checkout settings. It uses the host admin font, white panels, a neutral gray table header and WordPress blue for primary actions. The page's scoped CSS in `assets/admin.css` owns its tokens: accent `#2271b1`, border `#dcdcde`, muted text `#646970`. No global admin styling is overridden.

The table preserves native table semantics and horizontal scrolling on narrow screens. Product thumbnails have fixed 48px geometry. Search, category and coverage filters sit above the table; group actions are separated from row saves. Settings are disclosed below the workflow. Status messages remain next to their owning action. Dirty rows receive a pale background plus explicit text. Errors use text as well as color. WooCommerce owns searchable multi-selects; platform controls own category and operation dropdowns.
