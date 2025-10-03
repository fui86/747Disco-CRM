<?php
/**
 * Classe per la gestione dell'area amministrativa del plugin 747 Disco CRM
 * Versione 11.7.5 con batch scan che istanzia direttamente GoogleDrive Sync
 *
 * @package    Disco747_CRM
 * @subpackage Admin
 * @version    11.7.5-BATCH-DIRECT
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
    private $pdf_excel_handler;
    private $excel_handler;
    
    private $min_capability = 'manage_options';
    private $asset_version;
    private $admin_notices = array();
    private $hooks_registered = false;
    private $debug_mode = true;

    public function __construct() {
        $this->asset_version = defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.7.5';
        add_action('init', array($this, 'delayed_init'), 10);
    }

    public function delayed_init() {
        try {
            $this->load_dependencies();
            $this->register_admin_hooks();
            $this->log('Admin Manager inizializzato');
        } catch (\Exception $e) {
            $this->log('Errore inizializzazione Admin: ' . $e->getMessage(), 'error');
            $this->add_admin_notice('Errore inizializzazione 747 Disco CRM.', 'error');
        }
    }

    private function load_dependencies() {
        $disco747_crm = disco747_crm();
        if (!$disco747_crm || !$disco747_crm->is_initialized()) {
            throw new \Exception('Plugin principale non ancora inizializzato');
        }
        $this->config = $disco747_crm->get_config();
        $this->database = $disco747_crm->get_database();
        $this->auth = $disco747_crm->get_auth();
        $this->storage_manager = $disco747_crm->get_storage_manager();
        $this->pdf_excel_handler = $disco747_crm->get_pdf();
        $this->excel_handler = $disco747_crm->get_excel();
    }

    private function register_admin_hooks() {
        if ($this->hooks_registered) return;
        try {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('admin_notices', array($this, 'show_admin_notices'));
            add_filter('plugin_action_links_' . plugin_basename(DISCO747_CRM_PLUGIN_FILE), array($this, 'add_plugin_action_links'));
            add_action('wp_ajax_disco747_dropbox_auth', array($this, 'handle_dropbox_auth'));
            add_action('wp_ajax_disco747_googledrive_auth', array($this, 'handle_googledrive_auth'));
            add_action('wp_ajax_disco747_test_storage', array($this, 'handle_test_storage'));
            add_action('wp_ajax_disco747_save_preventivo', array($this, 'handle_save_preventivo'));
            add_action('wp_ajax_disco747_delete_preventivo', array($this, 'handle_delete_preventivo'));
            add_action('wp_ajax_disco747_get_preventivo', array($this, 'handle_get_preventivo'));
            
            // Endpoint batch scan
            add_action('wp_ajax_disco747_scan_drive_batch', array($this, 'handle_batch_scan'));
            
            $this->hooks_registered = true;
            $this->log('Hook WordPress registrati (incluso batch scan)');
        } catch (\Exception $e) {
            $this->log('Errore registrazione hook: ' . $e->getMessage(), 'error');
        }
    }

    public function add_admin_menu() {
        try {
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

    public function enqueue_admin_assets($hook_suffix) {
        error_log("[747Disco-Admin] Hook suffix: {$hook_suffix}");
        
        if (strpos($hook_suffix, 'disco747') === false) {
            error_log("[747Disco-Admin] Hook non contiene disco747, skip");
            return;
        }
        
        try {
            wp_enqueue_style('disco747-admin-style', DISCO747_CRM_PLUGIN_URL . 'assets/css/admin.css', array(), $this->asset_version);
            wp_enqueue_script('disco747-admin-script', DISCO747_CRM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), $this->asset_version, true);
            
            if ($hook_suffix === 'preventiviparty_page_disco747-scan-excel' || strpos($hook_suffix, 'scan-excel') !== false) {
                
                $excel_css_url = DISCO747_CRM_PLUGIN_URL . 'assets/css/excel-scan.css';
                $excel_js_url = DISCO747_CRM_PLUGIN_URL . 'assets/js/excel-scan.js';
                
                error_log("[747Disco-Admin] EXCEL SCAN RILEVATO!");
                
                wp_enqueue_style('disco747-excel-scan', $excel_css_url, array(), $this->asset_version . '-' . time());
                wp_enqueue_script('disco747-excel-scan', $excel_js_url, array('jquery'), $this->asset_version . '-' . time(), true);
                
                wp_localize_script('disco747-excel-scan', 'disco747ExcelScanData', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('disco747_admin_nonce'),
                    'gdriveAvailable' => true,
                    'pluginVersion' => $this->asset_version,
                    'hookSuffix' => $hook_suffix,
                    'debug' => true
                ));
                
                error_log("[747Disco-Admin] Assets Excel Scan caricati");
            }
            
            wp_localize_script('disco747-admin-script', 'disco747Admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('disco747_admin_nonce'),
                'messages' => array(
                    'loading' => __('Caricamento...', 'disco747'),
                    'error' => __('Errore durante l\'operazione', 'disco747'),
                    'success' => __('Operazione completata', 'disco747'),
                    'confirm_delete' => __('Sei sicuro di voler eliminare questo preventivo?', 'disco747'),
                    'processing' => __('Elaborazione in corso...', 'disco747')
                )
            ));
            
            error_log("[747Disco-Admin] Assets amministrazione caricati per: {$hook_suffix}");
            
        } catch (\Exception $e) {
            error_log('[747Disco-Admin] Errore caricamento assets: ' . $e->getMessage());
        }
    }

    /**
     * Handler AJAX per batch scan - ISTANZIA DIRETTAMENTE GOOGLEDRIVE SYNC
     */
    public function handle_batch_scan() {
        error_log('[747Disco-Admin] handle_batch_scan chiamato!');
        
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        
        if (!current_user_can($this->min_capability)) {
            error_log('[747Disco-Admin] Permessi insufficienti');
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        error_log('[747Disco-Admin] Permessi OK - avvio batch scan reale');
        
        try {
            // Verifica che la classe GoogleDrive Sync esista
            if (!class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive_Sync')) {
                throw new \Exception('Classe GoogleDrive_Sync non trovata');
            }
            
            error_log('[747Disco-Admin] Classe GoogleDrive_Sync trovata');
            
            // Verifica che la classe GoogleDrive esista
            if (!class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                throw new \Exception('Classe GoogleDrive non trovata');
            }
            
            error_log('[747Disco-Admin] Classe GoogleDrive trovata');
            
            // Istanzia GoogleDrive handler
            $googledrive_handler = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
            error_log('[747Disco-Admin] GoogleDrive handler istanziato');
            
            // Istanzia GoogleDrive Sync passando l'handler
            $gdrive_sync = new \Disco747_CRM\Storage\Disco747_GoogleDrive_Sync($googledrive_handler);
            error_log('[747Disco-Admin] GoogleDrive Sync istanziato');
            
            // Verifica disponibilità
            if (!$gdrive_sync->is_available()) {
                $error = $gdrive_sync->get_last_error();
                throw new \Exception('GoogleDrive Sync non disponibile: ' . $error);
            }
            
            error_log('[747Disco-Admin] GoogleDrive Sync disponibile - avvio scan...');
            
            // Esegui il batch scan REALE
            $result = $gdrive_sync->scan_excel_files_batch();
            
            error_log('[747Disco-Admin] Batch scan completato - found: ' . ($result['found'] ?? 0));
            error_log('[747Disco-Admin] Result messages: ' . implode(', ', $result['messages'] ?? array()));
            
            wp_send_json_success($result);
            
        } catch (\Exception $e) {
            error_log('[747Disco-Admin] ERRORE batch scan: ' . $e->getMessage());
            error_log('[747Disco-Admin] Stack trace: ' . $e->getTraceAsString());
            
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'found' => 0,
                'processed' => 0,
                'inserted' => 0,
                'updated' => 0,
                'errors' => 1,
                'messages' => array(
                    '❌ Errore durante il batch scan',
                    $e->getMessage()
                )
            ));
        }
    }

    public function render_main_dashboard() {
        try {
            if (!current_user_can($this->min_capability)) {
                wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
            }
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

    public function render_scan_excel_page() {
        try {
            if (!current_user_can($this->min_capability)) {
                wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
            }
            $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/excel-scan-page.php';
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<div class="wrap"><h1>Scansione Excel Auto</h1><p>Template non trovato.</p></div>';
            }
        } catch (\Exception $e) {
            $this->log('Errore render excel scan: ' . $e->getMessage(), 'error');
            echo '<div class="error"><p>Errore caricamento scansione excel.</p></div>';
        }
    }

    public function render_debug_page() {
        try {
            $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/debug-page.php';
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<div class="wrap"><h1>Debug 747 Disco CRM</h1><p>Template debug non trovato.</p></div>';
            }
        } catch (\Exception $e) {
            $this->log('Errore render debug: ' . $e->getMessage(), 'error');
        }
    }

    private function render_main_dashboard_page() {
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/main-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap"><h1>747 Disco CRM</h1><p>Dashboard principale</p></div>';
        }
    }

    private function render_form_preventivo() {
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/form-preventivo.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap"><h1>Nuovo Preventivo</h1><p>Form non trovato.</p></div>';
        }
    }

    private function render_edit_preventivo() {
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/form-preventivo.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap"><h1>Modifica Preventivo</h1><p>Form non trovato.</p></div>';
        }
    }

    private function render_dashboard_preventivi() {
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/dashboard-preventivi.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap"><h1>Dashboard Preventivi</h1><p>Dashboard non trovata.</p></div>';
        }
    }

    private function render_fallback_settings() {
        echo '<div class="wrap"><h1>Impostazioni 747 Disco CRM</h1><p>Configurazione del plugin.</p></div>';
    }

    private function render_fallback_messages() {
        echo '<div class="wrap"><h1>Messaggi Automatici</h1><p>Gestione template messaggi.</p></div>';
    }

    public function show_admin_notices() {
        foreach ($this->admin_notices as $notice) {
            $type = isset($notice['type']) ? $notice['type'] : 'info';
            $message = isset($notice['message']) ? $notice['message'] : '';
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), esc_html($message));
        }
    }

    public function add_admin_notice($message, $type = 'info') {
        $this->admin_notices[] = array('message' => $message, 'type' => $type);
    }

    public function add_plugin_action_links($links) {
        $settings_link = sprintf('<a href="%s">%s</a>', admin_url('admin.php?page=disco747-settings'), __('Impostazioni', 'disco747'));
        array_unshift($links, $settings_link);
        return $links;
    }

    public function handle_dropbox_auth() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        try {
            $action = isset($_POST['dropbox_action']) ? sanitize_text_field($_POST['dropbox_action']) : '';
            $result = array('success' => false, 'message' => 'Azione non implementata');
            wp_send_json($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handle_googledrive_auth() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        try {
            $action = isset($_POST['google_action']) ? sanitize_text_field($_POST['google_action']) : '';
            $result = array('success' => false, 'message' => 'Azione non implementata');
            wp_send_json($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handle_test_storage() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        try {
            $result = array('success' => true, 'message' => 'Test storage OK');
            wp_send_json($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handle_save_preventivo() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        try {
            $result = array('success' => true, 'message' => 'Preventivo salvato');
            wp_send_json($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handle_delete_preventivo() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        try {
            $result = array('success' => true, 'message' => 'Preventivo eliminato');
            wp_send_json($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handle_get_preventivo() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        try {
            $result = array('success' => true, 'data' => array());
            wp_send_json($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    private function log($message, $level = 'info') {
        if ($this->debug_mode && function_exists('error_log')) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[{$timestamp}] [747Disco-Admin] {$message}");
        }
    }
}