<?php
/**
 * Plugin Name:         EJO Client
 * Plugin URI:          https://github.com/erikjoling/ejo-client
 * Description:         Improved permissions and user experience for EJOweb clients.
 * Version:             1.5.1
 * Author:              Erik Joling
 * Author URI:          https://www.ejoweb.nl/
 * Text Domain:         ejo-client
 * Domain Path:         /languages
 *
 * GitHub Plugin URI:   https://github.com/erikjoling/ejo-client
 * GitHub Branch:       master
 *
 * Minimum PHP version: 5.6
 */

/**
 *
 */
final class EJO_Client
{
    /* Holds the instance of this class. */
    private static $_instance = null;

    /* Version number of this plugin */
    public static $version = '1.4.2';

    /* Stores the handle of this plugin */
    public static $handle;

    /* Stores the plugin sub-directory/file */
    public static $plugin;

    /* Stores the directory path for this plugin. */
    public static $dir;

    /* Stores the directory URI for this plugin. */
    public static $uri;

    /* Name of client role */
    public static $role_name = 'client';

    /* Returns the instance. */
    public static function init() 
    {
        if ( !self::$_instance )
            self::$_instance = new self;
        return self::$_instance;
    }

    /* Plugin setup. */
    private function __construct() 
    {
        /* Setup */
        self::setup();

        /* Add activation hook */
        register_activation_hook( __FILE__, ['EJO_Client', 'on_plugin_activation'] );

        /* Add uninstall hook */
        register_uninstall_hook( __FILE__, ['EJO_Client', 'on_plugin_uninstall'] );
        register_deactivation_hook( __FILE__, ['EJO_Client', 'on_plugin_uninstall'] );

        //* Add Reset when a plugin has been (de)activated
        add_action( 'admin_init', ['EJO_Client', 'reset_on_every_plugin_activation'], 99 );

        //* Add hook to remove damned Gravity Forms filter
        add_action( 'admin_menu', ['EJO_Client', 'gravityforms_fix'], 9 );
        add_action( 'after_setup_theme', ['EJO_Client', 'gravityforms_fix'], 99 );

        //* Reset caps on plugin and theme upgrades
        add_action( 'upgrader_process_complete', ['EJO_Client', 'reset_on_every_upgrade'], 10, 2 );

        //* Add Reset link to plugin actions row
        add_filter( 'plugin_action_links_' . self::$plugin, ['EJO_Client', 'add_plugin_actions_link'] );

        //* Hook client-cap reset to plugin page
        add_action( 'pre_current_active_plugins', ['EJO_Client', 'reset_on_plugins_page'] );

        // Restrict roles
        add_filter( 'editable_roles', ['EJO_Client', 'editable_roles'] );

        // 
        add_filter( 'map_meta_cap', ['EJO_Client', 'map_meta_cap'], 10, 4 );

        add_filter( 'wp_dropdown_users_args', ['EJO_Client', 'add_clients_to_user_dropdown'], 10, 2 );
        
        // DEBUG
        // add_action( 'after_setup_theme', function() {
        //     EJO_Client::get_caps();
        // });
    }

    /* Defines the directory path and URI for the plugin. */
    public static function setup() 
    {
        self::$handle = dirname( plugin_basename( __FILE__ ) );
        self::$plugin = plugin_basename( __FILE__ );
        self::$dir = plugin_dir_path( __FILE__ );
        self::$uri = plugin_dir_url( __FILE__ );

        /* Load the translation for the plugin */
        load_plugin_textdomain(self::$handle, false, self::$handle . '/languages' );

        /* Load the files of supported plugins */
        require_once( self::$dir . 'supported-plugins/wordpress-seo.php' );
        require_once( self::$dir . 'supported-plugins/gravityforms.php' );
        require_once( self::$dir . 'supported-plugins/ejo-contactadvertenties.php' );
    }


    /* Fire when activating this plugin */
    public static function on_plugin_activation()
    {
        self::register_client_role();
        self::set_client_caps();
    }

    /* Fire when uninstalling this plugin */
    public static function on_plugin_uninstall()
    {
        self::unregister_client_role();
    }   

    /* Register client role */
    public static function register_client_role() 
    {
        /* Try to get client role */
        $client_role = get_role( self::$role_name );

        /** 
         * If client-role doesn't exist, add it
         * Else remove capabilities of the existing client role
         */
        if ( is_null( $client_role ) ) {
            add_role( self::$role_name, __( 'Client' ) );
        }
        else {
            self::remove_client_caps($client_role);
        }
    }

