<?php
/**
 * Storage Manager - 747 Disco CRM
 * VERSIONE CORRETTA: Mantiene nome file originale
 * ✅ BUGFIX v11.7.1: Aggiunto metodo upload_to_googledrive con data_evento
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @version    11.7.1-BUGFIX
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Storage_Manager {
    
    private $storage_type;
    private $active_storage = null;
    private $debug_mode = true;
    
    public function __construct() {
        $this->storage_type = get_option('disco747_storage_type', 'googledrive');
        $this->initialize_storage();
        $this->log('[StorageManager] Inizializzato con tipo: ' . $this->storage_type);
    }
    
    private function log($message) {
        if ($this->debug_mode) {
            error_log("[747Disco-CRM] {$message}");
        }
    }
    
    private function initialize_storage() {
        try {
            if ($this->storage_type === 'googledrive') {
                if (!class_exists('\Disco747_CRM\Storage\Disco747_GoogleDrive')) {
                    throw new \RuntimeException('Classe GoogleDrive non trovata');
                }
                $this->active_storage = new Disco747_GoogleDrive();
                $this->log('[StorageManager] Google Drive handler inizializzato');
            } else {
                if (!class_exists('\Disco747_CRM\Storage\Disco747_Dropbox')) {
                    throw new \RuntimeException('Classe Dropbox non trovata');
                }
                $this->active_storage = new Disco747_Dropbox();
                $this->log('[StorageManager] Dropbox handler inizializzato');
            }
        } catch (\Throwable $e) {
            $this->log('[StorageManager] [ERROR] Errore inizializzazione: ' . $e->getMessage());
            $this->active_storage = null;
        }
    }
    
    /**
     * ✅ BUGFIX: Upload diretto su Google Drive con dati preventivo
     * NUOVO METODO per passare correttamente la data_evento a Google Drive
     * 
     * @param string $local_file_path Percorso file locale
     * @param string $remote_filename Nome file remoto (già corretto)
     * @param string $data_evento Data evento in formato Y-m-d
     * @return mixed Risultato upload
     */
    public function upload_to_googledrive($local_file_path, $remote_filename, $data_evento) {
        if ($this->storage_type !== 'googledrive') {
            $this->log('[StorageManager] Storage non è Google Drive, tipo attuale: ' . $this->storage_type);
            return false;
        }
        
        if (!$this->active_storage) {
            $this->log('[StorageManager] [ERROR] Google Drive non inizializzato');
            return false;
        }
        
        if (!file_exists($local_file_path)) {
            $this->log("[StorageManager] [ERROR] File non trovato: {$local_file_path}");
            return false;
        }
        
        // ✅ CORREZIONE: Chiama il metodo upload_to_googledrive di Google Drive
        // passando la data_evento corretta per organizzare i file nella cartella giusta
        if (method_exists($this->active_storage, 'upload_to_googledrive')) {
            $file_size = filesize($local_file_path);
            
            $this->log("[StorageManager] 📤 Upload {$remote_filename} ({$file_size} bytes)");
            $this->log("[StorageManager] ✅ Data evento: {$data_evento}");
            
            try {
                $result = $this->active_storage->upload_to_googledrive(
                    $local_file_path,
                    $remote_filename,
                    $data_evento
                );
                
                if ($result) {
                    $this->log("[StorageManager] ✅ Upload completato: {$remote_filename}");
                    return $result;
                } else {
                    $this->log("[StorageManager] [WARNING] Upload fallito per: {$remote_filename}");
                    return false;
                }
                
            } catch (\Exception $e) {
                $this->log("[StorageManager] [ERROR] Eccezione upload: " . $e->getMessage());
                return false;
            }
        } else {
            $this->log('[StorageManager] [ERROR] Metodo upload_to_googledrive non trovato in Google Drive handler');
            return false;
        }
    }
    
    /**
     * CORREZIONE: Upload file mantenendo il nome originale
     * [METODO ESISTENTE - NON MODIFICATO]
     */
    public function upload_file($local_file_path, $folder_context = '') {
        try {
            if (!$this->active_storage) {
                $this->log('[StorageManager] [ERROR] Storage non inizializzato');
                return false;
            }
            
            if (!file_exists($local_file_path)) {
                $this->log("[StorageManager] [ERROR] File non trovato: {$local_file_path}");
                return false;
            }
            
            // USA IL NOME FILE ORIGINALE
            $filename = basename($local_file_path);
            $file_size = filesize($local_file_path);
            
            $this->log("[StorageManager] Inizio upload file: {$filename} ({$file_size} bytes)");
            $this->log("[StorageManager] ✅ MANTIENI NOME ORIGINALE: {$filename}");
            
            if ($this->storage_type === 'googledrive') {
                if (method_exists($this->active_storage, 'upload_file')) {
                    // PASSA NULL COME DATA EVENTO PER NON RIGENERARE IL NOME
                    $result = $this->active_storage->upload_file($local_file_path, $filename, null);
                    
                    if ($result && isset($result['status']) && $result['status'] === 'ok') {
                        $this->log("[StorageManager] ✅ Upload completato: {$filename}");
                        return $result['webViewLink'] ?? $result['webContentLink'] ?? true;
                    } else {
                        $this->log("[StorageManager] [WARNING] Upload senza URL");
                        return false;
                    }
                } else {
                    $this->log('[StorageManager] [ERROR] Metodo upload_file non trovato');
                    return false;
                }
            } else {
                // Dropbox
                if (method_exists($this->active_storage, 'upload_file')) {
                    $result = $this->active_storage->upload_file($local_file_path, $filename);
                    return $result;
                } else {
                    $this->log('[StorageManager] [ERROR] Metodo upload non trovato per Dropbox');
                    return false;
                }
            }
            
        } catch (\Throwable $e) {
            $this->log("[StorageManager] [ERROR] Upload fallito: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete file from storage
     * [METODO ESISTENTE - NON MODIFICATO]
     */
    public function delete_file($file_url) {
        if (!$this->active_storage) {
            $this->log('[StorageManager] Storage non disponibile per eliminazione');
            return false;
        }
        
        try {
            if (method_exists($this->active_storage, 'delete_file')) {
                return $this->active_storage->delete_file($file_url);
            }
            
            $this->log('[StorageManager] Metodo delete_file non disponibile');
            return false;
            
        } catch (\Throwable $e) {
            $this->log('[StorageManager] Errore eliminazione: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test storage connection
     * [METODO ESISTENTE - NON MODIFICATO]
     */
    public function test_connection() {
        if (!$this->active_storage) {
            return array('success' => false, 'message' => 'Storage non inizializzato');
        }
        
        if (method_exists($this->active_storage, 'test_connection')) {
            return $this->active_storage->test_connection();
        }
        
        return array('success' => false, 'message' => 'Metodo test non disponibile');
    }
    
    /**
     * Check if storage is configured
     * [METODO ESISTENTE - NON MODIFICATO]
     */
    public function is_configured() {
        return $this->active_storage !== null;
    }
    
    /**
     * Get storage type
     * [METODO ESISTENTE - NON MODIFICATO]
     */
    public function get_storage_type() {
        return $this->storage_type;
    }
    
    /**
     * Get active storage handler
     * [METODO ESISTENTE - NON MODIFICATO]
     */
    public function get_active_storage() {
        return $this->active_storage;
    }
}