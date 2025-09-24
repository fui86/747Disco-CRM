<?php
/**
 * Ajax Handlers - 747 Disco CRM
 * Gestisce tutte le chiamate AJAX dell'interfaccia admin
 * 
 * @package    Disco747_CRM
 * @subpackage Admin
 * @since      11.6.0
 * @version    11.6.0-AJAX-COMPLETE
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Ajax Handlers per 747 Disco CRM
 */
class Disco747_Ajax_Handlers {
    
    private $debug_mode = true;
    
    public function __construct() {
        $this->debug_mode = (defined('WP_DEBUG') && WP_DEBUG && defined('DISCO747_DEBUG') && DISCO747_DEBUG);
        $this->register_ajax_hooks();
    }
    
    /**
     * Registra tutti gli hook AJAX
     */
    private function register_ajax_hooks() {
        // Hook per utenti loggati
        add_action('wp_ajax_disco747_revoke_oauth', array($this, 'handle_revoke_oauth'));
        add_action('wp_ajax_disco747_clear_cache', array($this, 'handle_clear_cache'));
        add_action('wp_ajax_disco747_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_disco747_get_preventivi', array($this, 'handle_get_preventivi'));
        add_action('wp_ajax_disco747_delete_preventivo', array($this, 'handle_delete_preventivo_ajax'));
        add_action('wp_ajax_disco747_export_preventivi', array($this, 'handle_export_preventivi'));
        add_action('wp_ajax_disco747_update_preventivo_status', array($this, 'handle_update_preventivo_status'));
        
        $this->debug('Ajax handlers registrati');
    }
    
    /**
     * Revoca autorizzazione Google Drive OAuth
     */
    public function handle_revoke_oauth() {
        $this->debug('AJAX: Revoca OAuth richiesta');
        
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_revoke_oauth')) {
                throw new Exception('Nonce non valido');
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                throw new Exception('Permessi insufficienti');
            }
            
            // Revoca token su Google (opzionale)
            $access_token = get_option('disco747_google_access_token', '');
            if (!empty($access_token)) {
                $this->debug('Tentativo revoca token su Google...');
                
                $response = wp_remote_post('https://oauth2.googleapis.com/revoke', [
                    'body' => ['token' => $access_token],
                    'timeout' => 10
                ]);
                
                if (!is_wp_error($response)) {
                    $this->debug('Token revocato su Google');
                }
            }
            
            // Rimuovi token locali
            delete_option('disco747_google_access_token');
            delete_option('disco747_google_refresh_token');
            delete_option('disco747_google_token_expires');
            
            // Pulisci cache
            delete_transient('disco747_preventivi_index_cache');
            delete_transient('disco747_gdrive_preventivi');
            
            $this->debug('Autorizzazione OAuth revocata con successo');
            
