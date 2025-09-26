<?php
/**
 * Classe per la gestione dell'area amministrativa del plugin 747 Disco CRM
 * CORRETTA: Replica ESATTAMENTE il menu del vecchio PreventiviParty
 * CON AGGIUNTA: Routing interno per nuove pagine preventivi + SCANSIONE EXCEL AUTO COMPLETA + BATCH ANALYSIS + PULSANTI MODIFICA
 * VERSIONE DEBUG: Debug completo per troubleshooting pulsanti modifica
 *
 * @package    Disco747_CRM
 * @subpackage Admin
 * @since      11.4.2
 * @version    11.4.6-DEBUG-MODIFICA-BUTTONS
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Admin;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_Admin VERSIONE DEBUG COMPLETA
 * 
 * @since 11.4.6-DEBUG-MODIFICA-BUTTONS
 */
class Disco747_Admin {
    
    /**
     * Componenti core
     */
    private $config;
    private $database;
    private $auth;
    private $storage_manager;
    private $pdf_excel_handler;  
    private $excel_handler;      
    private $googledrive_sync;   
    
    /**
     * Configurazione admin
     */
    private $min_capability = 'manage_options';
    private $asset_version;
    private $admin_notices = array();
    private $hooks_registered = false;
    private $debug_mode = true;

    /**
     * Costruttore SAFE con delay
     */
    public function __construct() {
        $this->asset_version = defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.4.6';
        
        // Inizializzazione ritardata per evitare problemi di dipendenze
        add_action('init', array($this, 'delayed_init'), 10);
    }

    /**
     * Inizializzazione ritardata e sicura
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
     * Carica dipendenze SAFE - CORREZIONE ROBUSTA PER GOOGLEDRIVE_SYNC
     */
    private function load_dependencies() {
        // Ottieni istanza principale (ora dovrebbe essere disponibile)
        $disco747_crm = disco747_crm();
        
        if (!$disco747_crm || !$disco747_crm->is_initialized()) {
            throw new \Exception('Plugin principale non ancora inizializzato');
        }

        // Carica componenti dal plugin principale
        $this->config = $disco747_crm->get_config();
        $this->database = $disco747_crm->get_database();
        $this->auth = $disco747_crm->get_auth();
        $this->storage_manager = $disco747_crm->get_storage_manager();
        
        // Carica handlers per preventivi (METODI CORRETTI)
        $this->pdf_excel_handler = $disco747_crm->get_pdf();
        $this->excel_handler = $disco747_crm->get_excel();
        
        // CORREZIONE: Carica Google Drive Sync con multiple strategie
        $this->googledrive_sync = $this->load_googledrive_sync_robust($disco747_crm);
        
        $this->log('Dipendenze caricate - GoogleDrive Sync: ' . ($this->googledrive_sync ? 'OK' : 'NON DISPONIBILE'));
    }

