<?php
/**
 * Classe per la gestione di Google Drive per 747 Disco CRM
 * VERSIONE CORRETTA - OAuth2 robusto + Upload completo
 *
 * @package    Disco747_CRM
 * @subpackage Storage
 * @since      11.7.0
 * @version    11.7.0-UPLOAD-FIXED
 */

namespace Disco747_CRM\Storage;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Custom Exception per Storage
 */
class StorageException extends \RuntimeException {}

/**
 * Classe Disco747_GoogleDrive COMPLETA E CORRETTA
 * 
 * Gestisce OAuth2, upload, listing completo con paginazione
 * 
 * @since 11.7.0-UPLOAD-FIXED
 */
class Disco747_GoogleDrive {
    
    /**
     * Configurazione OAuth2
     */
    private $credentials_cache = null;
    private $access_token_cache = null;
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    
    /**
     * Logging
     */
    private $debug_mode = true;

    /**
     * Array mesi in italiano
     */
    private $mesi_italiani = [
        1 => 'GENNAIO', 2 => 'FEBBRAIO', 3 => 'MARZO', 4 => 'APRILE',
        5 => 'MAGGIO', 6 => 'GIUGNO', 7 => 'LUGLIO', 8 => 'AGOSTO',
        9 => 'SETTEMBRE', 10 => 'OTTOBRE', 11 => 'NOVEMBRE', 12 => 'DICEMBRE'
    ];

    /**
     * Costruttore
     */
    public function __construct() {
        $this->setup_oauth_config();
        $this->log('[GoogleDrive] Handler v11.7.0-UPLOAD-FIXED inizializzato');
    }

    /**
     * Logging unificato con prefisso plugin
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode && function_exists('error_log')) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[747Disco-CRM] [$timestamp] $message");
        }
    }

    /**
     * Setup configurazione OAuth con URL redirect fisso
     */
    private function setup_oauth_config() {
        $this->client_id = defined('DISCO747_GOOGLE_CLIENT_ID') ? 
            DISCO747_GOOGLE_CLIENT_ID : 
            get_option('disco747_google_client_id', '');
            
        $this->client_secret = defined('DISCO747_GOOGLE_CLIENT_SECRET') ? 
            DISCO747_GOOGLE_CLIENT_SECRET : 
            get_option('disco747_google_client_secret', '');
            
        // URL redirect FISSO per evitare problemi con get_site_url()
        $this->redirect_uri = 'https://747disco.it/wp-admin/admin.php?page=disco747-settings&action=google_callback';
        
        $this->log("OAuth redirect URI impostato FISSO su: " . $this->redirect_uri);
    }

    // ========================================================================
    // METODO MANCANTE - UPLOAD_TO_GOOGLEDRIVE
    // ========================================================================

