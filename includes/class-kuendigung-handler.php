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
        ?>
<div class="rt-kuendigung-actions" style="padding: 10px;">
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
    </style>

    <button type="button" id="create-kuendigung-btn" class="button button-primary" style="width: 100%;"
        <?php echo $is_terminated ? 'disabled' : ''; ?>>
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

<!-- Kündigung Modal -->
<div id="kuendigung-modal" style="display: none;">
    <div class="kuendigung-modal-overlay"
        style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 100000; display: flex; align-items: center; justify-content: center;">
        <div class="kuendigung-modal-content"
            style="background: #fff; padding: 30px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto; border-radius: 4px; position: relative;">
            <button type="button" class="kuendigung-modal-close"
                style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            <h2 style="margin-top: 0;"><?php _e('Kündigung erstellen', 'rt-employee-manager-v2'); ?></h2>
            <form id="kuendigung-form" novalidate>
                <input type="hidden" id="kuendigung-employee-id" name="employee_id"
                    value="<?php echo esc_attr($post->ID); ?>" />
                <?php wp_nonce_field('create_kuendigung_v2', 'kuendigung_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label
                                for="kuendigungsart"><?php _e('Kündigungsart', 'rt-employee-manager-v2'); ?> *</label>
                        </th>
                        <td>
                            <select name="kuendigungsart" id="kuendigungsart">
                                <option value=""><?php _e('Bitte wählen', 'rt-employee-manager-v2'); ?></option>
                                <option value="Ordentliche">
                                    <?php _e('Ordentliche Kündigung', 'rt-employee-manager-v2'); ?></option>
                                <option value="Fristlose"><?php _e('Fristlose Kündigung', 'rt-employee-manager-v2'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                for="kuendigungsdatum"><?php _e('Kündigungsdatum', 'rt-employee-manager-v2'); ?>
                                *</label></th>
                        <td><input type="date" name="kuendigungsdatum" id="kuendigungsdatum" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                for="beendigungsdatum"><?php _e('Beendigungsdatum (letzter Arbeitstag)', 'rt-employee-manager-v2'); ?>
                                *</label></th>
                        <td><input type="date" name="beendigungsdatum" id="beendigungsdatum" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                for="kuendigungsgrund"><?php _e('Grund der Kündigung', 'rt-employee-manager-v2'); ?>
                                *</label></th>
                        <td><textarea name="kuendigungsgrund" id="kuendigungsgrund" rows="4"
                                class="large-text"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                for="kuendigungsfrist"><?php _e('Kündigungsfrist', 'rt-employee-manager-v2'); ?></label>
                        </th>
                        <td><input type="text" name="kuendigungsfrist" id="kuendigungsfrist" class="regular-text"
                                placeholder="<?php _e('z.B. 1 Monat zum Monatsende', 'rt-employee-manager-v2'); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                for="resturlaub"><?php _e('Resturlaub (Tage)', 'rt-employee-manager-v2'); ?></label>
                        </th>
                        <td><input type="number" name="resturlaub" id="resturlaub" min="0" step="0.5" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                for="ueberstunden"><?php _e('Überstunden (Stunden)', 'rt-employee-manager-v2'); ?></label>
                        </th>
                        <td><input type="number" name="ueberstunden" id="ueberstunden" min="0" step="0.5" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                for="employer_name"><?php _e('Aussteller (Firma/Kunde)', 'rt-employee-manager-v2'); ?>
                                *</label></th>
                        <td>
                            <?php
                                    $current_user = wp_get_current_user();
                                    $employer_name = get_user_meta($current_user->ID, 'company_name', true) ?: $current_user->display_name;
                                    ?>
                            <input type="text" name="employer_name" id="employer_name"
                                value="<?php echo esc_attr($employer_name); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                for="employer_email"><?php _e('Aussteller E-Mail', 'rt-employee-manager-v2'); ?>
                                *</label></th>
                        <td>
                            <?php
                                    $employer_email = $current_user->user_email;
                                    ?>
                            <input type="email" name="employer_email" id="employer_email"
                                value="<?php echo esc_attr($employer_email); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Optionen', 'rt-employee-manager-v2'); ?></th>
                        <td>
                            <label><input type="checkbox" name="zeugnis_gewuenscht" id="zeugnis_gewuenscht" />
                                <?php _e('Zeugnis gewünscht', 'rt-employee-manager-v2'); ?></label><br>
                            <label><input type="checkbox" name="uebergabe_erledigt" id="uebergabe_erledigt" />
                                <?php _e('Übergabe erledigt', 'rt-employee-manager-v2'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="notes"><?php _e('Anmerkungen', 'rt-employee-manager-v2'); ?></label>
                        </th>
                        <td><textarea name="notes" id="notes" rows="3" class="large-text"></textarea></td>
                    </tr>
                </table>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <h3 style="margin-top: 0;"><?php _e('PDF per E-Mail versenden', 'rt-employee-manager-v2'); ?></h3>

                    <?php
                            $employee_data = $this->get_employee_data($post->ID);
                            $employee_email = $employee_data['email'] ?? '';
                            ?>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 8px;">
                            <?php _e('E-Mail-Adresse:', 'rt-employee-manager-v2'); ?>
                            <input type="email" id="kuendigung-email-address"
                                placeholder="<?php _e('E-Mail-Adresse eingeben', 'rt-employee-manager-v2'); ?>"
                                value="<?php echo esc_attr($employee_email); ?>" class="regular-text"
                                style="width: 100%; margin-top: 5px;" required />
                        </label>
                        <p class="description" style="font-size: 12px; color: #666; margin-top: 5px;">
                            <?php _e('Das PDF wird an diese E-Mail-Adresse gesendet.', 'rt-employee-manager-v2'); ?>
                        </p>
                    </div>

                    <div class="email-options" style="margin-bottom: 15px;">
                        <?php 
                                $buchhaltung_email = get_option('rt_employee_v2_buchhaltung_email', '');
                                if (!empty($buchhaltung_email)): 
                                ?>
                        <p><label style="display: block;">
                                <input type="checkbox" id="send-to-bookkeeping-on-create" />
                                <?php _e('Auch an Buchhaltung senden', 'rt-employee-manager-v2'); ?>
                                <strong>(<?php echo esc_html($buchhaltung_email); ?>)</strong>
                            </label></p>
                        <?php else: ?>
                        <p style="color: #666; font-style: italic; font-size: 12px;">
                            <?php _e('Buchhaltung E-Mail nicht konfiguriert.', 'rt-employee-manager-v2'); ?>
                            <a
                                href="<?php echo admin_url('admin.php?page=rt-employee-manager-v2-settings'); ?>"><?php _e('Jetzt einrichten', 'rt-employee-manager-v2'); ?></a>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit"
                        class="button button-primary"><?php _e('Kündigung erstellen und PDF versenden', 'rt-employee-manager-v2'); ?></button>
                    <button type="button"
                        class="button kuendigung-modal-close"><?php _e('Abbrechen', 'rt-employee-manager-v2'); ?></button>
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
        
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('email_kuendigung_v2');
        
        // Register a custom script handle for Kündigung functionality
        wp_register_script('rt-kuendigung-v2', false, array('jquery'), RT_EMPLOYEE_V2_VERSION, true);
        wp_localize_script('rt-kuendigung-v2', 'rtKuendigungV2', array(
            'ajaxurl' => $ajax_url,
            'nonce' => $nonce
        ));
        wp_enqueue_script('rt-kuendigung-v2');
        
        // Add inline script
        wp_add_inline_script('rt-kuendigung-v2', '
            console.log("RT Employee Manager V2: Kündigung inline script START");
            if (typeof jQuery === "undefined") {
                console.error("RT Employee Manager V2: jQuery not loaded!");
            } else {
                console.log("RT Employee Manager V2: jQuery loaded, version:", jQuery.fn.jquery);
            }
            (function($) {
                console.log("RT Employee Manager V2: Kündigung script IIFE started");
                // Email validation helper
                function isValidEmail(email) {
                    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    return re.test(email);
                }
                
                $(document).ready(function() {
                    console.log("RT Employee Manager V2: Kündigung script document ready");
                    console.log("RT Employee Manager V2: Checking for create-kuendigung-btn:", $("#create-kuendigung-btn").length);
                    console.log("RT Employee Manager V2: Checking for kuendigung-form:", $("#kuendigung-form").length);
                    console.log("RT Employee Manager V2: Checking for kuendigung-modal:", $("#kuendigung-modal").length);
                    console.log("RT Employee Manager V2: rtKuendigungV2 object:", typeof rtKuendigungV2 !== "undefined" ? rtKuendigungV2 : "UNDEFINED");
                    // Function to update button state based on status
                    function updateKuendigungButtonState() {
                        var statusField = $("#status");
                        var statusValue = statusField.val();
                        var createBtn = $("#create-kuendigung-btn");
                        var statusNotice = $(".kuendigung-status-notice");
                        
                        if (statusValue === "terminated") {
                            // Status is Ausgeschieden - disable button
                            createBtn.prop("disabled", true);
                            if (statusNotice.length === 0) {
                                createBtn.before("<div class=\"kuendigung-status-notice\" style=\"padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404; margin-bottom: 10px;\"><strong>Mitarbeiter bereits ausgeschieden</strong><br><small>Der Beschäftigungsstatus ist bereits auf \"Ausgeschieden\" gesetzt. Ändern Sie den Status auf \"Beschäftigt\", um eine neue Kündigung zu erstellen.</small></div>");
                            } else {
                                statusNotice.show();
                            }
                        } else {
                            // Status is not terminated - enable button
                            createBtn.prop("disabled", false);
                            statusNotice.hide();
                        }
                    }
                    
                    // Check initial state
                    updateKuendigungButtonState();
                    
                    // Watch for status changes
                    $("#status").on("change", function() {
                        updateKuendigungButtonState();
                    });
                    
                    // Open modal
                    console.log("RT Employee Manager V2: Attaching click handler to create-kuendigung-btn");
                    $(document).on("click", "#create-kuendigung-btn", function(e) {
                        console.log("RT Employee Manager V2: Create Kündigung button clicked!");
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Check status before opening modal
                        var currentStatus = $("#status").val();
                        console.log("RT Employee Manager V2: Current status:", currentStatus);
                        if (currentStatus === "terminated") {
                            alert("Der Mitarbeiter ist bereits ausgeschieden. Bitte ändern Sie den Beschäftigungsstatus zuerst.");
                            return;
                        }
                        
                        console.log("RT Employee Manager V2: Showing modal, checking if exists:", $("#kuendigung-modal").length);
                        $("#kuendigung-modal").show();
                        console.log("RT Employee Manager V2: Modal should be visible now");
                    });
                    
                    // Close modal
                    $(document).on("click", ".kuendigung-modal-close, .kuendigung-modal-overlay", function(e) {
                        if (e.target === this || $(e.target).hasClass("kuendigung-modal-close")) {
                            console.log("RT Employee Manager V2: Closing modal");
                            $("#kuendigung-modal").hide();
                        }
                    });
                    
                    // Submit form
                    console.log("RT Employee Manager V2: Attaching submit handler to kuendigung-form");
                    $(document).on("submit", "#kuendigung-form", function(e) {
                        console.log("RT Employee Manager V2: Kündigung form submitted!");
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Manual validation
                        var kuendigungsart = $("#kuendigungsart").val();
                        var kuendigungsdatum = $("#kuendigungsdatum").val();
                        var beendigungsdatum = $("#beendigungsdatum").val();
                        var kuendigungsgrund = $("#kuendigungsgrund").val().trim();
                        var employer_name = $("#employer_name").val().trim();
                        var employer_email = $("#employer_email").val().trim();
                        
                        var errors = [];
                        if (!kuendigungsart) {
                            errors.push("Bitte wählen Sie die Kündigungsart aus.");
                            $("#kuendigungsart").focus();
                        }
                        if (!kuendigungsdatum) {
                            errors.push("Bitte geben Sie das Kündigungsdatum ein.");
                            if (errors.length === 1) $("#kuendigungsdatum").focus();
                        }
                        if (!beendigungsdatum) {
                            errors.push("Bitte geben Sie das Beendigungsdatum ein.");
                            if (errors.length === 1) $("#beendigungsdatum").focus();
                        }
                        if (!kuendigungsgrund) {
                            errors.push("Bitte geben Sie den Grund der Kündigung ein.");
                            if (errors.length === 1) $("#kuendigungsgrund").focus();
                        }
                        if (!employer_name) {
                            errors.push("Bitte geben Sie den Aussteller (Firma/Kunde) ein.");
                            if (errors.length === 1) $("#employer_name").focus();
                        }
                        if (!employer_email) {
                            errors.push("Bitte geben Sie die Aussteller E-Mail ein.");
                            if (errors.length === 1) $("#employer_email").focus();
                        } else if (!isValidEmail(employer_email)) {
                            errors.push("Bitte geben Sie eine gültige E-Mail-Adresse für den Aussteller ein.");
                            if (errors.length === 1) $("#employer_email").focus();
                        }
                        
                        if (errors.length > 0) {
                            alert("Bitte korrigieren Sie folgende Fehler:\n\n" + errors.join("\n"));
                            return false;
                        }
                        
                        var formData = $(this).serialize();
                        var submitBtn = $(this).find("button[type=submit]");
                        var originalText = submitBtn.text();
                        
                        // Get email options
                        var emailAddress = $("#kuendigung-email-address").val().trim();
                        var sendToBookkeeping = $("#send-to-bookkeeping-on-create").is(":checked");
                        
                        // Validate email address (required)
                        if (!emailAddress) {
                            alert("Bitte geben Sie eine E-Mail-Adresse ein.");
                            $("#kuendigung-email-address").focus();
                            return false;
                        }
                        
                        if (!isValidEmail(emailAddress)) {
                            alert("Bitte geben Sie eine gültige E-Mail-Adresse ein.");
                            $("#kuendigung-email-address").focus();
                            return false;
                        }
                        
                        submitBtn.prop("disabled", true).text("Erstelle Kündigung...");
                        
                        // Add email options to form data
                        var data = formData + "&action=create_kuendigung_v2";
                        data += "&email_address=" + encodeURIComponent(emailAddress);
                        data += "&send_to_bookkeeping=" + (sendToBookkeeping ? "1" : "");
                        
                        console.log("RT Employee Manager V2: Sending AJAX request");
                        console.log("RT Employee Manager V2: AJAX URL:", rtKuendigungV2.ajaxurl);
                        console.log("RT Employee Manager V2: Data:", data);
                        
                        $.ajax({
                            url: rtKuendigungV2.ajaxurl,
                            type: "POST",
                            data: data,
                            beforeSend: function() {
                                console.log("RT Employee Manager V2: AJAX request started");
                            },
                            success: function(response) {
                                console.log("RT Employee Manager V2: AJAX success response:", response);
                                if (response && response.success) {
                                    // Close modal
                                    $("#kuendigung-modal").hide();
                                    alert("✓ " + response.data.message);
                                    // Force reload after a short delay to ensure server has processed
                                    setTimeout(function() {
                                        location.reload(true);
                                    }, 500);
                                } else {
                                    console.error("RT Employee Manager V2: AJAX returned error:", response);
                                    alert("Fehler: " + (response && response.data ? response.data : "Unbekannter Fehler"));
                                    submitBtn.prop("disabled", false).text(originalText);
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error("RT Employee Manager V2: AJAX error:", status, error);
                                console.error("RT Employee Manager V2: XHR response:", xhr.responseText);
                                alert("Fehler beim Erstellen der Kündigung: " + error + " (Status: " + status + ")");
                                submitBtn.prop("disabled", false).text(originalText);
                            },
                            complete: function() {
                                console.log("RT Employee Manager V2: AJAX request complete");
                            }
                        });
                        
                        return false;
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
                    
                    console.log("RT Employee Manager V2: Kündigung script initialization complete");
                });
            })(jQuery);
            console.log("RT Employee Manager V2: Kündigung inline script END");
        ', 'after');
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
        
        // Get email address and options
        $email_address = sanitize_email($_POST['email_address'] ?? '');
        $send_to_bookkeeping = !empty($_POST['send_to_bookkeeping']);
        
        // Log for debugging
        error_log('RT Employee Manager V2: Creating Kündigung - email_address: ' . $email_address . ', send_to_bookkeeping: ' . ($send_to_bookkeeping ? 'yes' : 'no'));
        error_log('RT Employee Manager V2: POST data: ' . print_r($_POST, true));
        
        // Validate email address
        if (empty($email_address)) {
            wp_send_json_error(__('Bitte geben Sie eine E-Mail-Adresse ein.', 'rt-employee-manager-v2'));
        }
        
        // Update employee status to terminated (Ausgeschieden)
        $old_status = get_post_meta($employee_id, 'status', true);
        error_log('RT Employee Manager V2: Updating status - Old: ' . $old_status . ', New: terminated, Employee ID: ' . $employee_id);
        
        // Update status using update_post_meta (will add if doesn't exist, update if exists)
        $status_updated = update_post_meta($employee_id, 'status', 'terminated');
        
        // Verify status was updated
        $current_status = get_post_meta($employee_id, 'status', true);
        error_log('RT Employee Manager V2: Status after update_post_meta: ' . $current_status . ' (update_post_meta returned: ' . var_export($status_updated, true) . ')');
        
        // Force clear WordPress object cache
        wp_cache_delete($employee_id, 'post_meta');
        if (function_exists('clean_post_cache')) {
            clean_post_cache($employee_id);
        }
        
        // Double-check status
        $final_status = get_post_meta($employee_id, 'status', true);
        if ($final_status !== 'terminated') {
            error_log('RT Employee Manager V2: WARNING - Status verification failed! Expected: terminated, Got: ' . $final_status);
            // Try direct database update as fallback
            global $wpdb;
            $result = $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => 'terminated'),
                array('post_id' => $employee_id, 'meta_key' => 'status'),
                array('%s'),
                array('%d', '%s')
            );
            error_log('RT Employee Manager V2: Direct DB update result: ' . var_export($result, true));
            $final_status = get_post_meta($employee_id, 'status', true);
            error_log('RT Employee Manager V2: Status after direct DB update: ' . $final_status);
        } else {
            error_log('RT Employee Manager V2: Status successfully updated to: terminated');
        }
        
        $message = __('Kündigung erstellt.', 'rt-employee-manager-v2');
        
        // Generate PDF and send email
        $pdf_generator = new RT_Kuendigung_PDF_Generator_V2();
        $result = $pdf_generator->send_kuendigung_email_manual($kuendigung_id, $employee_id, $email_address, true, $send_to_bookkeeping);
        
        error_log('RT Employee Manager V2: Email sending result: ' . print_r($result, true));
        
        if ($result['success']) {
            $message .= ' ' . __('PDF erfolgreich per E-Mail versendet. Beschäftigungsstatus wurde auf "Ausgeschieden" geändert.', 'rt-employee-manager-v2');
        } else {
            $message .= ' ' . __('Kündigung erstellt und Beschäftigungsstatus auf "Ausgeschieden" geändert, aber E-Mail-Versand fehlgeschlagen: ', 'rt-employee-manager-v2') . ($result['error'] ?? __('Unbekannter Fehler', 'rt-employee-manager-v2'));
        }
        
        wp_send_json_success(array(
            'message' => $message,
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