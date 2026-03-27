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

        // Save authenticated settings (store Base64 auth hash for future API calls)
        update_option( self::$option_name, array(
            'server_url'    => self::$host,
            'db_path'       => $path,
            'username'      => $username,
            'auth_hash'     => base64_encode( $username . ':' . $password ),
            'authenticated' => true,
        ) );

        wp_send_json_success( array(
            'message'  => 'Authenticated successfully.',
            'username' => $username,
            'path'     => $path,
            'endpoint' => self::$host . $path . self::$agent_path,
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
                    <tr>
                        <th>Endpoint</th>
                        <td id="gigbuilder-display-endpoint">
                            <code><?php echo esc_html( self::get_endpoint_url() ); ?></code>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button" id="gigbuilder-disconnect-btn">Disconnect</button>
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
            var msgEl       = document.getElementById( 'gigbuilder-settings-message' );
            var loginPanel  = document.getElementById( 'gigbuilder-login' );
            var connPanel   = document.getElementById( 'gigbuilder-connected' );
            var widgetsInfo = document.getElementById( 'gigbuilder-widgets-info' );
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

                        showMsg( 'success', res.data.message );

                        // Update connected panel
                        document.getElementById( 'gigbuilder-display-username' ).textContent = res.data.username;
                        document.getElementById( 'gigbuilder-display-path' ).textContent = res.data.path;
                        document.getElementById( 'gigbuilder-display-endpoint' ).innerHTML = '<code>' + res.data.endpoint + '</code>';

                        // Swap panels
                        loginPanel.style.display = 'none';
                        connPanel.style.display = '';
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
                            widgetsInfo.style.display = 'none';
                            loginPanel.style.display = '';
                        }
                    });
            });
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
