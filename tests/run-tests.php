<?php
/** Standalone PHP regression suite: php tests/run-tests.php */
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
require dirname( __DIR__ ) . '/qa/phpstan-wordpress-stubs.php';
require dirname( __DIR__ ) . '/portare-unit-converter.php';
$count = 0;
function expect( $condition, string $label ): void {
    global $count; ++$count;
    if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
    echo 'PASS: ' . $label . "\n";
}
function same( $actual, $expected, string $label ): void { expect( $actual === $expected, $label ); }
function setup_plugin( array $options = array() ): PUC_Plugin {
    puc_test_reset();
    update_option( PUC_OPTION_KEY, array_merge( PUC_Settings::defaults(), $options ) );
    $plugin = new PUC_Plugin( new PUC_Settings() ); $plugin->init(); return $plugin;
}
try {
    same( PUC_VERSION, '0.1.2', 'version' );
    expect( isset( $GLOBALS['puc_test']['hooks']['plugins_loaded'] ), 'bootstrap is deferred to plugins_loaded' );
    do_action( 'plugins_loaded' );
    expect( isset( $GLOBALS['puc_test']['hooks']['admin_init'] ), 'bootstrap registers admin settings' );
    expect( isset( $GLOBALS['puc_test']['hooks']['wp_enqueue_scripts'] ), 'bootstrap registers frontend enqueue' );
    $settings = new PUC_Settings();
    $d = PUC_Settings::defaults();
    same( $d['enabled'], 1, 'enabled default' ); same( $d['floating'], 1, 'floating default' );
    same( $d['header'], 0, 'header off default' ); same( $d['footer'], 0, 'footer off default' );
    same( $d['remember'], 1, 'remember default' ); same( $d['default_unit'], 'in', 'initial inches default' );
    same( $d['position'], 'bottom-right', 'corner default' ); same( $d['decimals'], 1, 'rounding default' );
    same( $d['radius'], 8, 'radius default' ); same( $d['floating_offset'], 20, 'offset default' );
    same( $d['header_location'], 'menu_1', 'Blocksy main menu default' );
    same( $settings->get(), $d, 'fresh options resolve defaults' );
    same( $d['label_in'], 'Units: in', 'inch label keeps original default' );
    same( $d['label_cm'], 'Units: cm', 'metric label keeps original default' );
    same( $settings->sanitize( array( 'label_in' => 'Imperial', 'label_cm' => 'Metric' ) )['label_cm'], 'Metric', 'custom metric label accepted' );
    same( PUC_Settings::label( '  Inches   & feet  ', 'default' ), 'Inches & feet', 'label whitespace normalized' );
    same( PUC_Settings::label( '<b>Metric</b>', 'default' ), 'Metric', 'label HTML stripped' );
    same( PUC_Settings::label( '   ', 'Units: in' ), 'Units: in', 'blank label defaults' );
    same( PUC_Settings::label( array( 'malicious' ), 'Units: cm' ), 'Units: cm', 'array labels rejected' );
    same( PUC_Settings::label( str_repeat( 'A', 75 ), 'default' ), str_repeat( 'A', 60 ), 'label length bounded' );
    same( PUC_Settings::label( str_repeat( 'é', 75 ), 'default' ), str_repeat( 'é', 60 ), 'Unicode label truncation remains valid' );
    update_option( PUC_OPTION_KEY, array( 'label_in' => 'Inches & imperial', 'label_cm' => 'Metric' ) );
    $label_plugin = new PUC_Plugin( $settings );
    expect( false !== strpos( $label_plugin->button(), 'Inches &amp; imperial' ), 'PHP button label escaped' );
    ob_start(); $settings->render(); $label_html = ob_get_clean();
    expect( false !== strpos( $label_html, 'name="puc_settings[label_in]"' ) && false !== strpos( $label_html, 'name="puc_settings[label_cm]"' ), 'both label settings rendered' );
    expect( false !== strpos( $label_html, 'maxlength="60"' ), 'labels explain bounded text' );
    puc_test_reset();
    foreach ( array( array(), '1x', true, 1.0, -1, 'true', 2, null ) as $value ) {
        same( $settings->sanitize( array( 'enabled' => $value ) )['enabled'], 0, 'non-literal flag rejected: ' . gettype( $value ) );
    }
    same( $settings->sanitize( array( 'enabled' => '1' ) )['enabled'], 1, 'literal string flag accepted' );
    same( $settings->sanitize( array( 'enabled' => 1 ) )['enabled'], 1, 'literal int flag accepted' );
    same( $settings->sanitize( array() )['enabled'], 0, 'unchecked checkbox disables' );
    foreach ( array( null, 'bad', 7, true ) as $input ) {
        same( count( $settings->sanitize( $input ) ), count( $d ), 'non-array top-level input safe' );
    }
    $hostile = array_fill_keys( array_keys( $d ), array( 'evil' => '<script>alert(1)</script>' ) );
    $clean = $settings->sanitize( $hostile );
    same( $clean['default_unit'], 'in', 'nested enum rejected' ); same( $clean['decimals'], 1, 'nested integer rejected' );
    same( $clean['background_color'], '#173942', 'nested color rejected' ); same( $clean['header_selector'], '', 'nested selector rejected' );
    same( $clean['header_location'], 'menu_1', 'nested menu rejected' );
    same( $settings->sanitize( array( 'decimals' => '3' ) )['decimals'], 3, 'integer decimal string accepted' );
    same( $settings->sanitize( array( 'decimals' => '3.0' ) )['decimals'], 1, 'decimal syntax rejected' );
    same( $settings->sanitize( array( 'decimals' => '2e0' ) )['decimals'], 1, 'exponent syntax rejected' );
    same( $settings->sanitize( array( 'decimals' => 100 ) )['decimals'], 3, 'rounding clamped' );
    same( $settings->sanitize( array( 'radius' => '-1' ) )['radius'], 8, 'negative numeric string rejected' );
    same( $settings->sanitize( array( 'radius' => 101 ) )['radius'], 100, 'radius clamped' );
    same( $settings->sanitize( array( 'floating_offset' => 999 ) )['floating_offset'], 200, 'offset clamped' );
    same( $settings->sanitize( array( 'radius' => '8px' ) )['radius'], 8, 'CSS dimension input rejected' );
    same( $settings->sanitize( array( 'background_color' => '#ABCDEF' ) )['background_color'], '#abcdef', 'six hex canonicalized' );
    foreach ( array( '#fff', 'red', '#ffffff;}', '#ffffff<script>', array() ) as $color ) {
        same( $settings->sanitize( array( 'text_color' => $color ) )['text_color'], '#ffffff', 'invalid color rejected' );
    }
    same( PUC_Settings::selector( '#header .ct-header' ), '#header .ct-header', 'plain header selector accepted' );
    same( PUC_Settings::selector( 'nav.mobile-menu' ), 'nav.mobile-menu', 'mobile selector accepted' );
    same( PUC_Settings::selector( '[data-footer="yes"]' ), '[data-footer="yes"]', 'attribute selector accepted' );
    foreach ( array( '<script>', '#x{color:red}', 'javascript:alert(1)', 'expression(alert(1))', 'url(x)', '#x; body', str_repeat( 'a', 201 ), "#x\nbody", array(), '#x\\32' ) as $selector ) {
        same( PUC_Settings::selector( $selector ), '', 'hostile/oversize selector rejected' );
    }
    same( $settings->sanitize( array( 'header_location' => 'menu_mobile' ) )['header_location'], 'menu_mobile', 'registered menu accepted' );
    same( $settings->sanitize( array( 'header_location' => 'made-up' ) )['header_location'], 'menu_1', 'unregistered menu rejected' );
    same( $settings->sanitize( array( 'header_location' => 'footer' ) )['header_location'], 'menu_1', 'footer not eligible for header' );
    $GLOBALS['puc_test']['menus'] = array( 'primary' => 'Primary' );
    same( $settings->sanitize( array( 'header_location' => 'menu_1' ) )['header_location'], '', 'save checks current registrations' );
    same( $settings->sanitize( array( 'header_location' => 'primary' ) )['header_location'], 'primary', 'other theme menu accepted' );
    puc_test_reset();
    update_option( PUC_OPTION_KEY, 'corrupted' ); same( $settings->get(), $d, 'corrupt option safely defaults' );
    puc_test_reset(); PUC_Plugin::activate(); same( get_option( PUC_OPTION_KEY ), $d, 'activation seeds defaults' );
    update_option( PUC_OPTION_KEY, array( 'enabled' => 0 ) ); PUC_Plugin::activate();
    same( get_option( PUC_OPTION_KEY ), array( 'enabled' => 0 ), 'activation preserves existing option' );
    $plugin = setup_plugin( array( 'enabled' => 0, 'header' => 1, 'footer' => 1 ) );
    same( $GLOBALS['puc_test']['hooks'], array(), 'disabled registers no frontend hooks' );
    same( $plugin->shortcode(), '', 'disabled shortcode blank' );
    $plugin->enqueue(); same( $GLOBALS['puc_test']['scripts'], array(), 'disabled direct enqueue gated' );
    ob_start(); $plugin->render_placements(); same( ob_get_clean(), '', 'disabled direct placements gated' );
    same( $plugin->menu_items( '<li>Original</li>', (object) array( 'theme_location' => 'menu_1' ) ), '<li>Original</li>', 'disabled menu unchanged' );
    $plugin = setup_plugin( array( 'floating' => 0, 'header' => 0, 'footer' => 0 ) );
    expect( false !== strpos( $plugin->shortcode( array( 'class' => '<script>' ) ), 'data-puc-toggle' ), 'shortcode independent of placements' );
    expect( false === strpos( $plugin->shortcode( array( 'class' => '<script>' ) ), '<script>' ), 'shortcode ignores hostile attributes' );
    expect( false !== strpos( $plugin->button(), 'type="button"' ) && false !== strpos( $plugin->button(), 'aria-pressed="false"' ), 'accessible non-submit control' );
    do_action( 'wp_enqueue_scripts' );
    same( count( $GLOBALS['puc_test']['scripts'] ), 1, 'one lightweight script' );
    same( count( $GLOBALS['puc_test']['styles'] ), 1, 'one stylesheet' );
    same( $GLOBALS['puc_test']['scripts']['puc-converter'][3], true, 'script in footer' );
    same( $GLOBALS['puc_test']['scripts']['puc-converter'][2], '0.1.2', 'versioned asset' );
    same( $GLOBALS['puc_test']['localized']['PUCConfig'], array( 'decimals' => 1, 'remember' => true, 'defaultUnit' => 'in', 'header' => false, 'footer' => false, 'headerSelector' => '', 'footerSelector' => '', 'position' => 'bottom-right', 'labels' => array( 'in' => 'Units: in', 'cm' => 'Units: cm' ) ), 'exact frontend configuration contract' );
    expect( false !== strpos( $GLOBALS['puc_test']['inline']['puc-converter'], '--puc-offset:20px' ), 'bounded custom properties emitted' );
    expect( ! isset( $GLOBALS['puc_test']['hooks']['the_content'] ) && ! isset( $GLOBALS['puc_test']['hooks']['woocommerce_available_variation'] ), 'no authored-content or commerce mutation hooks' );
    $plugin = setup_plugin( array( 'header' => 1, 'footer' => 1 ) );
    $menu = apply_filters( 'wp_nav_menu_items', '<li>Link</li>', (object) array( 'theme_location' => 'menu_1' ) );
    expect( false !== strpos( $menu, '<li class="puc-menu-item">' ), 'header menu hook appends control' );
    same( apply_filters( 'wp_nav_menu_items', $menu, (object) array( 'theme_location' => 'menu_1' ) ), $menu, 'menu append idempotent' );
    expect( false !== strpos( $plugin->menu_items( '', (object) array( 'theme_location' => 'menu_mobile' ) ), 'data-puc-toggle' ), 'mobile menu fallback' );
    same( $plugin->menu_items( 'original', (object) array( 'theme_location' => 'footer' ) ), 'original', 'footer menu unchanged' );
    same( $plugin->menu_items( 'original', (object) array( 'theme_location' => 'unrelated' ) ), 'original', 'unrelated menu unchanged' );
    same( $plugin->menu_items( 'original', array() ), 'original', 'malformed menu args safe' );
    ob_start(); do_action( 'wp_footer' ); $html = ob_get_clean();
    expect( false !== strpos( $html, 'class="puc-floating" data-position="bottom-right"' ), 'floating placement emitted' );
    expect( false !== strpos( $html, 'class="puc-footer"' ), 'footer placement emitted' );
    same( substr_count( $html, 'data-puc-toggle' ), 2, 'two requested automatic controls' );
    $GLOBALS['puc_test']['admin'] = true;
    same( $plugin->shortcode(), '', 'admin shortcode gated' );
    foreach ( array( 'brizy-edit', 'brizy-edit-iframe', 'brizy-preview' ) as $flag ) {
        puc_test_reset(); $_GET[ $flag ] = '';
        $plugin = new PUC_Plugin( $settings ); $plugin->init();
        same( $GLOBALS['puc_test']['hooks'], array(), 'editor flag suppresses hooks: ' . $flag );
        same( $plugin->shortcode(), '', 'editor flag suppresses shortcode: ' . $flag );
    }
    puc_test_reset(); $_GET['brizy-edit'] = array( 'x' ); expect( PUC_Plugin::editor_request(), 'array editor flag fails closed' );
    puc_test_reset(); $_SERVER['REQUEST_URI'] = '/?brizy-edit-iframe=1'; expect( PUC_Plugin::editor_request(), 'URI editor query fallback' );
    puc_test_reset(); $_SERVER['REQUEST_URI'] = '/brizy-edit-content/'; expect( ! PUC_Plugin::editor_request(), 'ordinary path not mistaken for editor' );
    $settings->init(); do_action( 'admin_init' ); do_action( 'admin_menu' );
    $registration = $GLOBALS['puc_test']['settings'][ PUC_OPTION_KEY ];
    same( $registration[0], 'puc_settings_group', 'Settings API group registered' );
    same( $registration[1]['sanitize_callback'], array( $settings, 'sanitize' ), 'Settings API sanitizer registered' );
    same( $registration[1]['show_in_rest'], false, 'settings not exposed to REST' );
    same( $GLOBALS['puc_test']['pages']['portare-unit-converter'][2], 'manage_options', 'settings page capability' );
    update_option( PUC_OPTION_KEY, array_merge( $d, array( 'header_selector' => '\"><script>alert(1)</script>' ) ) );
    ob_start(); $settings->render(); $html = ob_get_clean();
    expect( false !== strpos( $html, 'name="_wpnonce"' ) && false !== strpos( $html, 'action="options.php"' ), 'form uses Settings API nonce/save path' );
    expect( false === strpos( $html, '<script>' ), 'settings output rejects injection' );
    expect( false !== strpos( $html, '[portare_unit_toggle]' ) && false !== strpos( $html, 'data-puc-ignore' ), 'settings explain shortcode and opt-out' );
    expect( false !== strpos( $html, 'name="puc_settings[header_selector]"' ) && false !== strpos( $html, 'name="puc_settings[floating_offset]"' ), 'selector and dimension controls present' );
    same( substr_count( $html, 'class="puc-color-picker"' ), 2, 'background and text have colour pickers' );
    same( substr_count( $html, 'data-css-var=' ), 4, 'four scoped appearance bindings' );
    expect( false !== strpos( $html, 'id="puc-preview"' ) && false !== strpos( $html, 'Live button preview' ), 'live preview region rendered' );
    expect( false === strpos( $html, 'Static preview' ), 'old static preview removed' );
    same( substr_count( $html, 'data-puc-preview-toggle' ), 5, 'unit samples and three placement examples rendered' );
    expect( false !== strpos( $html, 'type="button"' ) && false !== strpos( $html, 'preview only' ), 'preview controls do not submit settings' );
    $before_preview = get_option( PUC_OPTION_KEY );
    puc_test_reset();
    $settings->assets( 'dashboard' );
    same( $GLOBALS['puc_test']['scripts'], array(), 'preview assets excluded on unrelated admin pages' );
    $settings->assets( 'settings_page_portare-unit-converter' );
    same( $GLOBALS['puc_test']['scripts']['puc-settings'][1], array( 'jquery', 'wp-color-picker' ), 'native picker dependencies registered' );
    expect( isset( $GLOBALS['puc_test']['styles']['wp-color-picker'] ), 'native picker styles enqueued' );
    expect( isset( $GLOBALS['puc_test']['styles']['puc-preview-buttons'] ), 'preview reuses frontend button stylesheet' );
    expect( ! isset( $GLOBALS['puc_test']['scripts']['puc-converter'] ), 'sitewide converter never runs in admin preview' );
    same( get_option( PUC_OPTION_KEY ), false, 'asset loading performs no settings writes' );
    puc_test_reset();
    $GLOBALS['puc_test']['capability'] = false;
    $settings->assets( 'settings_page_portare-unit-converter' );
    same( $GLOBALS['puc_test']['scripts'], array(), 'unauthorized users receive no preview assets' );
    $GLOBALS['puc_test']['capability'] = false; $denied = false;
    try { $settings->render(); } catch ( RuntimeException $exception ) { $denied = true; }
    expect( $denied, 'unauthorized rendering denied' );
    puc_test_reset(); update_option( PUC_OPTION_KEY, $d ); update_option( 'unrelated', 'keep' );
    define( 'WP_UNINSTALL_PLUGIN', 'portare-unit-converter/portare-unit-converter.php' );
    require dirname( __DIR__ ) . '/uninstall.php';
    same( get_option( PUC_OPTION_KEY ), false, 'uninstall deletes plugin settings' );
    same( get_option( 'unrelated' ), 'keep', 'uninstall preserves unrelated data' );
    same( $GLOBALS['puc_test']['deleted'], array( PUC_OPTION_KEY ), 'uninstall deletes exactly one option' );
    echo "\n{$count} assertions passed; 0 failures.\n";
} catch ( Throwable $exception ) {
    fwrite( STDERR, $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n" ); exit( 1 );
}
