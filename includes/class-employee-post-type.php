<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Employee Post Type - Simplified
 */
class RT_Employee_Post_Type_V2 {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'add_capabilities'));
        add_filter('manage_angestellte_v2_posts_columns', array($this, 'custom_columns'));
        add_action('manage_angestellte_v2_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_action('pre_get_posts', array($this, 'filter_posts_for_kunden'));
        add_filter('map_meta_cap', array($this, 'map_employee_meta_caps'), 10, 4);
    }
    
    /**
     * Register post type
     */
    public function register_post_type() {
        $labels = array(
            'name' => __('Mitarbeiter', 'rt-employee-manager-v2'),
            'singular_name' => __('Mitarbeiter', 'rt-employee-manager-v2'),
            'menu_name' => __('Mitarbeiter', 'rt-employee-manager-v2'),
            'add_new' => __('Neuer Mitarbeiter', 'rt-employee-manager-v2'),
            'add_new_item' => __('Neuen Mitarbeiter hinzufügen', 'rt-employee-manager-v2'),
            'edit_item' => __('Mitarbeiter bearbeiten', 'rt-employee-manager-v2'),
            'new_item' => __('Neuer Mitarbeiter', 'rt-employee-manager-v2'),
            'view_item' => __('Mitarbeiter anzeigen', 'rt-employee-manager-v2'),
            'search_items' => __('Mitarbeiter suchen', 'rt-employee-manager-v2'),
            'not_found' => __('Keine Mitarbeiter gefunden', 'rt-employee-manager-v2'),
            'not_found_in_trash' => __('Keine Mitarbeiter im Papierkorb', 'rt-employee-manager-v2'),
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-groups',
            'menu_position' => 25,
            'capability_type' => array('angestellte_v2', 'angestellte_v2s'),
            'capabilities' => array(
                'edit_post' => 'edit_angestellte_v2',
                'read_post' => 'read_angestellte_v2',
                'delete_post' => 'delete_angestellte_v2',
                'edit_posts' => 'edit_angestellte_v2s',
                'edit_others_posts' => 'edit_others_angestellte_v2s',
                'publish_posts' => 'publish_angestellte_v2s',
                'read_private_posts' => 'read_private_angestellte_v2s',
                'delete_posts' => 'delete_angestellte_v2s',
                'delete_private_posts' => 'delete_private_angestellte_v2s',
                'delete_published_posts' => 'delete_published_angestellte_v2s',
                'delete_others_posts' => 'delete_others_angestellte_v2s',
                'edit_private_posts' => 'edit_private_angestellte_v2s',
                'edit_published_posts' => 'edit_published_angestellte_v2s',
                'create_posts' => 'edit_angestellte_v2s',
            ),
            'map_meta_cap' => true,
            'hierarchical' => false,
            'supports' => array('title'),
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
        );
        
        register_post_type('angestellte_v2', $args);
    }
    
    /**
     * Add capabilities to roles
     */
    public function add_capabilities() {
        // Define all employee capabilities
        $employee_caps = array(
            'edit_angestellte_v2',
            'read_angestellte_v2',
            'delete_angestellte_v2',
            'edit_angestellte_v2s',
            'edit_others_angestellte_v2s',
            'publish_angestellte_v2s',
            'read_private_angestellte_v2s',
            'delete_angestellte_v2s',
            'delete_private_angestellte_v2s',
            'delete_published_angestellte_v2s',
            'delete_others_angestellte_v2s',
            'edit_private_angestellte_v2s',
            'edit_published_angestellte_v2s',
        );
        
        // Add all capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($employee_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Add limited capabilities to kunden_v2 role
        $kunden_v2_role = get_role('kunden_v2');
        if ($kunden_v2_role) {
            $kunden_caps = array(
                'edit_angestellte_v2',
                'read_angestellte_v2',
                'delete_angestellte_v2',
                'edit_angestellte_v2s',
                'publish_angestellte_v2s',
                'delete_angestellte_v2s',
                'delete_published_angestellte_v2s',
                'edit_published_angestellte_v2s',
            );
            
            foreach ($kunden_caps as $cap) {
                $kunden_v2_role->add_cap($cap);
            }
        }
        
        // Add capabilities to original kunden role too (for backward compatibility)
        $kunden_role = get_role('kunden');
        if ($kunden_role) {
            $kunden_caps = array(
                'edit_angestellte_v2',
                'read_angestellte_v2',
                'delete_angestellte_v2',
                'edit_angestellte_v2s',
                'publish_angestellte_v2s',
                'delete_angestellte_v2s',
                'delete_published_angestellte_v2s',
                'edit_published_angestellte_v2s',
            );
            
            foreach ($kunden_caps as $cap) {
                $kunden_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Custom admin columns
     */
    public function custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __('Name', 'rt-employee-manager-v2');
        $new_columns['employee_id'] = __('Mitarbeiter-Nr.', 'rt-employee-manager-v2');
        $new_columns['email'] = __('E-Mail', 'rt-employee-manager-v2');
        $new_columns['employment_type'] = __('Art der Beschäftigung', 'rt-employee-manager-v2');
        $new_columns['status'] = __('Status', 'rt-employee-manager-v2');
        $new_columns['employer'] = __('Arbeitgeber', 'rt-employee-manager-v2');
        $new_columns['pdf_actions'] = __('PDF', 'rt-employee-manager-v2');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Custom column content
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'employee_id':
                echo esc_html($post_id);
                break;
                
            case 'email':
                $email = get_post_meta($post_id, 'email', true);
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                } else {
                    echo '—';
                }
                break;
                
            case 'employment_type':
                $type = get_post_meta($post_id, 'art_des_dienstverhaltnisses', true);
                echo esc_html($type ?: '—');
                break;
                
            case 'status':
                $status = get_post_meta($post_id, 'status', true) ?: 'active';
                $statuses = array(
                    'active' => __('Beschäftigt', 'rt-employee-manager-v2'),
                    'inactive' => __('Beurlaubt', 'rt-employee-manager-v2'),
                    'suspended' => __('Suspendiert', 'rt-employee-manager-v2'),
                    'terminated' => __('Ausgeschieden', 'rt-employee-manager-v2'),
                );
                
                $status_label = isset($statuses[$status]) ? $statuses[$status] : $status;
                $color = $status === 'active' ? 'green' : ($status === 'terminated' ? 'red' : 'orange');
                echo '<span style="color: ' . esc_attr($color) . ';">' . esc_html($status_label) . '</span>';
                break;
                
            case 'employer':
                $employer_id = get_post_meta($post_id, 'employer_id', true);
                if ($employer_id) {
                    $user = get_user_by('id', $employer_id);
                    if ($user) {
                        echo esc_html($user->display_name);
                    } else {
                        echo __('Unbekannt', 'rt-employee-manager-v2');
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'pdf_actions':
                $latest_pdf = get_post_meta($post_id, '_latest_pdf_url', true);
                
                // Show simple edit link instead of PDF buttons
                echo '<a href="' . get_edit_post_link($post_id) . '" class="button button-small">';
                echo __('Bearbeiten', 'rt-employee-manager-v2');
                echo '</a>';
                break;
        }
    }
    
    /**
     * Filter posts for kunden users - only show their own employees
     */
    public function filter_posts_for_kunden($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('post_type') !== 'angestellte_v2') {
            return;
        }
        
        $user = wp_get_current_user();
        if (in_array('kunden_v2', $user->roles) && !in_array('administrator', $user->roles)) {
            // Only show employees where employer_id matches current user
            $meta_query = $query->get('meta_query') ?: array();
            $meta_query[] = array(
                'key' => 'employer_id',
                'value' => $user->ID,
                'compare' => '='
            );
            $query->set('meta_query', $meta_query);
        }
    }
    
    /**
     * Map meta capabilities for employee posts
     */
    public function map_employee_meta_caps($caps, $cap, $user_id, $args) {
        // Only handle our post type
        if (!isset($args[0]) || get_post_type($args[0]) !== 'angestellte_v2') {
            return $caps;
        }
        
        $post = get_post($args[0]);
        $post_type = get_post_type_object($post->post_type);
        
        // Get the user object
        $user = get_userdata($user_id);
        
        // Administrators can do everything
        if (in_array('administrator', $user->roles)) {
            return $caps;
        }
        
        // For kunden users, check ownership
        if (in_array('kunden', $user->roles) || in_array('kunden_v2', $user->roles)) {
            $employer_id = get_post_meta($post->ID, 'employer_id', true);
            
            // Allow if they are the employer (owner) of this employee
            if ($employer_id && $employer_id == $user_id) {
                switch ($cap) {
                    case 'edit_angestellte_v2':
                    case 'delete_angestellte_v2':
                    case 'read_angestellte_v2':
                        return array('exist'); // Minimal capability that all users have
                        break;
                }
            } else {
                // If they don't own this employee, deny access
                return array('do_not_allow');
            }
        }
        
        return $caps;
    }
    
        
        if ($pagenow === 'edit.php' && $post_type === 'angestellte_v2') {
            ?>
            <script type="text/javascript">
            function generateQuickPDF(employeeId) {
                const statusDiv = document.getElementById('pdf-status-' + employeeId);
                if (statusDiv) {
                    statusDiv.innerHTML = '<span style="color: #0073aa;">Erstelle PDF...</span>';
                }
                
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=generate_employee_pdf&employee_id=' + employeeId + '&nonce=<?php echo wp_create_nonce('generate_pdf_v2'); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (statusDiv) {
                            statusDiv.innerHTML = '<span style="color: green;">✓ PDF erstellt</span>';
                        }
                        // Reload the page to show the new PDF buttons
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        if (statusDiv) {
                            statusDiv.innerHTML = '<span style="color: red;">Fehler</span>';
                        }
                        console.error('PDF generation failed:', data.data);
                    }
                })
            <?php
        }
    }
}