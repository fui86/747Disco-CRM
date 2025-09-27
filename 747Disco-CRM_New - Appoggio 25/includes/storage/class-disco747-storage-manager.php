<?php
/**
 * Storage Manager per gestione unificata storage providers
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @version    11.6.1-FIXED
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Storage_Manager {
    
    private $config;
    private $active_handler = null;
    private $googledrive_handler = null;
    private $dropbox_handler = null;
    private $storage_type = 'googledrive'; // Default
    
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
        // Determina il tipo di storage dalle impostazioni
        $this->storage_type = get_option('disco747_storage_type', 'googledrive');
        
        // Inizializza l'handler appropriato
        switch ($this->storage_type) {
            case 'googledrive':
                $this->initialize_googledrive();
                break;
                
            case 'dropbox':
                $this->initialize_dropbox();
                break;
                
            case 'both':
                $this->initialize_googledrive();
                $this->initialize_dropbox();
                break;
        }
        
        error_log('[747Disco-CRM] [StorageManager] Inizializzato con tipo: ' . $this->storage_type);
    }
    
    /**
     * Inizializza Google Drive handler
     */
    private function initialize_googledrive() {
        try {
            if (!class_exists('Disco747_CRM\Storage\Disco747_GoogleDrive')) {
                require_once DISCO747_CRM_PLUGIN_DIR . 'includes/storage/class-disco747-googledrive.php';
            }
            
            $this->googledrive_handler = new Disco747_GoogleDrive($this->config);
            
            // Imposta come handler attivo se è il tipo selezionato
            if ($this->storage_type === 'googledrive') {
                $this->active_handler = $this->googledrive_handler;
            }
            
            error_log('[747Disco-CRM] [StorageManager] Google Drive handler inizializzato');
            
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [StorageManager] Errore inizializzazione Google Drive: ' . $e->getMessage());
        }
    }
    
    /**
     * Inizializza Dropbox handler
     */
    private function initialize_dropbox() {
        try {
            if (!class_exists('Disco747_CRM\Storage\Disco747_Dropbox')) {
                require_once DISCO747_CRM_PLUGIN_DIR . 'includes/storage/class-disco747-dropbox.php';
            }
            
            $this->dropbox_handler = new Disco747_Dropbox($this->config);
            
            // Imposta come handler attivo se è il tipo selezionato
            if ($this->storage_type === 'dropbox') {
                $this->active_handler = $this->dropbox_handler;
            }
            
            error_log('[747Disco-CRM] [StorageManager] Dropbox handler inizializzato');
            
        } catch (\Exception $e) {
            error_log('[747Disco-CRM] [StorageManager] Errore inizializzazione Dropbox: ' . $e->getMessage());
        }
    }
    
    /**
     * METODO AGGIUNTO: Get Google Drive service
     * Restituisce il servizio Google Drive dal handler
     */
    public function get_drive_service() {
        if ($this->googledrive_handler) {
            // Assumiamo che il GoogleDrive handler abbia un metodo get_service()
            if (method_exists($this->googledrive_handler, 'get_service')) {
                return $this->googledrive_handler->get_service();
            }
            
            // Altrimenti prova ad accedere direttamente alla proprietà service
            if (property_exists($this->googledrive_handler, 'service')) {
                return $this->googledrive_handler->service;
            }
            
            // Ultimo tentativo: prova a ottenere il client e creare il servizio
            if (method_exists($this->googledrive_handler, 'get_client')) {
                $client = $this->googledrive_handler->get_client();
                if ($client && $client->getAccessToken()) {
                    return new \Google_Service_Drive($client);
                }
            }
        }
        
        error_log('[747Disco-CRM] [StorageManager] Drive service non disponibile');
        return null;
    }
    
    /**
     * Verifica se lo storage è configurato
     */
    public function is_configured() {
        if ($this->active_handler && method_exists($this->active_handler, 'is_configured')) {
            return $this->active_handler->is_configured();
        }
        return false;
    }
    
    /**
     * Upload file
     */
    public function upload_file($file_path, $folder_path = '', $options = array()) {
        $results = array();
        
        // Se è attivo "both", carica su entrambi
        if ($this->storage_type === 'both') {
            if ($this->googledrive_handler) {
                $results['googledrive'] = $this->googledrive_handler->upload_file($file_path, $folder_path, $options);
            }
            if ($this->dropbox_handler) {
                $results['dropbox'] = $this->dropbox_handler->upload_file($file_path, $folder_path, $options);
            }
            return $results;
        }
        
        // Altrimenti usa l'handler attivo
        if ($this->active_handler) {
            return $this->active_handler->upload_file($file_path, $folder_path, $options);
        }
        
        return false;
    }
    
    /**
     * Download file
     */
    public function download_file($file_id, $destination_path = null) {
        if ($this->active_handler) {
            return $this->active_handler->download_file($file_id, $destination_path);
        }
        return false;
    }
    
    /**
     * Delete file
     */
    public function delete_file($file_id) {
        if ($this->active_handler) {
            return $this->active_handler->delete_file($file_id);
        }
        return false;
    }
    
    /**
     * List files
     */
    public function list_files($folder_path = '') {
        if ($this->active_handler) {
            return $this->active_handler->list_files($folder_path);
        }
        return array();
    }
    
    /**
     * Create folder
     */
    public function create_folder($folder_path, $parent_id = null) {
        $results = array();
        
        // Se è attivo "both", crea su entrambi
        if ($this->storage_type === 'both') {
            if ($this->googledrive_handler) {
                $results['googledrive'] = $this->googledrive_handler->create_folder($folder_path, $parent_id);
            }
            if ($this->dropbox_handler) {
                $results['dropbox'] = $this->dropbox_handler->create_folder($folder_path, $parent_id);
            }
            return $results;
        }
        
        // Altrimenti usa l'handler attivo
        if ($this->active_handler) {
            return $this->active_handler->create_folder($folder_path, $parent_id);
        }
        
        return false;
    }
    
    /**
     * Get file URL
     */
    public function get_file_url($file_id) {
        if ($this->active_handler) {
            return $this->active_handler->get_file_url($file_id);
        }
        return '';
    }
    
    /**
     * Get file info
     */
    public function get_file_info($file_id) {
        if ($this->active_handler) {
            return $this->active_handler->get_file_info($file_id);
        }
        return null;
    }
    
    /**
     * Search files
     */
    public function search_files($query, $options = array()) {
        if ($this->active_handler) {
            return $this->active_handler->search_files($query, $options);
        }
        return array();
    }
    
    /**
     * Get storage type
     */
    public function get_storage_type() {
        return $this->storage_type;
    }
    
    /**
     * Set storage type
     */
    public function set_storage_type($type) {
        if (in_array($type, array('googledrive', 'dropbox', 'both'))) {
            $this->storage_type = $type;
            update_option('disco747_storage_type', $type);
            
            // Re-inizializza
            $this->initialize();
            
            return true;
        }
        return false;
    }
    
    /**
     * Get Google Drive handler
     */
    public function get_googledrive_handler() {
        return $this->googledrive_handler;
    }
    
    /**
     * Get Dropbox handler
     */
    public function get_dropbox_handler() {
        return $this->dropbox_handler;
    }
    
    /**
     * Get active handler
     */
    public function get_active_handler() {
        return $this->active_handler;
    }
    
    /**
     * Test connection
     */
    public function test_connection() {
        $results = array(
            'success' => false,
            'message' => '',
            'details' => array()
        );
        
        if ($this->storage_type === 'both') {
            // Test entrambi
            if ($this->googledrive_handler) {
                $results['details']['googledrive'] = $this->test_handler($this->googledrive_handler, 'Google Drive');
            }
            if ($this->dropbox_handler) {
                $results['details']['dropbox'] = $this->test_handler($this->dropbox_handler, 'Dropbox');
            }
            
            // Se almeno uno funziona, consideriamo successo
            $results['success'] = (!empty($results['details']['googledrive']['success']) || 
                                  !empty($results['details']['dropbox']['success']));
                                  
            if ($results['success']) {
                $results['message'] = 'Almeno un servizio di storage è connesso';
            } else {
                $results['message'] = 'Nessun servizio di storage disponibile';
            }
            
        } else {
            // Test singolo handler
            if ($this->active_handler) {
                $service_name = $this->storage_type === 'googledrive' ? 'Google Drive' : 'Dropbox';
                $test = $this->test_handler($this->active_handler, $service_name);
                $results = array_merge($results, $test);
            } else {
                $results['message'] = 'Nessun handler attivo';
            }
        }
        
        return $results;
    }
    
    /**
     * Test singolo handler
     */
    private function test_handler($handler, $service_name) {
        $result = array(
            'success' => false,
            'message' => '',
            'service' => $service_name
        );
        
        try {
            if (method_exists($handler, 'test_connection')) {
                $test = $handler->test_connection();
                $result['success'] = $test['success'] ?? false;
                $result['message'] = $test['message'] ?? 'Test completato';
            } else {
                // Fallback: prova a listare i file nella root
                $files = $handler->list_files('/');
                $result['success'] = is_array($files);
                $result['message'] = $result['success'] ? 
                    "Connesso - {$service_name} funzionante" : 
                    "Impossibile connettersi a {$service_name}";
            }
        } catch (\Exception $e) {
            $result['message'] = "Errore {$service_name}: " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Get authentication URL for OAuth
     */
    public function get_auth_url($service = null) {
        if (!$service) {
            $service = $this->storage_type;
        }
        
        switch ($service) {
            case 'googledrive':
                if ($this->googledrive_handler && method_exists($this->googledrive_handler, 'get_auth_url')) {
                    return $this->googledrive_handler->get_auth_url();
                }
                break;
                
            case 'dropbox':
                if ($this->dropbox_handler && method_exists($this->dropbox_handler, 'get_auth_url')) {
                    return $this->dropbox_handler->get_auth_url();
                }
                break;
        }
        
        return '';
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_callback($code, $service = null) {
        if (!$service) {
            $service = $this->storage_type;
        }
        
        switch ($service) {
            case 'googledrive':
                if ($this->googledrive_handler && method_exists($this->googledrive_handler, 'handle_callback')) {
                    return $this->googledrive_handler->handle_callback($code);
                }
                break;
                
            case 'dropbox':
                if ($this->dropbox_handler && method_exists($this->dropbox_handler, 'handle_callback')) {
                    return $this->dropbox_handler->handle_callback($code);
                }
                break;
        }
        
        return false;
    }
    
    /**
     * Revoke access
     */
    public function revoke_access($service = null) {
        if (!$service) {
            $service = $this->storage_type;
        }
        
        $results = array();
        
        if ($service === 'both' || $service === 'googledrive') {
            if ($this->googledrive_handler && method_exists($this->googledrive_handler, 'revoke_access')) {
                $results['googledrive'] = $this->googledrive_handler->revoke_access();
            }
        }
        
        if ($service === 'both' || $service === 'dropbox') {
            if ($this->dropbox_handler && method_exists($this->dropbox_handler, 'revoke_access')) {
                $results['dropbox'] = $this->dropbox_handler->revoke_access();
            }
        }
        
        return count($results) === 1 ? array_values($results)[0] : $results;
    }
    
    /**
     * Get quota/usage info
     */
    public function get_quota_info() {
        if ($this->active_handler && method_exists($this->active_handler, 'get_quota_info')) {
            return $this->active_handler->get_quota_info();
        }
        
        return array(
            'used' => 0,
            'allocated' => 0,
            'percent' => 0
        );
    }
}