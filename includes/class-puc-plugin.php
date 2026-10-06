<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PUC_Plugin {
    private $settings;
    public function __construct( PUC_Settings $settings ) { $this->settings = $settings; }
    public static function activate(): void { add_option( PUC_OPTION_KEY, PUC_Settings::defaults() ); }
    public function init(): void {
        // Register the shortcode even when disabled so it disappears rather than printing its tag.
        add_shortcode( 'portare_unit_toggle', array( $this, 'shortcode' ) );
        if ( ! $this->available() ) { return; }
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
        $options = $this->settings->get();
        if ( $options['floating'] || $options['footer'] ) { add_action( 'wp_footer', array( $this, 'render_placements' ), 30 ); }
        if ( $options['header'] ) { add_filter( 'wp_nav_menu_items', array( $this, 'menu_items' ), 20, 2 ); }
    }
    public static function editor_request(): bool {
        $uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $query = wp_parse_url( $uri, PHP_URL_QUERY );
        $flags = array();
        if ( is_string( $query ) ) { parse_str( $query, $flags ); }
        $flags = array_merge( $flags, is_array( $_GET ) ? $_GET : array() );
        $editor_flags = array();
        foreach ( array( 'brizy-edit', 'brizy-edit-iframe', 'brizy-preview' ) as $flag ) {
            if ( array_key_exists( $flag, $flags ) ) {
                $value = $flags[ $flag ];
                $editor_flags[ $flag ] = is_scalar( $value ) ? sanitize_text_field( wp_unslash( (string) $value ) ) : '';
            }
        }
        // Brizy supports bare flags. Values are sanitized but presence gates the editor;
        // malformed array flags also fail closed rather than allowing conversion.
        return ! empty( $editor_flags );
    }
    public function available(): bool { return ! is_admin() && ! self::editor_request() && 1 === $this->settings->get()['enabled']; }
    public function enqueue(): void {
        if ( ! $this->available() ) { return; }
        $o = $this->settings->get();
        wp_enqueue_style( 'puc-converter', plugins_url( 'assets/converter.css', PUC_PLUGIN_FILE ), array(), PUC_VERSION );
        wp_enqueue_script( 'puc-converter', plugins_url( 'assets/converter.js', PUC_PLUGIN_FILE ), array(), PUC_VERSION, true );
        wp_localize_script( 'puc-converter', 'PUCConfig', array( 'decimals' => $o['decimals'], 'remember' => (bool) $o['remember'], 'defaultUnit' => $o['default_unit'], 'header' => (bool) $o['header'], 'footer' => (bool) $o['footer'], 'headerSelector' => $o['header_selector'], 'footerSelector' => $o['footer_selector'], 'position' => $o['position'] ) );
        wp_add_inline_style( 'puc-converter', ':root{--puc-bg:' . $o['background_color'] . ';--puc-text:' . $o['text_color'] . ';--puc-radius:' . $o['radius'] . 'px;--puc-offset:' . $o['floating_offset'] . 'px;}' );
    }
    public function button(): string {
        return '<button type="button" class="puc-control" data-puc-toggle aria-pressed="false"><span data-puc-label>' . esc_html__( 'Units: in', 'portare-unit-converter' ) . '</span></button>';
    }
    public function shortcode( $attributes = array(), $content = null ): string { return $this->available() ? $this->button() : ''; }
    public function menu_items( $items, $args ): string {
        if ( ! $this->available() || ! is_object( $args ) ) { return $items; }
        $o = $this->settings->get();
        $location = isset( $args->theme_location ) && is_string( $args->theme_location ) ? $args->theme_location : '';
        if ( ! $o['header'] || '' === $location || 'footer' === $location || ! in_array( $location, array( $o['header_location'], 'menu_mobile' ), true ) ) { return $items; }
        if ( false !== strpos( $items, 'class="puc-menu-item"' ) ) { return $items; }
        return $items . '<li class="puc-menu-item">' . $this->button() . '</li>';
    }
    public function render_placements(): void {
        if ( ! $this->available() ) { return; }
        $o = $this->settings->get();
        if ( $o['floating'] ) { echo '<div class="puc-floating" data-position="' . esc_attr( $o['position'] ) . '">' . $this->button() . '</div>'; }
        if ( $o['footer'] ) { echo '<div class="puc-footer">' . $this->button() . '</div>'; }
    }
}
