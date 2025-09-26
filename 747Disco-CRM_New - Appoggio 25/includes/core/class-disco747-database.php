<?php
/**
 * Database management class for Disco747 CRM
 */

namespace Disco747_CRM\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Disco747_Database {
    
    private $wpdb;
    private $table_name;
    private $excel_analysis_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'disco747_preventivi';
        $this->excel_analysis_table = $wpdb->prefix . 'disco747_excel_analysis';
    }
    
    /**
     * Create tables if they don't exist
     */
    public function create_tables() {
        $this->create_preventivi_table();
        $this->create_excel_analysis_table();
    }
    
    /**
     * Create main preventivi table
     */
    private function create_preventivi_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
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
            drive_file_id VARCHAR(128),
            pdf_file_id VARCHAR(128),
            status VARCHAR(50) DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_data_evento (data_evento),
            KEY idx_status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create Excel analysis table
     */
    public function create_excel_analysis_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->excel_analysis_table} (
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
            analysis_success TINYINT(1) DEFAULT 0,
            analysis_errors_json LONGTEXT NULL,
            source VARCHAR(30) DEFAULT 'drive',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_file (file_id),
            KEY idx_filename (filename),
            KEY idx_data_evento (data_evento)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Save Excel analysis data with upsert logic
     */
    public function save_excel_analysis(array $data) {
        // Prepare data with defaults
        $defaults = [
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
            'analysis_success' => 0,
            'analysis_errors_json' => null,
            'source' => 'drive'
        ];
        
        $data = array_merge($defaults, $data);
        
        // Check for existing record
        $existing = null;
        if (!empty($data['file_id'])) {
            $existing = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->excel_analysis_table} WHERE file_id = %s",
                $data['file_id']
            ), ARRAY_A);
        }
        
        if (!$existing && !empty($data['filename'])) {
            $existing = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->excel_analysis_table} WHERE filename = %s",
                $data['filename']
            ), ARRAY_A);
        }
        
        if ($existing) {
            // Update existing - Conservative UPSERT
            $update_data = [];
            foreach ($data as $key => $value) {
                if ($key === 'id') continue;
                
                // Only update if new value is not empty and (existing is empty or we have better data)
                $existing_value = isset($existing[$key]) ? $existing[$key] : null;
                if (!empty($value) && (empty($existing_value) || $this->is_better_value($value, $existing_value, $key))) {
                    $update_data[$key] = $value;
                } elseif ($key === 'updated_at') {
                    $update_data[$key] = current_time('mysql');
                }
            }
            
            if (!empty($update_data)) {
                $result = $this->wpdb->update(
                    $this->excel_analysis_table,
                    $update_data,
                    ['id' => $existing['id']]
                );
                return $result !== false ? $existing['id'] : false;
            }
            return $existing['id'];
        } else {
            // Insert new record
            $result = $this->wpdb->insert($this->excel_analysis_table, $data);
            return $result !== false ? $this->wpdb->insert_id : false;
        }
    }
    
    /**
     * Helper to determine if new value is better than existing
     */
    private function is_better_value($new_value, $existing_value, $field_name) {
        // For dates, prefer more recent analysis
        if (in_array($field_name, ['data_evento', 'modified_time'])) {
            return true; // Always update dates from fresh scan
        }
        
        // For numeric fields, prefer non-zero values
        if (in_array($field_name, ['importo', 'acconto', 'saldo', 'numero_invitati'])) {
            return $new_value > 0 && $existing_value <= 0;
        }
        
        // For strings, prefer longer non-empty values
        if (is_string($new_value) && is_string($existing_value)) {
            return strlen(trim($new_value)) > strlen(trim($existing_value));
        }
        
        return false;
    }
    
    /**
     * Get Excel analysis records with search and pagination
     */
    public function get_excel_analysis(array $args = []) {
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'search' => '',
            'orderby' => 'updated_at',
            'order' => 'DESC'
        ];
        
        $args = array_merge($defaults, $args);
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        // Search functionality
        if (!empty($args['search'])) {
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(filename LIKE %s OR nome_referente LIKE %s OR cognome_referente LIKE %s OR tipo_evento LIKE %s)";
            $where_values = array_fill(0, 4, $search_term);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$this->excel_analysis_table} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_sql = $this->wpdb->prepare($count_sql, $where_values);
        }
        $total_items = $this->wpdb->get_var($count_sql);
        
        // Get records
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $sql = "SELECT * FROM {$this->excel_analysis_table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$args['per_page'], $offset]);
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $query_values), ARRAY_A);
        
        return [
            'items' => $results ?: [],
            'total' => intval($total_items),
            'per_page' => $args['per_page'],
            'current_page' => $args['page'],
            'total_pages' => ceil($total_items / $args['per_page'])
        ];
    }
    
    /**
     * Get single Excel analysis record by ID
     */
    public function get_excel_analysis_by_id($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->excel_analysis_table} WHERE id = %d",
            $id
        ), ARRAY_A);
    }
    
    /**
     * Save preventivo (existing functionality)
     */
    public function save_preventivo($data) {
        // Existing preventivo save logic
        $result = $this->wpdb->insert($this->table_name, $data);
        return $result !== false ? $this->wpdb->insert_id : false;
    }
    
    /**
     * Get preventivo by ID
     */
    public function get_preventivo($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ), ARRAY_A);
    }
    
    /**
     * Get all preventivi with pagination
     */
    public function get_preventivi($args = []) {
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'search' => ''
        ];
        
        $args = array_merge($defaults, $args);
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(nome_referente LIKE %s OR cognome_referente LIKE %s OR tipo_evento LIKE %s)";
            $where_values = array_merge($where_values, [$search_term, $search_term, $search_term]);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_sql = $this->wpdb->prepare($count_sql, $where_values);
        }
        $total_items = $this->wpdb->get_var($count_sql);
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$args['per_page'], $offset]);
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $query_values), ARRAY_A);
        
        return [
            'items' => $results ?: [],
            'total' => intval($total_items),
            'per_page' => $args['per_page'],
            'current_page' => $args['page'],
            'total_pages' => ceil($total_items / $args['per_page'])
        ];
    }
    
    /**
     * Update preventivo status
     */
    public function update_preventivo_status($id, $status) {
        return $this->wpdb->update(
            $this->table_name,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );
    }
    
    /**
     * Delete preventivo
     */
    public function delete_preventivo($id) {
        return $this->wpdb->delete($this->table_name, ['id' => $id]);
    }
    
    /**
     * Count preventivi with optional filters (for dashboard statistics)
     */
    public function count_preventivi($filters = array()) {
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (isset($filters['stato'])) {
            $where_conditions[] = "stato = %s";
            $where_values[] = $filters['stato'];
        }
        
        if (isset($filters['confermato'])) {
            $where_conditions[] = "confermato = %d";
            $where_values[] = intval($filters['confermato']);
        }
        
        if (isset($filters['data_from'])) {
            $where_conditions[] = "data_evento >= %s";
            $where_values[] = $filters['data_from'];
        }
        
        if (isset($filters['data_to'])) {
            $where_conditions[] = "data_evento <= %s";
            $where_values[] = $filters['data_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $sql = $this->wpdb->prepare($sql, $where_values);
        }
        
        return intval($this->wpdb->get_var($sql));
    }
    
    /**
     * Sum preventivi value (for dashboard statistics)
     */
    public function sum_preventivi_value($filters = array()) {
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (isset($filters['stato'])) {
            $where_conditions[] = "stato = %s";
            $where_values[] = $filters['stato'];
        }
        
        if (isset($filters['confermato'])) {
            $where_conditions[] = "confermato = %d";
            $where_values[] = intval($filters['confermato']);
        }
        
        if (isset($filters['data_from'])) {
            $where_conditions[] = "data_evento >= %s";
            $where_values[] = $filters['data_from'];
        }
        
        if (isset($filters['data_to'])) {
            $where_conditions[] = "data_evento <= %s";
            $where_values[] = $filters['data_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT SUM(importo) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $sql = $this->wpdb->prepare($sql, $where_values);
        }
        
        return floatval($this->wpdb->get_var($sql)) ?: 0;
    }
    
    /**
     * Insert new preventivo (alias for save_preventivo for compatibility)
     */
    public function insert_preventivo($data) {
        $result = $this->wpdb->insert($this->table_name, $data);
        return $result !== false ? $this->wpdb->insert_id : false;
    }
    
    /**
     * Update existing preventivo
     */
    public function update_preventivo($id, $data) {
        $data['updated_at'] = current_time('mysql');
        return $this->wpdb->update($this->table_name, $data, ['id' => $id]);
    }
}