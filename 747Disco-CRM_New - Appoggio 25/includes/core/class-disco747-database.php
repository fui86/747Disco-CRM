<?php
/**
 * Classe per la gestione del database del plugin 747 Disco CRM
 * Include gestione preventivi + NUOVA tabella Excel Analysis
 *
 * @package    Disco747_CRM
 * @subpackage Core
 * @since      11.4.2
 * @version    11.4.2
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Core;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_Database
 * 
 * Gestisce tutte le operazioni database del plugin
 * Include tabelle preventivi + NUOVA Excel Analysis
 * 
 * @since 11.4.2
 */
class Disco747_Database {
    
    /**
     * Istanza WordPress Database
     */
    private $wpdb;
    
    /**
     * Nomi tabelle
     */
    private $table_preventivi;
    private $table_messages;
    private $table_logs;
    private $table_excel_analysis; // NUOVA TABELLA
    
    /**
     * Configurazione
     */
    private $debug_mode = true;
    
    /**
     * Costruttore
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Definisce nomi tabelle
        $this->table_preventivi = $this->wpdb->prefix . 'disco747_preventivi';
        $this->table_messages = $this->wpdb->prefix . 'disco747_messages';
        $this->table_logs = $this->wpdb->prefix . 'disco747_logs';
        $this->table_excel_analysis = $this->wpdb->prefix . 'disco747_excel_analysis'; // NUOVA
        
        $this->log('[747Disco-DB] Database handler inizializzato');
    }
    
    // ============================================================================
    // GESTIONE SCHEMA E TABELLE
    // ============================================================================
    
    /**
     * Crea tutte le tabelle necessarie
     */
    public function create_tables() {
        $this->log('[747Disco-DB] Creazione tabelle...');
        
        try {
            $this->create_preventivi_table();
            $this->create_messages_table();
            $this->create_logs_table();
            $this->create_excel_analysis_table_complete(); // NUOVA
            
            $this->log('[747Disco-DB] Tutte le tabelle create/verificate');
            return true;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-DB] Errore creazione tabelle: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Crea tabella preventivi (esistente)
     */
    private function create_preventivi_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_preventivi} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nome_cliente VARCHAR(255) NOT NULL,
            telefono VARCHAR(50),
            email VARCHAR(255),
            data_evento DATE NOT NULL,
            tipo_evento VARCHAR(255),
            tipo_menu VARCHAR(50),
            numero_invitati INT(11),
            orario_evento VARCHAR(100),
            importo_preventivo DECIMAL(10,2),
            acconto_versato DECIMAL(10,2) DEFAULT 0,
            omaggio1 VARCHAR(500),
            omaggio2 VARCHAR(500),
            omaggio3 VARCHAR(500),
            extra1 VARCHAR(500),
            extra2 VARCHAR(500),
            extra3 VARCHAR(500),
            stato VARCHAR(50) DEFAULT 'attivo',
            pdf_url VARCHAR(1000),
            excel_url VARCHAR(1000),
            created_by BIGINT(20) UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_data_evento (data_evento),
            KEY idx_stato (stato),
            KEY idx_created_by (created_by)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->log('[747Disco-DB] Tabella preventivi creata/verificata');
    }
    
    /**
     * Crea tabella messaggi (esistente)
     */
    private function create_messages_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_messages} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            preventivo_id BIGINT(20) UNSIGNED,
            type VARCHAR(50) NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255),
            content LONGTEXT,
            status VARCHAR(50) DEFAULT 'pending',
            sent_at DATETIME,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_preventivo (preventivo_id),
            KEY idx_type (type),
            KEY idx_status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->log('[747Disco-DB] Tabella messaggi creata/verificata');
    }
    
    /**
     * Crea tabella logs (esistente)
     */
    private function create_logs_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_logs} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL,
            category VARCHAR(50),
            message TEXT NOT NULL,
            context LONGTEXT,
            user_id BIGINT(20) UNSIGNED,
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_level (level),
            KEY idx_category (category),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->log('[747Disco-DB] Tabella logs creata/verificata');
    }
    
    // ============================================================================
    // METODI PREVENTIVI (ESISTENTI)
    // ============================================================================
    
    /**
     * Inserisce nuovo preventivo
     *
     * @param array $data Dati preventivo
     * @return int|false ID preventivo o false se errore
     */
    public function insert_preventivo($data) {
        $this->log('[747Disco-DB] Inserimento nuovo preventivo');
        
        try {
            $prepared_data = $this->prepare_preventivo_data($data);
            $prepared_data['created_at'] = current_time('mysql');
            $prepared_data['created_by'] = get_current_user_id();
            
            $result = $this->wpdb->insert(
                $this->table_preventivi,
                $prepared_data,
                $this->get_preventivo_formats($prepared_data)
            );
            
            if ($result === false) {
                throw new \Exception('Errore inserimento: ' . $this->wpdb->last_error);
            }
            
            $preventivo_id = $this->wpdb->insert_id;
            $this->log('[747Disco-DB] Preventivo inserito ID: ' . $preventivo_id);
            
            return $preventivo_id;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-DB] Errore insert preventivo: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Aggiorna preventivo esistente
     *
     * @param int $id ID preventivo
     * @param array $data Dati da aggiornare
     * @return bool Successo operazione
     */
    public function update_preventivo($id, $data) {
        $this->log('[747Disco-DB] Aggiornamento preventivo ID: ' . $id);
        
        try {
            $prepared_data = $this->prepare_preventivo_data($data);
            $prepared_data['updated_at'] = current_time('mysql');
            
            $result = $this->wpdb->update(
                $this->table_preventivi,
                $prepared_data,
                array('id' => $id),
                $this->get_preventivo_formats($prepared_data),
                array('%d')
            );
            
            if ($result === false) {
                throw new \Exception('Errore aggiornamento: ' . $this->wpdb->last_error);
            }
            
            $this->log('[747Disco-DB] Preventivo aggiornato ID: ' . $id);
            return true;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-DB] Errore update preventivo: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Ottiene preventivi con filtri
     *
     * @param array $args Parametri ricerca
     * @return array Lista preventivi
     */
    public function get_preventivi($args = array()) {
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'stato' => '',
            'search' => ''
        );
        
        $args = array_merge($defaults, $args);
        
        try {
            $where_conditions = array();
            $where_values = array();
            
            if (!empty($args['stato'])) {
                $where_conditions[] = "stato = %s";
                $where_values[] = $args['stato'];
            }
            
            if (!empty($args['search'])) {
                $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
                $where_conditions[] = "(nome_cliente LIKE %s OR email LIKE %s OR tipo_evento LIKE %s)";
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                $where_values[] = $search_term;
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
            if (!$orderby) {
                $orderby = 'created_at DESC';
            }
            
            $limit_clause = $this->wpdb->prepare(
                "LIMIT %d OFFSET %d",
                intval($args['limit']),
                intval($args['offset'])
            );
            
            $sql = "SELECT * FROM {$this->table_preventivi} {$where_clause} ORDER BY {$orderby} {$limit_clause}";
            
            if (!empty($where_values)) {
                $sql = $this->wpdb->prepare($sql, $where_values);
            }
            
            $results = $this->wpdb->get_results($sql, ARRAY_A);
            
            return $results;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-DB] Errore get_preventivi: ' . $e->getMessage(), 'error');
            return array();
        }
    }
    
    /**
     * Ottiene singolo preventivo per ID
     *
     * @param int $id ID preventivo
     * @return array|null Dati preventivo
     */
    public function get_preventivo($id) {
        try {
            $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_preventivi} WHERE id = %d",
                    intval($id)
                ),
                ARRAY_A
            );
            
            return $result;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-DB] Errore get_preventivo: ' . $e->getMessage(), 'error');
            return null;
        }
    }
    
    // ============================================================================
    // NUOVI METODI PER EXCEL ANALYSIS
    // ============================================================================
    
    /**
     * Verifica e ripara la tabella Excel Analysis se necessaria
     * Metodo SICURO che esegue solo ALTER necessari
     */
    public function check_and_repair_excel_analysis_table() {
        $this->log('[747Disco-DB] Verifica schema tabella Excel analysis');
        
        try {
            $table_name = $this->table_excel_analysis;
            
            // Controlla se tabella esiste
            $table_exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table_name
                )
            );
            
            if (!$table_exists) {
                $this->log('[747Disco-DB] Tabella Excel analysis non esiste, la creo');
                $this->create_excel_analysis_table_complete();
                return true;
            }
            
            // Ottieni colonne esistenti
            $existing_columns = $this->get_table_columns($table_name);
            $required_columns = $this->get_required_excel_analysis_columns();
            
            // Identifica colonne mancanti
            $missing_columns = array_diff_key($required_columns, $existing_columns);
            
            if (empty($missing_columns)) {
                $this->log('[747Disco-DB] Schema Excel analysis già completo');
                return true;
            }
            
            // Aggiungi colonne mancanti
            foreach ($missing_columns as $column_name => $column_definition) {
                $alter_sql = "ALTER TABLE {$table_name} ADD COLUMN {$column_name} {$column_definition}";
                
                $result = $this->wpdb->query($alter_sql);
                
                if ($result === false) {
                    $this->log('[747Disco-DB] Errore aggiunta colonna: ' . $column_name . ' - ' . $this->wpdb->last_error, 'error');
                } else {
                    $this->log('[747Disco-DB] Colonna aggiunta: ' . $column_name);
                }
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-DB] Errore check/repair tabella: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Crea tabella Excel Analysis completa con tutti i campi richiesti
     * Versione AGGIORNATA con schema completo
     */
    private function create_excel_analysis_table_complete() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_excel_analysis} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            file_id VARCHAR(255) NOT NULL,
            filename VARCHAR(500) NOT NULL,
            drive_path VARCHAR(1000) NOT NULL,
            
            data_evento DATE NULL,
            tipo_evento VARCHAR(255) NULL,
            tipo_menu VARCHAR(50) NULL,
            orario VARCHAR(100) NULL,
            numero_invitati INT(11) NULL,
            
            nome_referente VARCHAR(255) NULL,
            cognome_referente VARCHAR(255) NULL,
            cellulare VARCHAR(50) NULL,
            email VARCHAR(255) NULL,
            
            omaggio1 VARCHAR(500) NULL,
            omaggio2 VARCHAR(500) NULL,
            omaggio3 VARCHAR(500) NULL,
            
            importo DECIMAL(10,2) NULL,
            acconto DECIMAL(10,2) NULL,
            saldo DECIMAL(10,2) NULL,
            
            extra1_nome VARCHAR(255) NULL,
            extra1_prezzo DECIMAL(10,2) NULL,
            extra2_nome VARCHAR(255) NULL,
            extra2_prezzo DECIMAL(10,2) NULL,
            extra3_nome VARCHAR(255) NULL,
            extra3_prezzo DECIMAL(10,2) NULL,
            
            analysis_success TINYINT(1) DEFAULT 1,
            analysis_errors_json TEXT NULL,
            source VARCHAR(50) DEFAULT 'excel_scan',
            
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY idx_file_id (file_id),
            KEY idx_filename (filename(255)),
            KEY idx_data_evento (data_evento),
            KEY idx_tipo_menu (tipo_menu),
            KEY idx_analysis_success (analysis_success),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->log('[747Disco-DB] Tabella Excel analysis creata con schema completo');
    }
    
    /**
     * UPSERT dati Excel Analysis (INSERT o UPDATE basato su file_id)
     *
     * @param array $row Dati da inserire/aggiornare
     * @return int|false ID record o false se errore
     */
    public function upsert_excel_analysis($row) {
        $this->log('[747Disco-DB] Upsert Excel analysis per file_id: ' . ($row['file_id'] ?? 'N/A'));
        
        try {
            // Validazione dati essenziali
            if (empty($row['file_id'])) {
                throw new \Exception('file_id obbligatorio per upsert');
            }
            
            // Prepara dati con defaults
            $data = $this->prepare_excel_analysis_data($row);
            
            // Controlla se record già esiste
            $existing_id = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->table_excel_analysis} WHERE file_id = %s",
                    $data['file_id']
                )
            );
            
            if ($existing_id) {
                // UPDATE
                $data['updated_at'] = current_time('mysql');
                unset($data['created_at']); // Non aggiornare created_at
                
                $result = $this->wpdb->update(
                    $this->table_excel_analysis,
                    $data,
                    array('id' => $existing_id),
                    $this->get_excel_data_formats($data),
                    array('%d')
                );
                
                if ($result === false) {
                    throw new \Exception('Errore UPDATE: ' . $this->wpdb->last_error);
                }
                
                $this->log('[747Disco-DB] Record aggiornato ID: ' . $existing_id);
                return intval($existing_id);
                
            } else {
                // INSERT
                $data['created_at'] = current_time('mysql');
                $data['updated_at'] = current_time('mysql');
                
                $result = $this->wpdb->insert(
                    $this->table_excel_analysis,
                    $data,
                    $this->get_excel_data_formats($data)
                );
                
                if ($result === false) {
                    throw new \Exception('Errore INSERT: ' . $this->wpdb->last_error);
                }
                
                $new_id = $this->wpdb->insert_id;
                $this->log('[747Disco-DB] Nuovo record creato ID: ' . $new_id);
                return $new_id;
            }
            
        } catch (\Exception $e) {
            $this->log('[747Disco-DB] Errore upsert Excel analysis: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Recupera dati Excel Analysis con filtri e paginazione
     *
     * @param array $args Parametri di ricerca
     * @return array Lista record
     */
    public function get_excel_analysis($args = array()) {
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'where' => array(),
            'search' => '',
            'year' => null,
            'month' => null,
            'tipo_menu' => null,
            'analysis_success' => null
        );
        
        $args = array_merge($defaults, $args);
        
        $this->log('[747Disco-DB] get_excel_analysis con filtri: ' . json_encode($args));
        
        try {
            // Costruisci WHERE clause
            $where_conditions = array();
            $where_values = array();
            
            // Filtri specifici
            if ($args['year']) {
                $where_conditions[] = "YEAR(data_evento) = %d";
                $where_values[] = intval($args['year']);
            }
            
            if ($args['month']) {
                $where_conditions[] = "MONTH(data_evento) = %d";
                $where_values[] = intval($args['month']);
            }
            
            if ($args['tipo_menu']) {
                $where_conditions[] = "tipo_menu = %s";
                $where_values[] = sanitize_text_field($args['tipo_menu']);
            }
            
            if ($args['analysis_success'] !== null) {
                $where_conditions[] = "analysis_success = %d";
                $where_values[] = $args['analysis_success'] ? 1 : 0;
            }
            
            // Ricerca testuale
            if (!empty($args['search'])) {
                $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
                $where_conditions[] = "(filename LIKE %s OR nome_referente LIKE %s OR cognome_referente LIKE %s OR tipo_evento LIKE %s)";
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                $where_values[] = $search_term;
            }
            
            // Condizioni personalizzate
            foreach ($args['where'] as $field => $value) {
                $where_conditions[] = "{$field} = %s";
                $where_values[] = $value;
            }
            
            // Assembla query
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
            if (!$orderby) {
                $orderby = 'created_at DESC';
            }
            
            $limit_clause = '';
            if ($args['limit'] > 0) {
                $limit_clause = $this->wpdb->prepare(
                    "LIMIT %d OFFSET %d",
                    intval($args['limit']),
                    intval($args['offset'])
                );
            }
            
            $sql = "SELECT * FROM {$this->table_excel_analysis} {$where_clause} ORDER BY {$orderby} {$limit_clause}";
            
            if (!empty($where_values)) {
                $sql = $this->wpdb->prepare($sql, $where_values);
            }
            
            $results = $this->wpdb->get_results($sql, ARRAY_A);
            
            $this->log('[747Disco-DB] Trovati ' . count($results) . ' record Excel analysis');
            
            return $results;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-DB] Errore get_excel_analysis: ' . $e->getMessage(), 'error');
            return array();
        }
    }
    
    /**
     * Recupera singolo record Excel Analysis per ID
     *
     * @param int $id ID record
     * @return array|null Dati record o null se non trovato
     */
    public function get_excel_row($id) {
        $this->log('[747Disco-DB] get_excel_row ID: ' . $id);
        
        try {
            $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_excel_analysis} WHERE id = %d",
                    intval($id)
                ),
                ARRAY_A
            );
            
            if ($result) {
                $this->log('[747Disco-DB] Record trovato per ID: ' . $id);
            } else {
                $this->log('[747Disco-DB] Nessun record trovato per ID: ' . $id);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-DB] Errore get_excel_row: ' . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Conta record Excel Analysis con filtri
     *
     * @param array $args Parametri di ricerca (stessi di get_excel_analysis)
     * @return int Numero record
     */
    public function count_excel_analysis($args = array()) {
        // Usa stessi filtri di get_excel_analysis ma conta solo
        $count_args = $args;
        $count_args['limit'] = 0; // No limit per count
        $count_args['offset'] = 0;
        
        try {
            // Costruisci WHERE (stessa logica di get_excel_analysis)
            $where_conditions = array();
            $where_values = array();
            
            if ($count_args['year']) {
                $where_conditions[] = "YEAR(data_evento) = %d";
                $where_values[] = intval($count_args['year']);
            }
            
            if ($count_args['month']) {
                $where_conditions[] = "MONTH(data_evento) = %d";
                $where_values[] = intval($count_args['month']);
            }
            
            if ($count_args['tipo_menu']) {
                $where_conditions[] = "tipo_menu = %s";
                $where_values[] = sanitize_text_field($count_args['tipo_menu']);
            }
            
            if ($count_args['analysis_success'] !== null) {
                $where_conditions[] = "analysis_success = %d";
                $where_values[] = $count_args['analysis_success'] ? 1 : 0;
            }
            
            if (!empty($count_args['search'])) {
                $search_term = '%' . $this->wpdb->esc_like($count_args['search']) . '%';
                $where_conditions[] = "(filename LIKE %s OR nome_referente LIKE %s OR cognome_referente LIKE %s OR tipo_evento LIKE %s)";
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                $where_values[] = $search_term;
            }
            
            foreach ($count_args['where'] as $field => $value) {
                $where_conditions[] = "{$field} = %s";
                $where_values[] = $value;
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $sql = "SELECT COUNT(*) FROM {$this->table_excel_analysis} {$where_clause}";
            
            if (!empty($where_values)) {
                $sql = $this->wpdb->prepare($sql, $where_values);
            }
            
            $count = $this->wpdb->get_var($sql);
            
            return intval($count);
            
        } catch (\Exception $e) {
            $this->log('[747Disco-DB] Errore count_excel_analysis: ' . $e->getMessage(), 'error');
            return 0;
        }
    }
    
    // ============================================================================
    // METODI DI UTILITÀ E SUPPORTO
    // ============================================================================
    
    /**
     * Ottiene colonne esistenti di una tabella
     */
    private function get_table_columns($table_name) {
        $columns = array();
        
        $results = $this->wpdb->get_results(
            "SHOW COLUMNS FROM {$table_name}",
            ARRAY_A
        );
        
        foreach ($results as $column) {
            $columns[$column['Field']] = $column['Type'];
        }
        
        return $columns;
    }
    
    /**
     * Definisce colonne richieste per Excel Analysis
     */
    private function get_required_excel_analysis_columns() {
        return array(
            'id' => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
            'file_id' => 'VARCHAR(255) NOT NULL',
            'filename' => 'VARCHAR(500) NOT NULL',
            'drive_path' => 'VARCHAR(1000) NOT NULL',
            'data_evento' => 'DATE NULL',
            'tipo_evento' => 'VARCHAR(255) NULL',
            'tipo_menu' => 'VARCHAR(50) NULL',
            'orario' => 'VARCHAR(100) NULL',
            'numero_invitati' => 'INT(11) NULL',
            'nome_referente' => 'VARCHAR(255) NULL',
            'cognome_referente' => 'VARCHAR(255) NULL',
            'cellulare' => 'VARCHAR(50) NULL',
            'email' => 'VARCHAR(255) NULL',
            'omaggio1' => 'VARCHAR(500) NULL',
            'omaggio2' => 'VARCHAR(500) NULL',
            'omaggio3' => 'VARCHAR(500) NULL',
            'importo' => 'DECIMAL(10,2) NULL',
            'acconto' => 'DECIMAL(10,2) NULL',
            'saldo' => 'DECIMAL(10,2) NULL',
            'extra1_nome' => 'VARCHAR(255) NULL',
            'extra1_prezzo' => 'DECIMAL(10,2) NULL',
            'extra2_nome' => 'VARCHAR(255) NULL',
            'extra2_prezzo' => 'DECIMAL(10,2) NULL',
            'extra3_nome' => 'VARCHAR(255) NULL',
            'extra3_prezzo' => 'DECIMAL(10,2) NULL',
            'analysis_success' => 'TINYINT(1) DEFAULT 1',
            'analysis_errors_json' => 'TEXT NULL',
            'source' => 'VARCHAR(50) DEFAULT \'excel_scan\'',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        );
    }
    
    /**
     * Prepara dati preventivo per insert/update
     */
    private function prepare_preventivo_data($data) {
        $prepared = array();
        
        // Campi obbligatori
        $prepared['nome_cliente'] = sanitize_text_field($data['nome_cliente'] ?? '');
        $prepared['data_evento'] = sanitize_text_field($data['data_evento'] ?? '');
        
        // Campi opzionali
        $prepared['telefono'] = sanitize_text_field($data['telefono'] ?? '');
        $prepared['email'] = sanitize_email($data['email'] ?? '');
        $prepared['tipo_evento'] = sanitize_text_field($data['tipo_evento'] ?? '');
        $prepared['tipo_menu'] = sanitize_text_field($data['tipo_menu'] ?? '');
        $prepared['numero_invitati'] = intval($data['numero_invitati'] ?? 0);
        $prepared['orario_evento'] = sanitize_text_field($data['orario_evento'] ?? '');
        $prepared['importo_preventivo'] = floatval($data['importo_preventivo'] ?? 0);
        $prepared['acconto_versato'] = floatval($data['acconto_versato'] ?? 0);
        $prepared['omaggio1'] = sanitize_textarea_field($data['omaggio1'] ?? '');
        $prepared['omaggio2'] = sanitize_textarea_field($data['omaggio2'] ?? '');
        $prepared['omaggio3'] = sanitize_textarea_field($data['omaggio3'] ?? '');
        $prepared['extra1'] = sanitize_textarea_field($data['extra1'] ?? '');
        $prepared['extra2'] = sanitize_textarea_field($data['extra2'] ?? '');
        $prepared['extra3'] = sanitize_textarea_field($data['extra3'] ?? '');
        $prepared['stato'] = sanitize_text_field($data['stato'] ?? 'attivo');
        $prepared['pdf_url'] = esc_url_raw($data['pdf_url'] ?? '');
        $prepared['excel_url'] = esc_url_raw($data['excel_url'] ?? '');
        
        return $prepared;
    }
    
    /**
     * Prepara dati per insert/update Excel Analysis con sanitizzazione
     *
     * @param array $row Dati grezzi
     * @return array Dati preparati
     */
    private function prepare_excel_analysis_data($row) {
        $prepared = array();
        
        // Campi obbligatori
        $prepared['file_id'] = sanitize_text_field($row['file_id']);
        $prepared['filename'] = sanitize_file_name($row['filename'] ?? '');
        $prepared['drive_path'] = sanitize_text_field($row['drive_path'] ?? '');
        
        // Dati evento
        $prepared['data_evento'] = $this->sanitize_date($row['data_evento'] ?? null);
        $prepared['tipo_evento'] = sanitize_text_field($row['tipo_evento'] ?? '');
        $prepared['tipo_menu'] = sanitize_text_field($row['tipo_menu'] ?? '');
        $prepared['orario'] = sanitize_text_field($row['orario'] ?? '');
        $prepared['numero_invitati'] = $this->sanitize_int($row['numero_invitati'] ?? null);
        
        // Dati cliente
        $prepared['nome_referente'] = sanitize_text_field($row['nome_referente'] ?? '');
        $prepared['cognome_referente'] = sanitize_text_field($row['cognome_referente'] ?? '');
        $prepared['cellulare'] = sanitize_text_field($row['cellulare'] ?? '');
        $prepared['email'] = sanitize_email($row['email'] ?? '');
        
        // Omaggi
        $prepared['omaggio1'] = sanitize_textarea_field($row['omaggio1'] ?? '');
        $prepared['omaggio2'] = sanitize_textarea_field($row['omaggio2'] ?? '');
        $prepared['omaggio3'] = sanitize_textarea_field($row['omaggio3'] ?? '');
        
        // Dati economici
        $prepared['importo'] = $this->sanitize_decimal($row['importo'] ?? null);
        $prepared['acconto'] = $this->sanitize_decimal($row['acconto'] ?? null);
        $prepared['saldo'] = $this->sanitize_decimal($row['saldo'] ?? null);
        
        // Extra
        $prepared['extra1_nome'] = sanitize_text_field($row['extra1_nome'] ?? '');
        $prepared['extra1_prezzo'] = $this->sanitize_decimal($row['extra1_prezzo'] ?? null);
        $prepared['extra2_nome'] = sanitize_text_field($row['extra2_nome'] ?? '');
        $prepared['extra2_prezzo'] = $this->sanitize_decimal($row['extra2_prezzo'] ?? null);
        $prepared['extra3_nome'] = sanitize_text_field($row['extra3_nome'] ?? '');
        $prepared['extra3_prezzo'] = $this->sanitize_decimal($row['extra3_prezzo'] ?? null);
        
        // Metadati analisi
        $prepared['analysis_success'] = isset($row['analysis_success']) ? ($row['analysis_success'] ? 1 : 0) : 1;
        $prepared['analysis_errors_json'] = !empty($row['analysis_errors_json']) ? json_encode($row['analysis_errors_json']) : null;
        $prepared['source'] = sanitize_text_field($row['source'] ?? 'excel_scan');
        
        // Rimuovi valori vuoti (ma mantieni 0 e false)
        foreach ($prepared as $key => $value) {
            if ($value === '' || $value === null) {
                $prepared[$key] = null;
            }
        }
        
        return $prepared;
    }
    
    /**
     * Genera formati wpdb per preventivi
     */
    private function get_preventivo_formats($data) {
        $formats = array();
        
        foreach ($data as $key => $value) {
            if (in_array($key, array('numero_invitati', 'created_by'))) {
                $formats[] = '%d'; // Intero
            } elseif (in_array($key, array('importo_preventivo', 'acconto_versato'))) {
                $formats[] = '%f'; // Decimale
            } else {
                $formats[] = '%s'; // Stringa
            }
        }
        
        return $formats;
    }
    
    /**
     * Genera array di format per wpdb Excel (stringa/intero/decimale)
     */
    private function get_excel_data_formats($data) {
        $formats = array();
        
        foreach ($data as $key => $value) {
            if (in_array($key, array('numero_invitati', 'analysis_success'))) {
                $formats[] = '%d'; // Intero
            } elseif (in_array($key, array('importo', 'acconto', 'saldo', 'extra1_prezzo', 'extra2_prezzo', 'extra3_prezzo'))) {
                $formats[] = '%f'; // Decimale
            } else {
                $formats[] = '%s'; // Stringa
            }
        }
        
        return $formats;
    }
    
    /**
     * Sanitizza data per database
     */
    private function sanitize_date($date) {
        if (empty($date)) {
            return null;
        }
        
        // Se è già formato Y-m-d, va bene
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        
        // Prova a convertire
        $timestamp = strtotime($date);
        if ($timestamp) {
            return date('Y-m-d', $timestamp);
        }
        
        return null;
    }
    
    /**
     * Sanitizza intero
     */
    private function sanitize_int($value) {
        if ($value === null || $value === '') {
            return null;
        }
        return intval($value);
    }
    
    /**
     * Sanitizza decimale
     */
    private function sanitize_decimal($value) {
        if ($value === null || $value === '') {
            return null;
        }
        return floatval($value);
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