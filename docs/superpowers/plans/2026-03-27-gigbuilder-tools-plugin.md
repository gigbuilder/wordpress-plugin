# Gigbuilder Tools WordPress Plugin — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a modular WordPress plugin with a Check Availability widget that queries a Domino CRM via server-side proxy and renders CRM-defined booking forms natively.

**Architecture:** WordPress plugin with shortcodes, an admin settings page (server URL + DB path), a shared API client that proxies requests to a Domino `jsontools` agent, and a JSON-schema form renderer. The Domino side is a single LotusScript web agent that routes actions and returns JSON. Browser never talks to Domino directly.

**Tech Stack:** PHP 7.4+ (WordPress plugin), JavaScript (vanilla, no framework), LotusScript (Domino 14 web agent with JSON classes)

---

## File Structure

```
gigbuilder-tools/
  gigbuilder-tools.php           — Main plugin bootstrap, autoloads includes, registers hooks
  includes/
    class-settings.php           — Admin settings page: server URL + database path fields
    class-api-client.php         — Server-side HTTP proxy to Domino jsontools agent
    class-form-renderer.php      — Renders HTML forms from CRM JSON field schema
  widgets/
    availability/
      class-availability.php     — Registers shortcode + AJAX handlers for check availability
      availability.js            — Date picker UI, AJAX calls, response rendering
      availability.css           — Default widget styles (theme-overridable)
  assets/
    css/gigbuilder-common.css    — Shared base styles for all gigbuilder widgets
    js/gigbuilder-common.js      — Shared JS: form renderer, status message display
  domino/
    jsontools.lss                — LotusScript source for the jsontools web agent
```

---

### Task 1: Plugin Bootstrap

**Files:**
- Create: `gigbuilder-tools/gigbuilder-tools.php`

- [ ] **Step 1: Create the main plugin file with WordPress header and autoloading**

```php
<?php
/**
 * Plugin Name: Gigbuilder Tools
 * Plugin URI: https://gigbuilder.com
 * Description: Widgets and tools for connecting WordPress sites to Gigbuilder CRM.
 * Version: 1.0.0
 * Author: Gigbuilder
 * License: GPL v2 or later
 * Text Domain: gigbuilder-tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GIGBUILDER_TOOLS_VERSION', '1.0.0' );
define( 'GIGBUILDER_TOOLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'GIGBUILDER_TOOLS_URL', plugin_dir_url( __FILE__ ) );

// Core includes
require_once GIGBUILDER_TOOLS_PATH . 'includes/class-settings.php';
require_once GIGBUILDER_TOOLS_PATH . 'includes/class-api-client.php';
require_once GIGBUILDER_TOOLS_PATH . 'includes/class-form-renderer.php';

// Widgets
require_once GIGBUILDER_TOOLS_PATH . 'widgets/availability/class-availability.php';

// Initialize
add_action( 'plugins_loaded', function() {
    Gigbuilder_Settings::init();
    Gigbuilder_Availability::init();
});
```

- [ ] **Step 2: Create directory structure**

Run:
```bash
mkdir -p gigbuilder-tools/includes
mkdir -p gigbuilder-tools/widgets/availability
mkdir -p gigbuilder-tools/assets/css
mkdir -p gigbuilder-tools/assets/js
mkdir -p gigbuilder-tools/domino
```

- [ ] **Step 3: Commit**

```bash
git add gigbuilder-tools/
git commit -m "feat: scaffold gigbuilder-tools plugin with directory structure"
```

---

### Task 2: Settings Page

**Files:**
- Create: `gigbuilder-tools/includes/class-settings.php`

- [ ] **Step 1: Create the settings class with server URL and database path fields**

```php
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
```

- [ ] **Step 2: Verify the settings page loads in WordPress admin**

Navigate to Settings → Gigbuilder Tools in the WordPress admin. Confirm both fields render and save correctly.

- [ ] **Step 3: Commit**

```bash
git add gigbuilder-tools/includes/class-settings.php
git commit -m "feat: add settings page with server URL and database path"
```

---

### Task 3: API Client

**Files:**
- Create: `gigbuilder-tools/includes/class-api-client.php`

