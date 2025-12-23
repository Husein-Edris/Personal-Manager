<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple PDF Generator - Creates actual PDF files
 */
class RT_PDF_Generator_V2 {
    
    public function __construct() {
        add_action('wp_ajax_generate_employee_pdf', array($this, 'ajax_generate_employee_pdf'));
        add_action('wp_ajax_download_employee_pdf', array($this, 'ajax_download_employee_pdf'));
        add_action('wp_ajax_email_employee_pdf', array($this, 'ajax_email_employee_pdf'));
    }
    
    /**
     * Generate PDF for employee (AJAX endpoint)
     */
    public function ajax_generate_employee_pdf() {
        if (!wp_verify_nonce($_POST['nonce'], 'generate_pdf_v2')) {
            wp_send_json_error(__('Sicherheitsfehler.', 'rt-employee-manager-v2'));
        }
        
        $employee_id = intval($_POST['employee_id']);
        if (!$employee_id || !$this->user_can_access_employee($employee_id)) {
            wp_send_json_error(__('Keine Berechtigung.', 'rt-employee-manager-v2'));
        }
        
        $pdf_url = $this->generate_employee_pdf($employee_id);
        
        if ($pdf_url) {
            wp_send_json_success(array('pdf_url' => $pdf_url));
        } else {
            wp_send_json_error(__('Fehler beim Erstellen der PDF.', 'rt-employee-manager-v2'));
        }
    }
    
    /**
     * Download employee PDF (AJAX endpoint)
     */
    public function ajax_download_employee_pdf() {
        if (!wp_verify_nonce($_GET['nonce'], 'download_pdf_v2')) {
            wp_die(__('Sicherheitsfehler.', 'rt-employee-manager-v2'));
        }
        
        $employee_id = intval($_GET['employee_id']);
        if (!$employee_id || !$this->user_can_access_employee($employee_id)) {
            wp_die(__('Keine Berechtigung.', 'rt-employee-manager-v2'));
        }
        
        $this->serve_employee_pdf($employee_id);
    }
    
    /**
     * Email employee PDF (AJAX endpoint)
     */
    public function ajax_email_employee_pdf() {
        if (!wp_verify_nonce($_POST['nonce'], 'email_pdf_v2')) {
            wp_send_json_error(__('Sicherheitsfehler.', 'rt-employee-manager-v2'));
        }
        
        $employee_id = intval($_POST['employee_id']);
        $email_address = sanitize_email($_POST['email']);
        
        if (!$employee_id || !$this->user_can_access_employee($employee_id)) {
            wp_send_json_error(__('Keine Berechtigung.', 'rt-employee-manager-v2'));
        }
        
        $result = $this->email_employee_pdf($employee_id, $email_address);
        
        if ($result) {
            wp_send_json_success(__('PDF wurde erfolgreich versendet.', 'rt-employee-manager-v2'));
        } else {
            wp_send_json_error(__('Fehler beim Versenden der E-Mail.', 'rt-employee-manager-v2'));
        }
    }
    
    /**
     * Check if user can access employee
     */
    private function user_can_access_employee($employee_id) {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        $user = wp_get_current_user();
        if (in_array('kunden_v2', $user->roles)) {
            $employer_id = get_post_meta($employee_id, 'employer_id', true);
            return $employer_id == $user->ID;
        }
        
        return false;
    }
    
    /**
     * Generate PDF for employee
     */
    public function generate_employee_pdf($employee_id) {
        $employee = get_post($employee_id);
        if (!$employee || $employee->post_type !== 'angestellte_v2') {
            return false;
        }
        
        // Get employee data
        $data = $this->get_employee_data($employee_id);
        
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/employee-pdfs/';
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        // Generate filename - HTML that can be printed to PDF
        $filename = 'employee-' . $employee_id . '-' . time() . '.html';
        $file_path = $pdf_dir . $filename;
        
        // Create HTML content optimized for printing to PDF
        $pdf_content = $this->create_simple_pdf($employee, $data);
        
        if (file_put_contents($file_path, $pdf_content)) {
            // Store file path for later access
            update_post_meta($employee_id, '_latest_pdf_path', $file_path);
            update_post_meta($employee_id, '_latest_pdf_url', $upload_dir['baseurl'] . '/employee-pdfs/' . $filename);
            
            // PDF created successfully
            return $upload_dir['baseurl'] . '/employee-pdfs/' . $filename;
        }
        
        // Failed to write file
        return false;
    }
    
