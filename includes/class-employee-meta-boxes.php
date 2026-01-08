<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Employee form fields and PDF actions
 */
class RT_Employee_Meta_Boxes_V2 {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_employee_meta'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'rt_employee_details_v2',
            __('Mitarbeiterdaten', 'rt-employee-manager-v2'),
            array($this, 'employee_meta_box_callback'),
            'angestellte_v2',
            'normal',
            'high'
        );
        
        // Add PDF actions meta box
        add_meta_box(
            'rt_employee_pdf_actions_v2',
            __('PDF Aktionen', 'rt-employee-manager-v2'),
            array($this, 'pdf_actions_meta_box_callback'),
            'angestellte_v2',
            'side',
            'high'
        );
    }
    
    /**
     * Show the main employee form
     */
    public function employee_meta_box_callback($post) {
        wp_nonce_field('rt_employee_meta_v2', 'rt_employee_meta_nonce');
        
        // Grab any existing data we have for this employee
        $data = $this->get_employee_data($post->ID);
        
        ?>
<div class="rt-meta-v2">
    <style>
    .rt-meta-v2 {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .rt-meta-v2 .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }

    .rt-meta-v2 .form-row-full {
        grid-column: 1 / -1;
    }

    .rt-meta-v2 .form-section {
        margin: 25px 0;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 4px;
    }

    .rt-meta-v2 .form-section h4 {
        margin: 0 0 15px 0;
        color: #333;
        border-bottom: 1px solid #ddd;
        padding-bottom: 8px;
    }

    .rt-meta-v2 label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #333;
    }

    .rt-meta-v2 input,
    .rt-meta-v2 select,
    .rt-meta-v2 textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .rt-meta-v2 input[type="checkbox"],
    .rt-meta-v2 input[type="radio"] {
        width: auto;
    }

    .rt-meta-v2 input:focus,
    .rt-meta-v2 select:focus,
    .rt-meta-v2 textarea:focus {
        border-color: #0073aa;
        outline: none;
        box-shadow: 0 0 0 1px #0073aa;
    }

    .rt-meta-v2 .checkbox-group label {
        display: inline;
        font-weight: normal;
        margin-left: 5px;
    }

    .rt-meta-v2 .radio-group {
        display: flex;
        gap: 15px;
    }

    .rt-meta-v2 .radio-group label {
        display: flex;
        align-items: center;
        font-weight: normal;
    }

    .rt-meta-v2 .radio-group input {
        width: auto;
        margin-right: 5px;
    }
    </style>

    <!-- Basic Info -->
    <div class="form-row">
        <div>
            <label for="anrede"><?php _e('Anrede', 'rt-employee-manager-v2'); ?></label>
            <select name="anrede" id="anrede">
                <option value=""><?php _e('Bitte wählen', 'rt-employee-manager-v2'); ?></option>
                <option value="Herr" <?php selected($data['anrede'], 'Herr'); ?>>
                    <?php _e('Herr', 'rt-employee-manager-v2'); ?></option>
                <option value="Frau" <?php selected($data['anrede'], 'Frau'); ?>>
                    <?php _e('Frau', 'rt-employee-manager-v2'); ?></option>
                <option value="Divers" <?php selected($data['anrede'], 'Divers'); ?>>
                    <?php _e('Divers', 'rt-employee-manager-v2'); ?></option>
            </select>
        </div>
        <div>
            <label for="vorname"><?php _e('Vorname', 'rt-employee-manager-v2'); ?> *</label>
            <input type="text" name="vorname" id="vorname" value="<?php echo esc_attr($data['vorname']); ?>" required />
        </div>
        <div>
            <label for="nachname"><?php _e('Nachname', 'rt-employee-manager-v2'); ?> *</label>
            <input type="text" name="nachname" id="nachname" value="<?php echo esc_attr($data['nachname']); ?>"
                required />
        </div>
    </div>

    <div class="form-row">
        <div>
            <label
                for="sozialversicherungsnummer"><?php _e('Sozialversicherungsnummer', 'rt-employee-manager-v2'); ?></label>
            <input type="text" name="sozialversicherungsnummer" id="sozialversicherungsnummer"
                value="<?php echo esc_attr($data['sozialversicherungsnummer']); ?>" maxlength="10"
                placeholder="1234567890" pattern="[0-9]{10}" inputmode="numeric" required />
        </div>
        <div>
            <label for="geburtsdatum"><?php _e('Geburtsdatum', 'rt-employee-manager-v2'); ?></label>
            <input type="date" name="geburtsdatum" id="geburtsdatum"
                value="<?php echo esc_attr($data['geburtsdatum']); ?>" />
        </div>
        <div>
            <label for="staatsangehoerigkeit"><?php _e('Staatsangehörigkeit', 'rt-employee-manager-v2'); ?></label>
            <input type="text" name="staatsangehoerigkeit" id="staatsangehoerigkeit"
                value="<?php echo esc_attr($data['staatsangehoerigkeit']); ?>" />
        </div>
    </div>

    <div class="form-row">
        <div>
            <label for="email"><?php _e('E-Mail-Adresse', 'rt-employee-manager-v2'); ?></label>
            <input type="email" name="email" id="email" value="<?php echo esc_attr($data['email']); ?>" />
        </div>
        <div>
            <label for="personenstand"><?php _e('Personenstand', 'rt-employee-manager-v2'); ?></label>
            <select name="personenstand" id="personenstand">
                <option value=""><?php _e('Bitte wählen', 'rt-employee-manager-v2'); ?></option>
                <option value="Ledig" <?php selected($data['personenstand'], 'Ledig'); ?>>
                    <?php _e('Ledig', 'rt-employee-manager-v2'); ?></option>
                <option value="Verheiratet" <?php selected($data['personenstand'], 'Verheiratet'); ?>>
                    <?php _e('Verheiratet', 'rt-employee-manager-v2'); ?></option>
                <option value="Geschieden" <?php selected($data['personenstand'], 'Geschieden'); ?>>
                    <?php _e('Geschieden', 'rt-employee-manager-v2'); ?></option>
                <option value="Verwitwet" <?php selected($data['personenstand'], 'Verwitwet'); ?>>
                    <?php _e('Verwitwet', 'rt-employee-manager-v2'); ?></option>
            </select>
        </div>
        <div></div>
    </div>

    <!-- Address Section -->
    <div class="form-section">
        <h4><?php _e('Adresse', 'rt-employee-manager-v2'); ?></h4>
        <div class="form-row">
            <div class="form-row-full">
                <label for="adresse_strasse"><?php _e('Straße und Hausnummer', 'rt-employee-manager-v2'); ?></label>
                <input type="text" name="adresse_strasse" id="adresse_strasse"
                    value="<?php echo esc_attr($data['adresse_strasse']); ?>" />
            </div>
        </div>
        <div class="form-row">
            <div>
                <label for="adresse_plz"><?php _e('PLZ', 'rt-employee-manager-v2'); ?></label>
                <input type="text" name="adresse_plz" id="adresse_plz"
                    value="<?php echo esc_attr($data['adresse_plz']); ?>" />
            </div>
            <div>
                <label for="adresse_ort"><?php _e('Ort', 'rt-employee-manager-v2'); ?></label>
                <input type="text" name="adresse_ort" id="adresse_ort"
                    value="<?php echo esc_attr($data['adresse_ort']); ?>" />
            </div>
            <div></div>
        </div>
    </div>

    <!-- Employment Section -->
    <div class="form-section">
        <h4><?php _e('Beschäftigungsdaten', 'rt-employee-manager-v2'); ?></h4>
        <div class="form-row">
            <div>
                <label for="eintrittsdatum"><?php _e('Eintrittsdatum', 'rt-employee-manager-v2'); ?> *</label>
                <input type="date" name="eintrittsdatum" id="eintrittsdatum"
                    value="<?php echo esc_attr($data['eintrittsdatum']); ?>" required />
            </div>
            <div>
                <label
                    for="art_des_dienstverhaltnisses"><?php _e('Art des Dienstverhältnisses', 'rt-employee-manager-v2'); ?>
                    *</label>
                <select name="art_des_dienstverhaltnisses" id="art_des_dienstverhaltnisses" required>
                    <option value=""><?php _e('Bitte wählen', 'rt-employee-manager-v2'); ?></option>
                    <option value="Angestellter"
                        <?php selected($data['art_des_dienstverhaltnisses'], 'Angestellter'); ?>>
                        <?php _e('Angestellter', 'rt-employee-manager-v2'); ?></option>
                    <option value="Arbeiter/in" <?php selected($data['art_des_dienstverhaltnisses'], 'Arbeiter/in'); ?>>
                        <?php _e('Arbeiter/in', 'rt-employee-manager-v2'); ?></option>
                    <option value="Lehrling" <?php selected($data['art_des_dienstverhaltnisses'], 'Lehrling'); ?>>
                        <?php _e('Lehrling', 'rt-employee-manager-v2'); ?></option>
                </select>
            </div>
            <div>
                <label
                    for="bezeichnung_der_tatigkeit"><?php _e('Bezeichnung der Tätigkeit', 'rt-employee-manager-v2'); ?></label>
                <input type="text" name="bezeichnung_der_tatigkeit" id="bezeichnung_der_tatigkeit"
                    value="<?php echo esc_attr($data['bezeichnung_der_tatigkeit']); ?>" />
            </div>
        </div>

        <div class="form-row">
            <div>
                <label
                    for="arbeitszeit_pro_woche"><?php _e('Arbeitszeit pro Woche (Stunden)', 'rt-employee-manager-v2'); ?></label>
                <input type="number" name="arbeitszeit_pro_woche" id="arbeitszeit_pro_woche"
                    value="<?php echo esc_attr($data['arbeitszeit_pro_woche']); ?>" min="1" max="60" step="0.5" />
            </div>
            <div>
                <label for="gehaltlohn"><?php _e('Gehalt/Lohn (€)', 'rt-employee-manager-v2'); ?></label>
                <input type="number" name="gehaltlohn" id="gehaltlohn"
                    value="<?php echo esc_attr($data['gehaltlohn']); ?>" min="0" step="0.01" />
            </div>
            <div>
                <label for="status"><?php _e('Beschäftigungsstatus', 'rt-employee-manager-v2'); ?></label>
                <select name="status" id="status">
                    <option value="active" <?php selected($data['status'], 'active'); ?>>
                        <?php _e('Beschäftigt', 'rt-employee-manager-v2'); ?></option>
                    <option value="inactive" <?php selected($data['status'], 'inactive'); ?>>
                        <?php _e('Beurlaubt', 'rt-employee-manager-v2'); ?></option>
                    <option value="suspended" <?php selected($data['status'], 'suspended'); ?>>
                        <?php _e('Suspendiert', 'rt-employee-manager-v2'); ?></option>
                    <option value="terminated" <?php selected($data['status'], 'terminated'); ?>>
                        <?php _e('Ausgeschieden', 'rt-employee-manager-v2'); ?></option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div>
                <label><?php _e('Gehalt/Lohn: Brutto/Netto', 'rt-employee-manager-v2'); ?></label>
                <div class="radio-group">
                    <label><input type="radio" name="type" value="Brutto" <?php checked($data['type'], 'Brutto'); ?> />
                        <?php _e('Brutto', 'rt-employee-manager-v2'); ?></label>
                    <label><input type="radio" name="type" value="Netto" <?php checked($data['type'], 'Netto'); ?> />
                        <?php _e('Netto', 'rt-employee-manager-v2'); ?></label>
                </div>
            </div>
            <div>
                <label><?php _e('Arbeitstage', 'rt-employee-manager-v2'); ?></label>
                <fieldset style="margin-top: 5px;">
                    <?php
                            $days = array(
                                'Mo' => __('Mo', 'rt-employee-manager-v2'),
                                'Di' => __('Di', 'rt-employee-manager-v2'),
                                'Mi' => __('Mi', 'rt-employee-manager-v2'),
                                'Do' => __('Do', 'rt-employee-manager-v2'),
                                'Fr' => __('Fr', 'rt-employee-manager-v2'),
                                'Sa' => __('Sa', 'rt-employee-manager-v2'),
                                'So' => __('So', 'rt-employee-manager-v2')
                            );
                            foreach ($days as $key => $label): ?>
                    <label style="display: inline-block; margin-right: 15px; margin-bottom: 5px;">
                        <input type="checkbox" name="arbeitstagen[]" value="<?php echo esc_attr($key); ?>"
                            <?php checked(in_array($key, $data['arbeitstagen'])); ?> />
                        <?php echo esc_html($label); ?>
                    </label>
                    <?php endforeach; ?>
                </fieldset>
            </div>
            <div></div>
        </div>
    </div>

    <!-- Employer Assignment -->
    <div class="form-section">
        <h4><?php _e('Zuordnung', 'rt-employee-manager-v2'); ?></h4>
        <div class="form-row">
            <?php if (current_user_can('manage_options')): ?>
            <div>
                <label for="employer_id"><?php _e('Arbeitgeber', 'rt-employee-manager-v2'); ?></label>
                <select name="employer_id" id="employer_id">
                    <option value=""><?php _e('Bitte wählen', 'rt-employee-manager-v2'); ?></option>
                    <?php
                                $users = get_users(array('role' => 'kunden_v2'));
                                foreach ($users as $user): ?>
                    <option value="<?php echo esc_attr($user->ID); ?>"
                        <?php selected($data['employer_id'], $user->ID); ?>>
                        <?php echo esc_html($user->display_name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" name="employer_id" value="<?php echo get_current_user_id(); ?>" />
            <div>
                <label><?php _e('Arbeitgeber', 'rt-employee-manager-v2'); ?></label>
                <input type="text" value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>" readonly />
                <p style="color: #666; font-size: 12px; margin-top: 5px;">
                    <?php _e('Arbeitgeber kann nicht geändert werden', 'rt-employee-manager-v2'); ?>
                </p>
            </div>
            <?php endif; ?>
            <div></div>
            <div></div>
        </div>
    </div>

    <!-- Notes -->
    <div class="form-section">
        <h4><?php _e('Anmerkungen', 'rt-employee-manager-v2'); ?></h4>
        <div class="form-row">
            <div class="form-row-full">
                <textarea name="anmerkungen" id="anmerkungen"
                    rows="3"><?php echo esc_textarea($data['anmerkungen']); ?></textarea>
            </div>
        </div>
    </div>
</div>
<?php
    }
    
    /**
     * Show PDF download and email options in the sidebar
     */
    public function pdf_actions_meta_box_callback($post) {
        if ($post->post_status !== 'publish') {
            echo '<p>' . __('Speichern Sie den Mitarbeiter zuerst, um PDF-Aktionen zu verwenden.', 'rt-employee-manager-v2') . '</p>';
            return;
        }
        
        $latest_pdf = get_post_meta($post->ID, '_latest_pdf_url', true);
        $last_emailed = get_post_meta($post->ID, '_pdf_emailed_at', true);
        $emailed_to = get_post_meta($post->ID, '_pdf_emailed_to', true);
        
        ?>
<div class="rt-pdf-actions" style="padding: 10px;">
    <style>
    .rt-pdf-actions .button {
        width: 100%;
        margin-bottom: 10px;
        text-align: center;
    }

    .pdf-status {
        background: #f1f1f1;
        padding: 8px;
        border-radius: 4px;
        margin-bottom: 10px;
        font-size: 12px;
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

    <!-- PDF Status -->
    <?php if ($latest_pdf): ?>
    <div class="pdf-status">
        <strong><?php _e('PDF Status:', 'rt-employee-manager-v2'); ?></strong><br>
        <span style="color: green;">✓ <?php _e('PDF verfügbar', 'rt-employee-manager-v2'); ?></span>
        <?php if ($last_emailed): ?>
        <br><small><?php printf(__('Zuletzt versendet: %s', 'rt-employee-manager-v2'), date_i18n(get_option('date_format') . ' H:i', strtotime($last_emailed))); ?></small>
        <?php if ($emailed_to): ?>
        <br><small><?php printf(__('An: %s', 'rt-employee-manager-v2'), esc_html($emailed_to)); ?></small>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- PDF Actions -->
    <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=generate_and_view_employee_pdf&employee_id=' . $post->ID), 'generate_view_pdf_v2', 'nonce'); ?>"
        target="_blank" class="button button-primary" style="width: 100%; text-align: center;">
        <?php _e('PDF anzeigen', 'rt-employee-manager-v2'); ?>
    </a>
    <small style="display: block; margin-top: 5px; color: #666; text-align: center;">
        <?php _e('Erzeugt automatisch aktuelle PDF mit allen Daten', 'rt-employee-manager-v2'); ?>
    </small>

    <!-- Email Form -->
    <div class="email-form" style="margin-top: 20px;">
        <h4 style="margin-top: 0;"><?php _e('PDF per E-Mail versenden', 'rt-employee-manager-v2'); ?></h4>

        <p style="margin-bottom: 15px;">
            <input type="email" id="pdf-email"
                placeholder="<?php _e('E-Mail-Adresse eingeben', 'rt-employee-manager-v2'); ?>" style="width: 100%;" />
        </p>

        <div class="email-options" style="margin-bottom: 15px;">
            <p><label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" id="send-to-kunde" />
                    <?php _e('An Kunde senden (oben eingegebene E-Mail)', 'rt-employee-manager-v2'); ?>
                </label></p>

            <?php 
                    $buchhaltung_email = get_option('rt_employee_v2_buchhaltung_email', '');
                    if (!empty($buchhaltung_email)): 
                    ?>
            <p><label style="display: block;">
                    <input type="checkbox" id="send-to-bookkeeping" />
                    <?php _e('Auch an Buchhaltung senden', 'rt-employee-manager-v2'); ?>
                    <strong>(<?php echo esc_html($buchhaltung_email); ?>)</strong>
                </label></p>
            <?php else: ?>
            <p style="color: #666; font-style: italic;">
                <?php _e('Buchhaltung E-Mail nicht konfiguriert.', 'rt-employee-manager-v2'); ?>
                <a
                    href="<?php echo admin_url('admin.php?page=rt-employee-manager-v2-settings'); ?>"><?php _e('Jetzt einrichten', 'rt-employee-manager-v2'); ?></a>
            </p>
            <?php endif; ?>
        </div>

        <p>
            <button type="button" id="send-pdf-email" class="button button-primary" style="width: 100%;">
                <?php _e('PDF versenden', 'rt-employee-manager-v2'); ?>
            </button>
        </p>

        <p style="font-size: 12px; color: #666; margin: 10px 0 0 0;">
            <?php _e('Das PDF wird automatisch als Anhang versendet.', 'rt-employee-manager-v2'); ?>
        </p>
    </div>

    <div id="pdf-messages" style="margin-top: 10px;"></div>
</div>

<?php
    }
    
    /**
     * Get all the employee data from post meta
     */
    private function get_employee_data($post_id) {
        $defaults = array(
            'anrede' => '',
            'vorname' => '',
            'nachname' => '',
            'sozialversicherungsnummer' => '',
            'geburtsdatum' => '',
            'staatsangehoerigkeit' => '',
            'email' => '',
            'personenstand' => '',
            'adresse_strasse' => '',
            'adresse_plz' => '',
            'adresse_ort' => '',
            'eintrittsdatum' => '',
            'art_des_dienstverhaltnisses' => '',
            'bezeichnung_der_tatigkeit' => '',
            'arbeitszeit_pro_woche' => '',
            'gehaltlohn' => '',
            'type' => '',
            'arbeitstagen' => array(),
            'anmerkungen' => '',
            'status' => 'active',
            'employer_id' => ''
        );
        
        $data = array();
        foreach ($defaults as $key => $default) {
            if ($key === 'arbeitstagen') {
                $value = get_post_meta($post_id, $key, true);
                $data[$key] = is_array($value) ? $value : array();
            } else {
                $data[$key] = get_post_meta($post_id, $key, true) ?: $default;
            }
        }
        
        return $data;
    }
    
    /**
     * Save all the form data when employee gets saved
     */
    public function save_employee_meta($post_id, $post) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only for our employee post type
        if ($post->post_type !== 'angestellte_v2') {
            return;
        }
        
        // Check the nonce
        if (!isset($_POST['rt_employee_meta_nonce']) || !wp_verify_nonce($_POST['rt_employee_meta_nonce'], 'rt_employee_meta_v2')) {
            return;
        }
        
        // Make sure user can edit this
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Collect all the fields we need to save
        $meta_updates = array();
        
        // All the employee fields
        $fields = array(
            'anrede', 'vorname', 'nachname', 'sozialversicherungsnummer', 'geburtsdatum',
            'staatsangehoerigkeit', 'email', 'personenstand', 'adresse_strasse', 
            'adresse_plz', 'adresse_ort', 'eintrittsdatum', 'art_des_dienstverhaltnisses',
            'bezeichnung_der_tatigkeit', 'arbeitszeit_pro_woche', 'gehaltlohn', 'type',
            'anmerkungen', 'status', 'employer_id'
        );
        
        // Go through each field and sanitize
        foreach ($fields as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
            
            // Quick check on the SVNR format
            if ($field === 'sozialversicherungsnummer' && !empty($value)) {
                if (!$this->validate_austrian_svnr($value)) {
                    // Skip invalid SVNR, don't save it
                    continue;
                }
            }
            
            $meta_updates[$field] = $value;
        }
        
        // Working days is an array, handle separately
        $arbeitstagen = isset($_POST['arbeitstagen']) && is_array($_POST['arbeitstagen']) 
                        ? array_map('sanitize_text_field', $_POST['arbeitstagen']) 
                        : array();
        $meta_updates['arbeitstagen'] = $arbeitstagen;
        
        // Save all the meta fields
        foreach ($meta_updates as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
        
        // Set the post title to first + last name
        $vorname = sanitize_text_field($_POST['vorname'] ?? '');
        $nachname = sanitize_text_field($_POST['nachname'] ?? '');
        
        if (!empty($vorname) || !empty($nachname)) {
            $new_title = trim($vorname . ' ' . $nachname);
            if ($post->post_title !== $new_title) {
                // Temporarily remove the hook to avoid infinite loop
                remove_action('save_post', array($this, 'save_employee_meta'), 10, 2);
                
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => $new_title,
                    'post_status' => 'publish'
                ));
                
                // Put the hook back
                add_action('save_post', array($this, 'save_employee_meta'), 10, 2);
            }
        }
        
        // Handle employer assignment
        $user = wp_get_current_user();
        if (in_array('kunden_v2', $user->roles) && !in_array('administrator', $user->roles)) {
            // Client users can only assign employees to themselves
            update_post_meta($post_id, 'employer_id', $user->ID);
        } else if (empty($_POST['employer_id']) && !empty($user->ID)) {
            // Admins get themselves as default if no employer selected
            update_post_meta($post_id, 'employer_id', $user->ID);
        }
    }
    
    /**
     * Check if Austrian SVNR looks valid (just 10 digits)
     */
    private function validate_austrian_svnr($svnr) {
        // Strip everything except numbers
        $svnr = preg_replace('/[^0-9]/', '', $svnr);
        
        // Austrian SVNR should be exactly 10 digits
        return strlen($svnr) === 10;
    }
    
    /**
     * Load up the JavaScript for form validation and email stuff
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        global $post_type;
        if ($post_type !== 'angestellte_v2') {
            return;
        }

        // Register and enqueue our own script handle with an empty source
        // This ensures wp_localize_script and wp_add_inline_script work properly
        wp_register_script(
            'rt-employee-manager-v2-admin',
            false, // No source file, we'll use inline
            array('jquery'),
            RT_EMPLOYEE_V2_VERSION,
            true // In footer for better execution timing
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('rt-employee-manager-v2-admin', 'rtEmployeeManagerV2', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('email_pdf_v2')
        ));
        
        // Add our inline script
        $ajax_url = esc_js(admin_url('admin-ajax.php'));
        $nonce = wp_create_nonce('email_pdf_v2');
        
        wp_add_inline_script('rt-employee-manager-v2-admin', '
            (function($) {
                "use strict";
                
                console.log("RT Employee Manager V2: Script file loaded, waiting for DOM ready...");
                
                $(document).ready(function() {
                    console.log("RT Employee Manager V2: DOM ready, initializing...");
                    
                    const svnrField = document.getElementById("sozialversicherungsnummer");
                    if (svnrField) {
                        console.log("RT Employee Manager V2: SVNR field found");
                        // Only allow numbers, max 10 digits
                        svnrField.addEventListener("input", function(e) {
                            e.target.value = e.target.value.replace(/[^0-9]/g, "").slice(0, 10);
                        });
                        
                        // Prevent non-numeric keystrokes
                        svnrField.addEventListener("keypress", function(e) {
                            if (!/[0-9]/.test(e.key) && !["Backspace", "Delete", "Tab", "Enter", "ArrowLeft", "ArrowRight"].includes(e.key)) {
                                e.preventDefault();
                            }
                        });
                        
                        // Handle paste events
                        svnrField.addEventListener("paste", function(e) {
                            e.preventDefault();
                            const paste = (e.clipboardData || window.clipboardData).getData("text");
                            const numbersOnly = paste.replace(/[^0-9]/g, "").slice(0, 10);
                            e.target.value = numbersOnly;
                            e.target.dispatchEvent(new Event("input"));
                        });
                        
                        // Validate on blur
                        svnrField.addEventListener("blur", function(e) {
                            const svnr = e.target.value;
                            if (svnr && !validateAustrianSVNR(svnr)) {
                                e.target.style.borderColor = "#dc3232";
                                showSVNRError("Bitte geben Sie genau 10 Ziffern ein");
                            } else {
                                e.target.style.borderColor = "";
                                hideSVNRError();
                            }
                        });
                    } else {
                        console.warn("RT Employee Manager V2: SVNR field not found");
                    }
                    
                    // Simple SVNR validation function - only check for 10 digits
                    function validateAustrianSVNR(svnr) {
                        return /^\d{10}$/.test(svnr);
                    }
                    
                    function showSVNRError(message) {
                        let errorDiv = document.getElementById("svnr-error");
                        if (!errorDiv && svnrField) {
                            errorDiv = document.createElement("div");
                            errorDiv.id = "svnr-error";
                            errorDiv.style.color = "#dc3232";
                            errorDiv.style.fontSize = "12px";
                            errorDiv.style.marginTop = "5px";
                            svnrField.parentNode.appendChild(errorDiv);
                        }
                        if (errorDiv) {
                            errorDiv.textContent = message;
                        }
                    }
                    
                    function hideSVNRError() {
                        const errorDiv = document.getElementById("svnr-error");
                        if (errorDiv) errorDiv.remove();
                    }
                    
                    // PDF Email functionality
                    const sendEmailBtn = document.getElementById("send-pdf-email");
                    console.log("RT Employee Manager V2: Looking for send-pdf-email button...", sendEmailBtn);
                    
                    if (sendEmailBtn) {
                        console.log("RT Employee Manager V2: PDF email button found, attaching listener...");
                        
                        sendEmailBtn.addEventListener("click", function(e) {
                            console.log("RT Employee Manager V2: PDF email button clicked!");
                            e.preventDefault();
                            e.stopPropagation();
                            
                            const emailInput = document.getElementById("pdf-email");
                            const sendToCustomer = document.getElementById("send-to-kunde");
                            const sendToBookkeeping = document.getElementById("send-to-bookkeeping");
                            
                            const customerEmail = emailInput ? emailInput.value.trim() : "";
                            const shouldSendToCustomer = sendToCustomer ? sendToCustomer.checked : false;
                            const shouldSendToBookkeeping = sendToBookkeeping ? sendToBookkeeping.checked : false;
                            
                            console.log("RT Employee Manager V2: Email values:", {
                                customerEmail: customerEmail,
                                shouldSendToCustomer: shouldSendToCustomer,
                                shouldSendToBookkeeping: shouldSendToBookkeeping
                            });
                            
                            // Validation
                            if (!shouldSendToCustomer && !shouldSendToBookkeeping) {
                                alert("Bitte wählen Sie mindestens einen Empfänger aus.");
                                return;
                            }
                            
                            if (shouldSendToCustomer && !customerEmail) {
                                alert("Bitte geben Sie eine E-Mail-Adresse ein.");
                                if (emailInput) emailInput.focus();
                                return;
                            }
                            
                            // Basic email validation
                            if (shouldSendToCustomer && customerEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(customerEmail)) {
                                alert("Bitte geben Sie eine gültige E-Mail-Adresse ein.");
                                if (emailInput) emailInput.focus();
                                return;
                            }
                            
                            // Disable button and show loading
                            sendEmailBtn.disabled = true;
                            const originalText = sendEmailBtn.textContent;
                            sendEmailBtn.textContent = "Sende E-Mail...";
                            
                            // Get current post ID
                            const postId = document.getElementById("post_ID") ? document.getElementById("post_ID").value : "";
                            
                            if (!postId) {
                                alert("Fehler: Mitarbeiter-ID konnte nicht gefunden werden.");
                                sendEmailBtn.disabled = false;
                                sendEmailBtn.textContent = originalText;
                                return;
                            }
                            
                            // Send AJAX request
                            const ajaxUrl = (typeof rtEmployeeManagerV2 !== "undefined" && rtEmployeeManagerV2.ajaxurl) 
                                ? rtEmployeeManagerV2.ajaxurl 
                                : "' . $ajax_url . '";
                            const nonce = (typeof rtEmployeeManagerV2 !== "undefined" && rtEmployeeManagerV2.nonce) 
                                ? rtEmployeeManagerV2.nonce 
                                : "' . $nonce . '";
                            
                            console.log("RT Employee Manager V2: Sending AJAX request to:", ajaxUrl);
                            
                            fetch(ajaxUrl, {
                                method: "POST",
                                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                                body: new URLSearchParams({
                                    action: "email_employee_pdf",
                                    employee_id: postId,
                                    customer_email: customerEmail,
                                    send_to_customer: shouldSendToCustomer ? "1" : "",
                                    send_to_bookkeeping: shouldSendToBookkeeping ? "1" : "",
                                    nonce: nonce
                                })
                            })
                            .then(response => {
                                console.log("RT Employee Manager V2: Response status:", response.status);
                                return response.text();
                            })
                            .then(text => {
                                console.log("RT Employee Manager V2: Raw response:", text);
                                try {
                                    const data = JSON.parse(text);
                                    if (data.success) {
                                        alert("✓ " + data.data.message);
                                        // Reset form
                                        if (emailInput) emailInput.value = "";
                                        if (sendToCustomer) sendToCustomer.checked = false;
                                        if (sendToBookkeeping) sendToBookkeeping.checked = false;
                                    } else {
                                        alert("Fehler: " + (data.data || "Unbekannter Fehler"));
                                    }
                                } catch (e) {
                                    console.error("RT Employee Manager V2: JSON parse error:", e, "Response:", text);
                                    alert("Server response error: " + text.substring(0, 200));
                                }
                            })
                            .catch(error => {
                                console.error("RT Employee Manager V2: Fetch error:", error);
                                alert("Fehler beim Senden: " + error.message);
                            })
                            .finally(() => {
                                // Re-enable button
                                sendEmailBtn.disabled = false;
                                sendEmailBtn.textContent = originalText;
                            });
                        });
                        
                        console.log("RT Employee Manager V2: ✓ PDF email button event listener attached successfully");
                    } else {
                        console.warn("RT Employee Manager V2: ✗ send-pdf-email button not found on page");
                    }
                    
                    console.log("RT Employee Manager V2: ✓ Meta box with SVNR validation and email functionality fully loaded");
                });
            })(jQuery);
        ');
        
        // Enqueue the script (this actually outputs it)
        wp_enqueue_script('rt-employee-manager-v2-admin');
    }
}