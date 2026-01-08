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
        
        $pdf_url = $this->generate_simple_pdf($employee_id);
        
        if ($pdf_url) {
            wp_send_json_success(array('pdf_url' => $pdf_url));
        } else {
            wp_send_json_error('PDF generation failed');
        }
    }
    
    
    /**
     * Generate PDF and show it directly in browser
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
        
        // Get the employee post
        $employee = get_post($employee_id);
        if (!$employee || $employee->post_type !== 'angestellte_v2') {
            wp_die('Invalid employee');
        }
        
        // Pull all the employee data
        $data = $this->get_all_employee_data($employee_id);
        
        // Build HTML version (not used here but available)
        $html = $this->create_complete_html($employee, $data);
        
        // Create the actual PDF
        $pdf_content = $this->create_actual_pdf($employee, $data);
        
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
        
        // Generate PDF
        $pdf_url = $this->generate_simple_pdf($employee_id);
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
        
        // Create email message
        $message = sprintf(
            __("Sehr geehrte Damen und Herren,\n\nanbei finden Sie die Mitarbeiterdaten für %s.\n\nUnternehmen: %s\nE-Mail: %s\nArt der Beschäftigung: %s\n\nMit freundlichen Grüßen\nIhr Team", 'rt-employee-manager-v2'),
            $employee->post_title,
            $company_name,
            isset($employee_data['email']) ? $employee_data['email'] : '',
            isset($employee_data['art_des_dienstverhaltnisses']) ? $employee_data['art_des_dienstverhaltnisses'] : ''
        );
        
        // Convert URL to file path for attachment
        $upload_dir = wp_upload_dir();
        $pdf_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $pdf_url);
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $attachments = array();
        
        if (file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        }
        
        return wp_mail($to_email, $subject, $message, $headers, $attachments);
    }
    
    /**
     * Generate simple PDF
     */
    private function generate_simple_pdf($employee_id) {
        $employee = get_post($employee_id);
        if (!$employee || $employee->post_type !== 'angestellte_v2') {
            return false;
        }
        
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/employee-pdfs/';
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        // Generate simple filename
        $timestamp = time();
        $filename = "employee-{$employee_id}-{$timestamp}.html";
        $file_path = $pdf_dir . $filename;
        
        // Get complete employee data
        $data = $this->get_all_employee_data($employee_id);
        
        // Create complete HTML
        $html = $this->create_complete_html($employee, $data);
        
        if (file_put_contents($file_path, $html)) {
            update_post_meta($employee_id, '_latest_pdf_path', $file_path);
            return $upload_dir['baseurl'] . '/employee-pdfs/' . $filename;
        }
        
        return false;
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
        
        return "<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <title>Mitarbeiterdaten - {$title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 30px; }
        h1 { color: #0073aa; margin-bottom: 10px; font-size: 24px; }
        h2 { color: #333; margin-bottom: 5px; font-size: 18px; }
        .field { margin-bottom: 12px; padding: 8px; border-bottom: 1px solid #eee; }
        .label { font-weight: bold; display: inline-block; width: 250px; color: #0073aa; }
        .value { display: inline-block; color: #333; }
        .section { margin: 25px 0; }
        .section-title { font-size: 16px; font-weight: bold; color: #0073aa; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px; }
        @media print { body { margin: 0; } .header { border-bottom: 2px solid #000; } }
        .company-info { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class=\"header\">
        <h1>Mitarbeiterdatenblatt</h1>
        <h2>{$title}</h2>
        <p>Erstellt am: {$date}</p>
    </div>
    
    <div class=\"company-info\">
        <strong>" . get_bloginfo('name') . "</strong><br>
        Mitarbeiterverwaltung
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
    
    <div style=\"margin-top: 40px; text-align: center; font-size: 12px; color: #666;\">
        <hr style=\"border: 1px solid #ddd; margin: 20px 0;\">
        RT Employee Manager V2 - " . get_bloginfo('name') . "<br>
        " . home_url() . "
    </div>
</body>
</html>";
    }
    
}   