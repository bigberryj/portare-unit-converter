# Portare Unit Converter

Display-only inch-to-centimeter switching for WordPress, including Brizy-rendered content, WooCommerce labels, and asynchronously opened Portare quote forms. Version 0.1.0.

## Installation

1. Upload the `portare-unit-converter` folder to `wp-content/plugins`, or upload the packaged ZIP through **Plugins → Add New → Upload Plugin**.
2. Activate **Portare Unit Converter**.
3. Open **Settings → Portare Unit Converter**.
4. Choose floating, header-menu, footer, or any combination. The default is a bottom-right floating control with inches initially displayed.

PHP 7.4+ and WordPress 6.0+. No WooCommerce dependency, external service, conversion API, or runtime package installation.

## Controls and settings

- Enable or disable the complete frontend enhancement.
- Independently select floating, header, and footer placements; all switches share one visitor preference.
- Select any floating corner, offset, colors, and radius.
- Choose header menu location; desktop/mobile menu integration includes a DOM fallback for page-builder headers.
- Optional header/footer CSS selectors for custom layouts. Invalid selectors fail safely.
- Choose initial inches or centimeters and whether the visitor's choice persists across pages in browser-local storage.
- Set rounding from zero to three decimals. Trailing zeroes are omitted.
- Place `[portare_unit_toggle]` in a shortcode-capable builder block for custom positioning. No automatic placement is required if a shortcode is used.

The button reports the active display units. Clicking `Units: in` changes supported inch measurements to centimeters; clicking `Units: cm` restores the exact original authored text, rather than converting rounded values back mathematically.

## What gets converted

Supported text includes integer/decimal inch measurements, fractions, Unicode fractions, ranges, and dimensional groups with explicit inch units. Examples include `72" L x 30" W`, `3/8″ wide`, and dynamically loaded quote option labels such as `30" h`. Numbers without inch units remain untouched.

Conversion only touches DOM text nodes. It never rewrites the WordPress database, Brizy layouts, URLs, product variation keys, quote selection values, prices, customer input, or stored quote records. An option without an explicit value is pinned to its original value before its visible label changes, preserving form submission semantics.

Add `data-puc-ignore` to an element to exclude its contents. Scripts, styles, editable areas, inputs, textareas, code, SVG, admin toolbar, and converter controls are excluded automatically.

## Honest limits

- Text embedded in images, videos, downloadable PDFs, CSS generated content, cross-origin frames, or closed shadow roots is not DOM text and is not converted.
- Ambiguous unlabelled numbers are not guessed. Label measurements with inches or a double-prime symbol.
- A number and unit split across separate HTML text nodes are not joined; keep the measurement together in its text element.
- Feet-and-inches combinations and square/cubic inches are intentionally not supported as linear inch measurements.
- Browser storage can be unavailable in private/restricted contexts. Switching still works for that page, and persistence problems are reported.
- An arbitrary new third-party widget could derive business logic from visible text; check such integrations before rollout beyond staging. Portare's quote controls use separate immutable values.
- Automatically inserted footer controls require a `wp_footer()` hook; standard WordPress/Blocksy themes include it. Header DOM fallback is available for builder menus, with a visible fallback if the requested header cannot be found.

## Development and tests

```sh
npm ci
npm test
php qa/lint.php
php tests/run-tests.php
```

Only development tests need Node dependencies; the installed plugin is dependency-free. `scripts/package.py` creates an installable ZIP under `dist/`, omitting development dependencies, Git history, and QA/private session files.

Documentation: [architecture](docs/architecture.md), [settings schema](docs/schema.md), [deployment and rollback](docs/deployment.md), [security](docs/security.md), and [changelog](docs/changelog.md).
