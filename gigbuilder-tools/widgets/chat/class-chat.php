<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gigbuilder_Chat {

    private static $popup_registered  = false;
    private static $assets_enqueued   = false;
    private static $webhook_url       = 'https://n8n.gigbuilder.com/webhook/8dabba9e-4d8d-49a4-86f8-43c90f08dd2c/chat';
    // Token is stored in settings, populated from CRM auth or manually

    public static function init() {
        add_shortcode( 'gigbuilder_chat', array( __CLASS__, 'shortcode_inline' ) );
        add_shortcode( 'gigbuilder_chat_popup', array( __CLASS__, 'shortcode_popup' ) );

        add_action( 'wp_ajax_gigbuilder_chat_send', array( __CLASS__, 'ajax_chat_send' ) );
        add_action( 'wp_ajax_nopriv_gigbuilder_chat_send', array( __CLASS__, 'ajax_chat_send' ) );
    }

    /**
     * Enqueue chat widget assets (once per page load).
     */
    private static function enqueue_assets() {
        if ( self::$assets_enqueued ) {
            return;
        }
        self::$assets_enqueued = true;

        wp_enqueue_style(
            'gigbuilder-chat',
            GIGBUILDER_TOOLS_URL . 'widgets/chat/chat.css',
            array(),
            GIGBUILDER_TOOLS_VERSION
        );

        wp_enqueue_script(
            'gigbuilder-chat',
            GIGBUILDER_TOOLS_URL . 'widgets/chat/chat.js',
            array(),
            GIGBUILDER_TOOLS_VERSION,
            true
        );

        $company_name   = Gigbuilder_Settings::get_chat_setting( 'chat_company_name' );
        $avatar         = Gigbuilder_Settings::get_chat_setting( 'chat_avatar' );
        $launcher_text  = Gigbuilder_Settings::get_chat_setting( 'chat_launcher_text' );
        $welcome_msg    = Gigbuilder_Settings::get_chat_setting( 'chat_welcome_message' );

        wp_localize_script( 'gigbuilder-chat', 'gigbuilderChat', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'gigbuilder_chat' ),
            'companyName'    => $company_name ?: get_bloginfo( 'name' ),
            'avatar'         => $avatar ?: "\xF0\x9F\x8E\xB5",
            'launcherText'   => $launcher_text,
            'welcomeMessage' => $welcome_msg,
        ) );
    }

    /**
     * Shortcode: [gigbuilder_chat] — inline mode.
     */
    public static function shortcode_inline( $atts ) {
        if ( ! Gigbuilder_Settings::get_setting( 'authenticated' ) ) {
            return '';
        }

        self::enqueue_assets();

        return '<div class="gbAiChat" data-mode="inline"></div>';
    }

    /**
     * Shortcode: [gigbuilder_chat_popup] — popup mode (injected in footer).
     */
    public static function shortcode_popup( $atts ) {
        if ( ! Gigbuilder_Settings::get_setting( 'authenticated' ) ) {
            return '';
        }

        if ( self::$popup_registered ) {
            return '';
        }
        self::$popup_registered = true;

        self::enqueue_assets();

        add_action( 'wp_footer', array( __CLASS__, 'render_popup' ) );

        return '';
    }

    /**
     * Render popup markup in the footer.
     */
    public static function render_popup() {
        echo '<div class="gbAiChat" data-mode="popup"></div>';
    }

    /**
     * AJAX: Proxy chat message to n8n webhook.
     */
    public static function ajax_chat_send() {
        check_ajax_referer( 'gigbuilder_chat', 'nonce' );

        $webhook_url = self::$webhook_url;

        $session_id    = sanitize_text_field( wp_unslash( $_POST['sessionId'] ?? '' ) );
        $chat_input    = sanitize_text_field( wp_unslash( $_POST['chatInput'] ?? '' ) );
        $voice_enabled = ( $_POST['voiceEnabled'] ?? '' ) === 'true';

        if ( empty( $chat_input ) ) {
            wp_send_json_error( array( 'message' => 'Message cannot be empty.' ) );
        }

        $metadata_path = Gigbuilder_Settings::get_chat_metadata_path();
        $token         = Gigbuilder_Settings::get_chat_setting( 'chat_token' );

        $payload = array(
            'action'       => 'sendMessage',
            'sessionId'    => $session_id,
            'chatInput'    => $chat_input,
            'voiceEnabled' => $voice_enabled,
            'metadata'     => array(
                'test'   => false,
                'path'   => $metadata_path,
                'token'  => $token,
                'url'    => isset( $_POST['pageUrl'] ) ? esc_url_raw( wp_unslash( $_POST['pageUrl'] ) ) : '',
                'domain' => home_url(),
                'html'   => true,
                'widget' => true,
            ),
        );

        $response = wp_remote_post( $webhook_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 90,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Failed to reach chat agent.' ) );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        // Pass through whatever n8n returns — even 500 responses may contain valid chat output
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
            wp_send_json_success( $data );
        }

        wp_send_json_error( array( 'message' => 'Chat agent is unavailable. Please try again.' ) );
    }
}
