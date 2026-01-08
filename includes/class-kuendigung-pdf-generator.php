<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles PDF generation and email sending for Kündigungen
 */
class RT_Kuendigung_PDF_Generator_V2 {
    
    
    /**
     * Generate Kündigung PDF content
     * 
     * @param int $kuendigung_id The Kündigung post ID
     * @param int $employee_id The employee post ID
     * @return string|false PDF binary content on success, false on failure
     */
    public function generate_kuendigung_pdf_content($kuendigung_id, $employee_id) {
        $kuendigung = get_post($kuendigung_id);
        $employee = get_post($employee_id);
        
        if (!$kuendigung || !$employee) {
            return false;
        }
        
        // Get all data
        $kuendigung_data = $this->get_kuendigung_data($kuendigung_id);
        $employee_data = $this->get_employee_data($employee_id);
        
        // Create HTML
        $html = $this->create_kuendigung_html($kuendigung, $employee, $kuendigung_data, $employee_data);
        
        // Generate PDF using DomPDF
        $pdf_content = $this->generate_pdf_from_html($html);
        
        return $pdf_content;
    }
    
    /**
     * Get Kündigung data
     */
    private function get_kuendigung_data($kuendigung_id) {
        return array(
            'kuendigungsart' => get_post_meta($kuendigung_id, 'kuendigungsart', true),
            'kuendigungsdatum' => get_post_meta($kuendigung_id, 'kuendigungsdatum', true),
            'beendigungsdatum' => get_post_meta($kuendigung_id, 'beendigungsdatum', true),
            'kuendigungsgrund' => get_post_meta($kuendigung_id, 'kuendigungsgrund', true),
            'employer_name' => get_post_meta($kuendigung_id, 'employer_name', true),
            'employer_email' => get_post_meta($kuendigung_id, 'employer_email', true),
            'kuendigungsfrist' => get_post_meta($kuendigung_id, 'kuendigungsfrist', true),
            'resturlaub' => get_post_meta($kuendigung_id, 'resturlaub', true),
            'ueberstunden' => get_post_meta($kuendigung_id, 'ueberstunden', true),
            'zeugnis_gewuenscht' => get_post_meta($kuendigung_id, 'zeugnis_gewuenscht', true),
            'uebergabe_erledigt' => get_post_meta($kuendigung_id, 'uebergabe_erledigt', true),
            'notes' => get_post_meta($kuendigung_id, 'notes', true)
        );
    }
    
    /**
     * Get employee data
     */
    private function get_employee_data($employee_id) {
        $fields = array(
            'vorname', 'nachname', 'email', 'anrede', 'sozialversicherungsnummer',
            'geburtsdatum', 'adresse_strasse', 'adresse_plz', 'adresse_ort',
            'eintrittsdatum', 'art_des_dienstverhaltnisses', 'beschaeftigung', 'status'
        );
        
        $data = array();
        foreach ($fields as $field) {
            $data[$field] = get_post_meta($employee_id, $field, true);
        }
        
        return $data;
    }
    
