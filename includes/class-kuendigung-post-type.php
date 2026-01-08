<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kündigung Post Type - Termination notices
 */
class RT_Kuendigung_Post_Type_V2 {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'add_capabilities'));
    }
    
    /**
     * Register post type
     */
    public function register_post_type() {
        $labels = array(
            'name' => __('Kündigungen', 'rt-employee-manager-v2'),
            'singular_name' => __('Kündigung', 'rt-employee-manager-v2'),
            'menu_name' => __('Kündigungen', 'rt-employee-manager-v2'),
            'add_new' => __('Neue Kündigung', 'rt-employee-manager-v2'),
            'add_new_item' => __('Neue Kündigung erstellen', 'rt-employee-manager-v2'),
            'edit_item' => __('Kündigung bearbeiten', 'rt-employee-manager-v2'),
            'new_item' => __('Neue Kündigung', 'rt-employee-manager-v2'),
            'view_item' => __('Kündigung anzeigen', 'rt-employee-manager-v2'),
            'search_items' => __('Kündigungen suchen', 'rt-employee-manager-v2'),
            'not_found' => __('Keine Kündigungen gefunden', 'rt-employee-manager-v2'),
            'not_found_in_trash' => __('Keine Kündigungen im Papierkorb', 'rt-employee-manager-v2'),
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // Hidden from main menu, accessed via employee screen
            'capability_type' => array('kuendigung_v2', 'kuendigung_v2s'),
            'capabilities' => array(
                'edit_post' => 'edit_kuendigung_v2',
                'read_post' => 'read_kuendigung_v2',
                'delete_post' => 'delete_kuendigung_v2',
                'edit_posts' => 'edit_kuendigung_v2s',
                'edit_others_posts' => 'edit_others_kuendigung_v2s',
                'publish_posts' => 'publish_kuendigung_v2s',
                'read_private_posts' => 'read_private_kuendigung_v2s',
                'delete_posts' => 'delete_kuendigung_v2s',
                'delete_private_posts' => 'delete_private_kuendigung_v2s',
                'delete_published_posts' => 'delete_published_kuendigung_v2s',
                'delete_others_posts' => 'delete_others_kuendigung_v2s',
                'edit_private_posts' => 'edit_private_kuendigung_v2s',
                'edit_published_posts' => 'edit_published_kuendigung_v2s',
                'create_posts' => 'edit_kuendigung_v2s',
            ),
            'map_meta_cap' => true,
            'hierarchical' => false,
            'supports' => array('title'),
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
        );
        
        register_post_type('kuendigung_v2', $args);
    }
    
    /**
     * Add capabilities to roles
     */
    public function add_capabilities() {
        $kuendigung_caps = array(
            'edit_kuendigung_v2',
            'read_kuendigung_v2',
            'delete_kuendigung_v2',
            'edit_kuendigung_v2s',
            'edit_others_kuendigung_v2s',
            'publish_kuendigung_v2s',
            'read_private_kuendigung_v2s',
            'delete_kuendigung_v2s',
            'delete_private_kuendigung_v2s',
            'delete_published_kuendigung_v2s',
            'delete_others_kuendigung_v2s',
            'edit_private_kuendigung_v2s',
            'edit_published_kuendigung_v2s',
        );
        
        // Add all capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($kuendigung_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Add limited capabilities to kunden_v2 role
        $kunden_v2_role = get_role('kunden_v2');
        if ($kunden_v2_role) {
            $kunden_caps = array(
                'edit_kuendigung_v2',
                'read_kuendigung_v2',
                'delete_kuendigung_v2',
                'edit_kuendigung_v2s',
                'publish_kuendigung_v2s',
                'delete_kuendigung_v2s',
                'delete_published_kuendigung_v2s',
                'edit_published_kuendigung_v2s',
            );
            
            foreach ($kunden_caps as $cap) {
                $kunden_v2_role->add_cap($cap);
            }
        }
    }
}
