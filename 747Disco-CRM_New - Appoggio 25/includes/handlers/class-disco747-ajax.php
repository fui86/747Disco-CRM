<?php
/**
 * Classe per gestione richieste AJAX - 747 Disco CRM
 * VERSIONE CORRETTA: Rimossi hook AJAX duplicati per preventivi
 *
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @version    11.6.1-FIXED
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Ajax {

    /**
     * Componenti core
     */
    private $config;
    private $database;
    private $auth;
    private $storage_manager;
    private $pdf_generator;
    private $excel_generator;
    
    /**
     * Flag debug
     */
    private $debug_mode = true;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->load_dependencies();
        $this->register_ajax_hooks();
        $this->log('AJAX Handler inizializzato (SENZA hook preventivi duplicati)');
    }

    /**
     * Carica dipendenze
     */
    private function load_dependencies() {
        $disco747_crm = disco747_crm();
        
        $this->config = $disco747_crm->get_config();
        $this->database = $disco747_crm->get_database();
        $this->auth = $disco747_crm->get_auth();
        $this->storage_manager = $disco747_crm->get_storage_manager();
        $this->pdf_generator = $disco747_crm->get_pdf();
        $this->excel_generator = $disco747_crm->get_excel();
    }

    /**
     * Registra hook AJAX - CORRETTO SENZA DUPLICATI PREVENTIVI
     */
    private function register_ajax_hooks() {
        
        // ✅ SOLO handlers per funzionalità NON preventivi
        
        // Handlers per sincronizzazione
        add_action('wp_ajax_disco747_sync_storage', array($this, 'handle_sync_storage'));
        add_action('wp_ajax_nopriv_disco747_sync_storage', array($this, 'handle_sync_storage'));
        
        add_action('wp_ajax_disco747_sync_single_preventivo', array($this, 'handle_sync_single_preventivo'));
        add_action('wp_ajax_nopriv_disco747_sync_single_preventivo', array($this, 'handle_sync_single_preventivo'));
        
        // Handlers per amministrazione
        add_action('wp_ajax_disco747_get_stats', array($this, 'handle_get_stats'));
        add_action('wp_ajax_nopriv_disco747_get_stats', array($this, 'handle_get_stats'));
        
        add_action('wp_ajax_disco747_export_data', array($this, 'handle_export_data'));
        add_action('wp_ajax_nopriv_disco747_export_data', array($this, 'handle_export_data'));
        
        // Handlers per configurazione
        add_action('wp_ajax_disco747_save_settings', array($this, 'handle_save_settings'));
        add_action('wp_ajax_nopriv_disco747_save_settings', array($this, 'handle_save_settings'));
        
        add_action('wp_ajax_disco747_save_message_template', array($this, 'handle_save_message_template'));
        add_action('wp_ajax_nopriv_disco747_save_message_template', array($this, 'handle_save_message_template'));
        
        // Handlers per file
        add_action('wp_ajax_disco747_regenerate_files', array($this, 'handle_regenerate_files'));
        add_action('wp_ajax_nopriv_disco747_regenerate_files', array($this, 'handle_regenerate_files'));
        
        // ❌ RIMOSSI: Hook preventivi gestiti SOLO da class-disco747-forms.php
        // Gli hook seguenti NON sono più registrati qui per evitare conflitti:
        // - wp_ajax_disco747_save_preventivo
        // - wp_ajax_disco747_get_preventivo  
        // - wp_ajax_disco747_delete_preventivo
        // - wp_ajax_disco747_get_preventivi
        // - wp_ajax_disco747_search_preventivi
        // - wp_ajax_disco747_duplicate_preventivo
        
        $this->log('Hook AJAX registrati (ESCLUSI preventivi per evitare conflitti)');
    }

    // ========================================================================
    // HANDLERS AJAX PER SINCRONIZZAZIONE
    // ========================================================================

    /**
     * Handler per sincronizzazione storage
     */
    public function handle_sync_storage() {
        try {
            if (!$this->verify_nonce()) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            if (!$this->check_permissions('manage_storage')) {
                wp_send_json_error('Permessi insufficienti');
                return;
            }
            
            $result = $this->storage_manager->sync_all();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (\Exception $e) {
            $this->log('Errore sync_storage: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore durante la sincronizzazione: ' . $e->getMessage());
        }
    }

    /**
     * Handler per sincronizzazione singolo preventivo
     */
    public function handle_sync_single_preventivo() {
        try {
            if (!$this->verify_nonce()) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            
            if ($preventivo_id <= 0) {
                wp_send_json_error('ID preventivo non valido');
                return;
            }
            
            $result = $this->storage_manager->sync_preventivo($preventivo_id);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (\Exception $e) {
            $this->log('Errore sync_single_preventivo: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore sincronizzazione: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // HANDLERS AJAX PER STATISTICHE
    // ========================================================================

    /**
     * Handler per ottenere statistiche
     */
    public function handle_get_stats() {
        try {
            if (!$this->verify_nonce()) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $period = sanitize_text_field($_POST['period'] ?? 'month');
            $stats = $this->database->get_statistics($period);
            
            wp_send_json_success($stats);
            
        } catch (\Exception $e) {
            $this->log('Errore get_stats: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore recupero statistiche');
        }
    }

    /**
     * Handler per esportazione dati
     */
    public function handle_export_data() {
        try {
            if (!$this->verify_nonce()) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            if (!$this->check_permissions('export_data')) {
                wp_send_json_error('Permessi insufficienti');
                return;
            }
            
            $format = sanitize_text_field($_POST['format'] ?? 'excel');
            $filters = $_POST['filters'] ?? array();
            
            $result = $this->database->export_preventivi($format, $filters);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (\Exception $e) {
            $this->log('Errore export_data: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore esportazione dati');
        }
    }

    // ========================================================================
    // HANDLERS AJAX PER CONFIGURAZIONE
    // ========================================================================

    /**
     * Handler per salvataggio impostazioni
     */
    public function handle_save_settings() {
        try {
            if (!$this->verify_nonce()) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            if (!$this->check_permissions('manage_settings')) {
                wp_send_json_error('Permessi insufficienti');
                return;
            }
            
            $settings = $_POST['settings'] ?? array();
            
            // Sanitizza impostazioni
            $clean_settings = $this->sanitize_settings($settings);
            
            // Salva impostazioni
            $result = update_option('disco747_settings', $clean_settings);
            
            if ($result) {
                wp_send_json_success('Impostazioni salvate con successo');
            } else {
                wp_send_json_error('Errore salvataggio impostazioni');
            }
            
        } catch (\Exception $e) {
            $this->log('Errore save_settings: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore salvataggio impostazioni');
        }
    }

    /**
     * Handler per salvataggio template messaggi
     */
    public function handle_save_message_template() {
        try {
            if (!$this->verify_nonce()) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $template_type = sanitize_text_field($_POST['template_type'] ?? '');
            $template_content = wp_kses_post($_POST['template_content'] ?? '');
            
            if (empty($template_type) || empty($template_content)) {
                wp_send_json_error('Parametri mancanti');
                return;
            }
            
            $templates = get_option('disco747_message_templates', array());
            $templates[$template_type] = $template_content;
            
            $result = update_option('disco747_message_templates', $templates);
            
            if ($result) {
                wp_send_json_success('Template salvato con successo');
            } else {
                wp_send_json_error('Errore salvataggio template');
            }
            
        } catch (\Exception $e) {
            $this->log('Errore save_message_template: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore salvataggio template');
        }
    }

    // ========================================================================
    // HANDLERS AJAX PER FILE
    // ========================================================================

    /**
     * Handler per rigenerazione file
     */
    public function handle_regenerate_files() {
        try {
            if (!$this->verify_nonce()) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            
            if ($preventivo_id <= 0) {
                wp_send_json_error('ID preventivo non valido');
                return;
            }
            
            // Ottieni dati preventivo
            $preventivo = $this->database->get_preventivo($preventivo_id);
            
            if (!$preventivo) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            // Rigenera file Excel e PDF
            $excel_result = $this->excel_generator->generate($preventivo);
            $pdf_result = $this->pdf_generator->generate($preventivo);
            
            if ($excel_result && $pdf_result) {
                wp_send_json_success('File rigenerati con successo');
            } else {
                wp_send_json_error('Errore rigenerazione file');
            }
            
        } catch (\Exception $e) {
            $this->log('Errore regenerate_files: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore rigenerazione file');
        }
    }

    // ========================================================================
    // UTILITY METHODS
    // ========================================================================

    /**
     * Verifica nonce
     */
    private function verify_nonce() {
        $nonce = $_POST['nonce'] ?? '';
        return wp_verify_nonce($nonce, 'disco747_admin_nonce');
    }

    /**
     * Controlla permessi
     */
    private function check_permissions($capability) {
        return current_user_can('manage_options');
    }

    /**
     * Sanitizza impostazioni
     */
    private function sanitize_settings($settings) {
        $clean = array();
        
        foreach ($settings as $key => $value) {
            $clean_key = sanitize_key($key);
            
            if (is_array($value)) {
                $clean[$clean_key] = $this->sanitize_settings($value);
            } else {
                $clean[$clean_key] = sanitize_text_field($value);
            }
        }
        
        return $clean;
    }

    /**
     * Log helper
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode) {
            error_log('[747Disco-Ajax] [' . $level . '] ' . $message);
        }
    }
}