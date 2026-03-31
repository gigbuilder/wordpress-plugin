<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gigbuilder_Settings {

    private static $option_name  = 'gigbuilder_tools_settings';
    private static $host         = 'https://www.gigbuilder.com';
    private static $profile_url  = 'https://www.gigbuilder.com/login.nsf/getprofile?open';
    private static $agent_path   = '/jsontools?open';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'wp_ajax_gigbuilder_authenticate', array( __CLASS__, 'ajax_authenticate' ) );
        add_action( 'wp_ajax_gigbuilder_disconnect', array( __CLASS__, 'ajax_disconnect' ) );
        add_action( 'wp_ajax_gigbuilder_save_appearance', array( __CLASS__, 'ajax_save_appearance' ) );
        add_action( 'wp_ajax_gigbuilder_save_chat_settings', array( __CLASS__, 'ajax_save_chat_settings' ) );
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

    /**
     * AJAX: Authenticate with Gigbuilder CRM.
     */
    public static function ajax_authenticate() {
        check_ajax_referer( 'gigbuilder_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $username = sanitize_text_field( $_POST['username'] ?? '' );
        $password = isset( $_POST['password'] ) ? sanitize_text_field( $_POST['password'] ) : '';

        if ( empty( $username ) || empty( $password ) ) {
            wp_send_json_error( array( 'message' => 'Username and password are required.' ) );
        }

        // Call Gigbuilder profile endpoint with Basic Auth
        $response = wp_remote_get( self::$profile_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Connection failed: ' . $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 401 ) {
            wp_send_json_error( array( 'message' => 'Invalid username or password.' ) );
        }
        if ( $code < 200 || $code >= 300 ) {
            wp_send_json_error( array( 'message' => 'Server returned HTTP ' . $code ) );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $data ) ) {
            wp_send_json_error( array( 'message' => 'Invalid response from CRM.' ) );
        }

        if ( ( $data['status'] ?? '' ) !== 'authenticated' ) {
            wp_send_json_error( array( 'message' => $data['message'] ?? 'Authentication failed.' ) );
        }

        $path = $data['path'] ?? '';
        if ( empty( $path ) ) {
            wp_send_json_error( array( 'message' => 'No database path returned from CRM.' ) );
        }

        // Ensure path starts with /
        if ( $path[0] !== '/' ) {
            $path = '/' . $path;
        }

        $token        = $data['token'] ?? '';
        $company_name = $data['name'] ?? '';

        // Save authenticated settings (store Base64 auth hash for future API calls)
        $save = array(
            'server_url'    => self::$host,
            'db_path'       => $path,
            'username'      => $username,
            'auth_hash'     => base64_encode( $username . ':' . $password ),
            'authenticated' => true,
        );

        // Preserve existing chat settings on re-auth
        $existing = get_option( self::$option_name, array() );
        foreach ( $existing as $k => $v ) {
            if ( strpos( $k, 'chat_' ) === 0 ) {
                $save[ $k ] = $v;
            }
        }

        // Store token and company name from CRM if provided
        if ( ! empty( $token ) ) {
            $save['chat_token'] = $token;
        }
        if ( ! empty( $company_name ) && empty( $save['chat_company_name'] ) ) {
            $save['chat_company_name'] = $company_name;
        }

        update_option( self::$option_name, $save );

        $msg = 'Authenticated successfully.';
        if ( empty( $token ) ) {
            $msg .= ' <strong>Warning:</strong> No MCP Access Token received. You must first generate an access token in Gigbuilder AI Setup before the chat widget will work.';
        }

        wp_send_json_success( array(
            'message'      => $msg,
            'username'     => $username,
            'path'         => $path,
            'endpoint'     => self::$host . $path . self::$agent_path,
            'token'        => ! empty( $token ) ? '***' : '',
            'companyName'  => $company_name,
        ) );
    }

    /**
     * AJAX: Disconnect / clear saved credentials.
     */
    public static function ajax_disconnect() {
        check_ajax_referer( 'gigbuilder_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        delete_option( self::$option_name );
        wp_send_json_success( array( 'message' => 'Disconnected.' ) );
    }

    /**
     * AJAX: Save widget appearance settings.
     */
    public static function ajax_save_appearance() {
        check_ajax_referer( 'gigbuilder_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $settings = get_option( self::$option_name, array() );

        $allowed_styles = array( 'card', 'stepped', 'minimal' );
        $allowed_inputs = array( 'calendar', 'dropdowns' );

        $style = sanitize_text_field( $_POST['widget_style'] ?? 'card' );
        $settings['widget_style'] = in_array( $style, $allowed_styles, true ) ? $style : 'card';

        $input = sanitize_text_field( $_POST['date_input'] ?? 'calendar' );
        $settings['date_input'] = in_array( $input, $allowed_inputs, true ) ? $input : 'calendar';

        $settings['heading_text']    = sanitize_text_field( $_POST['heading_text'] ?? '' );
        $settings['subheading_text'] = sanitize_text_field( $_POST['subheading_text'] ?? '' );
        $settings['button_text']     = sanitize_text_field( $_POST['button_text'] ?? '' );
        $settings['submit_text']     = sanitize_text_field( $_POST['submit_text'] ?? '' );

        update_option( self::$option_name, $settings );

        wp_send_json_success( array( 'message' => 'Appearance settings saved.' ) );
    }

    /**
     * AJAX: Save chat widget settings.
     */
    public static function ajax_save_chat_settings() {
        check_ajax_referer( 'gigbuilder_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $settings = get_option( self::$option_name, array() );

        $settings['chat_company_name']   = sanitize_text_field( $_POST['chat_company_name'] ?? '' );
        $settings['chat_avatar']         = sanitize_text_field( $_POST['chat_avatar'] ?? '' );
        $settings['chat_launcher_text']  = sanitize_text_field( $_POST['chat_launcher_text'] ?? '' );
        $settings['chat_welcome_message'] = sanitize_textarea_field( $_POST['chat_welcome_message'] ?? '' );
        $settings['chat_token']          = sanitize_text_field( $_POST['chat_token'] ?? '' );

        update_option( self::$option_name, $settings );

        wp_send_json_success( array( 'message' => 'Chat settings saved.' ) );
    }

    /**
     * Get chat setting with default fallback.
     */
    public static function get_chat_setting( $key ) {
        $settings = get_option( self::$option_name, array() );
        $defaults = array(
            'chat_token'          => '',
            'chat_company_name'   => '',
            'chat_avatar'         => "\xF0\x9F\x8E\xB5",
            'chat_launcher_text'  => '',
            'chat_welcome_message' => '',
        );
        return $settings[ $key ] ?? $defaults[ $key ] ?? '';
    }

    /**
     * Derive the chat metadata path from the stored db_path.
     * e.g. "/cal70.nsf" => "cal70.nsf", "/cal/il/djservice.nsf" => "cal/il/djservice.nsf"
     */
    public static function get_chat_metadata_path() {
        $db_path = self::get_setting( 'db_path' );
        return ltrim( $db_path, '/' );
    }

    /**
     * Get appearance setting with default fallback.
     */
    public static function get_appearance( $key ) {
        $settings = get_option( self::$option_name, array() );
        $defaults = array(
            'widget_style'    => 'card',
            'date_input'      => 'calendar',
            'heading_text'    => '',
            'subheading_text' => '',
            'button_text'     => '',
            'submit_text'     => '',
        );
        $value = $settings[ $key ] ?? $defaults[ $key ] ?? '';
        return $value;
    }

    /**
     * Get heading text default based on style.
     */
    public static function get_heading_default( $style = '' ) {
        if ( empty( $style ) ) {
            $style = self::get_appearance( 'widget_style' );
        }
        return $style === 'minimal' ? __( "When's the big day?", 'gigbuilder-tools' ) : __( 'Check Availability', 'gigbuilder-tools' );
    }

    /**
     * Render the settings page.
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings      = get_option( self::$option_name, array() );
        $authenticated = ! empty( $settings['authenticated'] );
        $username      = $settings['username'] ?? '';
        $db_path       = $settings['db_path'] ?? '';
        $nonce         = wp_create_nonce( 'gigbuilder_settings' );
        ?>
        <div class="wrap">
            <h1>Gigbuilder Tools <span style="font-size:0.5em;font-weight:normal;color:#999;">v<?php echo GIGBUILDER_TOOLS_VERSION; ?></span></h1>

            <div id="gigbuilder-settings-message" style="display:none;"></div>

            <!-- Authenticated State -->
            <div id="gigbuilder-connected" style="<?php echo $authenticated ? '' : 'display:none;'; ?>">
                <table class="form-table">
                    <tr>
                        <th>Status</th>
                        <td><span style="color:#46b450;font-weight:600;">&#10003; Connected</span></td>
                    </tr>
                    <tr>
                        <th>Account</th>
                        <td id="gigbuilder-display-username"><?php echo esc_html( $username ); ?></td>
                    </tr>
                    <tr>
                        <th>Database</th>
                        <td id="gigbuilder-display-path"><?php echo esc_html( $db_path ); ?></td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button" id="gigbuilder-disconnect-btn">Disconnect</button>
                </p>
            </div>

            <!-- Widget Appearance -->
            <?php
            $widget_style    = self::get_appearance( 'widget_style' );
            $date_input      = self::get_appearance( 'date_input' );
            $heading_text    = self::get_appearance( 'heading_text' );
            $subheading_text = self::get_appearance( 'subheading_text' );
            $button_text     = self::get_appearance( 'button_text' );
            $submit_text     = self::get_appearance( 'submit_text' );
            ?>
            <div id="gigbuilder-appearance" style="<?php echo $authenticated ? '' : 'display:none;'; ?>">
                <h2>Widget Appearance</h2>
                <p>Choose a layout style and customize the text for your availability widget.</p>

                <table class="form-table">
                    <tr>
                        <th>Widget Style</th>
                        <td>
                            <fieldset>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="radio" name="gigbuilder_widget_style" value="card" <?php checked( $widget_style, 'card' ); ?> />
                                    <strong>Structured Card</strong> &mdash; Contained card with clear visual hierarchy
                                </label>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="radio" name="gigbuilder_widget_style" value="stepped" <?php checked( $widget_style, 'stepped' ); ?> />
                                    <strong>Stepped Flow</strong> &mdash; Multi-step wizard with numbered progress
                                </label>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="radio" name="gigbuilder_widget_style" value="minimal" <?php checked( $widget_style, 'minimal' ); ?> />
                                    <strong>Minimal Inline</strong> &mdash; Compact search-bar style
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gigbuilder-heading-text">Heading</label></th>
                        <td>
                            <input type="text" id="gigbuilder-heading-text" class="regular-text"
                                   value="<?php echo esc_attr( $heading_text ); ?>"
                                   placeholder="<?php echo esc_attr( self::get_heading_default( $widget_style ) ); ?>" />
                            <p class="description">Leave blank for style default.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gigbuilder-subheading-text">Subheading</label></th>
                        <td>
                            <input type="text" id="gigbuilder-subheading-text" class="regular-text"
                                   value="<?php echo esc_attr( $subheading_text ); ?>"
                                   placeholder="<?php echo esc_attr__( 'Check if we\'re available for your event', 'gigbuilder-tools' ); ?>" />
                            <p class="description">Shown below the heading. Most prominent in Minimal style.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gigbuilder-button-text">Button Text</label></th>
                        <td>
                            <input type="text" id="gigbuilder-button-text" class="regular-text"
                                   value="<?php echo esc_attr( $button_text ); ?>"
                                   placeholder="<?php echo esc_attr__( 'Check Date', 'gigbuilder-tools' ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gigbuilder-submit-text">Submit Button Text</label></th>
                        <td>
                            <input type="text" id="gigbuilder-submit-text" class="regular-text"
                                   value="<?php echo esc_attr( $submit_text ); ?>"
                                   placeholder="<?php echo esc_attr__( 'Submit Request', 'gigbuilder-tools' ); ?>" />
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button button-primary" id="gigbuilder-save-appearance-btn">Save Appearance</button>
                    <span id="gigbuilder-appearance-spinner" class="spinner" style="float:none;"></span>
                </p>
            </div>

            <!-- Chat Widget Settings -->
            <?php
            $chat_company_name   = self::get_chat_setting( 'chat_company_name' );
            $chat_avatar         = self::get_chat_setting( 'chat_avatar' );
            $chat_launcher_text  = self::get_chat_setting( 'chat_launcher_text' );
            $chat_welcome_msg    = self::get_chat_setting( 'chat_welcome_message' );
            $chat_meta_path      = self::get_chat_metadata_path();
            $chat_token          = self::get_chat_setting( 'chat_token' );
            ?>
            <div id="gigbuilder-chat-settings" style="<?php echo $authenticated ? '' : 'display:none;'; ?>">
                <h2>Chat Widget</h2>
                <p>Configure the AI chat widget that connects to your n8n agent.</p>

                <table class="form-table">
                    <tr>
                        <th><label for="gigbuilder-chat-company-name">Company Name</label></th>
                        <td>
                            <input type="text" id="gigbuilder-chat-company-name" class="regular-text"
                                   value="<?php echo esc_attr( $chat_company_name ); ?>"
                                   placeholder="My Entertainment Company" />
                            <p class="description">Displayed in the chat header.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Avatar Emoji</th>
                        <td>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <span id="gigbuilder-chat-avatar-preview" style="font-size:32px;line-height:1;cursor:pointer;padding:4px 8px;border:1px solid #ddd;border-radius:4px;background:#f9f9f9;" title="Click to change"><?php echo esc_html( $chat_avatar ?: "\xF0\x9F\x8E\xB5" ); ?></span>
                                <button type="button" class="button" id="gigbuilder-chat-avatar-btn">Choose Emoji</button>
                                <input type="hidden" id="gigbuilder-chat-avatar" value="<?php echo esc_attr( $chat_avatar ); ?>" />
                            </div>
                            <div id="gigbuilder-emoji-picker" style="display:none;margin-top:8px;padding:12px;border:1px solid #ddd;border-radius:6px;background:#fff;max-width:340px;max-height:240px;overflow-y:auto;">
                                <div style="display:grid;grid-template-columns:repeat(8,1fr);gap:4px;text-align:center;"></div>
                            </div>
                            <p class="description">Shown in the chat header avatar circle.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gigbuilder-chat-launcher-text">Launcher Text</label></th>
                        <td>
                            <input type="text" id="gigbuilder-chat-launcher-text" class="regular-text"
                                   value="<?php echo esc_attr( $chat_launcher_text ); ?>"
                                   placeholder="Click to Chat" />
                            <p class="description">Text on the popup bubble. Leave blank for icon only.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gigbuilder-chat-welcome-message">Welcome Message</label></th>
                        <td>
                            <textarea id="gigbuilder-chat-welcome-message" class="large-text" rows="3"
                                      placeholder="Hello! How can I assist you today?"><?php echo esc_textarea( $chat_welcome_msg ); ?></textarea>
                            <p class="description">Initial bot message when the chat opens. HTML allowed.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gigbuilder-chat-token">MCP Access Token</label></th>
                        <td>
                            <input type="text" id="gigbuilder-chat-token" class="regular-text"
                                   value="<?php echo esc_attr( $chat_token ); ?>"
                                   placeholder="Auto-populated from CRM on login" />
                            <p class="description">CRM-issued token for the chat agent. Auto-populated when you authenticate, or enter manually.</p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button button-primary" id="gigbuilder-save-chat-btn">Save Chat Settings</button>
                    <span id="gigbuilder-chat-spinner" class="spinner" style="float:none;"></span>
                </p>
            </div>

            <!-- Available Widgets -->
            <div id="gigbuilder-widgets-info" style="<?php echo $authenticated ? '' : 'display:none;'; ?>">
                <h2>Available Widgets</h2>
                <p>Copy and paste these shortcodes into any page or post to add Gigbuilder widgets to your site.</p>

                <table class="widefat fixed striped" style="max-width:800px;">
                    <thead>
                        <tr>
                            <th style="width:35%;">Shortcode</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[gigbuilder_availability]</code></td>
                            <td>
                                <strong>Check Availability — Calendar</strong><br>
                                Full date checker with a calendar picker. Visitor selects a date, checks availability, and fills out the booking form if the date is open.
                            </td>
                        </tr>
                        <tr>
                            <td><code>[gigbuilder_datepicker]</code></td>
                            <td>
                                <strong>Check Availability — Dropdowns</strong><br>
                                Compact date checker using Month / Day / Year dropdown selects. Same booking flow, smaller footprint. Great for sidebars.
                            </td>
                        </tr>
                        <tr>
                            <td><code>[gigbuilder_clientcenter]</code></td>
                            <td>
                                <strong>Client Center</strong><br>
                                Button that opens the Gigbuilder Client Center in a new tab. Clients can manage their event, view contracts, and make payments.
                            </td>
                        </tr>
                        <tr>
                            <td><code>[gigbuilder_guestrequests]</code></td>
                            <td>
                                <strong>Guest Requests</strong><br>
                                Button that opens the Guest Music Request page in a new tab. Event guests can browse and request songs.
                            </td>
                        </tr>
                        <tr>
                            <td><code>[gigbuilder_chat]</code></td>
                            <td>
                                <strong>AI Chat — Inline</strong><br>
                                Embeds the AI chat widget directly in the page. Visitors can ask questions, check availability, and get instant answers.
                            </td>
                        </tr>
                        <tr>
                            <td><code>[gigbuilder_chat_popup]</code></td>
                            <td>
                                <strong>AI Chat — Popup</strong><br>
                                Adds a floating chat bubble in the bottom-right corner. Place on any page (or in a site-wide template) for always-available chat.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h3 style="margin-top:1.5em;">How to Use</h3>
                <ol>
                    <li>Create a new page or edit an existing one</li>
                    <li>Add a <strong>Shortcode</strong> block (or paste directly in the editor)</li>
                    <li>Paste the shortcode from above</li>
                    <li>Publish or update the page</li>
                </ol>

                <h3>Tips</h3>
                <ul>
                    <li>Each widget inherits your theme's styling automatically</li>
                    <li>You can place multiple widgets on different pages</li>
                    <li>The booking form fields are configured in your Gigbuilder CRM — changes there update automatically</li>
                    <li>Visitors can only submit one booking per browser session to prevent duplicates</li>
                </ul>
            </div>

            <!-- Login State -->
            <div id="gigbuilder-login" style="<?php echo $authenticated ? 'display:none;' : ''; ?>">
                <p>Enter your Gigbuilder CRM credentials to connect this site.</p>
                <table class="form-table">
                    <tr>
                        <th><label for="gigbuilder-username">Username</label></th>
                        <td><input type="text" id="gigbuilder-username" class="regular-text" autocomplete="username" /></td>
                    </tr>
                    <tr>
                        <th><label for="gigbuilder-password">Password</label></th>
                        <td><input type="password" id="gigbuilder-password" class="regular-text" autocomplete="current-password" /></td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button button-primary" id="gigbuilder-auth-btn">Authenticate</button>
                    <span id="gigbuilder-auth-spinner" class="spinner" style="float:none;"></span>
                </p>
            </div>
        </div>

        <script>
        (function() {
            var nonce       = '<?php echo $nonce; ?>';
            var ajaxUrl     = '<?php echo admin_url( "admin-ajax.php" ); ?>';
            var msgEl        = document.getElementById( 'gigbuilder-settings-message' );
            var loginPanel   = document.getElementById( 'gigbuilder-login' );
            var connPanel    = document.getElementById( 'gigbuilder-connected' );
            var widgetsInfo  = document.getElementById( 'gigbuilder-widgets-info' );
            var appearPanel  = document.getElementById( 'gigbuilder-appearance' );
            var chatPanel    = document.getElementById( 'gigbuilder-chat-settings' );
            var authBtn     = document.getElementById( 'gigbuilder-auth-btn' );
            var discBtn     = document.getElementById( 'gigbuilder-disconnect-btn' );
            var spinner     = document.getElementById( 'gigbuilder-auth-spinner' );

            function showMsg( type, text ) {
                msgEl.className = 'notice notice-' + type + ' is-dismissible';
                msgEl.innerHTML = '<p>' + text + '</p>';
                msgEl.style.display = '';
            }

            function hideMsg() {
                msgEl.style.display = 'none';
            }

            // Authenticate
            authBtn.addEventListener( 'click', function() {
                var user = document.getElementById( 'gigbuilder-username' ).value.trim();
                var pass = document.getElementById( 'gigbuilder-password' ).value;

                if ( ! user || ! pass ) {
                    showMsg( 'error', 'Please enter both username and password.' );
                    return;
                }

                hideMsg();
                authBtn.disabled = true;
                spinner.classList.add( 'is-active' );

                var fd = new FormData();
                fd.append( 'action', 'gigbuilder_authenticate' );
                fd.append( 'nonce', nonce );
                fd.append( 'username', user );
                fd.append( 'password', pass );

                fetch( ajaxUrl, { method: 'POST', body: fd } )
                    .then( function( r ) { return r.json(); } )
                    .then( function( res ) {
                        authBtn.disabled = false;
                        spinner.classList.remove( 'is-active' );

                        if ( ! res.success ) {
                            showMsg( 'error', res.data.message );
                            return;
                        }

                        showMsg( res.data.token ? 'success' : 'warning', res.data.message );

                        // Update connected panel
                        document.getElementById( 'gigbuilder-display-username' ).textContent = res.data.username;
                        document.getElementById( 'gigbuilder-display-path' ).textContent = res.data.path;

                        // Auto-populate chat settings from CRM
                        if ( res.data.companyName ) {
                            var nameEl = document.getElementById( 'gigbuilder-chat-company-name' );
                            if ( nameEl && !nameEl.value ) nameEl.value = res.data.companyName;
                        }
                        if ( res.data.token ) {
                            var tokenEl = document.getElementById( 'gigbuilder-chat-token' );
                            if ( tokenEl ) tokenEl.value = '(set from CRM)';
                        }

                        // Swap panels
                        loginPanel.style.display = 'none';
                        connPanel.style.display = '';
                        appearPanel.style.display = '';
                        chatPanel.style.display = '';
                        widgetsInfo.style.display = '';

                        // Clear password
                        document.getElementById( 'gigbuilder-password' ).value = '';
                    })
                    .catch( function() {
                        authBtn.disabled = false;
                        spinner.classList.remove( 'is-active' );
                        showMsg( 'error', 'Connection error. Please try again.' );
                    });
            });

            // Disconnect
            discBtn.addEventListener( 'click', function() {
                if ( ! confirm( 'Disconnect this site from Gigbuilder CRM?' ) ) return;

                hideMsg();

                var fd = new FormData();
                fd.append( 'action', 'gigbuilder_disconnect' );
                fd.append( 'nonce', nonce );

                fetch( ajaxUrl, { method: 'POST', body: fd } )
                    .then( function( r ) { return r.json(); } )
                    .then( function( res ) {
                        if ( res.success ) {
                            showMsg( 'success', 'Disconnected from Gigbuilder CRM.' );
                            connPanel.style.display = 'none';
                            appearPanel.style.display = 'none';
                            chatPanel.style.display = 'none';
                            widgetsInfo.style.display = 'none';
                            loginPanel.style.display = '';
                        }
                    });
            });

            // Appearance: update heading placeholder when style changes
            var styleRadios  = document.querySelectorAll( 'input[name="gigbuilder_widget_style"]' );
            var headingInput = document.getElementById( 'gigbuilder-heading-text' );
            var headingDefaults = { card: 'Check Availability', stepped: 'Check Availability', minimal: "When's the big day?" };

            for ( var i = 0; i < styleRadios.length; i++ ) {
                styleRadios[i].addEventListener( 'change', function() {
                    headingInput.placeholder = headingDefaults[ this.value ] || 'Check Availability';
                });
            }

            // Save Appearance
            var saveAppBtn   = document.getElementById( 'gigbuilder-save-appearance-btn' );
            var appSpinner   = document.getElementById( 'gigbuilder-appearance-spinner' );

            saveAppBtn.addEventListener( 'click', function() {
                hideMsg();
                saveAppBtn.disabled = true;
                appSpinner.classList.add( 'is-active' );

                var style = document.querySelector( 'input[name="gigbuilder_widget_style"]:checked' );
                var input = document.querySelector( 'input[name="gigbuilder_date_input"]:checked' );

                var fd = new FormData();
                fd.append( 'action', 'gigbuilder_save_appearance' );
                fd.append( 'nonce', nonce );
                fd.append( 'widget_style', style ? style.value : 'card' );
                fd.append( 'date_input', input ? input.value : 'calendar' );
                fd.append( 'heading_text', headingInput.value );
                fd.append( 'subheading_text', document.getElementById( 'gigbuilder-subheading-text' ).value );
                fd.append( 'button_text', document.getElementById( 'gigbuilder-button-text' ).value );
                fd.append( 'submit_text', document.getElementById( 'gigbuilder-submit-text' ).value );

                fetch( ajaxUrl, { method: 'POST', body: fd } )
                    .then( function( r ) { return r.json(); } )
                    .then( function( res ) {
                        saveAppBtn.disabled = false;
                        appSpinner.classList.remove( 'is-active' );
                        if ( res.success ) {
                            showMsg( 'success', res.data.message );
                        } else {
                            showMsg( 'error', res.data.message );
                        }
                    })
                    .catch( function() {
                        saveAppBtn.disabled = false;
                        appSpinner.classList.remove( 'is-active' );
                        showMsg( 'error', 'Connection error. Please try again.' );
                    });
            });
            // Emoji Picker
            var emojiGrid = [
                '\uD83C\uDFB5', '\uD83C\uDFB6', '\uD83C\uDFA4', '\uD83C\uDFB8', '\uD83C\uDFB9', '\uD83C\uDFBA', '\uD83C\uDFBB', '\uD83E\uDD41',
                '\uD83C\uDFBC', '\uD83C\uDFA7', '\uD83D\uDCBF', '\uD83D\uDD0A', '\uD83C\uDF89', '\uD83C\uDF8A', '\uD83C\uDF86', '\u2728',
                '\uD83C\uDF1F', '\uD83D\uDD25', '\uD83D\uDCAB', '\uD83D\uDC8E', '\uD83D\uDE80', '\uD83C\uDFC6', '\uD83D\uDCA1', '\uD83D\uDCAC',
                '\uD83D\uDDE8\uFE0F', '\uD83E\uDD16', '\uD83D\uDC7E', '\uD83D\uDC51', '\uD83C\uDFA9', '\uD83D\uDD76\uFE0F', '\uD83D\uDC4B', '\uD83D\uDE00',
                '\uD83D\uDE0E', '\uD83E\uDD29', '\uD83D\uDE09', '\uD83E\uDD1D', '\u2764\uFE0F', '\uD83D\uDCAA', '\uD83C\uDF08', '\u2B50',
                '\uD83C\uDF1E', '\uD83C\uDF3B', '\uD83C\uDF32', '\uD83C\uDF3F', '\uD83D\uDC3E', '\uD83D\uDC36', '\uD83D\uDC31', '\uD83E\uDD8B',
                '\uD83E\uDD85', '\uD83D\uDC1D', '\uD83D\uDC10', '\uD83D\uDC0E', '\u26BD', '\uD83C\uDFC0', '\uD83C\uDFBE', '\u26F3',
                '\uD83C\uDFAC', '\uD83C\uDFA0', '\uD83C\uDFA1', '\uD83C\uDFAA', '\uD83C\uDFD6\uFE0F', '\uD83C\uDF7B', '\uD83C\uDF70', '\uD83C\uDF4E'
            ];
            var pickerEl   = document.getElementById( 'gigbuilder-emoji-picker' );
            var pickerGrid = pickerEl ? pickerEl.querySelector( 'div' ) : null;
            var previewEl  = document.getElementById( 'gigbuilder-chat-avatar-preview' );
            var avatarInput = document.getElementById( 'gigbuilder-chat-avatar' );
            var avatarBtn  = document.getElementById( 'gigbuilder-chat-avatar-btn' );

            if ( pickerGrid ) {
                emojiGrid.forEach( function( em ) {
                    var span = document.createElement( 'span' );
                    span.textContent = em;
                    span.style.cssText = 'font-size:24px;cursor:pointer;padding:4px;border-radius:4px;text-align:center;line-height:1.4;';
                    span.addEventListener( 'mouseenter', function() { span.style.background = '#eee'; } );
                    span.addEventListener( 'mouseleave', function() { span.style.background = ''; } );
                    span.addEventListener( 'click', function() {
                        avatarInput.value = em;
                        previewEl.textContent = em;
                        pickerEl.style.display = 'none';
                    });
                    pickerGrid.appendChild( span );
                });
            }

            function toggleEmojiPicker() {
                pickerEl.style.display = pickerEl.style.display === 'none' ? '' : 'none';
            }
            if ( avatarBtn ) avatarBtn.addEventListener( 'click', toggleEmojiPicker );
            if ( previewEl ) previewEl.addEventListener( 'click', toggleEmojiPicker );

            // Save Chat Settings
            var saveChatBtn  = document.getElementById( 'gigbuilder-save-chat-btn' );
            var chatSpinner  = document.getElementById( 'gigbuilder-chat-spinner' );

            if ( saveChatBtn ) {
                saveChatBtn.addEventListener( 'click', function() {
                    hideMsg();
                    saveChatBtn.disabled = true;
                    chatSpinner.classList.add( 'is-active' );

                    var fd = new FormData();
                    fd.append( 'action', 'gigbuilder_save_chat_settings' );
                    fd.append( 'nonce', nonce );
                    fd.append( 'chat_company_name', document.getElementById( 'gigbuilder-chat-company-name' ).value );
                    fd.append( 'chat_avatar', avatarInput.value );
                    fd.append( 'chat_launcher_text', document.getElementById( 'gigbuilder-chat-launcher-text' ).value );
                    fd.append( 'chat_welcome_message', document.getElementById( 'gigbuilder-chat-welcome-message' ).value );
                    fd.append( 'chat_token', document.getElementById( 'gigbuilder-chat-token' ).value );

                    fetch( ajaxUrl, { method: 'POST', body: fd } )
                        .then( function( r ) { return r.json(); } )
                        .then( function( res ) {
                            saveChatBtn.disabled = false;
                            chatSpinner.classList.remove( 'is-active' );
                            if ( res.success ) {
                                showMsg( 'success', res.data.message );
                            } else {
                                showMsg( 'error', res.data.message );
                            }
                        })
                        .catch( function() {
                            saveChatBtn.disabled = false;
                            chatSpinner.classList.remove( 'is-active' );
                            showMsg( 'error', 'Connection error. Please try again.' );
                        });
                });
            }
        })();
        </script>
        <?php
    }

    public static function get_setting( $key ) {
        $settings = get_option( self::$option_name, array() );
        return $settings[ $key ] ?? '';
    }

    public static function get_app_url( $page ) {
        $server = self::get_setting( 'server_url' );
        $db     = self::get_setting( 'db_path' );
        if ( empty( $server ) || empty( $db ) ) {
            return '';
        }
        return $server . $db . '/' . $page;
    }

    public static function get_endpoint_url() {
        $server = self::get_setting( 'server_url' );
        $db     = self::get_setting( 'db_path' );
        if ( empty( $server ) || empty( $db ) ) {
            return '';
        }
        return $server . $db . self::$agent_path;
    }
}
