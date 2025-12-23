<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Dashboard - Simplified admin interface for administrators
 */
class RT_Admin_Dashboard_V2 {
    
    public function __construct() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_kunden_form'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Employee Manager V2', 'rt-employee-manager-v2'),
            __('Employee Manager V2', 'rt-employee-manager-v2'),
            'manage_options',
            'rt-employee-manager-v2-admin',
            array($this, 'admin_page'),
            'dashicons-businessman',
            25
        );
        
        add_submenu_page(
            'rt-employee-manager-v2-admin',
            __('Kunden verwalten', 'rt-employee-manager-v2'),
            __('Kunden', 'rt-employee-manager-v2'),
            'manage_options',
            'rt-employee-manager-v2-kunden',
            array($this, 'kunden_page')
        );
        
        add_submenu_page(
            'rt-employee-manager-v2-admin',
            __('Einstellungen', 'rt-employee-manager-v2'),
            __('Einstellungen', 'rt-employee-manager-v2'),
            'manage_options',
            'rt-employee-manager-v2-settings',
            array($this, 'settings_page')
        );
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $total_employees = wp_count_posts('angestellte_v2')->publish;
        $total_clients = count(get_users(array('role' => 'kunden_v2')));
        
        ?>
        <div class="wrap">
            <h1><?php _e('RT Employee Manager V2 - Admin Dashboard', 'rt-employee-manager-v2'); ?></h1>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3 style="margin-top: 0;"><?php _e('Mitarbeiter Gesamt', 'rt-employee-manager-v2'); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; color: #0073aa; margin: 0;">
                        <?php echo esc_html($total_employees); ?>
                    </p>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3 style="margin-top: 0;"><?php _e('Kunden Gesamt', 'rt-employee-manager-v2'); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; color: #46b450; margin: 0;">
                        <?php echo esc_html($total_clients); ?>
                    </p>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3 style="margin-top: 0;"><?php _e('Plugin Version', 'rt-employee-manager-v2'); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; color: #666; margin: 0;">
                        <?php echo esc_html(RT_EMPLOYEE_V2_VERSION); ?>
                    </p>
                </div>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <h3><?php _e('Schnellaktionen', 'rt-employee-manager-v2'); ?></h3>
                <p>
                    <a href="<?php echo admin_url('edit.php?post_type=angestellte_v2'); ?>" class="button button-primary">
                        <?php _e('Alle Mitarbeiter verwalten', 'rt-employee-manager-v2'); ?>
                    </a>
                    <a href="<?php echo admin_url('users.php?role=kunden_v2'); ?>" class="button">
                        <?php _e('Kunden verwalten', 'rt-employee-manager-v2'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=rt-employee-manager-v2-settings'); ?>" class="button">
                        <?php _e('Einstellungen', 'rt-employee-manager-v2'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Kunden management page
     */
    public function kunden_page() {
        $message = '';
        if (isset($_GET['created']) && $_GET['created'] === '1') {
            $message = '<div class="notice notice-success"><p>' . __('Kunde wurde erfolgreich erstellt.', 'rt-employee-manager-v2') . '</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Kunden verwalten', 'rt-employee-manager-v2'); ?></h1>
            
            <?php echo $message; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                <!-- Create New Customer -->
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3><?php _e('Neuen Kunden erstellen', 'rt-employee-manager-v2'); ?></h3>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('create_kunde_v2', 'kunde_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="company_name"><?php _e('Firmenname', 'rt-employee-manager-v2'); ?> *</label></th>
                                <td><input type="text" name="company_name" id="company_name" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th><label for="contact_name"><?php _e('Ansprechpartner', 'rt-employee-manager-v2'); ?> *</label></th>
                                <td><input type="text" name="contact_name" id="contact_name" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th><label for="email"><?php _e('E-Mail', 'rt-employee-manager-v2'); ?> *</label></th>
                                <td><input type="email" name="email" id="email" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th><label for="phone"><?php _e('Telefon', 'rt-employee-manager-v2'); ?></label></th>
                                <td><input type="tel" name="phone" id="phone" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th><label for="uid_number"><?php _e('UID-Nummer', 'rt-employee-manager-v2'); ?></label></th>
                                <td><input type="text" name="uid_number" id="uid_number" class="regular-text" /></td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="create_kunde" class="button-primary" value="<?php _e('Kunde erstellen', 'rt-employee-manager-v2'); ?>" />
                        </p>
                    </form>
                </div>
                
                <!-- Existing Customers -->
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3><?php _e('Vorhandene Kunden', 'rt-employee-manager-v2'); ?></h3>
                    <?php $this->display_existing_kunden(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle kunden form submission
     */
    public function handle_kunden_form() {
        if (!isset($_POST['create_kunde']) || !isset($_POST['kunde_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['kunde_nonce'], 'create_kunde_v2')) {
            wp_die(__('Sicherheitsfehler.', 'rt-employee-manager-v2'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung.', 'rt-employee-manager-v2'));
        }
        
        // Validate required fields
        $required_fields = array('company_name', 'contact_name', 'email');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_die(sprintf(__('Feld "%s" ist erforderlich.', 'rt-employee-manager-v2'), $field));
            }
        }
        
        $company_name = sanitize_text_field($_POST['company_name']);
        $contact_name = sanitize_text_field($_POST['contact_name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $uid_number = sanitize_text_field($_POST['uid_number']);
        
        // Check if email already exists
        if (email_exists($email)) {
            wp_die(__('Diese E-Mail-Adresse ist bereits registriert.', 'rt-employee-manager-v2'));
        }
        
        // Create user account
        $username = sanitize_user($email);
        $password = wp_generate_password(12);
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_die(__('Fehler beim Erstellen des Benutzerkontos: ', 'rt-employee-manager-v2') . $user_id->get_error_message());
        }
        
        // Set user role to kunden_v2
        $user = new WP_User($user_id);
        $user->set_role('kunden_v2');
        
        // Set user meta
        update_user_meta($user_id, 'company_name', $company_name);
        update_user_meta($user_id, 'contact_name', $contact_name);
        update_user_meta($user_id, 'phone', $phone);
        update_user_meta($user_id, 'uid_number', $uid_number);
        update_user_meta($user_id, 'created_by_admin', true);
        update_user_meta($user_id, 'created_at', current_time('mysql'));
        
        // Update user display name
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $company_name,
            'first_name' => $contact_name,
        ));
        
        // Send welcome email with login credentials
        $subject = sprintf(__('Ihr Konto bei %s wurde erstellt', 'rt-employee-manager-v2'), get_bloginfo('name'));
        $message = sprintf(
            __("Hallo %s,\n\nIhr Konto für %s wurde erstellt.\n\nIhre Zugangsdaten:\nBenutzername: %s\nPasswort: %s\n\nSie können sich hier anmelden:\n%s\n\nViele Grüße", 'rt-employee-manager-v2'),
            $contact_name,
            $company_name,
            $email,
            $password,
            wp_login_url()
        );
        
        wp_mail($email, $subject, $message);
        
        // Redirect with success message
        wp_redirect(add_query_arg('created', '1', admin_url('admin.php?page=rt-employee-manager-v2-kunden')));
        exit;
    }
    
    /**
     * Display existing kunden
     */
    private function display_existing_kunden() {
        $kunden = get_users(array('role' => 'kunden_v2'));
        
        if (empty($kunden)) {
            echo '<p>' . __('Noch keine Kunden erstellt.', 'rt-employee-manager-v2') . '</p>';
            return;
        }
        
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Unternehmen', 'rt-employee-manager-v2') . '</th>';
        echo '<th>' . __('Ansprechpartner', 'rt-employee-manager-v2') . '</th>';
        echo '<th>' . __('E-Mail', 'rt-employee-manager-v2') . '</th>';
        echo '<th>' . __('Mitarbeiter', 'rt-employee-manager-v2') . '</th>';
        echo '<th>' . __('Aktionen', 'rt-employee-manager-v2') . '</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($kunden as $kunde) {
            $company_name = get_user_meta($kunde->ID, 'company_name', true);
            $contact_name = get_user_meta($kunde->ID, 'contact_name', true);
            $employee_count = $this->get_employee_count_for_kunde($kunde->ID);
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($company_name) . '</strong></td>';
            echo '<td>' . esc_html($contact_name) . '</td>';
            echo '<td>' . esc_html($kunde->user_email) . '</td>';
            echo '<td>' . esc_html($employee_count) . '</td>';
            echo '<td>';
            echo '<a href="' . get_edit_user_link($kunde->ID) . '">' . __('Bearbeiten', 'rt-employee-manager-v2') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * Get employee count for kunde
     */
    private function get_employee_count_for_kunde($user_id) {
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
        
        return count(get_posts($args));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('rt_employee_v2_settings', 'rt_employee_v2_buchhaltung_email');
        register_setting('rt_employee_v2_settings', 'rt_employee_v2_company_address');
        register_setting('rt_employee_v2_settings', 'rt_employee_v2_pdf_template_header');
        register_setting('rt_employee_v2_settings', 'rt_employee_v2_pdf_template_footer');
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            update_option('rt_employee_v2_buchhaltung_email', sanitize_email($_POST['buchhaltung_email']));
            update_option('rt_employee_v2_company_address', sanitize_textarea_field($_POST['company_address']));
            update_option('rt_employee_v2_pdf_template_header', sanitize_textarea_field($_POST['pdf_template_header']));
            update_option('rt_employee_v2_pdf_template_footer', sanitize_textarea_field($_POST['pdf_template_footer']));
            echo '<div class="notice notice-success"><p>' . __('Einstellungen gespeichert!', 'rt-employee-manager-v2') . '</p></div>';
        }
        
        $buchhaltung_email = get_option('rt_employee_v2_buchhaltung_email', '');
        $company_address = get_option('rt_employee_v2_company_address', '');
        $pdf_header = get_option('rt_employee_v2_pdf_template_header', '');
        $pdf_footer = get_option('rt_employee_v2_pdf_template_footer', '');
        ?>
        <div class="wrap">
            <h1><?php _e('RT Employee Manager V2 - Einstellungen', 'rt-employee-manager-v2'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('rt_employee_v2_settings', 'rt_employee_v2_nonce'); ?>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
                    <h2><?php _e('E-Mail Konfiguration', 'rt-employee-manager-v2'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="buchhaltung_email"><?php _e('Buchhaltung E-Mail-Adresse', 'rt-employee-manager-v2'); ?></label>
                            </th>
                            <td>
                                <input type="email" id="buchhaltung_email" name="buchhaltung_email" 
                                       value="<?php echo esc_attr($buchhaltung_email); ?>" class="regular-text" />
                                <p class="description"><?php _e('E-Mail-Adresse der Buchhaltung für PDF-Versand', 'rt-employee-manager-v2'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
                    <h2><?php _e('Firmendaten', 'rt-employee-manager-v2'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="company_address"><?php _e('Firmenadresse', 'rt-employee-manager-v2'); ?></label>
                            </th>
                            <td>
                                <textarea id="company_address" name="company_address" rows="4" class="large-text"><?php echo esc_textarea($company_address); ?></textarea>
                                <p class="description"><?php _e('Vollständige Firmenadresse für PDF-Header', 'rt-employee-manager-v2'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
                    <h2><?php _e('PDF Template Anpassung', 'rt-employee-manager-v2'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="pdf_template_header"><?php _e('PDF Header Text', 'rt-employee-manager-v2'); ?></label>
                            </th>
                            <td>
                                <textarea id="pdf_template_header" name="pdf_template_header" rows="3" class="large-text"><?php echo esc_textarea($pdf_header); ?></textarea>
                                <p class="description"><?php _e('Zusätzlicher Text für PDF-Header (z.B. "Vertrauliche Mitarbeiterdaten")', 'rt-employee-manager-v2'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="pdf_template_footer"><?php _e('PDF Footer Text', 'rt-employee-manager-v2'); ?></label>
                            </th>
                            <td>
                                <textarea id="pdf_template_footer" name="pdf_template_footer" rows="3" class="large-text"><?php echo esc_textarea($pdf_footer); ?></textarea>
                                <p class="description"><?php _e('Zusätzlicher Text für PDF-Footer (z.B. Datenschutz-Hinweise)', 'rt-employee-manager-v2'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('Einstellungen speichern', 'rt-employee-manager-v2')); ?>
            </form>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-top: 20px;">
                <h2><?php _e('System Information', 'rt-employee-manager-v2'); ?></h2>
                <table class="widefat">
                    <tr>
                        <td><strong><?php _e('Plugin Version', 'rt-employee-manager-v2'); ?></strong></td>
                        <td><?php echo esc_html(RT_EMPLOYEE_V2_VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('WordPress Version', 'rt-employee-manager-v2'); ?></strong></td>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Buchhaltung E-Mail', 'rt-employee-manager-v2'); ?></strong></td>
                        <td><?php echo esc_html($buchhaltung_email ?: 'Nicht konfiguriert'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
}