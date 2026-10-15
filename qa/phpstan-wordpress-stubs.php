<?php
/** Minimal executable WordPress API doubles for CLI tests/static analysis only. */
if ( PHP_SAPI !== 'cli' ) { exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__ ) . '/' ); }
function puc_test_reset(): void {
    $GLOBALS['puc_test'] = array( 'hooks' => array(), 'shortcodes' => array(), 'options' => array(), 'admin' => false, 'capability' => true, 'menus' => array( 'menu_1' => 'Main menu', 'menu_mobile' => 'Mobile menu', 'footer' => 'Footer menu' ), 'styles' => array(), 'scripts' => array(), 'localized' => array(), 'inline' => array(), 'settings' => array(), 'pages' => array(), 'activation' => array(), 'deleted' => array() );
    $_GET = array(); $_SERVER['REQUEST_URI'] = '/';
}
puc_test_reset();
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugins_url( $path, $file = '' ) { return 'https://example.test/wp-content/plugins/portare-unit-converter/' . $path; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    if ( ! is_callable( $callback ) ) { throw new RuntimeException( 'Invalid callback for ' . $hook ); }
    $GLOBALS['puc_test']['hooks'][ $hook ][ $priority ][] = array( $callback, $accepted_args ); return true;
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return add_filter( $hook, $callback, $priority, $accepted_args ); }
function apply_filters( $hook, $value, ...$args ) {
    $callbacks = $GLOBALS['puc_test']['hooks'][ $hook ] ?? array(); ksort( $callbacks );
    foreach ( $callbacks as $group ) { foreach ( $group as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); } }
    return $value;
}
function do_action( $hook, ...$args ) {
    $callbacks = $GLOBALS['puc_test']['hooks'][ $hook ] ?? array(); ksort( $callbacks );
    foreach ( $callbacks as $group ) { foreach ( $group as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); } }
}
function add_shortcode( $tag, $callback ) { $GLOBALS['puc_test']['shortcodes'][ $tag ] = $callback; }
function register_activation_hook( $file, $callback ) { $GLOBALS['puc_test']['activation'][ $file ] = $callback; }
function get_option( $key, $default = false ) { return $GLOBALS['puc_test']['options'][ $key ] ?? $default; }
function add_option( $key, $value ) {
    if ( array_key_exists( $key, $GLOBALS['puc_test']['options'] ) ) { return false; }
    $GLOBALS['puc_test']['options'][ $key ] = $value; return true;
}
function update_option( $key, $value ) { $GLOBALS['puc_test']['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['puc_test']['options'][ $key ] ); $GLOBALS['puc_test']['deleted'][] = $key; return true; }
function is_admin() { return $GLOBALS['puc_test']['admin']; }
function current_user_can( $capability ) { return 'manage_options' === $capability && $GLOBALS['puc_test']['capability']; }
function wp_die( $message ) { throw new RuntimeException( $message ); }
function get_registered_nav_menus() { return $GLOBALS['puc_test']['menus']; }
function register_setting( $group, $key, $args = array() ) { $GLOBALS['puc_test']['settings'][ $key ] = array( $group, $args ); }
function add_options_page( $title, $menu_title, $capability, $slug, $callback ) { $GLOBALS['puc_test']['pages'][ $slug ] = array( $title, $menu_title, $capability, $callback ); return 'settings_page_' . $slug; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function __( $value, $domain = '' ) { return $value; }
function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
function checked( $value, $expected = true, $echo = true ) { $result = (string) $value === (string) $expected ? ' checked="checked"' : ''; if ( $echo ) { echo $result; } return $result; }
function selected( $value, $expected = true, $echo = true ) { $result = (string) $value === (string) $expected ? ' selected="selected"' : ''; if ( $echo ) { echo $result; } return $result; }
function settings_fields( $group ) { echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '"><input type="hidden" name="_wpnonce" value="stub-nonce">'; }
function submit_button() { echo '<button type="submit">Save Changes</button>'; }
function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false, $media = 'all' ) { $GLOBALS['puc_test']['styles'][ $handle ] = array( $src, $deps, $version, $media ); }
function wp_enqueue_script( $handle, $src, $deps = array(), $version = false, $footer = false ) { $GLOBALS['puc_test']['scripts'][ $handle ] = array( $src, $deps, $version, $footer ); }
function wp_localize_script( $handle, $name, $data ) { $GLOBALS['puc_test']['localized'][ $name ] = $data; return true; }
function wp_add_inline_style( $handle, $data ) { $GLOBALS['puc_test']['inline'][ $handle ] = $data; return true; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_unslash( $value ) { return stripslashes( $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