    //* Set the right caps for the client role
    public static function set_client_caps( $client_role = null )
    {
        if (!$client_role)
            $client_role = get_role( self::$role_name );

        if ( is_null( $client_role ) ) {
            return __('No Client Role found');
        }

        //* Remove all current capabilities of the client-role
        self::remove_client_caps($client_role);

        //* Get default client caps
        $client_caps = self::get_default_client_caps();

        //* Add other capabilities    
        $client_caps = array_merge( $client_caps, self::get_blog_caps() ); // Blog
        $client_caps = array_merge( $client_caps, ejo_get_gravityforms_caps() ); // Gravity Forms
        $client_caps = array_merge( $client_caps, get_ejo_contactadvertentie_caps() ); // EJO Contactadvertenties
        $client_caps = array_merge( $client_caps, ejo_get_wpseo_caps() ); // WordPress SEO caps

        //* Remove double capabilities
        $client_caps = array_unique($client_caps);

        //* Allow client_caps to be filtered
        $client_caps = apply_filters( 'ejo_client_caps', $client_caps );

        //* Remove double capabilities
        $client_caps = array_unique($client_caps);

        //* Add client capabilities to role
        foreach ($client_caps as $cap) {

            $client_role->add_cap( $cap );
        }
    }

    //* Alias for set_client_caps()
    public static function reset_client_caps( $client_role = null )
    {
        self::set_client_caps( $client_role );
    }

    /* Get default caps for client */
    public static function get_default_client_caps() 
    {
        $default_client_caps = array(
            //* Super Admin
            // 'manage_network',
            // 'manage_sites',
            // 'manage_network_users',
            // 'manage_network_plugins',
            // 'manage_network_themes',
            // 'manage_network_options',

            //* Admin
            // 'activate_plugins',
            // 'delete_plugins',
            // 'delete_themes',
            // 'edit_files',
            // 'edit_plugins',
            'edit_theme_options',
            // 'edit_themes',
            'export',
            // 'import',
            // 'install_plugins',
            // 'install_themes',
            // 'manage_options',
            'create_users',
            'delete_users',
            'edit_users',
            'list_users',
            'promote_users',
            'remove_users',
            // 'switch_themes',
            // 'update_core',
            // 'update_plugins',
            // 'update_themes',
            // 'edit_dashboard',

            //* Privacy
            // 'manage_privacy_options', // This doesn't work because this isn't a primary capability

            //* Editor
            'moderate_comments',
            'manage_categories',
            'manage_links',
            'edit_others_posts',
            'edit_pages',
            'edit_others_pages',
            'edit_published_pages',
            'publish_pages',
            'delete_pages',
            'delete_others_pages',
            'delete_published_pages',
            'delete_others_posts',
            'delete_private_posts',
            'edit_private_posts',
            'read_private_posts',
            'delete_private_pages',
            'edit_private_pages',
            'read_private_pages',
            'unfiltered_html',

            //* Author
            'edit_published_posts',
            'upload_files',
            'publish_posts',
            'delete_published_posts',

            //* Contributor
            'edit_posts',
            'delete_posts',

            //* All
            'read',

            //* Levels
            //* Needed to appear in author box (https://stackoverflow.com/questions/6330008/wordpress-custom-users-not-appearing-in-author-box)
            // 'level_7', // Does not work somehow. Fixed this by using filter `wp_dropdown_users_args`
        );

        //* Remove blog caps from client_caps by default
        $default_client_caps = array_diff($default_client_caps, self::get_blog_caps(true));

        return apply_filters( 'ejo_client_default_caps', $default_client_caps );
    }

    /* Get Blog capabilities */
    public static function get_blog_caps( $force_return = false ) 
    {
        //* Check if blog is enabled
        $is_blog_enabled = apply_filters( 'ejo_client_blog_enabled', true );

        //* Return empty array if blog is disabled and no forced return (check default_client_caps)
        if ( !$is_blog_enabled && !$force_return )
            return array();

        //* Blog capabilities
        $blog_caps = array(
            'edit_posts',
            'edit_others_posts',
            'edit_published_posts',
            'publish_posts',
            'delete_posts',
            'delete_others_posts',
            'delete_published_posts',
            'delete_private_posts',
            'edit_private_posts',
            'read_private_posts',
            'manage_categories',
            'moderate_comments',
        );

        return apply_filters( 'ejo_client_blog_caps', $blog_caps );
    }

    //* Check whether client-role has caps
    public static function get_client_caps()
    {
        $client_role = get_role( self::$role_name );

        return $client_role->capabilities;
    }

    //* Check whether client-role has caps
    public static function client_has_caps( $client_role = null )
    {
        if (!$client_role)
            $client_role = get_role( self::$role_name );

        //* Return true if not empty
        if ( ! empty($client_role->capabilities) )
            return true;

        return false;
    }

    //* Remove caps of the client-role
    public static function remove_client_caps( $client_role = null )
    {
        if (!$client_role)
            $client_role = get_role( self::$role_name );

        //* Remove capabilities
        foreach ($client_role->capabilities as $cap => $status ) {
            $client_role->remove_cap( $cap );
        }
    }

