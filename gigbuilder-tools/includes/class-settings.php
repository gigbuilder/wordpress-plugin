<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gigbuilder_Settings {

    private static $option_group = 'gigbuilder_tools';
    private static $option_name  = 'gigbuilder_tools_settings';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    public static function add_menu() {
        add_options_page(
            'Gigbuilder Tools',
            'Gigbuilder Tools',
            'manage_options',
            'gigbuilder-tools',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function register_settings() {
        register_setting( self::$option_group, self::$option_name, array(
            'sanitize_callback' => array( __CLASS__, 'sanitize' ),
        ) );

        add_settings_section(
            'gigbuilder_connection',
            'CRM Connection',
            null,
            'gigbuilder-tools'
        );

        add_settings_field(
            'server_url',
            'Server URL',
            array( __CLASS__, 'render_server_url' ),
            'gigbuilder-tools',
            'gigbuilder_connection'
        );

        add_settings_field(
            'db_path',
            'Database Path',
            array( __CLASS__, 'render_db_path' ),
            'gigbuilder-tools',
            'gigbuilder_connection'
        );
    }

    public static function sanitize( $input ) {
        $clean = array();
        $clean['server_url'] = esc_url_raw( rtrim( $input['server_url'] ?? '', '/' ) );
        $clean['db_path']    = sanitize_text_field( $input['db_path'] ?? '' );
        return $clean;
    }

    public static function render_server_url() {
        $settings = get_option( self::$option_name, array() );
        $value = $settings['server_url'] ?? '';
        echo '<input type="text" name="' . self::$option_name . '[server_url]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="https://tpa.gigbuilder.com" />';
        echo '<p class="description">The Gigbuilder CRM server URL (no trailing slash).</p>';
    }

    public static function render_db_path() {
        $settings = get_option( self::$option_name, array() );
        $value = $settings['db_path'] ?? '';
        echo '<input type="text" name="' . self::$option_name . '[db_path]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="/cal70.nsf" />';
        echo '<p class="description">Database file path (e.g., /cal70.nsf or /cal/il/dj123.nsf).</p>';
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Gigbuilder Tools Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::$option_group );
                do_settings_sections( 'gigbuilder-tools' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function get_setting( $key ) {
        $settings = get_option( self::$option_name, array() );
        return $settings[ $key ] ?? '';
    }

    public static function get_endpoint_url() {
        $server = self::get_setting( 'server_url' );
        $db     = self::get_setting( 'db_path' );
        if ( empty( $server ) || empty( $db ) ) {
            return '';
        }
        return $server . $db . '/jsontools?open';
    }
}
