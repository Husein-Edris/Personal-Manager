<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles Kündigung creation, PDF generation, and email sending
 */
class RT_Kuendigung_Handler_V2 {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_kuendigung_meta_box'));
        add_action('save_post_angestellte_v2', array($this, 'save_kuendigung_on_post_save'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_kuendigung_scripts'));
        add_action('wp_ajax_email_kuendigung_v2', array($this, 'ajax_email_kuendigung'));
    }
    
    /**
     * Add Kündigung button meta box to employee edit screen
     */
    public function add_kuendigung_meta_box() {
        add_meta_box(
            'rt_employee_kuendigung_v2',
            __('Kündigung', 'rt-employee-manager-v2'),
            array($this, 'kuendigung_meta_box_callback'),
            'angestellte_v2',
            'side',
            'default'
        );
    }
    
    /**
     * Meta box callback - shows Kündigung button
     */
    public function kuendigung_meta_box_callback($post) {
        if ($post->post_status !== 'publish') {
            echo '<p>' . __('Speichern Sie den Mitarbeiter zuerst, um eine Kündigung zu erstellen.', 'rt-employee-manager-v2') . '</p>';
            return;
        }
        
        // Check if employee is already terminated (Ausgeschieden)
        $employee_status = get_post_meta($post->ID, 'status', true);
        $is_terminated = ($employee_status === 'terminated');
        
        // Check if employee already has a Kündigung
        $existing_kuendigungen = get_posts(array(
            'post_type' => 'kuendigung_v2',
            'meta_query' => array(
                array(
                    'key' => 'employee_id',
                    'value' => $post->ID,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        $employee_data = $this->get_employee_data($post->ID);
        $employee_email = $employee_data['email'] ?? '';
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('email_kuendigung_v2');
        
        // Check if Kündigung was just created
        $kuendigung_created = isset($_GET['kuendigung_created']) && $_GET['kuendigung_created'] === '1';
        // Check if email was just sent
        $email_sent = isset($_GET['email_sent']) && $_GET['email_sent'] === '1';
        ?>
<div class="rt-kuendigung-actions" style="padding: 10px;">
        <?php if ($kuendigung_created): ?>
        <div class="notice notice-success is-dismissible" style="background: #d4edda; border-left: 4px solid #28a745; padding: 12px; margin-bottom: 15px; border-radius: 4px;">
            <p style="margin: 0; color: #155724; font-weight: bold;">
                <span style="font-size: 18px; margin-right: 8px;">✓</span>
                <?php _e('Kündigung erfolgreich erstellt!', 'rt-employee-manager-v2'); ?>
            </p>
            <p style="margin: 5px 0 0 0; color: #155724; font-size: 13px;">
                <?php _e('Der Beschäftigungsstatus wurde auf "Ausgeschieden" geändert.', 'rt-employee-manager-v2'); ?>
            </p>
        </div>
        <?php endif; ?>
        <?php if ($email_sent): ?>
        <div class="notice notice-success is-dismissible" style="background: #d4edda; border-left: 4px solid #28a745; padding: 12px; margin-bottom: 15px; border-radius: 4px;">
            <p style="margin: 0; color: #155724; font-weight: bold;">
                <span style="font-size: 18px; margin-right: 8px;">✓</span>
                <?php _e('PDF erfolgreich per E-Mail versendet!', 'rt-employee-manager-v2'); ?>
            </p>
        </div>
        <?php endif; ?>
    <style>
    .rt-kuendigung-actions .button {
        width: 100%;
        margin-bottom: 10px;
        text-align: center;
    }

    .rt-kuendigung-actions .button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .kuendigung-item {
        margin-bottom: 20px;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 4px;
        border: 1px solid #ddd;
    }

    .kuendigung-item h4 {
        margin-top: 0;
        margin-bottom: 10px;
    }

    .kuendigung-status {
        font-size: 12px;
        color: #666;
        margin-bottom: 10px;
    }

    .kuendigung-status .sent {
        color: green;
    }

    .email-form {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #ddd;
    }

    .email-form input[type="email"] {
        width: 100%;
        margin-bottom: 8px;
    }

    .email-options label {
        display: block;
        margin-bottom: 5px;
    }
    .field-error {
        color: #dc3232;
        font-size: 12px;
        display: block;
        margin-top: 5px;
    }
    #kuendigung-form-wrapper input.error,
    #kuendigung-form-wrapper select.error,
    #kuendigung-form-wrapper textarea.error {
        border-color: #dc3232 !important;
    }
    #kuendigung-error-summary {
        background: #ffeaea;
        border: 1px solid #dc3232;
        color: #dc3232;
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    #kuendigung-error-summary ul {
        margin: 10px 0 0 20px;
        padding: 0;
    }
    .notice-success {
        animation: slideIn 0.3s ease-out;
    }
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .kuendigung-toggle-header {
        background: #f7f7f7;
        border: 1px solid #ddd;
        border-bottom: none;
        padding: 8px 12px;
        cursor: pointer;
        user-select: none;
        font-weight: 600;
        font-size: 13px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .kuendigung-toggle-header:hover {
        background: #f0f0f0;
    }
    .kuendigung-toggle-icon {
        font-size: 12px;
        transition: transform 0.2s;
    }
    .kuendigung-toggle-header.active .kuendigung-toggle-icon {
        transform: rotate(180deg);
    }
    #kuendigung-form-wrapper {
        border: 1px solid #ddd;
        border-top: none;
        background: #fff;
    }
    #kuendigung-form-wrapper .inside {
        padding: 12px;
        margin: 0;
    }
    #kuendigung-form-wrapper .form-table {
        margin: 0;
    }
    #kuendigung-form-wrapper .form-table th {
        width: 140px;
        padding: 10px 10px 10px 0;
        font-size: 13px;
    }
    #kuendigung-form-wrapper .form-table td {
        padding: 10px 0;
    }
    #kuendigung-form-wrapper input[type="text"],
    #kuendigung-form-wrapper input[type="email"],
    #kuendigung-form-wrapper input[type="date"],
    #kuendigung-form-wrapper input[type="number"],
    #kuendigung-form-wrapper select,
    #kuendigung-form-wrapper textarea {
        width: 100%;
        max-width: 400px;
    }
    #kuendigung-form-wrapper .form-table textarea {
        max-width: 100%;
    }
    </style>

    <?php if (!$is_terminated): ?>
    <!-- Compact Toggle Panel -->
    <div class="kuendigung-toggle-panel">
        <div class="kuendigung-toggle-header" id="toggle-kuendigung-form">
            <span><?php _e('Kündigung erstellen', 'rt-employee-manager-v2'); ?></span>
            <span class="kuendigung-toggle-icon">▼</span>
        </div>
        
        <div id="kuendigung-form-wrapper" style="display: none;">
            <div class="inside">
                <p class="description" style="margin-bottom: 15px; color: #666; font-size: 12px;">
                    <?php _e('Füllen Sie die Felder aus und klicken Sie auf "Aktualisieren" (oben rechts), um die Kündigung zu erstellen und den Status zu ändern.', 'rt-employee-manager-v2'); ?>
                </p>
                <?php wp_nonce_field('save_kuendigung_v2', 'kuendigung_nonce'); ?>
                <table class="form-table">
                        <tr>
                            <th scope="row"><label for="kuendigungsart"><?php _e('Kündigungsart', 'rt-employee-manager-v2'); ?> *</label></th>
                            <td>
                                <select name="kuendigungsart" id="kuendigungsart">
                                    <option value=""><?php _e('Bitte wählen', 'rt-employee-manager-v2'); ?></option>
                                    <option value="Ordentliche"><?php _e('Ordentliche Kündigung', 'rt-employee-manager-v2'); ?></option>
                                    <option value="Fristlose"><?php _e('Fristlose Kündigung', 'rt-employee-manager-v2'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="kuendigungsdatum"><?php _e('Kündigungsdatum', 'rt-employee-manager-v2'); ?> *</label></th>
                            <td><input type="date" name="kuendigungsdatum" id="kuendigungsdatum" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="beendigungsdatum"><?php _e('Beendigungsdatum', 'rt-employee-manager-v2'); ?> *</label></th>
                            <td><input type="date" name="beendigungsdatum" id="beendigungsdatum" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="kuendigungsgrund"><?php _e('Grund der Kündigung', 'rt-employee-manager-v2'); ?> *</label></th>
                            <td><textarea name="kuendigungsgrund" id="kuendigungsgrund" rows="3" class="large-text"></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="kuendigungsfrist"><?php _e('Kündigungsfrist', 'rt-employee-manager-v2'); ?></label></th>
                            <td><input type="text" name="kuendigungsfrist" id="kuendigungsfrist" class="regular-text" placeholder="<?php _e('z.B. 1 Monat zum Monatsende', 'rt-employee-manager-v2'); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="resturlaub"><?php _e('Resturlaub (Tage)', 'rt-employee-manager-v2'); ?></label></th>
                            <td><input type="number" name="resturlaub" id="resturlaub" min="0" step="0.5" style="width: 120px;" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ueberstunden"><?php _e('Überstunden (Stunden)', 'rt-employee-manager-v2'); ?></label></th>
                            <td><input type="number" name="ueberstunden" id="ueberstunden" min="0" step="0.5" style="width: 120px;" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Optionen', 'rt-employee-manager-v2'); ?></th>
                            <td>
                                <label><input type="checkbox" name="zeugnis_gewuenscht" id="zeugnis_gewuenscht" /> <?php _e('Zeugnis gewünscht', 'rt-employee-manager-v2'); ?></label><br>
                                <label><input type="checkbox" name="uebergabe_erledigt" id="uebergabe_erledigt" /> <?php _e('Übergabe erledigt', 'rt-employee-manager-v2'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="notes"><?php _e('Anmerkungen', 'rt-employee-manager-v2'); ?></label></th>
                            <td><textarea name="notes" id="notes" rows="2" class="large-text"></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="kuendigung_email_address"><?php _e('E-Mail für PDF', 'rt-employee-manager-v2'); ?></label></th>
                            <td>
                                <?php $employee_data = $this->get_employee_data($post->ID); $employee_email = $employee_data['email'] ?? ''; ?>
                                <input type="email" name="kuendigung_email_address" id="kuendigung_email_address" placeholder="<?php _e('E-Mail-Adresse eingeben', 'rt-employee-manager-v2'); ?>" value="<?php echo esc_attr($employee_email); ?>" class="regular-text" />
                                <p class="description"><?php _e('Optional: E-Mail-Adresse für automatischen PDF-Versand nach dem Speichern.', 'rt-employee-manager-v2'); ?></p>
                                <?php $buchhaltung_email = get_option('rt_employee_v2_buchhaltung_email', ''); ?>
                                <?php if (!empty($buchhaltung_email)): ?>
                                <p style="margin-top: 8px;">
                                    <label>
                                        <input type="checkbox" name="send_to_bookkeeping_on_create" id="send_to_bookkeeping_on_create" />
                                        <?php _e('Auch an Buchhaltung senden', 'rt-employee-manager-v2'); ?>
                                        <strong>(<?php echo esc_html($buchhaltung_email); ?>)</strong>
                                    </label>
                                </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
    <!-- Employee is terminated - show email options -->
    <div style="padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404; margin-bottom: 15px;">
        <strong><?php _e('Mitarbeiter bereits ausgeschieden', 'rt-employee-manager-v2'); ?></strong>
    </div>
    
    <?php 
    // Get the most recent Kündigung for this employee
    $latest_kuendigung = get_posts(array(
        'post_type' => 'kuendigung_v2',
        'meta_query' => array(
            array(
                'key' => 'employee_id',
                'value' => $post->ID,
                'compare' => '='
            )
        ),
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'post_status' => 'any'
    ));
    
    if (!empty($latest_kuendigung)):
        $kuendigung = $latest_kuendigung[0];
        $email_sent = get_post_meta($kuendigung->ID, 'email_sent', true);
    ?>
    <div style="padding: 12px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
        <h4 style="margin-top: 0; font-size: 13px;"><?php _e('PDF per E-Mail versenden', 'rt-employee-manager-v2'); ?></h4>
        <?php if ($email_sent === '1'): ?>
        <p style="color: green; font-size: 12px; margin-bottom: 10px;">
            ✓ <?php _e('PDF bereits versendet', 'rt-employee-manager-v2'); ?>
        </p>
        <?php endif; ?>
        
        <p style="margin-bottom: 10px;">
            <input type="email" id="kuendigung-email-address-send" class="regular-text" 
                placeholder="<?php _e('E-Mail-Adresse eingeben', 'rt-employee-manager-v2'); ?>"
                value="<?php echo esc_attr($employee_email); ?>" style="width: 100%;" />
        </p>
        
        <?php $buchhaltung_email = get_option('rt_employee_v2_buchhaltung_email', ''); ?>
        <?php if (!empty($buchhaltung_email)): ?>
        <p style="margin-bottom: 10px;">
            <label>
                <input type="checkbox" id="send-to-bookkeeping-send" />
                <?php _e('Auch an Buchhaltung senden', 'rt-employee-manager-v2'); ?>
                <strong>(<?php echo esc_html($buchhaltung_email); ?>)</strong>
            </label>
        </p>
        <?php endif; ?>
        
        <p>
            <button type="button" class="button button-primary send-kuendigung-email" 
                data-kuendigung-id="<?php echo esc_attr($kuendigung->ID); ?>"
                data-employee-id="<?php echo esc_attr($post->ID); ?>" style="width: 100%;">
                <?php _e('PDF versenden', 'rt-employee-manager-v2'); ?>
            </button>
        </p>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($existing_kuendigungen)): ?>
    <div style="margin-top: 15px;">
        <strong><?php _e('Kündigungen:', 'rt-employee-manager-v2'); ?></strong>
        <?php foreach ($existing_kuendigungen as $kuendigung): 
                        $kuendigungsdatum = get_post_meta($kuendigung->ID, 'kuendigungsdatum', true);
                        $beendigungsdatum = get_post_meta($kuendigung->ID, 'beendigungsdatum', true);
                        $email_sent = get_post_meta($kuendigung->ID, 'email_sent', true);
                        $email_sent_date = get_post_meta($kuendigung->ID, 'email_sent_date', true);
                        $email_recipients = get_post_meta($kuendigung->ID, 'email_recipients', true);
                    ?>
        <div class="kuendigung-item" data-kuendigung-id="<?php echo esc_attr($kuendigung->ID); ?>">
            <h4>
                <?php echo esc_html($kuendigung->post_title); ?>
            </h4>

            <div class="kuendigung-status">
                <?php if ($kuendigungsdatum): ?>
                <strong><?php _e('Kündigungsdatum:', 'rt-employee-manager-v2'); ?></strong>
                <?php echo date_i18n('d.m.Y', strtotime($kuendigungsdatum)); ?><br>
                <?php endif; ?>
                <?php if ($beendigungsdatum): ?>
                <strong><?php _e('Beendigungsdatum:', 'rt-employee-manager-v2'); ?></strong>
                <?php echo date_i18n('d.m.Y', strtotime($beendigungsdatum)); ?><br>
                <?php endif; ?>
                <?php if ($email_sent === '1'): ?>
                <span class="sent">✓ <?php _e('PDF versendet', 'rt-employee-manager-v2'); ?></span>
                <?php if ($email_sent_date): ?>
                <br><small><?php printf(__('Am: %s', 'rt-employee-manager-v2'), date_i18n(get_option('date_format') . ' H:i', strtotime($email_sent_date))); ?></small>
                <?php endif; ?>
                <?php if ($email_recipients): ?>
                <br><small><?php printf(__('An: %s', 'rt-employee-manager-v2'), esc_html($email_recipients)); ?></small>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Email Form -->
            <div class="email-form">
                <h4 style="margin-top: 0; font-size: 13px;">
                    <?php _e('PDF per E-Mail versenden', 'rt-employee-manager-v2'); ?></h4>

                <p style="margin-bottom: 15px;">
                    <input type="email" class="kuendigung-email-input"
                        placeholder="<?php _e('E-Mail-Adresse eingeben', 'rt-employee-manager-v2'); ?>"
                        value="<?php echo esc_attr($employee_email); ?>" style="width: 100%;" />
                </p>

                <div class="email-options" style="margin-bottom: 15px;">
                    <p><label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" class="send-to-employee" checked />
                            <?php _e('An Mitarbeiter senden', 'rt-employee-manager-v2'); ?>
                            <?php if ($employee_email): ?>
                            <strong>(<?php echo esc_html($employee_email); ?>)</strong>
                            <?php endif; ?>
                        </label></p>

                    <?php 
                                    $buchhaltung_email = get_option('rt_employee_v2_buchhaltung_email', '');
                                    if (!empty($buchhaltung_email)): 
                                    ?>
                    <p><label style="display: block;">
                            <input type="checkbox" class="send-to-bookkeeping" />
                            <?php _e('Auch an Buchhaltung senden', 'rt-employee-manager-v2'); ?>
                            <strong>(<?php echo esc_html($buchhaltung_email); ?>)</strong>
                        </label></p>
                    <?php endif; ?>
                </div>

                <p>
                    <button type="button" class="button button-primary send-kuendigung-email"
                        data-kuendigung-id="<?php echo esc_attr($kuendigung->ID); ?>"
                        data-employee-id="<?php echo esc_attr($post->ID); ?>" style="width: 100%;">
                        <?php _e('PDF versenden', 'rt-employee-manager-v2'); ?>
                    </button>
                </p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php
    }
    
    /**
     * Save Kündigung when employee post is saved
     */
    public function save_kuendigung_on_post_save($post_id) {
        // Check autosave, revisions, and permissions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check if Kündigung form was submitted
        if (!isset($_POST['kuendigung_nonce']) || !wp_verify_nonce($_POST['kuendigung_nonce'], 'save_kuendigung_v2')) {
            return;
        }
        
        // Check if Kündigung fields are present (form was filled)
        if (empty($_POST['kuendigungsart']) || empty($_POST['kuendigungsdatum']) || empty($_POST['beendigungsdatum'])) {
            return;
        }
        
        // Check if employee is already terminated
        $current_status = get_post_meta($post_id, 'status', true);
        if ($current_status === 'terminated') {
            return; // Don't create duplicate Kündigung
        }
        
        // Get employee data (needed for employer info and title)
        $employee = get_post($post_id);
        $employee_data = $this->get_employee_data($post_id);
        
        // Check if employee has an employer assigned
        if (empty($employee_data['employer_name']) || empty($employee_data['employer_email'])) {
            return; // Employee must have an employer assigned
        }
        
        // Validate required fields (employer_name and employer_email come from DB, not form)
        $required = array('kuendigungsart', 'kuendigungsdatum', 'beendigungsdatum', 'kuendigungsgrund');
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                return; // Missing required field
            }
        }
        
        // Date validation
        $kuendigungsdatum = strtotime($_POST['kuendigungsdatum']);
        $beendigungsdatum = strtotime($_POST['beendigungsdatum']);
        if ($beendigungsdatum < $kuendigungsdatum) {
            return; // Invalid date
        }
        $employee_name = trim(($employee_data['vorname'] ?? '') . ' ' . ($employee_data['nachname'] ?? ''));
        if (empty($employee_name)) {
            $employee_name = $employee->post_title;
        }
        
        // Create Kündigung post
        $user = wp_get_current_user();
        $kuendigung_id = wp_insert_post(array(
            'post_type' => 'kuendigung_v2',
            'post_title' => sprintf(__('Kündigung: %s - %s', 'rt-employee-manager-v2'), $employee_name, date_i18n('d.m.Y', $kuendigungsdatum)),
            'post_status' => 'publish',
            'post_author' => $user->ID
        ));
        
        if (is_wp_error($kuendigung_id)) {
            return;
        }
        
        // Save all meta fields (employer_name and employer_email come from employee's customer data)
        $meta_fields = array(
            'employee_id' => $post_id,
            'kuendigungsart' => sanitize_text_field($_POST['kuendigungsart']),
            'kuendigungsdatum' => sanitize_text_field($_POST['kuendigungsdatum']),
            'beendigungsdatum' => sanitize_text_field($_POST['beendigungsdatum']),
            'kuendigungsgrund' => sanitize_textarea_field($_POST['kuendigungsgrund']),
            'employer_name' => $employee_data['employer_name'], // From employee's customer DB
            'employer_email' => $employee_data['employer_email'], // From employee's customer DB
            'kuendigungsfrist' => sanitize_text_field($_POST['kuendigungsfrist'] ?? ''),
            'resturlaub' => isset($_POST['resturlaub']) ? floatval($_POST['resturlaub']) : 0,
            'ueberstunden' => isset($_POST['ueberstunden']) ? floatval($_POST['ueberstunden']) : 0,
            'zeugnis_gewuenscht' => !empty($_POST['zeugnis_gewuenscht']) ? '1' : '0',
            'uebergabe_erledigt' => !empty($_POST['uebergabe_erledigt']) ? '1' : '0',
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        foreach ($meta_fields as $key => $value) {
            update_post_meta($kuendigung_id, $key, $value);
        }
        
        // Update employee status to terminated
        update_post_meta($post_id, 'status', 'terminated');
        
        // Send email if address provided
        if (!empty($_POST['kuendigung_email_address'])) {
            $email_address = sanitize_email($_POST['kuendigung_email_address']);
            $send_to_bookkeeping = !empty($_POST['send_to_bookkeeping_on_create']);
            
            $pdf_generator = new RT_Kuendigung_PDF_Generator_V2();
            $result = $pdf_generator->send_kuendigung_email_manual($kuendigung_id, $post_id, $email_address, true, $send_to_bookkeeping);
            
            if ($result['success']) {
                // Email sent successfully - meta already updated by send_kuendigung_email_manual
            }
        }
        
        // Set redirect parameter for success message
        add_filter('redirect_post_location', function($location) {
            return add_query_arg('kuendigung_created', '1', $location);
        });
    }
    
    /**
     * Enqueue scripts for Kündigung toggle
     */
    public function enqueue_kuendigung_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        global $post_type;
        if ($post_type !== 'angestellte_v2') {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('email_kuendigung_v2');
        
        // Localize script data - attach to jquery
        wp_localize_script('jquery', 'rtKuendigungV2', array(
            'ajaxurl' => $ajax_url,
            'nonce' => $nonce
        ));
        
        // Add inline script attached to jquery
        $js_code = <<<'JS'
            jQuery(document).ready(function($) {
                // Ensure rtKuendigungV2 is available
                if (typeof rtKuendigungV2 === "undefined") {
                    console.error("rtKuendigungV2 not defined");
                    return;
                }
                
                // Auto-dismiss success message after 5 seconds
                $(".notice-success").each(function() {
                    var $notice = $(this);
                    setTimeout(function() {
                        $notice.fadeOut(300, function() {
                            $notice.remove();
                        });
                    }, 5000);
                });
                
                function isValidEmail(email) {
                    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                }

                function showFieldError(fieldId, message) {
                    var $field = $("#" + fieldId);
                    $field.css("border-color", "#dc3232");
                    var $error = $field.closest("td, .form-field").find(".field-error");
                    if ($error.length === 0) {
                        $field.after("<span class=\"field-error\" style=\"color: #dc3232; font-size: 12px; display: block; margin-top: 5px;\">" + message + "</span>");
                    } else {
                        $error.text(message);
                    }
                }

                function clearFieldError(fieldId) {
                    var $field = $("#" + fieldId);
                    $field.css("border-color", "");
                    $field.closest("td, .form-field").find(".field-error").remove();
                }

                function validateForm() {
                    var isValid = true;
                    var errors = [];

                    // Clear all previous errors
                    $(".field-error").remove();
                    $("input, select, textarea").css("border-color", "");

                    // Required fields (employer_name and employer_email are auto-filled from DB)
                    var requiredFields = {
                        kuendigungsart: "Kündigungsart",
                        kuendigungsdatum: "Kündigungsdatum",
                        beendigungsdatum: "Beendigungsdatum",
                        kuendigungsgrund: "Grund der Kündigung"
                    };

                    for (var fieldId in requiredFields) {
                        var $field = $("#" + fieldId);
                        var value = $field.val();
                        if (fieldId === "kuendigungsgrund") {
                            value = value.trim();
                        }
                        if (!value) {
                            showFieldError(fieldId, "Pflichtfeld");
                            isValid = false;
                            errors.push("Bitte geben Sie " + requiredFields[fieldId] + " ein.");
                        } else {
                            clearFieldError(fieldId);
                        }
                    }

                    // Date validation
                    var kuendigungsdatum = $("#kuendigungsdatum").val();
                    var beendigungsdatum = $("#beendigungsdatum").val();
                    if (kuendigungsdatum && beendigungsdatum && beendigungsdatum < kuendigungsdatum) {
                        showFieldError("beendigungsdatum", "Beendigungsdatum muss nach oder gleich Kündigungsdatum sein");
                        isValid = false;
                        errors.push("Beendigungsdatum darf nicht vor Kündigungsdatum liegen.");
                    }

                    // PDF email validation (optional)
                    var emailAddress = $("#kuendigung_email_address").val().trim();
                    if (emailAddress && !isValidEmail(emailAddress)) {
                        showFieldError("kuendigung_email_address", "Ungültige E-Mail-Adresse");
                        isValid = false;
                        errors.push("Bitte geben Sie eine gültige E-Mail-Adresse für den PDF-Versand ein.");
                    } else {
                        clearFieldError("kuendigung_email_address");
                    }

                    // Show summary if errors exist
                    var $errorSummary = $("#kuendigung-error-summary");
                    if (!isValid) {
                        if ($errorSummary.length === 0) {
                            $("#kuendigung-form-wrapper").prepend("<div id=\"kuendigung-error-summary\" style=\"background: #ffeaea; border: 1px solid #dc3232; color: #dc3232; padding: 10px; margin-bottom: 20px; border-radius: 4px;\"><strong>Bitte korrigieren Sie die folgenden Fehler:</strong><ul style=\"margin: 10px 0 0 20px; padding: 0;\"></ul></div>");
                        }
                        var $errorList = $("#kuendigung-error-summary ul");
                        $errorList.empty();
                        errors.forEach(function(error) {
                            $errorList.append("<li>" + error + "</li>");
                        });
                        $("#kuendigung-error-summary").show();
                        // Scroll to top of form
                        $("html, body").animate({ scrollTop: $("#kuendigung-form-wrapper").offset().top - 50 }, 300);
                    } else {
                        $("#kuendigung-error-summary").hide();
                    }

                    return isValid;
                }

                // Validate before WordPress save button submits
                $(document).on("click", "#publish, #save-post", function(e) {
                    // Only validate if form is visible and has data
                    if ($("#kuendigung-form-wrapper").is(":visible")) {
                        var hasData = false;
                        $("#kuendigung-form-wrapper input, #kuendigung-form-wrapper select, #kuendigung-form-wrapper textarea").each(function() {
                            if ($(this).val() && $(this).attr("id") !== "kuendigung_email_address") {
                                hasData = true;
                                return false;
                            }
                        });
                        
                        if (hasData && !validateForm()) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            alert("Bitte korrigieren Sie die Fehler im Kündigungsformular vor dem Speichern.");
                            return false;
                        }
                    }
                });

                // Toggle form visibility
                $(document).on("click", "#toggle-kuendigung-form", function(e) {
                    e.preventDefault();
                    var $header = $(this);
                    var $wrapper = $("#kuendigung-form-wrapper");
                    var $icon = $header.find(".kuendigung-toggle-icon");
                    
                    if ($wrapper.is(":visible")) {
                        $wrapper.slideUp(200);
                        $header.removeClass("active");
                        $icon.text("▼");
                        // Clear form and errors
                        $("#kuendigung-form-wrapper input, #kuendigung-form-wrapper select, #kuendigung-form-wrapper textarea").val("");
                        $(".field-error").remove();
                        $("#kuendigung-error-summary").hide();
                        $("input, select, textarea").css("border-color", "");
                    } else {
                        $wrapper.slideDown(200);
                        $header.addClass("active");
                        $icon.text("▲");
                        // Clear any previous errors
                        $(".field-error").remove();
                        $("#kuendigung-error-summary").hide();
                        $("input, select, textarea").css("border-color", "");
                    }
                });

                    // Email sending for terminated employees and existing Kündigungen
                    $(document).on("click", ".send-kuendigung-email", function(e) {
                        e.preventDefault();
                        var button = $(this);
                        var item = button.closest(".kuendigung-item, .rt-kuendigung-actions");
                        
                        // Check which form we're using
                        var emailField = item.find("#kuendigung-email-address-send");
                        var isTerminatedSection = emailField.length > 0;
                        
                        var email = isTerminatedSection
                            ? emailField.val().trim()
                            : item.find(".kuendigung-email-input").val().trim();
                        
                        // For terminated section: if email provided, send to employee; checkbox is for bookkeeping only
                        // For existing Kündigungen: use checkboxes
                        var toEmployee = isTerminatedSection 
                            ? (email.length > 0)  // If email provided, send to employee
                            : item.find(".send-to-employee").is(":checked");
                        
                        var toBookkeeping = item.find("#send-to-bookkeeping-send").is(":checked") 
                            || item.find(".send-to-bookkeeping").is(":checked");
                        
                        if (!toEmployee && !toBookkeeping) {
                            alert("Bitte wählen Sie mindestens einen Empfänger aus.");
                            return;
                        }
                        
                        if (toEmployee && !email) {
                            alert("Bitte geben Sie eine E-Mail-Adresse ein.");
                            return;
                        }

                        var originalText = button.text();
                        button.prop("disabled", true).text("Versende PDF...");

                        $.ajax({
                            url: rtKuendigungV2.ajaxurl,
                            type: "POST",
                            data: {
                                action: "email_kuendigung_v2",
                                kuendigung_id: button.data("kuendigung-id"),
                                employee_id: button.data("employee-id"),
                                employee_email: email,
                                send_to_employee: toEmployee ? "1" : "",
                                send_to_bookkeeping: toBookkeeping ? "1" : "",
                                nonce: rtKuendigungV2.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Reload with success parameter
                                    var url = new URL(window.location.href);
                                    url.searchParams.set("email_sent", "1");
                                    window.location.href = url.toString();
                                } else {
                                    alert("Fehler: " + response.data);
                                    button.prop("disabled", false).text(originalText);
                                }
                            },
                            error: function() { 
                                alert("Fehler beim Versenden der E-Mail");
                                button.prop("disabled", false).text(originalText);
                            }
                        });
                    });
                });