- [ ] **Step 1: Create the API client class that proxies requests to Domino**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gigbuilder_API_Client {

    /**
     * Send a request to the Domino jsontools agent.
     *
     * @param array $payload The JSON payload (must include 'action' key).
     * @return array|WP_Error Decoded JSON response or WP_Error on failure.
     */
    public static function request( $payload ) {
        $url = Gigbuilder_Settings::get_endpoint_url();

        if ( empty( $url ) ) {
            return new WP_Error(
                'gigbuilder_not_configured',
                'Gigbuilder Tools is not configured. Please set the Server URL and Database Path in Settings.'
            );
        }

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'gigbuilder_connection_error',
                'Could not connect to Gigbuilder CRM: ' . $response->get_error_message()
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error(
                'gigbuilder_http_error',
                'CRM returned HTTP ' . $code
            );
        }

        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error(
                'gigbuilder_json_error',
                'Invalid JSON response from CRM'
            );
        }

        return $data;
    }

    /**
     * Check availability for a given date.
     *
     * @param string $date Date in MM/DD/YYYY format.
     * @return array|WP_Error
     */
    public static function check_availability( $date ) {
        return self::request( array(
            'action' => 'checkAvailability',
            'date'   => $date,
        ) );
    }

    /**
     * Submit a booking form.
     *
     * @param string $date    Date in MM/DD/YYYY format.
     * @param string $ip      Visitor IP address.
     * @param array  $answers Array of {name, value} answer objects.
     * @return array|WP_Error
     */
    public static function submit_booking( $date, $ip, $answers ) {
        return self::request( array(
            'action'  => 'submitBooking',
            'date'    => $date,
            'ip'      => $ip,
            'answers' => $answers,
        ) );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add gigbuilder-tools/includes/class-api-client.php
git commit -m "feat: add API client for Domino jsontools proxy"
```

---

### Task 4: Form Renderer

**Files:**
- Create: `gigbuilder-tools/includes/class-form-renderer.php`

- [ ] **Step 1: Create the form renderer that builds HTML from JSON field schema**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gigbuilder_Form_Renderer {

    /**
     * Render a complete form from a CRM form definition.
     *
     * @param array  $form_data The form object from the API (title, fields).
     * @param string $date      The selected date to include as hidden field.
     * @return string HTML form markup.
     */
    public static function render( $form_data, $date ) {
        $html = '<form class="gigbuilder-form" data-date="' . esc_attr( $date ) . '">';

        if ( ! empty( $form_data['title'] ) ) {
            $html .= '<h3 class="gigbuilder-form-title">' . esc_html( $form_data['title'] ) . '</h3>';
        }

        if ( ! empty( $form_data['fields'] ) ) {
            foreach ( $form_data['fields'] as $field ) {
                $html .= self::render_field( $field );
            }
        }

        $html .= '<div class="gigbuilder-field gigbuilder-field--submit">';
        $html .= '<button type="submit" class="gigbuilder-submit">Submit</button>';
        $html .= '</div>';
        $html .= '</form>';

        return $html;
    }

    /**
     * Render a single form field from its JSON definition.
     *
     * @param array $field Field definition with type, subType, name, label, etc.
     * @return string HTML markup for the field.
     */
    public static function render_field( $field ) {
        $type        = $field['type'] ?? 'input';
        $sub_type    = $field['subType'] ?? 'text';
        $name        = $field['name'] ?? '';
        $label       = $field['label'] ?? '';
        $placeholder = $field['placeholder'] ?? '';
        $required    = ! empty( $field['required'] );
        $values      = $field['values'] ?? array();
        $field_id    = 'gb-' . sanitize_html_class( $name );

        $css_class = 'gigbuilder-field gigbuilder-field--' . sanitize_html_class( $type );
        if ( $type === 'input' ) {
            $css_class .= ' gigbuilder-field--' . sanitize_html_class( $sub_type );
        }

        $html = '<div class="' . $css_class . '">';

        // Label
        if ( $label ) {
            $html .= '<label for="' . esc_attr( $field_id ) . '">';
            $html .= esc_html( $label );
            if ( $required ) {
                $html .= ' <span class="gigbuilder-required">*</span>';
            }
            $html .= '</label>';
        }

        // Field markup
        switch ( $type ) {
            case 'input':
                $input_type = 'text';
                if ( $sub_type === 'email' ) $input_type = 'email';
                if ( $sub_type === 'phone' ) $input_type = 'tel';
                if ( $sub_type === 'number' ) $input_type = 'number';

                $html .= '<input type="' . esc_attr( $input_type ) . '"';
                $html .= ' id="' . esc_attr( $field_id ) . '"';
                $html .= ' name="' . esc_attr( $name ) . '"';
                if ( $placeholder ) {
                    $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
                }
                if ( $required ) {
                    $html .= ' required';
                }
                $html .= ' />';
                break;

            case 'textarea':
                $html .= '<textarea';
                $html .= ' id="' . esc_attr( $field_id ) . '"';
                $html .= ' name="' . esc_attr( $name ) . '"';
                if ( $placeholder ) {
                    $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
                }
                if ( $required ) {
                    $html .= ' required';
                }
                $html .= '></textarea>';
                break;

            case 'select':
                $html .= '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '"';
                if ( $required ) {
                    $html .= ' required';
                }
                $html .= '>';
                $html .= '<option value="">— Select —</option>';
                foreach ( $values as $val ) {
                    $html .= '<option value="' . esc_attr( $val ) . '">' . esc_html( $val ) . '</option>';
                }
                $html .= '</select>';
                break;

            case 'radio':
                foreach ( $values as $i => $val ) {
                    $radio_id = $field_id . '-' . $i;
                    $html .= '<div class="gigbuilder-radio-option">';
                    $html .= '<input type="radio" id="' . esc_attr( $radio_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '"';
                    if ( $required && $i === 0 ) {
                        $html .= ' required';
                    }
                    $html .= ' />';
                    $html .= '<label for="' . esc_attr( $radio_id ) . '">' . esc_html( $val ) . '</label>';
                    $html .= '</div>';
                }
                break;

            case 'checkbox':
                foreach ( $values as $i => $val ) {
                    $cb_id = $field_id . '-' . $i;
                    $html .= '<div class="gigbuilder-checkbox-option">';
                    $html .= '<input type="checkbox" id="' . esc_attr( $cb_id ) . '" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $val ) . '" />';
                    $html .= '<label for="' . esc_attr( $cb_id ) . '">' . esc_html( $val ) . '</label>';
                    $html .= '</div>';
                }
                break;
        }

        $html .= '</div>';
        return $html;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add gigbuilder-tools/includes/class-form-renderer.php
git commit -m "feat: add form renderer for CRM JSON field schema"
```

---

### Task 5: Availability Widget — PHP (Shortcode + AJAX Handlers)

**Files:**
- Create: `gigbuilder-tools/widgets/availability/class-availability.php`

- [ ] **Step 1: Create the availability class with shortcode and AJAX handlers**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gigbuilder_Availability {

    public static function init() {
        add_shortcode( 'gigbuilder_availability', array( __CLASS__, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
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
        if ( ! has_shortcode( $post->post_content, 'gigbuilder_availability' ) ) {
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
            <h3 class="gigbuilder-widget-title"><?php echo esc_html( $atts['title'] ); ?></h3>

            <div class="gigbuilder-date-picker">
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
            <div class="gigbuilder-form-container"></div>
            <div class="gigbuilder-loading" style="display:none;">Checking...</div>
        </div>
        <?php
        return ob_get_clean();
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
        $answers = json_decode( stripslashes( $_POST['answers'] ?? '[]' ), true );

        if ( empty( $date ) || empty( $answers ) ) {
            wp_send_json_error( array( 'message' => 'Missing required fields.' ) );
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $result = Gigbuilder_API_Client::submit_booking( $date, $ip, $answers );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add gigbuilder-tools/widgets/availability/class-availability.php
git commit -m "feat: add availability shortcode with AJAX check and booking handlers"
```

---

### Task 6: Availability Widget — JavaScript

**Files:**
- Create: `gigbuilder-tools/assets/js/gigbuilder-common.js`
- Create: `gigbuilder-tools/widgets/availability/availability.js`

- [ ] **Step 1: Create the shared JS utilities**

```js
/**
 * Gigbuilder Tools — shared utilities
 */
var GigbuilderTools = (function() {
    'use strict';

    /**
     * Show a status message in a container element.
     */
    function showMessage( container, status, message ) {
        container.style.display = 'block';
        container.className = 'gigbuilder-message gigbuilder-message--' + status;
        container.textContent = message;
    }

    /**
     * Hide a message container.
     */
    function hideMessage( container ) {
        container.style.display = 'none';
        container.textContent = '';
    }

    /**
     * Collect form answers from a gigbuilder-form into the API format.
     * Returns array of {name, value} objects.
     */
    function collectFormAnswers( formEl ) {
        var answers = [];
        var inputs = formEl.querySelectorAll( 'input, select, textarea' );

        for ( var i = 0; i < inputs.length; i++ ) {
            var el = inputs[i];
            var name = el.name;
            if ( ! name || el.type === 'submit' ) continue;

            // Radio: only include checked
            if ( el.type === 'radio' && ! el.checked ) continue;

            // Checkbox: collect all checked values into one answer
            if ( el.type === 'checkbox' ) {
                if ( el.checked ) {
                    // Check if we already started this name
                    var existing = null;
                    for ( var j = 0; j < answers.length; j++ ) {
                        if ( answers[j].name === name.replace( '[]', '' ) ) {
                            existing = answers[j];
                            break;
                        }
                    }
                    if ( existing ) {
                        existing.value += ', ' + el.value;
                    } else {
                        answers.push({ name: name.replace( '[]', '' ), value: el.value });
                    }
                }
                continue;
            }

            answers.push({ name: name, value: el.value });
        }

        return answers;
    }

    return {
        showMessage: showMessage,
        hideMessage: hideMessage,
        collectFormAnswers: collectFormAnswers
    };
})();
```

- [ ] **Step 2: Create the availability widget JS**

```js
/**
 * Gigbuilder Availability Widget
 */
(function() {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function() {
        var widget = document.getElementById( 'gigbuilder-availability' );
        if ( ! widget ) return;

        var config       = window.gigbuilderAvailability || {};
        var checkBtn     = widget.querySelector( '.gigbuilder-check-btn' );
        var messageEl    = widget.querySelector( '.gigbuilder-message' );
        var formContainer = widget.querySelector( '.gigbuilder-form-container' );
        var loadingEl    = widget.querySelector( '.gigbuilder-loading' );
        var calendarWrap = widget.querySelector( '.gigbuilder-date-calendar' );
        var dropdownWrap = widget.querySelector( '.gigbuilder-date-dropdowns' );
        var modeRadios   = widget.querySelectorAll( 'input[name="gb-date-mode"]' );

        // Populate dropdowns
        var monthSelect = document.getElementById( 'gb-date-month' );
        var daySelect   = document.getElementById( 'gb-date-day' );
        var yearSelect  = document.getElementById( 'gb-date-year' );

        var months = ['January','February','March','April','May','June',
                      'July','August','September','October','November','December'];
        for ( var m = 0; m < months.length; m++ ) {
            var opt = document.createElement( 'option' );
            opt.value = String( m + 1 );
            opt.textContent = months[m];
            monthSelect.appendChild( opt );
        }

        for ( var d = 1; d <= 31; d++ ) {
            var opt = document.createElement( 'option' );
            opt.value = String( d );
            opt.textContent = String( d );
            daySelect.appendChild( opt );
        }

        var currentYear = new Date().getFullYear();
        for ( var y = currentYear; y <= currentYear + 3; y++ ) {
            var opt = document.createElement( 'option' );
            opt.value = String( y );
            opt.textContent = String( y );
            yearSelect.appendChild( opt );
        }

        // Toggle date mode
        for ( var i = 0; i < modeRadios.length; i++ ) {
            modeRadios[i].addEventListener( 'change', function() {
                if ( this.value === 'calendar' ) {
                    calendarWrap.style.display = '';
                    dropdownWrap.style.display = 'none';
                } else {
                    calendarWrap.style.display = 'none';
                    dropdownWrap.style.display = '';
                }
            });
        }

        /**
         * Get the selected date in MM/DD/YYYY format.
         */
        function getSelectedDate() {
            var mode = widget.querySelector( 'input[name="gb-date-mode"]:checked' ).value;

            if ( mode === 'calendar' ) {
                var val = document.getElementById( 'gb-date-calendar' ).value;
                if ( ! val ) return '';
                // Convert YYYY-MM-DD to MM/DD/YYYY
                var parts = val.split( '-' );
                return parts[1] + '/' + parts[2] + '/' + parts[0];
            } else {
                var mm = monthSelect.value;
                var dd = daySelect.value;
                var yy = yearSelect.value;
                if ( ! mm || ! dd || ! yy ) return '';
                return mm + '/' + dd + '/' + yy;
            }
        }

        // Check date button
        checkBtn.addEventListener( 'click', function() {
            var date = getSelectedDate();
            if ( ! date ) {
                GigbuilderTools.showMessage( messageEl, 'error', 'Please select a date.' );
                formContainer.innerHTML = '';
                return;
            }

            GigbuilderTools.hideMessage( messageEl );
            formContainer.innerHTML = '';
            loadingEl.style.display = 'block';

            var formData = new FormData();
            formData.append( 'action', 'gigbuilder_check_date' );
            formData.append( 'nonce', config.nonce );
            formData.append( 'date', date );

            fetch( config.ajaxUrl, { method: 'POST', body: formData } )
                .then( function( res ) { return res.json(); } )
                .then( function( response ) {
                    loadingEl.style.display = 'none';

                    if ( ! response.success ) {
                        GigbuilderTools.showMessage( messageEl, 'error', response.data.message || 'An error occurred.' );
                        return;
                    }

                    var data = response.data;
                    GigbuilderTools.showMessage( messageEl, data.status, data.message );

                    if ( data.status === 'available' && data.formHtml ) {
                        formContainer.innerHTML = data.formHtml;
                        attachFormHandler( formContainer.querySelector( '.gigbuilder-form' ), date );
                    }
                })
                .catch( function() {
                    loadingEl.style.display = 'none';
                    GigbuilderTools.showMessage( messageEl, 'error', 'Connection error. Please try again.' );
                });
        });

        /**
         * Attach submit handler to the CRM-rendered booking form.
         */
        function attachFormHandler( formEl, date ) {
            if ( ! formEl ) return;

            formEl.addEventListener( 'submit', function( e ) {
                e.preventDefault();

                var answers = GigbuilderTools.collectFormAnswers( formEl );
                loadingEl.style.display = 'block';

                var formData = new FormData();
                formData.append( 'action', 'gigbuilder_submit_booking' );
                formData.append( 'nonce', config.nonce );
                formData.append( 'date', date );
                formData.append( 'answers', JSON.stringify( answers ) );

                fetch( config.ajaxUrl, { method: 'POST', body: formData } )
                    .then( function( res ) { return res.json(); } )
                    .then( function( response ) {
                        loadingEl.style.display = 'none';

                        if ( ! response.success ) {
                            GigbuilderTools.showMessage( messageEl, 'error', response.data.message || 'An error occurred.' );
                            return;
                        }

                        var data = response.data;
                        GigbuilderTools.showMessage( messageEl, data.status, data.message );
                        formContainer.innerHTML = '';
                    })
                    .catch( function() {
                        loadingEl.style.display = 'none';
                        GigbuilderTools.showMessage( messageEl, 'error', 'Connection error. Please try again.' );
                    });
            });
        }
    });
})();
```

- [ ] **Step 3: Commit**

```bash
git add gigbuilder-tools/assets/js/gigbuilder-common.js gigbuilder-tools/widgets/availability/availability.js
git commit -m "feat: add availability widget JavaScript with date picker and form handling"
```

---

### Task 7: CSS Styles

**Files:**
- Create: `gigbuilder-tools/assets/css/gigbuilder-common.css`
- Create: `gigbuilder-tools/widgets/availability/availability.css`

- [ ] **Step 1: Create shared base styles**

```css
/* Gigbuilder Tools — shared base styles */

.gigbuilder-widget {
    max-width: 600px;
    margin: 1.5em 0;
}

.gigbuilder-widget-title {
    margin: 0 0 1em;
}

/* Messages */
.gigbuilder-message {
    padding: 0.75em 1em;
    border-radius: 4px;
    margin-bottom: 1em;
    font-weight: 500;
}

.gigbuilder-message--available {
    background: #eafbe7;
    color: #1a7d12;
    border: 1px solid #a3d99b;
}

.gigbuilder-message--booked {
    background: #fef3e2;
    color: #8a5a00;
    border: 1px solid #f0c674;
}

.gigbuilder-message--success {
    background: #eafbe7;
    color: #1a7d12;
    border: 1px solid #a3d99b;
}

.gigbuilder-message--error {
    background: #fce8e8;
    color: #c0392b;
    border: 1px solid #f1a9a0;
}

/* Forms */
.gigbuilder-form {
    margin-top: 1em;
}

.gigbuilder-form-title {
    margin: 0 0 0.75em;
}

.gigbuilder-field {
    margin-bottom: 1em;
}

.gigbuilder-field label {
    display: block;
    margin-bottom: 0.3em;
    font-weight: 500;
}

.gigbuilder-field input[type="text"],
.gigbuilder-field input[type="email"],
.gigbuilder-field input[type="tel"],
.gigbuilder-field input[type="number"],
.gigbuilder-field textarea,
.gigbuilder-field select {
    width: 100%;
    padding: 0.5em;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 1em;
    box-sizing: border-box;
}

.gigbuilder-field textarea {
    min-height: 80px;
    resize: vertical;
}

.gigbuilder-required {
    color: #c0392b;
}

.gigbuilder-radio-option,
.gigbuilder-checkbox-option {
    display: flex;
    align-items: center;
    gap: 0.4em;
    margin-bottom: 0.3em;
}

.gigbuilder-radio-option label,
.gigbuilder-checkbox-option label {
    display: inline;
    font-weight: normal;
    margin-bottom: 0;
}

/* Buttons */
.gigbuilder-button,
.gigbuilder-submit {
    display: inline-block;
    padding: 0.6em 1.5em;
    border: none;
    border-radius: 4px;
    font-size: 1em;
    cursor: pointer;
    background: #2271b1;
    color: #fff;
}

.gigbuilder-button:hover,
.gigbuilder-submit:hover {
    background: #135e96;
}

/* Loading */
.gigbuilder-loading {
    padding: 0.5em 0;
    color: #666;
    font-style: italic;
}
```

- [ ] **Step 2: Create availability-specific styles**

```css
/* Gigbuilder Availability Widget */

.gigbuilder-availability .gigbuilder-date-picker {
    margin-bottom: 1em;
}

.gigbuilder-date-mode {
    display: flex;
    gap: 1.5em;
    margin-bottom: 0.75em;
}

.gigbuilder-date-mode label {
    display: flex;
    align-items: center;
    gap: 0.3em;
    cursor: pointer;
    font-weight: normal;
}

.gigbuilder-date-calendar input[type="date"] {
    padding: 0.5em;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 1em;
    margin-bottom: 0.75em;
}

.gigbuilder-date-dropdowns {
    display: flex;
    gap: 0.5em;
    margin-bottom: 0.75em;
}

.gigbuilder-date-dropdowns select {
    padding: 0.5em;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 1em;
}
```

- [ ] **Step 3: Commit**

```bash
git add gigbuilder-tools/assets/css/gigbuilder-common.css gigbuilder-tools/widgets/availability/availability.css
git commit -m "feat: add shared and availability widget CSS styles"
```

---

### Task 8: Domino jsontools Agent

**Files:**
- Create: `gigbuilder-tools/domino/jsontools.lss`

- [ ] **Step 1: Create the LotusScript web agent source**

```lotusscript
Option Public
Option Declare

Sub Initialize
    On Error GoTo ErrorHandler

    Dim session As New NotesSession
    Dim db As NotesDatabase
    Dim webDoc As NotesDocument

    Set db = session.CurrentDatabase
    Set webDoc = session.DocumentContext

    ' Read POST body (handle chunked content)
    Dim body As String
    body = GetPostBody(webDoc)

    If body = "" Then
        Call SendResponse("error", "No request body received.", Nothing)
        Exit Sub
    End If

    ' Parse JSON
    Dim nav As NotesJSONNavigator
    Set nav = session.CreateJSONNavigator(body)

    ' Route by action
    Dim action As String
    Dim actionElem As NotesJSONElement
    Set actionElem = nav.GetElementByName("action", True)
    If actionElem Is Nothing Then
        Call SendResponse("error", "Missing action field.", Nothing)
        Exit Sub
    End If
    action = CStr(actionElem.Value)

    Select Case action
        Case "checkAvailability"
            Call HandleCheckAvailability(session, db, nav)
        Case "submitBooking"
            Call HandleSubmitBooking(session, db, nav)
        Case Else
            Call SendResponse("error", "Unknown action: " & action, Nothing)
    End Select

    Exit Sub

ErrorHandler:
    Call SendResponse("error", "Server error at line " & CStr(Erl) & ": " & Error$, Nothing)
    Exit Sub
End Sub

'----------------------------------------------------------
' Read POST body, handling chunked request_content fields
'----------------------------------------------------------
Function GetPostBody(webDoc As NotesDocument) As String
    Dim body As String
    body = CStr(webDoc.GetItemValue("Request_Content")(0))

    ' Check for chunked content
    Dim i As Integer
    Dim chunkField As String
    Dim chunkVal As String
    i = 0
    Do
        chunkField = "request_content_" & Format$(i, "000")
        If webDoc.HasItem(chunkField) Then
            chunkVal = CStr(webDoc.GetItemValue(chunkField)(0))
            If i = 0 Then
                body = chunkVal
            Else
                body = body & chunkVal
            End If
            i = i + 1
        Else
            Exit Do
        End If
    Loop

    GetPostBody = body
End Function

'----------------------------------------------------------
' Send a JSON response with status and message
' Optionally include additional JSON from a navigator
'----------------------------------------------------------
Sub SendResponse(status As String, message As String, extraNav As NotesJSONNavigator)
    Dim session As New NotesSession
    Dim nav As NotesJSONNavigator
    Set nav = session.CreateJSONNavigator("")

    Call nav.AppendElement(status, "status")
    Call nav.AppendElement(message, "message")

    ' If we have extra data (like form definition), we need to build it manually
    ' The extraNav is used by handlers that build their own full response

    Print "Content-Type: application/json"
    Print ""

    If extraNav Is Nothing Then
        Print nav.Stringify()
    Else
        Print extraNav.Stringify()
    End If
End Sub

'----------------------------------------------------------
' Check Availability Handler
'----------------------------------------------------------
Sub HandleCheckAvailability(session As NotesSession, db As NotesDatabase, requestNav As NotesJSONNavigator)
    On Error GoTo ErrHandler

    ' Get date from request
    Dim dateElem As NotesJSONElement
    Set dateElem = requestNav.GetElementByName("date", True)
    If dateElem Is Nothing Then
        Call SendResponse("error", "Missing date field.", Nothing)
        Exit Sub
    End If
    Dim dateStr As String
    dateStr = CStr(dateElem.Value)

    ' Look up the date in the calendar
    ' Use a view keyed by date to check for existing bookings
    Dim view As NotesView
    Set view = db.GetView("CalendarByDate")

    If view Is Nothing Then
        Call SendResponse("error", "Calendar view not found.", Nothing)
        Exit Sub
    End If

    Dim entry As NotesDocument
    Set entry = view.GetDocumentByKey(dateStr, True)

    ' Build response
    Dim responseNav As NotesJSONNavigator
    Set responseNav = session.CreateJSONNavigator("")

    If Not (entry Is Nothing) Then
        ' Date is booked
        Call responseNav.AppendElement("booked", "status")

        ' Get custom message from CRM config, or use default
        Dim bookedMsg As String
        bookedMsg = GetConfigValue(db, "BookedMessage")
        If bookedMsg = "" Then bookedMsg = "Sorry, that date is already booked."
        Call responseNav.AppendElement(bookedMsg, "message")
    Else
        ' Date is available
        Call responseNav.AppendElement("available", "status")

        ' Get custom message from CRM config, or use default
        Dim availMsg As String
        availMsg = GetConfigValue(db, "AvailableMessage")
        If availMsg = "" Then availMsg = "Great news! This date is available."
        Call responseNav.AppendElement(availMsg, "message")

        ' Build form definition from CRM config
        Dim formObj As NotesJSONObject
        Set formObj = responseNav.AppendObject("form")

        Dim formTitle As String
        formTitle = GetConfigValue(db, "BookingFormTitle")
        If formTitle = "" Then formTitle = "Book This Date"
        Call formObj.AppendElement(formTitle, "title")

        ' Get form fields from CRM configuration
        Call BuildFormFields(session, db, formObj)
    End If

    Print "Content-Type: application/json"
    Print ""
    Print responseNav.Stringify()

    Exit Sub

ErrHandler:
    Call SendResponse("error", "Availability check failed at line " & CStr(Erl) & ": " & Error$, Nothing)
    Exit Sub
End Sub

'----------------------------------------------------------
' Submit Booking Handler
'----------------------------------------------------------
Sub HandleSubmitBooking(session As NotesSession, db As NotesDatabase, requestNav As NotesJSONNavigator)
    On Error GoTo ErrHandler

    ' Get required fields
    Dim dateStr As String
    dateStr = CStr(requestNav.GetElementByName("date").Value)

    Dim ip As String
    ip = CStr(requestNav.GetElementByName("ip").Value)

    ' Create the booking request document
    Dim doc As NotesDocument
    Set doc = db.CreateDocument()
    doc.Form = "BookingRequest"

    Call doc.ReplaceItemValue("RequestDate", dateStr)
    Call doc.ReplaceItemValue("RemoteIP", ip)
    Call doc.ReplaceItemValue("Source", "WordPress")

    ' Set creation timestamp
    Dim dt As New NotesDateTime("")
    Call dt.SetNow
    Call doc.ReplaceItemValue("CreatedDate", dt)

    ' Process answers array
    Dim answersElem As NotesJSONElement
    Set answersElem = requestNav.GetElementByName("answers", True)
    If Not (answersElem Is Nothing) Then
        Dim answersArr As NotesJSONArray
        Set answersArr = answersElem.Value

        Dim answerEl As NotesJSONElement
        Set answerEl = answersArr.GetFirstElement()
        While Not (answerEl Is Nothing)
            Dim answerObj As NotesJSONObject
            Set answerObj = answerEl.Value

            Dim fieldName As String
            fieldName = CStr(answerObj.GetElementByName("name").Value)

            Dim fieldValue As String
            fieldValue = CStr(answerObj.GetElementByName("value").Value)

            ' Store each answer as a field on the document
            Call doc.ReplaceItemValue("WP_" & fieldName, fieldValue)

            Set answerEl = answersArr.GetNextElement(answerEl)
        Wend
    End If

    Call doc.Save(True, False)

    ' Build success response
    Dim responseNav As NotesJSONNavigator
    Set responseNav = session.CreateJSONNavigator("")
    Call responseNav.AppendElement("success", "status")

    Dim successMsg As String
    successMsg = GetConfigValue(db, "BookingSuccessMessage")
    If successMsg = "" Then successMsg = "Your request has been submitted!"
    Call responseNav.AppendElement(successMsg, "message")

    Print "Content-Type: application/json"
    Print ""
    Print responseNav.Stringify()

    Exit Sub

ErrHandler:
    Call SendResponse("error", "Booking submission failed at line " & CStr(Erl) & ": " & Error$, Nothing)
    Exit Sub
End Sub

'----------------------------------------------------------
' Get a configuration value from a CRM config document
'----------------------------------------------------------
Function GetConfigValue(db As NotesDatabase, key As String) As String
    On Error Resume Next
    Dim view As NotesView
    Set view = db.GetView("ConfigByKey")
    If view Is Nothing Then
        GetConfigValue = ""
        Exit Function
    End If

    Dim doc As NotesDocument
    Set doc = view.GetDocumentByKey(key, True)
    If doc Is Nothing Then
        GetConfigValue = ""
        Exit Function
    End If

    GetConfigValue = CStr(doc.GetItemValue("Value")(0))
End Function

'----------------------------------------------------------
' Build form fields array from CRM form configuration
' This reads the form field definitions stored in the CRM
' and adds them as a JSON array to the form object
'----------------------------------------------------------
Sub BuildFormFields(session As NotesSession, db As NotesDatabase, formObj As NotesJSONObject)
    On Error GoTo ErrHandler

    Dim fieldsArr As NotesJSONArray
    Set fieldsArr = formObj.AppendArray("fields")

    ' Read form field config docs from a view sorted by display order
    Dim view As NotesView
    Set view = db.GetView("BookingFormFields")

    If view Is Nothing Then
        ' Fallback: add a basic name + email form
        Call AddDefaultFields(fieldsArr)
        Exit Sub
    End If

    Dim doc As NotesDocument
    Set doc = view.GetFirstDocument()

    If doc Is Nothing Then
        Call AddDefaultFields(fieldsArr)
        Exit Sub
    End If

    While Not (doc Is Nothing)
        Dim fieldObj As NotesJSONObject
        Set fieldObj = fieldsArr.AppendObject()

        Call fieldObj.AppendElement(CStr(doc.GetItemValue("FieldType")(0)), "type")
        Call fieldObj.AppendElement(CStr(doc.GetItemValue("FieldSubType")(0)), "subType")
        Call fieldObj.AppendElement(CStr(doc.GetItemValue("FieldName")(0)), "name")
        Call fieldObj.AppendElement(CStr(doc.GetItemValue("FieldLabel")(0)), "label")
        Call fieldObj.AppendElement(CStr(doc.GetItemValue("FieldPlaceholder")(0)), "placeholder")

        ' Required field — stored as "1" or "0" in the CRM
        Dim reqVal As String
        reqVal = CStr(doc.GetItemValue("FieldRequired")(0))
        If reqVal = "1" Or LCase$(reqVal) = "yes" Then
            Call fieldObj.AppendElement(True, "required")
        Else
            Call fieldObj.AppendElement(False, "required")
        End If

        ' Values for select/radio/checkbox — stored as multi-value field
        Dim valuesArr As NotesJSONArray
        Set valuesArr = fieldObj.AppendArray("values")
        Dim vals As Variant
        vals = doc.GetItemValue("FieldValues")
        If vals(0) <> "" Then
            ForAll v In vals
                Call valuesArr.AppendElement(CStr(v))
            End ForAll
        End If

        Set doc = view.GetNextDocument(doc)
    Wend

    Exit Sub

ErrHandler:
    ' On error, fall back to default fields
    Call AddDefaultFields(fieldsArr)
    Exit Sub
End Sub

'----------------------------------------------------------
' Add default form fields as fallback
'----------------------------------------------------------
Sub AddDefaultFields(fieldsArr As NotesJSONArray)
    Dim f1 As NotesJSONObject
    Set f1 = fieldsArr.AppendObject()
    Call f1.AppendElement("input", "type")
    Call f1.AppendElement("text", "subType")
    Call f1.AppendElement("firstName", "name")
    Call f1.AppendElement("Your First Name", "label")
    Call f1.AppendElement("i.e. Sam", "placeholder")
    Call f1.AppendElement(True, "required")
    Dim v1 As NotesJSONArray
    Set v1 = f1.AppendArray("values")

    Dim f2 As NotesJSONObject
    Set f2 = fieldsArr.AppendObject()
    Call f2.AppendElement("input", "type")
    Call f2.AppendElement("text", "subType")
    Call f2.AppendElement("lastName", "name")
    Call f2.AppendElement("Your Last Name", "label")
    Call f2.AppendElement("", "placeholder")
    Call f2.AppendElement(True, "required")
    Dim v2 As NotesJSONArray
    Set v2 = f2.AppendArray("values")

    Dim f3 As NotesJSONObject
    Set f3 = fieldsArr.AppendObject()
    Call f3.AppendElement("input", "type")
    Call f3.AppendElement("email", "subType")
    Call f3.AppendElement("email", "name")
    Call f3.AppendElement("Email Address", "label")
    Call f3.AppendElement("you@example.com", "placeholder")
    Call f3.AppendElement(True, "required")
    Dim v3 As NotesJSONArray
    Set v3 = f3.AppendArray("values")

    Dim f4 As NotesJSONObject
    Set f4 = fieldsArr.AppendObject()
    Call f4.AppendElement("input", "type")
    Call f4.AppendElement("phone", "subType")
    Call f4.AppendElement("phone", "name")
    Call f4.AppendElement("Phone Number", "label")
    Call f4.AppendElement("(555) 123-4567", "placeholder")
    Call f4.AppendElement(True, "required")
    Dim v4 As NotesJSONArray
    Set v4 = f4.AppendArray("values")
End Sub
```

- [ ] **Step 2: Commit**

```bash
git add gigbuilder-tools/domino/jsontools.lss
git commit -m "feat: add Domino jsontools agent with availability check and booking handlers"
```

---

### Task 9: Integration Test — Manual Walkthrough

This task verifies the full end-to-end flow works.

- [ ] **Step 1: Activate the plugin in WordPress admin**

Navigate to Plugins → Installed Plugins. Activate "Gigbuilder Tools".

- [ ] **Step 2: Configure the settings**

Navigate to Settings → Gigbuilder Tools. Enter:
- Server URL: `https://tpa.gigbuilder.com`
- Database Path: `/cal70.nsf`

Click Save Changes.

- [ ] **Step 3: Add the shortcode to a page**

Edit any page and add:
```
[gigbuilder_availability title="Check Our Availability" button_text="Check Date"]
```

- [ ] **Step 4: Test the widget renders**

Visit the page. Confirm:
- Widget title "Check Our Availability" appears
- Calendar/dropdown toggle works
- Date picker renders correctly
- "Check Date" button is visible

- [ ] **Step 5: Test date check (requires Domino agent deployed)**

Select a date and click "Check Date". Confirm:
- Loading indicator appears
- Response message displays with correct status styling
- If available, booking form renders with CRM-defined fields
- If booked, only the message displays

- [ ] **Step 6: Test form submission (requires Domino agent deployed)**

Fill in the booking form and submit. Confirm:
- Loading indicator appears
- Success message displays
- Form clears after submission

- [ ] **Step 7: Commit any fixes from testing**

```bash
git add -A
git commit -m "fix: address issues found during integration testing"
```

---

## Self-Review Notes

**Spec coverage check:**
- System flow (browser → WP proxy → Domino): Covered in Tasks 3, 5, 6
- Domino endpoint pattern (`{server_url}{db_path}/jsontools?open`): Covered in Task 2 (settings) and Task 3 (API client)
- API contract (checkAvailability, submitBooking, status values): Covered in Tasks 3, 5, 8
- WordPress file structure: Covered in Task 1
- Settings page (server URL + DB path): Covered in Task 2
- Shortcode with attributes: Covered in Task 5
- AJAX flow with IP injection: Covered in Task 5 (`$_SERVER['REMOTE_ADDR']`)
- Date input (calendar + dropdowns, single date): Covered in Tasks 5, 6
- Form renderer (all 8 field types): Covered in Task 4
- Form HTML with `gigbuilder-` prefix, no inline styles: Covered in Task 4
- Domino agent (routing, chunked POST, JSON classes, error handling): Covered in Task 8
- Future widget extensibility: Architecture supports it (modular `widgets/` directory)

**Placeholder scan:** No TBDs, TODOs, or vague instructions found. All code is complete.

**Type consistency:** Method names, class names, AJAX action names, and nonce keys are consistent across PHP, JS, and HTML.
