<?php
/**
 * Wp_License_Manager_Client adds license handling properties to your WordPress theme or plugin.
 *
 * @author Jarkko Laine
 * @url http://fourbean.com/license-manager
 */
class Wp_License_Manager_Client {

    /**
     * The API endpoint. Configured through the class's constructor.
     *
     * @var String  The API endpoint.
     */
    private $api_endpoint;

    /**
     * The product id (slug) used for this product on the License Manager site.
     * Configured through the class's constructor.
     *
     * @var int     The product id of the related product in the license manager.
     */
    private $product_id;

    /**
     * The name of the product using this class. Configured in the class's constructor.
     *
     * @var int     The name of the product (plugin / theme) using this class.
     */
    private $product_name;

    /**
     * Current version of the product (plugin / theme) using this class.
     * Populated in the class's constructor.
     *
     * @var String  The version number of the installed plugin / theme.
     */
    private $local_version;

    /**
     * The slug of the plugin or theme using this class.
     * Populated in the class's constructor.
     *
     * @var String  The name / ID of the plugin / theme.
     */
    private $theme_slug;

    /**
     * The text domain of the plugin or theme using this class.
     * Populated in the class's constructor.
     *
     * @var String  The text domain of the plugin / theme.
     */
    private $theme_text_domain;

    /**
     * Initializes the license manager client.
     *
     * @param $product_id   string  The text id (slug) of the product on the license manager site
     * @param $product_name string  The name of the product, used for menus
     * @param $api_url      string  The URL to the license manager API (your license server)
     * @param $type         string  The type of project this class is being used in ('theme' or 'plugin')
     */
    public function __construct( $product_id, $product_name, $api_url, $type = 'theme' ) {
        $this->product_id = $product_id;
        $this->product_name = $product_name;
        $this->api_endpoint = $api_url;

        // TODO: All of the functionality should be limited to the admin area.
        $this->init_wordpress_hooks();

        // Save the theme information for comparing with the server
        if ( $type == 'theme' ) {
            add_action( 'after_setup_theme', array( $this, 'populate_theme_information' ) );
        } elseif ( $type == 'plugin' ) {
            // TODO
        }
    }

    /**
     * Hooks the class to required WordPress actions and filters.
     *
     * Important: this function is not supposed to do anything else than just that. All other functionality
     * must be activated through actions and filters.
     */
    public function init_wordpress_hooks() {
        // Check for updates
        add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_for_update' ) );

        // Add the menu screen for inserting license information
        add_action( 'admin_menu', array( $this, 'add_license_settings_page' ) );
        add_action( 'admin_init', array( $this, 'add_license_settings_fields' ) );
    }

    /**
     * Collects information about the current theme. Used for updating themes.
     */
    public function populate_theme_information() {
        $theme_data = wp_get_theme();
        $this->local_version = $theme_data->Version;
        $this->theme_slug = $theme_data->get_template();
        $this->theme_text_domain = $theme_data->TextDomain;
    }

    /**
     * Creates the settings items for entering license information (email + license key).
     *
     * NOTE: Depending on the theme or plugin, you may want to override this method in a sub class
     * to place the settings item in a different location.
     */
    public function add_license_settings_page() {
        $title = sprintf( __( '%s License', $this->theme_text_domain ), $this->product_name );

        add_options_page(
            $title,
            $title,
            'read',
            $this->product_id . '-licenses',
            array( $this, 'render_licenses_menu' ),
            'dashicons-lock'
        );
    }

    /**
     * Creates the settings fields needed for the license settings menu.
     */
    public function add_license_settings_fields() {
        $settings_group_id = $this->product_id . '-license-settings-group';
        $settings_section_id = $this->product_id . '-license-settings-section';

        register_setting( $settings_group_id, $this->product_id . '-license-settings' );

        add_settings_section(
            $settings_section_id,
            __( 'License', $this->theme_text_domain ),
            array( $this, 'render_settings_section' ),
            $settings_group_id
        );

        add_settings_field(
            $this->product_id . '-license-email',
            __( 'License e-mail address', $this->theme_text_domain ),
            array( $this, 'render_email_settings_field' ),
            $settings_group_id,
            $settings_section_id
        );

        add_settings_field(
            $this->product_id . '-license-key',
            __( 'License key', $this->theme_text_domain ),
            array( $this, 'render_license_key_settings_field' ),
            $settings_group_id,
            $settings_section_id
        );

    }

