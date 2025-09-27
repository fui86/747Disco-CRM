<?php
/**
 * Classe per la gestione dell'area amministrativa del plugin 747 Disco CRM
 * 
 * @package    Disco747_CRM
 * @subpackage Admin
 * @version    11.5.9-EXCEL-SCAN
 */

namespace Disco747_CRM\Admin;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Admin {
    
    private $config;
    private $database;
    private $auth;
    private $storage_manager;
    private $googledrive_sync;
    private $pdf_handler;
    private $excel_handler;
    
    private $min_capability = 'manage_options';
    private $asset_version;
    private $admin_notices = array();
    private $hooks_registered = false;
    private $debug_mode = true;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->asset_version = defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.5.9';
        $this->debug_mode = defined('DISCO747_CRM_DEBUG') && DISCO747_CRM_DEBUG;
        add_action('init', array($this, 'delayed_init'), 10);
    }

    /**
     * Inizializzazione ritardata
     */
    public function delayed_init() {
        try {
            $this->load_dependencies();
            $this->register_admin_hooks();
            $this->log('Admin Manager inizializzato');
        } catch (\Exception $e) {
            $this->log('Errore inizializzazione Admin: ' . $e->getMessage(), 'error');
            $this->add_admin_notice(
                'Errore inizializzazione 747 Disco CRM. Controlla i log per maggiori dettagli.',
                'error'
            );
        }
    }

    /**
     * Carica dipendenze
     */
    private function load_dependencies() {
        $disco747_crm = disco747_crm();
        
        if (!$disco747_crm || !$disco747_crm->is_initialized()) {
            throw new \Exception('Plugin principale non ancora inizializzato');
        }

        $this->config = $disco747_crm->get_config();
        $this->database = $disco747_crm->get_database();
        $this->auth = $disco747_crm->get_auth();
        $this->storage_manager = $disco747_crm->get_storage_manager();
        $this->pdf_handler = $disco747_crm->get_pdf();
        $this->excel_handler = $disco747_crm->get_excel();
        
        // Inizializza GoogleDrive Sync
        if ($this->storage_manager) {
            $drive_service = $this->storage_manager->get_drive_service();
            if ($drive_service) {
                require_once DISCO747_CRM_PLUGIN_DIR . 'includes/storage/class-disco747-googledrive-sync.php';
                $this->googledrive_sync = new \Disco747_CRM\Storage\Disco747_GoogleDrive_Sync($drive_service);
            }
        }
    }

    /**
     * Registra hook WordPress
     */
    private function register_admin_hooks() {
        if ($this->hooks_registered) {
            return;
        }

        try {
            // Menu
            add_action('admin_menu', array($this, 'add_admin_menu'));
            
            // Assets
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            
            // Notices
            add_action('admin_notices', array($this, 'show_admin_notices'));
            
            // Plugin action links
            add_filter('plugin_action_links_' . plugin_basename(DISCO747_CRM_PLUGIN_FILE), 
                array($this, 'add_plugin_action_links'));
            
            // AJAX handlers
            add_action('wp_ajax_disco747_batch_scan_excel', array($this, 'handle_batch_scan_excel'));
            add_action('wp_ajax_disco747_get_excel_analysis', array($this, 'handle_get_excel_analysis'));
            add_action('wp_ajax_disco747_save_preventivo', array($this, 'handle_save_preventivo'));
            add_action('wp_ajax_disco747_delete_preventivo', array($this, 'handle_delete_preventivo'));
            add_action('wp_ajax_disco747_get_preventivo', array($this, 'handle_get_preventivo'));
            
            $this->hooks_registered = true;
            $this->log('Hook WordPress registrati');
            
        } catch (\Exception $e) {
            $this->log('Errore registrazione hook: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Aggiunge menu amministrazione
     */
    public function add_admin_menu() {
        add_menu_page(
            __('PreventiviParty', 'disco747'),
            __('PreventiviParty', 'disco747'),
            $this->min_capability,
            'disco747-crm',
            array($this, 'render_main_dashboard'),
            'dashicons-clipboard',
            30
        );
        
        add_submenu_page(
            'disco747-crm',
            __('Impostazioni', 'disco747'),
            __('Impostazioni', 'disco747'),
            $this->min_capability,
            'disco747-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'disco747-crm',
            __('Messaggi Automatici', 'disco747'),
            __('Messaggi Automatici', 'disco747'),
            $this->min_capability,
            'disco747-messages',
            array($this, 'render_messages_page')
        );
        
        add_submenu_page(
            'disco747-crm',
            __('Scansione Excel Auto', 'disco747'),
            __('Scansione Excel Auto', 'disco747'),
            $this->min_capability,
            'disco747-scan-excel',
            array($this, 'render_scan_excel_page')
        );
    }

    /**
     * Carica assets admin
     */
    public function enqueue_admin_assets($hook_suffix) {
        if (strpos($hook_suffix, 'disco747') === false) return;
        
        // CSS
        wp_enqueue_style('disco747-admin-style', 
            DISCO747_CRM_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            $this->asset_version
        );
        
        // JS principale
        wp_enqueue_script('disco747-admin-script', 
            DISCO747_CRM_PLUGIN_URL . 'assets/js/admin.js', 
            array('jquery'), 
            $this->asset_version, 
            true
        );
        
        // Localizzazione
        wp_localize_script('disco747-admin-script', 'disco747_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('disco747_ajax_nonce')
        ));
        
        // Assets specifici per Excel Scan
        if ($hook_suffix === 'preventiviparty_page_disco747-scan-excel') {
            wp_enqueue_style('disco747-excel-scan-style',
                DISCO747_CRM_PLUGIN_URL . 'assets/css/excel-scan.css',
                array(),
                $this->asset_version
            );
            
            wp_enqueue_script('disco747-excel-scan-script',
                DISCO747_CRM_PLUGIN_URL . 'assets/js/excel-scan.js',
                array('jquery'),
                $this->asset_version,
                true
            );
            
            wp_localize_script('disco747-excel-scan-script', 'disco747ExcelScan', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('disco747_excel_scan'),
                'strings' => array(
                    'processing' => 'Elaborazione in corso...',
                    'success' => 'Operazione completata',
                    'error' => 'Si è verificato un errore'
                )
            ));
        }
    }

    /**
     * HANDLER AJAX: Batch Scan Excel con debug dettagliato
     */
    public function handle_batch_scan_excel() {
        // Log iniziale
        $this->log("[AJAX] handle_batch_scan_excel chiamato");
        
        // Verifica permessi
        if (!current_user_can($this->min_capability)) {
            $this->log("[AJAX] Errore: utente non autorizzato", 'error');
            wp_send_json_error(array('message' => 'Non autorizzato'));
            return;
        }
        
        // Verifica nonce
        if (!check_ajax_referer('disco747_excel_scan', 'nonce', false)) {
            $this->log("[AJAX] Errore: nonce non valido", 'error');
            wp_send_json_error(array('message' => 'Nonce non valido'));
            return;
        }
        
        // Crea tabella se non esiste
        if ($this->database) {
            $this->database->create_excel_analysis_table();
        }
        
        // Parametri
        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        $file_name = isset($_POST['file_name']) ? sanitize_text_field($_POST['file_name']) : '';
        $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
        $current_index = isset($_POST['current_index']) ? intval($_POST['current_index']) : 0;
        $total_files = isset($_POST['total_files']) ? intval($_POST['total_files']) : 1;
        
        $this->log("[AJAX] Processing file {$current_index}/{$total_files}: {$file_name} (ID: {$file_id})");
        
        // Response base
        $response = array(
            'ok' => false,
            'data' => null,
            'error' => '',
            'log' => array(),
            'database_id' => null
        );
        
        try {
            // Verifica Google Drive Sync
            if (!$this->googledrive_sync) {
                throw new \Exception('Google Drive Sync non disponibile. Verifica configurazione OAuth.');
            }
            
            // Analizza file singolo
            $scan_result = $this->googledrive_sync->read_single_excel($file_id);
            
            // Merge risultati
            $response['ok'] = $scan_result['ok'];
            $response['error'] = $scan_result['error'];
            $response['log'] = array_merge($response['log'], $scan_result['log']);
            
            if ($scan_result['ok'] && !empty($scan_result['data'])) {
                // Aggiungi metadati
                $scan_result['data']['filename'] = $file_name;
                $scan_result['data']['drive_path'] = $file_path;
                
                $response['data'] = $scan_result['data'];
                
                // Salva nel database
                if ($this->database) {
                    try {
                        $analysis_id = $this->database->save_excel_analysis($scan_result['data']);
                        
                        if ($analysis_id) {
                            $response['database_id'] = $analysis_id;
                            $response['log'][] = "[DB] Salvato nel database con ID: {$analysis_id}";
                            $this->log("[AJAX] Analisi salvata nel database con ID: {$analysis_id}");
                        } else {
                            $response['log'][] = "[DB] Errore salvataggio database";
                            $this->log("[AJAX] Errore salvataggio database", 'error');
                        }
                    } catch (\Exception $e) {
                        $response['log'][] = "[DB] Errore: " . $e->getMessage();
                        $this->log("[AJAX] Errore database: " . $e->getMessage(), 'error');
                    }
                }
                
                $response['log'][] = "[SUCCESS] File processato con successo";
                
            } else {
                $response['log'][] = "[ERROR] Analisi fallita: " . $scan_result['error'];
            }
            
        } catch (\Exception $e) {
            $response['ok'] = false;
            $response['error'] = $e->getMessage();
            $response['log'][] = "[EXCEPTION] " . $e->getMessage();
            $this->log("[AJAX] Exception: " . $e->getMessage(), 'error');
        }
        
        // Log finale
        $this->log("[AJAX] Risultato: " . ($response['ok'] ? 'SUCCESS' : 'FAILED'));
        
        // Invia risposta
        if ($response['ok']) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error($response);
        }
    }

    /**
     * HANDLER AJAX: Ottieni risultati analisi Excel
     */
    public function handle_get_excel_analysis() {
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error(array('message' => 'Non autorizzato'));
            return;
        }
        
        if (!check_ajax_referer('disco747_excel_scan', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce non valido'));
            return;
        }
        
        try {
            $args = array(
                'per_page' => isset($_POST['per_page']) ? intval($_POST['per_page']) : 20,
                'page' => isset($_POST['page']) ? intval($_POST['page']) : 1,
                'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : ''
            );
            
            $results = $this->database->get_excel_analysis($args);
            
            wp_send_json_success($results);
            
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Render pagina principale
     */
    public function render_main_dashboard() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        
        switch ($action) {
            case 'form_preventivo':
                $this->render_form_preventivo();
                break;
                
            case 'dashboard_preventivi':
                $this->render_dashboard_preventivi();
                break;
                
            case 'edit_preventivo':
                $this->render_edit_preventivo();
                break;
                
            default:
                $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/main-page.php';
                if (file_exists($template_path)) {
                    include $template_path;
                }
                break;
        }
    }

    /**
     * Render form preventivo con precompilazione da Excel
     */
    private function render_form_preventivo() {
        $preventivo = null;
        
        // Se viene da Excel scan, precompila i dati
        if (isset($_GET['source']) && $_GET['source'] === 'excel_analysis') {
            $analysis_id = isset($_GET['analysis_id']) ? intval($_GET['analysis_id']) : 0;
            
            if ($analysis_id > 0 && $this->database) {
                $this->log("[EDIT] Caricamento dati da analisi Excel ID: {$analysis_id}");
                
                try {
                    $analysis = $this->database->get_excel_analysis_by_id($analysis_id);
                    
                    if ($analysis) {
                        // Converti in oggetto compatibile con il form
                        $preventivo = (object) array(
                            'id' => 0,
                            'nome_referente' => $analysis['nome_referente'] ?? '',
                            'cognome_referente' => $analysis['cognome_referente'] ?? '',
                            'cellulare' => $analysis['cellulare'] ?? '',
                            'email' => $analysis['email'] ?? '',
                            'tipo_evento' => $analysis['tipo_evento'] ?? '',
                            'data_evento' => $analysis['data_evento'] ?? '',
                            'orario' => $analysis['orario'] ?? '',
                            'numero_invitati' => $analysis['numero_invitati'] ?? 0,
                            'tipo_menu' => $analysis['tipo_menu'] ?? '',
                            'importo' => $analysis['importo'] ?? 0,
                            'acconto' => $analysis['acconto'] ?? 0,
                            'saldo' => $analysis['saldo'] ?? 0,
                            'omaggio1' => $analysis['omaggio1'] ?? '',
                            'omaggio2' => $analysis['omaggio2'] ?? '',
                            'omaggio3' => $analysis['omaggio3'] ?? '',
                            'extra1_nome' => $analysis['extra1_nome'] ?? '',
                            'extra1_prezzo' => $analysis['extra1_prezzo'] ?? 0,
                            'extra2_nome' => $analysis['extra2_nome'] ?? '',
                            'extra2_prezzo' => $analysis['extra2_prezzo'] ?? 0,
                            'extra3_nome' => $analysis['extra3_nome'] ?? '',
                            'extra3_prezzo' => $analysis['extra3_prezzo'] ?? 0,
                            'source' => 'excel_analysis',
                            'source_filename' => $analysis['filename'] ?? ''
                        );
                        
                        $this->log("[EDIT] Dati precaricati da Excel: " . $analysis['filename']);
                    }
                    
                } catch (\Exception $e) {
                    $this->log("[EDIT] Errore caricamento dati Excel: " . $e->getMessage(), 'error');
                }
            }
        }
        
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/form-preventivo.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>Template form preventivo non trovato.</p></div>';
        }
    }

    /**
     * Render pagina Scansione Excel
     */
    public function render_scan_excel_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die('Non hai i permessi per accedere a questa pagina.');
        }
        
        // Preparazione variabili
        $is_googledrive_configured = $this->is_googledrive_configured();
        $excel_files_list = array();
        $analysis_results = array();
        $total_analysis = 0;
        $last_analysis_date = 'Mai';
        
        // Carica lista file Excel
        if ($is_googledrive_configured && $this->googledrive_sync) {
            try {
                $excel_files_list = $this->googledrive_sync->get_all_excel_files();
                $this->log('File Excel trovati: ' . count($excel_files_list));
            } catch (\Exception $e) {
                $this->log('Errore caricamento lista Excel: ' . $e->getMessage(), 'error');
            }
        }
        
        // Carica risultati analisi dal database
        if ($this->database) {
            try {
                $results = $this->database->get_excel_analysis(array('per_page' => 100));
                $analysis_results = $results['items'];
                $total_analysis = $results['total'];
                
                if (!empty($analysis_results)) {
                    $latest = $analysis_results[0];
                    $last_analysis_date = isset($latest['updated_at']) ? 
                        date('d/m/Y H:i', strtotime($latest['updated_at'])) : 'N/A';
                }
            } catch (\Exception $e) {
                $this->log('Errore caricamento analisi: ' . $e->getMessage(), 'error');
            }
        }
        
        // Include template
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/excel-scan-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap">';
            echo '<h1>Scansione Excel Auto</h1>';
            echo '<div class="error"><p>Template non trovato.</p></div>';
            echo '</div>';
        }
    }

    /**
     * Verifica configurazione Google Drive
     */
    private function is_googledrive_configured() {
        return $this->storage_manager && $this->storage_manager->is_configured();
    }

    /**
     * Log helper
     */
    private function log($message, $level = 'info') {
        if ($this->debug_mode || $level === 'error') {
            error_log("[747Disco-Admin] [{$level}] {$message}");
        }
    }

    /**
     * Altri metodi render...
     */
    public function render_dashboard_preventivi() {
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/dashboard-preventivi.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }
    
    public function render_settings_page() {
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }
    
    public function render_messages_page() {
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/messages-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }
    
    public function render_edit_preventivo() {
        $this->render_form_preventivo();
    }
    
    public function show_admin_notices() {
        foreach ($this->admin_notices as $notice) {
            echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">';
            echo '<p>' . esc_html($notice['message']) . '</p>';
            echo '</div>';
        }
    }
    
    public function add_admin_notice($message, $type = 'info') {
        $this->admin_notices[] = array(
            'message' => $message,
            'type' => $type
        );
    }
    
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=disco747-settings') . '">Impostazioni</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}