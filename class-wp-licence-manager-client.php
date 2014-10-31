<?php
/**
 * Wp_Licence_Manager_Client adds licence handling properties to your WordPress plugin.
 *
 *
 * @author Jarkko Laine
 * @url http://fourbean.com/licence-manager
 */

class Wp_Licence_Manager_Client {

    /**
     * The API endpoint. When using the class in your own WordPress plugin, replace this with
     * your installation URL of the WordPress Licence Manager plugin.
     *
     * @var String  The API endpoint.
     */
    //private $api_endpoint = 'http://fourbean.com/fourbase/demo/api/licence-manager';
    private $api_endpoint = 'http://localhost:8888/wordpress-licencer/api/licence-manager';

    /**
     * @var int     The product id of the related product in the licence manager.
     */
    private $product_id;

    private $product_name;

    /**
     * @var String  The version number of the installed plugin / theme. Populated in constructor.
     */
    private $local_version;

    /**
     * @var String  The name / ID of the installed plugin / theme. Populated in constructor.
     */
    private $theme_slug;

    private $theme_text_domain;

    /**
     *
     *
     */
    public function __construct( $product_id, $product_name ) {
        $this->product_id = $product_id;
        $this->product_name = $product_name;

        // TODO: All of the functionality should be limited to the admin area.
        $this->init_wordpress_hooks();

        // Save the theme information for comparing with the server
        // TODO: how about plugins?
        $theme_data = wp_get_theme();
        $this->local_version = $theme_data->Version;
        $this->theme_slug = $theme_data->get_template();
        $this->theme_text_domain = $theme_data->TextDomain;
    }

    /**
     * Hooks the class to required WordPress actions and filters.
     *
     * Important: this function is not supposed to do anything else than just that. All other functionality
     * must be activated through actions and filters.
     */
    public function init_wordpress_hooks() {
        // Checking if there are updates
        add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_for_update' ) );

        // Add the menu screen for inserting licence information
        add_action( 'admin_menu', array( $this, 'add_licence_settings_page' ) );
        add_action( 'admin_init', array( $this, 'add_licence_settings_fields' ) );

        // TODO: doing the update from our own site
        // TODO plugin should check settings and display an error message if email and licence code haven't been inserted
        // TODO: what would happen if someone added this theme also to the regular wp repository?
    }

    /**
     * Creates the settings items for entering licence information (email + licence key).
     *
     * NOTE: Depending on the theme or plugin, you may want to override this method in a sub class
     * to place the settings item in a different location.
     */
    public function add_licence_settings_page() {
        $title = sprintf( __( "%s Licence", $this->theme_text_domain ), $this->product_name );

        add_options_page(
            $title,
            $title,
            'read',
            $this->product_id . '-licences',
            array( $this, 'render_licences_menu' ),
            'dashicons-lock'
        );
    }

    public function add_licence_settings_fields() {
        $settings_group_id = $this->product_id . '-licence-settings-group';
        $settings_section_id = $this->product_id . '-licence-settings-section';

        register_setting( $settings_group_id, $this->product_id . '-licence-settings' );

        add_settings_section(
            $settings_section_id,
            __( 'Licence', $this->theme_text_domain ),
            array( $this, 'render_settings_section' ),
            $settings_group_id
        );

        add_settings_field(
            $this->product_id . '-licence-email',
            __( 'Licence e-mail address', $this->theme_text_domain ),
            array( $this, 'render_email_settings_field' ),
            $settings_group_id,
            $settings_section_id
        );

        add_settings_field(
            $this->product_id . '-licence-key',
            __( 'Licence key', $this->theme_text_domain ),
            array( $this, 'render_licence_key_settings_field' ),
            $settings_group_id,
            $settings_section_id
        );

    }

    public function render_settings_section() {
        echo "Settings section";
    }

    public function render_licences_menu() {
        // TODO: validate licence when saved!

        $title = sprintf( __( "%s Licence", $this->theme_text_domain ), $this->product_name );

        $settings_group_id = $this->product_id . '-licence-settings-group';

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

    public function render_email_settings_field() {
        $settings_field_name = $this->product_id . '-licence-settings';
        $options = get_option( $settings_field_name );
        ?>
            <input type='text' name='<?php echo $settings_field_name; ?>[email]' value='<?php echo $options['email']; ?>'>
        <?php
    }

    public function render_licence_key_settings_field() {
        $settings_field_name = $this->product_id . '-licence-settings';
        $options = get_option( $settings_field_name );
        ?>
            <input type='text' name='<?php echo $settings_field_name; ?>[licence_key]' value='<?php echo $options['licence_key']; ?>'>
        <?php
    }

    /**
     * A filter that checks if there are updates to the theme or plugin
     * using the Licence Manager API.
     *
     * @param $transient
     * @return mixed
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            // TODO: why?
            return $transient;
        }

        if ( $this->is_update_available() ) {
            $info = $this->get_licence_info();
            $transient->response[$this->theme_slug] = array(
                'new_version' => $info->version,
                'package' => $info->package_url,
                'url' => $info->description_url
            );

            // TODO: different message for when licence key hasn't been inserted
        }

        return $transient;
    }

    public function is_update_available() {
        $licence_info = $this->get_licence_info();

        if ( $this->is_api_error( $licence_info ) ) {
            // TODO: show "invalid licence info error"
            return false;
        }

        $server_version = $licence_info->version;

        return ($server_version > $this->local_version);
    }

    public function get_licence_info() {
        $options = get_option( $this->product_id . "-licence-settings" );
        if ( !isset( $options['email' ] ) || !isset( $options['licence_key'] ) ) {
            return array(); // TODO: proper error message
        }

        return $this->call_api(
            'info',
            array(
                'p' => $this->product_id,
                'e' => $options['email'],
                'l' => $options['licence_key']
            )
        );
    }

    /**
     * Makes a call to the WordPress Licence Manager API.
     *
     * @param $method   String  The API method to invoke on the licence manager site
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