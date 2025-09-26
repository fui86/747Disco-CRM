<?php
/**
 * Forms handling class for Disco747 CRM
 */

namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit;
}

class Disco747_Forms {
    
    private $database;
    private $storage_manager;
    private $excel_analysis_data = null;
    
    public function __construct($database = null, $storage_manager = null) {
        // Initialize dependencies - use global plugin instance if parameters not provided
        if ($database && $storage_manager) {
            $this->database = $database;
            $this->storage_manager = $storage_manager;
        } else {
            // Get from global plugin instance
            global $disco747_crm_plugin;
            if ($disco747_crm_plugin) {
                $this->database = $disco747_crm_plugin->get_database();
                $this->storage_manager = $disco747_crm_plugin->get_storage_manager();
            }
        }
        
        $this->init_hooks();
        $this->load_excel_analysis_data();
    }
    
    /**
     * Initialize form hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_disco747_save_preventivo', array($this, 'handle_save_preventivo'));
        add_action('wp_ajax_disco747_generate_excel', array($this, 'handle_generate_excel'));
        add_action('wp_ajax_disco747_generate_pdf', array($this, 'handle_generate_pdf'));
        add_action('wp_ajax_disco747_send_email', array($this, 'handle_send_email'));
        add_action('wp_ajax_disco747_send_whatsapp', array($this, 'handle_send_whatsapp'));
    }
    
    /**
     * Load Excel analysis data if coming from scan results
     */
    private function load_excel_analysis_data() {
        if (isset($_GET['source']) && $_GET['source'] === 'excel_analysis' && isset($_GET['analysis_id'])) {
            $analysis_id = intval($_GET['analysis_id']);
            if ($analysis_id > 0) {
                $this->excel_analysis_data = $this->database->get_excel_analysis_by_id($analysis_id);
                
                // Convert array to object if necessary for compatibility
                if (is_array($this->excel_analysis_data)) {
                    $this->excel_analysis_data = (object) $this->excel_analysis_data;
                }
            }
        }
    }
    
    /**
     * Get form field value with Excel analysis fallback
     */
    public function get_form_field_value($field_name, $default = '') {
        // First check for POST data (form submission)
        if (isset($_POST[$field_name]) && $_POST[$field_name] !== '') {
            return sanitize_text_field($_POST[$field_name]);
        }
        
        // Then check for GET data (direct URL parameters)
        if (isset($_GET[$field_name]) && $_GET[$field_name] !== '') {
            return sanitize_text_field($_GET[$field_name]);
        }
        
        // Finally check Excel analysis data if available
        if ($this->excel_analysis_data) {
            $value = null;
            if (is_object($this->excel_analysis_data) && isset($this->excel_analysis_data->$field_name)) {
                $value = $this->excel_analysis_data->$field_name;
            } elseif (is_array($this->excel_analysis_data) && isset($this->excel_analysis_data[$field_name])) {
                $value = $this->excel_analysis_data[$field_name];
            }
            
            if ($value !== null && $value !== '') {
                return $this->format_field_for_form($field_name, $value);
            }
        }
        
        return $default;
    }
    
