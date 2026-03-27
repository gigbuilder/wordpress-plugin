<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gigbuilder_Availability {

    public static function init() {
        add_shortcode( 'gigbuilder_availability', array( __CLASS__, 'render_shortcode' ) );
        add_shortcode( 'gigbuilder_datepicker', array( __CLASS__, 'render_datepicker_shortcode' ) );
        add_shortcode( 'gigbuilder_clientcenter', array( __CLASS__, 'render_clientcenter_shortcode' ) );
        add_shortcode( 'gigbuilder_guestrequests', array( __CLASS__, 'render_guestrequests_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        // Public AJAX endpoints — nopriv required so unauthenticated visitors can check dates and submit bookings.
        // CSRF protection via nonce; server-side proxy prevents direct CRM access.
        add_action( 'wp_ajax_gigbuilder_check_date', array( __CLASS__, 'ajax_check_date' ) );
        add_action( 'wp_ajax_nopriv_gigbuilder_check_date', array( __CLASS__, 'ajax_check_date' ) );
        add_action( 'wp_ajax_gigbuilder_submit_booking', array( __CLASS__, 'ajax_submit_booking' ) );
        add_action( 'wp_ajax_nopriv_gigbuilder_submit_booking', array( __CLASS__, 'ajax_submit_booking' ) );
    }

    public static function enqueue_assets() {
        if ( ! is_singular() ) {
            return;
        }

        global $post;
        if ( ! has_shortcode( $post->post_content, 'gigbuilder_availability' )
            && ! has_shortcode( $post->post_content, 'gigbuilder_datepicker' ) ) {
            return;
        }

        wp_enqueue_style(
            'gigbuilder-common',
            GIGBUILDER_TOOLS_URL . 'assets/css/gigbuilder-common.css',
            array(),
            GIGBUILDER_TOOLS_VERSION
        );
        wp_enqueue_style(
            'gigbuilder-availability',
            GIGBUILDER_TOOLS_URL . 'widgets/availability/availability.css',
            array( 'gigbuilder-common' ),
            GIGBUILDER_TOOLS_VERSION
        );
        wp_enqueue_script(
            'gigbuilder-common',
            GIGBUILDER_TOOLS_URL . 'assets/js/gigbuilder-common.js',
            array(),
            GIGBUILDER_TOOLS_VERSION,
            true
        );
        wp_enqueue_script(
            'gigbuilder-availability',
            GIGBUILDER_TOOLS_URL . 'widgets/availability/availability.js',
            array( 'gigbuilder-common' ),
            GIGBUILDER_TOOLS_VERSION,
            true
        );
        wp_localize_script( 'gigbuilder-availability', 'gigbuilderAvailability', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'gigbuilder_availability' ),
        ) );
    }

    public static function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'title'       => 'Check Availability',
            'button_text' => 'Check Date',
        ), $atts, 'gigbuilder_availability' );

        ob_start();
        ?>
        <div class="gigbuilder-widget gigbuilder-availability" id="gigbuilder-availability">
            <div class="gigbuilder-date-picker" style="display:none;">
                <!-- Calendar input -->
                <div class="gigbuilder-date-mode">
                    <label>
                        <input type="radio" name="gb-date-mode" value="calendar" checked />
                        Calendar
                    </label>
                    <label>
                        <input type="radio" name="gb-date-mode" value="dropdowns" />
                        Select Date
                    </label>
                </div>

                <div class="gigbuilder-date-calendar">
                    <input type="date" id="gb-date-calendar" class="gigbuilder-input" />
                </div>

                <div class="gigbuilder-date-dropdowns" style="display:none;">
                    <select id="gb-date-month" class="gigbuilder-input">
                        <option value="">Month</option>
                    </select>
                    <select id="gb-date-day" class="gigbuilder-input">
                        <option value="">Day</option>
                    </select>
                    <select id="gb-date-year" class="gigbuilder-input">
                        <option value="">Year</option>
                    </select>
                </div>

                <button type="button" class="gigbuilder-button gigbuilder-check-btn">
                    <?php echo esc_html( $atts['button_text'] ); ?>
                </button>
            </div>

            <div class="gigbuilder-message" style="display:none;"></div>
            <div class="gigbuilder-selected-date" style="display:none;"></div>
            <div class="gigbuilder-validation-errors" style="display:none;"></div>
            <div class="gigbuilder-form-container"></div>
            <div class="gigbuilder-loading" style="display:none;">Checking...</div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_datepicker_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'button_text' => 'Check Date',
        ), $atts, 'gigbuilder_datepicker' );

        ob_start();
        ?>
        <div class="gigbuilder-widget gigbuilder-availability" id="gigbuilder-availability">
            <div class="gigbuilder-date-picker" style="display:none;">
                <div class="gigbuilder-date-dropdowns" style="display:flex;">
                    <select id="gb-date-month" class="gigbuilder-input">
                        <option value="">Month</option>
                    </select>
                    <select id="gb-date-day" class="gigbuilder-input">
                        <option value="">Day</option>
                    </select>
                    <select id="gb-date-year" class="gigbuilder-input">
                        <option value="">Year</option>
                    </select>
                </div>

                <button type="button" class="gigbuilder-button gigbuilder-check-btn">
                    <?php echo esc_html( $atts['button_text'] ); ?>
                </button>
            </div>

            <div class="gigbuilder-message" style="display:none;"></div>
            <div class="gigbuilder-selected-date" style="display:none;"></div>
            <div class="gigbuilder-validation-errors" style="display:none;"></div>
            <div class="gigbuilder-form-container"></div>
            <div class="gigbuilder-loading" style="display:none;">Checking...</div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_clientcenter_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'text' => 'Client Center',
        ), $atts, 'gigbuilder_clientcenter' );

        $url = Gigbuilder_Settings::get_app_url( 'client.html' );
        if ( empty( $url ) ) {
            return '<p class="gigbuilder-not-configured">Gigbuilder Tools is not configured.</p>';
        }

        return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="gigbuilder-app-button">'
            . esc_html( $atts['text'] ) . '</a>';
    }

    public static function render_guestrequests_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'text' => 'Guest Requests',
        ), $atts, 'gigbuilder_guestrequests' );

        $url = Gigbuilder_Settings::get_app_url( 'guestmusic.html' );
        if ( empty( $url ) ) {
            return '<p class="gigbuilder-not-configured">Gigbuilder Tools is not configured.</p>';
        }

        return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="gigbuilder-app-button">'
            . esc_html( $atts['text'] ) . '</a>';
    }

    public static function ajax_check_date() {
        check_ajax_referer( 'gigbuilder_availability', 'nonce' );

        $date = sanitize_text_field( $_POST['date'] ?? '' );
        if ( empty( $date ) ) {
            wp_send_json_error( array( 'message' => 'Please select a date.' ) );
        }

        // Validate MM/DD/YYYY format
        if ( ! preg_match( '/^\d{1,2}\/\d{1,2}\/\d{4}$/', $date ) ) {
            wp_send_json_error( array( 'message' => 'Invalid date format.' ) );
        }

        $result = Gigbuilder_API_Client::check_availability( $date );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // If available, render the form server-side and include the HTML
        if ( $result['status'] === 'available' && ! empty( $result['form'] ) ) {
            $result['formHtml'] = Gigbuilder_Form_Renderer::render( $result['form'], $date );
        }

        wp_send_json_success( $result );
    }

    public static function ajax_submit_booking() {
        check_ajax_referer( 'gigbuilder_availability', 'nonce' );

        $date    = sanitize_text_field( $_POST['date'] ?? '' );
        $answers = json_decode( wp_unslash( $_POST['answers'] ?? '[]' ), true );

        if ( empty( $date ) || empty( $answers ) ) {
            wp_send_json_error( array( 'message' => 'Missing required fields.' ) );
        }

        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';

        $result = Gigbuilder_API_Client::submit_booking( $date, $ip, $answers );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }
}