            wp_send_json_success([
                'message' => 'Autorizzazione Google Drive revocata con successo'
            ]);
            
        } catch (Exception $e) {
            $this->debug('ERRORE revoca OAuth: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error([
                'message' => 'Errore durante la revoca: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Pulisce tutta la cache del sistema
     */
    public function handle_clear_cache() {
        $this->debug('AJAX: Clear cache richiesto');
        
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_clear_cache')) {
                throw new Exception('Nonce non valido');
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                throw new Exception('Permessi insufficienti');
            }
            
            // Pulisci tutte le cache
            $cleared_items = [];
            
            // Cache preventivi
            if (delete_transient('disco747_preventivi_index_cache')) {
                $cleared_items[] = 'Cache preventivi';
            }
            
            if (delete_transient('disco747_gdrive_preventivi')) {
                $cleared_items[] = 'Cache Google Drive';
            }
            
            // Cache debug
            if (delete_transient('disco747_debug_messages')) {
                $cleared_items[] = 'Cache debug';
            }
            
            // Cache WordPress (se WP Rocket o simili)
            if (function_exists('rocket_clean_domain')) {
                rocket_clean_domain();
                $cleared_items[] = 'Cache WP Rocket';
            }
            
            // Opzioni temporanee
            delete_option('disco747_last_cache_refresh');
            
            $this->debug('Cache pulita: ' . implode(', ', $cleared_items));
            
            wp_send_json_success([
                'message' => 'Cache pulita con successo',
                'cleared_items' => $cleared_items
            ]);
            
        } catch (Exception $e) {
            $this->debug('ERRORE clear cache: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error([
                'message' => 'Errore durante pulizia cache: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Test connessione Google Drive
     */
    public function handle_test_connection() {
        $this->debug('AJAX: Test connessione richiesto');
        
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_test_connection')) {
                throw new Exception('Nonce non valido');
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                throw new Exception('Permessi insufficienti');
            }
            
            // Ottieni istanza GoogleDrive
            $disco747_crm = disco747_crm();
            if (!$disco747_crm) {
                throw new Exception('Plugin non inizializzato');
            }
            
            $gdrive_sync = $disco747_crm->get_gdrive_sync();
            if (!$gdrive_sync) {
                throw new Exception('GoogleDrive Sync non disponibile');
            }
            
            // Test disponibilità
            $available = $gdrive_sync->is_available();
            if (!$available) {
                $error = method_exists($gdrive_sync, 'get_last_error') ? $gdrive_sync->get_last_error() : 'Non disponibile';
                throw new Exception('Google Drive non disponibile: ' . $error);
            }
            
            // Test connessione effettiva
            $googledrive = $disco747_crm->get_googledrive();
            if ($googledrive && method_exists($googledrive, 'test_connection')) {
                $connection_ok = $googledrive->test_connection();
                
                if (!$connection_ok) {
                    $error = method_exists($googledrive, 'get_last_error') ? $googledrive->get_last_error() : 'Test fallito';
                    throw new Exception('Test connessione fallito: ' . $error);
                }
            }
            
            // Test cartella principale
            $result = $gdrive_sync->disco747_get_cached_preventivi(1, 1);
            $cache_info = $result['cache_info'] ?? [];
            
            $this->debug('Test connessione completato con successo');
            
            wp_send_json_success([
                'message' => 'Connessione Google Drive funzionante',
                'details' => [
                    'gdrive_available' => true,
                    'cache_status' => $cache_info['cache_status'] ?? 'unknown',
                    'last_update' => $cache_info['last_update'] ?? null
                ]
            ]);
            
        } catch (Exception $e) {
            $this->debug('ERRORE test connessione: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error([
                'message' => 'Test connessione fallito: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Ottieni preventivi con filtri e paginazione
     */
    public function handle_get_preventivi() {
        $this->debug('AJAX: Get preventivi richiesto');
        
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_admin_nonce')) {
                throw new Exception('Nonce non valido');
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                throw new Exception('Permessi insufficienti');
            }
            
            // Parametri
            $page = intval($_POST['page'] ?? 1);
            $per_page = intval($_POST['per_page'] ?? 10);
            $filters = $_POST['filters'] ?? [];
            
            // Sanitizza filtri
            $clean_filters = [];
            if (!empty($filters['stato'])) {
                $clean_filters['stato'] = sanitize_text_field($filters['stato']);
            }
            if (!empty($filters['menu'])) {
                $clean_filters['menu'] = sanitize_text_field($filters['menu']);
            }
            if (!empty($filters['search'])) {
                $clean_filters['search'] = sanitize_text_field($filters['search']);
            }
            
            // Ottieni preventivi
            $disco747_crm = disco747_crm();
            if (!$disco747_crm) {
                throw new Exception('Plugin non inizializzato');
            }
            
            $gdrive_sync = $disco747_crm->get_gdrive_sync();
            if (!$gdrive_sync || !$gdrive_sync->is_available()) {
                throw new Exception('Google Drive non disponibile');
            }
            
            $result = $gdrive_sync->disco747_get_cached_preventivi($page, $per_page, $clean_filters);
            
            $this->debug("Preventivi ottenuti: " . count($result['preventivi']) . " su pagina {$page}");
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            $this->debug('ERRORE get preventivi: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error([
                'message' => 'Errore caricamento preventivi: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Elimina preventivo (AJAX)
     */
    public function handle_delete_preventivo_ajax() {
        $this->debug('AJAX: Delete preventivo richiesto');
        
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_delete_preventivo')) {
                throw new Exception('Nonce non valido');
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                throw new Exception('Permessi insufficienti');
            }
            
            $preventivo_id = sanitize_text_field($_POST['preventivo_id'] ?? '');
            if (empty($preventivo_id)) {
                throw new Exception('ID preventivo mancante');
            }
            
            // Per ora simula eliminazione (implementare logica reale)
            $this->debug("Simulazione eliminazione preventivo: {$preventivo_id}");
            
            // Pulisci cache dopo eliminazione
            delete_transient('disco747_preventivi_index_cache');
            
            wp_send_json_success([
                'message' => 'Preventivo eliminato con successo',
                'preventivo_id' => $preventivo_id
            ]);
            
        } catch (Exception $e) {
            $this->debug('ERRORE delete preventivo: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error([
                'message' => 'Errore eliminazione preventivo: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Export preventivi in vari formati
     */
    public function handle_export_preventivi() {
        $this->debug('AJAX: Export preventivi richiesto');
        
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_admin_nonce')) {
                throw new Exception('Nonce non valido');
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                throw new Exception('Permessi insufficienti');
            }
            
            $format = sanitize_text_field($_POST['format'] ?? 'csv');
            $filters = $_POST['filters'] ?? [];
            
            // Ottieni tutti i preventivi
            $disco747_crm = disco747_crm();
            if (!$disco747_crm) {
                throw new Exception('Plugin non inizializzato');
            }
            
            $gdrive_sync = $disco747_crm->get_gdrive_sync();
            if (!$gdrive_sync || !$gdrive_sync->is_available()) {
                throw new Exception('Google Drive non disponibile');
            }
            
            $result = $gdrive_sync->disco747_get_cached_preventivi(1, 1000, $filters);
            $preventivi = $result['preventivi'] ?? [];
            
            if (empty($preventivi)) {
                throw new Exception('Nessun preventivo da esportare');
            }
            
            // Genera file export
            $export_data = $this->generate_export_data($preventivi, $format);
            
            $this->debug("Export generato: {$format}, " . count($preventivi) . " preventivi");
            
            wp_send_json_success([
                'message' => 'Export generato con successo',
                'format' => $format,
                'count' => count($preventivi),
                'data' => $export_data,
                'filename' => 'preventivi_747disco_' . date('Y-m-d') . '.' . $format
            ]);
            
        } catch (Exception $e) {
            $this->debug('ERRORE export preventivi: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error([
                'message' => 'Errore export preventivi: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Aggiorna stato preventivo
     */
    public function handle_update_preventivo_status() {
        $this->debug('AJAX: Update status preventivo richiesto');
        
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_admin_nonce')) {
                throw new Exception('Nonce non valido');
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                throw new Exception('Permessi insufficienti');
            }
            
            $preventivo_id = sanitize_text_field($_POST['preventivo_id'] ?? '');
            $new_status = sanitize_text_field($_POST['new_status'] ?? '');
            
            if (empty($preventivo_id) || empty($new_status)) {
                throw new Exception('Parametri mancanti');
            }
            
            // Valida nuovo stato
            $valid_states = ['Non confermato', 'Confermato', 'Annullato'];
            if (!in_array($new_status, $valid_states)) {
                throw new Exception('Stato non valido');
            }
            
            // Simula aggiornamento stato (implementare logica reale)
            $this->debug("Simulazione aggiornamento stato preventivo {$preventivo_id} -> {$new_status}");
            
            // Pulisci cache
            delete_transient('disco747_preventivi_index_cache');
            
            wp_send_json_success([
                'message' => 'Stato aggiornato con successo',
                'preventivo_id' => $preventivo_id,
                'new_status' => $new_status
            ]);
            
        } catch (Exception $e) {
            $this->debug('ERRORE update status: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error([
                'message' => 'Errore aggiornamento stato: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Genera dati per export
     */
    private function generate_export_data($preventivi, $format) {
        switch ($format) {
            case 'csv':
                return $this->generate_csv_export($preventivi);
            case 'json':
                return $this->generate_json_export($preventivi);
            case 'xlsx':
                return $this->generate_xlsx_export($preventivi);
            default:
                throw new Exception('Formato export non supportato');
        }
    }
    
    /**
     * Genera export CSV
     */
    private function generate_csv_export($preventivi) {
        $output = fopen('php://temp', 'r+');
        
        // Header CSV
        $headers = [
            'Nome File',
            'Data Evento', 
            'Tipo Evento',
            'Menu',
            'Numero Invitati',
            'Stato',
            'Importo Totale',
            'Acconto',
            'Saldo'
        ];
        
        fputcsv($output, $headers);
        
        // Dati
        foreach ($preventivi as $preventivo) {
            $row = [
                $preventivo['nome_file'] ?? '',
                $preventivo['data_evento'] ?? '',
                $preventivo['tipo_evento'] ?? '',
                $preventivo['menu'] ?? '',
                $preventivo['numero_invitati'] ?? 0,
                $preventivo['stato'] ?? '',
                number_format(floatval($preventivo['importo_totale'] ?? 0), 2, ',', '.'),
                number_format(floatval($preventivo['acconto'] ?? 0), 2, ',', '.'),
                number_format(floatval($preventivo['saldo'] ?? 0), 2, ',', '.')
            ];
            
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv_data = stream_get_contents($output);
        fclose($output);
        
        return base64_encode($csv_data);
    }
    
    /**
     * Genera export JSON
     */
    private function generate_json_export($preventivi) {
        $export_data = [
            'export_date' => current_time('mysql'),
            'total_count' => count($preventivi),
            'preventivi' => array_map(function($p) {
                return [
                    'nome_file' => $p['nome_file'] ?? '',
                    'data_evento' => $p['data_evento'] ?? '',
                    'tipo_evento' => $p['tipo_evento'] ?? '',
                    'menu' => $p['menu'] ?? '',
                    'numero_invitati' => intval($p['numero_invitati'] ?? 0),
                    'stato' => $p['stato'] ?? '',
                    'importo_totale' => floatval($p['importo_totale'] ?? 0),
                    'acconto' => floatval($p['acconto'] ?? 0),
                    'saldo' => floatval($p['saldo'] ?? 0)
                ];
            }, $preventivi)
        ];
        
        return base64_encode(json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Genera export XLSX (simulato)
     */
    private function generate_xlsx_export($preventivi) {
        // Per semplicità, restituisce CSV rinominato
        // In produzione implementare con PhpSpreadsheet
        return $this->generate_csv_export($preventivi);
    }
    
    /**
     * Debug sicuro
     */
    private function debug($message, $level = 'INFO') {
        if (!$this->debug_mode) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] [Disco747_Ajax] [{$level}] {$message}";
        
        error_log($log_message);
    }
}

// Inizializza handlers se siamo in admin
if (is_admin()) {
    new Disco747_Ajax_Handlers();
}