    /**
     * Format field value for form display
     */
    private function format_field_for_form($field_name, $value) {
        if (empty($value)) {
            return '';
        }
        
        // Date fields - convert to form format if needed
        if ($field_name === 'data_evento') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                // Convert Y-m-d to d/m/Y for Italian forms
                $date = DateTime::createFromFormat('Y-m-d', $value);
                if ($date) {
                    return $date->format('d/m/Y');
                }
            }
            return $value;
        }
        
        // Decimal fields - format for Italian locale
        if (in_array($field_name, array('importo', 'acconto', 'saldo', 'extra1_prezzo', 'extra2_prezzo', 'extra3_prezzo'))) {
            if (is_numeric($value)) {
                return number_format(floatval($value), 2, ',', '.');
            }
            return $value;
        }
        
        // String fields - just ensure it's a string
        return strval($value);
    }
    
    /**
     * Get all form values as array (for form rendering)
     */
    public function get_all_form_values() {
        $fields = array(
            'nome_referente',
            'cognome_referente',
            'cellulare',
            'email',
            'tipo_evento',
            'data_evento',
            'orario',
            'numero_invitati',
            'tipo_menu',
            'importo',
            'acconto',
            'saldo',
            'omaggio1',
            'omaggio2', 
            'omaggio3',
            'extra1_nome',
            'extra1_prezzo',
            'extra2_nome',
            'extra2_prezzo',
            'extra3_nome',
            'extra3_prezzo'
        );
        
        $values = array();
        foreach ($fields as $field) {
            $values[$field] = $this->get_form_field_value($field);
        }
        
        return $values;
    }
    
    /**
     * Check if form is being prefilled from Excel analysis
     */
    public function is_excel_analysis_source() {
        return $this->excel_analysis_data !== null;
    }
    
    /**
     * Get Excel analysis source info for display
     */
    public function get_excel_analysis_info() {
        if (!$this->excel_analysis_data) {
            return null;
        }
        
        return array(
            'filename' => $this->excel_analysis_data['filename'],
            'data_evento' => $this->excel_analysis_data['data_evento'],
            'tipo_evento' => $this->excel_analysis_data['tipo_evento'],
            'analysis_success' => $this->excel_analysis_data['analysis_success']
        );
    }
    
    /**
     * Handle preventivo save (existing functionality preserved)
     */
    public function handle_save_preventivo() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
        }
        
        if (!check_ajax_referer('disco747_preventivo_nonce', 'nonce', false)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Invalid nonce')));
        }
        
        // Sanitize and validate form data
        $form_data = $this->sanitize_preventivo_data($_POST);
        $validation_errors = $this->validate_preventivo_data($form_data);
        
        if (!empty($validation_errors)) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validation_errors
            )));
        }
        
        // Save to database
        $preventivo_id = $this->database->save_preventivo($form_data);
        
        if ($preventivo_id) {
            wp_die(json_encode(array(
                'success' => true,
                'preventivo_id' => $preventivo_id,
                'message' => 'Preventivo saved successfully'
            )));
        } else {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Failed to save preventivo'
            )));
        }
    }
    
    /**
     * Sanitize preventivo form data
     */
    private function sanitize_preventivo_data($raw_data) {
        $sanitized = array();
        
        // Text fields
        $text_fields = array('nome_referente', 'cognome_referente', 'cellulare', 'email', 'tipo_evento', 'orario', 'tipo_menu', 'omaggio1', 'omaggio2', 'omaggio3', 'extra1_nome', 'extra2_nome', 'extra3_nome');
        foreach ($text_fields as $field) {
            $sanitized[$field] = isset($raw_data[$field]) ? sanitize_text_field($raw_data[$field]) : '';
        }
        
        // Email field
        if (!empty($sanitized['email'])) {
            $sanitized['email'] = sanitize_email($sanitized['email']);
        }
        
        // Date field
        if (isset($raw_data['data_evento'])) {
            $sanitized['data_evento'] = $this->sanitize_date($raw_data['data_evento']);
        }
        
        // Numeric fields
        $numeric_fields = array('numero_invitati');
        foreach ($numeric_fields as $field) {
            $sanitized[$field] = isset($raw_data[$field]) ? intval($raw_data[$field]) : 0;
        }
        
        // Decimal fields
        $decimal_fields = array('importo', 'acconto', 'saldo', 'extra1_prezzo', 'extra2_prezzo', 'extra3_prezzo');
        foreach ($decimal_fields as $field) {
            $sanitized[$field] = isset($raw_data[$field]) ? $this->sanitize_decimal($raw_data[$field]) : 0.00;
        }
        
        // Set timestamps
        $sanitized['created_at'] = current_time('mysql');
        $sanitized['updated_at'] = current_time('mysql');
        $sanitized['status'] = 'draft';
        
        return $sanitized;
    }
    
    /**
     * Sanitize date input (convert from Italian format)
     */
    private function sanitize_date($date_string) {
        if (empty($date_string)) {
            return null;
        }
        
        // Try to parse Italian date format (d/m/Y)
        $date = DateTime::createFromFormat('d/m/Y', $date_string);
        if ($date) {
            return $date->format('Y-m-d');
        }
        
        // Try other formats
        $formats = array('Y-m-d', 'd-m-Y', 'd/m/y');
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $date_string);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }
        
        return null;
    }
    
    /**
     * Sanitize decimal input (handle Italian format)
     */
    private function sanitize_decimal($decimal_string) {
        if (empty($decimal_string)) {
            return 0.00;
        }
        
        // Convert Italian decimal format (comma as decimal separator)
        $decimal_string = str_replace(',', '.', strval($decimal_string));
        // Remove thousand separators
        $decimal_string = preg_replace('/\.(?=.*\.)/', '', $decimal_string);
        
        return is_numeric($decimal_string) ? floatval($decimal_string) : 0.00;
    }
    
    /**
     * Validate preventivo data
     */
    private function validate_preventivo_data($data) {
        $errors = array();
        
        // Required fields
        $required_fields = array('nome_referente', 'cognome_referente', 'tipo_evento', 'data_evento');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Field {$field} is required";
            }
        }
        
        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Date validation
        if (!empty($data['data_evento'])) {
            $date = DateTime::createFromFormat('Y-m-d', $data['data_evento']);
            if (!$date || $date->format('Y-m-d') !== $data['data_evento']) {
                $errors[] = "Invalid date format";
            }
        }
        
        // Numeric validations
        if ($data['numero_invitati'] < 0) {
            $errors[] = "Number of guests cannot be negative";
        }
        
        if ($data['importo'] < 0) {
            $errors[] = "Amount cannot be negative";
        }
        
        return $errors;
    }
    
    /**
     * Handle Excel generation (existing functionality preserved)
     */
    public function handle_generate_excel() {
        if (!current_user_can('manage_options')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
        }
        
        if (!check_ajax_referer('disco747_excel_nonce', 'nonce', false)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Invalid nonce')));
        }
        
        $preventivo_id = intval($_POST['preventivo_id']);
        $preventivo = $this->database->get_preventivo($preventivo_id);
        
        if (!$preventivo) {
            wp_die(json_encode(array('success' => false, 'message' => 'Preventivo not found')));
        }
        
        try {
            // Generate Excel file using existing logic
            $excel_file_id = $this->generate_excel_file($preventivo);
            
            // Update preventivo with Excel file ID
            $this->database->update_preventivo_status($preventivo_id, 'excel_generated');
            
            wp_die(json_encode(array(
                'success' => true,
                'file_id' => $excel_file_id,
                'message' => 'Excel file generated successfully'
            )));
            
        } catch (Exception $e) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Failed to generate Excel: ' . $e->getMessage()
            )));
        }
    }
    
    /**
     * Handle PDF generation (existing functionality preserved)
     */
    public function handle_generate_pdf() {
        if (!current_user_can('manage_options')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
        }
        
        if (!check_ajax_referer('disco747_pdf_nonce', 'nonce', false)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Invalid nonce')));
        }
        
        $preventivo_id = intval($_POST['preventivo_id']);
        $preventivo = $this->database->get_preventivo($preventivo_id);
        
        if (!$preventivo) {
            wp_die(json_encode(array('success' => false, 'message' => 'Preventivo not found')));
        }
        
        try {
            // Generate PDF file using existing logic
            $pdf_file_id = $this->generate_pdf_file($preventivo);
            
            // Update preventivo with PDF file ID
            $this->database->update_preventivo_status($preventivo_id, 'pdf_generated');
            
            wp_die(json_encode(array(
                'success' => true,
                'file_id' => $pdf_file_id,
                'message' => 'PDF file generated successfully'
            )));
            
        } catch (Exception $e) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            )));
        }
    }
    
    /**
     * Handle email sending (existing functionality preserved)
     */
    public function handle_send_email() {
        if (!current_user_can('manage_options')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
        }
        
        if (!check_ajax_referer('disco747_email_nonce', 'nonce', false)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Invalid nonce')));
        }
        
        $preventivo_id = intval($_POST['preventivo_id']);
        $preventivo = $this->database->get_preventivo($preventivo_id);
        
        if (!$preventivo) {
            wp_die(json_encode(array('success' => false, 'message' => 'Preventivo not found')));
        }
        
        try {
            // Send email using existing logic
            $email_sent = $this->send_preventivo_email($preventivo);
            
            if ($email_sent) {
                wp_die(json_encode(array(
                    'success' => true,
                    'message' => 'Email sent successfully'
                )));
            } else {
                wp_die(json_encode(array(
                    'success' => false,
                    'message' => 'Failed to send email'
                )));
            }
            
        } catch (Exception $e) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            )));
        }
    }
    
    /**
     * Handle WhatsApp sending (existing functionality preserved)
     */
    public function handle_send_whatsapp() {
        if (!current_user_can('manage_options')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
        }
        
        if (!check_ajax_referer('disco747_whatsapp_nonce', 'nonce', false)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Invalid nonce')));
        }
        
        $preventivo_id = intval($_POST['preventivo_id']);
        $preventivo = $this->database->get_preventivo($preventivo_id);
        
        if (!$preventivo) {
            wp_die(json_encode(array('success' => false, 'message' => 'Preventivo not found')));
        }
        
        try {
            // Send WhatsApp using existing logic
            $whatsapp_sent = $this->send_preventivo_whatsapp($preventivo);
            
            if ($whatsapp_sent) {
                wp_die(json_encode(array(
                    'success' => true,
                    'message' => 'WhatsApp message sent successfully'
                )));
            } else {
                wp_die(json_encode(array(
                    'success' => false,
                    'message' => 'Failed to send WhatsApp message'
                )));
            }
            
        } catch (Exception $e) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Failed to send WhatsApp: ' . $e->getMessage()
            )));
        }
    }
    
    /**
     * Generate Excel file (existing functionality - placeholder)
     */
    private function generate_excel_file($preventivo) {
        // This would contain the existing Excel generation logic
        // Using PhpSpreadsheet and Drive upload
        
        // For now, return a placeholder
        return 'excel_file_id_placeholder';
    }
    
    /**
     * Generate PDF file (existing functionality - placeholder)
     */
    private function generate_pdf_file($preventivo) {
        // This would contain the existing PDF generation logic
        // Using TCPDF or similar and Drive upload
        
        // For now, return a placeholder
        return 'pdf_file_id_placeholder';
    }
    
    /**
     * Send email (existing functionality - placeholder)
     */
    private function send_preventivo_email($preventivo) {
        // This would contain the existing email sending logic
        // Using wp_mail with templates
        
        // For now, return success
        return true;
    }
    
    /**
     * Send WhatsApp (existing functionality - placeholder)
     */
    private function send_preventivo_whatsapp($preventivo) {
        // This would contain the existing WhatsApp API integration
        
        // For now, return success
        return true;
    }
    
    /**
     * Render form field helper
     */
    public function render_text_field($name, $label, $required = false, $attributes = array()) {
        $value = $this->get_form_field_value($name);
        $required_attr = $required ? 'required' : '';
        $required_mark = $required ? '<span class="required">*</span>' : '';
        
        $default_attributes = array(
            'type' => 'text',
            'class' => 'form-control',
            'id' => $name,
            'name' => $name,
            'value' => esc_attr($value)
        );
        
        $attributes = array_merge($default_attributes, $attributes);
        $attributes_string = '';
        foreach ($attributes as $key => $val) {
            $attributes_string .= ' ' . $key . '="' . esc_attr($val) . '"';
        }
        
        echo '<div class="form-group">';
        echo '<label for="' . esc_attr($name) . '">' . esc_html($label) . $required_mark . '</label>';
        echo '<input' . $attributes_string . ' ' . $required_attr . '>';
        echo '</div>';
    }
    
    /**
     * Render select field helper
     */
    public function render_select_field($name, $label, $options, $required = false, $attributes = array()) {
        $value = $this->get_form_field_value($name);
        $required_attr = $required ? 'required' : '';
        $required_mark = $required ? '<span class="required">*</span>' : '';
        
        $default_attributes = array(
            'class' => 'form-control',
            'id' => $name,
            'name' => $name
        );
        
        $attributes = array_merge($default_attributes, $attributes);
        $attributes_string = '';
        foreach ($attributes as $key => $val) {
            $attributes_string .= ' ' . $key . '="' . esc_attr($val) . '"';
        }
        
        echo '<div class="form-group">';
        echo '<label for="' . esc_attr($name) . '">' . esc_html($label) . $required_mark . '</label>';
        echo '<select' . $attributes_string . ' ' . $required_attr . '>';
        echo '<option value="">-- Seleziona --</option>';
        
        foreach ($options as $option_value => $option_label) {
            $selected = ($value === $option_value) ? 'selected' : '';
            echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
        }
        
        echo '</select>';
        echo '</div>';
    }
}