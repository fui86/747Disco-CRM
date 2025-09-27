<?php
/**
 * Handler dedicato per la scansione Excel di 747 Disco CRM
 * CLASSE INDIPENDENTE - NON MODIFICA FUNZIONALITÀ ESISTENTI
 * 
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @since      11.4.2
 */

namespace Disco747_CRM\Handlers;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe per gestire la scansione automatica dei file Excel da Google Drive
 */
class Disco747_Excel_Scan_Handler {
    
    /**
     * Nome della tabella per l'analisi Excel
     */
    private $table_name;
    
    /**
     * Costruttore
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'disco747_excel_analysis';
        
        // Registra hooks AJAX
        add_action('wp_ajax_disco747_batch_scan_excel', array($this, 'handle_batch_scan_ajax'));
        add_action('wp_ajax_disco747_single_scan_excel', array($this, 'handle_single_scan_ajax'));
        
        // Crea la tabella se non esiste
        $this->create_table_if_not_exists();
    }
    
    /**
     * Crea la tabella per l'analisi Excel se non esiste
     */
    private function create_table_if_not_exists() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            file_id VARCHAR(128) NULL,
            filename VARCHAR(255) NOT NULL,
            drive_path VARCHAR(512) NULL,
            modified_time DATETIME NULL,
            data_evento DATE NULL,
            tipo_evento VARCHAR(100) NULL,
            tipo_menu VARCHAR(50) NULL,
            orario VARCHAR(50) NULL,
            numero_invitati INT NULL,
            nome_referente VARCHAR(100) NULL,
            cognome_referente VARCHAR(100) NULL,
            cellulare VARCHAR(50) NULL,
            email VARCHAR(150) NULL,
            omaggio1 VARCHAR(255) NULL,
            omaggio2 VARCHAR(255) NULL,
            omaggio3 VARCHAR(255) NULL,
            importo DECIMAL(10,2) NULL,
            acconto DECIMAL(10,2) NULL,
            saldo DECIMAL(10,2) NULL,
            extra1_nome VARCHAR(255) NULL,
            extra1_prezzo DECIMAL(10,2) NULL,
            extra2_nome VARCHAR(255) NULL,
            extra2_prezzo DECIMAL(10,2) NULL,
            extra3_nome VARCHAR(255) NULL,
            extra3_prezzo DECIMAL(10,2) NULL,
            analysis_success TINYINT(1) DEFAULT 0,
            analysis_errors_json LONGTEXT NULL,
            source VARCHAR(30) DEFAULT 'drive',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_file_id (file_id),
            KEY idx_filename (filename),
            KEY idx_data_evento (data_evento),
            KEY idx_analysis_success (analysis_success)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Handler AJAX per scansione batch
     */
    public function handle_batch_scan_ajax() {
        // Verifica nonce
        if (!check_ajax_referer('disco747_excel_scan', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce non valido'));
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
            return;
        }
        
        try {
            $dry_run = isset($_POST['dry_run']) ? intval($_POST['dry_run']) === 1 : false;
            $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
            
            error_log("Disco747 Excel Scan - Avvio scansione batch - dry_run: {$dry_run}, file_id: {$file_id}");
            
            // Inizializza contatori
            $counters = array(
                'listed' => 0,
                'downloaded' => 0,
                'parsed_ok' => 0,
                'saved_ok' => 0,
                'errors' => 0
            );
            
            $errors = array();
            $results = array();
            
            // Prova a trovare file Excel esistenti per simulazione
            $excel_files = $this->find_excel_files_simulation();
            $counters['listed'] = count($excel_files);
            
            error_log("Disco747 Excel Scan - Trovati {$counters['listed']} file Excel da simulare");
            
            // Simula processo di scansione per ora
            foreach ($excel_files as $i => $file) {
                try {
                    error_log("Disco747 Excel Scan - Processando file simulato: {$file['name']}");
                    
                    $counters['downloaded']++;
                    
                    // Simula parsing Excel
                    $parsed_data = $this->simulate_excel_parsing($file);
                    if (!$parsed_data) {
                        $errors[] = "Impossibile parsare file: {$file['name']}";
                        $counters['errors']++;
                        continue;
                    }
                    
                    $counters['parsed_ok']++;
                    
                    // Salva nel database se non è dry run
                    if (!$dry_run) {
                        $analysis_id = $this->save_excel_analysis($parsed_data);
                        if ($analysis_id) {
                            $counters['saved_ok']++;
                            $results[] = array(
                                'analysis_id' => $analysis_id,
                                'filename' => $file['name'],
                                'data' => $parsed_data
                            );
                        } else {
                            $errors[] = "Impossibile salvare analisi per: {$file['name']}";
                            $counters['errors']++;
                        }
                    } else {
                        $counters['saved_ok']++;
                    }
                    
                    // Simula rate limiting
                    if ($i < count($excel_files) - 1) {
                        usleep(100000); // 100ms
                    }
                    
                } catch (Exception $e) {
                    $error_msg = "Errore processando {$file['name']}: " . $e->getMessage();
                    $errors[] = $error_msg;
                    error_log("Disco747 Excel Scan - {$error_msg}");
                    $counters['errors']++;
                }
            }
            
            error_log("Disco747 Excel Scan - Completata - Parsed: {$counters['parsed_ok']}, Saved: {$counters['saved_ok']}, Errors: {$counters['errors']}");
            
            wp_send_json_success(array(
                'counters' => $counters,
                'results' => $results,
                'errors' => array_slice($errors, 0, 3),
                'message' => "Scansione completata: {$counters['saved_ok']} file salvati, {$counters['errors']} errori"
            ));
            
        } catch (Exception $e) {
            error_log('Disco747 Excel Scan - Errore scansione batch: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Errore interno: ' . $e->getMessage()));
        }
    }
    
