<?php
/**
 * Classe per sincronizzazione preventivi da Google Drive - SCANSIONE COMPLETA + DEBUG EXCEL AUTO
 * MODIFICA: Trova TUTTI i file Excel su Google Drive senza scartarne nessuno + routine debug COMPLETA
 * CORRETTO: Mapping celle Template Nuovo aggiornato
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @since      11.6.0
 * @version    11.6.1-DEBUG-EXCEL-AUTO-CORRECTED
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_GoogleDrive_Sync - ACCETTA TUTTI I FILE EXCEL + DEBUG EXCEL AUTO COMPLETA + MAPPING CORRETTO
 * 
 * Trova TUTTI i file Excel nella cartella /747-Preventivi/ ricorsivamente
 * NON scarta nessun file Excel, anche se il nome non segue pattern standard
 * AGGIUNGE: Routine debug COMPLETA per lettura singolo file Excel con fallback
 * CORRETTO: Mapping celle Template Nuovo con celle corrette
 * 
 * @since 11.6.1-DEBUG-EXCEL-AUTO-CORRECTED
 */
class Disco747_GoogleDrive_Sync {

    private $googledrive;
    private $preventivi_cache = null;
    private $cache_duration = 300;
    private $debug_mode = true;
    private $sync_available = false;
    private $last_error = '';