    /**
     * ✅ METODO CORRETTO: Upload file su Google Drive con nome e data evento specifici
     * Questo metodo è richiesto dal StorageManager e Forms handler
     * 
     * @param string $local_file_path Percorso completo del file locale
     * @param string $remote_filename Nome del file da usare su Google Drive (già formattato)
     * @param string $data_evento Data evento in formato Y-m-d per organizzare le cartelle
     * @return mixed URL del file caricato o false in caso di errore
     */
    public function upload_to_googledrive($local_file_path, $remote_filename, $data_evento) {
        try {
            if (!file_exists($local_file_path)) {
                $this->log("File non trovato: {$local_file_path}", 'ERROR');
                return false;
            }
            
            $this->log("Upload file: {$remote_filename} con data evento: {$data_evento}");
            
            // Prepara i dati nel formato richiesto dal metodo upload_file esistente
            $preventivo_data = array(
                'data_evento' => $data_evento,
                'nome_file' => $remote_filename
            );
            
            // Chiama il metodo esistente upload_file
            $result = $this->upload_file($local_file_path, $preventivo_data);
            
            if ($result && isset($result['success']) && $result['success']) {
                $this->log("Upload completato con successo: {$remote_filename}");
                return $result['view_url'] ?? $result['webViewLink'] ?? true;
            } else {
                $this->log("Upload fallito per: {$remote_filename}", 'WARNING');
                return false;
            }
            
        } catch (\Exception $e) {
            $this->log("Errore upload_to_googledrive: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    // ========================================================================
    // GESTIONE OAUTH2 ROBUSTA
    // ========================================================================

    /**
     * Ottiene credenziali OAuth consolidate con cache
     */
    public function get_oauth_credentials() {
        if ($this->credentials_cache !== null) {
            return $this->credentials_cache;
        }

        // Consolida credenziali da varie fonti
        $gd_credentials = get_option('disco747_gd_credentials', array());
        
        $this->credentials_cache = array(
            'client_id'     => $this->client_id ?: ($gd_credentials['client_id'] ?? ''),
            'client_secret' => $this->client_secret ?: ($gd_credentials['client_secret'] ?? ''),
            'redirect_uri'  => $this->redirect_uri,
            'refresh_token' => $gd_credentials['refresh_token'] ?? '',
            'folder_id'     => $gd_credentials['folder_id'] ?? ''
        );

        return $this->credentials_cache;
    }

    /**
     * Verifica se OAuth è configurato completamente
     */
    public function is_oauth_configured() {
        $credentials = $this->get_oauth_credentials();
        return !empty($credentials['client_id']) && 
               !empty($credentials['client_secret']) && 
               !empty($credentials['refresh_token']);
    }

    /**
     * Genera URL di autorizzazione OAuth2 con force consent per refresh_token
     */
    public function generate_auth_url() {
        $credentials = $this->get_oauth_credentials();
        
        if (empty($credentials['client_id']) || empty($credentials['redirect_uri'])) {
            return array(
                'success' => false, 
                'message' => 'Client ID o Redirect URI non configurati nelle impostazioni Google Drive.'
            );
        }

        $state = wp_create_nonce('googledrive_oauth_' . get_current_user_id());
        
        $params = array(
            'client_id'     => $credentials['client_id'],
            'redirect_uri'  => $credentials['redirect_uri'],
            'scope'         => 'https://www.googleapis.com/auth/drive',
            'response_type' => 'code',
            'access_type'   => 'offline',
            'prompt'        => 'consent',  // FONDAMENTALE per ottenere refresh_token
            'state'         => $state
        );

        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        
        $this->log('URL autorizzazione Google generato con force consent');
        
        return array(
            'success' => true, 
            'auth_url' => $auth_url,
            'state' => $state
        );
    }

    /**
     * Ottiene access token valido, rinfrescandolo automaticamente se necessario
     */
    public function get_valid_access_token() {
        // Controlla cache in-memory prima
        if ($this->access_token_cache) {
            $expires = get_option('disco747_googledrive_token_expires', 0);
            if (time() < $expires - 300) { // 5 minuti di margine
                return $this->access_token_cache;
            }
        }
        
        // Controlla access token salvato
        $token = get_option('disco747_googledrive_access_token');
        $expires = get_option('disco747_googledrive_token_expires', 0);

        if ($token && time() < $expires - 300) { // 5 minuti di margine
            $this->access_token_cache = $token;
            return $token;
        }

        // Token scaduto o mancante, esegui refresh
        $this->log('Access token scaduto o mancante. Refresh in corso...');
        return $this->refresh_access_token();
    }

    /**
     * Refresh access token usando refresh_token
     */
    private function refresh_access_token() {
        $credentials = $this->get_oauth_credentials();
        
        if (empty($credentials['refresh_token'])) {
            throw new StorageException('Refresh token non disponibile. Riautorizza Google Drive.');
        }

        $this->log('Refresh access token in corso...');
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id'     => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $credentials['refresh_token'],
                'grant_type'    => 'refresh_token'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            throw new StorageException('Errore refresh token: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code !== 200 || !isset($body['access_token'])) {
            $error = $body['error_description'] ?? 'Errore sconosciuto';
            throw new StorageException("Errore refresh token Google: {$error}");
        }

        // Salva nuovo access token
        $this->save_access_token($body);
        
        $this->log('✅ Access token Google Drive refreshed con successo');
        
        return $body['access_token'];
    }

    /**
     * Salva access token e scadenza
     */
    private function save_access_token($token_data) {
        $access_token = $token_data['access_token'];
        $expires_in = $token_data['expires_in'] ?? 3600;
        
        update_option('disco747_googledrive_access_token', $access_token);
        update_option('disco747_googledrive_token_expires', time() + $expires_in);
        
        $this->access_token_cache = $access_token;
        
        $this->log("Access token salvato (scade in {$expires_in} secondi)");
    }

    // ========================================================================
    // UPLOAD E GESTIONE CARTELLE
    // ========================================================================

    /**
     * Upload file su Google Drive con struttura cartelle anno/mese
     */
    public function upload_file($file_path, $preventivo_data) {
        try {
            if (!file_exists($file_path)) {
                throw new StorageException("File non trovato: $file_path");
            }
            
            $token = $this->get_valid_access_token();
            
            // Determina cartella di destinazione
            $anno = date('Y', strtotime($preventivo_data['data_evento'] ?? 'now'));
            $mese_num = date('n', strtotime($preventivo_data['data_evento'] ?? 'now'));
            $mese_nome = $this->mesi_italiani[$mese_num] ?? 'SCONOSCIUTO';
            
            // Trova o crea struttura cartelle: /747-Preventivi/ANNO/MESE/
            $main_folder_id = $this->find_or_create_folder('747-Preventivi');
            $year_folder_id = $this->find_or_create_folder($anno, $main_folder_id);
            $month_folder_id = $this->find_or_create_folder($mese_nome, $year_folder_id);
            
            // Nome file
            $file_name = isset($preventivo_data['nome_file']) ? 
                        $preventivo_data['nome_file'] : 
                        $this->generate_file_name($preventivo_data);
            
            // Upload tramite multipart
            $boundary = wp_generate_uuid4();
            $file_content = file_get_contents($file_path);
            $mime_type = wp_check_filetype($file_path)['type'] ?: 'application/octet-stream';
            
            $metadata = json_encode(array(
                'name' => $file_name,
                'parents' => array($month_folder_id)
            ));
            
            $body = "--$boundary\r\n" .
                    "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
                    "$metadata\r\n" .
                    "--$boundary\r\n" .
                    "Content-Type: $mime_type\r\n\r\n" .
                    "$file_content\r\n" .
                    "--$boundary--";
            
            $response = wp_remote_post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => "multipart/related; boundary=\"$boundary\""
                ),
                'body' => $body,
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                throw new StorageException('Errore upload: ' . $response->get_error_message());
            }
            
            $upload_data = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code !== 200) {
                $error_msg = $upload_data['error']['message'] ?? "HTTP $http_code";
                throw new StorageException("Errore API upload: $error_msg");
            }
            
            $file_id = $upload_data['id'];
            $view_url = "https://drive.google.com/file/d/$file_id/view";
            
            $this->log("✅ File '$file_name' caricato con successo (ID: $file_id)");
            
            return array(
                'success' => true,
                'file_id' => $file_id,
                'file_name' => $file_name,
                'view_url' => $view_url,
                'webViewLink' => $view_url,
                'folder_path' => "/747-Preventivi/$anno/$mese_nome/"
            );
            
        } catch (\Exception $e) {
            $this->log('Errore upload file: ' . $e->getMessage(), 'ERROR');
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Trova o crea una cartella su Google Drive
     */
    private function find_or_create_folder($folder_name, $parent_id = null) {
        try {
            $token = $this->get_valid_access_token();
            
            // Cerca cartella esistente
            $query = "name='{$folder_name}' and mimeType='application/vnd.google-apps.folder' and trashed=false";
            if ($parent_id) {
                $query .= " and '{$parent_id}' in parents";
            }
            
            $search_url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id,name)',
                'pageSize' => 1
            ));
            
            $response = wp_remote_get($search_url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data['files'])) {
                    $folder_id = $data['files'][0]['id'];
                    $this->log("Cartella '{$folder_name}' trovata: {$folder_id}");
                    return $folder_id;
                }
            }
            
            // Crea nuova cartella
            $metadata = array(
                'name' => $folder_name,
                'mimeType' => 'application/vnd.google-apps.folder'
            );
            
            if ($parent_id) {
                $metadata['parents'] = array($parent_id);
            }
            
            $response = wp_remote_post('https://www.googleapis.com/drive/v3/files', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($metadata),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new StorageException('Errore creazione cartella: ' . $response->get_error_message());
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code !== 200 || !isset($data['id'])) {
                throw new StorageException('Creazione cartella fallita');
            }
            
            $folder_id = $data['id'];
            $this->log("Cartella '{$folder_name}' creata: {$folder_id}");
            
            return $folder_id;
            
        } catch (\Exception $e) {
            $this->log('Errore gestione cartella: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Genera nome file basato sui dati preventivo
     */
    private function generate_file_name($preventivo_data) {
        $data_evento = $preventivo_data['data_evento'] ?? date('Y-m-d');
        $tipo_evento = $preventivo_data['tipo_evento'] ?? 'Evento';
        $tipo_menu = $preventivo_data['tipo_menu'] ?? 'Menu 7';
        $acconto = floatval($preventivo_data['acconto'] ?? 0);
        
        // Data formato italiano
        $data_ita = date('d_m', strtotime($data_evento));
        
        // Prefisso se confermato (con acconto)
        $prefisso = $acconto > 0 ? 'CONF ' : '';
        
        // Sanitizza nome evento
        $evento_clean = preg_replace('/[^\w\s-]/', '', $tipo_evento);
        $evento_clean = preg_replace('/\s+/', ' ', trim($evento_clean));
        
        return "{$prefisso}{$data_ita} {$evento_clean} ({$tipo_menu}).xlsx";
    }

    // ========================================================================
    // TEST CONNESSIONE
    // ========================================================================

    /**
     * Test connessione all'API Google Drive con info account
     */
    public function test_oauth_connection() {
        try {
            $token = $this->get_valid_access_token();
            
            $response = wp_remote_get('https://www.googleapis.com/drive/v3/about?fields=user,storageQuota', array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 15
            ));
            
            if (is_wp_error($response)) {
                $error_msg = 'Errore di connessione al test: ' . $response->get_error_message();
                $this->log($error_msg, 'ERROR');
                return array('success' => false, 'message' => $error_msg);
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code === 200 && isset($body['user'])) {
                $user = $body['user'];
                $quota = $body['storageQuota'] ?? array();
                
                $used_gb = isset($quota['usage']) ? round($quota['usage'] / (1024*1024*1024), 2) : 'N/D';
                $limit_gb = isset($quota['limit']) ? round($quota['limit'] / (1024*1024*1024), 2) : 'Illimitato';
                
                $this->log('✅ Test connessione Google Drive riuscito per: ' . ($user['emailAddress'] ?? 'N/D'));
                
                return array(
                    'success' => true,
                    'message' => 'Connesso a Google Drive con successo!',
                    'user_name' => $user['displayName'] ?? 'N/D',
                    'user_email' => $user['emailAddress'] ?? 'N/D',
                    'storage_used' => $used_gb,
                    'storage_limit' => $limit_gb
                );
            } else {
                $error_details = $body['error']['message'] ?? 'Errore API sconosciuto';
                $error_msg = "Errore API Google Drive: $error_details";
                $this->log($error_msg, 'ERROR');
                return array('success' => false, 'message' => $error_msg);
            }
            
        } catch (\Exception $e) {
            $this->log('Errore durante il test connessione: ' . $e->getMessage(), 'ERROR');
            return array('success' => false, 'message' => 'Errore durante il test: ' . $e->getMessage());
        }
    }

    /**
     * Verifica se Google Drive è configurato
     */
    public function is_configured() {
        return $this->is_oauth_configured();
    }

    /**
     * Destructor per cleanup
     */
    public function __destruct() {
        if ($this->debug_mode) {
            $this->log('Google Drive Handler terminato');
        }
    }
}