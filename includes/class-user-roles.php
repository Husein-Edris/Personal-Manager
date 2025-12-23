<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * User Roles Management - Simplified
 */
class RT_User_Roles_V2 {
    
    public function __construct() {
        add_action('admin_init', array($this, 'restrict_admin_access'));
        add_action('admin_menu', array($this, 'customize_admin_menu'));
        add_filter('wp_admin_bar_show', array($this, 'hide_admin_bar_for_kunden'));
    }
    
    /**
     * Restrict admin access for kunden users
     */
    public function restrict_admin_access() {
        // Allow AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        $user = wp_get_current_user();
        if (!in_array('kunden_v2', $user->roles) || in_array('administrator', $user->roles)) {
            return;
        }
        
        // Get current page
        $current_page = '';
        if (isset($_GET['page'])) {
            $current_page = $_GET['page'];
        } elseif (isset($GLOBALS['pagenow'])) {
            $current_page = $GLOBALS['pagenow'];
        }
        
        // Allowed pages for kunden users
        $allowed_pages = array(
            'edit.php',
            'post-new.php',
            'post.php',
            'profile.php',
            'admin-ajax.php',
            'rt-employee-dashboard-v2',
            'rt-employee-reports-v2'
        );
        
        // Check if trying to access employee posts
        if (in_array($current_page, array('edit.php', 'post-new.php', 'post.php'))) {
            $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'post';
            
            // For post.php, check the actual post type
            if ($current_page === 'post.php' && isset($_GET['post'])) {
                $post = get_post($_GET['post']);
                if ($post) {
                    $post_type = $post->post_type;
                }
            }
            
            // Only allow access to angestellte_v2 post type
            if ($post_type !== 'angestellte_v2') {
                wp_redirect(admin_url('edit.php?post_type=angestellte_v2'));
                exit;
            }
            
            // For post.php, ensure they own the employee
            if ($current_page === 'post.php' && isset($_GET['post'])) {
                $post_id = intval($_GET['post']);
                $employer_id = get_post_meta($post_id, 'employer_id', true);
                
                if ($employer_id != $user->ID) {
                    wp_redirect(admin_url('edit.php?post_type=angestellte_v2'));
                    exit;
                }
            }
            
            return; // Allow access to employee posts
        }
        
        // Check if current page is in allowed list
        $is_allowed = in_array($current_page, $allowed_pages) || 
                     strpos($current_page, 'rt-employee-') === 0;
        
        if (!$is_allowed) {
            // Redirect to employee list
            wp_redirect(admin_url('edit.php?post_type=angestellte_v2'));
            exit;
        }
    }
    
    /**
     * Customize admin menu for kunden users
     */
    public function customize_admin_menu() {
        $user = wp_get_current_user();
        if (!in_array('kunden_v2', $user->roles) || in_array('administrator', $user->roles)) {
            return;
        }
        
        global $menu, $submenu;
        
        // Remove unwanted menu items for kunden users
        $remove_menu_items = array(
            'index.php',                 // Dashboard
            'separator1',                // Separator
            'edit.php',                  // Posts
            'upload.php',                // Media
            'edit.php?post_type=page',   // Pages
            'edit-comments.php',         // Comments
            'separator2',                // Separator
            'themes.php',                // Appearance
            'plugins.php',               // Plugins
            'users.php',                 // Users
            'tools.php',                 // Tools
            'options-general.php',       // Settings
            'separator-last',            // Separator
        );
        
        // Also remove specific admin bar items
        add_action('wp_before_admin_bar_render', array($this, 'remove_admin_bar_items'));
        
        foreach ($remove_menu_items as $item) {
            remove_menu_page($item);
        }
        
        // Remove unwanted submenus
        if (isset($submenu['edit.php?post_type=angestellte_v2'])) {
            unset($submenu['edit.php?post_type=angestellte_v2'][10]); // Remove "Add New" if needed
        }
        
        // Add custom menu items
        add_menu_page(
            __('Dashboard', 'rt-employee-manager-v2'),
            __('Dashboard', 'rt-employee-manager-v2'),
            'read',
            'rt-employee-dashboard-v2',
            array($this, 'dashboard_page'),
            'dashicons-dashboard',
            2
        );
        
    }
    
