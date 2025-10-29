# Changelog

All notable changes to this project will be documented in this file.

## [0.1.0] - 2025-10-30
Initial release.

### Added
- Registers a Resource Page Block Layout named `similarItems` so it appears in Admin → Sites → Configure resource pages.
- Delegates rendering to the theme partial `view/common/resource-page-blocks/similar-items.phtml` to allow full theme control of UI.
- Provides global (module-wide) configuration for similarity logic via Admin → Modules → Similar Items → Configure:
  - Scope to current site (on/off)
  - Use Item Sets with weight
  - Up to four property-based criteria (term, match type: eq/cont/in, weight)
  - Cap of terms per property and a pool-size multiplier to balance API calls vs. ranking quality
- Theme integration (example: foundation_tsukuba2025) supporting:
  - Two-column UI with list + mascot and hover thumbnail popovers
  - Speech bubble row (JA/EN), right-aligned with pointer
  - Title text (JA/EN) and show/hide toggle; maximum results
- Default similarity behavior (when no properties are explicitly configured):
  - dcterms:subject (eq, weight=2)
  - dcterms:creator (eq, weight=1)
  - Item set overlap (weight=3 if enabled)

### Notes
- If the theme setting `similar_items_enable` is off, the block renders nothing even if the module is active.
- This module focuses on logic and registration; visual presentation remains with the active theme.

[0.1.0]: https://github.com/wakitosh/SimilarItems/releases/tag/v0.1.0
