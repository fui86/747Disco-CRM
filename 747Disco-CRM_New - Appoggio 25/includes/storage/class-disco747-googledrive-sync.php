<?php
/**
 * Classe per sincronizzazione preventivi da Google Drive
 * VERSIONE 11.6.6-FULL-SCAN - Scansione completa senza limiti
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @since      11.6.6
 * @version    11.6.6-FULL-SCAN
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_GoogleDrive_Sync
 * Scansiona e analizza file Excel da Google Drive
 * 
 * @since 11.6.6-FULL-SCAN
 */
class Disco747_GoogleDrive_Sync {

    private $googledrive_handler;
    private $database;
    private $preventivi_cache = null;
    private $cache_duration = 300;
    private $debug_mode = true;
    private $sync_available = false;
    private $last_error = '';
    private $temp_dir = '';

    /**
     * Costruttore
     */
    public function __construct($googledrive_instance = null) {
        $session_id = 'INIT_' . date('His') . '_' . wp_rand(100, 999);
        
        try {
            $this->log("=== DEBUG SESSION {$session_id} CONSTRUCTOR START ===");
            
            if ($googledrive_instance) {
                $this->googledrive_handler = $googledrive_instance;
                $this->sync_available = true;
                $this->log("DEBUG: GoogleDrive instance fornita esternamente");
            } else {
                $this->log("DEBUG: Cerco di caricare classe GoogleDrive autonomamente...");
                if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                    $this->googledrive_handler = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
                    $this->sync_available = true;
                    $this->log("DEBUG: Classe GoogleDrive trovata e istanziata");
                } else {
                    $this->log("DEBUG: Classe GoogleDrive NON trovata", 'WARNING');
                    $this->sync_available = false;
                }
            }
            
            // Carica database
            $disco747_crm = disco747_crm();
            if ($disco747_crm && method_exists($disco747_crm, 'get_database')) {
                $this->database = $disco747_crm->get_database();
                $this->log("DEBUG: Database handler caricato");
            } else {
                $this->log("DEBUG: Database handler NON disponibile", 'WARNING');
            }
            
            // Setup temp directory
            $upload_dir = wp_upload_dir();
            $this->temp_dir = $upload_dir['basedir'] . '/disco747-temp/';
            
            if (!file_exists($this->temp_dir)) {
                wp_mkdir_p($this->temp_dir);
                $this->log("DEBUG: Cartella temp creata: " . $this->temp_dir);
            }
            
            $this->log("DEBUG: GoogleDrive Sync Handler inizializzato (disponibile: " . ($this->sync_available ? 'SI' : 'NO') . ")");
            $this->log("=== DEBUG SESSION {$session_id} CONSTRUCTOR END ===");
            
        } catch (\Exception $e) {
            $this->log("DEBUG: Errore inizializzazione GoogleDrive Sync: " . $e->getMessage(), 'ERROR');
            $this->sync_available = false;
            $this->last_error = $e->getMessage();
        }
    }

    /**
     * Sistema di logging
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode && function_exists('error_log')) {
            error_log('[747Disco-GDriveSync] ' . $message);
        }
    }

    /**
     * Verifica se il sync è disponibile
     */
    public function is_available() {
        $available = $this->sync_available && $this->googledrive_handler !== null;
        return $available;
    }

    /**
     * Ottieni ultimo errore
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Trova cartella principale in modo sicuro
     */
    private function find_main_folder_safe($token) {
        try {
            $search_names = array('747-Preventivi', 'PreventiviParty');
            
            foreach ($search_names as $folder_name) {
                $query = "mimeType='application/vnd.google-apps.folder' and name='{$folder_name}' and trashed=false";
                
                $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                    'q' => $query,
                    'fields' => 'files(id,name)',
                    'pageSize' => 10
                ));
                
                $response = wp_remote_get($url, array(
                    'headers' => array('Authorization' => 'Bearer ' . $token),
                    'timeout' => 30
                ));
                
                if (!is_wp_error($response)) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    
                    if (isset($data['files']) && !empty($data['files'])) {
                        $folder_id = $data['files'][0]['id'];
                        $this->log("Cartella {$folder_name} trovata: {$folder_id}");
                        return $folder_id;
                    }
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->log("Errore ricerca cartella principale: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Ottieni file Excel in una cartella
     */
    private function get_excel_files_in_folder($folder_id, $token) {
        try {
            $query = "'{$folder_id}' in parents and (mimeType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or mimeType='application/vnd.ms-excel') and trashed=false";
            
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id,name,size,modifiedTime)',
                'pageSize' => 100
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return array();
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return isset($data['files']) ? $data['files'] : array();
            
        } catch (\Exception $e) {
            $this->log("Errore ricerca file Excel: " . $e->getMessage(), 'ERROR');
            return array();
        }
    }

    /**
     * Ottieni sottocartelle
     */
    private function get_subfolders($folder_id, $token) {
        try {
            $query = "'{$folder_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";
            
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id,name)',
                'pageSize' => 50
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return array();
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return isset($data['files']) ? $data['files'] : array();
            
        } catch (\Exception $e) {
            $this->log("Errore ricerca sottocartelle: " . $e->getMessage(), 'ERROR');
            return array();
        }
    }

    /**
     * Helper: Ottiene token valido
     */
    private function get_valid_token() {
        if (!$this->googledrive_handler) {
            $this->log('[Token] GoogleDrive handler non disponibile', 'ERROR');
            return false;
        }
        
        try {
            if (!method_exists($this->googledrive_handler, 'get_valid_access_token')) {
                $this->log('[Token] Metodo get_valid_access_token non disponibile', 'ERROR');
                return false;
            }
            
            $token = $this->googledrive_handler->get_valid_access_token();
            
            if ($token) {
                $this->log('[Token] Token ottenuto con successo');
            } else {
                $this->log('[Token] Token non disponibile', 'WARNING');
            }
            
            return $token;
            
        } catch (\Exception $e) {
            $this->log('[Token] Errore: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    // ========================================================================
    // BATCH SCAN COMPLETO - TUTTI I FILE
    // ========================================================================

    /**
     * Batch scan con analisi Excel completa
     * FULL MODE: Tutti i file trovati
     * 
     * @return array Risultato con statistiche
     */
    public function scan_excel_files_batch() {
        $this->log('[BATCH-SCAN] ========== INIZIO BATCH SCAN COMPLETO ==========');
        
        $result = array(
            'found' => 0,
            'processed' => 0,
            'inserted' => 0,
            'updated' => 0,
            'errors' => 0,
            'messages' => array()
        );
        
        try {
            // Pulizia temp all'inizio
            $this->cleanup_temp_directory();
            
            // Ottieni token
            $token = $this->get_valid_token();
            if (!$token) {
                throw new \Exception('Token Google Drive non disponibile');
            }
            
            $result['messages'][] = 'Token Google Drive ottenuto';
            
            // Trova cartella principale
            $main_folder_id = $this->find_main_folder_safe($token);
            if (!$main_folder_id) {
                throw new \Exception('Cartella /747-Preventivi/ non trovata su Google Drive');
            }
            
            $result['messages'][] = 'Cartella /747-Preventivi/ trovata';
            
            // Lista tutti i file Excel
            $all_files = $this->list_excel_files_recursive($main_folder_id, $token);
            
            $result['found'] = count($all_files);
            $result['messages'][] = "Trovati {$result['found']} file Excel totali";
            
            // PROCESSAMENTO COMPLETO - TUTTI I FILE
            $files_to_process = $all_files;
            $result['messages'][] = "FULL MODE: Analisi di TUTTI i " . count($files_to_process) . " file trovati";
            
            // Processa ogni file
            foreach ($files_to_process as $index => $file_info) {
                try {
                    $current = $index + 1;
                    $this->log("[BATCH] ========================================");
                    $this->log("[BATCH] File {$current}/{$result['found']}: {$file_info['name']}");
                    $this->log("[BATCH] ========================================");
                    
                    $process_result = $this->process_single_excel_file($file_info, $token);
                    
                    if ($process_result['success']) {
                        if ($process_result['action'] === 'insert') {
                            $result['inserted']++;
                        } elseif ($process_result['action'] === 'update') {
                            $result['updated']++;
                        }
                        $result['processed']++;
                    } else {
                        $result['errors']++;
                        $result['messages'][] = "Errore: {$file_info['name']} - {$process_result['error']}";
                    }
                    
                } catch (\Exception $e) {
                    $result['errors']++;
                    $result['messages'][] = "Errore: {$file_info['name']} - {$e->getMessage()}";
                    $this->log("[BATCH] Errore processamento: " . $e->getMessage(), 'ERROR');
                }
            }
            
            // Pulizia finale
            $this->cleanup_temp_directory();
            
            $result['messages'][] = 'Batch scan completato';
            $result['messages'][] = "Risultati: {$result['inserted']} nuovi, {$result['updated']} aggiornati, {$result['errors']} errori";
            
        } catch (\Exception $e) {
            $this->log('[BATCH] ERRORE: ' . $e->getMessage(), 'ERROR');
            $result['errors']++;
            $result['messages'][] = 'Errore: ' . $e->getMessage();
        }
        
        $this->log('[BATCH-SCAN] ========== FINE BATCH SCAN ==========');
        return $result;
    }

    /**
     * Processa singolo file Excel
     */
    private function process_single_excel_file($file_info, $token) {
        $temp_file = null;
        
        try {
            $this->log("[PROCESS] ========== INIZIO FILE: {$file_info['name']} ==========");
            $this->log("[PROCESS] File ID: {$file_info['id']}");
            $this->log("[PROCESS] Size: {$file_info['size']} bytes");
            
            // 1. Download temporaneo
            $this->log("[PROCESS] STEP 1: Inizio download...");
            $temp_file = $this->download_file_temp($file_info['id'], $file_info['name'], $token);
            
            if (!$temp_file || !file_exists($temp_file)) {
                throw new \Exception('Download fallito - file non trovato dopo download');
            }
            
            $file_size_downloaded = filesize($temp_file);
            $this->log("[PROCESS] STEP 1: File scaricato: {$temp_file} ({$file_size_downloaded} bytes)");
            
            // 2. Lettura dati con PhpSpreadsheet
            $this->log("[PROCESS] STEP 2: Inizio lettura Excel...");
            $data = $this->read_excel_data($temp_file, $file_info);
            
            if (!$data) {
                throw new \Exception('Lettura Excel fallita - dati non estratti');
            }
            
            $this->log("[PROCESS] STEP 2: Dati estratti: cliente={$data['nome_cliente']}, data={$data['data_evento']}");
            
            // 3. Salvataggio database
            $this->log("[PROCESS] STEP 3: Inizio salvataggio database...");
            $save_result = $this->save_to_database($data);
            
            if (!$save_result['success']) {
                throw new \Exception('Salvataggio DB fallito: ' . ($save_result['error'] ?? 'unknown'));
            }
            
            $this->log("[PROCESS] STEP 3: Salvato in DB - action={$save_result['action']}, id={$save_result['id']}");
            
            // 4. CANCELLAZIONE IMMEDIATA FILE TEMP
            if ($temp_file && file_exists($temp_file)) {
                @unlink($temp_file);
                $this->log("[PROCESS] STEP 4: File temp cancellato: {$temp_file}");
            }
            
            $this->log("[PROCESS] ========== FINE FILE: {$file_info['name']} - SUCCESS ==========");
            
            return $save_result;
            
        } catch (\Exception $e) {
            $this->log("[PROCESS] ERRORE: {$e->getMessage()}", 'ERROR');
            
            // Cleanup in caso di errore
            if ($temp_file && file_exists($temp_file)) {
                @unlink($temp_file);
                $this->log("[PROCESS] File temp cancellato dopo errore");
            }
            
            $this->log("[PROCESS] ========== FINE FILE: {$file_info['name']} - ERROR ==========");
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Download file temporaneo da Google Drive
     */
    private function download_file_temp($file_id, $filename, $token) {
        try {
            $url = "https://www.googleapis.com/drive/v3/files/{$file_id}?alt=media";
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                throw new \Exception('Errore download: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            
            if (empty($body)) {
                throw new \Exception('File vuoto');
            }
            
            // Salva in temp
            $temp_filename = 'temp_' . time() . '_' . sanitize_file_name($filename);
            $temp_path = $this->temp_dir . $temp_filename;
            
            $written = file_put_contents($temp_path, $body);
            
            if ($written === false) {
                throw new \Exception('Impossibile scrivere file temporaneo');
            }
            
            $this->log("[DOWNLOAD] File salvato: {$temp_path} ({$written} bytes)");
            
            return $temp_path;
            
        } catch (\Exception $e) {
            $this->log("[DOWNLOAD] Errore: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Leggi dati da file Excel con PhpSpreadsheet - MAPPING CORRETTO
     */
    private function read_excel_data($file_path, $file_info) {
        try {
            // Verifica PhpSpreadsheet
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                throw new \Exception('PhpSpreadsheet non disponibile');
            }
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // TIPO MENU (B1)
            $tipo_menu = 'Menu 7';
            try {
                $tipo_menu_raw = $worksheet->getCell('B1')->getCalculatedValue();
                $tipo_menu_str = trim(strval($tipo_menu_raw ?? ''));
                if (!empty($tipo_menu_str)) {
                    $tipo_menu = $tipo_menu_str;
                }
            } catch (\Exception $e) {
                // ignore
            }
            
            // DATA EVENTO (C6)
            $data_evento_raw = '';
            try {
                $data_evento_raw = $worksheet->getCell('C6')->getCalculatedValue();
            } catch (\Exception $e) {
                // ignore
            }
            
            $data_evento = $this->parse_excel_date($data_evento_raw);
            
            // TIPO EVENTO (C7)
            $tipo_evento = '';
            try {
                $tipo_evento_raw = $worksheet->getCell('C7')->getCalculatedValue();
                $tipo_evento = trim(strval($tipo_evento_raw ?? ''));
            } catch (\Exception $e) {
                // ignore
            }
            
            // ORARIO EVENTO (C8)
            $orario_evento = '';
            try {
                $orario_raw = $worksheet->getCell('C8')->getCalculatedValue();
                $orario_evento = trim(strval($orario_raw ?? ''));
            } catch (\Exception $e) {
                // ignore
            }
            
            // NUMERO INVITATI (C9)
            $numero_invitati = 0;
            try {
                $numero_invitati = intval($worksheet->getCell('C9')->getCalculatedValue());
            } catch (\Exception $e) {
                // ignore
            }
            
            // NOME CLIENTE (C11)
            $nome_cliente = '';
            try {
                $nome_raw = $worksheet->getCell('C11')->getCalculatedValue();
                $nome_cliente = trim(strval($nome_raw ?? ''));
            } catch (\Exception $e) {
                // ignore
            }
            
            // COGNOME CLIENTE (C12)
            $cognome_cliente = '';
            try {
                $cognome_raw = $worksheet->getCell('C12')->getCalculatedValue();
                $cognome_cliente = trim(strval($cognome_raw ?? ''));
            } catch (\Exception $e) {
                // ignore
            }
            
            // Nome completo
            $nome_completo = trim($nome_cliente . ' ' . $cognome_cliente);
            if (empty($nome_completo)) {
                $nome_completo = $nome_cliente;
            }
            
            // TELEFONO (C14)
            $telefono = '';
            try {
                $telefono_raw = $worksheet->getCell('C14')->getCalculatedValue();
                $telefono = trim(strval($telefono_raw ?? ''));
            } catch (\Exception $e) {
                // ignore
            }
            
            // EMAIL (C15)
            $email = '';
            try {
                $email_raw = $worksheet->getCell('C15')->getCalculatedValue();
                $email = trim(strval($email_raw ?? ''));
            } catch (\Exception $e) {
                // ignore
            }
            
            // OMAGGI (C17, C18, C19)
            $omaggio1 = '';
            try {
                $omaggio1_raw = $worksheet->getCell('C17')->getCalculatedValue();
                $omaggio1 = trim(strval($omaggio1_raw ?? ''));
            } catch (\Exception $e) {
                // ignore
            }
            
            $omaggio2 = '';
            try {
                $omaggio2_raw = $worksheet->getCell('C18')->getCalculatedValue();
                $omaggio2 = trim(strval($omaggio2_raw ?? ''));
            } catch (\Exception $e) {
                // ignore
            }
            
            $omaggio3 = '';
            try {
                $omaggio3_raw = $worksheet->getCell('C19')->getCalculatedValue();
                $omaggio3 = trim(strval($omaggio3_raw ?? ''));
            } catch (\Exception $e) {
                // ignore
            }
            
            // IMPORTI (F27, F28)
            $importo_totale = 0;
            try {
                $importo_totale = floatval($worksheet->getCell('F27')->getCalculatedValue());
            } catch (\Exception $e) {
                // ignore
            }
            
            $acconto = 0;
            try {
                $acconto = floatval($worksheet->getCell('F28')->getCalculatedValue());
            } catch (\Exception $e) {
                // ignore
            }
            
            // EXTRA (C33-F35)
            $extra1 = '';
            $extra1_importo = 0;
            try {
                $extra1_raw = $worksheet->getCell('C33')->getCalculatedValue();
                $extra1 = trim(strval($extra1_raw ?? ''));
                if (!empty($extra1)) {
                    $extra1_importo = floatval($worksheet->getCell('F33')->getCalculatedValue());
                }
            } catch (\Exception $e) {
                // ignore
            }
            
            $extra2 = '';
            $extra2_importo = 0;
            try {
                $extra2_raw = $worksheet->getCell('C34')->getCalculatedValue();
                $extra2 = trim(strval($extra2_raw ?? ''));
                if (!empty($extra2)) {
                    $extra2_importo = floatval($worksheet->getCell('F34')->getCalculatedValue());
                }
            } catch (\Exception $e) {
                // ignore
            }
            
            $extra3 = '';
            $extra3_importo = 0;
            try {
                $extra3_raw = $worksheet->getCell('C35')->getCalculatedValue();
                $extra3 = trim(strval($extra3_raw ?? ''));
                if (!empty($extra3)) {
                    $extra3_importo = floatval($worksheet->getCell('F35')->getCalculatedValue());
                }
            } catch (\Exception $e) {
                // ignore
            }
            
            // Validazione minima
            if (empty($nome_completo)) {
                throw new \Exception('Nome cliente mancante');
            }
            
            if (empty($data_evento) || $data_evento === '1970-01-01') {
                throw new \Exception('Data evento non valida');
            }
            
            // Costruisci array dati
            $data = array(
                'googledrive_file_id' => $file_info['id'],
                'nome_cliente' => $nome_completo,
                'telefono' => $telefono,
                'email' => $email,
                'data_evento' => $data_evento,
                'tipo_evento' => $tipo_evento,
                'tipo_menu' => $tipo_menu,
                'numero_invitati' => $numero_invitati,
                'orario_evento' => $orario_evento,
                'importo_totale' => $importo_totale,
                'acconto' => $acconto,
                'omaggio1' => $omaggio1,
                'omaggio2' => $omaggio2,
                'omaggio3' => $omaggio3,
                'extra1' => $extra1,
                'extra1_importo' => $extra1_importo,
                'extra2' => $extra2,
                'extra2_importo' => $extra2_importo,
                'extra3' => $extra3,
                'extra3_importo' => $extra3_importo,
                'stato' => $this->determine_stato($file_info['name']),
                'googledrive_url' => "https://drive.google.com/file/d/{$file_info['id']}/view",
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );
            
            return $data;
            
        } catch (\Exception $e) {
            $this->log("[EXCEL] ERRORE lettura: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Parse data Excel
     */
    private function parse_excel_date($value) {
        if (empty($value)) {
            return date('Y-m-d');
        }
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }
        
        if (is_numeric($value)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                return date('Y-m-d');
            }
        }
        
        return date('Y-m-d');
    }

    /**
     * Determina stato da filename
     */
    private function determine_stato($filename) {
        if (stripos($filename, 'CONF') === 0) {
            return 'confermato';
        } elseif (stripos($filename, 'NO') === 0) {
            return 'annullato';
        } else {
            return 'attivo';
        }
    }

    /**
     * Salva nel database
     */
    private function save_to_database($data) {
        try {
            if (!$this->database) {
                throw new \Exception('Database non disponibile');
            }
            
            $existing = $this->database->get_preventivo_by_file_id($data['googledrive_file_id']);
            
            if ($existing) {
                $result = $this->database->update_preventivo($existing->id, $data);
                
                return array(
                    'success' => $result !== false,
                    'action' => 'update',
                    'id' => $existing->id
                );
            } else {
                $id = $this->database->insert_preventivo($data);
                
                return array(
                    'success' => $id !== false,
                    'action' => 'insert',
                    'id' => $id
                );
            }
            
        } catch (\Exception $e) {
            $this->log("[DB] Errore: " . $e->getMessage(), 'ERROR');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Lista ricorsiva file Excel
     */
    private function list_excel_files_recursive($folder_id, $token, $path = '/747-Preventivi') {
        $files = array();
        
        try {
            $excel_files = $this->get_excel_files_in_folder($folder_id, $token);
            
            foreach ($excel_files as $file) {
                $files[] = array(
                    'id' => $file['id'],
                    'name' => $file['name'],
                    'size' => $file['size'] ?? 0,
                    'modified' => $file['modifiedTime'] ?? '',
                    'path' => $path
                );
            }
            
            $subfolders = $this->get_subfolders($folder_id, $token);
            foreach ($subfolders as $subfolder) {
                $subfolder_path = $path . '/' . $subfolder['name'];
                $subfolder_files = $this->list_excel_files_recursive($subfolder['id'], $token, $subfolder_path);
                $files = array_merge($files, $subfolder_files);
            }
            
        } catch (\Exception $e) {
            $this->log('[Recursive] Errore: ' . $e->getMessage(), 'ERROR');
        }
        
        return $files;
    }

    /**
     * Pulizia cartella temporanea
     */
    private function cleanup_temp_directory() {
        try {
            if (!file_exists($this->temp_dir)) {
                return;
            }
            
            $files = glob($this->temp_dir . '*');
            $deleted = 0;
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                    $deleted++;
                }
            }
            
            if ($deleted > 0) {
                $this->log("[CLEANUP] Cancellati {$deleted} file temporanei");
            }
            
        } catch (\Exception $e) {
            $this->log("[CLEANUP] Errore: " . $e->getMessage(), 'ERROR');
        }
    }
}