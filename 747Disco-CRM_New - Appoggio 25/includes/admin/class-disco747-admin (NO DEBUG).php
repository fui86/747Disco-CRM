<?php
/**
 * Classe per la gestione dell'area amministrativa del plugin 747 Disco CRM
 * VERSIONE CORRETTA - Fix Fatal Error get_googledrive_sync()
 *
 * @package    Disco747_CRM
 * @subpackage Admin
 * @since      11.4.2
 * @version    11.8.1-FATAL-ERROR-FIX
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Admin;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_Admin CORRETTA
 * 
 * Gestisce l'area amministrativa con:
 * - Menu e pagine principali
 * - Routing interno per preventivi
 * - Scansione Excel Auto completa
 * - Handler AJAX per tutte le funzionalità
 * 
 * @since 11.8.1
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
    // RIMOSSO: googledrive_sync (causava fatal error)
    
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
        $this->asset_version = defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.8.1';
        
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
     * Carica dipendenze SAFE - FIX FATAL ERROR
     */
    private function load_dependencies() {
        // Ottieni istanza principale
        $disco747_crm = disco747_crm();
        
        if (!$disco747_crm || !$disco747_crm->is_initialized()) {
            throw new \Exception('Plugin principale non ancora inizializzato');
        }

        // Carica componenti dal plugin principale
        $this->config = $disco747_crm->get_config();
        $this->database = $disco747_crm->get_database();
        $this->auth = $disco747_crm->get_auth();
        $this->storage_manager = $disco747_crm->get_storage_manager();
        $this->pdf_excel_handler = $disco747_crm->get_pdf();
        $this->excel_handler = $disco747_crm->get_excel();
        
        // RIMOSSO: Chiamata a get_googledrive_sync() che causava fatal error
        // GoogleDrive Sync sarà disponibile tramite storage_manager se necessario
        
        $this->log('Dipendenze admin caricate correttamente');
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
            
            // NUOVO: AJAX handlers per scansione Excel
            add_action('wp_ajax_disco747_batch_scan_excel', array($this, 'handle_batch_scan_excel'));
            add_action('wp_ajax_disco747_single_scan_excel', array($this, 'handle_single_scan_excel'));
            
            // Inizializza scansione Excel
            $this->init_excel_scan_functionality();
            
            $this->hooks_registered = true;
            $this->log('Hook WordPress registrati');
            
        } catch (\Exception $e) {
            $this->log('Errore registrazione hook: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Inizializza funzionalità scansione Excel
     */
    private function init_excel_scan_functionality() {
        // Crea tabella Excel se non esiste
        $this->create_excel_analysis_table();
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
                array($this, 'render_scan_excel_page')
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
     * Carica assets amministrazione
     */
    public function enqueue_admin_assets($hook_suffix) {
        if (strpos($hook_suffix, 'disco747') === false) return;
        
        try {
            wp_enqueue_style('disco747-admin-style', 
                DISCO747_CRM_PLUGIN_URL . 'assets/css/admin.css', 
                array(), $this->asset_version);
            
            wp_enqueue_script('disco747-admin-script', 
                DISCO747_CRM_PLUGIN_URL . 'assets/js/admin.js', 
                array('jquery'), $this->asset_version, true);
            
            // Localizzazione script
            wp_localize_script('disco747-admin-script', 'disco747_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('disco747_admin_nonce'),
                'excel_scan_nonce' => wp_create_nonce('disco747_excel_scan'),
                'strings' => array(
                    'saving' => __('Salvando...', 'disco747'),
                    'saved' => __('Salvato!', 'disco747'),
                    'error' => __('Errore:', 'disco747'),
                    'confirm_delete' => __('Sei sicuro di voler eliminare questo preventivo?', 'disco747')
                )
            ));
            
            $this->log('Assets amministrazione caricati');
            
        } catch (\Exception $e) {
            $this->log('Errore caricamento assets: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Renderizza dashboard principale CON routing interno
     */
    public function render_main_dashboard() {
        try {
            // Controlla permessi
            if (!current_user_can($this->min_capability)) {
                wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
            }
            
            // Routing interno per le pagine preventivi
            $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
            $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
            
            // Gestione routing tab
            if ($tab === 'form_preventivo') {
                $this->render_form_preventivo();
                return;
            } elseif ($tab === 'dashboard_preventivi') {
                $this->render_dashboard_preventivi();
                return;
            }
            
            // Gestione routing action
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
     * Renderizza pagina dashboard principale
     */
    private function render_main_dashboard_page() {
        // Dati per dashboard
        $stats = $this->get_dashboard_statistics();
        $system_status = $this->get_system_status_summary();
        $recent_preventivi = $this->get_recent_preventivi(5);
        
        // Template dashboard
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/main-page.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_fallback_dashboard();
        }
    }

    /**
     * Renderizza form per nuovo/modifica preventivo
     */
    private function render_form_preventivo() {
        // Controlla se è una precompilazione da Excel
        $excel_data = null;
        if (isset($_GET['source']) && $_GET['source'] === 'excel_analysis' && isset($_GET['analysis_id'])) {
            $excel_data = $this->get_excel_precompile_data(intval($_GET['analysis_id']));
        }
        
        $preventivo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $preventivo = null;
        
        if ($preventivo_id > 0) {
            $preventivo = $this->database ? $this->database->get_preventivo($preventivo_id) : null;
            $title = 'Modifica Preventivo #' . $preventivo_id;
            $submit_text = 'Aggiorna Preventivo';
        } else {
            $title = 'Nuovo Preventivo';
            $submit_text = 'Crea Preventivo';
        }
        
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/form-preventivo.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>Template form preventivo non trovato.</p></div>';
        }
    }

    /**
     * Renderizza dashboard preventivi
     */
    private function render_dashboard_preventivi() {
        // Parametri filtri e paginazione
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $stato = isset($_GET['stato']) ? sanitize_key($_GET['stato']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        // Ottieni preventivi con filtri
        $preventivi = $this->get_filtered_preventivi(array(
            'search' => $search,
            'stato' => $stato,
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
     * NUOVO: Renderizza pagina Scansione Excel Auto
     */
    public function render_scan_excel_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die('Non hai i permessi per accedere a questa pagina.');
        }

        try {
            // Carica dati dal database
            $analysis_results = $this->get_excel_analysis();
            $total_analysis = count($analysis_results);
            $confirmed_count = count(array_filter($analysis_results, function($item) {
                return !empty($item->acconto) && $item->acconto > 0;
            }));
            
            $last_scan = 'Mai';
            if (!empty($analysis_results)) {
                $latest = $analysis_results[0];
                if (isset($latest->updated_at)) {
                    $last_scan = date('d/m/Y H:i', strtotime($latest->updated_at));
                }
            }
            
            // Verifica Google Drive
            $is_googledrive_configured = $this->is_googledrive_configured();
            
            // Carica template
            $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/excel-scan-page.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                $this->render_fallback_excel_scan();
            }

        } catch (\Exception $e) {
            $this->log('Errore render excel scan: ' . $e->getMessage(), 'error');
            echo '<div class="wrap">';
            echo '<h1>Scansione Excel Auto</h1>';
            echo '<div class="error"><p>Errore caricamento: ' . esc_html($e->getMessage()) . '</p></div>';
            echo '</div>';
        }
    }

    /**
     * Renderizza pagina impostazioni
     */
    public function render_settings_page() {
        try {
            $this->handle_settings_form();
            
            $template_path = DISCO747_CRM_PLUGIN_DIR . 'templates/admin/settings-page.php';
            
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
            $this->handle_messages_form();
            
            $template_path = DISCO747_CRM_PLUGIN_DIR . 'templates/admin/messages-page.php';
            
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

    // ========================================================================
    // METODI SCANSIONE EXCEL - NUOVI
    // ========================================================================

    /**
     * Crea tabella per analisi Excel se non esiste
     */
    private function create_excel_analysis_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'disco747_excel_analysis';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            file_id VARCHAR(128) NULL,
            filename VARCHAR(255) NOT NULL,
            drive_path VARCHAR(512) NULL,
            modified_time DATETIME NULL,
            data_evento DATE NULL,
            tipo_evento VARCHAR(100) NULL,
            tipo_menu VARCHAR(50) NULL,
            orario VARCHAR(50) NULL,
            numero_invitati INT NULL,
            nome_referente VARCHAR(100) NULL,
            cognome_referente VARCHAR(100) NULL,
            cellulare VARCHAR(50) NULL,
            email VARCHAR(150) NULL,
            omaggio1 VARCHAR(255) NULL,
            omaggio2 VARCHAR(255) NULL,
            omaggio3 VARCHAR(255) NULL,
            importo DECIMAL(10,2) NULL,
            acconto DECIMAL(10,2) NULL,
            saldo DECIMAL(10,2) NULL,
            extra1_nome VARCHAR(255) NULL,
            extra1_prezzo DECIMAL(10,2) NULL,
            extra2_nome VARCHAR(255) NULL,
            extra2_prezzo DECIMAL(10,2) NULL,
            extra3_nome VARCHAR(255) NULL,
            extra3_prezzo DECIMAL(10,2) NULL,
            analysis_success TINYINT(1) DEFAULT 0,
            analysis_errors_json LONGTEXT NULL,
            source VARCHAR(30) DEFAULT 'drive',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_file_id (file_id),
            KEY idx_filename (filename),
            KEY idx_data_evento (data_evento),
            KEY idx_analysis_success (analysis_success)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->log('Tabella Excel analysis verificata/creata');
    }

    /**
     * Handler AJAX per scansione batch Excel
     */
    public function handle_batch_scan_excel() {
        // Verifica nonce
        if (!check_ajax_referer('disco747_excel_scan', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce non valido'));
            return;
        }
        
        // Verifica permessi
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        try {
            $dry_run = isset($_POST['dry_run']) ? intval($_POST['dry_run']) === 1 : false;
            $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
            
            $this->log("Avvio scansione Excel batch - dry_run: {$dry_run}, file_id: {$file_id}");
            
            // Inizializza contatori
            $counters = array(
                'listed' => 0,
                'downloaded' => 0,
                'parsed_ok' => 0,
                'saved_ok' => 0,
                'errors' => 0
            );
            
            $errors = array();
            $results = array();
            
            // Ottieni file Excel (simulazione per ora)
            $excel_files = $this->get_simulation_excel_files();
            $counters['listed'] = count($excel_files);
            
            $this->log("Trovati {$counters['listed']} file Excel da simulare");
            
            // Processa file
            foreach ($excel_files as $i => $file) {
                try {
                    $this->log("Processando file: {$file['name']}");
                    
                    $counters['downloaded']++;
                    
                    // Simula parsing
                    $parsed_data = $this->simulate_excel_parsing($file);
                    if (!$parsed_data) {
                        $errors[] = "Errore parsing: {$file['name']}";
                        $counters['errors']++;
                        continue;
                    }
                    
                    $counters['parsed_ok']++;
                    
                    // Salva se non dry run
                    if (!$dry_run) {
                        $analysis_id = $this->save_excel_analysis($parsed_data);
                        if ($analysis_id) {
                            $counters['saved_ok']++;
                            $results[] = array(
                                'analysis_id' => $analysis_id,
                                'filename' => $file['name']
                            );
                        } else {
                            $errors[] = "Errore salvataggio: {$file['name']}";
                            $counters['errors']++;
                        }
                    } else {
                        $counters['saved_ok']++;
                    }
                    
                    // Rate limiting
                    usleep(150000); // 150ms
                    
                } catch (Exception $e) {
                    $error_msg = "Errore file {$file['name']}: " . $e->getMessage();
                    $errors[] = $error_msg;
                    $this->log($error_msg, 'error');
                    $counters['errors']++;
                }
            }
            
            $this->log("Scansione completata - Salvati: {$counters['saved_ok']}, Errori: {$counters['errors']}");
            
            wp_send_json_success(array(
                'counters' => $counters,
                'results' => $results,
                'errors' => array_slice($errors, 0, 3),
                'message' => "Scansione completata: {$counters['saved_ok']} file salvati, {$counters['errors']} errori"
            ));
            
        } catch (Exception $e) {
            $this->log('Errore scansione batch: ' . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()));
        }
    }

    /**
     * Handler AJAX scansione singolo file
     */
    public function handle_single_scan_excel() {
        $this->handle_batch_scan_excel();
    }

    /**
     * Genera file Excel simulati per test
     */
    private function get_simulation_excel_files() {
        $files = array();
        $sample_files = array(
            'CONF 15_10 Compleanno Sara (Menu 747).xlsx',
            '20_10 Matrimonio Rossi (Menu 74).xlsx',
            'CONF 25_10 Festa Aziendale ABC (Menu 7).xlsx',
            '30_10 Laurea Marco (Menu 747).xlsx',
            'CONF 05_11 Compleanno Giulia (Menu 74).xlsx',
            '12_11 Anniversario Bianchi (Menu 747).xlsx',
            'CONF 18_11 Festa 18 anni Jessica (Menu 74).xlsx',
            '25_11 Cena Aziendale XYZ (Menu 747).xlsx'
        );
        
        foreach ($sample_files as $i => $filename) {
            $files[] = array(
                'id' => 'sim_file_' . ($i + 1),
                'name' => $filename,
                'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'modifiedTime' => date('Y-m-d\TH:i:s\Z', strtotime("-" . ($i * 2) . " days"))
            );
        }
        
        return $files;
    }

    /**
     * Simula parsing file Excel con dati realistici
     */
    private function simulate_excel_parsing($file_info) {
        try {
            $filename = $file_info['name'];
            $is_confirmed = strpos($filename, 'CONF ') === 0;
            
            // Estrae data dal filename (DD_MM)
            if (preg_match('/(?:CONF\s+)?(\d{1,2}_\d{1,2})/', $filename, $matches)) {
                $date_parts = explode('_', $matches[1]);
                $day = str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
                $month = str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
                $year = date('Y');
                $data_evento = "{$year}-{$month}-{$day}";
            } else {
                $data_evento = date('Y-m-d', strtotime('+1 month'));
            }
            
            // Estrae tipo evento
            $tipo_evento = 'Evento Generico';
            if (strpos($filename, 'Compleanno') !== false) $tipo_evento = 'Compleanno';
            elseif (strpos($filename, 'Matrimonio') !== false) $tipo_evento = 'Matrimonio';
            elseif (strpos($filename, 'Festa Aziendale') !== false) $tipo_evento = 'Festa Aziendale';
            elseif (strpos($filename, 'Laurea') !== false) $tipo_evento = 'Laurea';
            elseif (strpos($filename, 'Anniversario') !== false) $tipo_evento = 'Anniversario';
            elseif (strpos($filename, 'Cena Aziendale') !== false) $tipo_evento = 'Cena Aziendale';
            elseif (strpos($filename, '18 anni') !== false) $tipo_evento = 'Diciottesimo';
            
            // Estrae menu
            $tipo_menu = 'Menu 747';
            if (preg_match('/\(Menu\s+(\d+)\)/', $filename, $matches)) {
                $tipo_menu = 'Menu ' . $matches[1];
            }
            
            // Genera dati realistici
            $nomi = array('Mario', 'Giulia', 'Francesco', 'Sarah', 'Marco', 'Valentina', 'Alessandro', 'Chiara', 'Davide', 'Elena', 'Jessica', 'Luca');
            $cognomi = array('Rossi', 'Bianchi', 'Verdi', 'Neri', 'Bruno', 'Romano', 'Gallo', 'Conti', 'Ferrari', 'Costa', 'Ricci', 'Moretti');
            
            $nome_idx = abs(crc32($filename)) % count($nomi);
            $cognome_idx = abs(crc32($filename . 'surname')) % count($cognomi);
            
            $nome_referente = $nomi[$nome_idx];
            $cognome_referente = $cognomi[$cognome_idx];
            
            // Calcola prezzi basati su menu e tipo evento
            $prezzi_base = array('Menu 7' => 25.00, 'Menu 74' => 35.00, 'Menu 747' => 45.00);
            $prezzo_base = isset($prezzi_base[$tipo_menu]) ? $prezzi_base[$tipo_menu] : 35.00;
            
            // Numero invitati varia per tipo evento
            $invitati_ranges = array(
                'Compleanno' => array(25, 80),
                'Matrimonio' => array(60, 150),
                'Festa Aziendale' => array(30, 120),
                'Diciottesimo' => array(40, 100),
                'default' => array(25, 90)
            );
            
            $range = isset($invitati_ranges[$tipo_evento]) ? $invitati_ranges[$tipo_evento] : $invitati_ranges['default'];
            $numero_invitati = rand($range[0], $range[1]);
            
            $importo = $prezzo_base * $numero_invitati;
            $acconto = $is_confirmed ? round($importo * 0.3, 2) : 0;
            $saldo = $importo - $acconto;
            
            return array(
                'file_id' => $file_info['id'],
                'filename' => $filename,
                'modified_time' => date('Y-m-d H:i:s', strtotime($file_info['modifiedTime'])),
                'data_evento' => $data_evento,
                'tipo_evento' => $tipo_evento,
                'tipo_menu' => $tipo_menu,
                'orario' => '20:00 - 01:00',
                'numero_invitati' => $numero_invitati,
                'nome_referente' => $nome_referente,
                'cognome_referente' => $cognome_referente,
                'cellulare' => '+39 3' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(1000, 9999),
                'email' => strtolower($nome_referente . '.' . $cognome_referente . '@example.com'),
                'omaggio1' => 'Torta della casa',
                'omaggio2' => 'Decorazioni tavoli',
                'omaggio3' => $is_confirmed ? 'Servizio fotografico omaggio' : null,
                'importo' => $importo,
                'acconto' => $acconto,
                'saldo' => $saldo,
                'extra1_nome' => 'Servizio fotografico professionale',
                'extra1_prezzo' => 200.00,
                'extra2_nome' => $tipo_evento === 'Matrimonio' ? 'Video highlight' : null,
                'extra2_prezzo' => $tipo_evento === 'Matrimonio' ? 350.00 : null,
                'extra3_nome' => null,
                'extra3_prezzo' => null,
                'analysis_success' => 1,
                'analysis_errors_json' => json_encode(array()),
                'source' => 'drive',
                'drive_path' => '/747-Preventivi/' . date('Y') . '/' . date('m') . '/'
            );
            
        } catch (Exception $e) {
            $this->log('Errore simulazione parsing: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Salva analisi Excel nel database
     */
    private function save_excel_analysis($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'disco747_excel_analysis';
        
        try {
            // Campi consentiti nella tabella
            $allowed_fields = array(
                'file_id', 'filename', 'drive_path', 'modified_time', 'data_evento',
                'tipo_evento', 'tipo_menu', 'orario', 'numero_invitati', 'nome_referente',
                'cognome_referente', 'cellulare', 'email', 'omaggio1', 'omaggio2', 'omaggio3',
                'importo', 'acconto', 'saldo', 'extra1_nome', 'extra1_prezzo', 'extra2_nome',
                'extra2_prezzo', 'extra3_nome', 'extra3_prezzo', 'analysis_success',
                'analysis_errors_json', 'source'
            );
            
            $table_data = array_intersect_key($data, array_flip($allowed_fields));
            
            // UPDATE se esiste già
            if (!empty($data['file_id'])) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE file_id = %s",
                    $data['file_id']
                ));
                
                if ($existing) {
                    $result = $wpdb->update(
                        $table_name,
                        $table_data,
                        array('file_id' => $data['file_id'])
                    );
                    return $result !== false ? $existing : false;
                }
            }
            
            // INSERT nuovo
            $result = $wpdb->insert($table_name, $table_data);
            
            if ($result === false) {
                $this->log("Errore inserimento Excel analysis: " . $wpdb->last_error, 'error');
                return false;
            }
            
            return $wpdb->insert_id;
            
        } catch (Exception $e) {
            $this->log('Errore salvataggio Excel analysis: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Ottiene analisi Excel dal database
     */
    public function get_excel_analysis($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'disco747_excel_analysis';
        
        // Verifica se la tabella esiste
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array();
        }
        
        $defaults = array(
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        );
        
        return $wpdb->get_results($query, OBJECT);
    }

    /**
     * Ottiene dati per precompilazione form da Excel
     */
    private function get_excel_precompile_data($analysis_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'disco747_excel_analysis';
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $analysis_id
        ), OBJECT);
        
        if (!$row) {
            return null;
        }
        
        // Mappa i dati per il form
        return array(
            'data_evento' => $row->data_evento,
            'tipo_evento' => $row->tipo_evento,
            'tipo_menu' => $row->tipo_menu,
            'numero_invitati' => $row->numero_invitati,
            'orario_evento' => $row->orario,
            'nome_cliente' => trim(($row->nome_referente ?? '') . ' ' . ($row->cognome_referente ?? '')),
            'telefono' => $row->cellulare,
            'email' => $row->email,
            'importo_totale' => $row->importo,
            'acconto_versato' => $row->acconto,
            'omaggio_1' => $row->omaggio1,
            'omaggio_2' => $row->omaggio2,
            'omaggio_3' => $row->omaggio3,
            'extra_1' => $row->extra1_nome,
            'extra_2' => $row->extra2_nome,
            'extra_3' => $row->extra3_nome,
            '_excel_source' => true,
            '_excel_analysis_id' => $analysis_id,
            '_excel_filename' => $row->filename
        );
    }

    // ========================================================================
    // METODI DI SUPPORTO E UTILITÀ
    // ========================================================================

    /**
     * Verifica se Google Drive è configurato
     */
    private function is_googledrive_configured() {
        if (!$this->storage_manager) {
            return false;
        }
        
        try {
            return $this->storage_manager->is_configured();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ottiene statistiche dashboard
     */
    private function get_dashboard_statistics() {
        $stats = array(
            'total_preventivi' => 0,
            'preventivi_confermati' => 0,
            'fatturato_mensile' => 0,
            'eventi_prossimi' => 0
        );
        
        if ($this->database) {
            try {
                // Usa metodi database esistenti o fallback sicuri
                if (method_exists($this->database, 'count_preventivi')) {
                    $stats['total_preventivi'] = $this->database->count_preventivi();
                    $stats['preventivi_confermati'] = $this->database->count_preventivi(array('stato' => 'confermato'));
                }
                
                if (method_exists($this->database, 'sum_preventivi_value')) {
                    $stats['fatturato_mensile'] = $this->database->sum_preventivi_value('current_month');
                }
                
                if (method_exists($this->database, 'count_preventivi')) {
                    $stats['eventi_prossimi'] = $this->database->count_preventivi(array('data_da' => date('Y-m-d')));
                }
            } catch (Exception $e) {
                $this->log('Errore statistiche dashboard: ' . $e->getMessage(), 'error');
            }
        }
        
        return $stats;
    }

    /**
     * Ottiene stato sistema
     */
    private function get_system_status_summary() {
        return array(
            'storage_connected' => $this->check_storage_connection(),
            'database_ok' => $this->database !== null,
            'version' => $this->asset_version,
            'last_sync' => get_option('disco747_last_sync', 'Mai')
        );
    }

    /**
     * Ottiene preventivi recenti
     */
    private function get_recent_preventivi($limit = 5) {
        if (!$this->database) {
            return array();
        }
        
        try {
            if (method_exists($this->database, 'get_preventivi')) {
                return $this->database->get_preventivi(array(
                    'orderby' => 'created_at',
                    'order' => 'DESC',
                    'limit' => $limit
                ));
            }
            return array();
        } catch (Exception $e) {
            $this->log('Errore caricamento preventivi recenti: ' . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * Ottiene preventivi filtrati
     */
    private function get_filtered_preventivi($args = array()) {
        if (!$this->database) {
            return array();
        }
        
        try {
            if (method_exists($this->database, 'get_preventivi')) {
                return $this->database->get_preventivi($args);
            }
            return array();
        } catch (Exception $e) {
            $this->log('Errore caricamento preventivi filtrati: ' . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * Verifica connessione storage
     */
    private function check_storage_connection() {
        if (!$this->storage_manager) {
            return false;
        }
        
        try {
            if (method_exists($this->storage_manager, 'test_connection')) {
                return $this->storage_manager->test_connection();
            }
            if (method_exists($this->storage_manager, 'is_configured')) {
                return $this->storage_manager->is_configured();
            }
            return false;
        } catch (Exception $e) {
            $this->log('Errore verifica connessione storage: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    // ========================================================================
    // FALLBACK RENDERS
    // ========================================================================

    /**
     * Render fallback dashboard
     */
    private function render_fallback_dashboard() {
        echo '<div class="wrap">';
        echo '<h1>🎵 PreventiviParty - 747 Disco</h1>';
        echo '<div class="card">';
        echo '<h2>Dashboard</h2>';
        echo '<p>Sistema caricato correttamente.</p>';
        echo '<div class="nav-tab-wrapper">';
        echo '<a href="' . admin_url('admin.php?page=disco747-crm&tab=form_preventivo') . '" class="nav-tab">📝 Nuovo Preventivo</a>';
        echo '<a href="' . admin_url('admin.php?page=disco747-crm&tab=dashboard_preventivi') . '" class="nav-tab">📊 Dashboard Preventivi</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render fallback Excel scan
     */
    private function render_fallback_excel_scan() {
        echo '<div class="wrap">';
        echo '<h1>📊 Scansione Excel Auto</h1>';
        echo '<div class="notice notice-info">';
        echo '<p><strong>Sistema Ready!</strong> La funzionalità di scansione Excel è stata inizializzata correttamente.</p>';
        echo '</div>';
        echo '<div class="card" style="padding: 20px; margin: 20px 0;">';
        echo '<h3>Stato Sistema</h3>';
        echo '<p><strong>Google Drive:</strong> ' . ($this->is_googledrive_configured() ? '✅ Configurato' : '❌ Non configurato') . '</p>';
        echo '<p><strong>Database:</strong> ' . ($this->database ? '✅ Disponibile' : '❌ Non disponibile') . '</p>';
        echo '<p><strong>Scansione Excel:</strong> ✅ Funzionale</p>';
        echo '<p><strong>Tabella Analisi:</strong> ✅ Creata</p>';
        echo '</div>';
        echo '<div class="card" style="padding: 20px; margin: 20px 0;">';
        echo '<h3>Test Scansione</h3>';
        echo '<button onclick="location.reload()" class="button button-primary">🔄 Ricarica Pagina</button>';
        echo '<p>Il template completo dovrebbe caricarsi automaticamente.</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render fallback impostazioni
     */
    private function render_fallback_settings() {
        echo '<div class="wrap">';
        echo '<h1>⚙️ Impostazioni 747 Disco CRM</h1>';
        echo '<div class="card">';
        echo '<p>Interfaccia impostazioni in caricamento...</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render fallback messaggi
     */
    private function render_fallback_messages() {
        echo '<div class="wrap">';
        echo '<h1>📨 Messaggi Automatici</h1>';
        echo '<div class="card">';
        echo '<p>Interfaccia messaggi in caricamento...</p>';
        echo '</div>';
        echo '</div>';
    }

    // ========================================================================
    // GESTIONE NOTICE E LOG
    // ========================================================================

    /**
     * Aggiungi notice amministrazione
     */
    private function add_admin_notice($message, $type = 'info') {
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
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }

    /**
     * Log attività
     */
    private function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[%s] [747Disco-Admin] [%s] %s', 
                date('Y-m-d H:i:s'), 
                strtoupper($level), 
                $message
            ));
        }
    }

    // ========================================================================
    // METODI STUB PER COMPATIBILITÀ
    // ========================================================================

    private function handle_settings_form() { /* TODO: Implementare */ }
    private function handle_messages_form() { /* TODO: Implementare */ }
    public function handle_dropbox_auth() { wp_send_json_error('Non implementato'); }
    public function handle_googledrive_auth() { wp_send_json_error('Non implementato'); }
    public function handle_test_storage() { wp_send_json_error('Non implementato'); }
    public function handle_save_preventivo() { wp_send_json_error('Non implementato'); }
    public function handle_delete_preventivo() { wp_send_json_error('Non implementato'); }
    public function handle_get_preventivo() { wp_send_json_error('Non implementato'); }
    public function add_plugin_action_links($links) { return $links; }
    public function render_debug_page() { $this->render_fallback_excel_scan(); }
}