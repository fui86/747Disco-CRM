<?php
/**
 * Admin Manager per 747 Disco CRM
 * 
 * @package Disco747_CRM
 * @subpackage Admin
 */

namespace Disco747_CRM\Admin;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Admin {
    
    private $plugin_name;
    private $version;
    
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        error_log('[' . date('Y-m-d H:i:s') . '] [747Disco-Admin] Admin Manager inizializzato');
    }
    
    /**
     * Registra hook WordPress
     */
    public function register_admin_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_disco747_scan_drive_batch', array($this, 'handle_batch_scan'));
        add_action('wp_ajax_disco747_get_preventivi_table', array($this, 'handle_get_preventivi_table'));
        
        error_log('[' . date('Y-m-d H:i:s') . '] [747Disco-Admin] Hook WordPress registrati (incluso batch scan)');
    }
    
    /**
     * Aggiunge voci menu amministrazione
     */
    public function add_admin_menu() {
        // Menu principale
        add_menu_page(
            '747 Disco CRM',
            'PreventiviParty',
            'manage_options',
            'disco747-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-calendar-alt',
            30
        );
        
        // Sottomenu Dashboard
        add_submenu_page(
            'disco747-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'disco747-dashboard',
            array($this, 'render_dashboard_page')
        );
        
        // Form Preventivo
        add_submenu_page(
            'disco747-dashboard',
            'Nuovo Preventivo',
            'Nuovo Preventivo',
            'manage_options',
            'disco747-form',
            array($this, 'render_form_page')
        );
        
        // Gestione Preventivi
        add_submenu_page(
            'disco747-dashboard',
            'Gestione Preventivi',
            'Gestione Preventivi',
            'manage_options',
            'disco747-manage',
            array($this, 'render_manage_page')
        );
        
        // Scansione Excel
        add_submenu_page(
            'disco747-dashboard',
            'Scansione Excel Auto',
            'Scansione Excel Auto',
            'manage_options',
            'disco747-scan-excel',
            array($this, 'render_scan_excel_page')
        );
        
        // Impostazioni
        add_submenu_page(
            'disco747-dashboard',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'disco747-settings',
            array($this, 'render_settings_page')
        );
        
        // Messaggi Automatici
        add_submenu_page(
            'disco747-dashboard',
            'Messaggi Automatici',
            'Messaggi Automatici',
            'manage_options',
            'disco747-messages',
            array($this, 'render_messages_page')
        );
        
        error_log('[' . date('Y-m-d H:i:s') . '] [747Disco-Admin] Menu amministrazione aggiunto');
    }
    
    /**
     * Carica assets CSS/JS
     */
    public function enqueue_admin_assets($hook_suffix) {
        error_log('[747Disco-Admin] Hook suffix: ' . $hook_suffix);
        
        // Carica solo nelle pagine del plugin
        if (strpos($hook_suffix, 'disco747') === false && strpos($hook_suffix, 'preventiviparty') === false) {
            return;
        }
        
        // CSS Admin
        wp_enqueue_style(
            'disco747-admin-css',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/admin.css',
            array(),
            $this->version
        );
        
        // JS Admin generale
        wp_enqueue_script(
            'disco747-admin-js',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );
        
        // Se siamo nella pagina Excel Scan, carica lo script specifico
        if (strpos($hook_suffix, 'disco747-scan-excel') !== false) {
            error_log('[747Disco-Admin] EXCEL SCAN RILEVATO!');
            
            wp_enqueue_script(
                'disco747-scan-excel-js',
                plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/scan-excel.js',
                array('jquery'),
                $this->version,
                true
            );
            
            wp_localize_script('disco747-scan-excel-js', 'disco747ScanExcel', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('disco747_scan_drive')
            ));
            
            error_log('[747Disco-Admin] Assets Excel Scan caricati');
        }
        
        error_log('[747Disco-Admin] Assets amministrazione caricati per: ' . $hook_suffix);
    }
    
    /**
     * Render Dashboard - FIXED PATH
     */
    public function render_dashboard_page() {
        $view_file = plugin_dir_path(__FILE__) . 'views/dashboard-page.php';
        if (file_exists($view_file)) {
            require_once $view_file;
        } else {
            echo '<div class="wrap"><h1>Errore: File view non trovato</h1><p>' . esc_html($view_file) . '</p></div>';
            error_log('[747Disco-Admin] File view non trovato: ' . $view_file);
        }
    }
    
    /**
     * Render Form Preventivo - FIXED PATH
     */
    public function render_form_page() {
        $view_file = plugin_dir_path(__FILE__) . 'views/form-page.php';
        if (file_exists($view_file)) {
            require_once $view_file;
        } else {
            echo '<div class="wrap"><h1>Errore: File view non trovato</h1><p>' . esc_html($view_file) . '</p></div>';
            error_log('[747Disco-Admin] File view non trovato: ' . $view_file);
        }
    }
    
    /**
     * Render Gestione Preventivi - FIXED PATH
     */
    public function render_manage_page() {
        $view_file = plugin_dir_path(__FILE__) . 'views/manage-page.php';
        if (file_exists($view_file)) {
            require_once $view_file;
        } else {
            echo '<div class="wrap"><h1>Errore: File view non trovato</h1><p>' . esc_html($view_file) . '</p></div>';
            error_log('[747Disco-Admin] File view non trovato: ' . $view_file);
        }
    }
    
    /**
     * Render Scansione Excel - FIXED PATH
     */
    public function render_scan_excel_page() {
        $view_file = plugin_dir_path(__FILE__) . 'views/scan-excel-page.php';
        if (file_exists($view_file)) {
            require_once $view_file;
        } else {
            echo '<div class="wrap"><h1>Errore: File view non trovato</h1><p>' . esc_html($view_file) . '</p></div>';
            error_log('[747Disco-Admin] File view non trovato: ' . $view_file);
        }
    }
    
    /**
     * Render Impostazioni - FIXED PATH
     */
    public function render_settings_page() {
        $view_file = plugin_dir_path(__FILE__) . 'views/settings-page.php';
        if (file_exists($view_file)) {
            require_once $view_file;
        } else {
            echo '<div class="wrap"><h1>Errore: File view non trovato</h1><p>' . esc_html($view_file) . '</p></div>';
            error_log('[747Disco-Admin] File view non trovato: ' . $view_file);
        }
    }
    
    /**
     * Render Messaggi Automatici - FIXED PATH
     */
    public function render_messages_page() {
        $view_file = plugin_dir_path(__FILE__) . 'views/messages-page.php';
        if (file_exists($view_file)) {
            require_once $view_file;
        } else {
            echo '<div class="wrap"><h1>Errore: File view non trovato</h1><p>' . esc_html($view_file) . '</p></div>';
            error_log('[747Disco-Admin] File view non trovato: ' . $view_file);
        }
    }
    
    /**
     * AJAX Handler: Batch Scan Google Drive
     */
    public function handle_batch_scan() {
        error_log('[747Disco-Admin] handle_batch_scan chiamato!');
        
        // Verifica nonce
        check_ajax_referer('disco747_scan_drive', 'nonce');
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            error_log('[747Disco-Admin] Permessi insufficienti');
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        error_log('[747Disco-Admin] Permessi OK - avvio batch scan reale');
        
        try {
            // Carica classe GoogleDrive_Sync
            if (!class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive_Sync')) {
                error_log('[747Disco-Admin] Classe GoogleDrive_Sync NON trovata');
                throw new \Exception('Classe GoogleDrive_Sync non disponibile');
            }
            
            error_log('[747Disco-Admin] Classe GoogleDrive_Sync trovata');
            
            // Carica GoogleDrive handler
            if (!class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                error_log('[747Disco-Admin] Classe GoogleDrive NON trovata');
                throw new \Exception('Classe GoogleDrive non disponibile');
            }
            
            error_log('[747Disco-Admin] Classe GoogleDrive trovata');
            
            // Istanzia GoogleDrive
            $googledrive = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
            error_log('[747Disco-Admin] GoogleDrive handler istanziato');
            
            // Istanzia GoogleDrive_Sync
            $sync = new \Disco747_CRM\Storage\Disco747_GoogleDrive_Sync($googledrive);
            error_log('[747Disco-Admin] GoogleDrive Sync istanziato');
            
            // Verifica disponibilità
            if (!$sync->is_available()) {
                $error = $sync->get_last_error();
                error_log('[747Disco-Admin] GoogleDrive Sync NON disponibile: ' . $error);
                throw new \Exception('Sync non disponibile: ' . $error);
            }
            
            error_log('[747Disco-Admin] GoogleDrive Sync disponibile - avvio scan...');
            
            // Esegui batch scan
            $result = $sync->scan_excel_files_batch();
            
            error_log('[747Disco-Admin] Batch scan completato - found: ' . $result['found']);
            error_log('[747Disco-Admin] Result messages: ' . implode(', ', $result['messages']));
            
            wp_send_json_success($result);
            
        } catch (\Exception $e) {
            error_log('[747Disco-Admin] ERRORE batch scan: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX Handler: Ottieni preventivi per tabella
     */
    public function handle_get_preventivi_table() {
        // Verifica nonce
        check_ajax_referer('disco747_table', 'nonce');
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        try {
            $disco747_crm = disco747_crm();
            
            if (!$disco747_crm) {
                throw new \Exception('Plugin CRM non disponibile');
            }
            
            $database = $disco747_crm->get_database();
            
            if (!$database) {
                throw new \Exception('Database non disponibile');
            }
            
            // Ottieni tutti i preventivi
            $preventivi = $database->get_preventivi(array(
                'orderby' => 'data_evento',
                'order' => 'DESC',
                'limit' => 1000
            ));
            
            // Converti oggetti in array per JSON
            $preventivi_array = array();
            foreach ($preventivi as $preventivo) {
                $preventivi_array[] = (array) $preventivo;
            }
            
            wp_send_json_success(array(
                'preventivi' => $preventivi_array,
                'total' => count($preventivi_array)
            ));
            
        } catch (\Exception $e) {
            error_log('[747Disco-Admin] Errore get_preventivi_table: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
}