    /**
     * Gravity Forms adds a filter to WordPress `user_has_cap` to check wether to
     * give a user `gforms_has_full_access` capability. Something about this filter
     * isn't playing nicely with EJO Client. So remove the darned thing!
     */
    public static function gravityforms_fix() {

        $user = wp_get_current_user();

        /* Only remove for EJO Client */
        if (in_array(self::$role_name, $user->roles)) {
            remove_filter( 'user_has_cap', array( 'RGForms', 'user_has_cap' ), 10, 3 );
        }
    }

    /* Unregister client role */
    public static function unregister_client_role()
    {
        /* Remove client role */
        remove_role( self::$role_name );

        /** 
         * When a role is removed, the users who have this role lose all rights on the site 
         * Maybe assign them to editor role
         */
    }

    /* Add reset link to plugin actions row */
    public static function add_plugin_actions_link( $links )
    {
        $links[] = '<a href="'. esc_url( get_admin_url(null, 'plugins.php?reset-ejo-client=true') ) .'">Reset</a>';

        return $links;
    }

    /**
     * Reset client caps on every plugin (de)activation
     */
    public static function reset_on_every_plugin_activation()
    {
        global $pagenow;

        if ($pagenow == 'plugins.php') {

            if ( isset($_GET['activate']) || isset($_GET['deactivate']) || isset($_GET['activate-multi']) || isset($_GET['deactivate-multi']) ) {
                self::set_client_caps();
            }
        }
    }

    /**
     * Reset client caps on every plugin (de)activation
     */
    public static function reset_on_every_upgrade($upgrader_object, $data ) 
    {
        //* Reset client caps on plugin or theme upgrade
        if ( $data['type'] == 'plugin' || $data['type'] == 'theme' ) {
            self::set_client_caps();
        }
    }

    /* Reset Action on plugins page */
    public static function reset_on_plugins_page()
    {
        if ( isset($_GET['reset-ejo-client']) ) {

            $reset_ejo_client = esc_attr($_GET['reset-ejo-client']);

            if ( $reset_ejo_client == true ) {
                self::set_client_caps();

                echo '<div id="message" class="updated notice is-dismissible">';
                echo '<p>EJO Client Reset</p>';
                echo '</div>';
            }
        }
    }

    /**
     * Helper function get getting roles that the user is allowed to create/edit/delete.
     *
     * @param   WP_User $user
     * @return  array
     */
    public static function get_allowed_roles( $user ) {
        $allowed = array();

        if ( in_array( 'administrator', $user->roles ) ) { // Admin can edit all roles
            $allowed = array_keys( $GLOBALS['wp_roles']->roles );
        } 
        elseif ( in_array( self::$role_name, $user->roles ) ) {
            $allowed[] = self::$role_name;
        } 

        return $allowed;
    }

    /**
     * Remove roles that are not allowed for the current user role.
     */
    public static function editable_roles( $roles ) {
        if ( $user = wp_get_current_user() ) {
            $allowed = self::get_allowed_roles( $user );

            foreach ( $roles as $role => $caps ) {
                if ( ! in_array( $role, $allowed ) )
                    unset( $roles[ $role ] );
            }
        }

        return $roles;
    }

    /**
     * Edit capabilities??
     */
    public static function map_meta_cap( $caps, $cap, $user_ID, $args ) {

        // Map `manage_privacy_options` to 'edit_theme_options' capability
        // Custom primary capabilities are not allowed or something...
        if ( $cap === 'manage_privacy_options' ) {
            $caps = array('edit_theme_options');
        }

        // Prevent users deleting/editing users with a role outside their allowance.
        if ( ( $cap === 'edit_user' || $cap === 'delete_user' ) && $args ) {
            $the_user = get_userdata( $user_ID ); // The user performing the task
            $user     = get_userdata( $args[0] ); // The user being edited/deleted

            if ( $the_user && $user && $the_user->ID != $user->ID /* User can always edit self */ ) {
                $allowed = self::get_allowed_roles( $the_user );

                if ( array_diff( $user->roles, $allowed ) ) {
                    // Target user has roles outside of our limits
                    $caps[] = 'not_allowed';
                }
            }
        }

        return $caps;
    }

    /**
     * Add Clients to author dropdown of page-editing
     *
     * NOTE: This method adds all users to the dropdown!
     * More info: https://stackoverflow.com/questions/6330008/wordpress-custom-users-not-appearing-in-author-box
     */
    public static function add_clients_to_user_dropdown( $query_args, $r ) 
    {         
        $query_args['who'] = '';
        return $query_args;
    }

    //* Get caps
    public static function get_caps()
    {
        $client_role = get_role( self::$role_name );

        // error_log(print_r($client_role, true));

        //* Return true if not empty
        if ( ! empty($client_role->capabilities) )
            return $client_role->capabilities;
    }
}

/* Call EJO Client Admin */
EJO_Client::init();
