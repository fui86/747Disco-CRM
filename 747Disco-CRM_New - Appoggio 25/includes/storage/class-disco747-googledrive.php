<?php
/**
 * Google Drive Storage Handler
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @version    11.7.0-SERVICE-FIXED
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Drive_Permission;

class Disco747_GoogleDrive {
    
    private $client;
    private $service;
    private $config;
    private $credentials_option = 'disco747_googledrive_credentials';
    private $token_option = 'disco747_googledrive_token';
    
    /**
     * Costruttore
     */
    public function __construct($config = null) {
        $this->config = $config;
        $this->initialize();
    }
    
    /**
     * Inizializzazione
     */
    private function initialize() {
        error_log('[747Disco-CRM] [' . date('Y-m-d H:i:s') . '] [GoogleDrive] Handler v11.7.0-SERVICE-FIXED inizializzato');
        
        // Carica libreria Google se necessario
        $this->load_google_library();
        
        // Inizializza client
        $this->setup_client();
        
        // Se abbiamo un token salvato, prova ad autenticarsi
        $saved_token = get_option($this->token_option);
        if ($saved_token) {
            try {
                $this->client->setAccessToken($saved_token);
                
                // Verifica se il token è scaduto
                if ($this->client->isAccessTokenExpired()) {
                    // Prova a rinnovare
                    if ($this->client->getRefreshToken()) {
                        $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                        $new_token = $this->client->getAccessToken();
                        update_option($this->token_option, $new_token);
                    }
                }
                
                // Crea servizio Drive
                $this->service = new Google_Service_Drive($this->client);
                
            } catch (\Exception $e) {
                error_log('[747Disco-CRM] [GoogleDrive] Errore inizializzazione servizio: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Carica libreria Google
     */
    private function load_google_library() {
        $vendor_autoload = DISCO747_CRM_PLUGIN_DIR . 'vendor/autoload.php';
        
        if (!class_exists('Google_Client')) {
            if (file_exists($vendor_autoload)) {
                require_once $vendor_autoload;
            } else {
                error_log('[747Disco-CRM] [GoogleDrive] Libreria Google non trovata. Installa con: composer require google/apiclient:^2.0');
            }
        }
    }
    
    /**
     * Setup Google Client
     */
    private function setup_client() {
        if (!class_exists('Google_Client')) {
            error_log('[747Disco-CRM] [GoogleDrive] Google Client class non disponibile');
            return false;
        }
        
        $this->client = new Google_Client();
        
        // Configura client
        $this->client->setApplicationName('747 Disco CRM');
        $this->client->setScopes([
            Google_Service_Drive::DRIVE,
            Google_Service_Drive::DRIVE_FILE,
            Google_Service_Drive::DRIVE_METADATA
        ]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        
        // Imposta redirect URI fisso
        $redirect_uri = 'https://747disco.it/wp-admin/admin.php?page=disco747-settings&action=google_callback';
        $this->client->setRedirectUri($redirect_uri);
        
        error_log('[747Disco-CRM] [' . date('Y-m-d H:i:s') . '] OAuth redirect URI impostato FISSO su: ' . $redirect_uri);
        
        // Carica credenziali salvate
        $credentials = get_option($this->credentials_option);
        if ($credentials && is_array($credentials)) {
            if (!empty($credentials['client_id']) && !empty($credentials['client_secret'])) {
                $this->client->setClientId($credentials['client_id']);
                $this->client->setClientSecret($credentials['client_secret']);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * METODO AGGIUNTO: Get Google Drive service
     * Restituisce l'oggetto Google_Service_Drive
     */
    public function get_service() {
        if (!$this->service && $this->client && $this->client->getAccessToken()) {
            $this->service = new Google_Service_Drive($this->client);
        }
        return $this->service;
    }
    
    /**
     * Get Google Client
     */
    public function get_client() {
        return $this->client;
    }
    
    /**
     * Verifica se è configurato
     */
    public function is_configured() {
        $credentials = get_option($this->credentials_option);
        $token = get_option($this->token_option);
        
        return !empty($credentials) && 
               !empty($credentials['client_id']) && 
               !empty($credentials['client_secret']) && 
               !empty($token);
    }
    
    /**
     * Get Auth URL
     */
    public function get_auth_url() {
        if (!$this->client) {
            return '';
        }
        
        // Assicura che le credenziali siano caricate
        $credentials = get_option($this->credentials_option);
        if ($credentials && !empty($credentials['client_id']) && !empty($credentials['client_secret'])) {
            $this->client->setClientId($credentials['client_id']);
            $this->client->setClientSecret($credentials['client_secret']);
        } else {
            return '';
        }
        
        return $this->client->createAuthUrl();
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_callback($code) {
        if (!$this->client || !$code) {
            return false;
        }
        
        try {
            // Scambia codice per token
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                throw new \Exception($token['error_description'] ?? 'Errore sconosciuto');
            }
            
            // Salva token
            update_option($this->token_option, $token);
            
            // Imposta token nel client
            $this->client->setAccessToken($token);
            
            // Crea servizio
            $this->service = new Google_Service_Drive($this->client);
            
            return true;
            
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [GoogleDrive] Errore callback OAuth: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload file
     */
    public function upload_file($file_path, $folder_path = '', $options = array()) {
        if (!$this->service) {
            error_log('[747Disco-CRM] [GoogleDrive] Servizio non disponibile per upload');
            return false;
        }
        
        if (!file_exists($file_path)) {
            error_log('[747Disco-CRM] [GoogleDrive] File non trovato: ' . $file_path);
            return false;
        }
        
        try {
            // Prepara metadata file
            $file = new Google_Service_Drive_DriveFile();
            $file->setName(basename($file_path));
            
            // Se specificata cartella, trova o crea
            if ($folder_path) {
                $folder_id = $this->ensure_folder_path($folder_path);
                if ($folder_id) {
                    $file->setParents(array($folder_id));
                }
            }
            
            // Determina MIME type
            $mime_type = mime_content_type($file_path);
            
            // Upload
            $result = $this->service->files->create(
                $file,
                array(
                    'data' => file_get_contents($file_path),
                    'mimeType' => $mime_type,
                    'uploadType' => 'multipart',
                    'fields' => 'id,name,webViewLink,webContentLink'
                )
            );
            
            error_log('[747Disco-CRM] [GoogleDrive] File caricato con successo. ID: ' . $result->id);
            
            return array(
                'id' => $result->id,
                'name' => $result->name,
                'url' => $result->webViewLink,
                'download_url' => $result->webContentLink
            );
            
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [GoogleDrive] Errore upload: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ensure folder path exists
     */
    private function ensure_folder_path($path) {
        if (!$this->service) {
            return null;
        }
        
        // Rimuovi slash iniziali e finali
        $path = trim($path, '/');
        
        // Dividi il percorso
        $parts = explode('/', $path);
        $parent_id = 'root';
        
        foreach ($parts as $folder_name) {
            if (empty($folder_name)) continue;
            
            // Cerca la cartella
            $query = sprintf(
                "name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                str_replace("'", "\\'", $folder_name),
                $parent_id
            );
            
            try {
                $response = $this->service->files->listFiles(array(
                    'q' => $query,
                    'spaces' => 'drive',
                    'fields' => 'files(id, name)',
                    'pageSize' => 1
                ));
                
                if (count($response->files) > 0) {
                    // Cartella esiste
                    $parent_id = $response->files[0]->id;
                } else {
                    // Crea cartella
                    $folder_metadata = new Google_Service_Drive_DriveFile();
                    $folder_metadata->setName($folder_name);
                    $folder_metadata->setMimeType('application/vnd.google-apps.folder');
                    
                    if ($parent_id !== 'root') {
                        $folder_metadata->setParents(array($parent_id));
                    }
                    
                    $folder = $this->service->files->create($folder_metadata, array(
                        'fields' => 'id'
                    ));
                    
                    $parent_id = $folder->id;
                    
                    error_log('[747Disco-CRM] [GoogleDrive] Cartella creata: ' . $folder_name . ' (ID: ' . $parent_id . ')');
                }
                
            } catch (\Exception $e) {
                error_log('[747Disco-CRM] [GoogleDrive] Errore gestione cartella: ' . $e->getMessage());
                return null;
            }
        }
        
        return $parent_id;
    }
    
    /**
     * Download file
     */
    public function download_file($file_id, $destination_path = null) {
        if (!$this->service) {
            return false;
        }
        
        try {
            // Get file content
            $response = $this->service->files->get($file_id, array('alt' => 'media'));
            $content = $response->getBody()->getContents();
            
            if ($destination_path) {
                // Save to file
                file_put_contents($destination_path, $content);
                return $destination_path;
            } else {
                // Return content
                return $content;
            }
            
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [GoogleDrive] Errore download: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete file
     */
    public function delete_file($file_id) {
        if (!$this->service) {
            return false;
        }
        
        try {
            $this->service->files->delete($file_id);
            return true;
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [GoogleDrive] Errore eliminazione: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * List files
     */
    public function list_files($folder_path = '') {
        if (!$this->service) {
            return array();
        }
        
        try {
            $query = "trashed = false";
            
            // Se specificata cartella
            if ($folder_path) {
                $folder_id = $this->get_folder_id($folder_path);
                if ($folder_id) {
                    $query .= " and '{$folder_id}' in parents";
                }
            }
            
            $files = array();
            $pageToken = null;
            
            do {
                $response = $this->service->files->listFiles(array(
                    'q' => $query,
                    'spaces' => 'drive',
                    'fields' => 'nextPageToken, files(id, name, mimeType, size, modifiedTime, webViewLink)',
                    'pageToken' => $pageToken
                ));
                
                foreach ($response->files as $file) {
                    $files[] = array(
                        'id' => $file->id,
                        'name' => $file->name,
                        'mime_type' => $file->mimeType,
                        'size' => $file->size,
                        'modified' => $file->modifiedTime,
                        'url' => $file->webViewLink
                    );
                }
                
                $pageToken = $response->nextPageToken;
                
            } while ($pageToken);
            
            return $files;
            
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [GoogleDrive] Errore listing: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get folder ID by path
     */
    private function get_folder_id($path) {
        if (!$this->service) {
            return null;
        }
        
        $path = trim($path, '/');
        $parts = explode('/', $path);
        $parent_id = 'root';
        
        foreach ($parts as $folder_name) {
            if (empty($folder_name)) continue;
            
            $query = sprintf(
                "name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                str_replace("'", "\\'", $folder_name),
                $parent_id
            );
            
            try {
                $response = $this->service->files->listFiles(array(
                    'q' => $query,
                    'spaces' => 'drive',
                    'fields' => 'files(id)',
                    'pageSize' => 1
                ));
                
                if (count($response->files) > 0) {
                    $parent_id = $response->files[0]->id;
                } else {
                    return null; // Cartella non trovata
                }
                
            } catch (\Exception $e) {
                return null;
            }
        }
        
        return $parent_id;
    }
    
    /**
     * Create folder
     */
    public function create_folder($folder_name, $parent_id = null) {
        if (!$this->service) {
            return false;
        }
        
        try {
            $folder_metadata = new Google_Service_Drive_DriveFile();
            $folder_metadata->setName($folder_name);
            $folder_metadata->setMimeType('application/vnd.google-apps.folder');
            
            if ($parent_id) {
                $folder_metadata->setParents(array($parent_id));
            }
            
            $folder = $this->service->files->create($folder_metadata, array(
                'fields' => 'id, webViewLink'
            ));
            
            return array(
                'id' => $folder->id,
                'url' => $folder->webViewLink
            );
            
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [GoogleDrive] Errore creazione cartella: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get file URL
     */
    public function get_file_url($file_id) {
        if (!$this->service) {
            return '';
        }
        
        try {
            $file = $this->service->files->get($file_id, array(
                'fields' => 'webViewLink'
            ));
            return $file->webViewLink;
        } catch (\Exception $e) {
            return '';
        }
    }
    
    /**
     * Get file info
     */
    public function get_file_info($file_id) {
        if (!$this->service) {
            return null;
        }
        
        try {
            $file = $this->service->files->get($file_id, array(
                'fields' => 'id, name, mimeType, size, modifiedTime, webViewLink, webContentLink'
            ));
            
            return array(
                'id' => $file->id,
                'name' => $file->name,
                'mime_type' => $file->mimeType,
                'size' => $file->size,
                'modified' => $file->modifiedTime,
                'url' => $file->webViewLink,
                'download_url' => $file->webContentLink
            );
            
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [GoogleDrive] Errore info file: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Search files
     */
    public function search_files($query, $options = array()) {
        if (!$this->service) {
            return array();
        }
        
        try {
            $search_query = "fullText contains '{$query}' and trashed = false";
            
            if (!empty($options['mime_type'])) {
                $search_query .= " and mimeType = '{$options['mime_type']}'";
            }
            
            if (!empty($options['folder_id'])) {
                $search_query .= " and '{$options['folder_id']}' in parents";
            }
            
            $files = array();
            $pageToken = null;
            
            do {
                $response = $this->service->files->listFiles(array(
                    'q' => $search_query,
                    'spaces' => 'drive',
                    'fields' => 'nextPageToken, files(id, name, mimeType, size, modifiedTime, webViewLink)',
                    'pageToken' => $pageToken,
                    'pageSize' => $options['limit'] ?? 100
                ));
                
                foreach ($response->files as $file) {
                    $files[] = array(
                        'id' => $file->id,
                        'name' => $file->name,
                        'mime_type' => $file->mimeType,
                        'size' => $file->size,
                        'modified' => $file->modifiedTime,
                        'url' => $file->webViewLink
                    );
                }
                
                $pageToken = $response->nextPageToken;
                
                // Limita se specificato
                if (!empty($options['limit']) && count($files) >= $options['limit']) {
                    break;
                }
                
            } while ($pageToken);
            
            return $files;
            
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [GoogleDrive] Errore ricerca: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Test connection
     */
    public function test_connection() {
        $result = array(
            'success' => false,
            'message' => ''
        );
        
        if (!$this->service) {
            $result['message'] = 'Servizio Google Drive non inizializzato';
            return $result;
        }
        
        try {
            // Prova a ottenere informazioni about
            $about = $this->service->about->get(array(
                'fields' => 'user(displayName, emailAddress), storageQuota'
            ));
            
            $result['success'] = true;
            $result['message'] = sprintf(
                'Connesso come: %s (%s)',
                $about->user->displayName,
                $about->user->emailAddress
            );
            
            // Aggiungi info quota se disponibile
            if ($about->storageQuota) {
                $used = $about->storageQuota->usage ?? 0;
                $total = $about->storageQuota->limit ?? 0;
                
                if ($total > 0) {
                    $percent = round(($used / $total) * 100, 1);
                    $result['quota'] = array(
                        'used' => $this->format_bytes($used),
                        'total' => $this->format_bytes($total),
                        'percent' => $percent
                    );
                }
            }
            
        } catch (\Exception $e) {
            $result['message'] = 'Errore connessione: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Revoke access
     */
    public function revoke_access() {
        try {
            if ($this->client && $this->client->getAccessToken()) {
                $this->client->revokeToken();
            }
            
            // Elimina token salvato
            delete_option($this->token_option);
            
            // Reset service
            $this->service = null;
            
            return true;
            
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [GoogleDrive] Errore revoca accesso: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get quota info
     */
    public function get_quota_info() {
        if (!$this->service) {
            return array(
                'used' => 0,
                'allocated' => 0,
                'percent' => 0
            );
        }
        
        try {
            $about = $this->service->about->get(array(
                'fields' => 'storageQuota'
            ));
            
            $used = $about->storageQuota->usage ?? 0;
            $total = $about->storageQuota->limit ?? 0;
            
            return array(
                'used' => $used,
                'allocated' => $total,
                'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0
            );
            
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [GoogleDrive] Errore quota: ' . $e->getMessage());
            return array(
                'used' => 0,
                'allocated' => 0,
                'percent' => 0
            );
        }
    }
    
    /**
     * Format bytes
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}