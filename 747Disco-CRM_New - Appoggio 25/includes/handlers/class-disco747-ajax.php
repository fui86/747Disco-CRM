<?php
/**
 * SOLO AGGIUNTE per includes/handlers/class-disco747-ajax.php
 * DA AGGIUNGERE alla classe esistente senza modificare nulla
 *
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @since      11.4.2
 */

// ============================================================================
// STEP 1: AGGIUNGERE questi 3 hook nel metodo register_ajax_hooks() ESISTENTE
// ============================================================================

/*
Nel metodo register_ajax_hooks() esistente, aggiungere queste 3 righe:

// NUOVI: Handlers per Excel Analysis
add_action('wp_ajax_disco747_scan_drive_batch', array($this, 'handle_scan_drive_batch'));
add_action('wp_ajax_disco747_excel_table_data', array($this, 'handle_excel_table_data'));
add_action('wp_ajax_disco747_open_preventivo_from_analysis', array($this, 'handle_open_preventivo_from_analysis'));
*/

// ============================================================================
// STEP 2: AGGIUNGERE questi 3 metodi alla fine della classe esistente
// ============================================================================

/**
 * AJAX: Esegue scansione batch di Google Drive per file Excel
 * Endpoint per il pulsante "Analizza Ora"
 */
public function handle_scan_drive_batch() {
    $this->log('[747Disco-Scan] Richiesta scan batch ricevuta');
    
    try {
        // Verifica sicurezza
        if (!check_ajax_referer('disco747_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Sicurezza: nonce non valido'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        // Verifica plugin inizializzato
        $disco747_crm = disco747_crm();
        if (!$disco747_crm || !$disco747_crm->is_initialized()) {
            wp_send_json_error(array('message' => 'Plugin non inizializzato'));
        }
        
        // Parametri scansione
        $options = array(
            'page_size' => 50,
            'max_results' => 500
        );
        
        // Contatori
        $stats = array(
            'files_found' => 0,
            'files_processed' => 0,
            'analysis_success' => 0,
            'analysis_errors' => 0,
            'errors' => array()
        );
        
        $start_time = microtime(true);
        
        // FASE 1: Scansiona Google Drive
        $sync_handler = new \Disco747_CRM\Storage\Disco747_GoogleDrive_Sync();
        $scan_result = $sync_handler->scan_excel_files_batch($options);
        
        if (!$scan_result['success']) {
            wp_send_json_error(array(
                'message' => 'Errore scansione: ' . $scan_result['error'],
                'stats' => $stats
            ));
        }
        
        $files = $scan_result['files'];
        $stats['files_found'] = count($files);
        
        $this->log('[747Disco-Scan] Trovati ' . $stats['files_found'] . ' file Excel');
        
        // FASE 2: Analizza ogni file
        $parser = new \Disco747_CRM\Generators\Disco747_Excel_Parser();
        $database = $disco747_crm->get_database();
        
        if (!$database) {
            wp_send_json_error(array('message' => 'Database non disponibile'));
        }
        
        foreach ($files as $index => $file_info) {
            try {
                $stats['files_processed']++;
                
                // Download file
                $file_content = $sync_handler->download_excel_file($file_info['file_id']);
                
                if ($file_content === false) {
                    $stats['analysis_errors']++;
                    $stats['errors'][] = "Download fallito: " . $file_info['filename'];
                    continue;
                }
                
                // Parse Excel
                $parse_result = $parser->parse_excel_file($file_content, $file_info['filename']);
                
                if (!$parse_result['success']) {
                    $stats['analysis_errors']++;
                    $stats['errors'][] = "Parse fallito: " . $file_info['filename'];
                    
                    // Salva record con errore
                    $error_row = array_merge($file_info, array(
                        'analysis_success' => 0,
                        'analysis_errors_json' => $parse_result['errors']
                    ));
                    $database->upsert_excel_analysis($error_row);
                    continue;
                }
                
                // Salva dati estratti
                $excel_data = array_merge($file_info, $parse_result['data']);
                $excel_data['analysis_success'] = 1;
                $excel_data['analysis_errors_json'] = null;
                
                $record_id = $database->upsert_excel_analysis($excel_data);
                
                if ($record_id) {
                    $stats['analysis_success']++;
                } else {
                    $stats['analysis_errors']++;
                    $stats['errors'][] = "Salvataggio DB fallito: " . $file_info['filename'];
                }
                
                // Rate limiting
                if ($index % 10 === 0 && $index > 0) {
                    usleep(500000); // 0.5 secondi
                }
                
            } catch (\Exception $e) {
                $stats['analysis_errors']++;
                $stats['errors'][] = "Errore: " . $file_info['filename'] . " - " . $e->getMessage();
                $this->log('[747Disco-Scan] Errore file: ' . $e->getMessage(), 'error');
            }
        }
        
        $stats['processing_time'] = round(microtime(true) - $start_time, 2);
        
        // Statistiche finali dal DB
        $final_stats = array(
            'total_files' => $database->count_excel_analysis(),
            'analyzed_success' => $database->count_excel_analysis(array('analysis_success' => 1)),
            'analysis_errors' => $database->count_excel_analysis(array('analysis_success' => 0))
        );
        
        // Conta confermati
        global $wpdb;
        $excel_table = $wpdb->prefix . 'disco747_excel_analysis';
        $final_stats['confirmed_count'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$excel_table} WHERE acconto > 0"
        ));
        
        $this->log('[747Disco-Scan] Completato: ' . $stats['analysis_success'] . ' successi, ' . $stats['analysis_errors'] . ' errori');
        
        wp_send_json_success(array(
            'message' => 'Scansione completata',
            'batch_stats' => $stats,
            'stats' => $final_stats, // Per aggiornare contatori UI
            'scan_time' => date('Y-m-d H:i:s')
        ));
        
    } catch (\Exception $e) {
        $this->log('[747Disco-Scan] Errore generale: ' . $e->getMessage(), 'error');
        wp_send_json_error(array(
            'message' => 'Errore: ' . $e->getMessage(),
            'stats' => $stats ?? array()
        ));
    }
}

