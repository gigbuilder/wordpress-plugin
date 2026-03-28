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

    /**
     * Render the date input (calendar or dropdowns).
     */
    private static function render_date_input( $date_input ) {
        ob_start();
        if ( $date_input === 'dropdowns' ) {
            ?>
            <div class="gigbuilder-date-dropdowns">
                <select id="gb-date-month" class="gigbuilder-input">
                    <option value=""><?php esc_html_e( 'Month', 'gigbuilder-tools' ); ?></option>
                </select>
                <select id="gb-date-day" class="gigbuilder-input">
                    <option value=""><?php esc_html_e( 'Day', 'gigbuilder-tools' ); ?></option>
                </select>
                <select id="gb-date-year" class="gigbuilder-input">
                    <option value=""><?php esc_html_e( 'Year', 'gigbuilder-tools' ); ?></option>
                </select>
            </div>
            <?php
        } else {
            ?>
            <div class="gigbuilder-date-calendar">
                <input type="date" id="gb-date-calendar" class="gigbuilder-input" />
            </div>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Render common elements (message, selected date, validation, form container, loading).
     */
    private static function render_common_elements() {
        ob_start();
        ?>
        <div class="gigbuilder-message" style="display:none;"></div>
        <div class="gigbuilder-selected-date" style="display:none;"></div>
        <div class="gigbuilder-validation-errors" style="display:none;"></div>
        <div class="gigbuilder-form-container"></div>
        <div class="gigbuilder-loading" style="display:none;"><?php esc_html_e( 'Checking...', 'gigbuilder-tools' ); ?></div>
        <?php
        return ob_get_clean();
    }

    /**
     * Resolve shortcode attributes with settings fallbacks.
     */
    private static function resolve_atts( $atts, $shortcode_tag, $date_input_override = '' ) {
        $style      = Gigbuilder_Settings::get_appearance( 'widget_style' );
        $date_input = ! empty( $date_input_override ) ? $date_input_override : Gigbuilder_Settings::get_appearance( 'date_input' );

        $heading_default = Gigbuilder_Settings::get_appearance( 'heading_text' );
        if ( empty( $heading_default ) ) {
            $heading_default = Gigbuilder_Settings::get_heading_default( $style );
        }

        $subheading_default = Gigbuilder_Settings::get_appearance( 'subheading_text' );
        $button_default     = Gigbuilder_Settings::get_appearance( 'button_text' );
        $submit_default     = Gigbuilder_Settings::get_appearance( 'submit_text' );

        return shortcode_atts( array(
            'style'       => $style,
            'date_input'  => $date_input,
            'heading'     => $heading_default,
            'subheading'  => $subheading_default,
            'button_text' => ! empty( $button_default ) ? $button_default : __( 'Check Date', 'gigbuilder-tools' ),
            'submit_text' => ! empty( $submit_default ) ? $submit_default : __( 'Submit Request', 'gigbuilder-tools' ),
        ), $atts, $shortcode_tag );
    }

    public static function render_shortcode( $atts ) {
        $atts = self::resolve_atts( $atts, 'gigbuilder_availability' );

        $style       = $atts['style'];
        $date_input  = $atts['date_input'];
        $heading     = $atts['heading'];
        $subheading  = $atts['subheading'];
        $button_text = $atts['button_text'];
        $submit_text = $atts['submit_text'];

        $date_html   = self::render_date_input( $date_input );
        $common_html = self::render_common_elements();

        ob_start();
        ?>
        <div class="gigbuilder-widget gigbuilder-availability gigbuilder-style-<?php echo esc_attr( $style ); ?>"
             id="gigbuilder-availability"
             data-submit-text="<?php echo esc_attr( $submit_text ); ?>">

        <?php if ( $style === 'card' ) : ?>
            <div class="gigbuilder-card">
                <div class="gigbuilder-card-label"><?php echo esc_html( $heading ); ?></div>
                <?php if ( ! empty( $subheading ) ) : ?>
                    <p class="gigbuilder-subheading"><?php echo esc_html( $subheading ); ?></p>
                <?php endif; ?>
                <div class="gigbuilder-date-picker" style="display:none;">
                    <div class="gigbuilder-field-label"><?php esc_html_e( 'Select Date', 'gigbuilder-tools' ); ?></div>
                    <div class="gigbuilder-date-row">
                        <?php echo $date_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <button type="button" class="gigbuilder-button gigbuilder-check-btn">
                            <?php echo esc_html( $button_text ); ?>
                        </button>
                    </div>
                </div>
                <?php echo $common_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>

        <?php elseif ( $style === 'stepped' ) : ?>
            <div class="gigbuilder-steps">
                <div class="gigbuilder-step gigbuilder-step--active" data-step="1">
                    <div class="gigbuilder-step-indicator">
                        <div class="gigbuilder-step-number">1</div>
                        <div class="gigbuilder-step-line"></div>
                    </div>
                    <div class="gigbuilder-step-content">
                        <div class="gigbuilder-step-title"><?php echo esc_html( $heading ); ?></div>
                        <?php if ( ! empty( $subheading ) ) : ?>
                            <p class="gigbuilder-subheading"><?php echo esc_html( $subheading ); ?></p>
                        <?php endif; ?>
                        <div class="gigbuilder-date-picker" style="display:none;">
                            <?php echo $date_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <button type="button" class="gigbuilder-button gigbuilder-check-btn">
                                <?php echo esc_html( $button_text ); ?>
                            </button>
                        </div>
                        <div class="gigbuilder-message" style="display:none;"></div>
                        <div class="gigbuilder-selected-date" style="display:none;"></div>
                    </div>
                </div>
                <div class="gigbuilder-step gigbuilder-step--upcoming" data-step="2">
                    <div class="gigbuilder-step-indicator">
                        <div class="gigbuilder-step-number">2</div>
                        <div class="gigbuilder-step-line"></div>
                    </div>
                    <div class="gigbuilder-step-content">
                        <div class="gigbuilder-step-title"><?php esc_html_e( 'Your details', 'gigbuilder-tools' ); ?></div>
                        <div class="gigbuilder-validation-errors" style="display:none;"></div>
                        <div class="gigbuilder-form-container"></div>
                    </div>
                </div>
                <div class="gigbuilder-step gigbuilder-step--upcoming" data-step="3">
                    <div class="gigbuilder-step-indicator">
                        <div class="gigbuilder-step-number">3</div>
                    </div>
                    <div class="gigbuilder-step-content">
                        <div class="gigbuilder-step-title"><?php esc_html_e( 'Confirmation', 'gigbuilder-tools' ); ?></div>
                    </div>
                </div>
                <div class="gigbuilder-loading" style="display:none;"><?php esc_html_e( 'Checking...', 'gigbuilder-tools' ); ?></div>
            </div>

        <?php else : // minimal ?>
            <h3 class="gigbuilder-heading"><?php echo esc_html( $heading ); ?></h3>
            <?php if ( ! empty( $subheading ) ) : ?>
                <p class="gigbuilder-subheading"><?php echo esc_html( $subheading ); ?></p>
            <?php endif; ?>
            <div class="gigbuilder-date-picker" style="display:none;">
                <div class="gigbuilder-search-row">
                    <?php echo $date_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <button type="button" class="gigbuilder-button gigbuilder-check-btn">
                        <?php echo esc_html( $button_text ); ?> <span class="gigbuilder-arrow">&rarr;</span>
                    </button>
                </div>
            </div>
            <?php echo $common_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Deprecated: use [gigbuilder_availability date_input="dropdowns"] instead.
     */
    public static function render_datepicker_shortcode( $atts ) {
        $atts = self::resolve_atts( $atts, 'gigbuilder_datepicker', 'dropdowns' );
        return self::render_shortcode( $atts );
    }

    public static function render_clientcenter_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'text' => __( 'Client Center', 'gigbuilder-tools' ),
        ), $atts, 'gigbuilder_clientcenter' );

        $url = Gigbuilder_Settings::get_app_url( 'client.html' );
        if ( empty( $url ) ) {
            return '<p class="gigbuilder-not-configured">' . esc_html__( 'Gigbuilder Tools is not configured.', 'gigbuilder-tools' ) . '</p>';
        }

        return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="gigbuilder-app-button">'
            . esc_html( $atts['text'] ) . '</a>';
    }

    public static function render_guestrequests_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'text' => __( 'Guest Requests', 'gigbuilder-tools' ),
        ), $atts, 'gigbuilder_guestrequests' );

        $url = Gigbuilder_Settings::get_app_url( 'guestmusic.html' );
        if ( empty( $url ) ) {
            return '<p class="gigbuilder-not-configured">' . esc_html__( 'Gigbuilder Tools is not configured.', 'gigbuilder-tools' ) . '</p>';
        }

        return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="gigbuilder-app-button">'
            . esc_html( $atts['text'] ) . '</a>';
    }

    public static function ajax_check_date() {
        check_ajax_referer( 'gigbuilder_availability', 'nonce' );

        $date = sanitize_text_field( $_POST['date'] ?? '' );
        if ( empty( $date ) ) {
            wp_send_json_error( array( 'message' => __( 'Please select a date.', 'gigbuilder-tools' ) ) );
        }

        // Validate MM/DD/YYYY format
        if ( ! preg_match( '/^\d{1,2}\/\d{1,2}\/\d{4}$/', $date ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid date format.', 'gigbuilder-tools' ) ) );
        }

        $result = Gigbuilder_API_Client::check_availability( $date );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // If available, render the form server-side and include the HTML
        if ( $result['status'] === 'available' && ! empty( $result['form'] ) ) {
            $submit_text = Gigbuilder_Settings::get_appearance( 'submit_text' );
            if ( empty( $submit_text ) ) {
                $submit_text = __( 'Submit Request', 'gigbuilder-tools' );
            }
            $result['formHtml'] = Gigbuilder_Form_Renderer::render( $result['form'], $date, $submit_text );
        }

        wp_send_json_success( $result );
    }

    public static function ajax_submit_booking() {
        check_ajax_referer( 'gigbuilder_availability', 'nonce' );

        $date    = sanitize_text_field( $_POST['date'] ?? '' );
        $answers = json_decode( wp_unslash( $_POST['answers'] ?? '[]' ), true );

        if ( empty( $date ) || empty( $answers ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'gigbuilder-tools' ) ) );
        }

        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';

        $result = Gigbuilder_API_Client::submit_booking( $date, $ip, $answers );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }
}
