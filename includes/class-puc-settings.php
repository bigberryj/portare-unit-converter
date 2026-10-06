<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PUC_Settings {
    public static function defaults(): array {
        return array( 'enabled' => 1, 'floating' => 1, 'header' => 0, 'footer' => 0, 'remember' => 1,
            'default_unit' => 'in', 'decimals' => 1, 'position' => 'bottom-right',
            'header_location' => 'menu_1', 'header_selector' => '', 'footer_selector' => '',
            'background_color' => '#173942', 'text_color' => '#ffffff', 'radius' => 8, 'floating_offset' => 20 );
    }
    public function init(): void {
        add_action( 'admin_menu', array( $this, 'add_page' ) );
        add_action( 'admin_init', array( $this, 'register' ) );
    }
    public function add_page(): void {
        add_options_page( 'Portare Unit Converter', 'Portare Unit Converter', 'manage_options', 'portare-unit-converter', array( $this, 'render' ) );
    }
    public function register(): void {
        register_setting( 'puc_settings_group', PUC_OPTION_KEY, array( 'type' => 'array', 'sanitize_callback' => array( $this, 'sanitize' ), 'default' => self::defaults(), 'show_in_rest' => false ) );
    }
    private static function flag( $value ): int { return ( 1 === $value || '1' === $value ) ? 1 : 0; }
    private static function integer( $value, int $min, int $max, int $default ): int {
        if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/\A[0-9]{1,6}\z/', $value ) ) ) { return $default; }
        return max( $min, min( $max, (int) $value ) );
    }
    public static function selector( $value ): string {
        if ( ! is_string( $value ) || strlen( $value ) > 200 ) { return ''; }
        $value = trim( $value );
        // Conservative plain CSS selectors, not stylesheet rules, escapes or script expressions.
        if ( preg_match( '/[<>{};\\\\`\x00-\x1f]/', $value ) || preg_match( '/(?:javascript|expression|url\s*\(|script)/i', $value ) ) { return ''; }
        if ( ! preg_match( '/\A[a-zA-Z0-9_\-\s.#,:>+~\[\]="\x27()*^$|]*\z/', $value ) ) { return ''; }
        return $value;
    }
    public function sanitize( $input ): array {
        $defaults = self::defaults();
        $input = is_array( $input ) ? $input : array();
        $out = $defaults;
        foreach ( array( 'enabled', 'floating', 'header', 'footer', 'remember' ) as $key ) { $out[ $key ] = self::flag( $input[ $key ] ?? 0 ); }
        foreach ( array( 'default_unit' => array( 'in', 'cm' ), 'position' => array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' ) ) as $key => $allowed ) {
            $value = $input[ $key ] ?? null;
            $out[ $key ] = is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $defaults[ $key ];
        }
        $menus = get_registered_nav_menus();
        $location = $input['header_location'] ?? null;
        $fallback = array_key_exists( 'menu_1', $menus ) ? 'menu_1' : '';
        $out['header_location'] = '' === $location ? '' : ( is_string( $location ) && array_key_exists( $location, $menus ) && 'footer' !== $location ? $location : $fallback );
        foreach ( array( 'header_selector', 'footer_selector' ) as $key ) { $out[ $key ] = self::selector( $input[ $key ] ?? '' ); }
        foreach ( array( 'background_color', 'text_color' ) as $key ) {
            $value = $input[ $key ] ?? null;
            $out[ $key ] = is_string( $value ) && preg_match( '/\A#[0-9a-fA-F]{6}\z/', $value ) ? strtolower( $value ) : $defaults[ $key ];
        }
        $out['decimals'] = self::integer( $input['decimals'] ?? null, 0, 3, 1 );
        $out['radius'] = self::integer( $input['radius'] ?? null, 0, 100, 8 );
        $out['floating_offset'] = self::integer( $input['floating_offset'] ?? null, 0, 200, 20 );
        return $out;
    }
    public function get(): array {
        $stored = get_option( PUC_OPTION_KEY, array() );
        return $this->sanitize( array_merge( self::defaults(), is_array( $stored ) ? $stored : array() ) );
    }
    private function select( string $key, string $label, array $options, array $values ): void {
        echo '<p><label for="puc-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label> <select id="puc-' . esc_attr( $key ) . '" name="' . esc_attr( PUC_OPTION_KEY . '[' . $key . ']' ) . '">';
        foreach ( $options as $value => $text ) { echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $values[ $key ], (string) $value, false ) . '>' . esc_html( $text ) . '</option>'; }
        echo '</select></p>';
    }
    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to manage these settings.', 'portare-unit-converter' ) ); return; }
        $values = $this->get();
        echo '<div class="wrap"><h1>Portare Unit Converter</h1><p>Display-only inches to centimetres. Saved content, product data, prices and numeric inputs are never changed. Images and PDFs cannot be converted; unlabelled numbers are skipped.</p><p>With initial units set to inches, no conversion happens until a visitor toggles (unless a remembered preference applies). Brizy editor/preview and wp-admin are excluded.</p><form method="post" action="options.php">';
        settings_fields( 'puc_settings_group' );
        echo '<fieldset><legend>Enable and placements (choose any combination)</legend>';
        foreach ( array( 'enabled' => 'Enable converter globally', 'floating' => 'Floating button', 'header' => 'Header/menu button', 'footer' => 'Footer button', 'remember' => 'Remember visitor preference on this browser' ) as $key => $label ) {
            echo '<p><label><input type="checkbox" name="' . esc_attr( PUC_OPTION_KEY . '[' . $key . ']' ) . '" value="1"' . checked( $values[ $key ], 1, false ) . '> ' . esc_html( $label ) . '</label></p>';
        }
        echo '</fieldset>';
        $this->select( 'position', 'Floating corner', array( 'top-left' => 'Top left', 'top-right' => 'Top right', 'bottom-left' => 'Bottom left', 'bottom-right' => 'Bottom right' ), $values );
        $this->select( 'decimals', 'Decimal places', array( 0 => '0', 1 => '1', 2 => '2', 3 => '3' ), $values );
        $this->select( 'default_unit', 'Initial units', array( 'in' => 'Inches', 'cm' => 'Centimetres' ), $values );
        $menus = get_registered_nav_menus(); unset( $menus['footer'] );
        $this->select( 'header_location', 'Header menu location (mobile menu fallback supported)', array( '' => 'Automatic DOM fallback' ) + $menus, $values );
        foreach ( array( 'header_selector' => 'Optional header CSS selector', 'footer_selector' => 'Optional footer CSS selector', 'background_color' => 'Button background (six-digit hex)', 'text_color' => 'Button text (six-digit hex)', 'radius' => 'Button radius (0–100 px)', 'floating_offset' => 'Floating edge offset (0–200 px)' ) as $key => $label ) {
            $numeric = in_array( $key, array( 'radius', 'floating_offset' ), true );
            echo '<p><label for="puc-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label> <input id="puc-' . esc_attr( $key ) . '" type="' . ( $numeric ? 'number' : 'text' ) . '" name="' . esc_attr( PUC_OPTION_KEY . '[' . $key . ']' ) . '" value="' . esc_attr( (string) $values[ $key ] ) . '"' . ( $numeric ? ' min="0" max="' . ( 'radius' === $key ? '100' : '200' ) . '" step="1"' : ' maxlength="200"' ) . '></p>';
        }
        echo '<p>Shortcode: <code>[portare_unit_toggle]</code> works independently of placements. Global disable removes all controls and frontend assets. Opt out a content subtree with <code>data-puc-ignore</code>. Leave selectors blank for automatic theme/builder fallback.</p>';
        echo '<fieldset><legend>Static preview (not interactive)</legend><button type="button" disabled>Units: in</button> <button type="button" disabled>Units: cm</button></fieldset>';
        submit_button(); echo '</form></div>';
    }
}
