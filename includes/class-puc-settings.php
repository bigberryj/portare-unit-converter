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
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
    }
    public function add_page(): void {
        add_options_page( 'Portare Unit Converter', 'Portare Unit Converter', 'manage_options', 'portare-unit-converter', array( $this, 'render' ) );
    }
    public function register(): void {
        register_setting( 'puc_settings_group', PUC_OPTION_KEY, array( 'type' => 'array', 'sanitize_callback' => array( $this, 'sanitize' ), 'default' => self::defaults(), 'show_in_rest' => false ) );
    }
    public function assets( $hook ): void {
        if ( 'settings_page_portare-unit-converter' !== $hook || ! current_user_can( 'manage_options' ) ) { return; }
        wp_enqueue_style( 'wp-color-picker' );
        // Reuse the real frontend button CSS; never run the sitewide converter in admin.
        wp_enqueue_style( 'puc-preview-buttons', plugins_url( 'assets/converter.css', PUC_PLUGIN_FILE ), array(), PUC_VERSION );
        wp_enqueue_style( 'puc-settings', plugins_url( 'assets/settings.css', PUC_PLUGIN_FILE ), array( 'puc-preview-buttons', 'wp-color-picker' ), PUC_VERSION );
        wp_enqueue_script( 'puc-settings', plugins_url( 'assets/settings.js', PUC_PLUGIN_FILE ), array( 'jquery', 'wp-color-picker' ), PUC_VERSION, true );
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
        echo '<div class="wrap puc-settings"><h1>Portare Unit Converter</h1><p>Display-only inches to centimetres. Saved content, product data, prices and numeric inputs are never changed. Images and PDFs cannot be converted; unlabelled numbers are skipped.</p><p>With initial units set to inches, no conversion happens until a visitor toggles (unless a remembered preference applies). Brizy editor/preview and wp-admin are excluded.</p><form id="puc-settings-form" method="post" action="options.php">';
        settings_fields( 'puc_settings_group' );
        echo '<div class="puc-settings-layout"><div class="puc-settings-controls"><h2>Behaviour and placement</h2><fieldset><legend>Enable and placements (choose any combination)</legend>';
        foreach ( array( 'enabled' => 'Enable converter globally', 'floating' => 'Floating button', 'header' => 'Header/menu button', 'footer' => 'Footer button', 'remember' => 'Remember visitor preference on this browser' ) as $key => $label ) {
            echo '<p><label><input type="checkbox" name="' . esc_attr( PUC_OPTION_KEY . '[' . $key . ']' ) . '" value="1"' . checked( $values[ $key ], 1, false ) . '> ' . esc_html( $label ) . '</label></p>';
        }
        echo '</fieldset>';
        $this->select( 'position', 'Floating corner', array( 'top-left' => 'Top left', 'top-right' => 'Top right', 'bottom-left' => 'Bottom left', 'bottom-right' => 'Bottom right' ), $values );
        $this->select( 'decimals', 'Decimal places', array( 0 => '0', 1 => '1', 2 => '2', 3 => '3' ), $values );
        $this->select( 'default_unit', 'Initial units', array( 'in' => 'Inches', 'cm' => 'Centimetres' ), $values );
        $menus = get_registered_nav_menus(); unset( $menus['footer'] );
        $this->select( 'header_location', 'Header menu location (mobile menu fallback supported)', array( '' => 'Automatic DOM fallback' ) + $menus, $values );
        echo '<h2>Button appearance</h2>';
        $properties = array( 'background_color' => '--puc-bg', 'text_color' => '--puc-text', 'radius' => '--puc-radius', 'floating_offset' => '--puc-offset' );
        $defaults = self::defaults();
        foreach ( array( 'background_color' => 'Button background colour', 'text_color' => 'Button text colour', 'radius' => 'Button radius (0–100 px)', 'floating_offset' => 'Floating edge offset (0–200 px)', 'header_selector' => 'Optional header CSS selector', 'footer_selector' => 'Optional footer CSS selector' ) as $key => $label ) {
            $numeric = in_array( $key, array( 'radius', 'floating_offset' ), true );
            $color = in_array( $key, array( 'background_color', 'text_color' ), true );
            $extra = isset( $properties[ $key ] ) ? ' data-css-var="' . esc_attr( $properties[ $key ] ) . '" data-default="' . esc_attr( (string) $defaults[ $key ] ) . '" data-unit="' . ( $numeric ? 'px' : '' ) . '"' : '';
            if ( $color ) { $extra .= ' class="puc-color-picker" data-default-color="' . esc_attr( $defaults[ $key ] ) . '" pattern="#[a-fA-F0-9]{6}" maxlength="7" required aria-describedby="puc-preview-help"'; }
            echo '<p><label for="puc-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label> <input id="puc-' . esc_attr( $key ) . '" type="' . ( $numeric ? 'number' : 'text' ) . '" name="' . esc_attr( PUC_OPTION_KEY . '[' . $key . ']' ) . '" value="' . esc_attr( (string) $values[ $key ] ) . '"' . $extra . ( $numeric ? ' min="0" max="' . ( 'radius' === $key ? '100' : '200' ) . '" step="1"' : ( $color ? '' : ' maxlength="200"' ) ) . '></p>';
        }
        echo '<p>Shortcode: <code>[portare_unit_toggle]</code> works independently of placements. Global disable removes all controls and frontend assets. Opt out a content subtree with <code>data-puc-ignore</code>. Leave selectors blank for automatic theme/builder fallback.</p>';
        submit_button(); echo '</div>';
        echo '<aside id="puc-preview" class="puc-preview" aria-labelledby="puc-preview-title"><h2 id="puc-preview-title">Live button preview</h2><p id="puc-preview-help">Changes below are a preview only. Click Save Changes to apply them to the website. Click a preview button to try switching units.</p><div class="puc-preview-samples"><button type="button" class="puc-control" data-puc-preview-toggle data-puc-preview-unit="in" aria-label="Preview inches button" aria-pressed="false">Units: in</button><button type="button" class="puc-control" data-puc-preview-toggle data-puc-preview-unit="cm" aria-label="Preview centimetres button" aria-pressed="true">Units: cm</button></div><h3>Placement preview</h3><div class="puc-preview-canvas"><div class="puc-preview-header"><span>Site header</span><span data-puc-preview-placement="header"><button type="button" class="puc-control" data-puc-preview-toggle>Units: in</button></span></div><div class="puc-preview-content"><strong>Example product</strong><p data-puc-preview-measurement>72 inches</p><small>Selected placements are shown here.</small></div><div class="puc-preview-footer"><span>Site footer</span><span data-puc-preview-placement="footer"><button type="button" class="puc-control" data-puc-preview-toggle>Units: in</button></span></div><div class="puc-floating" data-puc-preview-placement="floating" data-position="' . esc_attr( $values['position'] ) . '"><button type="button" class="puc-control" data-puc-preview-toggle>Units: in</button></div></div><p class="puc-preview-message" role="status" aria-live="polite"></p><noscript><p>Enable JavaScript for the live preview and colour picker. You can still enter six-digit hex colours and save normally.</p></noscript></aside></div></form></div>';
    }
}