    /**
     * Create Kündigung HTML template
     */
    private function create_kuendigung_html($kuendigung, $employee, $kuendigung_data, $employee_data) {
        // Format dates
        $kuendigungsdatum_formatted = $kuendigung_data['kuendigungsdatum'] ? date_i18n('d.m.Y', strtotime($kuendigung_data['kuendigungsdatum'])) : '';
        $beendigungsdatum_formatted = $kuendigung_data['beendigungsdatum'] ? date_i18n('d.m.Y', strtotime($kuendigung_data['beendigungsdatum'])) : '';
        $erstellt_am = date_i18n('d.m.Y H:i');
        
        // Employee name
        $employee_name = trim(($employee_data['vorname'] ?? '') . ' ' . ($employee_data['nachname'] ?? ''));
        if (empty($employee_name)) {
            $employee_name = $employee->post_title;
        }
        
        // Get logo
        $logo_id = get_option('rt_employee_v2_pdf_logo', 0);
        $logo_src = '';
        if ($logo_id) {
            $logo_path = get_attached_file($logo_id);
            if ($logo_path && file_exists($logo_path)) {
                $logo_mime = get_post_mime_type($logo_id);
                if (in_array($logo_mime, array('image/jpeg', 'image/png', 'image/gif'))) {
                    $logo_data = file_get_contents($logo_path);
                    $logo_base64 = base64_encode($logo_data);
                    $logo_src = 'data:' . $logo_mime . ';base64,' . $logo_base64;
                }
            }
        }
        
        // Get PDF header/footer text from settings
        $pdf_header_text = get_option('rt_employee_v2_pdf_template_header', 'Mitarbeiterverwaltung');
        $pdf_footer_text = get_option('rt_employee_v2_pdf_template_footer', '');
        
        // Generate Kündigung text
        $kuendigungstext = $this->generate_kuendigungstext($kuendigung_data, $employee_name, $employee_data);
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 2cm; }
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #000;
        }
        .logo {
            max-width: 150px;
            max-height: 60px;
        }
        .header-right {
            text-align: right;
            font-size: 14pt;
            font-weight: bold;
        }
        .info-box {
            background-color: #f5f5f5;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #333;
        }
        .info-box h2 {
            margin: 0 0 10px 0;
            font-size: 16pt;
            color: #333;
        }
        .info-box p {
            margin: 5px 0;
        }
        h1 {
            font-size: 18pt;
            margin: 30px 0 20px 0;
            text-align: center;
            font-weight: bold;
        }
        .section {
            margin: 25px 0;
        }
        .section-title {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        .data-row {
            margin: 8px 0;
            display: flex;
        }
        .data-label {
            font-weight: bold;
            width: 200px;
            flex-shrink: 0;
        }
        .data-value {
            flex: 1;
        }
        .kuendigungstext {
            margin: 25px 0;
            padding: 15px;
            background-color: #fafafa;
            border: 1px solid #ddd;
            line-height: 1.8;
            text-align: justify;
        }
        .signature-section {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            border-top: 1px solid #000;
            padding-top: 10px;
            margin-top: 60px;
        }
        .signature-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">';
        
        if ($logo_src) {
            $html .= '<img src="' . esc_attr($logo_src) . '" alt="Logo" class="logo" />';
        }
        
        $html .= '</div>
        <div class="header-right">' . esc_html($pdf_header_text) . '</div>
    </div>
    
    <div class="info-box">
        <h2>Kündigung</h2>
        <p><strong>Mitarbeiter:</strong> ' . esc_html($employee_name) . '</p>
        <p><strong>Erstellt am:</strong> ' . esc_html($erstellt_am) . '</p>
    </div>
    
    <h1>Kündigungserklärung</h1>
    
    <div class="section">
        <div class="section-title">Arbeitgeber</div>
        <div class="data-row">
            <div class="data-label">Firma:</div>
            <div class="data-value">' . esc_html($kuendigung_data['employer_name']) . '</div>
        </div>
        <div class="data-row">
            <div class="data-label">E-Mail:</div>
            <div class="data-value">' . esc_html($kuendigung_data['employer_email']) . '</div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">Mitarbeiter</div>
        <div class="data-row">
            <div class="data-label">Name:</div>
            <div class="data-value">' . esc_html($employee_name) . '</div>
        </div>';
        
        if (!empty($employee_data['email'])) {
            $html .= '<div class="data-row">
                <div class="data-label">E-Mail:</div>
                <div class="data-value">' . esc_html($employee_data['email']) . '</div>
            </div>';
        }
        
        if (!empty($employee_data['adresse_strasse'])) {
            $address = trim($employee_data['adresse_strasse']);
            if (!empty($employee_data['adresse_plz'])) {
                $address .= ', ' . $employee_data['adresse_plz'];
            }
            if (!empty($employee_data['adresse_ort'])) {
                $address .= ' ' . $employee_data['adresse_ort'];
            }
            $html .= '<div class="data-row">
                <div class="data-label">Adresse:</div>
                <div class="data-value">' . esc_html($address) . '</div>
            </div>';
        }
        
        $html .= '</div>
    
    <div class="section">
        <div class="section-title">Kündigungsdetails</div>
        <div class="data-row">
            <div class="data-label">Kündigungsart:</div>
            <div class="data-value">' . esc_html($kuendigung_data['kuendigungsart']) . '</div>
        </div>
        <div class="data-row">
            <div class="data-label">Kündigungsdatum:</div>
            <div class="data-value">' . esc_html($kuendigungsdatum_formatted) . '</div>
        </div>
        <div class="data-row">
            <div class="data-label">Beendigungsdatum:</div>
            <div class="data-value">' . esc_html($beendigungsdatum_formatted) . '</div>
        </div>';
        
        if (!empty($kuendigung_data['kuendigungsfrist'])) {
            $html .= '<div class="data-row">
                <div class="data-label">Kündigungsfrist:</div>
                <div class="data-value">' . esc_html($kuendigung_data['kuendigungsfrist']) . '</div>
            </div>';
        }
        
        if (!empty($kuendigung_data['resturlaub']) && floatval($kuendigung_data['resturlaub']) > 0) {
            $html .= '<div class="data-row">
                <div class="data-label">Resturlaub:</div>
                <div class="data-value">' . esc_html($kuendigung_data['resturlaub']) . ' Tage</div>
            </div>';
        }
        
        if (!empty($kuendigung_data['ueberstunden']) && floatval($kuendigung_data['ueberstunden']) > 0) {
            $html .= '<div class="data-row">
                <div class="data-label">Überstunden:</div>
                <div class="data-value">' . esc_html($kuendigung_data['ueberstunden']) . ' Stunden</div>
            </div>';
        }
        
        $html .= '</div>
    
    <div class="kuendigungstext">
        ' . nl2br(esc_html($kuendigungstext)) . '
    </div>
    
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-label">Ort, Datum</div>
            <div style="margin-top: 40px;">' . esc_html($kuendigungsdatum_formatted) . '</div>
        </div>
        <div class="signature-box">
            <div class="signature-label">Unterschrift Arbeitgeber</div>
            <div style="margin-top: 40px;">_________________________</div>
        </div>
    </div>';
    
        if (!empty($pdf_footer_text)) {
            $html .= '<div class="footer">' . esc_html($pdf_footer_text) . '</div>';
        }
        
        $html .= '</body>
</html>';
        
        return $html;
    }
    
    /**
     * Generate Kündigung text from data
     */
    private function generate_kuendigungstext($kuendigung_data, $employee_name, $employee_data) {
        $text = "Hiermit kündige ich das Arbeitsverhältnis mit " . $employee_name;
        
        if (!empty($employee_data['art_des_dienstverhaltnisses'])) {
            $text .= " (" . $employee_data['art_des_dienstverhaltnisses'] . ")";
        }
        
        $text .= " zum " . date_i18n('d.m.Y', strtotime($kuendigung_data['beendigungsdatum'])) . ".\n\n";
        
        $text .= "Kündigungsart: " . $kuendigung_data['kuendigungsart'] . " Kündigung\n";
        $text .= "Kündigungsdatum: " . date_i18n('d.m.Y', strtotime($kuendigung_data['kuendigungsdatum'])) . "\n";
        $text .= "Beendigungsdatum: " . date_i18n('d.m.Y', strtotime($kuendigung_data['beendigungsdatum'])) . "\n\n";
        
        if (!empty($kuendigung_data['kuendigungsfrist'])) {
            $text .= "Kündigungsfrist: " . $kuendigung_data['kuendigungsfrist'] . "\n\n";
        }
        
        if (!empty($kuendigung_data['kuendigungsgrund'])) {
            $text .= "Grund der Kündigung:\n" . $kuendigung_data['kuendigungsgrund'] . "\n\n";
        }
        
        if (!empty($kuendigung_data['resturlaub']) && floatval($kuendigung_data['resturlaub']) > 0) {
            $text .= "Resturlaub: " . $kuendigung_data['resturlaub'] . " Tage\n";
        }
        
        if (!empty($kuendigung_data['ueberstunden']) && floatval($kuendigung_data['ueberstunden']) > 0) {
            $text .= "Überstunden: " . $kuendigung_data['ueberstunden'] . " Stunden\n";
        }
        
        if (!empty($kuendigung_data['zeugnis_gewuenscht']) && $kuendigung_data['zeugnis_gewuenscht'] === '1') {
            $text .= "\nEin Arbeitszeugnis wird gewünscht.\n";
        }
        
        return $text;
    }
    
    /**
     * Generate PDF from HTML using DomPDF
     */
    private function generate_pdf_from_html($html) {
        if (!class_exists('\Dompdf\Dompdf')) {
            error_log('RT Employee Manager V2: DomPDF not found for Kündigung PDF');
            return false;
        }
        
        try {
            $dompdf = new \Dompdf\Dompdf();
            
            $options = $dompdf->getOptions();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', false);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            
            $dompdf->setOptions($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            return $dompdf->output();
        } catch (Exception $e) {
            error_log('RT Employee Manager V2: Kündigung PDF generation error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save PDF to file
     */
    private function save_pdf_to_file($pdf_content, $kuendigung_id, $employee_id) {
        $upload_dir = wp_upload_dir();
        $kuendigung_dir = $upload_dir['basedir'] . '/rt-employee-manager-v2/kuendigungen';
        
        if (!file_exists($kuendigung_dir)) {
            wp_mkdir_p($kuendigung_dir);
        }
        
        $filename = 'kuendigung-' . $kuendigung_id . '-' . $employee_id . '-' . time() . '.pdf';
        $filepath = $kuendigung_dir . '/' . $filename;
        
        if (file_put_contents($filepath, $pdf_content) === false) {
            return false;
        }
        
        return $filepath;
    }
    
    /**
     * Send Kündigung email with PDF attachment (manual)
     * 
     * @param int $kuendigung_id The Kündigung post ID
     * @param int $employee_id The employee post ID
     * @param string $email_address The email address to send to
     * @param bool $send_to_employee Whether to send to the email address (always true now)
     * @param bool $send_to_bookkeeping Whether to send to bookkeeping
     * @return array Result with success status and optional error message
     */
    public function send_kuendigung_email_manual($kuendigung_id, $employee_id, $email_address, $send_to_employee, $send_to_bookkeeping) {
        // Generate PDF
        $pdf_content = $this->generate_kuendigung_pdf_content($kuendigung_id, $employee_id);
        if ($pdf_content === false) {
            return array('success' => false, 'error' => 'PDF generation failed');
        }
        
        // Save PDF to file for email attachment
        $pdf_file = $this->save_pdf_to_file($pdf_content, $kuendigung_id, $employee_id);
        if ($pdf_file === false) {
            return array('success' => false, 'error' => 'Failed to save PDF file');
        }
        
        $kuendigung_data = $this->get_kuendigung_data($kuendigung_id);
        $employee_data = $this->get_employee_data($employee_id);
        
        // Build recipients list - always send to the provided email address
        $recipients = array();
        
        if (!empty($email_address)) {
            $recipients[] = sanitize_email($email_address);
        }
        
        if ($send_to_bookkeeping) {
            $bookkeeping_email = get_option('rt_employee_v2_buchhaltung_email', '');
            if (!empty($bookkeeping_email)) {
                $recipients[] = $bookkeeping_email;
            }
        }
        
        if (empty($recipients)) {
            return array('success' => false, 'error' => 'No recipients selected');
        }
        
        // Log for debugging
        error_log('RT Employee Manager V2: Sending Kündigung email to: ' . implode(', ', $recipients));
        error_log('RT Employee Manager V2: PDF file: ' . $pdf_file);
        
        // Email subject
        $employee_name = trim(($employee_data['vorname'] ?? '') . ' ' . ($employee_data['nachname'] ?? ''));
        if (empty($employee_name)) {
            $employee = get_post($employee_id);
            $employee_name = $employee->post_title;
        }
        
        $subject = sprintf(__('Kündigung Ihres Dienstverhältnisses - %s', 'rt-employee-manager-v2'), $employee_name);
        
        // Email body
        $body = "Sehr geehrte/r " . $employee_name . ",\n\n";
        $body .= "anbei erhalten Sie die Kündigung Ihres Dienstverhältnisses.\n\n";
        $body .= "Kündigungsart: " . $kuendigung_data['kuendigungsart'] . "\n";
        $body .= "Kündigungsdatum: " . date_i18n('d.m.Y', strtotime($kuendigung_data['kuendigungsdatum'])) . "\n";
        $body .= "Beendigungsdatum: " . date_i18n('d.m.Y', strtotime($kuendigung_data['beendigungsdatum'])) . "\n\n";
        $body .= "Mit freundlichen Grüßen\n";
        $body .= $kuendigung_data['employer_name'] . "\n";
        
        // Email headers
        $sender_name = get_option('rt_employee_v2_email_sender_name', 'WordPress');
        $sender_email = get_option('rt_employee_v2_email_sender_email', get_option('admin_email'));
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $sender_email . '>'
        );
        
        // Check if PDF file exists
        if (!file_exists($pdf_file)) {
            error_log('RT Employee Manager V2: PDF file does not exist: ' . $pdf_file);
            return array('success' => false, 'error' => 'PDF file not found');
        }
        
        error_log('RT Employee Manager V2: PDF file exists, size: ' . filesize($pdf_file) . ' bytes');
        
        // Send email
        $sent = wp_mail($recipients, $subject, $body, $headers, array($pdf_file));
        
        // Check for wp_mail errors
        global $phpmailer;
        if (!$sent && isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
            $error_message = $phpmailer->ErrorInfo;
            error_log('RT Employee Manager V2: wp_mail error: ' . $error_message);
            return array('success' => false, 'error' => 'wp_mail failed: ' . $error_message);
        }
        
        if ($sent) {
            // Store email info in meta
            update_post_meta($kuendigung_id, 'email_sent', '1');
            update_post_meta($kuendigung_id, 'email_sent_date', current_time('mysql'));
            update_post_meta($kuendigung_id, 'email_recipients', implode(', ', $recipients));
            
            error_log('RT Employee Manager V2: Kündigung email sent successfully to: ' . implode(', ', $recipients));
            return array('success' => true);
        } else {
            error_log('RT Employee Manager V2: wp_mail returned false, but no error info available');
            return array('success' => false, 'error' => 'wp_mail returned false');
        }
    }
}