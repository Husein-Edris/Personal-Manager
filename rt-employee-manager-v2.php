<?php
/**
 * Plugin Name: RT Employee Manager V2
 * Plugin URI: https://edrishusein.com
 * Description: Simplified employee management system for Austrian accounting firms with minimal dependencies
 * Version: 2.0.0
 * Author: Edris Husein
 * License: GPL v2 or later
 * Text Domain: rt-employee-manager-v2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RT_EMPLOYEE_V2_VERSION', '2.0.0');
define('RT_EMPLOYEE_V2_PLUGIN_FILE', __FILE__);
define('RT_EMPLOYEE_V2_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RT_EMPLOYEE_V2_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class - Simplified Architecture
 */
class RT_Employee_Manager_V2 {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Increase memory limit if needed
        if (function_exists('ini_get') && function_exists('ini_set')) {
            $current_limit = ini_get('memory_limit');
            if (intval($current_limit) < 512) {
                @ini_set('memory_limit', '512M');
            }
        }
        
        // Load core classes
        $this->load_classes();
        
        // Initialize components
        new RT_Employee_Post_Type_V2();
        new RT_Employee_Meta_Boxes_V2();
        new RT_User_Roles_V2();
        // Removed frontend registration - admins create kunden directly
        
        if (is_admin()) {
            new RT_Admin_Dashboard_V2();
        }
        
        // Initialize PDF generator
        new RT_PDF_Generator_V2();
        
        // Check if we need to update capabilities
        $this->maybe_update_capabilities();
    }
    
    /**
     * Load required classes
     */
    private function load_classes() {
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
     * Plugin activation
     */
    public function activate() {
        // Create roles and capabilities
        $this->create_roles();
        
        // Initialize post types to set capabilities
        $this->init_post_types_for_activation();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        add_option('rt_employee_v2_version', RT_EMPLOYEE_V2_VERSION);
        
        error_log('RT Employee Manager V2: Plugin activated successfully');
    }
    
    /**
     * Initialize post types during activation to set capabilities
     */
    private function init_post_types_for_activation() {
        // Load and initialize the post type class to register capabilities
        require_once RT_EMPLOYEE_V2_PLUGIN_DIR . 'includes/class-employee-post-type.php';
        $post_type_handler = new RT_Employee_Post_Type_V2();
        // Capabilities are set during construction
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
        error_log('RT Employee Manager V2: Plugin deactivated');
    }
    
    /**
     * Create user roles
     */
    private function create_roles() {
        // Remove existing role if it exists
        remove_role('kunden_v2');
        
        // Create kunden role with simplified capabilities
        add_role('kunden_v2', __('Kunden', 'rt-employee-manager-v2'), array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'upload_files' => true,
        ));
        
        error_log('RT Employee Manager V2: Created kunden_v2 role');
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'rt-employee-manager-v2',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    
    /**
     * Maybe update capabilities if version changed
     */
    private function maybe_update_capabilities() {
        $current_version = get_option('rt_employee_v2_capabilities_version', '0');
        
        if ($current_version !== RT_EMPLOYEE_V2_VERSION) {
            // Force update capabilities
            $this->init_post_types_for_activation();
            update_option('rt_employee_v2_capabilities_version', RT_EMPLOYEE_V2_VERSION);
            error_log('RT Employee Manager V2: Updated capabilities for version ' . RT_EMPLOYEE_V2_VERSION);
        }
    }
}

// Initialize plugin
RT_Employee_Manager_V2::get_instance();