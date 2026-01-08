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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_kuendigung_scripts'));
        add_action('wp_ajax_create_kuendigung_v2', array($this, 'ajax_create_kuendigung'));
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
        ?>
        <div class="rt-kuendigung-actions" style="padding: 10px;">
            <style>
            .rt-kuendigung-actions .button {
                width: 100%;
                margin-bottom: 10px;
                text-align: center;
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
            </style>
            
            <button type="button" id="create-kuendigung-btn" class="button button-primary" style="width: 100%;">
                <?php _e('Mitarbeiter kündigen', 'rt-employee-manager-v2'); ?>
            </button>
            
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
                                <a href="<?php echo admin_url('post.php?post=' . $kuendigung->ID . '&action=edit'); ?>">
                                    <?php echo esc_html($kuendigung->post_title); ?>
                                </a>
                            </h4>
                            
                            <div class="kuendigung-status">
                                <?php if ($kuendigungsdatum): ?>
                                    <strong><?php _e('Kündigungsdatum:', 'rt-employee-manager-v2'); ?></strong> <?php echo date_i18n('d.m.Y', strtotime($kuendigungsdatum)); ?><br>
                                <?php endif; ?>
                                <?php if ($beendigungsdatum): ?>
                                    <strong><?php _e('Beendigungsdatum:', 'rt-employee-manager-v2'); ?></strong> <?php echo date_i18n('d.m.Y', strtotime($beendigungsdatum)); ?><br>
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
                                <h4 style="margin-top: 0; font-size: 13px;"><?php _e('PDF per E-Mail versenden', 'rt-employee-manager-v2'); ?></h4>
                                
                                <p style="margin-bottom: 15px;">
                                    <input type="email" class="kuendigung-email-input" 
                                        placeholder="<?php _e('E-Mail-Adresse eingeben', 'rt-employee-manager-v2'); ?>" 
                                        value="<?php echo esc_attr($employee_email); ?>"
                                        style="width: 100%;" />
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
                                        <?php _e('An Buchhaltung senden', 'rt-employee-manager-v2'); ?>
                                        <strong>(<?php echo esc_html($buchhaltung_email); ?>)</strong>
                                    </label></p>
                                    <?php endif; ?>
                                </div>
                                
                                <p>
                                    <button type="button" class="button button-primary send-kuendigung-email" 
                                        data-kuendigung-id="<?php echo esc_attr($kuendigung->ID); ?>"
                                        data-employee-id="<?php echo esc_attr($post->ID); ?>"
                                        style="width: 100%;">
                                        <?php _e('PDF versenden', 'rt-employee-manager-v2'); ?>
                                    </button>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Kündigung Modal -->
        <div id="kuendigung-modal" style="display: none;">
            <div class="kuendigung-modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 100000; display: flex; align-items: center; justify-content: center;">
                <div class="kuendigung-modal-content" style="background: #fff; padding: 30px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto; border-radius: 4px; position: relative;">
                    <button type="button" class="kuendigung-modal-close" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                    <h2 style="margin-top: 0;"><?php _e('Kündigung erstellen', 'rt-employee-manager-v2'); ?></h2>
                    <form id="kuendigung-form">
                        <input type="hidden" id="kuendigung-employee-id" name="employee_id" value="<?php echo esc_attr($post->ID); ?>" />
                        <?php wp_nonce_field('create_kuendigung_v2', 'kuendigung_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="kuendigungsart"><?php _e('Kündigungsart', 'rt-employee-manager-v2'); ?> *</label></th>
                                <td>
                                    <select name="kuendigungsart" id="kuendigungsart" required>
                                        <option value=""><?php _e('Bitte wählen', 'rt-employee-manager-v2'); ?></option>
                                        <option value="Ordentliche"><?php _e('Ordentliche Kündigung', 'rt-employee-manager-v2'); ?></option>
                                        <option value="Fristlose"><?php _e('Fristlose Kündigung', 'rt-employee-manager-v2'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="kuendigungsdatum"><?php _e('Kündigungsdatum', 'rt-employee-manager-v2'); ?> *</label></th>
                                <td><input type="date" name="kuendigungsdatum" id="kuendigungsdatum" required /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="beendigungsdatum"><?php _e('Beendigungsdatum (letzter Arbeitstag)', 'rt-employee-manager-v2'); ?> *</label></th>
                                <td><input type="date" name="beendigungsdatum" id="beendigungsdatum" required /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="kuendigungsgrund"><?php _e('Grund der Kündigung', 'rt-employee-manager-v2'); ?> *</label></th>
                                <td><textarea name="kuendigungsgrund" id="kuendigungsgrund" rows="4" class="large-text" required></textarea></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="kuendigungsfrist"><?php _e('Kündigungsfrist', 'rt-employee-manager-v2'); ?></label></th>
                                <td><input type="text" name="kuendigungsfrist" id="kuendigungsfrist" class="regular-text" placeholder="<?php _e('z.B. 1 Monat zum Monatsende', 'rt-employee-manager-v2'); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="resturlaub"><?php _e('Resturlaub (Tage)', 'rt-employee-manager-v2'); ?></label></th>
                                <td><input type="number" name="resturlaub" id="resturlaub" min="0" step="0.5" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ueberstunden"><?php _e('Überstunden (Stunden)', 'rt-employee-manager-v2'); ?></label></th>
                                <td><input type="number" name="ueberstunden" id="ueberstunden" min="0" step="0.5" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="employer_name"><?php _e('Aussteller (Firma/Kunde)', 'rt-employee-manager-v2'); ?> *</label></th>
                                <td>
                                    <?php
                                    $current_user = wp_get_current_user();
                                    $employer_name = get_user_meta($current_user->ID, 'company_name', true) ?: $current_user->display_name;
                                    ?>
                                    <input type="text" name="employer_name" id="employer_name" value="<?php echo esc_attr($employer_name); ?>" class="regular-text" required />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="employer_email"><?php _e('Aussteller E-Mail', 'rt-employee-manager-v2'); ?> *</label></th>
                                <td>
                                    <?php
                                    $employer_email = $current_user->user_email;
                                    ?>
                                    <input type="email" name="employer_email" id="employer_email" value="<?php echo esc_attr($employer_email); ?>" class="regular-text" required />
                                </td>
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
                                <td><textarea name="notes" id="notes" rows="3" class="large-text"></textarea></td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Kündigung erstellen und PDF versenden', 'rt-employee-manager-v2'); ?></button>
                            <button type="button" class="button kuendigung-modal-close"><?php _e('Abbrechen', 'rt-employee-manager-v2'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue scripts for Kündigung modal
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
        wp_localize_script('jquery', 'rtKuendigungV2', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('email_kuendigung_v2')
        ));
        
        wp_add_inline_script('jquery', '
            (function($) {
                $(document).ready(function() {
                    // Open modal
                    $("#create-kuendigung-btn").on("click", function() {
                        $("#kuendigung-modal").show();
                    });
                    
                    // Close modal
                    $(".kuendigung-modal-close, .kuendigung-modal-overlay").on("click", function(e) {
                        if (e.target === this) {
                            $("#kuendigung-modal").hide();
                        }
                    });
                    
                    // Submit form
                    $("#kuendigung-form").on("submit", function(e) {
                        e.preventDefault();
                        
                        var formData = $(this).serialize();
                        var submitBtn = $(this).find("button[type=submit]");
                        var originalText = submitBtn.text();
                        
                        submitBtn.prop("disabled", true).text("Erstelle Kündigung...");
                        
                        $.ajax({
                            url: rtKuendigungV2.ajaxurl,
                            type: "POST",
                            data: formData + "&action=create_kuendigung_v2",
                            success: function(response) {
                                if (response.success) {
                                    alert("✓ " + response.data.message);
                                    location.reload();
                                } else {
                                    alert("Fehler: " + response.data);
                                }
                            },
                            error: function() {
                                alert("Fehler beim Erstellen der Kündigung");
                            },
                            complete: function() {
                                submitBtn.prop("disabled", false).text(originalText);
                            }
                        });
                    });
                    
                    // Email sending
                    $(document).on("click", ".send-kuendigung-email", function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        var button = $(this);
                        var kuendigungId = button.data("kuendigung-id");
                        var employeeId = button.data("employee-id");
                        var kuendigungItem = button.closest(".kuendigung-item");
                        var emailInput = kuendigungItem.find(".kuendigung-email-input");
                        var sendToEmployee = kuendigungItem.find(".send-to-employee");
                        var sendToBookkeeping = kuendigungItem.find(".send-to-bookkeeping");
                        
                        var employeeEmail = emailInput.val().trim();
                        var shouldSendToEmployee = sendToEmployee.is(":checked");
                        var shouldSendToBookkeeping = sendToBookkeeping.is(":checked");
                        
                        // Validation
                        if (!shouldSendToEmployee && !shouldSendToBookkeeping) {
                            alert("Bitte wählen Sie mindestens einen Empfänger aus.");
                            return;
                        }
                        
                        if (shouldSendToEmployee && !employeeEmail) {
                            alert("Bitte geben Sie die Mitarbeiter-E-Mail-Adresse ein.");
                            emailInput.focus();
                            return;
                        }
                        
                        var originalText = button.text();
                        button.prop("disabled", true).text("Versende PDF...");
                        
                        $.ajax({
                            url: rtKuendigungV2.ajaxurl,
                            type: "POST",
                            data: {
                                action: "email_kuendigung_v2",
                                kuendigung_id: kuendigungId,
                                employee_id: employeeId,
                                employee_email: employeeEmail,
                                send_to_employee: shouldSendToEmployee ? "1" : "",
                                send_to_bookkeeping: shouldSendToBookkeeping ? "1" : "",
                                nonce: rtKuendigungV2.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert("✓ " + response.data.message);
                                    location.reload();
                                } else {
                                    alert("Fehler: " + response.data);
                                }
                            },
                            error: function() {
                                alert("Fehler beim Versenden der E-Mail");
                            },
                            complete: function() {
                                button.prop("disabled", false).text(originalText);
                            }
                        });
                    });
                });
            })(jQuery);
        ');
    }
    
    /**
     * AJAX handler to create Kündigung
     */
    public function ajax_create_kuendigung() {
        if (!isset($_POST['kuendigung_nonce']) || !wp_verify_nonce($_POST['kuendigung_nonce'], 'create_kuendigung_v2')) {
            wp_send_json_error('Security error');
        }
        
        $employee_id = intval($_POST['employee_id']);
        if (!$employee_id) {
            wp_send_json_error('Invalid employee ID');
        }
        
        // Permission check
        $employee = get_post($employee_id);
        if (!$employee || $employee->post_type !== 'angestellte_v2') {
            wp_send_json_error('Invalid employee');
        }
        
        $user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_customer = in_array('kunden_v2', $user->roles);
        
        if (!$is_admin && !$is_customer) {
            wp_send_json_error('No permission');
        }
        
        if (!$is_admin) {
            $employer_id = get_post_meta($employee_id, 'employer_id', true);
            if ($employer_id != $user->ID) {
                wp_send_json_error('No permission for this employee');
            }
        }
        
        // Collect and validate form data
        $kuendigungsart = sanitize_text_field($_POST['kuendigungsart'] ?? '');
        $kuendigungsdatum = sanitize_text_field($_POST['kuendigungsdatum'] ?? '');
        $beendigungsdatum = sanitize_text_field($_POST['beendigungsdatum'] ?? '');
        $kuendigungsgrund = sanitize_textarea_field($_POST['kuendigungsgrund'] ?? '');
        $employer_name = sanitize_text_field($_POST['employer_name'] ?? '');
        $employer_email = sanitize_email($_POST['employer_email'] ?? '');
        
        // Required fields validation
        if (empty($kuendigungsart) || empty($kuendigungsdatum) || empty($beendigungsdatum) || empty($kuendigungsgrund) || empty($employer_name) || empty($employer_email)) {
            wp_send_json_error('Bitte füllen Sie alle Pflichtfelder aus');
        }
        
        // Optional fields
        $kuendigungsfrist = sanitize_text_field($_POST['kuendigungsfrist'] ?? '');
        $resturlaub = isset($_POST['resturlaub']) ? floatval($_POST['resturlaub']) : 0;
        $ueberstunden = isset($_POST['ueberstunden']) ? floatval($_POST['ueberstunden']) : 0;
        $zeugnis_gewuenscht = !empty($_POST['zeugnis_gewuenscht']);
        $uebergabe_erledigt = !empty($_POST['uebergabe_erledigt']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        // Get employee data for title
        $employee_data = $this->get_employee_data($employee_id);
        $employee_name = trim(($employee_data['vorname'] ?? '') . ' ' . ($employee_data['nachname'] ?? ''));
        if (empty($employee_name)) {
            $employee_name = $employee->post_title;
        }
        
        // Create Kündigung post
        $kuendigung_title = sprintf(__('Kündigung: %s - %s', 'rt-employee-manager-v2'), $employee_name, date_i18n('d.m.Y', strtotime($kuendigungsdatum)));
        
        $kuendigung_id = wp_insert_post(array(
            'post_type' => 'kuendigung_v2',
            'post_title' => $kuendigung_title,
            'post_status' => 'publish',
            'post_author' => $user->ID
        ));
        
        if (is_wp_error($kuendigung_id)) {
            wp_send_json_error('Failed to create Kündigung: ' . $kuendigung_id->get_error_message());
        }
        
        // Save all meta fields
        $meta_fields = array(
            'employee_id' => $employee_id,
            'kuendigungsart' => $kuendigungsart,
            'kuendigungsdatum' => $kuendigungsdatum,
            'beendigungsdatum' => $beendigungsdatum,
            'kuendigungsgrund' => $kuendigungsgrund,
            'employer_name' => $employer_name,
            'employer_email' => $employer_email,
            'kuendigungsfrist' => $kuendigungsfrist,
            'resturlaub' => $resturlaub,
            'ueberstunden' => $ueberstunden,
            'zeugnis_gewuenscht' => $zeugnis_gewuenscht ? '1' : '0',
            'uebergabe_erledigt' => $uebergabe_erledigt ? '1' : '0',
            'notes' => $notes
        );
        
        foreach ($meta_fields as $key => $value) {
            update_post_meta($kuendigung_id, $key, $value);
        }
        
        // Update employee status to terminated
        update_post_meta($employee_id, 'status', 'terminated');
        
        wp_send_json_success(array(
            'message' => __('Kündigung erstellt. Sie können das PDF jetzt per E-Mail versenden.', 'rt-employee-manager-v2'),
            'kuendigung_id' => $kuendigung_id
        ));
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