    /**
     * Handler AJAX per scansione singolo file
     */
    public function handle_single_scan_ajax() {
        // Reindirizza alla scansione batch con singolo file
        $this->handle_batch_scan_ajax();
    }
    
    /**
     * Simula la ricerca di file Excel (da sostituire con logica reale)
     */
    private function find_excel_files_simulation() {
        // Per ora restituisce file simulati per testare l'interfaccia
        $files = array();
        
        $sample_files = array(
            'CONF 15_10 Compleanno Sara (Menu 747).xlsx',
            '20_10 Matrimonio Rossi (Menu 74).xlsx',
            'CONF 25_10 Festa Aziendale ABC (Menu 7).xlsx',
            '30_10 Laurea Marco (Menu 747).xlsx',
            'CONF 05_11 Compleanno Giulia (Menu 74).xlsx'
        );
        
        foreach ($sample_files as $i => $filename) {
            $files[] = array(
                'id' => 'file_' . ($i + 1),
                'name' => $filename,
                'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'modifiedTime' => date('Y-m-d\TH:i:s\Z', strtotime("-" . ($i * 2) . " days"))
            );
        }
        
        return $files;
    }
    
    /**
     * Simula il parsing di un file Excel
     */
    private function simulate_excel_parsing($file_info) {
        try {
            // Estrae informazioni dal nome del file
            $filename = $file_info['name'];
            $is_confirmed = strpos($filename, 'CONF ') === 0;
            
            // Estrae data dal filename (formato: [CONF ]DD_MM)
            $date_match = array();
            if (preg_match('/(?:CONF\s+)?(\d{1,2}_\d{1,2})/', $filename, $date_match)) {
                $date_parts = explode('_', $date_match[1]);
                $day = str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
                $month = str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
                $year = date('Y');
                $data_evento = "{$year}-{$month}-{$day}";
            } else {
                $data_evento = date('Y-m-d', strtotime('+1 month'));
            }
            
            // Estrae tipo evento
            $tipo_evento = 'Evento Generico';
            if (strpos($filename, 'Compleanno') !== false) {
                $tipo_evento = 'Compleanno';
            } elseif (strpos($filename, 'Matrimonio') !== false) {
                $tipo_evento = 'Matrimonio';
            } elseif (strpos($filename, 'Festa Aziendale') !== false) {
                $tipo_evento = 'Festa Aziendale';
            } elseif (strpos($filename, 'Laurea') !== false) {
                $tipo_evento = 'Laurea';
            }
            
            // Estrae menu dal filename (formato: (Menu XX))
            $tipo_menu = 'Menu 747'; // Default
            if (preg_match('/\(Menu\s+(\d+)\)/', $filename, $matches)) {
                $tipo_menu = 'Menu ' . $matches[1];
            }
            
            // Genera dati simulati realistici
            $nomi = array('Mario', 'Giulia', 'Francesco', 'Sarah', 'Marco', 'Valentina', 'Luca', 'Chiara');
            $cognomi = array('Rossi', 'Bianchi', 'Verdi', 'Neri', 'Bruno', 'Romano', 'Gallo', 'Conti');
            
            $nome_idx = crc32($filename) % count($nomi);
            $cognome_idx = crc32($filename . 'surname') % count($cognomi);
            
            $nome_referente = $nomi[$nome_idx];
            $cognome_referente = $cognomi[$cognome_idx];
            
            // Prezzi basati sul tipo di menu
            $prezzi_base = array(
                'Menu 7' => 25.00,
                'Menu 74' => 35.00,
                'Menu 747' => 45.00
            );
            
            $prezzo_base = isset($prezzi_base[$tipo_menu]) ? $prezzi_base[$tipo_menu] : 35.00;
            $numero_invitati = rand(20, 100);
            $importo = $prezzo_base * $numero_invitati;
            $acconto = $is_confirmed ? ($importo * 0.3) : 0;
            $saldo = $importo - $acconto;
            
            // Crea array dati
            $data = array(
                'file_id' => $file_info['id'],
                'filename' => $filename,
                'modified_time' => date('Y-m-d H:i:s', strtotime($file_info['modifiedTime'])),
                'data_evento' => $data_evento,
                'tipo_evento' => $tipo_evento,
                'tipo_menu' => $tipo_menu,
                'orario' => '20:00 - 01:00',
                'numero_invitati' => $numero_invitati,
                'nome_referente' => $nome_referente,
                'cognome_referente' => $cognome_referente,
                'cellulare' => '+39 3' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(1000, 9999),
                'email' => strtolower($nome_referente . '.' . $cognome_referente . '@example.com'),
                'omaggio1' => 'Torta compleanno',
                'omaggio2' => 'Decorazioni tavoli',
                'omaggio3' => null,
                'importo' => $importo,
                'acconto' => $acconto,
                'saldo' => $saldo,
                'extra1_nome' => 'Servizio fotografico',
                'extra1_prezzo' => 200.00,
                'extra2_nome' => null,
                'extra2_prezzo' => null,
                'extra3_nome' => null,
                'extra3_prezzo' => null,
                'analysis_success' => 1,
                'analysis_errors_json' => json_encode(array()),
                'source' => 'drive',
                'drive_path' => '/747-Preventivi/' . date('Y') . '/' . date('m') . '/'
            );
            
            error_log("Disco747 Excel Scan - Dati simulati per: {$filename} - Evento: {$tipo_evento}, Importo: €" . number_format($importo, 2));
            
            return $data;
            
        } catch (Exception $e) {
            error_log('Disco747 Excel Scan - Errore simulazione parsing: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva i dati di analisi Excel nel database
     */
    private function save_excel_analysis($data) {
        global $wpdb;
        
        try {
            // Rimuovi campi che non appartengono alla tabella
            $table_data = array_intersect_key($data, array_flip(array(
                'file_id', 'filename', 'drive_path', 'modified_time', 'data_evento',
                'tipo_evento', 'tipo_menu', 'orario', 'numero_invitati', 'nome_referente',
                'cognome_referente', 'cellulare', 'email', 'omaggio1', 'omaggio2', 'omaggio3',
                'importo', 'acconto', 'saldo', 'extra1_nome', 'extra1_prezzo', 'extra2_nome',
                'extra2_prezzo', 'extra3_nome', 'extra3_prezzo', 'analysis_success',
                'analysis_errors_json', 'source', 'drive_path'
            )));
            
            // Prova prima un UPDATE se esiste già un record con lo stesso file_id
            if (!empty($data['file_id'])) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$this->table_name} WHERE file_id = %s",
                    $data['file_id']
                ));
                
                if ($existing) {
                    $result = $wpdb->update(
                        $this->table_name,
                        $table_data,
                        array('file_id' => $data['file_id']),
                        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%f', '%s', '%f', '%s', '%f', '%d', '%s', '%s', '%s'),
                        array('%s')
                    );
                    
                    return $result !== false ? $existing : false;
                }
            }
            
            // INSERT nuovo record
            $result = $wpdb->insert(
                $this->table_name,
                $table_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%f', '%s', '%f', '%s', '%f', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                error_log("Disco747 Excel Scan - Errore inserimento database: " . $wpdb->last_error);
                return false;
            }
            
            $insert_id = $wpdb->insert_id;
            error_log("Disco747 Excel Scan - Analisi salvata con ID: {$insert_id}");
            
            return $insert_id;
            
        } catch (Exception $e) {
            error_log('Disco747 Excel Scan - Errore salvataggio analisi: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottiene tutte le analisi Excel dal database
     */
    public function get_excel_analysis($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'menu_filter' => '',
            'status_filter' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        // Filtro ricerca
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(nome_referente LIKE %s OR cognome_referente LIKE %s OR email LIKE %s OR cellulare LIKE %s OR tipo_evento LIKE %s)";
            $where_values = array_merge($where_values, array($search, $search, $search, $search, $search));
        }
        
        // Filtro menu
        if (!empty($args['menu_filter'])) {
            $where_conditions[] = "tipo_menu = %s";
            $where_values[] = $args['menu_filter'];
        }
        
        // Filtro stato
        if (!empty($args['status_filter'])) {
            if ($args['status_filter'] === 'confirmed') {
                $where_conditions[] = "acconto > 0";
            } elseif ($args['status_filter'] === 'pending') {
                $where_conditions[] = "analysis_success = 1 AND (acconto IS NULL OR acconto <= 0)";
            } elseif ($args['status_filter'] === 'error') {
                $where_conditions[] = "analysis_success = 0";
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $order_clause = sprintf('ORDER BY %s %s', $args['orderby'], $args['order']);
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} {$order_clause} {$limit_clause}";
        
        if (!empty($where_values)) {
            $prepared_query = $wpdb->prepare($query, $where_values);
        } else {
            $prepared_query = $query;
        }
        
        return $wpdb->get_results($prepared_query, OBJECT);
    }
    
    /**
     * Ottiene una singola analisi Excel per ID
     */
    public function get_excel_analysis_by_id($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ), OBJECT);
    }
    
    /**
     * Log delle attività
     */
    private function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Disco747 Excel Scan [{$level}]: {$message}");
        }
    }
}

// Inizializza l'handler
new Disco747_Excel_Scan_Handler();