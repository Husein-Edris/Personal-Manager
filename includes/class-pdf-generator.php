<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles PDF creation and email sending for employee data
 */
class RT_PDF_Generator_V2 {
    
    public function __construct() {
        add_action('wp_ajax_generate_employee_pdf', array($this, 'ajax_generate_employee_pdf'));
        add_action('wp_ajax_email_employee_pdf', array($this, 'ajax_email_employee_pdf'));
        add_action('wp_ajax_generate_and_view_employee_pdf', array($this, 'ajax_generate_and_view_employee_pdf'));
    }
    
    /**
     * Handle AJAX request to generate PDF
     */
    public function ajax_generate_employee_pdf() {
        if (!wp_verify_nonce($_POST['nonce'], 'generate_pdf_v2')) {
            wp_send_json_error('Security error');
        }
        
        $employee_id = intval($_POST['employee_id']);
        if (!$employee_id) {
            wp_send_json_error('Invalid employee ID');
        }
        
        // Make sure user can access this employee
        if (!current_user_can('manage_options')) {
            $user = wp_get_current_user();
            if (!in_array('kunden_v2', $user->roles)) {
                wp_send_json_error('No permission');
            }
            
            $employer_id = get_post_meta($employee_id, 'employer_id', true);
            if ($employer_id != $user->ID) {
                wp_send_json_error('No permission for this employee');
            }
        }
        
        // Use unified PDF generation method
        $pdf_url = $this->generate_employee_pdf_file($employee_id);
        
        if ($pdf_url) {
            wp_send_json_success(array('pdf_url' => $pdf_url));
        } else {
            wp_send_json_error('PDF generation failed');
        }
    }
    
    
    /**
     * Generate PDF and show it directly in browser (uses unified PDF generation method)
     */
    public function ajax_generate_and_view_employee_pdf() {
        if (!wp_verify_nonce($_GET['nonce'], 'generate_view_pdf_v2')) {
            wp_die('Security error');
        }
        
        $employee_id = intval($_GET['employee_id']);
        if (!$employee_id) {
            wp_die('Invalid employee ID');
        }
        
        // Same permission check as above
        if (!current_user_can('manage_options')) {
            $user = wp_get_current_user();
            if (!in_array('kunden_v2', $user->roles)) {
                wp_die('No permission');
            }
            
            $employer_id = get_post_meta($employee_id, 'employer_id', true);
            if ($employer_id != $user->ID) {
                wp_die('No permission for this employee');
            }
        }
        
        // Generate PDF content using unified method (same as email)
        $pdf_content = $this->generate_employee_pdf_content($employee_id);
        
        if ($pdf_content === false || empty($pdf_content)) {
            wp_die('Failed to generate PDF');
        }
        
        // Get employee for filename
        $employee = get_post($employee_id);
        $filename = 'mitarbeiter-' . sanitize_title($employee->post_title) . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf_content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        echo $pdf_content;
        exit;
    }