    /**
     * Costruttore SICURO con DEBUG
     */
    public function __construct($googledrive_instance = null) {
        $session_id = 'INIT_' . date('His') . '_' . wp_rand(100, 999);
        
        try {
            $this->log("=== DEBUG SESSION {$session_id} CONSTRUCTOR START ===");
            
            if ($googledrive_instance) {
                $this->googledrive = $googledrive_instance;
                $this->sync_available = true;
                $this->log("DEBUG: GoogleDrive instance fornita esternamente");
            } else {
                $this->log("DEBUG: Cerco di caricare classe GoogleDrive autonomamente...");
                if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                    $this->googledrive = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
                    $this->sync_available = true;
                    $this->log("DEBUG: Classe GoogleDrive trovata e istanziata");
                } else {
                    $this->log("DEBUG: Classe GoogleDrive NON trovata", 'WARNING');
                    $this->sync_available = false;
                }
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
        $available = $this->sync_available && $this->googledrive !== null;
        $this->log("DEBUG: is_available() chiamato - Risultato: " . ($available ? 'SI' : 'NO'));
        return $available;
    }

    /**
     * Ottieni ultimo errore
     */
    public function get_last_error() {
        return $this->last_error;
    }

    // ============================================================================
    // SCANSIONE COMPLETA PREVENTIVI
    // ============================================================================

    /**
     * Ottieni tutti i preventivi da Google Drive con cache
     */
    public function get_all_preventivi($force_refresh = false) {
        $session_id = 'SYNC_' . date('His') . '_' . wp_rand(100, 999);
        
        try {
            $this->log("=== DEBUG SESSION {$session_id} START ===");
            $this->log("DEBUG STEP 1: get_all_preventivi chiamato con force_refresh=" . ($force_refresh ? 'TRUE' : 'FALSE'));
            
            // STEP 2: VERIFICA DISPONIBILITA'
            if (!$this->sync_available || !$this->googledrive) {
                $this->log("DEBUG STEP 2: Sync non disponibile - googledrive=" . ($this->googledrive ? 'SI' : 'NO') . ", sync_available=" . ($this->sync_available ? 'SI' : 'NO'));
                $this->log("=== DEBUG SESSION {$session_id} END (NO SYNC) ===");
                return array();
            }
            $this->log("DEBUG STEP 2: ✅ GoogleDrive sync disponibile");
            
            // STEP 3: CONTROLLO CACHE
            if (!$force_refresh) {
                $this->log("DEBUG STEP 3A: Controllo cache esistente...");
                $cached = get_transient('disco747_gdrive_preventivi');
                if ($cached && is_array($cached)) {
                    $this->log("DEBUG STEP 3B: ✅ Cache valida trovata (" . count($cached) . " elementi)");
                    $this->log("DEBUG STEP 3C: SUGGERIMENTO: Per vedere tutti i file usa force_refresh=TRUE");
                    $this->log("=== DEBUG SESSION {$session_id} END (CACHE) ===");
                    return $cached;
                } else {
                    $this->log("DEBUG STEP 3E: Cache NON trovata o scaduta - Procedo con scansione COMPLETA");
                }
            } else {
                $this->log("DEBUG STEP 3F: force_refresh = TRUE - IGNORO cache, avvio scansione completa");
                delete_transient('disco747_gdrive_preventivi');
                $this->log("DEBUG STEP 3G: Cache cancellata forzatamente");
            }

            // STEP 4: INIZIO SCANSIONE COMPLETA
            $this->log("DEBUG STEP 4: 🔍 Avvio SCANSIONE COMPLETA di tutti i file Excel su Google Drive...");
            
            // STEP 5: VERIFICA TOKEN
            if (!method_exists($this->googledrive, 'get_valid_access_token')) {
                throw new \Exception("DEBUG: Metodo get_valid_access_token non disponibile nella classe GoogleDrive");
            }
            
            $this->log("DEBUG STEP 5: Recupero token di accesso...");
            $token = $this->googledrive->get_valid_access_token();
            if (!$token) {
                throw new \Exception("DEBUG: Impossibile ottenere token di accesso Google Drive");
            }
            $this->log("DEBUG STEP 5: Token di accesso ottenuto correttamente");

            // STEP 6: CERCA CARTELLA PRINCIPALE
            $this->log("DEBUG STEP 6: Cerco cartella principale /747-Preventivi/...");
            $main_folder_id = $this->find_main_folder_safe($token);
            if (!$main_folder_id) {
                $this->log("DEBUG STEP 6: Cartella principale /747-Preventivi/ NON TROVATA", 'WARNING');
                $this->log("=== DEBUG SESSION {$session_id} END (NO FOLDER) ===");
                return array();
            }
            $this->log("DEBUG STEP 6: Cartella principale trovata: {$main_folder_id}");

            // STEP 7: SCANSIONE COMPLETA (NUOVO METODO)
            $this->log("DEBUG STEP 7: 🔍 Avvio scansione COMPLETA di tutti i file Excel...");
            $preventivi = $this->scan_all_excel_files_recursive($main_folder_id, $token);
            $this->log("DEBUG STEP 7: 📊 Scansione COMPLETA completata, trovati " . count($preventivi) . " preventivi totali");
            
            if (empty($preventivi)) {
                $this->log("DEBUG STEP 7: ⚠️ Nessun file Excel trovato in tutta la struttura");
                $this->log("=== DEBUG SESSION {$session_id} END (NO FILES) ===");
                return array();
            }

            // STEP 8: ORDINAMENTO
            $this->log("DEBUG STEP 8: Ordinamento preventivi per data...");
            usort($preventivi, function($a, $b) {
                $date_a = isset($a['data_evento']) ? strtotime($a['data_evento']) : 0;
                $date_b = isset($b['data_evento']) ? strtotime($b['data_evento']) : 0;
                return $date_b - $date_a; // Più recenti primi
            });

            // STEP 9: SALVATAGGIO CACHE
            $this->log("DEBUG STEP 9: Salvataggio in cache...");
            set_transient('disco747_gdrive_preventivi', $preventivi, $this->cache_duration);
            $this->log("DEBUG STEP 9: ✅ Cache salvata per {$this->cache_duration} secondi");
            
            $this->log("=== DEBUG SESSION {$session_id} END SUCCESS (" . count($preventivi) . " preventivi) ===");
            return $preventivi;
            
        } catch (\Exception $e) {
            $this->log("DEBUG SESSION {$session_id} EXCEPTION: " . $e->getMessage(), 'ERROR');
            $this->log("=== DEBUG SESSION {$session_id} END (ERROR) ===");
            $this->last_error = $e->getMessage();
            return array();
        }
    }

    // ============================================================================
    // ROUTINE DEBUG EXCEL AUTO - LETTURA SINGOLO FILE CON FALLBACK COMPLETO
    // ============================================================================

    /**
     * NUOVO: Debug lettura singolo file Excel con fallback completo
     * Routine completa per scansione Excel Auto come da specifiche del documento
     * 
     * @param string $file_id File ID di Google Drive
     * @return array ['ok' => bool, 'data' => array, 'log' => array, 'error' => string]
     */
    public function debug_read_single_excel($file_id) {
        $debug_enabled = defined('DISCO747_CRM_DEBUG') && DISCO747_CRM_DEBUG;
        $log = array();
        $temp_file_path = null;
        
        try {
            $timestamp = current_time('mysql');
            $session_id = 'SCAN_' . date('His') . '_' . wp_rand(100, 999);
            $log[] = "[{$timestamp}] === DEBUG EXCEL AUTO SESSION {$session_id} START ===";
            $log[] = "[{$timestamp}] INPUT: file_id = {$file_id}";
            
            if ($debug_enabled) {
                error_log("747 Disco DEBUG: debug_read_single_excel START per file_id={$file_id}");
            }

            // Step 1: Verifica disponibilità 
            if (!$this->sync_available || !$this->googledrive) {
                $error = "GoogleDrive sync non disponibile";
                $log[] = "[{$timestamp}] ERROR: {$error}";
                return array('ok' => false, 'data' => array(), 'log' => $log, 'error' => $error);
            }
            $log[] = "[{$timestamp}] DEBUG: GoogleDrive handler disponibile";

            // Step 2: Verifica credenziali salvate
            $log[] = "[{$timestamp}] DEBUG: Verifica credenziali OAuth Google Drive...";
            $credentials_check = $this->check_saved_credentials($log);
            if (!$credentials_check['ok']) {
                return array('ok' => false, 'data' => array(), 'log' => $log, 'error' => $credentials_check['error']);
            }

            // Step 3: Ottieni token di accesso
            $log[] = "[{$timestamp}] DEBUG: Recupero token di accesso valido...";
            try {
                $token = $this->googledrive->get_valid_access_token();
                if (!$token) {
                    throw new \Exception("Token di accesso nullo");
                }
                $log[] = "[{$timestamp}] DEBUG: Token ottenuto correttamente (lunghezza: " . strlen($token) . " caratteri)";
            } catch (\Exception $token_error) {
                $error = "Errore autenticazione: " . $token_error->getMessage();
                $log[] = "[{$timestamp}] ERROR: {$error}";
                $log[] = "[{$timestamp}] HINT: Verifica che le credenziali OAuth siano configurate correttamente nelle Impostazioni";
                return array('ok' => false, 'data' => array(), 'log' => $log, 'error' => $error);
            }

            // Step 4: Ricerca file su Google Drive
            $log[] = "[{$timestamp}] DEBUG: Ricerca file con ID {$file_id} su Google Drive...";
            $file_info = $this->get_file_info_by_id($file_id, $token, $log);
            if (!$file_info) {
                $error = "File non trovato o non accessibile";
                $log[] = "[{$timestamp}] ERROR: {$error}";
                return array('ok' => false, 'data' => array(), 'log' => $log, 'error' => $error);
            }
            $log[] = "[{$timestamp}] DEBUG: File trovato: " . $file_info['name'] . " (" . $file_info['size'] . " bytes)";

            // Step 5: Download file temporaneo
            $log[] = "[{$timestamp}] DEBUG: Inizio download file temporaneo...";
            $download_result = $this->download_excel_temp_file($file_id, $token, $log);
            if (!$download_result['success']) {
                $error = "Errore download: " . $download_result['error'];
                $log[] = "[{$timestamp}] ERROR: {$error}";
                return array('ok' => false, 'data' => array(), 'log' => $log, 'error' => $error);
            }
            $temp_file_path = $download_result['path'];
            $log[] = "[{$timestamp}] DEBUG: Download completato: " . basename($temp_file_path);

            // Step 6: Lettura campi Excel con PhpSpreadsheet
            $log[] = "[{$timestamp}] DEBUG: Inizio lettura campi Excel con PhpSpreadsheet...";
            $excel_result = $this->read_excel_fields($temp_file_path, $log, $file_info);
            if (!$excel_result['success']) {
                $error = "Errore lettura Excel: " . $excel_result['error'];
                $log[] = "[{$timestamp}] ERROR: {$error}";
                return array('ok' => false, 'data' => array(), 'log' => $log, 'error' => $error);
            }

            // Step 7: Preparazione dati finali
            $data = $excel_result['data'];
            $data['nome_file'] = $file_info['name'];
            $data['file_size'] = $file_info['size'];
            $data['file_id'] = $file_id;
            $data['modified_time'] = $file_info['modifiedTime'] ?? '';
            
            $log[] = "[{$timestamp}] DEBUG: ✅ Lettura Excel completata con successo";
            $log[] = "[{$timestamp}] === DEBUG EXCEL AUTO SESSION {$session_id} SUCCESS ===";
            
            if ($debug_enabled) {
                error_log("747 Disco DEBUG: debug_read_single_excel SUCCESS per file_id={$file_id}");
            }
            
            return array('ok' => true, 'data' => $data, 'log' => $log, 'error' => '');
            
        } catch (\Exception $e) {
            $timestamp = current_time('mysql');
            $error = "Exception: " . $e->getMessage();
            $log[] = "[{$timestamp}] EXCEPTION: {$error}";
            
            if ($debug_enabled) {
                error_log("747 Disco DEBUG: debug_read_single_excel EXCEPTION per file_id={$file_id} - " . $error);
            }
            
            return array('ok' => false, 'data' => array(), 'log' => $log, 'error' => $error);
            
        } finally {
            // Step 9: Cleanup sempre del file temporaneo
            if ($temp_file_path) {
                $this->cleanup_temp_file($temp_file_path, $log);
            }
        }
    }

    // ============================================================================
    // LETTURA EXCEL CON MAPPING CORRETTO
    // ============================================================================

    /**
     * Lettura campi specifici dal file Excel con tutti i fallback
     */
    private function read_excel_fields($temp_path, &$log, $file_info) {
        try {
            $timestamp = current_time('mysql');
            $log[] = "[{$timestamp}] DEBUG: Apertura file Excel con PhpSpreadsheet...";
            
            // Verifica che PhpSpreadsheet sia disponibile
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                return array('success' => false, 'error' => 'PhpSpreadsheet non disponibile');
            }
            
            // Configurazione reader per performance
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($temp_path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            // Carica il file
            $spreadsheet = $reader->load($temp_path);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $log[] = "[{$timestamp}] DEBUG: Excel aperto, foglio attivo trovato";
            
            // Determina template (nuovo/vecchio) controllando cella B1
            $template = $this->detect_template($worksheet, $log);
            
            $data = array();
            $data['template'] = $template;
            
            if ($template === 'nuovo') {
                $this->read_template_nuovo($worksheet, $data, $log);
            } else {
                $this->read_template_vecchio($worksheet, $data, $log, $file_info);
            }
            
            // Normalizzazione data con fallback se necessario
            $data['data_evento'] = $this->normalize_date($data['data_evento'] ?? null, $file_info, $log);
            
            $log[] = "[{$timestamp}] DEBUG: Lettura Excel completata";
            
            return array('success' => true, 'template' => $template, 'data' => $data);
            
        } catch (\Exception $e) {
            $timestamp = current_time('mysql');
            $log[] = "[{$timestamp}] ERROR: Errore lettura Excel: " . $e->getMessage();
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Rileva template (nuovo o vecchio) controllando cella B1
     */
    private function detect_template($worksheet, &$log) {
        try {
            $timestamp = current_time('mysql');
            $b1_value = $worksheet->getCell('B1')->getCalculatedValue();
            $b1_string = strtolower(trim($b1_value));
            
            $log[] = "[{$timestamp}] DEBUG: Controllo template - Cella B1: '{$b1_value}'";
            
            if (strpos($b1_string, 'menu') !== false) {
                $template = 'nuovo';
            } else {
                $template = 'vecchio';
            }
            
            $log[] = "[{$timestamp}] DEBUG: Template rilevato: {$template}";
            return $template;
            
        } catch (\Exception $e) {
            $log[] = "[{$timestamp}] WARNING: Errore rilevamento template, assumo 'vecchio': " . $e->getMessage();
            return 'vecchio';
        }
    }

    /**
     * ✅ CORRETTO: Leggi template NUOVO con mapping CORRETTO
     */
    private function read_template_nuovo($worksheet, &$data, &$log) {
        $timestamp = current_time('mysql');
        $log[] = "[{$timestamp}] DEBUG: Lettura template NUOVO con mapping CORRETTO...";
        
        try {
            // ✅ B1 → menu (string)
            $data['menu'] = trim($worksheet->getCell('B1')->getCalculatedValue());
            $log[] = "[{$timestamp}] DEBUG: Menu (B1): " . $data['menu'];
            
            // ✅ C6 → data_evento (gestisci seriale Excel o stringa; output Y-m-d)
            $data['data_evento'] = $this->read_date_cell($worksheet, 'C6', $log);
            
            // ✅ C7 → tipo_evento (string)
            $data['tipo_evento'] = trim($worksheet->getCell('C7')->getCalculatedValue());
            $log[] = "[{$timestamp}] DEBUG: Tipo evento (C7): " . $data['tipo_evento'];
            
            // ✅ C8 → orari_raw (string tipo "HH:MM - HH:MM" oppure numeri Excel)
            $data['orari_raw'] = $this->read_orari_cell($worksheet, 'C8', $log);
            $this->parse_orari($data, $log); // Separa orario_inizio e orario_fine
            
            // ✅ C9 → numero_invitati (int) - CORRETTO: era C8, ora C9
            $data['numero_invitati'] = $this->read_numeric_cell($worksheet, 'C9', $log);
            
            // ✅ C11 → referente_nome (string)
            $data['referente_nome'] = trim($worksheet->getCell('C11')->getCalculatedValue());
            $log[] = "[{$timestamp}] DEBUG: Nome referente (C11): " . $data['referente_nome'];
            
            // ✅ C12 → referente_cognome (string)
            $data['referente_cognome'] = trim($worksheet->getCell('C12')->getCalculatedValue());
            $log[] = "[{$timestamp}] DEBUG: Cognome referente (C12): " . $data['referente_cognome'];
            
            // ✅ C14 → telefono (string)
            $data['telefono'] = trim($worksheet->getCell('C14')->getCalculatedValue());
            $log[] = "[{$timestamp}] DEBUG: Telefono (C14): " . $data['telefono'];
            
            // ✅ C15 → email (string)
            $data['email'] = trim($worksheet->getCell('C15')->getCalculatedValue());
            $log[] = "[{$timestamp}] DEBUG: Email (C15): " . $data['email'];
            
            // ✅ F27 → importo_totale (float) - CORRETTO: era C27, ora F27
            $data['importo_totale'] = $this->read_numeric_cell($worksheet, 'F27', $log);
            
            // ✅ F28 → acconto (float)
            $data['acconto'] = $this->read_numeric_cell($worksheet, 'F28', $log);
            
            // ✅ F30 → da_saldare (float)
            $data['da_saldare'] = $this->read_numeric_cell($worksheet, 'F30', $log);
            
            // ✅ C17, C18, C19 → omaggi_list[] (string, solo non vuoti)
            $data['omaggi_list'] = array();
            $omaggi_cells = array('C17', 'C18', 'C19');
            foreach ($omaggi_cells as $cell) {
                $omaggio = trim($worksheet->getCell($cell)->getCalculatedValue());
                if (!empty($omaggio)) {
                    $data['omaggi_list'][] = $omaggio;
                    $log[] = "[{$timestamp}] DEBUG: Omaggio ({$cell}): " . $omaggio;
                }
            }
            
            // ✅ C33/F33, C34/F34, C35/F35 → extra_list[] = {descrizione, prezzo(float)}
            $data['extra_list'] = array();
            $extra_rows = array(33, 34, 35);
            foreach ($extra_rows as $row) {
                $descrizione = trim($worksheet->getCell("C{$row}")->getCalculatedValue());
                $prezzo = $this->read_numeric_cell($worksheet, "F{$row}", $log);
                
                if (!empty($descrizione) || $prezzo > 0) {
                    $data['extra_list'][] = array(
                        'descrizione' => $descrizione,
                        'prezzo' => $prezzo
                    );
                    $log[] = "[{$timestamp}] DEBUG: Extra (C{$row}/F{$row}): {$descrizione} - €{$prezzo}";
                }
            }
            
            // ✅ Determina STATO basato su filename e acconto
            $data['stato'] = $this->determine_stato_from_filename_and_acconto($data, $log);
            
            $log[] = "[{$timestamp}] DEBUG: ✅ Lettura template NUOVO completata con successo";
            
        } catch (\Exception $e) {
            $log[] = "[{$timestamp}] WARNING: Errore lettura template nuovo: " . $e->getMessage();
        }
    }

    /**
     * ✅ NUOVO: Leggi celle orari (C8) gestendo sia stringhe che numeri Excel
     */
    private function read_orari_cell($worksheet, $cell, &$log) {
        try {
            $timestamp = current_time('mysql');
            $cell_value = $worksheet->getCell($cell)->getCalculatedValue();
            
            if (empty($cell_value)) {
                $log[] = "[{$timestamp}] DEBUG: Cella orari {$cell} vuota";
                return '';
            }
            
            // Se è stringa "HH:MM - HH:MM"
            if (is_string($cell_value) && strpos($cell_value, ' - ') !== false) {
                $log[] = "[{$timestamp}] DEBUG: Orari {$cell}: '{$cell_value}' (stringa)";
                return trim($cell_value);
            }
            
            // Se è numero Excel (formato ora)
            if (is_numeric($cell_value)) {
                try {
                    $time = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cell_value);
                    $formatted = $time->format('H:i');
                    $log[] = "[{$timestamp}] DEBUG: Orari {$cell}: '{$formatted}' (da numero Excel)";
                    return $formatted;
                } catch (\Exception $e) {
                    $log[] = "[{$timestamp}] WARNING: Impossibile convertire numero Excel orario: " . $e->getMessage();
                }
            }
            
            // Fallback: restituisci come stringa
            $string_value = trim($cell_value);
            $log[] = "[{$timestamp}] DEBUG: Orari {$cell}: '{$string_value}' (fallback stringa)";
            return $string_value;
            
        } catch (\Exception $e) {
            $log[] = "[{$timestamp}] ERROR: Errore lettura orari {$cell}: " . $e->getMessage();
            return '';
        }
    }

    /**
     * ✅ NUOVO: Separa orari_raw in orario_inizio e orario_fine
     */
    private function parse_orari(&$data, &$log) {
        $timestamp = current_time('mysql');
        $orari_raw = $data['orari_raw'] ?? '';
        
        if (empty($orari_raw)) {
            $data['orario_inizio'] = '';
            $data['orario_fine'] = '';
            $log[] = "[{$timestamp}] DEBUG: Nessun orario da parsare";
            return;
        }
        
        // Se contiene " - " allora è formato "HH:MM - HH:MM"
        if (strpos($orari_raw, ' - ') !== false) {
            $parts = explode(' - ', $orari_raw);
            $data['orario_inizio'] = trim($parts[0] ?? '');
            $data['orario_fine'] = trim($parts[1] ?? '');
            $log[] = "[{$timestamp}] DEBUG: Orari separati: inizio='{$data['orario_inizio']}', fine='{$data['orario_fine']}'";
        } else {
            // Singolo orario, assume come orario_inizio
            $data['orario_inizio'] = trim($orari_raw);
            $data['orario_fine'] = '';
            $log[] = "[{$timestamp}] DEBUG: Singolo orario: inizio='{$data['orario_inizio']}'";
        }
    }

    /**
     * ✅ NUOVO: Determina stato preventivo basato su filename e acconto
     */
    private function determine_stato_from_filename_and_acconto($data, &$log) {
        $timestamp = current_time('mysql');
        $filename = $data['nome_file'] ?? '';
        $acconto = floatval($data['acconto'] ?? 0);
        
        // Confermato se filename inizia con "CONF " oppure acconto > 0
        if (stripos($filename, 'CONF ') === 0 || $acconto > 0) {
            $stato = 'Confermato';
            $reason = stripos($filename, 'CONF ') === 0 ? 'filename con CONF' : 'acconto presente';
        }
        // Annullato se filename inizia con "NO "
        elseif (stripos($filename, 'NO ') === 0) {
            $stato = 'Annullato';
            $reason = 'filename con NO';
        }
        // Altrimenti Attivo
        else {
            $stato = 'Attivo';
            $reason = 'default';
        }
        
        $log[] = "[{$timestamp}] DEBUG: Stato determinato: '{$stato}' ({$reason})";
        return $stato;
    }

    /**
     * Leggi template VECCHIO con tutti i fallback (MANTENUTO IDENTICO)
     */
    private function read_template_vecchio($worksheet, &$data, &$log, $file_info) {
        $timestamp = current_time('mysql');
        $log[] = "[{$timestamp}] DEBUG: Lettura template VECCHIO...";
        
        try {
            // Data evento: C4
            $data['data_evento'] = $this->read_date_cell($worksheet, 'C4', $log);
            
            // Tipo evento: C5
            $data['tipo_evento'] = trim($worksheet->getCell('C5')->getCalculatedValue());
            $log[] = "[{$timestamp}] DEBUG: Tipo evento (C5): " . $data['tipo_evento'];
            
            // Menu: testo in A18 (tra doppi apici "...")
            $data['menu'] = $this->read_menu_from_a18($worksheet, $log);
            
            // Importo totale: prova C25, se vuota fallback
            $data['importo_totale'] = $this->read_importo_with_fallback($worksheet, $log);
            
            // Acconto: F23
            $data['acconto'] = $this->read_numeric_cell($worksheet, 'F23', $log);
            
            // Numero invitati: C8, se vuota fallback
            $data['numero_invitati'] = $this->read_invitati_with_fallback($worksheet, $log);
            
        } catch (\Exception $e) {
            $log[] = "[{$timestamp}] WARNING: Errore lettura template vecchio: " . $e->getMessage();
        }
    }

    // ============================================================================
    // METODI HELPER SUPPORTO (tutti i metodi esistenti mantenuti)
    // ============================================================================

    /**
     * Leggi cella data con gestione formati
     */
    private function read_date_cell($worksheet, $cell, &$log) {
        try {
            $timestamp = current_time('mysql');
            $cell_value = $worksheet->getCell($cell)->getCalculatedValue();
            
            if (empty($cell_value)) {
                $log[] = "[{$timestamp}] DEBUG: Cella data {$cell} vuota";
                return null;
            }
            
            // Se è già un oggetto DateTime
            if ($cell_value instanceof \DateTime) {
                $formatted = $cell_value->format('Y-m-d');
                $log[] = "[{$timestamp}] DEBUG: Data {$cell}: {$formatted} (da DateTime)";
                return $formatted;
            }
            
            // Se è un numero di serie Excel
            if (is_numeric($cell_value)) {
                try {
                    $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cell_value);
                    $formatted = $date->format('Y-m-d');
                    $log[] = "[{$timestamp}] DEBUG: Data {$cell}: {$formatted} (da numero Excel)";
                    return $formatted;
                } catch (\Exception $e) {
                    $log[] = "[{$timestamp}] WARNING: Impossibile convertire numero Excel data: " . $e->getMessage();
                }
            }
            
            // Prova parsing stringa
            $date_str = trim($cell_value);
            $parsed_date = date_create($date_str);
            if ($parsed_date) {
                $formatted = $parsed_date->format('Y-m-d');
                $log[] = "[{$timestamp}] DEBUG: Data {$cell}: {$formatted} (da stringa)";
                return $formatted;
            }
            
            $log[] = "[{$timestamp}] WARNING: Formato data non riconosciuto in {$cell}: '{$cell_value}'";
            return null;
            
        } catch (\Exception $e) {
            $log[] = "[{$timestamp}] ERROR: Errore lettura data {$cell}: " . $e->getMessage();
            return null;
        }
    }

    /**
     * Leggi cella numerica
     */
    private function read_numeric_cell($worksheet, $cell, &$log) {
        try {
            $timestamp = current_time('mysql');
            $cell_value = $worksheet->getCell($cell)->getCalculatedValue();
            
            if (empty($cell_value) && $cell_value !== 0) {
                $log[] = "[{$timestamp}] DEBUG: Cella numerica {$cell} vuota";
                return 0;
            }
            
            $numeric_value = floatval($cell_value);
            $log[] = "[{$timestamp}] DEBUG: Valore numerico {$cell}: {$numeric_value}";
            return $numeric_value;
            
        } catch (\Exception $e) {
            $log[] = "[{$timestamp}] WARNING: Errore lettura cella numerica {$cell}: " . $e->getMessage();
            return 0;
        }
    }

    // ============================================================================
    // TUTTI GLI ALTRI METODI ESISTENTI (mantenuti identici per non rompere nulla)
    // ============================================================================

    /**
     * Scansione ricorsiva di tutte le cartelle per trovare file Excel
     */
    private function scan_all_excel_files_recursive($folder_id, $token) {
        try {
            $this->log("Inizio scansione ricorsiva cartella: {$folder_id}");
            
            $all_preventivi = array();
            
            // Prima cerca file Excel nella cartella corrente
            $excel_files = $this->get_excel_files_in_folder($folder_id, $token);
            
            foreach ($excel_files as $file) {
                $preventivo = $this->create_preventivo_from_file($file, $folder_id, $token);
                if ($preventivo) {
                    $all_preventivi[] = $preventivo;
                }
            }
            
            // Poi cerca nelle sottocartelle
            $subfolders = $this->get_subfolders($folder_id, $token);
            foreach ($subfolders as $subfolder) {
                $preventivi_subfolder = $this->scan_all_excel_files_recursive($subfolder['id'], $token);
                $all_preventivi = array_merge($all_preventivi, $preventivi_subfolder);
            }
            
            return $all_preventivi;
            
        } catch (\Exception $e) {
            $this->log("Errore scansione ricorsiva: " . $e->getMessage(), 'ERROR');
        }
        
        return array();
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
     * Crea preventivo da file
     */
    private function create_preventivo_from_file($file, $folder_id, $token) {
        try {
            // Logica semplificata per compatibilità con il sistema esistente
            return array(
                'googledrive_id' => $file['id'],
                'filename' => $file['name'],
                'file_size' => isset($file['size']) ? intval($file['size']) : 0,
                'modified_time' => $file['modifiedTime'] ?? '',
                'data_evento' => $this->extract_date_from_filename($file['name']),
                'tipo_evento' => $this->extract_event_type_from_filename($file['name']),
                'menu' => $this->extract_menu_from_filename($file['name']),
                'stato_preventivo' => $this->determine_status_from_filename($file['name']),
                'folder_id' => $folder_id
            );
            
        } catch (\Exception $e) {
            $this->log("Errore creazione preventivo da file: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    // TUTTI GLI ALTRI METODI HELPER MANTENUTI IDENTICI...
    // (extract_date_from_filename, extract_event_type_from_filename, ecc.)

    /**
     * Estrai data dal nome file
     */
    private function extract_date_from_filename($filename) {
        if (preg_match('/(\d{1,2})_(\d{1,2})/', $filename, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = date('Y');
            return "{$year}-{$month}-{$day}";
        }
        return null;
    }

    /**
     * Estrai tipo evento dal nome file
     */
    private function extract_event_type_from_filename($filename) {
        $clean_filename = preg_replace('/^(CONF\s|NO\s)/', '', $filename);
        $clean_filename = preg_replace('/\s*\(Menu\s+\d+\)\.xlsx?$/i', '', $clean_filename);
        $clean_filename = preg_replace('/^\d{1,2}_\d{1,2}\s+/', '', $clean_filename);
        return trim($clean_filename);
    }

    /**
     * Estrai menu dal nome file
     */
    private function extract_menu_from_filename($filename) {
        if (preg_match('/\(Menu\s+(\d+)\)/i', $filename, $matches)) {
            return "Menu " . $matches[1];
        }
        return "Menu 7";
    }

    /**
     * Determina stato dal nome file
     */
    private function determine_status_from_filename($filename) {
        if (stripos($filename, 'CONF ') === 0) {
            return 'Confermato';
        } elseif (stripos($filename, 'NO ') === 0) {
            return 'Annullato';
        }
        return 'Attivo';
    }

    // Altri metodi mantenuti identici per compatibilità...
    // (check_saved_credentials, get_file_info_by_id, download_excel_temp_file, 
    //  cleanup_temp_file, normalize_date, read_menu_from_a18, read_importo_with_fallback,
    //  read_invitati_with_fallback)

    /**
     * Verifica credenziali salvate (struttura corretta)
     */
    private function check_saved_credentials(&$log) {
        $timestamp = current_time('mysql');
        
        $gd_credentials = get_option('disco747_gd_credentials', array());
        $client_id = $gd_credentials['client_id'] ?? '';
        $client_secret = $gd_credentials['client_secret'] ?? '';
        $refresh_token = $gd_credentials['refresh_token'] ?? '';
        
        if (empty($client_id)) {
            $error = "Client ID Google Drive non configurato";
            $log[] = "[{$timestamp}] ERROR: {$error}";
            return array('ok' => false, 'error' => $error);
        }
        
        if (empty($client_secret)) {
            $error = "Client Secret Google Drive non configurato";
            $log[] = "[{$timestamp}] ERROR: {$error}";
            return array('ok' => false, 'error' => $error);
        }
        
        if (empty($refresh_token)) {
            $error = "Refresh Token Google Drive non configurato";
            $log[] = "[{$timestamp}] ERROR: {$error}";
            $log[] = "[{$timestamp}] HINT: Completa l'autorizzazione OAuth nelle Impostazioni";
            return array('ok' => false, 'error' => $error);
        }
        
        $log[] = "[{$timestamp}] DEBUG: Credenziali OAuth presenti e configurate";
        return array('ok' => true, 'error' => '');
    }

    /**
     * Ottieni informazioni file by ID
     */
    private function get_file_info_by_id($file_id, $token, &$log) {
        try {
            $timestamp = current_time('mysql');
            
            $url = 'https://www.googleapis.com/drive/v3/files/' . $file_id . '?' . http_build_query(array(
                'fields' => 'id,name,size,modifiedTime,parents,mimeType'
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                $log[] = "[{$timestamp}] ERROR: Errore HTTP durante ricerca file: " . $response->get_error_message();
                return null;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $log[] = "[{$timestamp}] ERROR: HTTP {$response_code} durante ricerca file";
                return null;
            }
            
            $body = wp_remote_retrieve_body($response);
            $file_data = json_decode($body, true);
            
            if (!$file_data || !isset($file_data['id'])) {
                $log[] = "[{$timestamp}] ERROR: Risposta API non valida o file non trovato";
                return null;
            }
            
            $log[] = "[{$timestamp}] DEBUG: File info ottenute: " . $file_data['name'];
            return $file_data;
            
        } catch (\Exception $e) {
            $log[] = "[{$timestamp}] EXCEPTION: get_file_info_by_id - " . $e->getMessage();
            return null;
        }
    }

    /**
     * Download file temporaneo per lettura Excel
     */
    private function download_excel_temp_file($file_id, $token, &$log) {
        try {
            $timestamp = current_time('mysql');
            
            // Directory temporanea WordPress
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/disco747-temp';
            if (!is_dir($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $temp_filename = 'excel_' . $file_id . '_' . time() . '_' . wp_rand(1000, 9999) . '.xlsx';
            $temp_path = $temp_dir . '/' . $temp_filename;
            
            $log[] = "[{$timestamp}] DEBUG: Path temporaneo: {$temp_path}";
            
            // URL download diretto da Google Drive
            $download_url = 'https://www.googleapis.com/drive/v3/files/' . $file_id . '?alt=media';
            
            $log[] = "[{$timestamp}] DEBUG: Avvio download da: {$download_url}";
            
            // Download via wp_remote_get
            $response = wp_remote_get($download_url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'error' => 'Errore HTTP: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return array('success' => false, 'error' => 'HTTP ' . $response_code);
            }
            
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                return array('success' => false, 'error' => 'Contenuto file vuoto');
            }
            
            // Salva file temporaneo
            $written = file_put_contents($temp_path, $body);
            if ($written === false) {
                return array('success' => false, 'error' => 'Impossibile scrivere file temporaneo');
            }
            
            $log[] = "[{$timestamp}] DEBUG: File scaricato, dimensione: " . strlen($body) . " bytes";
            
            return array('success' => true, 'path' => $temp_path);

        } catch (\Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Cleanup file temporaneo
     */
    private function cleanup_temp_file($temp_path, &$log) {
        $timestamp = current_time('mysql');
        
        if (file_exists($temp_path)) {
            if (unlink($temp_path)) {
                $log[] = "[{$timestamp}] DEBUG: File temporaneo eliminato: " . basename($temp_path);
            } else {
                $log[] = "[{$timestamp}] WARNING: Impossibile eliminare file temporaneo: " . basename($temp_path);
            }
        }
    }

    /**
     * Normalizza data con fallback dal filename
     */
    private function normalize_date($data_evento, $file_info, &$log) {
        $timestamp = current_time('mysql');
        
        if (!empty($data_evento)) {
            $log[] = "[{$timestamp}] DEBUG: Data già disponibile: {$data_evento}";
            return $data_evento;
        }
        
        // Fallback: estrai dal filename
        if (!empty($file_info['name'])) {
            $extracted_date = $this->extract_date_from_filename($file_info['name']);
            if ($extracted_date) {
                $log[] = "[{$timestamp}] DEBUG: Data estratta da filename: {$extracted_date}";
                return $extracted_date;
            }
        }
        
        $log[] = "[{$timestamp}] WARNING: Impossibile determinare data evento";
        return null;
    }

    /**
     * Altri metodi helper per il template vecchio mantenuti identici...
     */
    
    private function read_menu_from_a18($worksheet, &$log) {
        try {
            $timestamp = current_time('mysql');
            $a18_value = trim($worksheet->getCell('A18')->getCalculatedValue());
            
            $log[] = "[{$timestamp}] DEBUG: Cella A18 raw: '{$a18_value}'";
            
            if (empty($a18_value)) {
                $log[] = "[{$timestamp}] DEBUG: Cella A18 vuota, menu sconosciuto";
                return 'Menu 7';
            }
            
            // Cerca testo tra doppi apici
            if (preg_match('/"([^"]+)"/', $a18_value, $matches)) {
                $menu_text = trim($matches[1]);
                $log[] = "[{$timestamp}] DEBUG: Menu estratto da A18: '{$menu_text}'";
                
                // Mappa stringhe specifiche
                $menu_mappings = array(
                    '7-4' => 'Menu 74',
                    '747' => 'Menu 747',
                    '7' => 'Menu 7'
                );
                
                foreach ($menu_mappings as $pattern => $menu_name) {
                    if (strpos($menu_text, $pattern) !== false) {
                        $log[] = "[{$timestamp}] DEBUG: Menu mappato: {$pattern} → {$menu_name}";
                        return $menu_name;
                    }
                }
                
                return $menu_text;
            }
            
            $log[] = "[{$timestamp}] DEBUG: Nessun testo tra apici trovato in A18, assumo Menu 7";
            return 'Menu 7';
            
        } catch (\Exception $e) {
            $log[] = "[{$timestamp}] WARNING: Errore lettura menu A18: " . $e->getMessage();
            return 'Menu 7';
        }
    }

    private function read_importo_with_fallback($worksheet, &$log) {
        try {
            $timestamp = current_time('mysql');
            
            // Prima prova C25
            $c25_value = $this->read_numeric_cell($worksheet, 'C25', $log);
            if ($c25_value > 0) {
                return $c25_value;
            }
            
            $log[] = "[{$timestamp}] DEBUG: C25 vuota, attivo fallback ricerca 'Totale'...";
            
            // Fallback: cerca "Totale" nelle prime 50 righe, colonne A-H
            for ($row = 1; $row <= 50; $row++) {
                for ($col = 'A'; $col <= 'H'; $col++) {
                    try {
                        $cell_address = $col . $row;
                        $cell_value = trim($worksheet->getCell($cell_address)->getCalculatedValue());
                        
                        if (stripos($cell_value, 'totale') !== false) {
                            $log[] = "[{$timestamp}] DEBUG: Trovato 'Totale' in {$cell_address}: '{$cell_value}'";
                            
                            // Cerca valore adiacente (destra e sotto)
                            $adjacent_cells = array(
                                chr(ord($col) + 1) . $row,  // destra
                                $col . ($row + 1)           // sotto
                            );
                            
                            foreach ($adjacent_cells as $adj_cell) {
                                if (ord($adj_cell[0]) > ord('H')) continue; // non oltre colonna H
                                
                                try {
                                    $adj_value = $this->read_numeric_cell($worksheet, $adj_cell, $log);
                                    if ($adj_value > 0) {
                                        $log[] = "[{$timestamp}] DEBUG: Valore totale trovato in {$adj_cell}: {$adj_value} (FALLBACK)";
                                        return $adj_value;
                                    }
                                } catch (\Exception $e) {
                                    // Ignora errori celle adiacenti
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignora errori singole celle
                    }
                }
            }
            
            $log[] = "[{$timestamp}] WARNING: Fallback 'Totale' non ha trovato risultati";
            return 0;
            
        } catch (\Exception $e) {
            $log[] = "[{$timestamp}] ERROR: Errore lettura importo con fallback: " . $e->getMessage();
            return 0;
        }
    }

    private function read_invitati_with_fallback($worksheet, &$log) {
        try {
            $timestamp = current_time('mysql');
            
            // Prima prova C8
            $c8_value = $this->read_numeric_cell($worksheet, 'C8', $log);
            if ($c8_value > 0) {
                return $c8_value;
            }
            
            $log[] = "[{$timestamp}] DEBUG: C8 vuota, attivo fallback ricerca 'invitati'...";
            
            // Fallback: cerca "invitati" nelle prime 50 righe, colonne A-H
            for ($row = 1; $row <= 50; $row++) {
                for ($col = 'A'; $col <= 'H'; $col++) {
                    try {
                        $cell_address = $col . $row;
                        $cell_value = trim($worksheet->getCell($cell_address)->getCalculatedValue());
                        
                        if (stripos($cell_value, 'invitati') !== false) {
                            $log[] = "[{$timestamp}] DEBUG: Trovato 'invitati' in {$cell_address}: '{$cell_value}'";
                            
                            // Cerca valore adiacente (destra e sotto)
                            $adjacent_cells = array(
                                chr(ord($col) + 1) . $row,  // destra
                                $col . ($row + 1)           // sotto
                            );
                            
                            foreach ($adjacent_cells as $adj_cell) {
                                if (ord($adj_cell[0]) > ord('H')) continue; // non oltre colonna H
                                
                                try {
                                    $adj_value = $this->read_numeric_cell($worksheet, $adj_cell, $log);
                                    if ($adj_value > 0) {
                                        $log[] = "[{$timestamp}] DEBUG: Numero invitati trovato in {$adj_cell}: {$adj_value} (FALLBACK)";
                                        return $adj_value;
                                    }
                                } catch (\Exception $e) {
                                    // Ignora errori celle adiacenti
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignora errori singole celle
                    }
                }
            }
            
            $log[] = "[{$timestamp}] WARNING: Fallback 'invitati' non ha trovato risultati";
            return 0;
            
        } catch (\Exception $e) {
            $log[] = "[{$timestamp}] ERROR: Errore lettura invitati con fallback: " . $e->getMessage();
            return 0;
        }
    }
}
?>