/**
 * AJAX: Fornisce dati tabella Excel Analysis
 * Con filtri, ricerca e paginazione
 */
public function handle_excel_table_data() {
    $this->log('[747Disco-ExcelPage] Richiesta dati tabella');
    
    try {
        // Verifica sicurezza
        if (!check_ajax_referer('disco747_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce non valido'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        // Database
        $disco747_crm = disco747_crm();
        $database = $disco747_crm ? $disco747_crm->get_database() : null;
        
        if (!$database) {
            wp_send_json_error(array('message' => 'Database non disponibile'));
        }
        
        // Parametri
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = 20;
        $search = sanitize_text_field($_POST['search'] ?? '');
        $tipo_menu = sanitize_text_field($_POST['tipo_menu'] ?? '');
        
        $offset = ($page - 1) * $per_page;
        
        // Query parametri
        $args = array(
            'limit' => $per_page,
            'offset' => $offset,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => $search,
            'tipo_menu' => $tipo_menu
        );
        
        // Ottieni dati
        $records = $database->get_excel_analysis($args);
        $total_records = $database->count_excel_analysis($args);
        $total_pages = ceil($total_records / $per_page);
        
        // Formatta record per tabella
        $formatted_records = array();
        foreach ($records as $record) {
            $stato = 'pending';
            if ($record['analysis_success'] == 0) {
                $stato = 'error';
            } elseif ($record['acconto'] > 0) {
                $stato = 'confirmed';
            }
            
            $formatted_records[] = array(
                'id' => $record['id'],
                'filename' => $record['filename'],
                'data_evento' => $record['data_evento'] ? date('d/m/Y', strtotime($record['data_evento'])) : '-',
                'tipo_evento' => $record['tipo_evento'] ?: '-',
                'tipo_menu' => $record['tipo_menu'] ?: '-',
                'nome_completo' => trim(($record['nome_referente'] ?? '') . ' ' . ($record['cognome_referente'] ?? '')),
                'importo' => $record['importo'] ? '€ ' . number_format($record['importo'], 2) : '-',
                'acconto' => $record['acconto'] ? '€ ' . number_format($record['acconto'], 2) : '-',
                'stato' => $stato,
                'web_view_link' => isset($record['web_view_link']) ? $record['web_view_link'] : null,
                'created_at' => date('d/m/Y H:i', strtotime($record['created_at']))
            );
        }
        
        wp_send_json_success(array(
            'records' => $formatted_records,
            'pagination' => array(
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_records' => $total_records,
                'per_page' => $per_page
            )
        ));
        
    } catch (\Exception $e) {
        $this->log('[747Disco-ExcelPage] Errore tabella: ' . $e->getMessage(), 'error');
        wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()));
    }
}

/**
 * AJAX: Routing sicuro per aprire form preventivo precompilato
 * Da dati Excel Analysis
 */
public function handle_open_preventivo_from_analysis() {
    $this->log('[747Disco-Edit] Richiesta apertura form da Excel');
    
    try {
        // Verifica sicurezza
        if (!check_ajax_referer('disco747_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce non valido'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'));
        }
        
        // Parametri
        $analysis_id = intval($_POST['analysis_id'] ?? 0);
        
        if (!$analysis_id) {
            wp_send_json_error(array('message' => 'ID analisi non valido'));
        }
        
        // Verifica record esiste
        $disco747_crm = disco747_crm();
        $database = $disco747_crm ? $disco747_crm->get_database() : null;
        
        if (!$database) {
            wp_send_json_error(array('message' => 'Database non disponibile'));
        }
        
        $record = $database->get_excel_row($analysis_id);
        
        if (!$record) {
            wp_send_json_error(array('message' => 'Record non trovato'));
        }
        
        // Genera URL sicuro per form preventivo
        $base_url = admin_url('admin.php?page=disco747-main');
        $params = array(
            'action' => 'new_preventivo',
            'source' => 'excel_analysis',
            'analysis_id' => $analysis_id,
            '_wpnonce' => wp_create_nonce('disco747_form_precompile_' . $analysis_id)
        );
        
        $redirect_url = add_query_arg($params, $base_url);
        
        $this->log('[747Disco-Edit] URL generato per ID ' . $analysis_id . ': ' . $redirect_url);
        
        wp_send_json_success(array(
            'redirect_url' => $redirect_url,
            'message' => 'URL generato correttamente'
        ));
        
    } catch (\Exception $e) {
        $this->log('[747Disco-Edit] Errore routing: ' . $e->getMessage(), 'error');
        wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()));
    }
}

/**
 * Logging con prefisso
 */
private function log($message, $level = 'info') {
    if ($this->debug_mode && function_exists('error_log')) {
        $prefix = '[' . date('Y-m-d H:i:s') . '] ';
        error_log($prefix . $message);
    }
}