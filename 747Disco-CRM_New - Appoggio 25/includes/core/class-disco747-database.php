<?php
/**
 * Database Manager - 747 Disco CRM
 * VERSIONE 11.6.4-FIX-PREVENTIVO-ID - Rimosso campo preventivo_id obsoleto
 * 
 * @package Disco747_CRM
 * @version 11.6.4-FIX
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
        $this->maybe_update_table_structure();
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
            extra1_importo decimal(10,2) DEFAULT 0.00,
            extra2 varchar(200) DEFAULT '',
            extra2_importo decimal(10,2) DEFAULT 0.00,
            extra3 varchar(200) DEFAULT '',
            extra3_importo decimal(10,2) DEFAULT 0.00,
            stato varchar(20) NOT NULL DEFAULT 'attivo',
            excel_url text DEFAULT '',
            pdf_url text DEFAULT '',
            googledrive_url text DEFAULT '',
            googledrive_file_id varchar(100) DEFAULT '',
            created_at datetime NOT NULL,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY data_evento (data_evento),
            KEY stato (stato),
            KEY googledrive_file_id (googledrive_file_id)
        ) {$this->charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('[747Disco-DB] Tabella verificata/creata: ' . $this->table_name);
    }
    
    /**
     * Aggiorna struttura tabella se necessario (per installazioni esistenti)
     */
    private function maybe_update_table_structure() {
        global $wpdb;
        
        // Verifica se le colonne extra_importo esistono già
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'extra%_importo'");
        
        if (count($columns) < 3) {
            error_log('[747Disco-DB] Aggiornamento struttura tabella - aggiunta colonne extra_importo...');
            
            // Aggiungi colonne mancanti
            $updates = array();
            
            if (!$this->column_exists('extra1_importo')) {
                $updates[] = "ADD COLUMN extra1_importo decimal(10,2) DEFAULT 0.00 AFTER extra1";
            }
            
            if (!$this->column_exists('extra2_importo')) {
                $updates[] = "ADD COLUMN extra2_importo decimal(10,2) DEFAULT 0.00 AFTER extra2";
            }
            
            if (!$this->column_exists('extra3_importo')) {
                $updates[] = "ADD COLUMN extra3_importo decimal(10,2) DEFAULT 0.00 AFTER extra3";
            }
            
            if (!$this->column_exists('googledrive_file_id')) {
                $updates[] = "ADD COLUMN googledrive_file_id varchar(100) DEFAULT '' AFTER googledrive_url";
                $updates[] = "ADD KEY googledrive_file_id (googledrive_file_id)";
            }
            
            if (!empty($updates)) {
                $sql = "ALTER TABLE {$this->table_name} " . implode(', ', $updates);
                $result = $wpdb->query($sql);
                
                if ($result !== false) {
                    error_log('[747Disco-DB] ✅ Struttura tabella aggiornata con successo');
                } else {
                    error_log('[747Disco-DB] ❌ Errore aggiornamento struttura: ' . $wpdb->last_error);
                }
            }
        }
        
        // ✅ FIX: Rimuovi colonna preventivo_id obsoleta se esiste
        if ($this->column_exists('preventivo_id')) {
            error_log('[747Disco-DB] Rimozione colonna obsoleta preventivo_id...');
            
            // Prima rimuovi l'indice se esiste
            $wpdb->query("ALTER TABLE {$this->table_name} DROP INDEX IF EXISTS idx_preventivo_id");
            
            // Poi rimuovi la colonna
            $result = $wpdb->query("ALTER TABLE {$this->table_name} DROP COLUMN preventivo_id");
            
            if ($result !== false) {
                error_log('[747Disco-DB] ✅ Colonna preventivo_id rimossa con successo');
            } else {
                error_log('[747Disco-DB] ⚠️ Errore rimozione preventivo_id: ' . $wpdb->last_error);
            }
        }
    }
    
    /**
     * Verifica se una colonna esiste
     */
    private function column_exists($column_name) {
        global $wpdb;
        
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$this->table_name} LIKE %s",
                $column_name
            )
        );
        
        return !empty($result);
    }
    
    /**
     * Inserisce nuovo preventivo
     */
    public function insert_preventivo($data) {
        global $wpdb;
        
        $insert_data = array(
            'data_evento' => $data['data_evento'],
            'tipo_evento' => $data['tipo_evento'] ?? '',
            'tipo_menu' => $data['tipo_menu'] ?? 'Menu 7',
            'numero_invitati' => $data['numero_invitati'] ?? 50,
            'orario_evento' => $data['orario_evento'] ?? '',
            'nome_cliente' => $data['nome_cliente'],
            'telefono' => $data['telefono'] ?? '',
            'email' => $data['email'] ?? '',
            'importo_totale' => $data['importo_totale'] ?? 0,
            'acconto' => $data['acconto'] ?? 0,
            'omaggio1' => $data['omaggio1'] ?? '',
            'omaggio2' => $data['omaggio2'] ?? '',
            'omaggio3' => $data['omaggio3'] ?? '',
            'extra1' => $data['extra1'] ?? '',
            'extra1_importo' => $data['extra1_importo'] ?? 0,
            'extra2' => $data['extra2'] ?? '',
            'extra2_importo' => $data['extra2_importo'] ?? 0,
            'extra3' => $data['extra3'] ?? '',
            'extra3_importo' => $data['extra3_importo'] ?? 0,
            'stato' => $data['stato'] ?? 'attivo',
            'excel_url' => $data['excel_url'] ?? '',
            'pdf_url' => $data['pdf_url'] ?? '',
            'googledrive_url' => $data['googledrive_url'] ?? '',
            'googledrive_file_id' => $data['googledrive_file_id'] ?? '',
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
     * Upsert preventivo (insert o update basato su googledrive_file_id)
     */
    public function upsert_preventivo_by_file_id($data) {
        global $wpdb;
        
        $file_id = $data['googledrive_file_id'] ?? '';
        
        if (empty($file_id)) {
            error_log('[747Disco-DB] upsert_preventivo_by_file_id: file_id mancante, eseguo insert normale');
            return $this->insert_preventivo($data);
        }
        
        // Cerca preventivo esistente con questo file_id
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE googledrive_file_id = %s",
            $file_id
        ));
        
        if ($existing) {
            error_log('[747Disco-DB] Preventivo esistente trovato (ID: ' . $existing->id . '), eseguo UPDATE');
            $result = $this->update_preventivo($existing->id, $data);
            return $result ? $existing->id : false;
        } else {
            error_log('[747Disco-DB] Preventivo non esistente, eseguo INSERT');
            return $this->insert_preventivo($data);
        }
    }
    
    /**
     * Upsert preventivo generico (insert o update)
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
     * Ottieni preventivo per Google Drive File ID
     */
    public function get_preventivo_by_file_id($file_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE googledrive_file_id = %s",
            $file_id
        ));
    }
    
    /**
     * Verifica se preventivo esiste per File ID
     */
    public function preventivo_exists_by_file_id($file_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE googledrive_file_id = %s",
            $file_id
        ));
        
        return $count > 0;
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
     * Elimina preventivo
     */
    public function delete_preventivo($preventivo_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $preventivo_id),
            array('%d')
        );
        
        if ($result === false) {
            error_log('[747Disco-DB] Errore delete: ' . $wpdb->last_error);
            return false;
        }
        
        error_log('[747Disco-DB] ✅ Preventivo eliminato: ID ' . $preventivo_id);
        return true;
    }
    
    /**
     * Ottieni statistiche
     */
    public function get_stats() {
        global $wpdb;
        
        $stats = array(
            'total' => 0,
            'attivi' => 0,
            'confermati' => 0,
            'annullati' => 0,
            'questo_mese' => 0
        );
        
        try {
            $stats['total'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"));
            $stats['attivi'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE stato = 'attivo'"));
            $stats['confermati'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE acconto > 0 OR stato = 'confermato'"));
            $stats['annullati'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE stato = 'annullato'"));
            
            $primo_giorno_mese = date('Y-m-01 00:00:00');
            $stats['questo_mese'] = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
                $primo_giorno_mese
            )));
            
        } catch (\Exception $e) {
            error_log('[747Disco-DB] Errore get_stats: ' . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Verifica salute database
     */
    public function check_health() {
        global $wpdb;
        
        $health = array(
            'table_exists' => false,
            'columns_count' => 0,
            'rows_count' => 0,
            'status' => 'unknown'
        );
        
        try {
            // Verifica esistenza tabella
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
            $health['table_exists'] = ($table_exists === $this->table_name);
            
            if ($health['table_exists']) {
                // Conta colonne
                $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name}");
                $health['columns_count'] = count($columns);
                
                // Conta righe
                $health['rows_count'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"));
                
                $health['status'] = 'ok';
            } else {
                $health['status'] = 'table_missing';
            }
            
        } catch (\Exception $e) {
            $health['status'] = 'error';
            $health['error'] = $e->getMessage();
            error_log('[747Disco-DB] Errore check_health: ' . $e->getMessage());
        }
        
        return $health;
    }
    
    /**
     * Log interno
     */
    private function log($message) {
        if ($this->debug_mode && function_exists('error_log')) {
            error_log('[747Disco-DB] ' . $message);
        }
    }
}