    /**
     * Renders the description for the settings section.
     */
    public function render_settings_section() {
        echo "Settings section";
    }

    /**
     * Renders the settings page for entering license information.
     */
    public function render_licenses_menu() {
        // TODO: validate license when saved!

        $title = sprintf( __( '%s License', $this->theme_text_domain ), $this->product_name );

        $settings_group_id = $this->product_id . '-license-settings-group';

        ?>
        <form action='options.php' method='post'>

            <h2><?php echo $title; ?></h2>

            <?php
            settings_fields( $settings_group_id );
            do_settings_sections( $settings_group_id );
            submit_button();
            ?>

        </form>
    <?php
    }

    /**
     * Renders the email settings field on the license settings page.
     */
    public function render_email_settings_field() {
        $settings_field_name = $this->product_id . '-license-settings';
        $options = get_option( $settings_field_name );
        ?>
            <input type='text' name='<?php echo $settings_field_name; ?>[email]' value='<?php echo $options['email']; ?>'>
        <?php
    }

    /**
     * Renders the license key settings field on the license settings page.
     */
    public function render_license_key_settings_field() {
        $settings_field_name = $this->product_id . '-license-settings';
        $options = get_option( $settings_field_name );
        ?>
            <input type='text' name='<?php echo $settings_field_name; ?>[license_key]' value='<?php echo $options['license_key']; ?>'>
        <?php
    }

    /**
     * A filter that checks if there are updates to the theme or plugin
     * using the License Manager API.
     *
     * @param $transient
     * @return mixed
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        if ( $this->is_update_available() ) {
            $info = $this->get_license_info();
            $transient->response[$this->theme_slug] = array(
                'new_version' => $info->version,
                'package' => $info->package_url,
                'url' => $info->description_url
            );

            // TODO: different message for when license key hasn't been inserted
        }

        return $transient;
    }

    /**
     * Checks whether there is an update available for this theme or not.
     *
     * @return bool True if the remote version of the product is newer than this one
     */
    public function is_update_available() {
        $license_info = $this->get_license_info();

        if ( $this->is_api_error( $license_info ) ) {
            // TODO: show "invalid license info error"
            return false;
        }

        $server_version = $license_info->version;

        return ($server_version > $this->local_version);
    }

    public function get_license_info() {
        $options = get_option( $this->product_id . "-license-settings" );
        if ( !isset( $options['email' ] ) || !isset( $options['license_key'] ) ) {
            return array(); // TODO: proper error message
        }

        return $this->call_api(
            'info',
            array(
                'p' => $this->product_id,
                'e' => $options['email'],
                'l' => $options['license_key']
            )
        );
    }

    /**
     * Makes a call to the WordPress License Manager API.
     *
     * @param $method   String  The API method to invoke on the license manager site
     * @param $params   array   The parameters for the API call
     * @return          array   The API response
     */
    private function call_api( $method, $params ) {
        // TODO: should we check for double slashes?
        $url = $this->api_endpoint . '/' . $method;

        // Append parameters for GET request
        $first = true;
        foreach ( $params as $key => $value ) {
            if ( $first ) {
                $url .= '?';
                $first = false;
            }
            $url .= $key . '=' . $value . '&';
        }

        $response = wp_remote_get( $url );
        if ( is_wp_error( $response ) ) {
            echo 'Error making request: ' . $response->get_error_message() . ' )';
            return -1; // TODO proper error response
        }

        $response_body = wp_remote_retrieve_body( $response );
        $response_code = wp_remote_retrieve_response_code( $response );

        $result = json_decode( $response_body );
        return $result;
    }

    private function is_api_error( $response ) {
        if ( !is_array( $response ) ) {
            return true;
        }

        if ( isset( $response['error'] ) ) {
            return true;
        }

        return false;
    }

} 