    /**
     * Create simple PDF using HTML-to-print approach (more reliable)
     */
    private function create_simple_pdf($employee, $data) {
        // Create HTML content that browsers can print to PDF
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Mitarbeiterdaten - ' . esc_html($employee->post_title) . '</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            line-height: 1.4;
            color: #333;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 2px solid #333; 
            padding-bottom: 15px; 
        }
        h1 { 
            color: #0073aa; 
            margin-bottom: 10px; 
        }
        h2 { 
            color: #333; 
            margin-bottom: 5px; 
        }
        .field { 
            margin-bottom: 12px; 
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .label { 
            font-weight: bold; 
            display: inline-block; 
            width: 250px; 
            color: #0073aa;
        }
        .value { 
            display: inline-block; 
            color: #333;
        }
        .date {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 10px;
        }
        @media print {
            body { margin: 0; }
            .header { border-bottom: 2px solid #000; }
        }
        .company-info {
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Mitarbeiterdatenblatt</h1>
        <h2>' . esc_html($employee->post_title) . '</h2>
        <div class="date">Erstellt am: ' . date('d.m.Y H:i') . '</div>
    </div>
    
    <div class="company-info">
        <strong>' . get_bloginfo('name') . '</strong><br>
        Mitarbeiterverwaltung
    </div>';

        $fields = array(
            'vorname' => 'Vorname',
            'nachname' => 'Nachname', 
            'email' => 'E-Mail',
            'sozialversicherungsnummer' => 'Sozialversicherungsnummer',
            'geburtsdatum' => 'Geburtsdatum',
            'staatsangehoerigkeit' => 'Staatsangehörigkeit',
            'eintrittsdatum' => 'Eintrittsdatum',
            'art_des_dienstverhaltnisses' => 'Art des Dienstverhältnisses',
            'bezeichnung_der_tatigkeit' => 'Bezeichnung der Tätigkeit',
            'arbeitszeit_pro_woche' => 'Arbeitszeit pro Woche',
            'gehaltlohn' => 'Gehalt/Lohn'
        );
        
        foreach ($fields as $key => $label) {
            $value = !empty($data[$key]) ? $data[$key] : '-';
            if ($key === 'arbeitszeit_pro_woche' && !empty($value)) {
                $value .= ' Stunden';
            }
            
            $html .= '<div class="field">';
            $html .= '<span class="label">' . esc_html($label) . ':</span>';
            $html .= '<span class="value">' . esc_html($value) . '</span>';
            $html .= '</div>';
        }
        
        $html .= '
    <div style="margin-top: 40px; text-align: center; font-size: 12px; color: #666;">
        <hr style="border: 1px solid #ddd; margin: 20px 0;">
        Dokument erstellt durch RT Employee Manager V2<br>
        ' . home_url() . '
    </div>
</body>
</html>';

        return $html;
    }
    
    /**
     * Escape special characters for PDF
     */
    private function escape_pdf_string($string) {
        $string = str_replace(array('(', ')', '\\'), array('\\(', '\\)', '\\\\'), $string);
        // Convert German umlauts to ASCII equivalents
        $string = str_replace(
            array('ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'),
            array('ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'),
            $string
        );
        return $string;
    }
    
    /**
     * Get employee data
     */
    private function get_employee_data($employee_id) {
        $fields = array(
            'vorname', 'nachname', 'email', 'sozialversicherungsnummer', 'geburtsdatum',
            'staatsangehoerigkeit', 'eintrittsdatum', 'art_des_dienstverhaltnisses',
            'bezeichnung_der_tatigkeit', 'arbeitszeit_pro_woche', 'gehaltlohn'
        );
        
        $data = array();
        foreach ($fields as $field) {
            $data[$field] = get_post_meta($employee_id, $field, true);
        }
        
        return $data;
    }
    
    /**
     * Serve PDF for download
     */
    private function serve_employee_pdf($employee_id) {
        $pdf_path = get_post_meta($employee_id, '_latest_pdf_path', true);
        
        if (!$pdf_path || !file_exists($pdf_path)) {
            // Generate new PDF (prevent infinite loops)
            $pdf_url = $this->generate_employee_pdf($employee_id);
            if (!$pdf_url) {
                wp_die(__('PDF konnte nicht erstellt werden.', 'rt-employee-manager-v2'));
            }
            $pdf_path = get_post_meta($employee_id, '_latest_pdf_path', true);
        }
        
        if ($pdf_path && file_exists($pdf_path)) {
            $employee = get_post($employee_id);
            $filename = 'mitarbeiter-' . sanitize_title($employee->post_title) . '.html';
            
            // Serve as HTML that opens in browser for printing to PDF
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($pdf_path));
            
            readfile($pdf_path);
            exit;
        }
        
        wp_die(__('PDF nicht gefunden.', 'rt-employee-manager-v2'));
    }
    
    /**
     * Email employee PDF
     */
    private function email_employee_pdf($employee_id, $email_address) {
        if (empty($email_address)) {
            return false;
        }
        
        $employee = get_post($employee_id);
        $pdf_url = $this->generate_employee_pdf($employee_id);
        
        if (!$pdf_url) {
            return false;
        }
        
        $subject = sprintf(__('Mitarbeiterdaten: %s', 'rt-employee-manager-v2'), $employee->post_title);
        $message = sprintf(
            __("Anbei finden Sie die Mitarbeiterdaten für %s.\n\nDokument: %s\n\nViele Grüße", 'rt-employee-manager-v2'),
            $employee->post_title,
            $pdf_url
        );
        
        return wp_mail($email_address, $subject, $message);
    }
}