    /**
     * Handle email sending via AJAX
     */
    public function ajax_email_employee_pdf() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'email_pdf_v2')) {
            wp_send_json_error('Security error');
        }
        
        $employee_id = intval($_POST['employee_id']);
        if (!$employee_id) {
            wp_send_json_error('Invalid employee ID');
        }
        
        // Check who's trying to send this
        $user = wp_get_current_user();
        if (!$user || $user->ID == 0) {
            wp_send_json_error('User not authenticated');
        }
        
        // Either admin or client user
        $is_admin = current_user_can('manage_options');
        $is_customer = in_array('kunden_v2', $user->roles) || in_array('kunden', $user->roles);
        
        if (!$is_admin && !$is_customer) {
            wp_send_json_error('No permission - invalid role');
        }
        
        // Clients can only email their own employees
        if (!$is_admin) {
            $employer_id = get_post_meta($employee_id, 'employer_id', true);
            if ($employer_id != $user->ID) {
                wp_send_json_error('No permission for this employee - ownership check failed');
            }
        }
        
        // Get email addresses - use isset instead of null coalescing
        $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
        $send_to_customer = !empty($_POST['send_to_customer']);
        $send_to_bookkeeping = !empty($_POST['send_to_bookkeeping']);
        
        if (!$send_to_customer && !$send_to_bookkeeping) {
            wp_send_json_error('Please select at least one recipient');
        }
        
        if ($send_to_customer && empty($customer_email)) {
            wp_send_json_error('Customer email is required');
        }
        
        // Generate PDF using unified method (saves to file for email attachment)
        $pdf_url = $this->generate_employee_pdf_file($employee_id);
        if (!$pdf_url) {
            wp_send_json_error('Failed to generate PDF');
        }
        
        // Send emails
        $sent_count = 0;
        $errors = array();
        
        $employee = get_post($employee_id);
        $subject = sprintf(__('Mitarbeiterdaten: %s', 'rt-employee-manager-v2'), $employee->post_title);
        
        // Send to customer
        if ($send_to_customer) {
            if ($this->send_pdf_email($customer_email, $subject, $pdf_url, $employee_id)) {
                $sent_count++;
            } else {
                $errors[] = __('Fehler beim Senden an Kunde', 'rt-employee-manager-v2');
            }
        }
        
        // Send to bookkeeping
        if ($send_to_bookkeeping) {
            $bookkeeping_email = get_option('rt_employee_v2_buchhaltung_email', '');
            if (!empty($bookkeeping_email)) {
                if ($this->send_pdf_email($bookkeeping_email, $subject, $pdf_url, $employee_id)) {
                    $sent_count++;
                } else {
                    $errors[] = __('Fehler beim Senden an Buchhaltung', 'rt-employee-manager-v2');
                }
            } else {
                $errors[] = __('Buchhaltung E-Mail nicht konfiguriert', 'rt-employee-manager-v2');
            }
        }
        
        if ($sent_count > 0) {
            wp_send_json_success(array(
                'sent_count' => $sent_count,
                'errors' => $errors,
                'message' => sprintf(__('%d E-Mail(s) erfolgreich versendet', 'rt-employee-manager-v2'), $sent_count)
            ));
        } else {
            wp_send_json_error('Failed to send any emails: ' . implode(', ', $errors));
        }
    }
    
    /**
     * Send PDF email with attachment
     */
    private function send_pdf_email($to_email, $subject, $pdf_url, $employee_id) {
        $employee = get_post($employee_id);
        if (!$employee) {
            return false;
        }

        // Get employee data for email content
        $employee_data = $this->get_all_employee_data($employee_id);
        $company_name = isset($employee_data['employer_name']) ? $employee_data['employer_name'] : 'Unbekannt';

        // Get template settings
        $subject_template = get_option('rt_employee_v2_email_subject_template', 'Mitarbeiterdaten: {FIRSTNAME} {LASTNAME} - {KUNDE}');
        $body_template = get_option('rt_employee_v2_email_body_template', '');

        // Use template subject with placeholders (always use template if set)
        $final_subject = $this->replace_email_placeholders($subject_template, $employee_id);
        if (empty($final_subject)) {
            $final_subject = sprintf(__('Mitarbeiterdaten: %s', 'rt-employee-manager-v2'), $employee->post_title);
        }

        // Use template body if provided, otherwise use default
        if (!empty($body_template)) {
            // Template is provided - use it (overrides default)
            $message = $this->replace_email_placeholders($body_template, $employee_id);
        } else {
            // No template - use default message
            $message = sprintf(
                __("Sehr geehrte Damen und Herren,\n\nanbei finden Sie die Mitarbeiterdaten für %s.\n\nUnternehmen: %s\nE-Mail: %s\nArt der Beschäftigung: %s\n\nMit freundlichen Grüßen\nIhr Team", 'rt-employee-manager-v2'),
                $employee->post_title,
                $company_name,
                isset($employee_data['email']) ? $employee_data['email'] : '',
                isset($employee_data['art_des_dienstverhaltnisses']) ? $employee_data['art_des_dienstverhaltnisses'] : ''
            );
        }

        // Convert URL to file path for attachment
        $upload_dir = wp_upload_dir();
        
        // Handle different URL formats (with or without domain)
        $pdf_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $pdf_url);
        
        // If the URL contains the full site URL, replace that too
        $pdf_path = str_replace(site_url('/wp-content/uploads/'), $upload_dir['basedir'] . '/', $pdf_path);
        
        // Additional check: if URL contains http/https, extract just the path
        if (strpos($pdf_url, 'http') === 0) {
            $parsed_url = parse_url($pdf_url);
            if (isset($parsed_url['path'])) {
                // Remove /wp-content/uploads from path and use basedir
                $path_parts = explode('/wp-content/uploads/', $parsed_url['path']);
                if (isset($path_parts[1])) {
                    $pdf_path = $upload_dir['basedir'] . '/' . $path_parts[1];
                }
            }
        }

        // Build email headers
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        // Get sender name and email from settings
        $sender_name = get_option('rt_employee_v2_email_sender_name', '');
        $sender_email = get_option('rt_employee_v2_email_sender_email', '');
        
        // Set From header if sender info is configured
        if (!empty($sender_name) && !empty($sender_email)) {
            $headers[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
        } elseif (!empty($sender_email)) {
            $headers[] = 'From: ' . $sender_email;
        }
        
        $attachments = array();

        if (file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        } else {
            error_log('RT Employee Manager V2: PDF file not found for attachment: ' . $pdf_path);
            error_log('RT Employee Manager V2: Original PDF URL: ' . $pdf_url);
            error_log('RT Employee Manager V2: Upload basedir: ' . $upload_dir['basedir']);
            error_log('RT Employee Manager V2: Upload baseurl: ' . $upload_dir['baseurl']);
        }

        return wp_mail($to_email, $final_subject, $message, $headers, $attachments);
    }

    /**
     * Replace placeholders in email templates
     * Returns all available placeholders for documentation
     */
    private function replace_email_placeholders($template, $employee_id) {
        $employee_data = $this->get_all_employee_data($employee_id);

        $firstname = isset($employee_data['vorname']) ? $employee_data['vorname'] : '';
        $lastname = isset($employee_data['nachname']) ? $employee_data['nachname'] : '';
        $company_name = isset($employee_data['employer_name']) ? $employee_data['employer_name'] : '';
        $current_user = wp_get_current_user();
        $sender_name = $current_user->display_name;
        
        // Get sender name from settings if available
        $sender_name_setting = get_option('rt_employee_v2_email_sender_name', '');
        if (!empty($sender_name_setting)) {
            $sender_name = $sender_name_setting;
        }

        $replacements = array(
            // Employee personal info
            '{FIRSTNAME}' => esc_html($firstname),
            '{LASTNAME}' => esc_html($lastname),
            '{EMPLOYEE_NAME}' => esc_html(trim($firstname . ' ' . $lastname)),
            '{EMPLOYEE_EMAIL}' => esc_html(isset($employee_data['email']) ? $employee_data['email'] : ''),
            '{SVNR}' => esc_html(isset($employee_data['sozialversicherungsnummer']) ? $employee_data['sozialversicherungsnummer'] : ''),
            '{GEBURTSDATUM}' => esc_html(isset($employee_data['geburtsdatum']) ? $employee_data['geburtsdatum'] : ''),
            
            // Company/Employer info
            '{KUNDE}' => esc_html($company_name),
            '{COMPANY_NAME}' => esc_html($company_name),
            '{EMPLOYER_NAME}' => esc_html($company_name),
            
            // Employment info
            '{BESCHAEFTIGUNG}' => esc_html(isset($employee_data['art_des_dienstverhaltnisses']) ? $employee_data['art_des_dienstverhaltnisses'] : ''),
            '{ART_DER_BESCHAEFTIGUNG}' => esc_html(isset($employee_data['art_des_dienstverhaltnisses']) ? $employee_data['art_des_dienstverhaltnisses'] : ''),
            '{TATIGKEIT}' => esc_html(isset($employee_data['bezeichnung_der_tatigkeit']) ? $employee_data['bezeichnung_der_tatigkeit'] : ''),
            '{EINTRITTSDATUM}' => esc_html(isset($employee_data['eintrittsdatum']) ? $employee_data['eintrittsdatum'] : ''),
            '{STATUS}' => esc_html(isset($employee_data['status']) ? $employee_data['status'] : ''),
            
            // Sender info
            '{SENDER_NAME}' => esc_html($sender_name),
            
            // Date
            '{DATE}' => esc_html(date_i18n('d.m.Y')),
            '{TIME}' => esc_html(date_i18n('H:i')),
            '{DATETIME}' => esc_html(date_i18n('d.m.Y H:i')),
        );

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Get list of all available email placeholders for documentation
     * Public method so it can be called from admin dashboard
     */
    public function get_email_placeholders_list() {
        return array(
            'Employee Personal Info' => array(
                '{FIRSTNAME}' => 'Employee first name (Vorname)',
                '{LASTNAME}' => 'Employee last name (Nachname)',
                '{EMPLOYEE_NAME}' => 'Full employee name (Vorname Nachname)',
                '{EMPLOYEE_EMAIL}' => 'Employee email address',
                '{SVNR}' => 'Social security number (Sozialversicherungsnummer)',
                '{GEBURTSDATUM}' => 'Date of birth (Geburtsdatum)',
            ),
            'Company/Employer Info' => array(
                '{KUNDE}' => 'Customer/Company name',
                '{COMPANY_NAME}' => 'Company name (same as {KUNDE})',
                '{EMPLOYER_NAME}' => 'Employer name (same as {KUNDE})',
            ),
            'Employment Info' => array(
                '{BESCHAEFTIGUNG}' => 'Type of employment (Art des Dienstverhältnisses)',
                '{ART_DER_BESCHAEFTIGUNG}' => 'Type of employment (full name)',
                '{TATIGKEIT}' => 'Job title/Activity (Bezeichnung der Tätigkeit)',
                '{EINTRITTSDATUM}' => 'Start date (Eintrittsdatum)',
                '{STATUS}' => 'Employment status',
            ),
            'Sender Info' => array(
                '{SENDER_NAME}' => 'Name of person sending the email (from settings or current user)',
            ),
            'Date/Time' => array(
                '{DATE}' => 'Current date (d.m.Y format)',
                '{TIME}' => 'Current time (H:i format)',
                '{DATETIME}' => 'Current date and time (d.m.Y H:i format)',
            ),
        );
    }
    
    /**
     * UNIFIED METHOD: Generate PDF content from employee ID (single source of truth)
     * Used by both view and email functions - all PDF generation goes through here
     * 
     * @param int $employee_id The employee post ID
     * @return string|false PDF binary content on success, false on failure
     */
    private function generate_employee_pdf_content($employee_id) {
        // Get employee post
        $employee = get_post($employee_id);
        if (!$employee || $employee->post_type !== 'angestellte_v2') {
            return false;
        }
        
        // Get all employee data
        $data = $this->get_all_employee_data($employee_id);
        
        // Create HTML template (single source for all PDFs)
        $html = $this->create_complete_html($employee, $data);
        
        // Generate PDF from HTML using DomPDF (preferred method)
        $pdf_content = $this->generate_pdf_from_html($html);
        
        // Fallback to manual PDF generation if DomPDF fails
        if ($pdf_content === false) {
            $pdf_content = $this->create_actual_pdf($employee, $data);
        }
        
        return $pdf_content;
    }
    
    /**
     * Generate PDF file and save to uploads directory (for email attachments)
     * Uses the unified generate_employee_pdf_content() method
     * 
     * @param int $employee_id The employee post ID
     * @return string|false PDF file URL on success, false on failure
     */
    private function generate_employee_pdf_file($employee_id) {
        // Generate PDF content using unified method
        $pdf_content = $this->generate_employee_pdf_content($employee_id);
        
        if ($pdf_content === false || empty($pdf_content)) {
            return false;
        }
        
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/employee-pdfs/';
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        // Generate PDF filename
        $timestamp = time();
        $filename = "employee-{$employee_id}-{$timestamp}.pdf";
        $file_path = $pdf_dir . $filename;
        
        // Save PDF to file
        if (file_put_contents($file_path, $pdf_content)) {
            update_post_meta($employee_id, '_latest_pdf_path', $file_path);
            update_post_meta($employee_id, '_latest_pdf_url', $upload_dir['baseurl'] . '/employee-pdfs/' . $filename);
            return $upload_dir['baseurl'] . '/employee-pdfs/' . $filename;
        }
        
        return false;
    }
    
    /**
     * Generate PDF content from HTML using DomPDF
     * 
     * @param string $html HTML content to convert
     * @return string|false PDF binary content on success, false on failure
     */
    private function generate_pdf_from_html($html) {
        // Check if DomPDF is available
        if (!class_exists('\Dompdf\Dompdf')) {
            error_log('RT Employee Manager V2: DomPDF not found. Please run composer install.');
            return false;
        }
        
        try {
            // Use DomPDF to convert HTML to PDF
            $dompdf = new \Dompdf\Dompdf();
            
            // Configure DomPDF options for better image handling
            $options = $dompdf->getOptions();
            $options->set('isRemoteEnabled', true); // Allow remote/local URLs
            $options->set('isHtml5ParserEnabled', true); // Enable HTML5 parser
            $options->set('isPhpEnabled', false); // Security: disable PHP execution
            $options->set('isFontSubsettingEnabled', true); // Enable font subsetting
            $options->set('defaultFont', 'DejaVu Sans'); // Use default font
            
            $dompdf->setOptions($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            // Return PDF content
            return $dompdf->output();
        } catch (Exception $e) {
            error_log('RT Employee Manager V2: PDF generation error: ' . $e->getMessage());
            error_log('RT Employee Manager V2: PDF generation stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Get all employee data
     */
    private function get_all_employee_data($employee_id) {
        $fields = array(
            'anrede', 'vorname', 'nachname', 'sozialversicherungsnummer', 'geburtsdatum',
            'staatsangehoerigkeit', 'email', 'personenstand', 'adresse_strasse', 
            'adresse_plz', 'adresse_ort', 'eintrittsdatum', 'art_des_dienstverhaltnisses',
            'bezeichnung_der_tatigkeit', 'arbeitszeit_pro_woche', 'gehaltlohn', 'type',
            'arbeitstagen', 'anmerkungen', 'status', 'employer_id'
        );
        
        $data = array();
        foreach ($fields as $field) {
            if ($field === 'arbeitstagen') {
                $value = get_post_meta($employee_id, $field, true);
                $data[$field] = is_array($value) ? $value : array();
            } else {
                $data[$field] = get_post_meta($employee_id, $field, true);
            }
        }
        
        // Get employer info if available
        if (!empty($data['employer_id'])) {
            $employer = get_user_by('id', $data['employer_id']);
            if ($employer) {
                $data['employer_name'] = get_user_meta($data['employer_id'], 'company_name', true) ?: $employer->display_name;
                $data['employer_contact'] = get_user_meta($data['employer_id'], 'contact_name', true);
                $data['employer_email'] = $employer->user_email;
                $data['employer_phone'] = get_user_meta($data['employer_id'], 'phone', true);
                $data['employer_uid'] = get_user_meta($data['employer_id'], 'uid_number', true);
                $data['employer_address'] = get_user_meta($data['employer_id'], 'company_address', true);
            }
        }
        
        return $data;
    }
    
    /**
     * Create actual PDF content
     */
    private function create_actual_pdf($employee, $data) {
        // Working days translation
        $working_days_german = array(
            'monday' => 'Montag', 'tuesday' => 'Dienstag', 'wednesday' => 'Mittwoch',
            'thursday' => 'Donnerstag', 'friday' => 'Freitag', 'saturday' => 'Samstag', 'sunday' => 'Sonntag'
        );
        
        $working_days_list = '';
        if (!empty($data['arbeitstagen']) && is_array($data['arbeitstagen'])) {
            $days = array();
            foreach ($data['arbeitstagen'] as $day) {
                $days[] = isset($working_days_german[$day]) ? $working_days_german[$day] : $day;
            }
            $working_days_list = implode(', ', $days);
        }
        
        $title = $employee->post_title;
        $date = date('d.m.Y H:i');
        $company = get_bloginfo('name');
        
        // Get settings for better template
        $company_address = get_option('rt_employee_v2_company_address', '');
        $pdf_header = get_option('rt_employee_v2_pdf_template_header', '');
        $pdf_footer = get_option('rt_employee_v2_pdf_template_footer', '');
        
        // Create PDF using basic PDF structure
        $pdf = "%PDF-1.4\n";
        
        // PDF objects
        $pdf .= "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n\n";
        
        $pdf .= "2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n\n";
        
        // Header with professional black line
        $content = "q\n1 0 0 1 0 0 cm\n0 0 0 RG\n3 w\n72 770 m\n540 770 l\nS\nQ\n";
        
        // Content stream with enhanced header
        $content .= "BT\n/F2 20 Tf\n72 745 Td\n(MITARBEITERDATENBLATT) Tj\n";
        
        // Horizontal line under title
        $content .= "ET\nq\n0 0 0 RG\n1 w\n72 735 m\n400 735 l\nS\nQ\nBT\n";
        
        // Employer name header - right aligned
        $employer_name = !empty($data['employer_name']) ? $data['employer_name'] : $company;
        $content .= "/F2 12 Tf\n450 745 Td\n(" . $this->clean_text($employer_name) . ") Tj\n";
        
        // Back to left alignment for main content
        $content .= "ET\nBT\n72 700 Td\n";
        
        // Custom header text with box
        if (!empty($pdf_header)) {
            $content .= "ET\nq\n0.9 0.9 0.9 rg\n72 685 468 20 re\nf\nQ\n";
            $content .= "q\n0 0 0 RG\n1 w\n72 685 468 20 re\nS\nQ\nBT\n";
            $content .= "/F2 11 Tf\n77 690 Td\n(" . $this->clean_text($pdf_header) . ") Tj\n";
            $content .= "ET\nBT\n72 655 Td\n";
        } else {
            $content .= "0 -45 Td\n";
        }
        
        // Employee name with emphasis
        $content .= "/F2 16 Tf\n(MITARBEITER: " . strtoupper($this->clean_text($title)) . ") Tj\n";
        $content .= "0 -20 Td\n/F1 10 Tf\n(Erstellt am: " . $date . ") Tj\n";
        
        // Add spacing before sections
        $content .= "0 -35 Td\n";
        
        // Personal data section with underline and proper spacing
        $content .= "/F2 14 Tf\n(PERSOENLICHE DATEN) Tj\n";
        $content .= "ET\nq\n0 0 0 RG\n1 w\n72 " . (655 - 35 - 5) . " m\n200 " . (655 - 35 - 5) . " l\nS\nQ\n";
        
        $fields_personal = array(
            'Anrede' => $data['anrede'] ?: '-',
            'Vorname' => $data['vorname'] ?: '-',
            'Nachname' => $data['nachname'] ?: '-',
            'Sozialversicherungsnummer' => $data['sozialversicherungsnummer'] ?: '-',
            'Geburtsdatum' => $data['geburtsdatum'] ?: '-',
            'Staatsangehoerigkeit' => $data['staatsangehoerigkeit'] ?: '-',
            'E-Mail' => $data['email'] ?: '-',
            'Personenstand' => $data['personenstand'] ?: '-'
        );
        
        $y_position = 655 - 35 - 40; // Increased spacing after section header
        $field_count = 0;
        foreach ($fields_personal as $label => $value) {
            $current_y = $y_position - ($field_count * 18);
            
            // Position label
            $content .= "ET\nBT\n72 " . $current_y . " Td\n";
            $content .= "/F2 9 Tf\n(" . strtoupper($label) . ":) Tj\n";
            
            // Position value next to label
            $content .= "ET\nBT\n240 " . $current_y . " Td\n";
            $content .= "/F1 10 Tf\n(" . $this->clean_text($value) . ") Tj\n";
            
            $field_count++;
        }
        
        // Address section with professional styling
        $address_y_start = $y_position - (count($fields_personal) * 18) - 35;
        $content .= "ET\nBT\n72 " . $address_y_start . " Td\n";
        $content .= "/F2 14 Tf\n(ADRESSE) Tj\n";
        $content .= "ET\nq\n0 0 0 RG\n1 w\n72 " . ($address_y_start - 5) . " m\n200 " . ($address_y_start - 5) . " l\nS\nQ\n";
        
        $fields_address = array(
            'Strasse' => $data['adresse_strasse'],
            'PLZ' => $data['adresse_plz'], 
            'Ort' => $data['adresse_ort']
        );
        
        $address_field_count = 0;
        foreach ($fields_address as $label => $value) {
            $current_y = $address_y_start - 25 - ($address_field_count * 18);
            
            // Position label
            $content .= "BT\n72 " . $current_y . " Td\n";
            $content .= "/F2 9 Tf\n(" . strtoupper($label) . ":) Tj\n";
            
            // Position value next to label
            $content .= "ET\nBT\n240 " . $current_y . " Td\n";
            $content .= "/F1 10 Tf\n(" . $this->clean_text($value ?: '-') . ") Tj\n";
            $content .= "ET\n";
            
            $address_field_count++;
        }
        
        // Employment section with professional styling
        $employment_y_start = $address_y_start - (count($fields_address) * 18) - 35;
        $content .= "BT\n72 " . $employment_y_start . " Td\n";
        $content .= "/F2 14 Tf\n(BESCHAEFTIGUNGSDATEN) Tj\n";
        $content .= "ET\nq\n0 0 0 RG\n1 w\n72 " . ($employment_y_start - 5) . " m\n250 " . ($employment_y_start - 5) . " l\nS\nQ\n";
        
        $fields_employment = array(
            'Eintrittsdatum' => $data['eintrittsdatum'],
            'Art des Dienstverhaeltnisses' => $data['art_des_dienstverhaltnisses'],
            'Taetigkeit' => $data['bezeichnung_der_tatigkeit'],
            'Arbeitszeit/Woche' => $data['arbeitszeit_pro_woche'] ? $data['arbeitszeit_pro_woche'] . ' Std.' : '',
            'Gehalt/Lohn' => $data['gehaltlohn'] ? $data['gehaltlohn'] . ' EUR (' . ($data['type'] ?: 'Brutto') . ')' : '',
            'Status' => $data['status'],
            'Arbeitstage' => $working_days_list
        );
        
        $employment_field_count = 0;
        foreach ($fields_employment as $label => $value) {
            if (!empty($value)) {
                $current_y = $employment_y_start - 25 - ($employment_field_count * 18);
                
                // Position label
                $content .= "BT\n72 " . $current_y . " Td\n";
                $content .= "/F2 9 Tf\n(" . strtoupper($label) . ":) Tj\n";
                
                // Position value next to label
                $content .= "ET\nBT\n240 " . $current_y . " Td\n";
                $content .= "/F1 10 Tf\n(" . $this->clean_text($value) . ") Tj\n";
                $content .= "ET\n";
                
                $employment_field_count++;
            }
        }
        
        // Employer info with professional styling
        $employer_y = $employment_y_start - ($employment_field_count * 18) - 35;
        if (!empty($data['employer_name'])) {
            $content .= "BT\n72 " . $employer_y . " Td\n";
            $content .= "/F2 9 Tf\n(ARBEITGEBER:) Tj\n";
            $content .= "ET\nBT\n240 " . $employer_y . " Td\n";
            $content .= "/F1 10 Tf\n(" . $this->clean_text($data['employer_name']) . ") Tj\n";
            $content .= "ET\n";
            $employer_y -= 18; // Adjust for next section
        }
        
        // Notes section with professional styling
        if (!empty($data['anmerkungen'])) {
            $notes_y_start = $employer_y - 35;
            $content .= "BT\n72 " . $notes_y_start . " Td\n";
            $content .= "/F2 14 Tf\n(ANMERKUNGEN) Tj\n";
            $content .= "ET\nq\n0 0 0 RG\n1 w\n72 " . ($notes_y_start - 5) . " m\n200 " . ($notes_y_start - 5) . " l\nS\nQ\n";
            
            // Create gray background box for notes
            $content .= "q\n0.95 0.95 0.95 rg\n72 " . ($notes_y_start - 45) . " 468 35 re\nf\nQ\n";
            $content .= "q\n0 0 0 RG\n1 w\n72 " . ($notes_y_start - 45) . " 468 35 re\nS\nQ\n";
            
            $notes = $this->clean_text($data['anmerkungen']);
            $content .= "BT\n77 " . ($notes_y_start - 25) . " Td\n/F1 9 Tf\n(" . substr($notes, 0, 200) . ") Tj\nET\n";
        }
        
        // Professional footer section
        $content .= "ET\nBT\n72 100 Td\n";
        
        // Footer separator line
        $content .= "ET\nq\n0.8 0.8 0.8 RG\n1 w\n72 110 m\n540 110 l\nS\nQ\nBT\n";
        $content .= "72 90 Td\n";
        
        // Company address in footer
        if (!empty($company_address)) {
            $content .= "/F2 9 Tf\n(" . $this->clean_text($company) . ") Tj\n";
            $address_lines = explode("\n", $company_address);
            foreach ($address_lines as $line) {
                if (!empty(trim($line))) {
                    $content .= "0 -10 Td\n/F1 8 Tf\n(" . $this->clean_text(trim($line)) . ") Tj\n";
                }
            }
            $content .= "0 -15 Td\n";
        }
        
        // Custom footer
        if (!empty($pdf_footer)) {
            $content .= "/F1 9 Tf\n(" . $this->clean_text($pdf_footer) . ") Tj\n";
            $content .= "0 -12 Td\n";
        }
        
        // Default footer with professional spacing
        $content .= "0 -10 Td\n(" . home_url() . ") Tj\n";
        
        $content .= "ET\n";
        
        $content_length = strlen($content);
        
        $pdf .= "3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n";
        $pdf .= "/Resources <</Font <<\n";
        $pdf .= "/F1 <</Type /Font /Subtype /Type1 /BaseFont /Helvetica>>\n";
        $pdf .= "/F2 <</Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold>>\n";
        $pdf .= ">>>>\n";
        $pdf .= ">>\nendobj\n\n";
        
        $pdf .= "4 0 obj\n<<\n/Length " . $content_length . "\n>>\nstream\n";
        $pdf .= $content;
        $pdf .= "\nendstream\nendobj\n\n";
        
        // Cross-reference table and trailer
        $pdf .= "xref\n0 5\n0000000000 65535 f \n";
        $pdf .= sprintf("%010d 00000 n \n", strpos($pdf, "1 0 obj"));
        $pdf .= sprintf("%010d 00000 n \n", strpos($pdf, "2 0 obj"));
        $pdf .= sprintf("%010d 00000 n \n", strpos($pdf, "3 0 obj"));
        $pdf .= sprintf("%010d 00000 n \n", strpos($pdf, "4 0 obj"));
        
        $xref_pos = strpos($pdf, "xref");
        $pdf .= "trailer\n<<\n/Size 5\n/Root 1 0 R\n>>\n";
        $pdf .= "startxref\n" . $xref_pos . "\n%%EOF\n";
        
        return $pdf;
    }
    
    /**
     * Clean text for PDF
     */
    private function clean_text($text) {
        // Convert special characters
        $text = str_replace(array('ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'), 
                           array('ae', 'oe', 'ue', 'ss', 'Ae', 'Oe', 'Ue'), $text);
        // Escape PDF special characters
        $text = str_replace(array('(', ')', '\\'), array('\\(', '\\)', '\\\\'), $text);
        return $text;
    }
    
    /**
     * Create complete HTML with all fields
     */
    private function create_complete_html($employee, $data) {
        $title = $employee->post_title;
        $date = date('d.m.Y H:i');
        
        // Working days translation
        $working_days_german = array(
            'monday' => 'Montag',
            'tuesday' => 'Dienstag', 
            'wednesday' => 'Mittwoch',
            'thursday' => 'Donnerstag',
            'friday' => 'Freitag',
            'saturday' => 'Samstag',
            'sunday' => 'Sonntag'
        );
        
        $working_days_list = '';
        if (!empty($data['arbeitstagen']) && is_array($data['arbeitstagen'])) {
            $days = array();
            foreach ($data['arbeitstagen'] as $day) {
                $days[] = isset($working_days_german[$day]) ? $working_days_german[$day] : $day;
            }
            $working_days_list = implode(', ', $days);
        }
        
        // Get logo - always use data URI for maximum compatibility with DomPDF
        $logo_id = get_option('rt_employee_v2_pdf_logo', 0);
        $logo_src = '';
        if ($logo_id) {
            $logo_path = get_attached_file($logo_id);
            $logo_url = wp_get_attachment_image_url($logo_id, 'full');
            
            // Get MIME type - critical for data URI to work
            $mime_type = get_post_mime_type($logo_id);
            if (empty($mime_type) && $logo_path) {
                $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
                $mime_map = array(
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    'svg' => 'image/svg+xml'
                );
                $mime_type = isset($mime_map[$ext]) ? $mime_map[$ext] : 'image/jpeg';
            }
            
            // Ensure we have a valid MIME type before proceeding
            if (empty($mime_type)) {
                error_log('RT Employee Manager V2: Could not determine MIME type for logo ID: ' . $logo_id);
            }
            
            // Read image file and create data URI - most reliable method for DomPDF
            if ($logo_path && file_exists($logo_path) && !empty($mime_type)) {
                $image_data = @file_get_contents($logo_path);
                if ($image_data !== false && !empty($image_data)) {
                    // Validate that we have actual image data
                    // Check file signature for common formats
                    $valid_signature = false;
                    if (strpos($mime_type, 'jpeg') !== false) {
                        $valid_signature = (substr($image_data, 0, 3) === "\xFF\xD8\xFF");
                    } elseif (strpos($mime_type, 'png') !== false) {
                        $valid_signature = (substr($image_data, 0, 8) === "\x89PNG\r\n\x1a\n");
                    } elseif (strpos($mime_type, 'gif') !== false) {
                        $valid_signature = (substr($image_data, 0, 6) === "GIF87a" || substr($image_data, 0, 6) === "GIF89a");
                    } elseif (strpos($mime_type, 'svg') !== false) {
                        // SVG starts with <?xml or <svg
                        $valid_signature = (stripos(substr($image_data, 0, 100), '<svg') !== false || stripos(substr($image_data, 0, 100), '<?xml') !== false);
                    } else {
                        // For other formats, trust the MIME type
                        $valid_signature = true;
                    }
                    
                    if ($valid_signature) {
                        // Create data URI - most reliable method for DomPDF
                        $base64 = base64_encode($image_data);
                        if (!empty($base64)) {
                            $logo_src = 'data:' . trim($mime_type) . ';base64,' . $base64;
                            error_log('RT Employee Manager V2: Created data URI for logo. MIME: ' . $mime_type . ', Size: ' . strlen($image_data) . ' bytes');
                        } else {
                            error_log('RT Employee Manager V2: Failed to base64 encode logo image');
                        }
                    } else {
                        error_log('RT Employee Manager V2: Logo file signature does not match MIME type: ' . $mime_type);
                    }
                } else {
                    error_log('RT Employee Manager V2: Failed to read logo file at: ' . ($logo_path ?: 'N/A'));
                }
            } else {
                error_log('RT Employee Manager V2: Logo file not accessible. Path: ' . ($logo_path ?: 'N/A') . ', Exists: ' . ($logo_path && file_exists($logo_path) ? 'yes' : 'no'));
            }
            
            // Fallback to absolute URL if data URI failed or file not found
            if (empty($logo_src) && $logo_url) {
                $logo_src = (strpos($logo_url, 'http') === 0) ? $logo_url : site_url($logo_url);
                error_log('RT Employee Manager V2: Using absolute URL fallback: ' . $logo_src);
            }
        } else {
            error_log('RT Employee Manager V2: No logo ID set in options');
        }
        
        // Get header and footer text from settings
        $pdf_header_text = get_option('rt_employee_v2_pdf_template_header', '');
        $pdf_footer_text = get_option('rt_employee_v2_pdf_template_footer', '');
        
        // Default header text if not set
        if (empty($pdf_header_text)) {
            $pdf_header_text = 'Mitarbeiterverwaltung';
        }
        
        // Default footer text if not set
        if (empty($pdf_footer_text)) {
            $pdf_footer_text = 'RT Employee Manager V2 - ' . get_bloginfo('name') . "\n" . home_url();
        }
        
        return "<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <title>Mitarbeiterdaten - {$title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; color: #333; }
        .pdf-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #333; }
        .pdf-header-left { flex: 0 0 auto; }
        .pdf-header-right { flex: 1; text-align: right; padding-top: 10px; }
        .pdf-logo { max-height: 120px; max-width: 200px; width: auto; height: auto; object-fit: contain; }
        .pdf-header-text { font-size: 16px; font-weight: bold; color: #333; white-space: pre-line; }
        .employee-info-box { margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; }
        .employee-info-box h1 { margin: 0 0 10px 0; color: #0073aa; font-size: 24px; font-weight: bold; }
        .employee-info-box h2 { margin: 0 0 5px 0; color: #333; font-size: 18px; font-weight: normal; }
        .employee-info-box p { margin: 0; color: #666; font-size: 14px; }
        .field { margin-bottom: 12px; padding: 8px; border-bottom: 1px solid #eee; }
        .label { font-weight: bold; display: inline-block; width: 250px; color: #0073aa; }
        .value { display: inline-block; color: #333; }
        .section { margin: 25px 0; }
        .section-title { font-size: 16px; font-weight: bold; color: #0073aa; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px; }
        .pdf-footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666; white-space: pre-line; }
        @media print { body { margin: 0; } .pdf-header { border-bottom: 2px solid #000; } }
    </style>
</head>
<body>
    <div class=\"pdf-header\">
        <div class=\"pdf-header-left\">" . (!empty($logo_src) ? '<img src="' . htmlspecialchars($logo_src, ENT_QUOTES | ENT_XML1, 'UTF-8') . '" alt="Logo" class="pdf-logo" style="max-height: 120px; max-width: 200px; height: auto; width: auto;" />' : '<!-- No logo set -->') . "</div>
        <div class=\"pdf-header-right\">
            <div class=\"pdf-header-text\">" . nl2br(esc_html($pdf_header_text)) . "</div>
        </div>
    </div>
    
    <div class=\"employee-info-box\">
        <h1>Mitarbeiterdatenblatt</h1>
        <h2>{$title}</h2>
        <p>Erstellt am: {$date}</p>
    </div>
    
    <div class=\"section\">
        <div class=\"section-title\">Persönliche Daten</div>
        
        <div class=\"field\">
            <span class=\"label\">Anrede:</span>
            <span class=\"value\">" . esc_html($data['anrede'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Vorname:</span>
            <span class=\"value\">" . esc_html($data['vorname'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Nachname:</span>
            <span class=\"value\">" . esc_html($data['nachname'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Sozialversicherungsnummer:</span>
            <span class=\"value\">" . esc_html($data['sozialversicherungsnummer'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Geburtsdatum:</span>
            <span class=\"value\">" . esc_html($data['geburtsdatum'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Staatsangehörigkeit:</span>
            <span class=\"value\">" . esc_html($data['staatsangehoerigkeit'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">E-Mail-Adresse:</span>
            <span class=\"value\">" . esc_html($data['email'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Personenstand:</span>
            <span class=\"value\">" . esc_html($data['personenstand'] ?: '-') . "</span>
        </div>
    </div>
    
    <div class=\"section\">
        <div class=\"section-title\">Adresse</div>
        
        <div class=\"field\">
            <span class=\"label\">Straße:</span>
            <span class=\"value\">" . esc_html($data['adresse_strasse'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">PLZ:</span>
            <span class=\"value\">" . esc_html($data['adresse_plz'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Ort:</span>
            <span class=\"value\">" . esc_html($data['adresse_ort'] ?: '-') . "</span>
        </div>
    </div>
    
    <div class=\"section\">
        <div class=\"section-title\">Beschäftigungsdaten</div>
        
        <div class=\"field\">
            <span class=\"label\">Eintrittsdatum:</span>
            <span class=\"value\">" . esc_html($data['eintrittsdatum'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Art des Dienstverhältnisses:</span>
            <span class=\"value\">" . esc_html($data['art_des_dienstverhaltnisses'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Bezeichnung der Tätigkeit:</span>
            <span class=\"value\">" . esc_html($data['bezeichnung_der_tatigkeit'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Arbeitszeit pro Woche:</span>
            <span class=\"value\">" . esc_html($data['arbeitszeit_pro_woche'] ? $data['arbeitszeit_pro_woche'] . ' Stunden' : '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Gehalt/Lohn (€):</span>
            <span class=\"value\">" . esc_html($data['gehaltlohn'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Beschäftigungsstatus:</span>
            <span class=\"value\">" . esc_html($data['status'] ?: 'Beschäftigt') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Gehalt/Lohn:</span>
            <span class=\"value\">" . esc_html($data['type'] ?: '-') . "</span>
        </div>
        
        <div class=\"field\">
            <span class=\"label\">Arbeitstage:</span>
            <span class=\"value\">" . esc_html($working_days_list ?: '-') . "</span>
        </div>
        
        " . (!empty($data['employer_name']) ? "
        <div class=\"field\">
            <span class=\"label\">Arbeitgeber:</span>
            <span class=\"value\">" . esc_html($data['employer_name']) . "</span>
        </div>
        " : "") . "
    </div>
    
    " . (!empty($data['anmerkungen']) ? "
    <div class=\"section\">
        <div class=\"section-title\">Anmerkungen</div>
        <div style=\"padding: 10px; background: #f9f9f9; border: 1px solid #ddd;\">
            " . nl2br(esc_html($data['anmerkungen'])) . "
        </div>
    </div>
    " : "") . "
    
    <div class=\"pdf-footer\">
        " . nl2br(esc_html($pdf_footer_text)) . "
    </div>
</body>
</html>";
    }
    
}   