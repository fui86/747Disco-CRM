<?php
/**
 * Classe per la sincronizzazione avanzata e scansione batch di Google Drive
 * Estende le funzionalità esistenti con metodi per analisi massiva dei file Excel
 *
 * @package    Disco747_CRM
 * @subpackage Storage
 * @since      11.4.2
 * @version    11.4.2
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Storage;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_GoogleDrive_Sync
 * 
 * Gestisce operazioni avanzate di sincronizzazione e scansione batch per Google Drive
 * Mantiene tutte le funzionalità esistenti e aggiunge nuove capacità di analisi
 * 
 * @since 11.4.2
 */
class Disco747_GoogleDrive_Sync {
    
    /**
     * Configurazione API
     */
    private $base_url = 'https://www.googleapis.com/drive/v3';
    private $upload_url = 'https://www.googleapis.com/upload/drive/v3';
    private $token_url = 'https://oauth2.googleapis.com/token';
    
    /**
     * Rate limiting per batch scan
     */
    private $requests_per_minute = 100;
    private $batch_size = 50;
    private $retry_max_attempts = 3;
    private $retry_delay_seconds = 2;
    
    /**
     * Cache e tracking
     */
    private $folder_cache = array();
    private $request_count = 0;
    private $last_request_time = 0;
    
