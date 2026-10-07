# Changelog

## 0.1.1 — 2026-10-06

- Added native WordPress color pickers for background and text, keeping strict six-digit hex validation.
- Replaced static buttons with a live scoped preview that reuses the real frontend button CSS.
- Preview reflects radius, edge offset, corner, placement toggles, units, and rounding without saving or touching browser preference.
- Added responsive settings layout, invalid-color help, admin-only asset gates and preview tests. Existing option schema/settings are unchanged.

## 0.1.0 — 2026-10-06

- Initial display-only inch-to-centimeter toggle with exact original restoration.
- Independent floating, header-menu, and footer placements plus shortcode.
- Rounding, initial units, visitor preference, custom selectors and scoped appearance settings.
- Dynamic text/quote-label handling while preserving form values.
- Automated PHP and browser-DOM tests, staging verification, and installable package.
