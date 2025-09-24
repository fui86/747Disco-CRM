<?php
/**
 * Classe per la gestione dell'area amministrativa del plugin 747 Disco CRM
 * FILE COMPLETO - Versione 11.7.2 con bugfix data_evento + Excel Scan
 *
 * @package    Disco747_CRM
 * @subpackage Admin
 * @version    11.7.2-BUGFIX-COMPLETE
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
        $this->asset_version = defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.7.2';
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
            $this->hooks_registered = true;
            $this->log('Hook WordPress registrati');
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
            // ✅ RIPRISTINATO: Sottomenu Scansione Excel Auto
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
        if (strpos($hook_suffix, 'disco747') === false) return;
        try {
            wp_enqueue_style('disco747-admin-style', DISCO747_CRM_PLUGIN_URL . 'assets/css/admin.css', array(), $this->asset_version);
            wp_enqueue_script('disco747-admin-script', DISCO747_CRM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), $this->asset_version, true);
            
            // ✅ AGGIUNTO: CSS/JS per Excel Scan
            if ($hook_suffix === 'preventiviparty_page_disco747-scan-excel') {
                wp_enqueue_style('disco747-excel-scan', DISCO747_CRM_PLUGIN_URL . 'assets/css/excel-scan.css', array(), $this->asset_version);
                wp_enqueue_script('disco747-excel-scan', DISCO747_CRM_PLUGIN_URL . 'assets/js/excel-scan.js', array('jquery'), $this->asset_version, true);
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
            $this->log('Assets amministrazione caricati');
        } catch (\Exception $e) {
            $this->log('Errore caricamento assets: ' . $e->getMessage(), 'error');
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

    private function render_main_dashboard_page() {
        $stats = $this->get_dashboard_statistics();
        $system_status = $this->get_system_status_summary();
        $recent_preventivi = $this->get_recent_preventivi(5);
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/main-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_fallback_dashboard();
        }
    }

    private function render_form_preventivo() {
        $preventivo = null;
        $title = 'Nuovo Preventivo';
        $submit_text = 'Crea Preventivo';
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/form-preventivo.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>Template form preventivo non trovato.</p></div>';
        }
    }

    private function render_edit_preventivo() {
        $preventivo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($preventivo_id <= 0) {
            echo '<div class="error"><p>ID preventivo non valido.</p></div>';
            return;
        }
        $preventivo = $this->database->get_preventivo($preventivo_id);
        if (!$preventivo) {
            echo '<div class="error"><p>Preventivo non trovato.</p></div>';
            return;
        }
        $title = 'Modifica Preventivo #' . $preventivo_id;
        $submit_text = 'Aggiorna Preventivo';
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/form-preventivo.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>Template form preventivo non trovato.</p></div>';
        }
    }

    private function render_dashboard_preventivi() {
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/dashboard-preventivi.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>Template dashboard preventivi non trovato.</p></div>';
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

    /**
     * ✅ RIPRISTINATO: Render pagina Scansione Excel
     */
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
            echo '<div class="error"><p>Errore caricamento debug.</p></div>';
        }
    }

    /**
     * ✅ BUGFIX: Controllo sicuro del nonce
     */
    public function handle_save_preventivo() {
        // Verifica che sia una richiesta AJAX con nonce
        if (!isset($_POST['nonce'])) {
            return; // Non è una richiesta per questo handler
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'disco747_admin_nonce')) {
            wp_send_json_error('Nonce non valido');
            return;
        }
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        try {
            $data = $this->sanitize_preventivo_data($_POST);
            $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
            if ($preventivo_id > 0) {
                $result = $this->database->update_preventivo($preventivo_id, $data);
                $message = 'Preventivo aggiornato con successo';
            } else {
                $preventivo_id = $this->database->insert_preventivo($data);
                $result = $preventivo_id !== false;
                $message = 'Preventivo creato con successo';
            }
            if ($result) {
                $this->generate_preventivo_files($preventivo_id, $data);
                do_action('disco747_new_preventivo', $preventivo_id, $data);
                wp_send_json_success(array(
                    'message' => $message,
                    'preventivo_id' => $preventivo_id,
                    'redirect' => admin_url('admin.php?page=disco747-crm&action=dashboard_preventivi')
                ));
            } else {
                wp_send_json_error('Errore durante il salvataggio');
            }
        } catch (Exception $e) {
            $this->log('Errore salvataggio preventivo: ' . $e->getMessage(), 'error');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    /**
     * ✅ BUGFIX: Controllo sicuro del nonce
     */
    public function handle_delete_preventivo() {
        if (!isset($_POST['nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'disco747_admin_nonce')) {
            wp_send_json_error('Nonce non valido');
            return;
        }
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        $preventivo_id = intval($_POST['preventivo_id']);
        if ($preventivo_id <= 0) {
            wp_send_json_error('ID preventivo non valido');
            return;
        }
        
        try {
            $preventivo = $this->database->get_preventivo($preventivo_id);
            if (!$preventivo) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            if (!empty($preventivo->googledrive_url)) {
                $this->delete_files_from_storage($preventivo);
            }
            $result = $this->database->delete_preventivo($preventivo_id);
            if ($result) {
                wp_send_json_success('Preventivo eliminato con successo');
            } else {
                wp_send_json_error('Errore durante l\'eliminazione');
            }
        } catch (Exception $e) {
            $this->log('Errore eliminazione preventivo: ' . $e->getMessage(), 'error');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    /**
     * ✅ BUGFIX: Controllo sicuro del nonce
     */
    public function handle_get_preventivo() {
        if (!isset($_POST['nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'disco747_admin_nonce')) {
            wp_send_json_error('Nonce non valido');
            return;
        }
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        $preventivo_id = intval($_POST['preventivo_id']);
        if ($preventivo_id <= 0) {
            wp_send_json_error('ID preventivo non valido');
            return;
        }
        
        $preventivo = $this->database->get_preventivo($preventivo_id);
        if ($preventivo) {
            wp_send_json_success($preventivo);
        } else {
            wp_send_json_error('Preventivo non trovato');
        }
    }

    public function handle_dropbox_auth() {
        wp_send_json_success('Dropbox auth handled');
    }

    public function handle_googledrive_auth() {
        wp_send_json_success('Google Drive auth handled');
    }

    public function handle_test_storage() {
        $connected = $this->check_storage_connection();
        if ($connected) {
            wp_send_json_success('Storage connesso correttamente');
        } else {
            wp_send_json_error('Storage non connesso');
        }
    }

    private function sanitize_preventivo_data($post_data) {
        return array(
            'nome_referente' => sanitize_text_field($post_data['nome_referente'] ?? ''),
            'cognome_referente' => sanitize_text_field($post_data['cognome_referente'] ?? ''),
            'mail' => sanitize_email($post_data['mail'] ?? ''),
            'cellulare' => sanitize_text_field($post_data['cellulare'] ?? ''),
            'tipo_evento' => sanitize_text_field($post_data['tipo_evento'] ?? ''),
            'data_evento' => sanitize_text_field($post_data['data_evento'] ?? ''),
            'orario_inizio' => sanitize_text_field($post_data['orario_inizio'] ?? '20:30'),
            'orario_fine' => sanitize_text_field($post_data['orario_fine'] ?? '01:30'),
            'numero_invitati' => intval($post_data['numero_invitati'] ?? 50),
            'tipo_menu' => sanitize_text_field($post_data['tipo_menu'] ?? ''),
            'importo' => floatval($post_data['importo'] ?? 0),
            'acconto' => floatval($post_data['acconto'] ?? 0),
            'omaggio1' => sanitize_text_field($post_data['omaggio1'] ?? ''),
            'omaggio2' => sanitize_text_field($post_data['omaggio2'] ?? ''),
            'omaggio3' => sanitize_text_field($post_data['omaggio3'] ?? ''),
            'note_aggiuntive' => sanitize_textarea_field($post_data['note_aggiuntive'] ?? ''),
            'stato' => sanitize_key($post_data['stato'] ?? 'bozza'),
            'confermato' => isset($post_data['confermato']) ? 1 : 0
        );
    }

    /**
     * ✅ BUGFIX: Genera file PDF e Excel con dati completi
     */
    private function generate_preventivo_files($preventivo_id, $data) {
        try {
            $pdf_result = false;
            $excel_result = false;
            if ($this->pdf_excel_handler) {
                $pdf_result = $this->pdf_excel_handler->generate_pdf($data);
            }
            if ($this->excel_handler) {
                $excel_result = $this->excel_handler->generate_excel($data);
            }
            if ($pdf_result || $excel_result) {
                $this->upload_files_to_storage($preventivo_id, $pdf_result, $excel_result, $data);
            }
        } catch (Exception $e) {
            $this->log('Errore generazione file: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * ✅ BUGFIX: Upload con data_evento corretta
     */
    private function upload_files_to_storage($preventivo_id, $pdf_path, $excel_path, $preventivo_data) {
        try {
            if (!$this->storage_manager) {
                $this->log('Storage manager non disponibile', 'warning');
                return;
            }
            $uploaded_urls = array();
            if (empty($preventivo_data['data_evento'])) {
                $this->log('⚠️ data_evento mancante!', 'error');
                $preventivo_data['data_evento'] = date('Y-m-d');
            }
            $this->log('📤 Upload preventivo #' . $preventivo_id . ' - Data: ' . $preventivo_data['data_evento']);
            if ($pdf_path && file_exists($pdf_path)) {
                $this->log('📄 Upload PDF: ' . basename($pdf_path));
                $pdf_result = $this->storage_manager->upload_to_googledrive(
                    $pdf_path,
                    basename($pdf_path),
                    $preventivo_data['data_evento']
                );
                if ($pdf_result) {
                    $uploaded_urls['pdf_url'] = is_array($pdf_result) 
                        ? ($pdf_result['webViewLink'] ?? $pdf_result['webContentLink'] ?? '') 
                        : $pdf_result;
                    $this->log('✅ PDF caricato');
                }
            }
            if ($excel_path && file_exists($excel_path)) {
                $this->log('📊 Upload Excel: ' . basename($excel_path));
                $excel_result = $this->storage_manager->upload_to_googledrive(
                    $excel_path,
                    basename($excel_path),
                    $preventivo_data['data_evento']
                );
                if ($excel_result) {
                    $uploaded_urls['excel_url'] = is_array($excel_result) 
                        ? ($excel_result['webViewLink'] ?? $excel_result['webContentLink'] ?? '') 
                        : $excel_result;
                    $this->log('✅ Excel caricato');
                }
            }
            if (!empty($uploaded_urls)) {
                $update_data = $uploaded_urls;
                $update_data['googledrive_url'] = $uploaded_urls['pdf_url'] ?? $uploaded_urls['excel_url'] ?? '';
                $this->database->update_preventivo($preventivo_id, $update_data);
                $this->log('✅ URL salvati nel database');
            }
        } catch (Exception $e) {
            $this->log('❌ Errore upload: ' . $e->getMessage(), 'error');
        }
    }

    private function delete_files_from_storage($preventivo) {
        try {
            if (!$this->storage_manager) {
                $this->log('Storage manager non disponibile per eliminazione', 'warning');
                return;
            }
            if (!empty($preventivo->pdf_url)) {
                $this->storage_manager->delete_file($preventivo->pdf_url);
            }
            if (!empty($preventivo->excel_url)) {
                $this->storage_manager->delete_file($preventivo->excel_url);
            }
            if (!empty($preventivo->googledrive_url) && 
                $preventivo->googledrive_url !== $preventivo->pdf_url && 
                $preventivo->googledrive_url !== $preventivo->excel_url) {
                $this->storage_manager->delete_file($preventivo->googledrive_url);
            }
        } catch (Exception $e) {
            $this->log('Errore eliminazione file: ' . $e->getMessage(), 'error');
        }
    }

    private function get_dashboard_statistics() {
        if (!$this->database) {
            return array(
                'total_preventivi' => 0,
                'preventivi_attivi' => 0,
                'preventivi_confermati' => 0,
                'valore_totale' => 0
            );
        }
        return array(
            'total_preventivi' => $this->database->count_preventivi(),
            'preventivi_attivi' => $this->database->count_preventivi(array('stato' => 'attivo')),
            'preventivi_confermati' => $this->database->count_preventivi(array('confermato' => 1)),
            'valore_totale' => $this->database->sum_preventivi_value()
        );
    }

    private function get_system_status_summary() {
        return array(
            'storage_type' => get_option('disco747_storage_type', 'googledrive'),
            'storage_connected' => $this->check_storage_connection(),
            'plugin_version' => DISCO747_CRM_VERSION ?? '11.7.2',
            'last_sync' => get_option('disco747_last_sync', 'Mai')
        );
    }

    private function get_recent_preventivi($limit = 5) {
        if (!$this->database) {
            return array();
        }
        return $this->database->get_preventivi(array(
            'orderby' => 'created_at', 
            'order' => 'DESC',
            'limit' => $limit
        ));
    }

    private function check_storage_connection() {
        if (!$this->storage_manager) {
            return false;
        }
        try {
            if (method_exists($this->storage_manager, 'test_connection')) {
                return $this->storage_manager->test_connection();
            }
            return $this->storage_manager->is_configured();
        } catch (Exception $e) {
            $this->log('Errore verifica storage: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=disco747-settings') . '">' . 
                        __('Impostazioni', 'disco747') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    private function render_fallback_dashboard() {
        echo '<div class="wrap">';
        echo '<h1>747 Disco CRM - PreventiviParty</h1>';
        echo '<div class="notice notice-warning"><p>Template dashboard non trovato.</p></div>';
        echo '</div>';
    }

    private function render_fallback_settings() {
        echo '<div class="wrap">';
        echo '<h1>Impostazioni 747 Disco CRM</h1>';
        echo '<div class="notice notice-warning"><p>Template impostazioni non trovato.</p></div>';
        echo '</div>';
    }

    private function render_fallback_messages() {
        echo '<div class="wrap">';
        echo '<h1>Messaggi Automatici</h1>';
        echo '<div class="notice notice-warning"><p>Template messaggi non trovato.</p></div>';
        echo '</div>';
    }

    public function add_admin_notice($message, $type = 'info') {
        $this->admin_notices[] = array(
            'message' => $message,
            'type' => $type
        );
    }

    public function show_admin_notices() {
        foreach ($this->admin_notices as $notice) {
            $class = 'notice notice-' . $notice['type'];
            if ($notice['type'] === 'success') {
                $class .= ' is-dismissible';
            }
            echo '<div class="' . $class . '"><p>' . esc_html($notice['message']) . '</p></div>';
        }
    }

    private function log($message, $level = 'info') {
        if ($this->debug_mode) {
            try {
                if (function_exists('disco747_log')) {
                    disco747_log($message, strtoupper($level));
                } else {
                    $timestamp = date('Y-m-d H:i:s');
                    error_log("[{$timestamp}] [747Disco-Admin] [" . strtoupper($level) . "] {$message}");
                }
            } catch (\Exception $e) {
                error_log('[747 Disco CRM Admin] ' . $message);
            }
        }
    }
}