    /**
     * NUOVO: Caricamento robusto Google Drive Sync con fallback
     */
    private function load_googledrive_sync_robust($disco747_crm) {
        try {
            // Strategia 1: Metodo get_gdrive_sync se disponibile
            if (method_exists($disco747_crm, 'get_gdrive_sync')) {
                $sync = $disco747_crm->get_gdrive_sync();
                if ($sync) {
                    $this->log('Google Drive Sync caricato via get_gdrive_sync()');
                    return $sync;
                }
            }
            
            // Strategia 2: Tramite storage manager
            if ($this->storage_manager && method_exists($this->storage_manager, 'get_googledrive_sync')) {
                $sync = $this->storage_manager->get_googledrive_sync();
                if ($sync) {
                    $this->log('Google Drive Sync caricato via storage_manager');
                    return $sync;
                }
            }
            
            // Strategia 3: Istanziazione diretta se classe esiste
            if (class_exists('\\Disco747_CRM\\Storage\\Disco747_GoogleDrive_Sync')) {
                $googledrive = $disco747_crm->get_googledrive();
                if ($googledrive) {
                    $sync = new \Disco747_CRM\Storage\Disco747_GoogleDrive_Sync($googledrive);
                    $this->log('Google Drive Sync istanziato direttamente');
                    return $sync;
                }
            }
            
            $this->log('Google Drive Sync NON disponibile - nessuna strategia funzionante', 'warning');
            return null;
            
        } catch (\Exception $e) {
            $this->log('Errore caricamento Google Drive Sync: ' . $e->getMessage(), 'warning');
            return null;
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
            // Menu principale
            add_action('admin_menu', array($this, 'add_admin_menu'));
            
            // Assets admin
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            
            // Notice admin
            add_action('admin_notices', array($this, 'show_admin_notices'));
            
            // Link azioni plugin
            add_filter('plugin_action_links_' . 
                plugin_basename(DISCO747_CRM_PLUGIN_FILE), array($this, 'add_plugin_action_links'));
            
            // AJAX handlers esistenti
            add_action('wp_ajax_disco747_dropbox_auth', array($this, 'handle_dropbox_auth'));
            add_action('wp_ajax_disco747_googledrive_auth', array($this, 'handle_googledrive_auth'));
            add_action('wp_ajax_disco747_test_storage', array($this, 'handle_test_storage'));
            
            // AJAX handlers per preventivi
            add_action('wp_ajax_disco747_save_preventivo', array($this, 'handle_save_preventivo'));
            add_action('wp_ajax_disco747_delete_preventivo', array($this, 'handle_delete_preventivo'));
            add_action('wp_ajax_disco747_get_preventivo', array($this, 'handle_get_preventivo'));
            
            // NUOVO: AJAX handler per batch analysis
            add_action('wp_ajax_disco747_batch_scan_excel', array($this, 'handle_batch_scan_excel'));
            
            $this->hooks_registered = true;
            $this->log('Hook WordPress registrati');
            
        } catch (\Exception $e) {
            $this->log('Errore registrazione hook: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Aggiunge menu amministrazione - REPLICA ESATTA PreventiviParty
     */
    public function add_admin_menu() {
        try {
            // Menu principale: PreventiviParty (come il vecchio plugin)
            add_menu_page(
                __('PreventiviParty', 'disco747'),
                __('PreventiviParty', 'disco747'),
                $this->min_capability,
                'disco747-crm',
                array($this, 'render_main_dashboard'),
                'dashicons-clipboard',
                30
            );

            // Sottomenu: Impostazioni (replica esatta)
            add_submenu_page(
                'disco747-crm',
                __('Impostazioni', 'disco747'),
                __('Impostazioni', 'disco747'),
                $this->min_capability,
                'disco747-settings',
                array($this, 'render_settings_page')
            );

            // Sottomenu: Messaggi Automatici (replica esatta)
            add_submenu_page(
                'disco747-crm',
                __('Messaggi Automatici', 'disco747'),
                __('Messaggi Automatici', 'disco747'),
                $this->min_capability,
                'disco747-messages',
                array($this, 'render_messages_page')
            );

            // NUOVO: Sottomenu Scansione Excel Auto
            add_submenu_page(
                'disco747-crm',
                __('Scansione Excel Auto', 'disco747'),
                __('Scansione Excel Auto', 'disco747'),
                $this->min_capability,
                'disco747-scan-excel',
                array($this, 'render_scan_excel_auto_page')
            );

            // Debug page (se abilitata)
            if (get_option('disco747_debug_mode', false)) {
                add_submenu_page(
                    'disco747-crm',
                    __('Debug & Test', 'disco747'),
                    __('Debug & Test', 'disco747'),
                    $this->min_capability,
                    'disco747-debug',
                    array($this, 'render_debug_page')
                );
            }

            $this->log('Menu amministrazione aggiunto');
            
        } catch (\Exception $e) {
            $this->log('Errore aggiunta menu: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * MODIFICATO: Renderizza dashboard principale CON routing interno
     */
    public function render_main_dashboard() {
        try {
            // Controlla permessi
            if (!current_user_can($this->min_capability)) {
                wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
            }
            
            // Routing interno per le pagine preventivi
            $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
            
            switch ($action) {
                case 'new_preventivo':
                    $this->render_form_preventivo();
                    break;
                    
                case 'edit_preventivo':
                    $this->render_edit_preventivo();
                    break;
                    
                case 'dashboard_preventivi':
                    $this->render_dashboard_preventivi();
                    break;
                    
                default:
                    $this->render_main_dashboard_page();
                    break;
            }
            
        } catch (\Exception $e) {
            $this->log('Errore render dashboard: ' . $e->getMessage(), 'error');
            echo '<div class="error"><p>Errore caricamento dashboard.</p></div>';
        }
    }

    /**
     * Renderizza pagina dashboard principale (contenuto originale)
     */
    private function render_main_dashboard_page() {
        // Dati per dashboard
        $stats = $this->get_dashboard_statistics();
        $system_status = $this->get_system_status_summary();
        $recent_preventivi = $this->get_recent_preventivi(5);
        
        // Template esistente con aggiunta pulsanti
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/main-page.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_fallback_dashboard();
        }
    }

    /**
     * CORRETTO: Renderizza pagina "Scansione Excel Auto" CON PREPARAZIONE VARIABILI ROBUSTA E METODI DATABASE CORRETTI
     */
    public function render_scan_excel_auto_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die('Non hai i permessi per accedere a questa pagina.');
        }

        try {
            // === FASE 1: PREPARAZIONE VARIABILI PER TEMPLATE ===
            
            // Verifica configurazione Google Drive
            $is_googledrive_configured = $this->is_googledrive_configured();
            
            // Inizializza variabili di base
            $scan_result = null;
            $show_results = false;
            $excel_files_list = array();
            $analysis_results = array();
            $last_analysis_date = 'Mai';
            $total_analysis = 0;
            
            // Carica lista file Excel se Google Drive è configurato
            if ($is_googledrive_configured && $this->googledrive_sync) {
                try {
                    $excel_files_list = $this->get_excel_files_list();
                    $this->log('File Excel caricati: ' . count($excel_files_list));
                } catch (\Exception $e) {
                    $this->log('Errore caricamento lista Excel: ' . $e->getMessage(), 'warning');
                    $excel_files_list = array();
                }
            }
            
            // CORREZIONE: Carica risultati analisi dal database se disponibile (METODO CORRETTO)
            if ($this->database && method_exists($this->database, 'get_excel_analysis')) {
                try {
                    $analysis_results = $this->database->get_excel_analysis();
                    $total_analysis = count($analysis_results);
                    if (!empty($analysis_results)) {
                        $latest = $analysis_results[0];
                        $last_analysis_date = $latest->analysis_date ?? 'N/A';
                    }
                    $this->log('Analisi caricate dal database: ' . $total_analysis);
                } catch (\Exception $e) {
                    $this->log('Errore caricamento analisi database: ' . $e->getMessage(), 'warning');
                }
            }
            
            // === FASE 2: GESTIONE FORM POST ===
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disco747_scan_nonce'])) {
                $this->log('POST ricevuto per scansione Excel');
                
                if (wp_verify_nonce($_POST['disco747_scan_nonce'], 'disco747_scan_excel_auto')) {
                    $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
                    $this->log('File ID ricevuto: ' . $file_id);
                    
                    if (!empty($file_id) && $this->googledrive_sync) {
                        $this->log('Eseguo debug_read_single_excel per file_id: ' . $file_id);
                        $scan_result = $this->googledrive_sync->debug_read_single_excel($file_id);
                        $show_results = true;
                        
                        // CORREZIONE: Salva automaticamente nel database se disponibile e la scansione è riuscita (METODO CORRETTO)
                        if ($this->database && method_exists($this->database, 'save_excel_analysis') && 
                            $scan_result['ok'] && !empty($scan_result['data'])) {
                            try {
                                $analysis_data = $scan_result['data'];
                                $analysis_data['file_id'] = $file_id;
                                $analysis_id = $this->database->save_excel_analysis($analysis_data);
                                $this->log("Analisi salvata nel database con ID: {$analysis_id}");
                                
                                // Ricarica risultati analisi per aggiornare la lista (METODO CORRETTO)
                                $analysis_results = $this->database->get_excel_analysis();
                                $total_analysis = count($analysis_results);
                            } catch (\Exception $e) {
                                $this->log('Errore salvataggio analisi: ' . $e->getMessage(), 'warning');
                            }
                        }
                        
                        $this->log('Scansione completata: ' . ($scan_result['ok'] ? 'SUCCESS' : 'ERROR'));
                    } else {
                        $scan_result = array(
                            'ok' => false,
                            'data' => array(),
                            'log' => array('Errore: File ID vuoto o Google Drive Sync non disponibile'),
                            'error' => 'File ID vuoto o Google Drive Sync non disponibile'
                        );
                        $show_results = true;
                    }
                } else {
                    $this->log('Verifica nonce fallita', 'warning');
                }
            }
            
            // === FASE 3: RENDERING TEMPLATE ===
            
            // Cerca template con fallback
            $template_paths = array(
                DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/excel-scan-page.php',
                DISCO747_CRM_PLUGIN_DIR . 'templates/admin/excel-scan-page.php',
                DISCO747_CRM_PLUGIN_DIR . 'admin/views/excel-scan-page.php',
                DISCO747_CRM_PLUGIN_DIR . 'views/excel-scan-page.php'
            );
            
            $template_path = null;
            foreach ($template_paths as $path) {
                if (file_exists($path)) {
                    $template_path = $path;
                    break;
                }
            }
            
            if ($template_path) {
                $this->log('Caricamento template Excel scan: ' . basename($template_path));
                // Include template con tutte le variabili preparate
                include $template_path;
            } else {
                $this->log('Template Excel scan non trovato, uso fallback', 'warning');
                $this->render_fallback_excel_scan();
            }

        } catch (\Exception $e) {
            $this->log('Errore render pagina Excel scan: ' . $e->getMessage(), 'error');
            echo '<div class="wrap">';
            echo '<h1>Errore Scansione Excel</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>Errore durante il caricamento della pagina: ' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * NUOVO: Fallback per pagina Excel scan
     */
    private function render_fallback_excel_scan() {
        echo '<div class="wrap">';
        echo '<h1>📊 Scansione Excel Auto</h1>';
        echo '<div class="notice notice-info">';
        echo '<p><strong>Template in caricamento...</strong> Il sistema sta preparando l\'interfaccia di scansione Excel.</p>';
        echo '</div>';
        
        // Stato Google Drive
        $is_configured = $this->is_googledrive_configured();
        echo '<div class="card" style="padding: 20px; margin: 20px 0;">';
        echo '<h3>Stato Sistema</h3>';
        echo '<p><strong>Google Drive:</strong> ' . ($is_configured ? '✅ Configurato' : '❌ Non configurato') . '</p>';
        echo '<p><strong>Google Drive Sync:</strong> ' . ($this->googledrive_sync ? '✅ Disponibile' : '❌ Non disponibile') . '</p>';
        echo '<p><strong>Database:</strong> ' . ($this->database ? '✅ Disponibile' : '❌ Non disponibile') . '</p>';
        echo '</div>';
        
        if (!$is_configured) {
            echo '<div class="notice notice-error">';
            echo '<p>Configura Google Drive nelle <a href="' . admin_url('admin.php?page=disco747-settings') . '">Impostazioni</a> per utilizzare la scansione Excel.</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * CORRETTO: AJAX handler per batch scan Excel CON SALVATAGGIO DATABASE E METODI CORRETTI
     */
    public function handle_batch_scan_excel() {
        try {
            // Verifica permessi e nonce
            if (!current_user_can($this->min_capability)) {
                wp_die('Non autorizzato');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_batch_scan')) {
                wp_die('Nonce non valido');
            }
            
            // Parametri
            $file_id = sanitize_text_field($_POST['file_id']);
            $file_name = sanitize_text_field($_POST['file_name']);
            $file_path = sanitize_text_field($_POST['file_path']);
            $current_index = intval($_POST['current_index']);
            $total_files = intval($_POST['total_files']);
            
            $this->log("BATCH SCAN: Avvio analisi file {$current_index}/{$total_files}: {$file_name} (ID: {$file_id})");
            
            // Verifica handler disponibile
            if (!$this->googledrive_sync) {
                throw new \Exception('Handler Google Drive Sync non disponibile');
            }
            
            // Esegui scansione singola
            $scan_result = $this->googledrive_sync->debug_read_single_excel($file_id);
            
            // Aggiungi informazioni aggiuntive
            if (isset($scan_result['data'])) {
                $scan_result['data']['nome_file'] = $file_name;
                $scan_result['data']['percorso_drive'] = $file_path;
                $scan_result['data']['file_id'] = $file_id;
            }
            
            // CORREZIONE: Salva automaticamente nel database se l'analisi è riuscita (METODO CORRETTO)
            $analysis_id = null;
            if ($this->database && method_exists($this->database, 'save_excel_analysis') && 
                $scan_result['ok'] && !empty($scan_result['data'])) {
                try {
                    $analysis_data = $scan_result['data'];
                    $analysis_data['file_id'] = $file_id;
                    $analysis_data['ok'] = $scan_result['ok'];
                    
                    $analysis_id = $this->database->save_excel_analysis($analysis_data);
                    $this->log("BATCH SCAN: Analisi salvata nel database con ID: {$analysis_id}");
                    
                    // Aggiungi ID database alla risposta
                    $scan_result['database_id'] = $analysis_id;
                } catch (\Exception $e) {
                    $this->log('BATCH SCAN: Errore salvataggio database: ' . $e->getMessage(), 'warning');
                    // Non bloccare il processo per errori di salvataggio
                }
            }
            
            $this->log("BATCH SCAN: Completata analisi file {$current_index}/{$total_files}: " . 
                      ($scan_result['ok'] ? 'SUCCESSO' : 'ERRORE - ' . ($scan_result['error'] ?? 'Errore sconosciuto')) .
                      ($analysis_id ? " (DB ID: {$analysis_id})" : ''));
            
            // Risposta JSON
            wp_send_json_success($scan_result);
            
        } catch (\Exception $e) {
            $this->log('Errore batch scan AJAX: ' . $e->getMessage(), 'error');
            
            wp_send_json_success(array(
                'ok' => false,
                'error' => $e->getMessage(),
                'log' => array('Errore durante l\'analisi: ' . $e->getMessage()),
                'data' => array()
            ));
        }
    }

    /**
     * Ottieni lista file Excel da Google Drive - CORRETTO NOME METODO
     */
    private function get_excel_files_list() {
        try {
            if (!$this->googledrive_sync) {
                $this->log('Google Drive Sync non disponibile per lista file', 'warning');
                return array();
            }
            
            // CORRETTO: Usa get_all_preventivi (senza _from_drive)
            $all_preventivi = $this->googledrive_sync->get_all_preventivi(true); // force_refresh = true per vedere tutti i file
            
            $excel_files = array();
            foreach ($all_preventivi as $preventivo) {
                if (isset($preventivo['googledrive_id']) && isset($preventivo['filename'])) {
                    $excel_files[] = array(
                        'id' => $preventivo['googledrive_id'],
                        'name' => $preventivo['filename'],
                        'path' => $preventivo['folder_path'] ?? '/747-Preventivi/',
                        'size' => $preventivo['file_size'] ?? 0,
                        'modified' => $preventivo['last_modified'] ?? ''
                    );
                }
            }
            
            $this->log('File Excel caricati da Google Drive: ' . count($excel_files));
            return $excel_files;
            
        } catch (\Exception $e) {
            $this->log('Errore caricamento lista Excel: ' . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * Verifica se Google Drive è configurato - PROTETTO DA ERRORI
     */
    private function is_googledrive_configured() {
        try {
            $gd_credentials = get_option('disco747_gd_credentials', array());
            $client_id = $gd_credentials['client_id'] ?? '';
            $client_secret = $gd_credentials['client_secret'] ?? '';
            $refresh_token = $gd_credentials['refresh_token'] ?? '';
            
            return !empty($client_id) && !empty($client_secret) && !empty($refresh_token);
        } catch (\Exception $e) {
            $this->log('Errore verifica configurazione Google Drive: ' . $e->getMessage(), 'warning');
            return false;
        }
    }

    // =========================================================================
    // METODI FORM PREVENTIVI - MODIFICATI PER SUPPORTARE PULSANTI MODIFICA CON DEBUG COMPLETO
    // =========================================================================

    /**
     * MODIFICATO: Renderizza form per nuovo preventivo CON SUPPORTO DATI URL ENCODED
     */
    private function render_form_preventivo() {
        $preventivo = null;
        $title = 'Nuovo Preventivo';
        $submit_text = 'Crea Preventivo';
        
        // Supporto per dati pre-compilati da scansione Excel manuale
        if (isset($_GET['source']) && $_GET['source'] === 'excel_scan' && isset($_GET['data'])) {
            try {
                $encoded_data = urldecode($_GET['data']);
                $scan_data = json_decode(base64_decode($encoded_data), true);
                
                if ($scan_data && is_array($scan_data)) {
                    $preventivo = new \stdClass();
                    
                    // Converti dati scansione in formato preventivo
                    $preventivo->data_evento = $scan_data['data_evento'] ?? '';
                    $preventivo->tipo_evento = $scan_data['tipo_evento'] ?? '';
                    $preventivo->menu = $scan_data['menu'] ?? '';
                    $preventivo->numero_invitati = intval($scan_data['numero_invitati'] ?? 0);
                    $preventivo->orario_evento = $scan_data['orari_raw'] ?? '';
                    
                    $preventivo->nome_cliente = trim(($scan_data['referente_nome'] ?? '') . ' ' . ($scan_data['referente_cognome'] ?? ''));
                    $preventivo->telefono = $scan_data['telefono'] ?? '';
                    $preventivo->email = $scan_data['email'] ?? '';
                    
                    $preventivo->importo_totale = floatval($scan_data['importo_totale'] ?? 0);
                    $preventivo->acconto = floatval($scan_data['acconto'] ?? 0);
                    
                    $preventivo->omaggio1 = '';
                    $preventivo->omaggio2 = '';
                    $preventivo->omaggio3 = '';
                    
                    $preventivo->id = 0; // Nuovo preventivo
                    $preventivo->source = 'excel_scan';
                    
                    $title = 'Nuovo Preventivo da Scansione Excel';
                    
                    $this->log('Preventivo pre-compilato da dati scansione Excel manuale', 'success');
                }
            } catch (\Exception $e) {
                $this->log('Errore decodifica dati scansione Excel: ' . $e->getMessage(), 'warning');
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
     * MODIFICATO: Renderizza form per modifica preventivo CON DEBUG COMPLETO
     */
    private function render_edit_preventivo() {
        // DEBUG: Log parametri ricevuti
        error_log("🔍 DEBUG EDIT: Parametri GET ricevuti:");
        foreach ($_GET as $key => $value) {
            error_log("  - {$key}: {$value}");
        }
        
        $preventivo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $source = isset($_GET['source']) ? sanitize_key($_GET['source']) : '';
        $analysis_id = isset($_GET['analysis_id']) ? intval($_GET['analysis_id']) : 0;
        
        error_log("🔍 DEBUG EDIT: preventivo_id={$preventivo_id}, source={$source}, analysis_id={$analysis_id}");
        
        $preventivo = null;
        $title = 'Modifica Preventivo';
        $submit_text = 'Aggiorna Preventivo';
        
        // CASO 1: Modifica preventivo esistente (comportamento originale)
        if ($preventivo_id > 0) {
            error_log("🔍 DEBUG EDIT: CASO 1 - Modifica preventivo esistente ID: {$preventivo_id}");
            $preventivo = $this->database->get_preventivo($preventivo_id);
            
            if (!$preventivo) {
                error_log("❌ DEBUG EDIT: Preventivo ID {$preventivo_id} NON TROVATO");
                wp_die('Preventivo non trovato');
            }
            
            error_log("✅ DEBUG EDIT: Preventivo esistente trovato");
            $title = 'Modifica Preventivo #' . $preventivo_id;
            $submit_text = 'Aggiorna Preventivo';
        }
        // CASO 2: Crea nuovo preventivo da analisi Excel
        elseif ($source === 'excel_analysis' && $analysis_id > 0) {
            error_log("🔍 DEBUG EDIT: CASO 2 - Nuovo preventivo da analisi Excel ID: {$analysis_id}");
            
            $preventivo = $this->get_preventivo_from_excel_analysis($analysis_id);
            
            if (!$preventivo) {
                error_log("❌ DEBUG EDIT: Conversione analisi Excel fallita per ID: {$analysis_id}");
                wp_die('Analisi Excel non trovata o non valida');
            }
            
            error_log("✅ DEBUG EDIT: Preventivo da analisi Excel creato con successo");
            $title = 'Nuovo Preventivo da Analisi Excel';
            $submit_text = 'Crea Preventivo';
        }
        // CASO 3: Errore - parametri non validi
        else {
            error_log("❌ DEBUG EDIT: CASO 3 - Parametri non validi");
            wp_die('Parametri non validi per la modifica/creazione preventivo');
        }
        
        // DEBUG: Log dati preventivo finale
        if ($preventivo) {
            error_log("🔍 DEBUG EDIT: Preventivo finale preparato:");
            error_log("  - nome_cliente: " . ($preventivo->nome_cliente ?? 'N/A'));
            error_log("  - menu: " . ($preventivo->menu ?? 'N/A'));
            error_log("  - importo_totale: " . ($preventivo->importo_totale ?? 'N/A'));
            error_log("  - source: " . ($preventivo->source ?? 'N/A'));
        }
        
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/form-preventivo.php';
        
        if (file_exists($template_path)) {
            error_log("✅ DEBUG EDIT: Caricamento template: " . basename($template_path));
            include $template_path;
        } else {
            error_log("❌ DEBUG EDIT: Template form-preventivo.php NON TROVATO");
            echo '<div class="error"><p>Template form preventivo non trovato.</p></div>';
        }
    }

    /**
     * VERSIONE DEBUG COMPLETA: Converti analisi Excel in oggetto preventivo per pre-compilazione form
     */
    private function get_preventivo_from_excel_analysis($analysis_id) {
        error_log("🔍 DEBUG CONVERSION: Inizio conversione analisi Excel ID: {$analysis_id}");
        
        try {
            if (!$this->database) {
                error_log("❌ DEBUG CONVERSION: Database non disponibile");
                return null;
            }
            error_log("✅ DEBUG CONVERSION: Database disponibile");
            
            // PRIMA STRATEGIA: Metodo dedicato get_excel_analysis_by_id
            $analysis = null;
            if (method_exists($this->database, 'get_excel_analysis_by_id')) {
                error_log("✅ DEBUG CONVERSION: Metodo get_excel_analysis_by_id trovato, chiamo...");
                $analysis = $this->database->get_excel_analysis_by_id($analysis_id);
                error_log("📊 DEBUG CONVERSION: Risultato get_excel_analysis_by_id: " . ($analysis ? 'TROVATO' : 'NULL'));
                
                if ($analysis) {
                    error_log("📊 DEBUG CONVERSION: Tipo oggetto ricevuto: " . gettype($analysis));
                    error_log("📊 DEBUG CONVERSION: Classe oggetto: " . get_class($analysis));
                }
            } else {
                error_log("⚠️ DEBUG CONVERSION: Metodo get_excel_analysis_by_id NON trovato, uso query diretta");
                
                // SECONDA STRATEGIA: Query diretta
                global $wpdb;
                $table_name = $wpdb->prefix . 'disco747_excel_analysis';
                error_log("📊 DEBUG CONVERSION: Tabella: {$table_name}");
                
                // Verifica se la tabella esiste
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
                if ($table_exists !== $table_name) {
                    error_log("❌ DEBUG CONVERSION: Tabella {$table_name} NON ESISTE");
                    return null;
                }
                error_log("✅ DEBUG CONVERSION: Tabella {$table_name} esiste");
                
                $query = "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1";
                error_log("📊 DEBUG CONVERSION: Query: {$query} con ID: {$analysis_id}");
                
                $analysis = $wpdb->get_row($wpdb->prepare($query, $analysis_id));
                
                if ($wpdb->last_error) {
                    error_log("❌ DEBUG CONVERSION: Errore database: " . $wpdb->last_error);
                    return null;
                }
                
                error_log("📊 DEBUG CONVERSION: Risultato query diretta: " . ($analysis ? 'TROVATO' : 'NULL'));
            }
            
            if (!$analysis) {
                error_log("❌ DEBUG CONVERSION: Nessuna analisi trovata per ID: {$analysis_id}");
                return null;
            }
            
            // Debug proprietà analisi
            error_log("📊 DEBUG CONVERSION: Proprietà analisi trovate:");
            $properties = ['analysis_success', 'referente_nome', 'referente_cognome', 'menu', 'data_evento', 
                          'tipo_evento', 'numero_invitati', 'telefono', 'email', 'importo_totale', 'acconto'];
            
            foreach ($properties as $prop) {
                $value = $analysis->$prop ?? 'MISSING';
                error_log("  - {$prop}: {$value}");
            }
            
            // Verifica successo analisi
            if (!$analysis->analysis_success) {
                error_log("❌ DEBUG CONVERSION: Analisi non riuscita (analysis_success = false)");
                return null;
            }
            error_log("✅ DEBUG CONVERSION: Analisi riuscita, procedo con conversione");
            
            // Converti dati analisi in formato preventivo
            $preventivo = new \stdClass();
            
            // Dati evento
            $preventivo->data_evento = $analysis->data_evento ?: '';
            $preventivo->tipo_evento = $analysis->tipo_evento ?: '';
            $preventivo->menu = $analysis->menu ?: '';
            $preventivo->numero_invitati = intval($analysis->numero_invitati ?: 0);
            $preventivo->orario_evento = $analysis->orari_raw ?: '';
            
            // Dati cliente
            $nome_completo = trim(($analysis->referente_nome ?: '') . ' ' . ($analysis->referente_cognome ?: ''));
            $preventivo->nome_cliente = $nome_completo;
            $preventivo->telefono = $analysis->telefono ?: '';
            $preventivo->email = $analysis->email ?: '';
            
            // Dati economici
            $preventivo->importo_totale = floatval($analysis->importo_totale ?: 0);
            $preventivo->acconto = floatval($analysis->acconto ?: 0);
            
            // Omaggi (se disponibili nel formato JSON)
            $preventivo->omaggio1 = '';
            $preventivo->omaggio2 = '';
            $preventivo->omaggio3 = '';
            
            // Extra (se disponibili)
            $preventivo->extra1 = '';
            $preventivo->extra2 = '';
            $preventivo->extra3 = '';
            
            // Stato
            $preventivo->stato = $analysis->acconto > 0 ? 'confermato' : 'bozza';
            $preventivo->confermato = $analysis->acconto > 0 ? 1 : 0;
            
            // Metadata
            $preventivo->id = 0; // Nuovo preventivo
            $preventivo->created_at = current_time('mysql');
            $preventivo->updated_at = current_time('mysql');
            $preventivo->created_by = get_current_user_id();
            
            // Aggiungi riferimento all'analisi Excel
            $preventivo->excel_analysis_id = $analysis_id;
            $preventivo->source = 'excel_analysis';
            
            // Debug oggetto preventivo finale
            error_log("🔍 DEBUG CONVERSION: Preventivo finale creato:");
            error_log("  - nome_cliente: " . $preventivo->nome_cliente);
            error_log("  - menu: " . $preventivo->menu);
            error_log("  - data_evento: " . $preventivo->data_evento);
            error_log("  - importo_totale: " . $preventivo->importo_totale);
            error_log("  - acconto: " . $preventivo->acconto);
            error_log("  - stato: " . $preventivo->stato);
            error_log("  - source: " . $preventivo->source);
            
            $this->log("Preventivo creato da analisi Excel ID {$analysis_id}", 'success');
            error_log("✅ DEBUG CONVERSION: Conversione completata con successo");
            return $preventivo;
            
        } catch (\Exception $e) {
            error_log("❌ DEBUG CONVERSION: ECCEZIONE durante conversione: " . $e->getMessage());
            error_log("❌ DEBUG CONVERSION: Stack trace: " . $e->getTraceAsString());
            $this->log('Errore conversione analisi Excel in preventivo: ' . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Renderizza dashboard preventivi
     */
    private function render_dashboard_preventivi() {
        // Parametri filtri
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $stato = isset($_GET['stato']) ? sanitize_key($_GET['stato']) : '';
        $anno = isset($_GET['anno']) ? intval($_GET['anno']) : '';
        $mese = isset($_GET['mese']) ? intval($_GET['mese']) : '';
        $menu = isset($_GET['menu']) ? sanitize_key($_GET['menu']) : '';
        
        // Parametri paginazione
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        // Ottieni preventivi con filtri
        $preventivi = $this->get_filtered_preventivi(array(
            'search' => $search,
            'stato' => $stato,
            'anno' => $anno,
            'mese' => $mese,
            'menu' => $menu,
            'paged' => $paged,
            'per_page' => $per_page
        ));
        
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/dashboard-preventivi.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>Template dashboard preventivi non trovato.</p></div>';
        }
    }

    /**
     * Renderizza pagina impostazioni
     */
    public function render_settings_page() {
        try {
            if (!current_user_can($this->min_capability)) {
                wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
            }
            
            $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                $this->render_fallback_settings();
            }
            
        } catch (\Exception $e) {
            $this->log('Errore render impostazioni: ' . $e->getMessage(), 'error');
            echo '<div class="error"><p>Errore caricamento impostazioni.</p></div>';
        }
    }

    /**
     * Renderizza pagina messaggi
     */
    public function render_messages_page() {
        try {
            if (!current_user_can($this->min_capability)) {
                wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
            }
            
            $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/messages-page.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                $this->render_fallback_messages();
            }
            
        } catch (\Exception $e) {
            $this->log('Errore render messaggi: ' . $e->getMessage(), 'error');
            echo '<div class="error"><p>Errore caricamento messaggi.</p></div>';
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Solo nelle pagine del plugin
        if (strpos($hook_suffix, 'disco747') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // CSS admin
        wp_enqueue_style(
            'disco747-admin',
            DISCO747_CRM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $this->asset_version
        );
        
        // JavaScript admin
        wp_enqueue_script(
            'disco747-admin',
            DISCO747_CRM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            $this->asset_version,
            true
        );
        
        // Localizzazione script
        wp_localize_script('disco747-admin', 'disco747_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('disco747_admin_nonce'),
            'strings' => array(
                'saving' => __('Salvataggio...', 'disco747'),
                'saved' => __('Salvato!', 'disco747'),
                'error' => __('Errore durante il salvataggio.', 'disco747')
            )
        ));
    }

    /**
     * Ottieni statistiche dashboard
     */
    private function get_dashboard_statistics() {
        if (!$this->database) {
            return array(
                'total_preventivi' => 0,
                'preventivi_attivi' => 0,
                'preventivi_confermati' => 0,
                'valore_totale' => 0
            );
        }
        
        $stats = array(
            'total_preventivi' => $this->database->count_preventivi(),
            'preventivi_attivi' => $this->database->count_preventivi(array('stato' => 'attivo')),
            'preventivi_confermati' => $this->database->count_preventivi(array('confermato' => 1)),
            'valore_totale' => $this->database->sum_preventivi_value()
        );
        
        return $stats;
    }

    /**
     * Ottieni status sistema
     */
    private function get_system_status_summary() {
        return array(
            'storage_type' => get_option('disco747_storage_type', 'googledrive'),
            'storage_connected' => $this->check_storage_connection(),
            'plugin_version' => DISCO747_CRM_VERSION ?? '11.4.6',
            'database' => $this->database !== null,
            'storage' => $this->storage_manager !== null,
            'pdf_generator_status' => $this->pdf_excel_handler !== null ? 'active' : 'inactive',
            'excel_generator_status' => $this->excel_handler !== null ? 'active' : 'inactive'
        );
    }

    /**
     * Controlla connessione storage
     */
    private function check_storage_connection() {
        try {
            if ($this->storage_manager) {
                // Tenta un'operazione di test semplice
                return method_exists($this->storage_manager, 'is_connected') ? 
                       $this->storage_manager->is_connected() : true;
            }
            return false;
        } catch (\Exception $e) {
            $this->log('Errore verifica storage: ' . $e->getMessage(), 'warning');
            return false;
        }
    }

    /**
     * Ottieni preventivi recenti
     */
    private function get_recent_preventivi($limit = 5) {
        try {
            if ($this->database) {
                return $this->database->get_recent_preventivi($limit);
            }
            return array();
        } catch (\Exception $e) {
            $this->log('Errore caricamento preventivi recenti: ' . $e->getMessage(), 'warning');
            return array();
        }
    }

    /**
     * Ottieni preventivi filtrati (per dashboard preventivi)
     */
    private function get_filtered_preventivi($filters) {
        try {
            if ($this->database && method_exists($this->database, 'get_filtered_preventivi')) {
                return $this->database->get_filtered_preventivi($filters);
            }
            return array();
        } catch (\Exception $e) {
            $this->log('Errore caricamento preventivi filtrati: ' . $e->getMessage(), 'warning');
            return array();
        }
    }

    /**
     * Aggiunge link azioni plugin
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . 
                        admin_url('admin.php?page=disco747-settings') . '">' . 
                        __('Impostazioni', 'disco747') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Dashboard di fallback - SEMPRE FUNZIONANTE CON NAVIGAZIONE
     */
    private function render_fallback_dashboard() {
        echo '<div class="wrap">';
        echo '<h1>🎉 747 Disco CRM - PreventiviParty</h1>';
        echo '<div class="notice notice-success"><p><strong>✅ Plugin attivo e funzionante!</strong> Il sistema sta caricando i componenti...</p></div>';
        
        // Stato sistema
        $system_status = $this->get_system_status_summary();
        
        echo '<div class="card" style="max-width: 800px; padding: 20px; margin: 20px 0;">';
        echo '<h2>🚀 Benvenuto in PreventiviParty!</h2>';
        echo '<p>Il plugin 747 Disco CRM è stato installato correttamente e sta funzionando.</p>';
        
        // Stato componenti
        echo '<h3>📋 Stato Componenti:</h3>';
        echo '<table class="widefat" style="margin: 10px 0;">';
        echo '<thead><tr><th>Componente</th><th>Stato</th></tr></thead>';
        echo '<tbody>';
        echo '<tr><td>Database Manager</td><td>' . ($system_status['database'] ? '✅ Attivo' : '❌ Non disponibile') . '</td></tr>';
        echo '<tr><td>Storage Manager</td><td>' . ($system_status['storage'] ? '✅ Attivo' : '❌ Non disponibile') . '</td></tr>';
        echo '<tr><td>PDF Generator</td><td>' . ($system_status['pdf_generator_status'] === 'active' ? '✅ Attivo' : '❌ Non disponibile') . '</td></tr>';
        echo '<tr><td>Excel Generator</td><td>' . ($system_status['excel_generator_status'] === 'active' ? '✅ Attivo' : '❌ Non disponibile') . '</td></tr>';
        echo '</tbody></table>';
        
        // Navigazione
        echo '<h3>🧭 Navigazione Plugin:</h3>';
        echo '<div style="display: flex; gap: 10px; margin: 15px 0; flex-wrap: wrap;">';
        
        // Pulsante Form Preventivo
        echo '<a href="' . admin_url('admin.php?page=disco747-crm&action=new_preventivo') . '" 
               class="button button-primary" style="padding: 10px 20px; font-size: 14px;">
               ➕ Nuovo Preventivo
              </a>';
        
        // Pulsante Dashboard Preventivi
        echo '<a href="' . admin_url('admin.php?page=disco747-crm&action=dashboard_preventivi') . '" 
               class="button button-secondary" style="padding: 10px 20px; font-size: 14px;">
               📊 Dashboard Preventivi
              </a>';
        
        // Pulsante Impostazioni
        echo '<a href="' . admin_url('admin.php?page=disco747-settings') . '" 
               class="button button-secondary" style="padding: 10px 20px; font-size: 14px;">
               ⚙️ Impostazioni
              </a>';

        // NUOVO: Pulsante Scansione Excel
        echo '<a href="' . admin_url('admin.php?page=disco747-scan-excel') . '" 
               class="button button-secondary" style="padding: 10px 20px; font-size: 14px;">
               📊 Scansione Excel Auto
              </a>';
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Fallback rendering per impostazioni
     */
    private function render_fallback_settings() {
        echo '<div class="wrap"><h1>Impostazioni 747 Disco CRM</h1>';
        echo '<p>Template impostazioni in caricamento...</p>';
        echo '</div>';
    }

    /**
     * Fallback rendering per messaggi
     */
    private function render_fallback_messages() {
        echo '<div class="wrap"><h1>Messaggi Automatici</h1>';
        echo '<p>Template messaggi in caricamento...</p>';
        echo '</div>';
    }

    // =========================================================================
    // AJAX HANDLERS E METODI DI SUPPORTO (mantenuti identici)
    // =========================================================================

    /**
     * Handle AJAX save preventivo
     */
    public function handle_save_preventivo() {
        // Verifica nonce e permessi
        if (!wp_verify_nonce($_POST['nonce'], 'disco747_admin_nonce')) {
            wp_send_json_error('Nonce non valido');
        }
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        try {
            // Sanitizza dati POST
            $data = $this->sanitize_preventivo_data($_POST);
            
            // Salva nel database
            if ($data['id'] > 0) {
                // Aggiorna esistente
                $result = $this->database->update_preventivo($data['id'], $data);
            } else {
                // Nuovo preventivo
                $result = $this->database->insert_preventivo($data);
                $data['id'] = $result;
            }
            
            if ($result) {
                // Genera file PDF e Excel
                $this->generate_preventivo_files($data['id'], $data);
                
                wp_send_json_success(array(
                    'message' => 'Preventivo salvato con successo',
                    'preventivo_id' => $data['id']
                ));
            } else {
                wp_send_json_error('Errore salvataggio preventivo');
            }
            
        } catch (\Exception $e) {
            $this->log('Errore AJAX save preventivo: ' . $e->getMessage(), 'error');
            wp_send_json_error('Errore interno: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX delete preventivo
     */
    public function handle_delete_preventivo() {
        if (!wp_verify_nonce($_POST['nonce'], 'disco747_admin_nonce')) {
            wp_send_json_error('Nonce non valido');
        }
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        try {
            $preventivo_id = intval($_POST['preventivo_id']);
            
            if ($preventivo_id <= 0) {
                wp_send_json_error('ID preventivo non valido');
            }
            
            // Ottieni dati preventivo prima della cancellazione
            $preventivo = $this->database->get_preventivo($preventivo_id);
            
            if (!$preventivo) {
                wp_send_json_error('Preventivo non trovato');
            }
            
            // Elimina file dallo storage
            $this->delete_files_from_storage($preventivo);
            
            // Elimina dal database
            $result = $this->database->delete_preventivo($preventivo_id);
            
            if ($result) {
                wp_send_json_success('Preventivo eliminato con successo');
            } else {
                wp_send_json_error('Errore eliminazione preventivo');
            }
            
        } catch (\Exception $e) {
            $this->log('Errore AJAX delete preventivo: ' . $e->getMessage(), 'error');
            wp_send_json_error('Errore interno: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX get preventivo
     */
    public function handle_get_preventivo() {
        if (!wp_verify_nonce($_POST['nonce'], 'disco747_admin_nonce')) {
            wp_send_json_error('Nonce non valido');
        }
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        try {
            $preventivo_id = intval($_POST['preventivo_id']);
            
            if ($preventivo_id <= 0) {
                wp_send_json_error('ID preventivo non valido');
            }
            
            $preventivo = $this->database->get_preventivo($preventivo_id);
            
            if ($preventivo) {
                wp_send_json_success($preventivo);
            } else {
                wp_send_json_error('Preventivo non trovato');
            }
            
        } catch (\Exception $e) {
            $this->log('Errore AJAX get preventivo: ' . $e->getMessage(), 'error');
            wp_send_json_error('Errore interno: ' . $e->getMessage());
        }
    }

    /**
     * Placeholder handlers per compatibility
     */
    public function handle_dropbox_auth() {
        wp_send_json_error('Dropbox auth non implementato in questa versione');
    }

    public function handle_googledrive_auth() {
        wp_send_json_error('Google Drive auth gestito dalle impostazioni');
    }

    public function handle_test_storage() {
        try {
            $connected = $this->check_storage_connection();
            wp_send_json_success(array(
                'connected' => $connected,
                'message' => $connected ? 'Storage connesso' : 'Storage non connesso'
            ));
        } catch (\Exception $e) {
            wp_send_json_error('Errore test storage: ' . $e->getMessage());
        }
    }

    /**
     * Sanitizza dati preventivo POST
     */
    private function sanitize_preventivo_data($post_data) {
        return array(
            'id' => intval($post_data['preventivo_id'] ?? 0),
            
            // Dati evento
            'data_evento' => sanitize_text_field($post_data['data_evento'] ?? ''),
            'tipo_evento' => sanitize_text_field($post_data['tipo_evento'] ?? ''),
            'menu' => sanitize_key($post_data['menu'] ?? ''),
            'numero_invitati' => intval($post_data['numero_invitati'] ?? 0),
            'orario_evento' => sanitize_text_field($post_data['orario_evento'] ?? ''),
            
            // Dati cliente
            'nome_cliente' => sanitize_text_field($post_data['nome_cliente'] ?? ''),
            'telefono' => sanitize_text_field($post_data['telefono'] ?? ''),
            'email' => sanitize_email($post_data['email'] ?? ''),
            
            // Dati economici
            'importo_totale' => floatval($post_data['importo_totale'] ?? 0),
            'acconto' => floatval($post_data['acconto'] ?? 0),
            
            // Extra
            'omaggio1' => sanitize_text_field($post_data['omaggio1'] ?? ''),
            'omaggio2' => sanitize_text_field($post_data['omaggio2'] ?? ''),
            'omaggio3' => sanitize_text_field($post_data['omaggio3'] ?? ''),
            'note_aggiuntive' => sanitize_textarea_field($post_data['note_aggiuntive'] ?? ''),
            
            // Stato
            'stato' => sanitize_key($post_data['stato'] ?? 'bozza'),
            'confermato' => isset($post_data['confermato']) ? 1 : 0
        );
    }

    /**
     * Genera file PDF e Excel per preventivo
     */
    private function generate_preventivo_files($preventivo_id, $data) {
        try {
            $pdf_result = false;
            $excel_result = false;
            
            // Genera PDF
            if ($this->pdf_excel_handler) {
                $pdf_result = $this->pdf_excel_handler->generate_pdf($data);
            }
            
            // Genera Excel
            if ($this->excel_handler) {
                $excel_result = $this->excel_handler->generate_excel($data);
            }
            
            if ($pdf_result || $excel_result) {
                // Upload su storage tramite storage_manager
                $this->upload_files_to_storage($preventivo_id, $pdf_result, $excel_result);
            }
            
        } catch (Exception $e) {
            $this->log('Errore generazione file: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Upload file su storage tramite storage_manager
     */
    private function upload_files_to_storage($preventivo_id, $pdf_path, $excel_path) {
        try {
            if (!$this->storage_manager) {
                $this->log('Storage manager non disponibile', 'warning');
                return;
            }
            
            $folder_name = "Preventivo_" . $preventivo_id . "_" . date('Y-m-d');
            $uploaded_urls = array();
            
            // Upload PDF
            if ($pdf_path) {
                $pdf_url = $this->storage_manager->upload_file($pdf_path, $folder_name);
                if ($pdf_url) {
                    $uploaded_urls['pdf_url'] = $pdf_url;
                }
            }
            
            // Upload Excel
            if ($excel_path) {
                $excel_url = $this->storage_manager->upload_file($excel_path, $folder_name);
                if ($excel_url) {
                    $uploaded_urls['excel_url'] = $excel_url;
                }
            }
            
            // Aggiorna database con URL
            if (!empty($uploaded_urls)) {
                // Aggiungi anche googledrive_url per compatibilità
                $update_data = $uploaded_urls;
                $update_data['googledrive_url'] = $uploaded_urls['pdf_url'] ?? $uploaded_urls['excel_url'] ?? '';
                
                $this->database->update_preventivo($preventivo_id, $update_data);
            }
            
        } catch (Exception $e) {
            $this->log('Errore upload storage: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Elimina file dallo storage tramite storage_manager
     */
    private function delete_files_from_storage($preventivo) {
        try {
            if (!$this->storage_manager) {
                $this->log('Storage manager non disponibile per eliminazione file', 'warning');
                return;
            }
            
            // Elimina PDF se esiste
            if (!empty($preventivo->pdf_url)) {
                $this->storage_manager->delete_file($preventivo->pdf_url);
            }
            
            // Elimina Excel se esiste
            if (!empty($preventivo->excel_url)) {
                $this->storage_manager->delete_file($preventivo->excel_url);
            }
            
            // Elimina anche googledrive_url generico se diverso
            if (!empty($preventivo->googledrive_url) && 
                $preventivo->googledrive_url !== $preventivo->pdf_url && 
                $preventivo->googledrive_url !== $preventivo->excel_url) {
                $this->storage_manager->delete_file($preventivo->googledrive_url);
            }
            
        } catch (Exception $e) {
            $this->log('Errore eliminazione file storage: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Aggiunge notice amministrazione
     */
    public function add_admin_notice($message, $type = 'info') {
        $this->admin_notices[] = array(
            'message' => $message,
            'type' => $type
        );
    }

    /**
     * Mostra notice amministrazione
     */
    public function show_admin_notices() {
        foreach ($this->admin_notices as $notice) {
            $class = 'notice notice-' . $notice['type'];
            if ($notice['type'] === 'success') {
                $class .= ' is-dismissible';
            }
            echo '<div class="' . $class . '"><p>' . esc_html($notice['message']) . '</p></div>';
        }
    }

    /**
     * Log helper - usa sistema logging globale
     */
    private function log($message, $level = 'info') {
        if ($this->debug_mode) {
            try {
                // Usa funzione globale di logging del plugin
                if (function_exists('disco747_log')) {
                    disco747_log($message, strtoupper($level));
                } else {
                    // Fallback: error_log diretto
                    $timestamp = date('Y-m-d H:i:s');
                    error_log("[{$timestamp}] [747Disco-CRM-Admin] [" . strtoupper($level) . "] {$message}");
                }
            } catch (\Exception $e) {
                // Fallback sicuro
                error_log('[747 Disco CRM Admin] ' . $message);
            }
        }
    }
}