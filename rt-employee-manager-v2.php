<?php

/**
 * Plugin Name: RT Employee Manager V2
 * Plugin URI: https://edrishusein.com
 * Description: Simplified employee management system for Austrian accounting firms with minimal dependencies
 * Version: 2.1.0
 * Author: Edris Husein
 * Text Domain: rt-employee-manager-v2
 */

// Don't allow direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('RT_EMPLOYEE_V2_VERSION', '2.2.0');
define('RT_EMPLOYEE_V2_PLUGIN_FILE', __FILE__);
define('RT_EMPLOYEE_V2_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RT_EMPLOYEE_V2_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class RT_Employee_Manager_V2
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }


    public function init()
    {
        // PDFs can be memory hogs, bump the limit if we can
        if (function_exists('ini_get') && function_exists('ini_set')) {
            $current_limit = ini_get('memory_limit');
            if (intval($current_limit) < 512) {
                @ini_set('memory_limit', '512M');
            }
        }

        $this->load_classes();

        new RT_Employee_Post_Type_V2();
        new RT_Employee_Meta_Boxes_V2();
        new RT_User_Roles_V2();

        if (is_admin()) {
            new RT_Admin_Dashboard_V2();
        }

        // PDF stuff
        new RT_PDF_Generator_V2();

        // Handle capability updates when plugin version changes
        $this->maybe_update_capabilities();
    }

    /**
     * Load all class files
     */
    private function load_classes()
    {
        $classes = array(
            'class-employee-post-type.php',
            'class-employee-meta-boxes.php',
            'class-user-roles.php',
            'class-admin-dashboard.php',
            'class-pdf-generator.php'
        );

        foreach ($classes as $class) {
            $file = RT_EMPLOYEE_V2_PLUGIN_DIR . 'includes/' . $class;
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Run when plugin gets activated
     */
    public function activate()
    {
        // Set up the user roles we need
        $this->create_roles();

        // Make sure post type capabilities are registered
        $this->init_post_types_for_activation();

        // WordPress needs this after registering post types
        flush_rewrite_rules();

        // Track what version we're running
        add_option('rt_employee_v2_version', RT_EMPLOYEE_V2_VERSION);

        error_log('RT Employee Manager V2: Plugin activated successfully');
    }

    /**
     * Load post types during activation so capabilities get set properly
     */
    private function init_post_types_for_activation()
    {
        // Need to load this manually during activation
        require_once RT_EMPLOYEE_V2_PLUGIN_DIR . 'includes/class-employee-post-type.php';
        $post_type_handler = new RT_Employee_Post_Type_V2();
        // The capabilities get registered when this class initializes
    }

    /**
     * Clean up when plugin gets deactivated
     */
    public function deactivate()
    {
        flush_rewrite_rules();
        error_log('RT Employee Manager V2: Plugin deactivated');
    }

    /**
     * Set up the kunden user role for clients
     */
    private function create_roles()
    {
        // Clean slate - remove the old role if it's hanging around
        remove_role('kunden_v2');

        // Create the client role with basic permissions
        add_role('kunden_v2', __('Kunden', 'rt-employee-manager-v2'), array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'upload_files' => true,
        ));

        error_log('RT Employee Manager V2: Created kunden_v2 role');
    }

    /**
     * Set up German translations
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'rt-employee-manager-v2',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }


    /**
     * Check if we need to update capabilities when plugin version changes
     */
    private function maybe_update_capabilities()
    {
        $current_version = get_option('rt_employee_v2_capabilities_version', '0');

        if ($current_version !== RT_EMPLOYEE_V2_VERSION) {
            // Plugin version changed, refresh the capabilities
            $this->init_post_types_for_activation();
            update_option('rt_employee_v2_capabilities_version', RT_EMPLOYEE_V2_VERSION);
            error_log('RT Employee Manager V2: Updated capabilities for version ' . RT_EMPLOYEE_V2_VERSION);
        }
    }
}

// Initialize plugin
RT_Employee_Manager_V2::get_instance();