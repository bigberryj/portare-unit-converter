<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
// Configuration only. Authored content and product data are never touched.
delete_option( 'puc_settings' );
