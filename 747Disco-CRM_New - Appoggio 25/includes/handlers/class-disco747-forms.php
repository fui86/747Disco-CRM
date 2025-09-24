<?php
/**
 * Forms Handler per 747 Disco CRM
 * VERSIONE 11.8.3 - Gestione attach_pdf e include_pdf_link
 * 
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @version    11.8.3
 */

namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Forms {
    
    private $database;
    private $excel;
    private $pdf;
    private $storage;
    private $log_enabled = true;
    private $components_loaded = false;
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'disco747_preventivi';
        $this->log('[Forms] Handler Forms inizializzato - Tabella: ' . $this->table_name);
        
        add_action('wp_ajax_disco747_save_preventivo', array($this, 'handle_ajax_submission'));
        add_action('wp_ajax_nopriv_disco747_save_preventivo', array($this, 'handle_ajax_submission'));
        
        add_action('wp_ajax_disco747_generate_pdf', array($this, 'handle_generate_pdf'));
        add_action('wp_ajax_disco747_download_pdf', array($this, 'handle_download_pdf'));
        add_action('wp_ajax_disco747_send_email_template', array($this, 'handle_send_email_template'));
        add_action('wp_ajax_disco747_send_whatsapp_template', array($this, 'handle_send_whatsapp_template'));
        
        add_action('disco747_cleanup_temp_files', array($this, 'cleanup_temp_files'));
        if (!wp_next_scheduled('disco747_cleanup_temp_files')) {
            wp_schedule_event(time(), 'hourly', 'disco747_cleanup_temp_files');
        }
        
        $this->log('[Forms] Hook AJAX registrati correttamente');
    }
    
    public function handle_ajax_submission() {
        try {
            $this->log('[Forms] INIZIO GESTIONE PREVENTIVO');
            
            if (!$this->verify_nonce()) {
                $this->log('[Forms] ERRORE: Nonce non valido');
                wp_send_json_error('Sessione scaduta. Ricarica la pagina.');
                return;
            }
            
            $this->load_components();
            $data = $this->validate_form_data($_POST);
            
            if (!$data) {
                wp_send_json_error('Dati del form non validi');
                return;
            }
            
            $db_id = $this->save_preventivo($data);
            
            if (!$db_id) {
                wp_send_json_error('Errore durante il salvataggio del preventivo');
                return;
            }
            
            $this->log('[Forms] Preventivo salvato con ID: ' . $db_id);
            
            $excel_path = $this->create_excel_safe($data);
            $this->log('[Forms] Excel: ' . ($excel_path ? 'GENERATO' : 'FALLITO'));
            
            $cloud_url = null;
            if ($excel_path) {
                $this->log('[Forms] Upload Excel su storage cloud...');
                $cloud_url = $this->upload_to_storage_safe($excel_path, null, $data);
                $this->log('[Forms] Upload cloud: ' . ($cloud_url ? 'COMPLETATO' : 'FALLITO'));
            }
            
            $this->log('[Forms] PREVENTIVO COMPLETATO CON SUCCESSO (SOLO EXCEL)');
            
            wp_send_json_success(array(
                'message' => 'Preventivo salvato con successo!',
                'preventivo_id' => $data['preventivo_id'],
                'db_id' => $db_id,
                'keep_form_open' => true,
                'data' => $data,
                'files' => array(
                    'excel_path' => $excel_path ? basename($excel_path) : null,
                ),
                'cloud_url' => $cloud_url
            ));
            
        } catch (\Exception $e) {
            $this->log('[Forms] ERRORE FATALE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore durante l\'elaborazione: ' . $e->getMessage());
        }
    }
    
    public function handle_generate_pdf() {
        try {
            if (!$this->verify_nonce()) {
                wp_send_json_error('Sessione scaduta');
                return;
            }
            
            $preventivo_id = sanitize_text_field($_POST['preventivo_id'] ?? '');
            
            if (empty($preventivo_id)) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            $this->load_components();
            
            global $wpdb;
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE preventivo_id = %s",
                $preventivo_id
            ), ARRAY_A);
            
            if (!$preventivo) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            $this->log('[PDF] Inizio generazione PDF per preventivo: ' . $preventivo_id);
            
            if (!$this->pdf) {
                wp_send_json_error('PDF generator non disponibile');
                return;
            }
            
            $pdf_path = $this->pdf->generate_pdf($preventivo);
            
            if (!$pdf_path || !file_exists($pdf_path)) {
                $this->log('[PDF] Errore: PDF non generato', 'ERROR');
                wp_send_json_error('Errore durante la generazione del PDF');
                return;
            }
            
            $this->log('[PDF] PDF generato: ' . $pdf_path);
            
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/disco747-temp-pdf';
            
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $file_name = basename($pdf_path);
            $temp_file = $temp_dir . '/' . $file_name;
            
            if ($pdf_path !== $temp_file) {
                copy($pdf_path, $temp_file);
            }
            
            $download_token = wp_generate_password(32, false);
            set_transient('disco747_pdf_' . $download_token, array(
                'file' => $temp_file,
                'name' => $file_name,
                'preventivo_id' => $preventivo_id
            ), HOUR_IN_SECONDS);
            
            $this->log('[PDF] File salvato in temp: ' . $temp_file);
            
            try {
                $this->upload_to_storage_safe(null, $temp_file, $preventivo);
            } catch (\Exception $e) {
                $this->log('[PDF] Warning upload cloud: ' . $e->getMessage(), 'WARNING');
            }
            
            wp_send_json_success(array(
                'message' => 'PDF generato con successo',
                'download_token' => $download_token,
                'file_name' => $file_name,
                'pdf_path' => $temp_file
            ));
            
        } catch (\Exception $e) {
            $this->log('[PDF] Errore: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    public function handle_download_pdf() {
        $token = sanitize_text_field($_GET['token'] ?? '');
        
        if (empty($token)) {
            wp_die('Token mancante');
        }
        
        $file_data = get_transient('disco747_pdf_' . $token);
        
        if (!$file_data || !isset($file_data['file']) || !file_exists($file_data['file'])) {
            wp_die('File non trovato o scaduto');
        }
        
        $file_path = $file_data['file'];
        $file_name = $file_data['name'];
        
        delete_transient('disco747_pdf_' . $token);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        readfile($file_path);
        
        @unlink($file_path);
        
        exit;
    }
    
    public function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/disco747-temp-pdf';
        
        if (!file_exists($temp_dir)) {
            return;
        }
        
        $files = glob($temp_dir . '/*.pdf');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 3600) {
                    @unlink($file);
                    $this->log('[Cleanup] File rimosso: ' . basename($file));
                }
            }
        }
    }
    
    /**
     * MODIFICATO: Gestisce parametro attach_pdf
     */
    public function handle_send_email_template() {
        try {
            if (!$this->verify_nonce()) {
                wp_send_json_error('Sessione scaduta');
                return;
            }
            
            $preventivo_id = sanitize_text_field($_POST['preventivo_id'] ?? '');
            $template_number = intval($_POST['template_number'] ?? 1);
            $attach_pdf = intval($_POST['attach_pdf'] ?? 0); // NUOVO parametro
            
            if (empty($preventivo_id)) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            $this->load_components();
            
            global $wpdb;
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE preventivo_id = %s",
                $preventivo_id
            ), ARRAY_A);
            
            if (!$preventivo) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            if (empty($preventivo['mail'])) {
                wp_send_json_error('Email del cliente non presente');
                return;
            }
            
            $template_key = 'disco747_email_template_' . $template_number;
            $subject_key = 'disco747_email_subject_' . $template_number;
            
            $template = get_option($template_key, '');
            $subject = get_option($subject_key, 'Preventivo 747 Disco');
            
            if (empty($template)) {
                wp_send_json_error('Template email non configurato');
                return;
            }
            
            $body = $this->replace_placeholders($template, $preventivo);
            $subject = $this->replace_placeholders($subject, $preventivo);
            
            $to = $preventivo['mail'];
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: 747 Disco <noreply@747disco.it>'
            );
            
            $attachments = array();
            
            // CONTROLLA SE ALLEGARE IL PDF
            if ($attach_pdf == 1) {
                $this->log('[Email] Richiesto allegato PDF');
                $pdf_path = $this->ensure_pdf_exists($preventivo);
                
                if ($pdf_path && file_exists($pdf_path)) {
                    $attachments[] = $pdf_path;
                    $this->log('[Email] PDF allegato: ' . basename($pdf_path));
                } else {
                    $this->log('[Email] Warning: PDF non disponibile per allegato', 'WARNING');
                }
            } else {
                $this->log('[Email] Invio senza allegato PDF');
            }
            
            $sent = wp_mail($to, $subject, $body, $headers, $attachments);
            
            if ($sent) {
                $this->log('[Email] Email inviata con successo a: ' . $to);
                wp_send_json_success(array(
                    'message' => 'Email inviata con successo a ' . $to,
                    'pdf_attached' => !empty($attachments)
                ));
            } else {
                $this->log('[Email] Errore invio email a: ' . $to, 'ERROR');
                wp_send_json_error('Errore durante l\'invio dell\'email. Verifica la configurazione SMTP.');
            }
            
        } catch (\Exception $e) {
            $this->log('[Email] Errore: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    private function ensure_pdf_exists($preventivo) {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/disco747-temp-pdf';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $files = glob($temp_dir . '/*.pdf');
        foreach ($files as $file) {
            if (strpos(basename($file), $preventivo['preventivo_id']) !== false) {
                $this->log('[PDF] PDF esistente trovato: ' . basename($file));
                return $file;
            }
        }
        
        $this->log('[PDF] Generazione nuovo PDF per email...');
        
        if (!$this->pdf) {
            throw new \Exception('PDF generator non disponibile');
        }
        
        $pdf_path = $this->pdf->generate_pdf($preventivo);
        
        if (!$pdf_path || !file_exists($pdf_path)) {
            throw new \Exception('Errore generazione PDF');
        }
        
        $file_name = basename($pdf_path);
        $temp_file = $temp_dir . '/' . $file_name;
        
        if ($pdf_path !== $temp_file) {
            copy($pdf_path, $temp_file);
        }
        
        return $temp_file;
    }
    
    /**
     * MODIFICATO: Gestisce parametro include_pdf_link
     */
    public function handle_send_whatsapp_template() {
        try {
            if (!$this->verify_nonce()) {
                wp_send_json_error('Sessione scaduta');
                return;
            }
            
            $preventivo_id = sanitize_text_field($_POST['preventivo_id'] ?? '');
            $template_number = intval($_POST['template_number'] ?? 1);
            $include_pdf_link = intval($_POST['include_pdf_link'] ?? 0); // NUOVO parametro
            
            if (empty($preventivo_id)) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            global $wpdb;
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE preventivo_id = %s",
                $preventivo_id
            ), ARRAY_A);
            
            if (!$preventivo) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            if (empty($preventivo['cellulare'])) {
                wp_send_json_error('Numero di telefono non presente');
                return;
            }
            
            $template_key = 'disco747_whatsapp_template_' . $template_number;
            $template = get_option($template_key, '');
            
            if (empty($template)) {
                wp_send_json_error('Template WhatsApp non configurato');
                return;
            }
            
            $message = $this->replace_placeholders($template, $preventivo);
            
            // AGGIUNGI LINK PDF SE RICHIESTO
            if ($include_pdf_link == 1) {
                $this->log('[WhatsApp] Richiesto link PDF nel messaggio');
                
                // Genera PDF se non esiste
                try {
                    $pdf_path = $this->ensure_pdf_exists($preventivo);
                    
                    if ($pdf_path) {
                        // Genera token per download
                        $download_token = wp_generate_password(32, false);
                        set_transient('disco747_pdf_' . $download_token, array(
                            'file' => $pdf_path,
                            'name' => basename($pdf_path),
                            'preventivo_id' => $preventivo_id
                        ), DAY_IN_SECONDS); // 24 ore per WhatsApp
                        
                        $download_url = admin_url('admin-ajax.php?action=disco747_download_pdf&token=' . $download_token);
                        $message .= "\n\n📄 Scarica il preventivo PDF:\n" . $download_url;
                        
                        $this->log('[WhatsApp] Link PDF aggiunto al messaggio');
                    }
                } catch (\Exception $e) {
                    $this->log('[WhatsApp] Warning: impossibile generare link PDF - ' . $e->getMessage(), 'WARNING');
                }
            } else {
                $this->log('[WhatsApp] Invio senza link PDF');
            }
            
            $phone = preg_replace('/[^0-9]/', '', $preventivo['cellulare']);
            
            if (substr($phone, 0, 2) !== '39' && strlen($phone) == 10) {
                $phone = '39' . $phone;
            }
            
            $whatsapp_url = 'https://wa.me/' . $phone . '?text=' . urlencode($message);
            
            $this->log('[WhatsApp] URL generato per: ' . $phone);
            
            wp_send_json_success(array(
                'message' => 'Link WhatsApp generato',
                'whatsapp_url' => $whatsapp_url,
                'phone' => $phone,
                'formatted_phone' => '+' . $phone,
                'pdf_link_included' => ($include_pdf_link == 1)
            ));
            
        } catch (\Exception $e) {
            $this->log('[WhatsApp] Errore: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    private function replace_placeholders($text, $data) {
        $replacements = array(
            '{{nome}}' => $data['nome_referente'] ?? '',
            '{{cognome}}' => $data['cognome_referente'] ?? '',
            '{{nome_completo}}' => trim(($data['nome_referente'] ?? '') . ' ' . ($data['cognome_referente'] ?? '')),
            '{{email}}' => $data['mail'] ?? '',
            '{{telefono}}' => $data['cellulare'] ?? '',
            '{{data_evento}}' => !empty($data['data_evento']) ? date('d/m/Y', strtotime($data['data_evento'])) : '',
            '{{tipo_evento}}' => $data['tipo_evento'] ?? '',
            '{{menu}}' => $data['tipo_menu'] ?? '',
            '{{numero_invitati}}' => $data['numero_invitati'] ?? '',
            '{{importo}}' => !empty($data['importo_preventivo']) ? '€ ' . number_format($data['importo_preventivo'], 2, ',', '.') : '',
            '{{acconto}}' => !empty($data['acconto']) ? '€ ' . number_format($data['acconto'], 2, ',', '.') : '',
            '{{preventivo_id}}' => $data['preventivo_id'] ?? '',
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    private function verify_nonce() {
        $nonce_field = 'disco747_nonce';
        
        if (!isset($_POST[$nonce_field])) {
            return false;
        }
        
        $nonce = sanitize_text_field($_POST[$nonce_field]);
        return wp_verify_nonce($nonce, 'disco747_form_nonce');
    }
    
    private function load_components() {
        if ($this->components_loaded) {
            return;
        }
        
        $disco747 = disco747_crm();
        
        if (!$disco747) {
            throw new \Exception('Plugin principale non inizializzato');
        }
        
        $this->database = $disco747->get_database();
        $this->excel = $disco747->get_excel();
        $this->pdf = $disco747->get_pdf();
        $this->storage = $disco747->get_storage_manager();
        
        $this->components_loaded = true;
    }
    
    private function validate_form_data($post_data) {
        $data = array(
            'preventivo_id' => $this->generate_preventivo_id(),
            'nome_referente' => sanitize_text_field($post_data['nome_referente'] ?? ''),
            'cognome_referente' => sanitize_text_field($post_data['cognome_referente'] ?? ''),
            'cellulare' => sanitize_text_field($post_data['cellulare'] ?? ''),
            'mail' => sanitize_email($post_data['mail'] ?? ''),
            'codice_fiscale' => sanitize_text_field($post_data['codice_fiscale'] ?? ''),
            'data_evento' => sanitize_text_field($post_data['data_evento'] ?? ''),
            'tipo_evento' => sanitize_text_field($post_data['tipo_evento'] ?? ''),
            'tipo_menu' => sanitize_text_field($post_data['tipo_menu'] ?? ''),
            'numero_invitati' => intval($post_data['numero_invitati'] ?? 0),
            'orario_inizio' => sanitize_text_field($post_data['orario_inizio'] ?? ''),
            'orario_fine' => sanitize_text_field($post_data['orario_fine'] ?? ''),
            'importo_preventivo' => floatval($post_data['importo_preventivo'] ?? 0),
            'acconto' => floatval($post_data['acconto'] ?? 0),
            'omaggio1' => sanitize_text_field($post_data['omaggio1'] ?? ''),
            'omaggio2' => sanitize_text_field($post_data['omaggio2'] ?? ''),
            'omaggio3' => sanitize_text_field($post_data['omaggio3'] ?? ''),
            'extra1' => sanitize_text_field($post_data['extra1'] ?? ''),
            'extra1_importo' => floatval($post_data['extra1_importo'] ?? 0),
            'extra2' => sanitize_text_field($post_data['extra2'] ?? ''),
            'extra2_importo' => floatval($post_data['extra2_importo'] ?? 0),
            'extra3' => sanitize_text_field($post_data['extra3'] ?? ''),
            'extra3_importo' => floatval($post_data['extra3_importo'] ?? 0),
            'note_interne' => sanitize_textarea_field($post_data['note_interne'] ?? ''),
            'stato' => sanitize_text_field($post_data['stato'] ?? 'attivo'),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        if (empty($data['nome_referente']) || empty($data['data_evento'])) {
            return false;
        }
        
        return $data;
    }
    
    private function generate_preventivo_id() {
        $year = date('y');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $year . $random;
    }
    
    private function save_preventivo($data) {
        global $wpdb;
        
        $insert_data = array(
            'preventivo_id' => $data['preventivo_id'],
            'nome_referente' => $data['nome_referente'],
            'cognome_referente' => $data['cognome_referente'],
            'cellulare' => $data['cellulare'],
            'mail' => $data['mail'],
            'codice_fiscale' => $data['codice_fiscale'],
            'data_evento' => $data['data_evento'],
            'tipo_evento' => $data['tipo_evento'],
            'tipo_menu' => $data['tipo_menu'],
            'numero_invitati' => $data['numero_invitati'],
            'orario_inizio' => $data['orario_inizio'],
            'orario_fine' => $data['orario_fine'],
            'importo_preventivo' => $data['importo_preventivo'],
            'acconto' => $data['acconto'],
            'omaggio1' => $data['omaggio1'],
            'omaggio2' => $data['omaggio2'],
            'omaggio3' => $data['omaggio3'],
            'extra1' => $data['extra1'],
            'extra1_importo' => $data['extra1_importo'],
            'extra2' => $data['extra2'],
            'extra2_importo' => $data['extra2_importo'],
            'extra3' => $data['extra3'],
            'extra3_importo' => $data['extra3_importo'],
            'note_interne' => $data['note_interne'],
            'stato' => $data['stato'],
            'created_by' => $data['created_by'],
            'created_at' => $data['created_at']
        );
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result === false) {
            $this->log('[Forms] Errore SQL: ' . $wpdb->last_error, 'ERROR');
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    private function create_excel_safe($data) {
        try {
            if (!$this->excel) {
                throw new \Exception('Excel generator non disponibile');
            }
            return $this->excel->generate_excel($data);
        } catch (\Exception $e) {
            $this->log('[Excel] Errore: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    private function create_pdf_safe($data) {
        try {
            if (!$this->pdf) {
                throw new \Exception('PDF generator non disponibile');
            }
            return $this->pdf->generate_pdf($data);
        } catch (\Exception $e) {
            $this->log('[PDF] Errore: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    private function upload_to_storage_safe($excel_path, $pdf_path, $data) {
        try {
            if (!$this->storage) {
                throw new \Exception('Storage non disponibile');
            }
            
            $uploaded = false;
            
            if ($excel_path) {
                $this->storage->upload_file($excel_path, $data);
                $uploaded = true;
            }
            
            if ($pdf_path) {
                $this->storage->upload_file($pdf_path, $data);
                $uploaded = true;
            }
            
            return $uploaded ? 'success' : false;
            
        } catch (\Exception $e) {
            $this->log('[Storage] Errore: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    private function log($message, $level = 'INFO') {
        if (!$this->log_enabled) {
            return;
        }
        error_log("[747-Forms-{$level}] " . $message);
    }
}