<?php
/**
 * Database management class for Disco747 CRM
 * 
 * @package    Disco747_CRM
 * @subpackage Core
 * @version    11.5.9-EXCEL-SCAN
 */

namespace Disco747_CRM\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Disco747_Database {
    
    private $wpdb;
    private $table_preventivi;
    private $table_excel_analysis;
    private $table_messages;
    private $table_logs;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Define table names
        $this->table_preventivi = $wpdb->prefix . 'disco747_preventivi';
        $this->table_excel_analysis = $wpdb->prefix . 'disco747_excel_analysis';
        $this->table_messages = $wpdb->prefix . 'disco747_messages';
        $this->table_logs = $wpdb->prefix . 'disco747_logs';
    }
    
    /**
     * Create all tables
     */
    public function create_tables() {
        $this->create_preventivi_table();
        $this->create_excel_analysis_table();
        $this->create_messages_table();
        $this->create_logs_table();
    }
    
    /**
     * Create preventivi table
     */
    private function create_preventivi_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_preventivi} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            numero_preventivo VARCHAR(20) NOT NULL,
            nome_referente VARCHAR(100) NOT NULL,
            cognome_referente VARCHAR(100) NOT NULL,
            cellulare VARCHAR(20),
            email VARCHAR(150),
            tipo_evento VARCHAR(100),
            data_evento DATE,
            orario VARCHAR(50),
            numero_invitati INT,
            tipo_menu VARCHAR(50),
            importo DECIMAL(10,2),
            acconto DECIMAL(10,2),
            saldo DECIMAL(10,2),
            omaggio1 VARCHAR(255),
            omaggio2 VARCHAR(255),
            omaggio3 VARCHAR(255),
            extra1_nome VARCHAR(255),
            extra1_prezzo DECIMAL(10,2),
            extra2_nome VARCHAR(255),
            extra2_prezzo DECIMAL(10,2),
            extra3_nome VARCHAR(255),
            extra3_prezzo DECIMAL(10,2),
            note TEXT,
            drive_file_id VARCHAR(128),
            pdf_file_id VARCHAR(128),
            excel_file_id VARCHAR(128),
            status VARCHAR(50) DEFAULT 'draft',
            created_by BIGINT(20) UNSIGNED,
            updated_by BIGINT(20) UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_numero (numero_preventivo),
            KEY idx_data_evento (data_evento),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create Excel analysis table
     */
    public function create_excel_analysis_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_excel_analysis} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
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
            stato VARCHAR(20) DEFAULT 'Neutro',
            analysis_success TINYINT(1) DEFAULT 0,
            analysis_errors_json LONGTEXT NULL,
            source VARCHAR(30) DEFAULT 'drive',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_file_id (file_id),
            KEY idx_filename (filename),
            KEY idx_data_evento (data_evento),
            KEY idx_tipo_menu (tipo_menu),
            KEY idx_stato (stato),
            KEY idx_analysis_success (analysis_success),
            KEY idx_created_at (created_at),
            KEY idx_updated_at (updated_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log("[747Disco-DB] Tabella Excel analysis creata/verificata");
    }
    
    /**
     * Create messages table
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
    }
    
    /**
     * Create logs table
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
    }
    
    /**
     * Save Excel analysis data with upsert logic
     */
    public function save_excel_analysis($data) {
        error_log("[747Disco-DB] save_excel_analysis chiamato con: " . print_r(array_keys($data), true));
        
        // Prepare data with defaults
        $defaults = array(
            'file_id' => null,
            'filename' => '',
            'drive_path' => null,
            'modified_time' => null,
            'data_evento' => null,
            'tipo_evento' => null,
            'tipo_menu' => null,
            'orario' => null,
            'numero_invitati' => null,
            'nome_referente' => null,
            'cognome_referente' => null,
            'cellulare' => null,
            'email' => null,
            'omaggio1' => null,
            'omaggio2' => null,
            'omaggio3' => null,
            'importo' => null,
            'acconto' => null,
            'saldo' => null,
            'extra1_nome' => null,
            'extra1_prezzo' => null,
            'extra2_nome' => null,
            'extra2_prezzo' => null,
            'extra3_nome' => null,
            'extra3_prezzo' => null,
            'stato' => 'Neutro',
            'analysis_success' => 1,
            'analysis_errors_json' => null,
            'source' => 'drive'
        );
        
        $data = array_merge($defaults, $data);
        
        // Determine stato from filename if not set
        if (empty($data['stato']) && !empty($data['filename'])) {
            $filename_upper = strtoupper($data['filename']);
            if (strpos($filename_upper, 'CONF') === 0) {
                $data['stato'] = 'CONF';
            } elseif (strpos($filename_upper, 'NO') === 0) {
                $data['stato'] = 'NO';
            }
        }
        
        // Format dates
        if (!empty($data['data_evento'])) {
            $data['data_evento'] = date('Y-m-d', strtotime($data['data_evento']));
        }
        
        if (!empty($data['modified_time'])) {
            $data['modified_time'] = date('Y-m-d H:i:s', strtotime($data['modified_time']));
        }
        
        // Check for existing record by file_id
        $existing = null;
        if (!empty($data['file_id'])) {
            $existing = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->table_excel_analysis} WHERE file_id = %s",
                $data['file_id']
            ), ARRAY_A);
        }
        
        // If not found by file_id, check by filename
        if (!$existing && !empty($data['filename'])) {
            $existing = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->table_excel_analysis} WHERE filename = %s",
                $data['filename']
            ), ARRAY_A);
        }
        
        // Remove fields that shouldn't be in database
        unset($data['id']);
        unset($data['created_at']);
        $data['updated_at'] = current_time('mysql');
        
        if ($existing) {
            // Update existing record
            error_log("[747Disco-DB] Aggiornamento record esistente ID: " . $existing['id']);
            
            $result = $this->wpdb->update(
                $this->table_excel_analysis,
                $data,
                array('id' => $existing['id'])
            );
            
            if ($result !== false) {
                error_log("[747Disco-DB] Record aggiornato con successo");
                return $existing['id'];
            } else {
                error_log("[747Disco-DB] Errore aggiornamento: " . $this->wpdb->last_error);
                return false;
            }
        } else {
            // Insert new record
            error_log("[747Disco-DB] Inserimento nuovo record");
            
            $result = $this->wpdb->insert($this->table_excel_analysis, $data);
            
            if ($result !== false) {
                $insert_id = $this->wpdb->insert_id;
                error_log("[747Disco-DB] Record inserito con ID: " . $insert_id);
                return $insert_id;
            } else {
                error_log("[747Disco-DB] Errore inserimento: " . $this->wpdb->last_error);
                return false;
            }
        }
    }
    
    /**
     * Get Excel analysis records with filters
     */
    public function get_excel_analysis($args = array()) {
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'search' => '',
            'stato' => '',
            'menu' => '',
            'orderby' => 'updated_at',
            'order' => 'DESC'
        );
        
        $args = array_merge($defaults, $args);
        
        // Build WHERE clause
        $where_conditions = array('1=1');
        $where_values = array();
        
        // Search filter
        if (!empty($args['search'])) {
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(filename LIKE %s OR nome_referente LIKE %s OR cognome_referente LIKE %s OR tipo_evento LIKE %s OR email LIKE %s)";
            array_push($where_values, $search_term, $search_term, $search_term, $search_term, $search_term);
        }
        
        // Stato filter
        if (!empty($args['stato'])) {
            $where_conditions[] = "stato = %s";
            $where_values[] = $args['stato'];
        }
        
        // Menu filter
        if (!empty($args['menu'])) {
            $where_conditions[] = "tipo_menu = %s";
            $where_values[] = $args['menu'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Count total records
        $count_sql = "SELECT COUNT(*) FROM {$this->table_excel_analysis} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_sql = $this->wpdb->prepare($count_sql, $where_values);
        }
        $total_items = $this->wpdb->get_var($count_sql);
        
        // Get records with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        // Validate orderby and order
        $allowed_orderby = array('id', 'data_evento', 'tipo_evento', 'updated_at', 'created_at', 'filename', 'stato');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'updated_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql = "SELECT * FROM {$this->table_excel_analysis} 
                WHERE {$where_clause} 
                ORDER BY {$orderby} {$order} 
                LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($args['per_page'], $offset));
        
        if (!empty($query_values)) {
            $sql = $this->wpdb->prepare($sql, $query_values);
        }
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        return array(
            'items' => $results ?: array(),
            'total' => intval($total_items),
            'per_page' => $args['per_page'],
            'current_page' => $args['page'],
            'total_pages' => ceil($total_items / $args['per_page'])
        );
    }
    
    /**
     * Get single Excel analysis record by ID
     */
    public function get_excel_analysis_by_id($id) {
        error_log("[747Disco-DB] get_excel_analysis_by_id chiamato con ID: " . $id);
        
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_excel_analysis} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if ($result) {
            error_log("[747Disco-DB] Record trovato: " . $result['filename']);
        } else {
            error_log("[747Disco-DB] Nessun record trovato per ID: " . $id);
        }
        
        return $result;
    }
    
    /**
     * Get Excel analysis by file ID
     */
    public function get_excel_analysis_by_file_id($file_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_excel_analysis} WHERE file_id = %s",
            $file_id
        ), ARRAY_A);
    }
    
    /**
     * Delete Excel analysis record
     */
    public function delete_excel_analysis($id) {
        return $this->wpdb->delete(
            $this->table_excel_analysis,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Get Excel analysis stats
     */
    public function get_excel_analysis_stats() {
        $stats = array();
        
        // Total records
        $stats['total'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_excel_analysis}"
        );
        
        // By stato
        $stati = $this->wpdb->get_results(
            "SELECT stato, COUNT(*) as count 
             FROM {$this->table_excel_analysis} 
             GROUP BY stato",
            ARRAY_A
        );
        
        $stats['by_stato'] = array();
        foreach ($stati as $stato) {
            $stats['by_stato'][$stato['stato']] = intval($stato['count']);
        }
        
        // By menu
        $menus = $this->wpdb->get_results(
            "SELECT tipo_menu, COUNT(*) as count 
             FROM {$this->table_excel_analysis} 
             WHERE tipo_menu IS NOT NULL 
             GROUP BY tipo_menu",
            ARRAY_A
        );
        
        $stats['by_menu'] = array();
        foreach ($menus as $menu) {
            $stats['by_menu'][$menu['tipo_menu']] = intval($menu['count']);
        }
        
        // Success rate
        $stats['success'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_excel_analysis} WHERE analysis_success = 1"
        );
        
        $stats['failed'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_excel_analysis} WHERE analysis_success = 0"
        );
        
        // Last analysis
        $stats['last_analysis'] = $this->wpdb->get_var(
            "SELECT MAX(updated_at) FROM {$this->table_excel_analysis}"
        );
        
        return $stats;
    }
    
    /**
     * Save preventivo
     */
    public function save_preventivo($data) {
        // Generate numero preventivo if not exists
        if (empty($data['numero_preventivo'])) {
            $data['numero_preventivo'] = $this->generate_numero_preventivo();
        }
        
        // Add user info
        if (empty($data['created_by'])) {
            $data['created_by'] = get_current_user_id();
        }
        $data['updated_by'] = get_current_user_id();
        
        // Check if update or insert
        if (!empty($data['id'])) {
            $id = $data['id'];
            unset($data['id']);
            
            $result = $this->wpdb->update(
                $this->table_preventivi,
                $data,
                array('id' => $id)
            );
            
            return $result !== false ? $id : false;
        } else {
            $result = $this->wpdb->insert($this->table_preventivi, $data);
            return $result !== false ? $this->wpdb->insert_id : false;
        }
    }
    
    /**
     * Get preventivo by ID
     */
    public function get_preventivo($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_preventivi} WHERE id = %d",
            $id
        ), ARRAY_A);
    }
    
    /**
     * Get all preventivi with filters
     */
    public function get_preventivi($args = array()) {
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = array_merge($defaults, $args);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(nome_referente LIKE %s OR cognome_referente LIKE %s OR email LIKE %s OR numero_preventivo LIKE %s)";
            array_push($where_values, $search_term, $search_term, $search_term, $search_term);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$this->table_preventivi} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_sql = $this->wpdb->prepare($count_sql, $where_values);
        }
        $total_items = $this->wpdb->get_var($count_sql);
        
        // Get records
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'created_at DESC';
        
        $sql = "SELECT * FROM {$this->table_preventivi} 
                WHERE {$where_clause} 
                ORDER BY {$orderby} 
                LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($args['per_page'], $offset));
        
        if (!empty($query_values)) {
            $sql = $this->wpdb->prepare($sql, $query_values);
        }
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        return array(
            'items' => $results ?: array(),
            'total' => intval($total_items),
            'per_page' => $args['per_page'],
            'current_page' => $args['page'],
            'total_pages' => ceil($total_items / $args['per_page'])
        );
    }
    
    /**
     * Delete preventivo
     */
    public function delete_preventivo($id) {
        return $this->wpdb->delete(
            $this->table_preventivi,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Generate numero preventivo
     */
    private function generate_numero_preventivo() {
        $year = date('Y');
        
        // Get last numero for this year
        $last_numero = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT numero_preventivo 
             FROM {$this->table_preventivi} 
             WHERE numero_preventivo LIKE %s 
             ORDER BY id DESC 
             LIMIT 1",
            $year . '-%'
        ));
        
        if ($last_numero) {
            $parts = explode('-', $last_numero);
            $next_num = intval($parts[1]) + 1;
        } else {
            $next_num = 1;
        }
        
        return sprintf('%s-%03d', $year, $next_num);
    }
    
    /**
     * Log message to database
     */
    public function log($message, $level = 'info', $category = 'general', $context = array()) {
        return $this->wpdb->insert(
            $this->table_logs,
            array(
                'level' => $level,
                'category' => $category,
                'message' => $message,
                'context' => json_encode($context),
                'user_id' => get_current_user_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Get logs
     */
    public function get_logs($args = array()) {
        $defaults = array(
            'per_page' => 50,
            'page' => 1,
            'level' => '',
            'category' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = array_merge($defaults, $args);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['level'])) {
            $where_conditions[] = "level = %s";
            $where_values[] = $args['level'];
        }
        
        if (!empty($args['category'])) {
            $where_conditions[] = "category = %s";
            $where_values[] = $args['category'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'created_at DESC';
        
        $sql = "SELECT * FROM {$this->table_logs} 
                WHERE {$where_clause} 
                ORDER BY {$orderby} 
                LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($args['per_page'], $offset));
        
        if (!empty($query_values)) {
            $sql = $this->wpdb->prepare($sql, $query_values);
        }
        
        return $this->wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Clean old logs
     */
    public function clean_old_logs($days = 30) {
        return $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->table_logs} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}