JS;
        wp_add_inline_script('jquery', $js_code);
    }
    
    
    /**
     * Get employee data
     */
    private function get_employee_data($employee_id) {
        $fields = array(
            'vorname', 'nachname', 'email', 'anrede', 'sozialversicherungsnummer',
            'geburtsdatum', 'adresse_strasse', 'adresse_plz', 'adresse_ort',
            'eintrittsdatum', 'art_des_dienstverhaltnisses'
        );
        
        $data = array();
        foreach ($fields as $field) {
            $data[$field] = get_post_meta($employee_id, $field, true);
        }
        
        // Get employer info
        $employer_id = get_post_meta($employee_id, 'employer_id', true);
        if ($employer_id) {
            $employer = get_user_by('id', $employer_id);
            if ($employer) {
                $data['employer_name'] = get_user_meta($employer_id, 'company_name', true) ?: $employer->display_name;
                $data['employer_email'] = $employer->user_email;
            }
        }
        
        return $data;
    }
    
    /**
     * AJAX handler to send Kündigung email
     */
    public function ajax_email_kuendigung() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'email_kuendigung_v2')) {
            wp_send_json_error('Security error');
        }
        
        $kuendigung_id = intval($_POST['kuendigung_id'] ?? 0);
        $employee_id = intval($_POST['employee_id'] ?? 0);
        
        if (!$kuendigung_id || !$employee_id) {
            wp_send_json_error('Invalid Kündigung or Employee ID');
        }
        
        // Permission check
        $user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_customer = in_array('kunden_v2', $user->roles);
        
        if (!$is_admin && !$is_customer) {
            wp_send_json_error('No permission');
        }
        
        $kuendigung = get_post($kuendigung_id);
        if (!$kuendigung || $kuendigung->post_type !== 'kuendigung_v2') {
            wp_send_json_error('Invalid Kündigung');
        }
        
        // Check ownership
        if (!$is_admin) {
            $employer_id = get_post_meta($employee_id, 'employer_id', true);
            if ($employer_id != $user->ID) {
                wp_send_json_error('No permission for this employee');
            }
        }
        
        // Get email options
        $employee_email = sanitize_email($_POST['employee_email'] ?? '');
        $send_to_employee = !empty($_POST['send_to_employee']);
        $send_to_bookkeeping = !empty($_POST['send_to_bookkeeping']);
        
        if (!$send_to_employee && !$send_to_bookkeeping) {
            wp_send_json_error('Bitte wählen Sie mindestens einen Empfänger aus');
        }
        
        if ($send_to_employee && empty($employee_email)) {
            wp_send_json_error('Bitte geben Sie die Mitarbeiter-E-Mail-Adresse ein');
        }
        
        // Generate PDF and send email
        $pdf_generator = new RT_Kuendigung_PDF_Generator_V2();
        $result = $pdf_generator->send_kuendigung_email_manual($kuendigung_id, $employee_id, $employee_email, $send_to_employee, $send_to_bookkeeping);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Kündigung PDF erfolgreich per E-Mail versendet.', 'rt-employee-manager-v2')
            ));
        } else {
            wp_send_json_error($result['error'] ?? __('Fehler beim Versenden der E-Mail', 'rt-employee-manager-v2'));
        }
    }
}