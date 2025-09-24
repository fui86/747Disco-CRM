<?php
/**
 * Database Manager - 747 Disco CRM
 * FILE COMPLETO CON METODI PER PREVENTIVI
 * 
 * @package Disco747_CRM
 * @version 11.6.2
 */

namespace Disco747_CRM\Core;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Database {
    
    private $table_name;
    private $charset_collate;
    private $debug_mode = true;
    
    public function __construct() {
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'disco747_preventivi';
        $this->charset_collate = $wpdb->get_charset_collate();
        
        $this->maybe_create_tables();
    }
    
    /**
     * Crea tabelle se non esistono
     */
    private function maybe_create_tables() {
        global $wpdb;
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            data_evento date NOT NULL,
            tipo_evento varchar(100) NOT NULL,
            tipo_menu varchar(50) NOT NULL DEFAULT 'Menu 7',
            numero_invitati int(11) NOT NULL DEFAULT 50,
            orario_evento varchar(50) DEFAULT '',
            nome_cliente varchar(200) NOT NULL,
            telefono varchar(50) DEFAULT '',
            email varchar(100) DEFAULT '',
            importo_totale decimal(10,2) NOT NULL DEFAULT 0.00,
            acconto decimal(10,2) NOT NULL DEFAULT 0.00,
            omaggio1 varchar(200) DEFAULT '',
            omaggio2 varchar(200) DEFAULT '',
            omaggio3 varchar(200) DEFAULT '',
            extra1 varchar(200) DEFAULT '',
            extra2 varchar(200) DEFAULT '',
            extra3 varchar(200) DEFAULT '',
            stato varchar(20) NOT NULL DEFAULT 'attivo',
            excel_url text DEFAULT '',
            pdf_url text DEFAULT '',
            googledrive_url text DEFAULT '',
            created_at datetime NOT NULL,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY data_evento (data_evento),
            KEY stato (stato)
        ) {$this->charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Inserisce nuovo preventivo
     */
    public function insert_preventivo($data) {
        global $wpdb;
        
        $insert_data = array(
            'data_evento' => $data['data_evento'],
            'tipo_evento' => $data['tipo_evento'],
            'tipo_menu' => $data['tipo_menu'],
            'numero_invitati' => $data['numero_invitati'],
            'orario_evento' => $data['orario_evento'] ?? '',
            'nome_cliente' => $data['nome_cliente'],
            'telefono' => $data['telefono'] ?? '',
            'email' => $data['email'] ?? '',
            'importo_totale' => $data['importo_totale'],
            'acconto' => $data['acconto'] ?? 0,
            'omaggio1' => $data['omaggio1'] ?? '',
            'omaggio2' => $data['omaggio2'] ?? '',
            'omaggio3' => $data['omaggio3'] ?? '',
            'extra1' => $data['extra1'] ?? '',
            'extra2' => $data['extra2'] ?? '',
            'extra3' => $data['extra3'] ?? '',
            'stato' => $data['stato'] ?? 'attivo',
            'excel_url' => $data['excel_url'] ?? '',
            'pdf_url' => $data['pdf_url'] ?? '',
            'googledrive_url' => $data['googledrive_url'] ?? '',
            'created_at' => $data['created_at'] ?? current_time('mysql'),
            'created_by' => $data['created_by'] ?? get_current_user_id(),
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        if ($result === false) {
            error_log('[747Disco-DB] Errore insert: ' . $wpdb->last_error);
            return false;
        }
        
        $insert_id = $wpdb->insert_id;
        error_log('[747Disco-DB] ✅ Preventivo inserito con ID: ' . $insert_id);
        
        return $insert_id;
    }
    
    /**
     * Aggiorna preventivo esistente
     */
    public function update_preventivo($preventivo_id, $data) {
        global $wpdb;
        
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table_name, 
            $data, 
            array('id' => $preventivo_id)
        );
        
        if ($result === false) {
            error_log('[747Disco-DB] Errore update: ' . $wpdb->last_error);
            return false;
        }
        
        error_log('[747Disco-DB] ✅ Preventivo aggiornato: ID ' . $preventivo_id);
        return true;
    }
    
    /**
     * Upsert preventivo (insert o update)
     */
    public function upsert_preventivo($data) {
        if (isset($data['id']) && $data['id'] > 0) {
            $id = $data['id'];
            unset($data['id']);
            return $this->update_preventivo($id, $data);
        } else {
            return $this->insert_preventivo($data);
        }
    }
    
    /**
     * Ottieni preventivo per ID
     */
    public function get_preventivo($preventivo_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $preventivo_id
        ));
    }
    
    /**
     * Ottieni tutti i preventivi
     */
    public function get_preventivi($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 100,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = "1=1";
        
        if (isset($args['stato'])) {
            $where .= $wpdb->prepare(" AND stato = %s", $args['stato']);
        }
        
        if (isset($args['confermato']) && $args['confermato']) {
            $where .= " AND acconto > 0";
        }
        
        $query = "SELECT * FROM {$this->table_name} 
                  WHERE {$where} 
                  ORDER BY {$args['orderby']} {$args['order']} 
                  LIMIT {$args['limit']} OFFSET {$args['offset']}";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Conta preventivi
     */
    public function count_preventivi($args = array()) {
        global $wpdb;
        
        $where = "1=1";
        
        if (isset($args['stato'])) {
            $where .= $wpdb->prepare(" AND stato = %s", $args['stato']);
        }
        
        if (isset($args['confermato']) && $args['confermato']) {
            $where .= " AND acconto > 0";
        }
        
        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}");
    }
    
    /**
     * Somma valore preventivi
     */
    public function sum_preventivi_value($args = array()) {
        global $wpdb;
        
        $where = "1=1";
        
        if (isset($args['stato'])) {
            $where .= $wpdb->prepare(" AND stato = %s", $args['stato']);
        }
        
        return floatval($wpdb->get_var(
            "SELECT SUM(importo_totale) FROM {$this->table_name} WHERE {$where}"
        ));
    }
    
    /**
     * Ottieni preventivi recenti
     */
    public function get_recent_preventivi($limit = 5) {
        return $this->get_preventivi(array(
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => $limit
        ));
    }
    
    /**
     * Ottieni preventivi filtrati (per dashboard)
     */
    public function get_filtered_preventivi($filters) {
        global $wpdb;
        
        $where_clauses = array("1=1");
        
        // Ricerca
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_clauses[] = $wpdb->prepare(
                "(nome_cliente LIKE %s OR tipo_evento LIKE %s OR email LIKE %s)",
                $search, $search, $search
            );
        }
        
        // Stato
        if (!empty($filters['stato'])) {
            $where_clauses[] = $wpdb->prepare("stato = %s", $filters['stato']);
        }
        
        // Anno
        if (!empty($filters['anno'])) {
            $where_clauses[] = $wpdb->prepare("YEAR(data_evento) = %d", $filters['anno']);
        }
        
        // Mese
        if (!empty($filters['mese'])) {
            $where_clauses[] = $wpdb->prepare("MONTH(data_evento) = %d", $filters['mese']);
        }
        
        // Menu
        if (!empty($filters['menu'])) {
            $where_clauses[] = $wpdb->prepare("tipo_menu = %s", $filters['menu']);
        }
        
        $where = implode(' AND ', $where_clauses);
        
        // Paginazione
        $per_page = $filters['per_page'] ?? 20;
        $paged = $filters['paged'] ?? 1;
        $offset = ($paged - 1) * $per_page;
        
        $query = "SELECT * FROM {$this->table_name} 
                  WHERE {$where} 
                  ORDER BY data_evento DESC 
                  LIMIT {$per_page} OFFSET {$offset}";
        
        $results = $wpdb->get_results($query);
        
        // Conta totale
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}");
        
        return array(
            'preventivi' => $results,
            'total' => $total,
            'per_page' => $per_page,
            'current_page' => $paged,
            'total_pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * Ottieni nome tabella
     */
    public function get_table_name() {
        return $this->table_name;
    }
    
    /**
     * Log helper
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode) {
            error_log('[747Disco-DB] ' . $message);
        }
    }
}