    /**
     * Dashboard page for kunden users
     */
    public function dashboard_page() {
        $user = wp_get_current_user();
        $employee_count = $this->get_employee_count($user->ID);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Mitarbeiter Dashboard', 'rt-employee-manager-v2'); ?></h1>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3 style="margin-top: 0;"><?php _e('Mitarbeiter Gesamt', 'rt-employee-manager-v2'); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; color: #0073aa; margin: 0;">
                        <?php echo esc_html($employee_count['total']); ?>
                    </p>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3 style="margin-top: 0;"><?php _e('Beschäftigt', 'rt-employee-manager-v2'); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; color: #46b450; margin: 0;">
                        <?php echo esc_html($employee_count['active']); ?>
                    </p>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3 style="margin-top: 0;"><?php _e('Inaktiv', 'rt-employee-manager-v2'); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; color: #dc3232; margin: 0;">
                        <?php echo esc_html($employee_count['inactive']); ?>
                    </p>
                </div>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin: 20px 0;">
                <h3><?php _e('Schnellaktionen', 'rt-employee-manager-v2'); ?></h3>
                <p>
                    <a href="<?php echo admin_url('post-new.php?post_type=angestellte_v2'); ?>" class="button button-primary">
                        <?php _e('Neuen Mitarbeiter hinzufügen', 'rt-employee-manager-v2'); ?>
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=angestellte_v2'); ?>" class="button">
                        <?php _e('Alle Mitarbeiter anzeigen', 'rt-employee-manager-v2'); ?>
                    </a>
                </p>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <h3><?php _e('Neueste Mitarbeiter', 'rt-employee-manager-v2'); ?></h3>
                <?php $this->display_recent_employees($user->ID); ?>
            </div>
        </div>
        <?php
    }
    
    
    /**
     * Hide admin bar for kunden users on frontend
     */
    public function hide_admin_bar_for_kunden($show) {
        $user = wp_get_current_user();
        if (in_array('kunden_v2', $user->roles) && !in_array('administrator', $user->roles)) {
            return false;
        }
        return $show;
    }
    
    /**
     * Remove admin bar items for kunden users
     */
    public function remove_admin_bar_items() {
        global $wp_admin_bar;
        
        $user = wp_get_current_user();
        if (!in_array('kunden_v2', $user->roles) || in_array('administrator', $user->roles)) {
            return;
        }
        
        // Remove unwanted admin bar items
        $wp_admin_bar->remove_node('new-content');
        $wp_admin_bar->remove_node('comments');
        $wp_admin_bar->remove_node('new-post');
        $wp_admin_bar->remove_node('new-page');
        $wp_admin_bar->remove_node('new-media');
        $wp_admin_bar->remove_node('new-user');
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('about');
        $wp_admin_bar->remove_node('wporg');
        $wp_admin_bar->remove_node('documentation');
        $wp_admin_bar->remove_node('support-forums');
        $wp_admin_bar->remove_node('feedback');
        $wp_admin_bar->remove_node('view-site');
        
        // Remove "Edit Page" link specifically
        if (isset($_GET['post']) && get_post_type($_GET['post']) === 'page') {
            $wp_admin_bar->remove_node('edit');
        }
    }
    
    /**
     * Get employee count for user
     */
    private function get_employee_count($user_id) {
        $args = array(
            'post_type' => 'angestellte_v2',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'employer_id',
                    'value' => $user_id,
                    'compare' => '='
                )
            )
        );
        
        $employees = get_posts($args);
        
        $counts = array(
            'total' => count($employees),
            'active' => 0,
            'inactive' => 0
        );
        
        foreach ($employees as $employee) {
            $status = get_post_meta($employee->ID, 'status', true) ?: 'active';
            if ($status === 'active') {
                $counts['active']++;
            } else {
                $counts['inactive']++;
            }
        }
        
        return $counts;
    }
    
    /**
     * Display recent employees
     */
    private function display_recent_employees($user_id, $limit = 5) {
        $args = array(
            'post_type' => 'angestellte_v2',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'employer_id',
                    'value' => $user_id,
                    'compare' => '='
                )
            )
        );
        
        $employees = get_posts($args);
        
        if (empty($employees)) {
            echo '<p>' . __('Noch keine Mitarbeiter angelegt.', 'rt-employee-manager-v2') . '</p>';
            return;
        }
        
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Name', 'rt-employee-manager-v2') . '</th>';
        echo '<th>' . __('E-Mail', 'rt-employee-manager-v2') . '</th>';
        echo '<th>' . __('Status', 'rt-employee-manager-v2') . '</th>';
        echo '<th>' . __('Erstellt', 'rt-employee-manager-v2') . '</th>';
        echo '<th>' . __('Aktionen', 'rt-employee-manager-v2') . '</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($employees as $employee) {
            $email = get_post_meta($employee->ID, 'email', true);
            $status = get_post_meta($employee->ID, 'status', true) ?: 'active';
            
            $status_labels = array(
                'active' => __('Beschäftigt', 'rt-employee-manager-v2'),
                'inactive' => __('Beurlaubt', 'rt-employee-manager-v2'),
                'suspended' => __('Suspendiert', 'rt-employee-manager-v2'),
                'terminated' => __('Ausgeschieden', 'rt-employee-manager-v2'),
            );
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($employee->post_title) . '</strong></td>';
            echo '<td>' . esc_html($email) . '</td>';
            echo '<td>' . esc_html($status_labels[$status] ?? $status) . '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($employee->post_date))) . '</td>';
            echo '<td><a href="' . get_edit_post_link($employee->ID) . '">' . __('Bearbeiten', 'rt-employee-manager-v2') . '</a></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        if (count($employees) >= $limit) {
            echo '<p><a href="' . admin_url('edit.php?post_type=angestellte_v2') . '">' . __('Alle Mitarbeiter anzeigen →', 'rt-employee-manager-v2') . '</a></p>';
        }
    }
}