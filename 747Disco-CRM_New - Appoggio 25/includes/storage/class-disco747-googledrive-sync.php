<?php
/**
 * Classe per sincronizzazione e scansione batch Excel da Google Drive
 * 
 * @package    Disco747_CRM  
 * @subpackage Storage
 * @version    11.5.9-EXCEL-SCAN
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_GoogleDrive_Sync {
    
    private $drive_service = null;
    private $cache_duration = 300; // 5 minuti
    private $last_error = '';
    private $debug_mode = false;
    private $max_retries = 3;
    private $retry_delay = 2; // secondi
    
    /**
     * Costruttore
     */
    public function __construct($drive_service = null) {
        $this->drive_service = $drive_service;
        $this->debug_mode = defined('DISCO747_CRM_DEBUG') && DISCO747_CRM_DEBUG;
    }
    
    /**
     * Set Google Drive service
     */
    public function set_drive_service($service) {
        $this->drive_service = $service;
    }
    
    /**
     * Log helper
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode || $level === 'ERROR') {
            error_log("[747Disco-Scan] [{$level}] {$message}");
        }
    }
    
    /**
     * Get all Excel files from Google Drive /747-Preventivi/ folder
     */
    public function get_all_excel_files() {
        $this->log("Avvio scansione completa cartella /747-Preventivi/");
        
        if (!$this->drive_service) {
            $this->log("Drive service non disponibile", 'ERROR');
            return array();
        }
        
        $all_files = array();
        $folders_to_scan = array();
        
        try {
            // Trova la cartella principale /747-Preventivi/
            $main_folder_query = "name = '747-Preventivi' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
            $main_folder_results = $this->drive_service->files->listFiles(array(
                'q' => $main_folder_query,
                'fields' => 'files(id, name)',
                'pageSize' => 10
            ));
            
            if (empty($main_folder_results->files)) {
                $this->log("Cartella /747-Preventivi/ non trovata", 'ERROR');
                return array();
            }
            
            $main_folder_id = $main_folder_results->files[0]->id;
            $this->log("Cartella principale trovata: ID={$main_folder_id}");
            
            // Aggiungi la cartella principale alla lista da scansionare
            $folders_to_scan[] = array(
                'id' => $main_folder_id,
                'path' => '/747-Preventivi'
            );
            
            // Scansione ricorsiva delle sottocartelle
            while (!empty($folders_to_scan)) {
                $current_folder = array_shift($folders_to_scan);
                $current_folder_id = $current_folder['id'];
                $current_path = $current_folder['path'];
                
                $this->log("Scansione cartella: {$current_path}");
                
                // Cerca sottocartelle
                $subfolder_query = "'{$current_folder_id}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
                $pageToken = null;
                
                do {
                    $subfolder_results = $this->drive_service->files->listFiles(array(
                        'q' => $subfolder_query,
                        'fields' => 'nextPageToken, files(id, name)',
                        'pageSize' => 100,
                        'pageToken' => $pageToken
                    ));
                    
                    foreach ($subfolder_results->files as $subfolder) {
                        $folders_to_scan[] = array(
                            'id' => $subfolder->id,
                            'path' => $current_path . '/' . $subfolder->name
                        );
                    }
                    
                    $pageToken = $subfolder_results->nextPageToken;
                } while ($pageToken);
                
                // Cerca file Excel nella cartella corrente
                $excel_query = "'{$current_folder_id}' in parents and (mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or mimeType = 'application/vnd.ms-excel') and trashed = false";
                $pageToken = null;
                
                do {
                    $excel_results = $this->drive_service->files->listFiles(array(
                        'q' => $excel_query,
                        'fields' => 'nextPageToken, files(id, name, modifiedTime, size, webViewLink)',
                        'pageSize' => 100,
                        'pageToken' => $pageToken
                    ));
                    
                    foreach ($excel_results->files as $file) {
                        $file_info = array(
                            'id' => $file->id,
                            'name' => $file->name,
                            'path' => $current_path . '/' . $file->name,
                            'modified' => $file->modifiedTime,
                            'size' => $file->size,
                            'webViewLink' => $file->webViewLink
                        );
                        
                        $all_files[] = $file_info;
                        $this->log("File trovato: {$file_info['name']} (ID: {$file_info['id']})");
                    }
                    
                    $pageToken = $excel_results->nextPageToken;
                } while ($pageToken);
                
                // Piccola pausa per evitare rate limiting
                if (count($folders_to_scan) > 0) {
                    usleep(100000); // 100ms
                }
            }
            
            $this->log("Scansione completata. Trovati " . count($all_files) . " file Excel");
            return $all_files;
            
        } catch (\Exception $e) {
            $this->log("Errore durante la scansione: " . $e->getMessage(), 'ERROR');
            $this->last_error = $e->getMessage();
            return array();
        }
    }
    
    /**
     * Read and parse single Excel file from Google Drive
     */
    public function read_single_excel($file_id, $retry_count = 0) {
        $this->log("Lettura file Excel ID: {$file_id} (tentativo " . ($retry_count + 1) . ")");
        
        $result = array(
            'ok' => false,
            'data' => array(),
            'error' => '',
            'log' => array()
        );
        
        if (!$this->drive_service) {
            $result['error'] = 'Google Drive service non disponibile';
            $result['log'][] = '[ERROR] ' . $result['error'];
            return $result;
        }
        
        $temp_file = null;
        
        try {
            // Ottieni metadata del file
            $file = $this->drive_service->files->get($file_id, array(
                'fields' => 'id, name, modifiedTime, size'
            ));
            
            $result['log'][] = "[INFO] File: {$file->name} (Size: {$file->size} bytes)";
            
            // Download del file
            $content = $this->drive_service->files->get($file_id, array('alt' => 'media'));
            
            // Salva in file temporaneo
            $temp_file = sys_get_temp_dir() . '/disco747_' . uniqid() . '.xlsx';
            file_put_contents($temp_file, $content->getBody()->getContents());
            
            $result['log'][] = "[INFO] File scaricato in: {$temp_file}";
            
            // Parse Excel con PHPSpreadsheet
            if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
                require_once ABSPATH . 'wp-content/plugins/747disco-crm/vendor/autoload.php';
            }
            
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($temp_file);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($temp_file);
            
            $worksheet = $spreadsheet->getActiveSheet();
            $result['log'][] = "[INFO] Foglio attivo: " . $worksheet->getTitle();
            
            // Estrai dati secondo le specifiche
            $data = array(
                'file_id' => $file_id,
                'filename' => $file->name,
                'drive_path' => '/747-Preventivi/' . $file->name,
                'modified_time' => $file->modifiedTime,
                'analysis_success' => 1,
                'analysis_errors_json' => null
            );
            
            // Determina stato dal nome file
            $filename_upper = strtoupper($file->name);
            if (strpos($filename_upper, 'CONF') === 0) {
                $data['stato'] = 'CONF';
            } elseif (strpos($filename_upper, 'NO') === 0) {
                $data['stato'] = 'NO';
            } else {
                $data['stato'] = 'Neutro';
            }
            
            // Mappa celle Excel -> campi database
            $cell_mappings = array(
                'data_evento' => 'A1',
                'tipo_evento' => 'B1', 
                'tipo_menu' => 'C1',
                'orario' => 'D1',
                'numero_invitati' => 'E1',
                'nome_referente' => 'F1',
                'cognome_referente' => 'G1',
                'cellulare' => 'H1',
                'email' => 'I1',
                'omaggio1' => 'J1',
                'omaggio2' => 'K1',
                'omaggio3' => 'L1',
                'importo' => 'M1',
                'acconto' => 'N1',
                'saldo' => 'O1',
                'extra1_nome' => 'P1',
                'extra1_prezzo' => 'Q1',
                'extra2_nome' => 'R1',
                'extra2_prezzo' => 'S1',
                'extra3_nome' => 'T1',
                'extra3_prezzo' => 'U1'
            );
            
            // Leggi celle con gestione errori
            foreach ($cell_mappings as $field => $cell) {
                try {
                    $value = $worksheet->getCell($cell)->getValue();
                    
                    // Conversione tipi
                    if (in_array($field, array('numero_invitati'))) {
                        $data[$field] = intval($value);
                    } elseif (in_array($field, array('importo', 'acconto', 'saldo', 'extra1_prezzo', 'extra2_prezzo', 'extra3_prezzo'))) {
                        $data[$field] = floatval(str_replace(',', '.', $value));
                    } elseif ($field === 'data_evento' && $value) {
                        // Gestione date Excel
                        if (is_numeric($value)) {
                            $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                            $data[$field] = $date->format('Y-m-d');
                        } else {
                            $data[$field] = date('Y-m-d', strtotime($value));
                        }
                    } else {
                        $data[$field] = trim(strval($value));
                    }
                    
                    if (!empty($data[$field])) {
                        $result['log'][] = "[DATA] {$field}: {$data[$field]}";
                    }
                    
                } catch (\Exception $e) {
                    $result['log'][] = "[WARNING] Errore lettura cella {$cell}: " . $e->getMessage();
                }
            }
            
            // Se tipo_menu non trovato nel file, prova a estrarlo dal nome file
            if (empty($data['tipo_menu'])) {
                if (preg_match('/\(Menu\s+([^\)]+)\)/i', $file->name, $matches)) {
                    $data['tipo_menu'] = trim($matches[1]);
                    $result['log'][] = "[INFO] Menu estratto dal nome file: {$data['tipo_menu']}";
                }
            }
            
            // Calcola saldo se mancante
            if (empty($data['saldo']) && !empty($data['importo']) && isset($data['acconto'])) {
                $data['saldo'] = $data['importo'] - $data['acconto'];
                $result['log'][] = "[CALC] Saldo calcolato: {$data['saldo']}";
            }
            
            $result['ok'] = true;
            $result['data'] = $data;
            $result['log'][] = "[SUCCESS] Analisi completata con successo";
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['log'][] = "[ERROR] " . $result['error'];
            
            // Retry logic
            if ($retry_count < $this->max_retries - 1) {
                $result['log'][] = "[INFO] Nuovo tentativo tra {$this->retry_delay} secondi...";
                sleep($this->retry_delay);
                return $this->read_single_excel($file_id, $retry_count + 1);
            }
            
            // Salva errore nel database
            if (isset($data)) {
                $data['analysis_success'] = 0;
                $data['analysis_errors_json'] = json_encode(array(
                    'error' => $result['error'],
                    'log' => $result['log']
                ));
                $result['data'] = $data;
            }
        } finally {
            // Pulizia file temporaneo
            if ($temp_file && file_exists($temp_file)) {
                @unlink($temp_file);
                $result['log'][] = "[INFO] File temporaneo rimosso";
            }
        }
        
        return $result;
    }
    
    /**
     * Batch scan all Excel files
     */
    public function batch_scan_all_excel($progress_callback = null) {
        $this->log("=== INIZIO SCANSIONE BATCH ===");
        
        $results = array(
            'success' => 0,
            'failed' => 0,
            'total' => 0,
            'files' => array(),
            'errors' => array()
        );
        
        // Ottieni lista file
        $files = $this->get_all_excel_files();
        $results['total'] = count($files);
        
        if (empty($files)) {
            $this->log("Nessun file Excel trovato", 'WARNING');
            return $results;
        }
        
        $this->log("Trovati {$results['total']} file da analizzare");
        
        // Processa ogni file
        foreach ($files as $index => $file) {
            $file_number = $index + 1;
            
            $this->log("Processing file {$file_number}/{$results['total']}: {$file['name']}");
            
            // Callback progress
            if ($progress_callback && is_callable($progress_callback)) {
                call_user_func($progress_callback, array(
                    'current' => $file_number,
                    'total' => $results['total'],
                    'file' => $file['name'],
                    'percentage' => round(($file_number / $results['total']) * 100)
                ));
            }
            
            // Analizza file
            $scan_result = $this->read_single_excel($file['id']);
            
            $file_result = array(
                'file_id' => $file['id'],
                'filename' => $file['name'],
                'path' => $file['path'],
                'success' => $scan_result['ok'],
                'data' => $scan_result['data'],
                'error' => $scan_result['error'],
                'log' => $scan_result['log']
            );
            
            $results['files'][] = $file_result;
            
            if ($scan_result['ok']) {
                $results['success']++;
                $this->log("✅ File {$file_number} analizzato con successo");
            } else {
                $results['failed']++;
                $results['errors'][] = array(
                    'file' => $file['name'],
                    'error' => $scan_result['error']
                );
                $this->log("❌ File {$file_number} fallito: " . $scan_result['error'], 'ERROR');
            }
            
            // Pausa per evitare rate limiting
            if ($file_number < $results['total']) {
                usleep(500000); // 500ms
            }
        }
        
        $this->log("=== FINE SCANSIONE BATCH ===");
        $this->log("Risultati: {$results['success']} successi, {$results['failed']} fallimenti su {$results['total']} totali");
        
        return $results;
    }
    
    /**
     * Get last error
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    /**
     * Clear cache
     */
    public function clear_cache() {
        delete_transient('disco747_gdrive_excel_files');
        delete_transient('disco747_gdrive_preventivi');
        $this->log("Cache cleared");
    }
}