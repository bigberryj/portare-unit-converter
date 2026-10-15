# Integration API

Shortcode: `[portare_unit_toggle]` returns an accessible unit-switch button. All buttons bearing `data-puc-toggle` synchronize with the same display mode.

Exclusion: `data-puc-ignore` on an element skips its descendants.

Frontend configuration: `window.PUCConfig` is emitted by WordPress and contains presentation settings only. It never includes credentials, customer data, prices, or conversion AJAX endpoints.

No public REST endpoints and no AJAX writes. Admin persistence uses the WordPress Settings API with the normal nonce and manage_options capability.
