# Design context

## Overview
This plugin extends the existing WooCommerce product editor for Persian-speaking store managers. Use WooCommerce controls and the host admin styling rather than introducing a separate visual system.

## Components
The complementary-products field belongs to Product data → Linked products. WooCommerce owns the searchable, sortable multi-select (`wc-product-search`), its search responses, keyboard behavior and selected-product chips. The standard product Update action owns saving and feedback. No custom admin CSS or typography is introduced.

## Do's and Don'ts
Use a visible Persian label and associated help text. Explain directional relations and replacement versus complementary products. An empty selection is valid and means no recommendations. Do not apply storefront modal styling to admin controls.