    /**
     * Debug e logging
     */
    private $debug_mode = true;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->log('[747Disco-Scan] GoogleDrive Sync inizializzato');
    }
    
    // ============================================================================
    // METODI PER SCANSIONE BATCH - NUOVE FUNZIONALITÀ
    // ============================================================================
    
    /**
     * Scansiona ricorsivamente tutte le cartelle /747-Preventivi/AAAA/MMMM/ 
     * per trovare file Excel (.xlsx) con paginazione e rate limiting
     *
     * @param array $options Opzioni di scansione
     * @return array Lista file con metadati
     */
    public function scan_excel_files_batch($options = array()) {
        $defaults = array(
            'page_size' => $this->batch_size,
            'max_results' => 1000,
            'year_from' => null,
            'year_to' => null,
            'include_metadata' => true
        );
        
        $options = array_merge($defaults, $options);
        
        $this->log('[747Disco-Scan] Avvio scansione batch Excel files');
        
        try {
            // Ottieni le credenziali OAuth
            $credentials = $this->get_oauth_credentials();
            if (!$credentials) {
                throw new \Exception('Credenziali Google Drive non configurate');
            }
            
            // Ottieni token valido
            $token = $this->get_valid_access_token($credentials);
            
            // Trova cartella principale /747-Preventivi/
            $main_folder_id = $this->find_main_preventivi_folder($token);
            if (!$main_folder_id) {
                throw new \Exception('Cartella /747-Preventivi/ non trovata su Google Drive');
            }
            
            // Scansiona ricorsivamente per anno/mese
            $all_files = array();
            $year_folders = $this->list_year_folders($token, $main_folder_id, $options);
            
            foreach ($year_folders as $year_folder) {
                $month_folders = $this->list_month_folders($token, $year_folder['id']);
                
                foreach ($month_folders as $month_folder) {
                    $excel_files = $this->list_excel_files_in_folder(
                        $token, 
                        $month_folder['id'], 
                        $year_folder['name'], 
                        $month_folder['name'],
                        $options
                    );
                    
                    $all_files = array_merge($all_files, $excel_files);
                    
                    // Rate limiting check
                    $this->check_rate_limit();
                    
                    // Limite massimo risultati
                    if (count($all_files) >= $options['max_results']) {
                        break 2;
                    }
                }
            }
            
            $this->log('[747Disco-Scan] Scansione completata: ' . count($all_files) . ' file Excel trovati');
            
            return array(
                'success' => true,
                'files' => $all_files,
                'total_found' => count($all_files),
                'scan_time' => date('Y-m-d H:i:s')
            );
            
        } catch (\Exception $e) {
            $this->log('[747Disco-Scan] Errore scansione batch: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'files' => array()
            );
        }
    }
    
    /**
     * Trova cartella principale /747-Preventivi/ su Google Drive
     *
     * @param string $token Token di accesso
     * @return string|null ID della cartella principale
     */
    private function find_main_preventivi_folder($token) {
        $credentials = $this->get_oauth_credentials();
        $configured_folder_id = $credentials['folder_id'] ?? null;
        
        if ($configured_folder_id) {
            $this->log('[747Disco-Scan] Uso cartella configurata: ' . $configured_folder_id);
            return $configured_folder_id;
        }
        
        // Cerca cartella per nome se non configurata
        $query = "name='747-Preventivi' and mimeType='application/vnd.google-apps.folder' and trashed=false";
        
        $response = $this->make_api_request(
            $token,
            '/files',
            array('q' => $query, 'fields' => 'files(id,name)')
        );
        
        if ($response && !empty($response['files'])) {
            return $response['files'][0]['id'];
        }
        
        return null;
    }
    
    /**
     * Lista le cartelle anno (AAAA) nella cartella principale
     *
     * @param string $token Token di accesso
     * @param string $parent_folder_id ID cartella padre
     * @param array $options Opzioni di filtro
     * @return array Lista cartelle anno
     */
    private function list_year_folders($token, $parent_folder_id, $options = array()) {
        $query = "'{$parent_folder_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";
        
        $response = $this->make_api_request(
            $token,
            '/files',
            array('q' => $query, 'fields' => 'files(id,name)', 'pageSize' => 100)
        );
        
        if (!$response || empty($response['files'])) {
            return array();
        }
        
        $year_folders = array();
        foreach ($response['files'] as $folder) {
            // Verifica che sia una cartella anno (4 cifre)
            if (preg_match('/^\d{4}$/', $folder['name'])) {
                $year = intval($folder['name']);
                
                // Applica filtri anno se specificati
                if ($options['year_from'] && $year < $options['year_from']) continue;
                if ($options['year_to'] && $year > $options['year_to']) continue;
                
                $year_folders[] = $folder;
            }
        }
        
        return $year_folders;
    }
    
    /**
     * Lista le cartelle mese (MM o MMMM) in una cartella anno
     *
     * @param string $token Token di accesso
     * @param string $year_folder_id ID cartella anno
     * @return array Lista cartelle mese
     */
    private function list_month_folders($token, $year_folder_id) {
        $query = "'{$year_folder_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";
        
        $response = $this->make_api_request(
            $token,
            '/files',
            array('q' => $query, 'fields' => 'files(id,name)', 'pageSize' => 20)
        );
        
        if (!$response || empty($response['files'])) {
            return array();
        }
        
        $month_folders = array();
        foreach ($response['files'] as $folder) {
            // Verifica formato mese (MM o nome mese)
            if (preg_match('/^(0[1-9]|1[0-2])$/', $folder['name']) || 
                in_array(strtolower($folder['name']), $this->get_month_names())) {
                $month_folders[] = $folder;
            }
        }
        
        return $month_folders;
    }
    
    /**
     * Lista i file Excel in una specifica cartella mese
     *
     * @param string $token Token di accesso
     * @param string $folder_id ID cartella mese
     * @param string $year Anno per path
     * @param string $month Mese per path
     * @param array $options Opzioni
     * @return array Lista file Excel
     */
    private function list_excel_files_in_folder($token, $folder_id, $year, $month, $options = array()) {
        $query = "'{$folder_id}' in parents and (name contains '.xlsx' or name contains '.xls') and trashed=false";
        
        $params = array(
            'q' => $query,
            'fields' => 'files(id,name,size,createdTime,modifiedTime,webViewLink)',
            'pageSize' => $options['page_size'] ?? $this->batch_size
        );
        
        $excel_files = array();
        $next_page_token = null;
        
        do {
            if ($next_page_token) {
                $params['pageToken'] = $next_page_token;
            }
            
            $response = $this->make_api_request($token, '/files', $params);
            
            if (!$response) {
                break;
            }
            
            if (!empty($response['files'])) {
                foreach ($response['files'] as $file) {
                    // Filtra solo file Excel validi
                    if ($this->is_valid_excel_file($file['name'])) {
                        $excel_files[] = array(
                            'file_id' => $file['id'],
                            'filename' => $file['name'],
                            'drive_path' => "/747-Preventivi/{$year}/{$month}/" . $file['name'],
                            'size' => intval($file['size'] ?? 0),
                            'created_time' => $file['createdTime'] ?? null,
                            'modified_time' => $file['modifiedTime'] ?? null,
                            'web_view_link' => $file['webViewLink'] ?? null,
                            'year' => $year,
                            'month' => $month,
                            'folder_id' => $folder_id
                        );
                    }
                }
            }
            
            $next_page_token = $response['nextPageToken'] ?? null;
            
            // Rate limiting
            $this->check_rate_limit();
            
        } while ($next_page_token);
        
        return $excel_files;
    }
    
    /**
     * Scarica il contenuto di un file Excel da Google Drive
     *
     * @param string $file_id ID del file su Google Drive
     * @param int $max_retries Numero massimo di tentativi
     * @return string|false Contenuto del file o false in caso di errore
     */
    public function download_excel_file($file_id, $max_retries = null) {
        if ($max_retries === null) {
            $max_retries = $this->retry_max_attempts;
        }
        
        $this->log('[747Disco-Scan] Download file Excel: ' . $file_id);
        
        $attempt = 0;
        while ($attempt < $max_retries) {
            $attempt++;
            
            try {
                $credentials = $this->get_oauth_credentials();
                $token = $this->get_valid_access_token($credentials);
                
                $response = wp_remote_get($this->base_url . '/files/' . $file_id . '?alt=media', array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'User-Agent' => '747-Disco-CRM/11.4.2'
                    ),
                    'timeout' => 60
                ));
                
                if (is_wp_error($response)) {
                    throw new \Exception('Errore HTTP: ' . $response->get_error_message());
                }
                
                $http_code = wp_remote_retrieve_response_code($response);
                
                if ($http_code === 200) {
                    $content = wp_remote_retrieve_body($response);
                    $this->log('[747Disco-Scan] File scaricato: ' . strlen($content) . ' bytes');
                    return $content;
                } else if ($http_code === 429) {
                    // Rate limit: attendi e riprova
                    $this->log('[747Disco-Scan] Rate limit raggiunto, attendo...');
                    sleep($this->retry_delay_seconds * $attempt);
                    continue;
                } else {
                    throw new \Exception('HTTP Error: ' . $http_code);
                }
                
            } catch (\Exception $e) {
                $this->log('[747Disco-Scan] Tentativo ' . $attempt . ' fallito: ' . $e->getMessage());
                
                if ($attempt >= $max_retries) {
                    $this->log('[747Disco-Scan] Download fallito dopo ' . $max_retries . ' tentativi', 'error');
                    return false;
                }
                
                sleep($this->retry_delay_seconds * $attempt);
            }
        }
        
        return false;
    }
    
    /**
     * Ottieni metadati estesi di un file
     *
     * @param string $file_id ID del file
     * @return array|null Metadati del file
     */
    public function get_file_metadata($file_id) {
        try {
            $credentials = $this->get_oauth_credentials();
            $token = $this->get_valid_access_token($credentials);
            
            $response = $this->make_api_request(
                $token,
                '/files/' . $file_id,
                array('fields' => 'id,name,size,createdTime,modifiedTime,webViewLink,parents')
            );
            
            return $response;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-Scan] Errore recupero metadati: ' . $e->getMessage(), 'error');
            return null;
        }
    }
    
    // ============================================================================
    // METODI DI UTILITÀ E SUPPORTO
    // ============================================================================
    
    /**
     * Verifica se un nome file è un Excel valido per i preventivi
     *
     * @param string $filename Nome del file
     * @return bool
     */
    private function is_valid_excel_file($filename) {
        // Deve terminare con .xlsx
        if (!preg_match('/\.xlsx?$/i', $filename)) {
            return false;
        }
        
        // Deve avere pattern preventivo (data + tipo evento)
        // Esempi: "14_10 Compleanno (Menu 747).xlsx", "CONF 25_12 Matrimonio.xlsx"
        if (preg_match('/^(NO\s+|CONF\s+)?\d{1,2}_\d{1,2}\s+.+\.xlsx?$/i', $filename)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Rate limiting: controlla e attende se necessario
     */
    private function check_rate_limit() {
        $this->request_count++;
        $current_time = time();
        
        // Se abbiamo fatto troppe richieste nell'ultimo minuto, aspetta
        if ($this->request_count >= $this->requests_per_minute) {
            $time_passed = $current_time - $this->last_request_time;
            if ($time_passed < 60) {
                $sleep_time = 60 - $time_passed + 1;
                $this->log('[747Disco-Scan] Rate limit: attendo ' . $sleep_time . ' secondi');
                sleep($sleep_time);
            }
            $this->request_count = 0;
        }
        
        $this->last_request_time = $current_time;
    }
    
    /**
     * Esegue una richiesta API con retry automatico
     *
     * @param string $token Token di accesso
     * @param string $endpoint Endpoint API
     * @param array $params Parametri query
     * @return array|null Risposta decodificata
     */
    private function make_api_request($token, $endpoint, $params = array()) {
        $url = $this->base_url . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $attempt = 0;
        while ($attempt < $this->retry_max_attempts) {
            $attempt++;
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'User-Agent' => '747-Disco-CRM/11.4.2'
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                $this->log('[747Disco-Scan] Errore HTTP tentativo ' . $attempt . ': ' . $response->get_error_message());
                if ($attempt >= $this->retry_max_attempts) {
                    return null;
                }
                sleep($this->retry_delay_seconds);
                continue;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($http_code === 200) {
                return json_decode($body, true);
            } else if ($http_code === 429) {
                $this->log('[747Disco-Scan] Rate limit API, tentativo ' . $attempt);
                sleep($this->retry_delay_seconds * $attempt);
                continue;
            } else {
                $this->log('[747Disco-Scan] Errore API HTTP ' . $http_code . ': ' . $body);
                return null;
            }
        }
        
        return null;
    }
    
    /**
     * Lista nomi mesi supportati (per cartelle mese)
     *
     * @return array
     */
    private function get_month_names() {
        return array(
            'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
            'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre',
            'january', 'february', 'march', 'april', 'may', 'june',
            'july', 'august', 'september', 'october', 'november', 'december'
        );
    }
    
    // ============================================================================
    // METODI ESISTENTI DA MANTENERE (COMPATIBILITY)
    // ============================================================================
    
    /**
     * Ottiene le credenziali OAuth configurate
     *
     * @return array|null Credenziali OAuth
     */
    private function get_oauth_credentials() {
        $credentials = get_option('disco747_googledrive_oauth', array());
        
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            return null;
        }
        
        return $credentials;
    }
    
    /**
     * Ottiene un token di accesso valido
     *
     * @param array $credentials Credenziali OAuth
     * @return string Token di accesso
     * @throws \Exception Se non riesce ad ottenere il token
     */
    private function get_valid_access_token($credentials = null) {
        if (!$credentials) {
            $credentials = $this->get_oauth_credentials();
        }
        
        if (!$credentials) {
            throw new \Exception('Credenziali OAuth non configurate');
        }
        
        // Controlla se abbiamo un token valido in cache
        $cached_token = get_transient('disco747_gdrive_access_token');
        if ($cached_token) {
            return $cached_token;
        }
        
        // Refresh token se necessario
        if (empty($credentials['refresh_token'])) {
            throw new \Exception('Refresh token non disponibile');
        }
        
        $response = wp_remote_post($this->token_url, array(
            'body' => array(
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $credentials['refresh_token'],
                'grant_type' => 'refresh_token'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new \Exception('Errore refresh token: ' . $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['access_token'])) {
            throw new \Exception('Token non ottenuto');
        }
        
        // Cache token per 50 minuti (scade in 60)
        set_transient('disco747_gdrive_access_token', $body['access_token'], 3000);
        
        return $body['access_token'];
    }
    
    /**
     * Logging con prefisso identificativo
     *
     * @param string $message Messaggio da loggare
     * @param string $level Livello di log
     */
    private function log($message, $level = 'info') {
        if ($this->debug_mode && function_exists('error_log')) {
            $prefix = '[' . date('Y-m-d H:i:s') . '] ';
            error_log($prefix . $message);
        }
    }
}