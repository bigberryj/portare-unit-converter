=== Portare Unit Converter ===
Contributors: bigberryj
Tags: inches, centimetres, units, display
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later

Display-only inches to centimetres toggle. No WooCommerce dependency.

== Description ==
A lightweight sitewide frontend enhancement for explicitly labelled inch measurements. Authored posts, Brizy content, product dimensions, WooCommerce variation payloads, prices and numeric form inputs are never rewritten. Turning back to inches restores the original display text.

Choose floating, header/menu and footer placements independently, or use [portare_unit_toggle] anywhere shortcodes are rendered. All controls stay synchronised by the frontend script. Settings are under Settings > Portare Unit Converter and require manage_options. The Settings API protects saves with its nonce and capability checks.

Default: enabled, floating bottom-right, inches initially, remember visitor units, one decimal place, 20 px edge offset, 8 px radius, background #173942 and text #ffffff. Header/footer are off. With inches initially, no conversion happens until a visitor toggles unless a remembered centimetre preference applies. The remember option uses this browser's local storage; it does not create user accounts or send preferences to a server.

Registered header menu locations are validated on save. Blocksy menu_1 is the default; menu_mobile is a mobile fallback. The native footer menu is not decorated. Optional bounded plain CSS selectors allow builder header/footer mounting; leave blank for automatic fallback. Global disable prevents assets and all controls, including shortcodes. Brizy editing/iframe/preview requests and wp-admin are excluded.

== Installation ==
1. Copy the complete portare-unit-converter folder into wp-content/plugins/.
2. Activate Portare Unit Converter.
3. Open Settings > Portare Unit Converter, choose placements and Save Changes.
4. Purge any frontend/page cache after changing settings.

== Frequently Asked Questions ==
= What cannot be converted? =
Images, PDFs, unlabelled numbers and numeric inputs. Only display text is enhanced; stored content and commerce values remain untouched.

= Can I exclude content? =
Put data-puc-ignore on an element to exclude its subtree from conversion.

= Can I put a toggle in a builder? =
Use [portare_unit_toggle] in an element that renders WordPress shortcodes. It works independently of automatic placements while globally enabled. Builder previews intentionally do not convert content.

= Are the preview buttons interactive? =
The settings screen includes a static preview only. Verify real conversion on a normal frontend page.

== Changelog ==
= 0.1.0 =
Initial release: display-only conversion, shared accessible controls, strict settings, theme/builder placement options and configuration-only uninstall.
