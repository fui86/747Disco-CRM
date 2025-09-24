<?php
/**
 * Template per la pagina Scansione Excel Auto - 747 Disco CRM
 * FIXATO: JavaScript batch analysis + accesso dati database corretto
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      11.7.0-EXCEL-SCAN-DEDICATED
 * @version    11.7.5-COMPLETE-FIXED
 */

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// Dati necessari dovrebbero essere già stati preparati dal controller
$excel_files_list = $excel_files_list ?? array();
$scan_result = $scan_result ?? null;
$show_results = $show_results ?? false;
$is_googledrive_configured = $is_googledrive_configured ?? false;
$analysis_results = $analysis_results ?? array();
$last_analysis_date = $last_analysis_date ?? 'Mai';
$total_analysis = $total_analysis ?? 0;

// Funzioni helper per formattazione dati - ACCESSO DIRETTO AI CAMPI DATABASE
if (!function_exists('format_currency_excel')) {
    function format_currency_excel($amount) {
        return '€ ' . number_format(floatval($amount), 2, ',', '.');
    }
}

if (!function_exists('format_date_excel')) {
    function format_date_excel($date) {
        if (empty($date)) return 'N/A';
        
        // Se è già in formato Y-m-d, convertilo a d/m/Y
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            return date('d/m/Y', strtotime($date));
        }
        
        // Se è un serial Excel, convertilo
        if (is_numeric($date)) {
            $unix_date = ($date - 25569) * 86400;
            return date('d/m/Y', $unix_date);
        }
        
        return $date;
    }
}

if (!function_exists('format_time_excel')) {
    function format_time_excel($time) {
        if (empty($time)) return '';
        
        // Se è già in formato H:i
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return $time;
        }
        
        // Se è un numero Excel (frazione di giorno)
        if (is_numeric($time) && $time > 0) {
            $hours = floor($time * 24);
            $minutes = floor(($time * 24 - $hours) * 60);
            return sprintf('%d:%02d', $hours, $minutes);
        }
        
        return $time;
    }
}

if (!function_exists('parse_orari_excel')) {
    function parse_orari_excel($orari_raw) {
        if (empty($orari_raw)) {
            return ['inizio' => '', 'fine' => ''];
        }
        
        // Se è stringa formato "HH:MM - HH:MM"
        if (is_string($orari_raw) && strpos($orari_raw, '-') !== false) {
            $parts = explode('-', $orari_raw);
            return [
                'inizio' => trim($parts[0] ?? ''),
                'fine' => trim($parts[1] ?? '')
            ];
        }
        
        return ['inizio' => $orari_raw, 'fine' => ''];
    }
}

if (!function_exists('determine_stato_excel')) {
    function determine_stato_excel($result, $filename = '') {
        $acconto = floatval($result->acconto ?? 0);
        $filename_lower = strtolower($filename);
        
        // Confermato se filename inizia con "CONF" oppure acconto > 0
        if (strpos($filename_lower, 'conf ') === 0 || $acconto > 0) {
            return [
                'text' => 'Confermato',
                'class' => 'stato-confermato',
                'color' => '#28a745'
            ];
        }
        
        // Annullato se filename inizia con "No", "NO", "Annullato"
        if (strpos($filename_lower, 'no ') === 0 || 
            strpos($filename_lower, 'annullato') === 0) {
            return [
                'text' => 'Annullato', 
                'class' => 'stato-annullato',
                'color' => '#dc3545'
            ];
        }
        
        // Altrimenti in definizione
        return [
            'text' => 'In definizione',
            'class' => 'stato-in-definizione', 
            'color' => '#ffc107'
        ];
    }
}

if (!function_exists('format_omaggi_excel')) {
    function format_omaggi_excel($omaggi_list) {
        if (empty($omaggi_list)) return '';
        
        if (is_string($omaggi_list)) {
            $omaggi_list = json_decode($omaggi_list, true);
        }
        
        if (!is_array($omaggi_list)) return '';
        
        $omaggi_non_vuoti = array_filter($omaggi_list, function($item) {
            return !empty(trim($item));
        });
        
        return implode(', ', $omaggi_non_vuoti);
    }
}

if (!function_exists('format_extra_excel')) {
    function format_extra_excel($extra_list) {
        if (empty($extra_list)) return '';
        
        if (is_string($extra_list)) {
            $extra_list = json_decode($extra_list, true);
        }
        
        if (!is_array($extra_list)) return '';
        
        $extra_formatted = [];
        foreach ($extra_list as $extra) {
            if (!empty($extra['descrizione']) && !empty($extra['prezzo'])) {
                $extra_formatted[] = $extra['descrizione'] . ' ' . format_currency_excel($extra['prezzo']);
            }
        }
        
        return implode(', ', $extra_formatted);
    }
}

if (!function_exists('format_whatsapp_link')) {
    function format_whatsapp_link($telefono) {
        if (empty($telefono)) return '';
        
        // Pulisci il numero di telefono
        $clean_phone = preg_replace('/[^0-9]/', '', $telefono);
        
        // Se inizia con 39, usa così com'è, altrimenti aggiungi 39
        if (!preg_match('/^39/', $clean_phone)) {
            $clean_phone = '39' . $clean_phone;
        }
        
        return 'https://wa.me/' . $clean_phone;
    }
}

// Funzione helper per determinare stato preventivo (legacy)
function determina_stato_preventivo($result) {
    $acconto = floatval($result->acconto ?? 0);
    $importo = floatval($result->importo_totale ?? 0);
    
    if ($acconto > 0 && $importo > 0) {
        return [
            'class' => 'stato-confermato',
            'text' => 'Confermato',
            'icon' => '✅',
            'color' => '#28a745'
        ];
    } elseif ($importo > 0) {
        return [
            'class' => 'stato-in-corso', 
            'text' => 'In corso',
            'icon' => '⏳',
            'color' => '#ffc107'
        ];
    } else {
        return [
            'class' => 'stato-non-confermato',
            'text' => 'Non Confermato', 
            'icon' => '❌',
            'color' => '#dc3545'
        ];
    }
}

// Debug iniziale template
error_log("TEMPLATE DEBUG: excel-scan-page.php caricato");
error_log("TEMPLATE DEBUG: excel_files_list count = " . count($excel_files_list));
error_log("TEMPLATE DEBUG: analysis_results count = " . count($analysis_results));
error_log("TEMPLATE DEBUG: is_googledrive_configured = " . ($is_googledrive_configured ? 'true' : 'false'));
?>

<div class="disco747-excel-scan-wrapper" style="max-width: 1400px; margin: 0 auto; padding: 20px;">

    <!-- Header Pagina -->
    <div style="background: linear-gradient(135deg, #d4af37, #f4e797); padding: 30px; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); margin-bottom: 30px; position: relative; overflow: hidden;">
        
        <!-- Decorazione -->
        <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; opacity: 0.3;"></div>
        <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%; opacity: 0.2;"></div>
        
        <div style="position: relative;">
            <h1 style="margin: 0 0 10px 0; color: #2b1e1a; font-size: 2.2rem; font-weight: 700;">
                📊 Scansione Excel Automatica
            </h1>
            <p style="margin: 0; color: #856404; font-size: 1.1rem; font-weight: 500;">
                Analizza automaticamente i file Excel dalla cartella Google Drive /747-Preventivi/
            </p>
            
            <!-- Breadcrumb aggiornato -->
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.3);">
                <a href="<?php echo admin_url('admin.php?page=disco747-crm'); ?>" style="color: #856404; text-decoration: none; font-weight: 600;">
                    🏠 Dashboard
                </a>
                <span style="color: #856404; margin: 0 10px;">→</span>
                <a href="<?php echo admin_url('admin.php?page=disco747-scan-excel'); ?>" style="color: #2b1e1a; text-decoration: none; font-weight: 600;">
                    📊 Excel Scan
                </a>
                <span style="color: #856404; margin: 0 10px;">→</span>
                <a href="<?php echo admin_url('admin.php?page=disco747-crm&action=dashboard_preventivi'); ?>" class="disco747-button disco747-button-secondary" style="font-size: 12px; padding: 8px 12px;">
                    📋 Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Stato Google Drive -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-header">
            ☁️ Stato Google Drive
        </div>
        <div class="disco747-card-content">
            <?php if ($is_googledrive_configured): ?>
                <div class="disco747-notice success">
                    <p><strong>✅ Google Drive configurato correttamente!</strong></p>
                    <p>La scansione automatica dei file Excel è attiva. I file vengono cercati nella cartella <code>/747-Preventivi/</code>.</p>
                </div>
            <?php else: ?>
                <div class="disco747-notice error">
                    <p><strong>❌ Google Drive non configurato</strong></p>
                    <p>Per utilizzare questa funzione, configura prima Google Drive nelle <a href="<?php echo admin_url('admin.php?page=disco747-settings'); ?>">Impostazioni</a>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lista Files Excel -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-header">
            📁 File Excel Disponibili
            <div id="files-count" style="float: right; font-size: 14px; font-weight: normal;">
                <?php echo count($excel_files_list); ?> file trovati
            </div>
        </div>
        <div class="disco747-card-content">
            
            <!-- Controlli ricerca e refresh -->
            <div style="display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" id="excel-search" 
                           placeholder="🔍 Cerca file Excel per nome..." 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;"
                           <?php echo !$is_googledrive_configured ? 'disabled' : ''; ?>>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" id="search-files-btn" class="disco747-button disco747-button-primary">
                        🔍 Cerca
                    </button>
                    <button type="button" id="refresh-files-btn" class="disco747-button disco747-button-secondary">
                        🔄 Refresh
                    </button>
                    <button type="button" id="refresh-all-btn" class="disco747-button disco747-button-secondary">
                        🔄 Reset Tutto
                    </button>
                </div>
            </div>
            
            <!-- Tabella files -->
            <div id="files-table-container">
                <?php if ($is_googledrive_configured): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <div style="font-size: 48px; margin-bottom: 15px;">⏳</div>
                        <h3 style="margin: 0 0 10px 0;">Caricamento file...</h3>
                        <p style="margin: 0;">Connessione a Google Drive in corso</p>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <div style="font-size: 48px; margin-bottom: 15px;">☁️</div>
                        <h3 style="margin: 0 0 10px 0;">Google Drive non configurato</h3>
                        <p style="margin: 0;">Configura Google Drive per vedere i file disponibili</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Paginazione -->
            <div id="files-pagination" style="margin-top: 20px; text-align: center; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" id="prev-page-btn" class="disco747-button disco747-button-secondary" disabled>
                        ← Precedente
                    </button>
                    <div id="page-info" style="color: #666;">
                        Pagina 1 di 1
                    </div>
                    <button type="button" id="next-page-btn" class="disco747-button disco747-button-secondary" disabled>
                        Successiva →
                    </button>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Analisi Manuale per File ID -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-header">
            🔍 Analisi Manuale File
        </div>
        <div class="disco747-card-content">
            <div class="disco747-notice info">
                <p><strong>💡 Modalità Manuale</strong> - Inserisci l'ID di un file Excel specifico per analizzarlo direttamente.</p>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 20px; align-items: end; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2b1e1a;">
                        ID File Google Drive:
                    </label>
                    <input type="text" id="manual-file-id" 
                           placeholder="Es: 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" 
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-family: monospace; font-size: 13px;"
                           <?php echo !$is_googledrive_configured ? 'disabled' : ''; ?>>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        L'ID del file si trova nell'URL di Google Drive dopo /d/ e prima di /view
                    </div>
                </div>
                <div>
                    <button type="button" id="analyze-manual-btn" class="disco747-button disco747-button-primary">
                        🔬 Analizza File ID
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Risultati Analisi -->
    <div id="analysis-results" style="display: none; margin-bottom: 30px;">
        <div class="disco747-card">
            <div class="disco747-card-header">
                📊 Risultati Analisi File
                <div style="float: right;">
                    <button type="button" id="export-results-btn" class="disco747-button disco747-button-secondary" style="margin-right: 10px;">
                        📤 Esporta JSON
                    </button>
                    <button type="button" id="clear-results-btn" class="disco747-button disco747-button-secondary">
                        🧹 Pulisci
                    </button>
                </div>
            </div>
            <div class="disco747-card-content">
                
                <!-- Summary analisi -->
                <div id="analysis-summary" style="display: none;">
                    <!-- Popolato dinamicamente da JavaScript -->
                </div>
                
                <!-- Dashboard dati estratti -->
                <div id="extracted-data-dashboard">
                    <!-- Popolato dinamicamente da JavaScript -->
                </div>
                
            </div>
        </div>
    </div>

    <!-- Debug Log -->
    <div id="debug-log-section" style="display: none; margin-bottom: 30px;">
        <div class="disco747-card">
            <div class="disco747-card-header">
                🐛 Log Debug Analisi
                <div style="float: right;">
                    <button type="button" id="toggle-log-btn" class="disco747-button disco747-button-secondary" style="margin-right: 10px;">
                        👁️ Mostra
                    </button>
                    <button type="button" id="copy-log-btn" class="disco747-button disco747-button-secondary" style="margin-right: 10px;">
                        📋 Copia
                    </button>
                    <button type="button" id="download-log-btn" class="disco747-button disco747-button-secondary">
                        💾 Scarica
                    </button>
                </div>
            </div>
            <div class="disco747-card-content">
                
                <!-- Statistiche log -->
                <div class="log-stats" style="display: none; margin-bottom: 20px;">
                    <!-- Popolato dinamicamente -->
                </div>
                
                <!-- Contenuto log -->
                <div id="debug-log-content" style="display: none; max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4; border: 1px solid #e9ecef;">
                    <!-- Popolato dinamicamente -->
                </div>
                
            </div>
        </div>
    </div>

    <!-- ANALISI BATCH - SEMPRE VISIBILE SE CI SONO FILE -->
    <?php if (!empty($excel_files_list)): ?>
        <div class="disco747-card" style="margin-bottom: 30px;">
            <div class="disco747-card-header">
                🚀 Analisi Batch - Tutti i File Excel
                <div style="font-size: 0.9em; font-weight: normal;">
                    <?php echo count($excel_files_list); ?> file da analizzare
                </div>
            </div>
            <div class="disco747-card-content">
                <div class="disco747-notice warning">
                    <p><strong>⚠️ Attenzione:</strong> Questa funzione analizzerà TUTTI i file Excel trovati nella cartella /747-Preventivi/.</p>
                    <p>Il processo può richiedere diversi minuti. I risultati verranno salvati automaticamente nel database.</p>
                </div>
                
                <!-- Statistiche Batch -->
                <div class="batch-stats">
                    <div class="batch-stat-card">
                        <div class="batch-stat-number" style="color: #28a745;" id="stat-success">0</div>
                        <div>Successi</div>
                    </div>
                    <div class="batch-stat-card">
                        <div class="batch-stat-number" style="color: #dc3545;" id="stat-errors">0</div>
                        <div>Errori</div>
                    </div>
                    <div class="batch-stat-card">
                        <div class="batch-stat-number" style="color: #ffc107;" id="stat-rate">0%</div>
                        <div>Tasso Successo</div>
                    </div>
                    <div class="batch-stat-card">
                        <div class="batch-stat-number" style="color: #17a2b8;" id="stat-remaining"><?php echo count($excel_files_list); ?></div>
                        <div>Rimanenti</div>
                    </div>
                </div>
                
                <!-- Progress Bar e Timer -->
                <div class="batch-progress-container">
                    <div class="batch-timer" id="batch-timer">00:00:00</div>
                    <div class="batch-progress-bar">
                        <div class="batch-progress-fill" id="batch-progress"></div>
                    </div>
                    <div style="text-align: center; color: #666; font-size: 0.9em;">
                        <span id="progress-text">Pronto per iniziare l'analisi batch</span>
                    </div>
                </div>
                
                <!-- Controlli Batch -->
                <div class="batch-controls">
                    <button type="button" id="start-batch-analysis" class="disco747-button disco747-button-primary">
                        🚀 Avvia Analisi Batch
                    </button>
                    <button type="button" id="stop-batch-analysis" class="disco747-button disco747-button-danger" style="display: none;">
                        ⏹️ Ferma Analisi
                    </button>
                </div>
                
                <!-- Tabella Risultati Batch -->
                <div id="batch-results-container" style="margin-top: 30px; display: none;">
                    <h4 style="margin-bottom: 15px;">📊 Risultati Analisi Batch in Tempo Reale</h4>
                    <div style="max-height: 400px; overflow-y: auto; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        <table class="batch-results-table" id="batch-results-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th style="width: 250px;">📄 Nome File</th>
                                    <th style="width: 100px;">📅 Data</th>
                                    <th style="width: 150px;">🎉 Evento</th>
                                    <th style="width: 80px;">🍽️ Menu</th>
                                    <th style="width: 80px;">👥 Inv.</th>
                                    <th style="width: 100px;">💰 Importo</th>
                                    <th style="width: 100px;">✅ Stato</th>
                                </tr>
                            </thead>
                            <tbody id="batch-results-body">
                                <!-- Righe popolate dinamicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Debug e Controlli -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-header">
            🛠️ Debug e Controlli
        </div>
        <div class="disco747-card-content">
            <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                <button type="button" id="toggle-debug-log" class="disco747-button disco747-button-secondary">
                    👁️ Mostra/Nascondi Log
                </button>
                <button type="button" id="clear-debug-log" class="disco747-button disco747-button-secondary">
                    🧹 Pulisci Log
                </button>
                <button type="button" id="download-debug-log" class="disco747-button disco747-button-secondary">
                    💾 Scarica Log
                </button>
                <label style="display: flex; align-items: center; gap: 8px; color: #666;">
                    <input type="checkbox" id="auto-scroll-log" checked> Auto-scroll log
                </label>
            </div>
            
            <div id="debug-log-container" style="display: none;">
                <div id="debug-log"></div>
            </div>
        </div>
    </div>

    <!-- TABELLA RISULTATI ANALISI EXCEL CON LE 14 COLONNE - ACCESSO DATI CORRETTO -->
    <div class="disco747-card" style="margin-top: 30px;">
        <div class="disco747-card-header">
            📊 Risultati Analisi Excel (<?php echo $total_analysis; ?> analisi trovate)
            <div style="float: right; font-size: 14px; font-weight: normal;">
                <span style="color: #fff;">Ultima analisi: <?php echo $last_analysis_date; ?></span>
            </div>
        </div>
        
        <div class="disco747-card-content">
            
            <!-- Filtri -->
            <div class="analysis-filters" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <div>
                        <input type="text" id="search-analysis" placeholder="Cerca per nome, evento, email..." 
                               style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 250px;">
                    </div>
                    <div>
                        <select id="filter-menu" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">Tutti i Menu</option>
                            <option value="Menu 7">Menu 7</option>
                            <option value="Menu 74">Menu 74</option>
                            <option value="Menu 7-4">Menu 7-4</option>
                            <option value="Menu 747">Menu 747</option>
                            <option value="Menu 7-4-7">Menu 7-4-7</option>
                        </select>
                    </div>
                    <div>
                        <select id="filter-success" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">Tutti gli Stati</option>
                            <option value="1">Solo Successi</option>
                            <option value="0">Solo Errori</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" id="apply-filters" style="padding: 8px 16px; background: #d4af37; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                            Applica Filtri
                        </button>
                        <button type="button" id="reset-filters" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <?php if (empty($analysis_results)): ?>
                <!-- Messaggio nessun risultato -->
                <div style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 15px;">📋</div>
                    <h3 style="margin: 0 0 10px 0;">Nessuna analisi trovata</h3>
                    <p style="margin: 0;">Utilizza la scansione manuale o batch per analizzare i file Excel.</p>
                </div>
            <?php else: ?>
                
                <!-- Statistiche rapide -->
                <div class="analysis-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                    <?php
                    $success_count = 0;
                    $error_count = 0;
                    $total_importo = 0;
                    $total_acconto = 0;
                    
                    foreach ($analysis_results as $result) {
                        if ($result->analysis_success) {
                            $success_count++;
                            $total_importo += floatval($result->importo_totale ?? 0);
                            $total_acconto += floatval($result->acconto ?? 0);
                        } else {
                            $error_count++;
                        }
                    }
                    ?>
                    
                    <div style="background: #d4edda; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 20px; font-weight: bold; color: #155724;">✅ <?php echo $success_count; ?></div>
                        <div style="color: #155724; font-weight: bold;">Analisi Riuscite</div>
                    </div>
                    
                    <div style="background: #f8d7da; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 20px; font-weight: bold; color: #721c24;">❌ <?php echo $error_count; ?></div>
                        <div style="color: #721c24; font-weight: bold;">Errori</div>
                    </div>
                    
                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 20px; font-weight: bold; color: #856404;">💰 <?php echo format_currency_excel($total_importo); ?></div>
                        <div style="color: #856404; font-weight: bold;">Fatturato Totale</div>
                    </div>
                    
                    <div style="background: #d1ecf1; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 20px; font-weight: bold; color: #0c5460;">💳 <?php echo format_currency_excel($total_acconto); ?></div>
                        <div style="color: #0c5460; font-weight: bold;">Acconti Totali</div>
                    </div>
                </div>

                <!-- NUOVA TABELLA CON 14 COLONNE - ACCESSO DATI CORRETTO DAI CAMPI DATABASE -->
                <div class="table-responsive" style="overflow-x: auto; margin-top: 20px;">
                    <table class="analysis-results-table" style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <thead>
                            <tr style="background: linear-gradient(135deg, #d4af37, #f4e797); color: #333;">
                                <th style="padding: 12px 8px; text-align: center; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 100px;">📅 Data evento</th>
                                <th style="padding: 12px 8px; text-align: left; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 120px;">🎉 Tipo evento</th>
                                <th style="padding: 12px 8px; text-align: center; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 120px;">🕐 Orario</th>
                                <th style="padding: 12px 8px; text-align: center; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 80px;">👥 Invitati</th>
                                <th style="padding: 12px 8px; text-align: left; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 150px;">👤 Referente</th>
                                <th style="padding: 12px 8px; text-align: center; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 120px;">📱 Telefono</th>
                                <th style="padding: 12px 8px; text-align: left; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 180px;">📧 Email</th>
                                <th style="padding: 12px 8px; text-align: center; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 100px;">🍽️ Menu</th>
                                <th style="padding: 12px 8px; text-align: right; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 100px;">💰 Importo totale</th>
                                <th style="padding: 12px 8px; text-align: right; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 100px;">💳 Acconto</th>
                                <th style="padding: 12px 8px; text-align: right; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 100px;">💸 Da saldare</th>
                                <th style="padding: 12px 8px; text-align: left; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 150px;">🎁 Omaggi</th>
                                <th style="padding: 12px 8px; text-align: left; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 150px;">➕ Extra</th>
                                <th style="padding: 12px 8px; text-align: center; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 120px;">✅ Stato</th>
                                <th style="padding: 12px 8px; text-align: center; font-weight: bold; border-bottom: 2px solid #c19b26; min-width: 100px;">⚙️ Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analysis_results as $index => $result): ?>
                                <?php 
                                // ACCESSO DIRETTO AI CAMPI DATABASE - NON PIÙ DA JSON
                                
                                // Parse orari (ora da campo diretto)
                                $orari = parse_orari_excel($result->orario_inizio ?? ''); // o da altro campo orario se esiste
                                
                                // Determina stato
                                $stato = determine_stato_excel($result, $result->filename ?? '');
                                
                                // Formatta omaggi e extra (da campi JSON se esistono, altrimenti da campi separati)
                                $omaggi = format_omaggi_excel($result->omaggi_list ?? '');
                                $extra = format_extra_excel($result->extra_list ?? '');
                                ?>
                                <tr class="table-row analysis-row" style="<?php echo $index % 2 === 0 ? 'background: #f9f9f9;' : 'background: white;'; ?> border-bottom: 1px solid #e9ecef;">
                                    
                                    <!-- 1. Data evento (formato dd/mm/YYYY) - CAMPO DIRETTO -->
                                    <td style="padding: 12px 8px; text-align: center; font-weight: bold; color: #2b1e1a;">
                                        <?php echo format_date_excel($result->data_evento ?? ''); ?>
                                    </td>
                                    
                                    <!-- 2. Tipo evento - CAMPO DIRETTO -->
                                    <td style="padding: 12px 8px; color: #2b1e1a;">
                                        <?php echo esc_html($result->tipo_evento ?? 'N/A'); ?>
                                    </td>
                                    
                                    <!-- 3. Orario inizio - Orario fine -->
                                    <td style="padding: 12px 8px; text-align: center; font-size: 0.9em;">
                                        <?php 
                                        $orario_inizio = format_time_excel($result->orario_inizio ?? '');
                                        $orario_fine = format_time_excel($result->orario_fine ?? '');
                                        
                                        if (!empty($orario_inizio) && !empty($orario_fine) && $orario_fine != '00:00') {
                                            echo $orario_inizio . '<br><span style="color: #666;">↓</span><br>' . $orario_fine;
                                        } elseif (!empty($orario_inizio)) {
                                            echo $orario_inizio;
                                        } else {
                                            echo '<span style="color: #ccc;">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                    
                                    <!-- 4. Numero invitati (int) - CAMPO DIRETTO -->
                                    <td style="padding: 12px 8px; text-align: center; font-weight: bold; color: #17a2b8;">
                                        <?php echo intval($result->numero_invitati ?? 0); ?>
                                    </td>
                                    
                                    <!-- 5. Referente (Nome + Cognome) - CAMPI DIRETTI -->
                                    <td style="padding: 12px 8px; color: #2b1e1a;">
                                        <?php 
                                        $nome = trim($result->referente_nome ?? '');
                                        $cognome = trim($result->referente_cognome ?? '');
                                        echo esc_html(trim($nome . ' ' . $cognome)) ?: 'N/A';
                                        ?>
                                    </td>
                                    
                                    <!-- 6. Telefono (link WhatsApp) - CAMPO DIRETTO -->
                                    <td style="padding: 12px 8px; text-align: center;">
                                        <?php if (!empty($result->telefono)): ?>
                                            <a href="<?php echo format_whatsapp_link($result->telefono); ?>" 
                                               target="_blank" 
                                               style="background: #25D366; color: white; padding: 6px 10px; border-radius: 15px; text-decoration: none; font-size: 0.85em; font-weight: 600;">
                                                📱 <?php echo esc_html($result->telefono); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #ccc;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- 7. Email - CAMPO DIRETTO -->
                                    <td style="padding: 12px 8px; color: #2b1e1a; font-size: 0.9em;">
                                        <?php if (!empty($result->email)): ?>
                                            <a href="mailto:<?php echo esc_attr($result->email); ?>" 
                                               style="color: #17a2b8; text-decoration: none;">
                                                <?php echo esc_html($result->email); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #ccc;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- 8. Menu - CAMPO DIRETTO -->
                                    <td style="padding: 12px 8px; text-align: center; font-weight: bold;">
                                        <span style="background: #17a2b8; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.85em;">
                                            <?php echo esc_html($result->menu ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    
                                    <!-- 9. Importo totale (formattato: € 1.234,56) - CAMPO DIRETTO -->
                                    <td style="padding: 12px 8px; text-align: right; font-weight: bold; color: #28a745;">
                                        <?php echo format_currency_excel($result->importo_totale ?? 0); ?>
                                    </td>
                                    
                                    <!-- 10. Acconto (formattato) - CAMPO DIRETTO -->
                                    <td style="padding: 12px 8px; text-align: right; font-weight: bold; color: <?php echo floatval($result->acconto ?? 0) > 0 ? '#28a745' : '#6c757d'; ?>;">
                                        <?php echo format_currency_excel($result->acconto ?? 0); ?>
                                    </td>
                                    
                                    <!-- 11. Da saldare (formattato) - CAMPO DIRETTO -->
                                    <td style="padding: 12px 8px; text-align: right; font-weight: bold; color: #ffc107;">
                                        <?php echo format_currency_excel($result->da_saldare ?? 0); ?>
                                    </td>
                                    
                                    <!-- 12. Omaggi (stringa unica separata da virgole) - CAMPO JSON O SEPARATI -->
                                    <td style="padding: 12px 8px; color: #28a745; font-size: 0.9em;">
                                        <?php 
                                        echo !empty($omaggi) ? esc_html($omaggi) : '<span style="color: #ccc;">Nessuno</span>';
                                        ?>
                                    </td>
                                    
                                    <!-- 13. Extra (formato: Descr1 €X, Descr2 €Y) - CAMPO JSON O SEPARATI -->
                                    <td style="padding: 12px 8px; color: #ff6b35; font-size: 0.9em;">
                                        <?php 
                                        echo !empty($extra) ? esc_html($extra) : '<span style="color: #ccc;">Nessuno</span>';
                                        ?>
                                    </td>
                                    
                                    <!-- 14. Stato (badge testuale) -->
                                    <td style="padding: 12px 8px; text-align: center;">
                                        <span class="status-badge <?php echo $stato['class']; ?>" 
                                              style="background: <?php echo $stato['color']; ?>; color: white; padding: 6px 12px; border-radius: 15px; font-size: 0.85em; font-weight: 600;">
                                            <?php echo $stato['text']; ?>
                                        </span>
                                    </td>
                                    
                                    <!-- 15. Azioni (pulsante modifica) -->
                                    <td style="padding: 12px 8px; text-align: center;">
                                        <?php if ($result->analysis_success): ?>
                                            <?php
                                            // CORRETTO: URL per modifica preventivo con dati pre-compilati
                                            $edit_url = admin_url('admin.php?page=disco747-crm&action=edit_preventivo&source=excel_analysis&analysis_id=' . intval($result->id));
                                            ?>
                                            <a href="<?php echo esc_url($edit_url); ?>" 
                                               class="disco747-button disco747-button-primary" 
                                               style="padding: 6px 12px; font-size: 0.8em; text-decoration: none; border-radius: 6px; display: inline-block;"
                                               title="Modifica preventivo con dati pre-compilati">
                                                ✏️ Modifica
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #ccc; font-size: 0.8em;">Non disponibile</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginazione se necessaria (da implementare) -->
                <div style="text-align: center; margin-top: 20px; color: #666;">
                    Visualizzati <?php echo count($analysis_results); ?> risultati
                </div>
                
            <?php endif; ?>
            
        </div>
    </div>

</div>

<!-- CSS Specificatamente per questa pagina -->
<style>
.disco747-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(43, 30, 26, 0.1);
    overflow: hidden;
    margin-bottom: 25px;
}

.disco747-card-header {
    background: linear-gradient(135deg, #2b1e1a 0%, #3d2f2a 100%);
    color: white;
    padding: 20px 25px;
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.disco747-card-content {
    padding: 25px;
}

.disco747-notice {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 5px solid;
}

.disco747-notice.success {
    background: #d4edda;
    color: #155724;
    border-left-color: #28a745;
}

.disco747-notice.error {
    background: #f8d7da;
    color: #721c24;
    border-left-color: #dc3545;
}

.disco747-notice.warning {
    background: #fff3cd;
    color: #856404;
    border-left-color: #ffc107;
}

.disco747-notice.info {
    background: #d1ecf1;
    color: #0c5460;
    border-left-color: #17a2b8;
}

.disco747-button {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
    font-size: 14px;
}

.disco747-button-primary {
    background: linear-gradient(135deg, #d4af37 0%, #f4e797 100%);
    color: #2b1e1a;
}

.disco747-button-secondary {
    background: #6c757d;
    color: white;
}

.disco747-button-danger {
    background: #dc3545;
    color: white;
}

.disco747-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.disco747-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Batch Analysis Styles */
.batch-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.batch-stat-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    border: 2px solid #e9ecef;
}

.batch-stat-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.batch-progress-container {
    margin: 25px 0;
}

.batch-timer {
    text-align: center;
    font-size: 1.2rem;
    font-weight: bold;
    color: #2b1e1a;
    margin-bottom: 15px;
}

.batch-progress-bar {
    width: 100%;
    height: 25px;
    background: #e9ecef;
    border-radius: 15px;
    overflow: hidden;
    margin-bottom: 10px;
}

.batch-progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    width: 0%;
    transition: width 0.3s ease;
}

.batch-controls {
    text-align: center;
    margin: 25px 0;
}

/* Tabella risultati batch */
.batch-results-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.batch-results-table th {
    background: linear-gradient(135deg, #2b1e1a 0%, #3d2f2a 100%);
    color: white;
    padding: 12px 8px;
    text-align: left;
    font-weight: 600;
    font-size: 0.9rem;
}

.batch-results-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.85rem;
}

.batch-results-table tr:nth-child(even) {
    background: #f8f9fa;
}

.batch-results-table .status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 600;
    white-space: nowrap;
}

.status-warning { background: #ffc107; color: #212529; }
.status-success { background: #28a745; color: white; }
.status-error { background: #dc3545; color: white; }

/* Stili responsive per la tabella analisi */
.analysis-results-table th {
    font-size: 0.85em;
    white-space: nowrap;
}

.analysis-results-table td {
    font-size: 0.9em;
    vertical-align: top;
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.analysis-row:hover {
    background: #f1f3f4 !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.status-badge {
    display: inline-block;
    white-space: nowrap;
}

@media (max-width: 1200px) {
    .analysis-results-table {
        font-size: 0.8em;
    }
    
    .analysis-results-table th,
    .analysis-results-table td {
        padding: 8px 4px;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .disco747-excel-scan-wrapper {
        padding: 10px;
    }
    
    .disco747-card-header {
        padding: 15px 20px;
        font-size: 1.1rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .disco747-card-content {
        padding: 20px;
    }
    
    .batch-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .table-responsive {
        font-size: 0.75em;
    }
    
    .analysis-filters {
        flex-direction: column;
        align-items: stretch;
    }
    
    .analysis-filters > div {
        margin-bottom: 10px;
    }
}
</style>

<!-- JavaScript COMPLETO CON BATCH ANALYSIS -->
<script>
jQuery(document).ready(function($) {
    console.log('Inizializzazione Excel Scanner...');
    
    // Variabili globali per batch analysis
    let batchFiles = <?php echo json_encode($excel_files_list); ?>;
    let isRunningBatch = false;
    let startTime = null;
    let timerInterval = null;
    let currentFileIndex = 0;
    let successCount = 0;
    let errorCount = 0;
    
    // Debug log function
    function addDebugLog(message, type = 'info') {
        const now = new Date();
        const timestamp = now.toLocaleTimeString('it-IT');
        const typeColors = {
            'info': '#17a2b8',
            'success': '#28a745',
            'warning': '#ffc107',
            'error': '#dc3545'
        };
        
        const logContainer = $('#debug-log-container #debug-log');
        if (logContainer.length === 0) {
            console.log(`[${timestamp}] ${message}`);
            return;
        }
        
        const logEntry = `
            <div style="margin-bottom: 5px; padding: 8px 12px; background: #f8f9fa; border-left: 4px solid ${typeColors[type]}; border-radius: 4px; font-size: 0.85em;">
                <span style="color: #666; font-weight: bold;">[${timestamp}]</span>
                <span style="color: ${typeColors[type]}; font-weight: 600; text-transform: uppercase; margin-left: 10px;">${type}</span>
                <span style="margin-left: 10px;">${message}</span>
            </div>
        `;
        
        logContainer.append(logEntry);
        
        // Auto-scroll se abilitato
        if ($('#auto-scroll-log').is(':checked')) {
            logContainer.scrollTop(logContainer[0].scrollHeight);
        }
        
        // Limita numero massimo di righe log
        const maxLines = 500;
        const logEntries = logContainer.children();
        if (logEntries.length > maxLines) {
            logEntries.first().remove();
        }
    }
    
    // Aggiorna statistiche batch
    function updateStats() {
        $('#stat-success').text(successCount);
        $('#stat-errors').text(errorCount);
        
        const totalProcessed = successCount + errorCount;
        const rate = totalProcessed > 0 ? Math.round((successCount / totalProcessed) * 100) : 0;
        $('#stat-rate').text(rate + '%');
        
        const remaining = Math.max(0, batchFiles.length - totalProcessed);
        $('#stat-remaining').text(remaining);
        
        // Aggiorna progress bar
        if (batchFiles.length > 0) {
            const progress = Math.round((totalProcessed / batchFiles.length) * 100);
            $('#batch-progress').css('width', progress + '%');
            $('#progress-text').text(`File ${totalProcessed} di ${batchFiles.length} processati (${progress}%)`);
        }
    }
    
    // Aggiorna timer batch
    function updateTimer() {
        if (!startTime) return;
        
        const elapsed = new Date() - startTime;
        const hours = Math.floor(elapsed / 3600000);
        const minutes = Math.floor((elapsed % 3600000) / 60000);
        const seconds = Math.floor((elapsed % 60000) / 1000);
        
        $('#batch-timer').text(
            String(hours).padStart(2, '0') + ':' +
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0')
        );
    }
    
    // Crea riga risultato batch
    function createBatchResultRow(fileNumber, fileName) {
        return `
            <tr id="batch-row-${fileNumber + 1}">
                <td style="padding: 8px; text-align: center; font-weight: bold;">${fileNumber + 1}</td>
                <td style="padding: 8px;" title="${fileName}">${fileName.length > 32 ? fileName.substring(0, 32) + '...' : fileName}</td>
                <td style="padding: 8px;"><span class="batch-data-evento">-</span></td>
                <td style="padding: 8px;"><span class="batch-tipo-evento">-</span></td>
                <td style="padding: 8px;"><span class="batch-menu">-</span></td>
                <td style="padding: 8px; text-align: center;"><span class="batch-invitati">-</span></td>
                <td style="padding: 8px; text-align: right;"><span class="batch-importo">-</span></td>
                <td style="padding: 8px; text-align: center;">
                    <span class="status-badge status-warning">⏳ In attesa</span>
                </td>
            </tr>
        `;
    }
    
    // Aggiorna riga risultato batch
    function updateBatchResultRow(fileNumber, data, status) {
        const row = $(`#batch-row-${fileNumber}`);
        
        if (status === 'processing') {
            row.removeClass('success error').addClass('processing');
            row.find('.status-badge').removeClass('status-success status-error status-warning')
               .addClass('status-warning').html('⏳ Analisi...');
        } else if (status === 'success') {
            row.removeClass('processing error').addClass('success');
            row.find('.status-badge').removeClass('status-warning status-error')
               .addClass('status-success').html('✅ Successo');
               
            // Popola dati se disponibili
            if (data) {
                row.find('.batch-data-evento').text(data.data_evento || 'N/A');
                row.find('.batch-tipo-evento').text(data.tipo_evento || 'N/A');
                row.find('.batch-menu').text(data.menu || 'N/A');
                row.find('.batch-invitati').text(data.numero_invitati || '0');
                
                const importo = parseFloat(data.importo_totale || 0);
                row.find('.batch-importo').text(importo > 0 ? '€' + importo.toFixed(2) : '-');
            }
        } else if (status === 'error') {
            row.removeClass('processing success').addClass('error');
            row.find('.status-badge').removeClass('status-warning status-success')
               .addClass('status-error').html('❌ Errore');
        }
    }
    
    // Handler avvio batch analysis
    $('#start-batch-analysis').on('click', function() {
        if (batchFiles.length === 0) {
            alert('Nessun file Excel da analizzare.');
            return;
        }
        
        const confirmed = confirm(
            `Sei sicuro di voler analizzare TUTTI i ${batchFiles.length} file Excel?\n\n` +
            `Questa operazione può richiedere diversi minuti e i risultati verranno salvati automaticamente nel database.`
        );
        
        if (!confirmed) return;
        
        // Inizializza batch
        isRunningBatch = true;
        currentFileIndex = 0;
        successCount = 0;
        errorCount = 0;
        startTime = new Date();
        
        // Mostra controlli
        $('#start-batch-analysis').hide();
        $('#stop-batch-analysis').show();
        $('#batch-results-container').show();
        
        // Avvia timer
        timerInterval = setInterval(updateTimer, 1000);
        
        // Crea righe tabella
        const tbody = $('#batch-results-body');
        tbody.empty();
        
        batchFiles.forEach((file, index) => {
            tbody.append(createBatchResultRow(index, file.name));
        });
        
        addDebugLog('🚀 ANALISI BATCH AVVIATA', 'success');
        addDebugLog(`📄 File da analizzare: ${batchFiles.length}`, 'info');
        
        // Avvia processo
        processBatchFile();
    });
    
    // Handler stop batch analysis
    $('#stop-batch-analysis').on('click', function() {
        if (confirm('Sei sicuro di voler fermare l\'analisi batch?')) {
            isRunningBatch = false;
            clearInterval(timerInterval);
            
            $('#start-batch-analysis').show();
            $('#stop-batch-analysis').hide();
            
            addDebugLog('⏹️ ANALISI BATCH FERMATA DALL\'UTENTE', 'warning');
        }
    });
    
    // Funzione principale batch processing
    function processBatchFile() {
        if (!isRunningBatch || currentFileIndex >= batchFiles.length) {
            completeBatchAnalysis();
            return;
        }
        
        const file = batchFiles[currentFileIndex];
        const fileNumber = currentFileIndex + 1;
        
        addDebugLog(`📄 Analisi file ${fileNumber}/${batchFiles.length}: ${file.name}`, 'info');
        
        // Aggiorna stato riga
        updateBatchResultRow(fileNumber, null, 'processing');
        
        // Chiamata AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_batch_scan_excel',
                nonce: '<?php echo wp_create_nonce("disco747_batch_scan"); ?>',
                file_id: file.id,
                file_name: file.name,
                file_path: file.path || '',
                current_index: currentFileIndex,
                total_files: batchFiles.length
            },
            success: function(response) {
                if (response.success && response.data && response.data.ok) {
                    successCount++;
                    addDebugLog(`✅ File ${fileNumber} analizzato con successo`, 'success');
                    
                    // Aggiorna riga nella tabella
                    updateBatchResultRow(fileNumber, response.data.data, 'success');
                    
                    // Log dati se disponibili
                    const data = response.data.data;
                    if (data) {
                        addDebugLog(`📊 Menu: ${data.menu || 'N/A'}, Data: ${data.data_evento || 'N/A'}, Invitati: ${data.numero_invitati || 'N/A'}`, 'info');
                    }
                } else {
                    errorCount++;
                    addDebugLog(`❌ File ${fileNumber} con errori: ${response.data?.error || 'Errore sconosciuto'}`, 'error');
                    
                    // Aggiorna riga nella tabella con errore
                    updateBatchResultRow(fileNumber, response.data, 'error');
                }
                
                // Aggiorna statistiche
                updateStats();
                
                // Processa file successivo
                currentFileIndex++;
                setTimeout(processBatchFile, 500);
            },
            error: function(xhr, status, error) {
                errorCount++;
                addDebugLog(`❌ Errore AJAX per file ${fileNumber}: ${error}`, 'error');
                
                // Aggiorna riga con errore
                updateBatchResultRow(fileNumber, {}, 'error');
                
                // Aggiorna statistiche
                updateStats();
                
                // Processa file successivo
                currentFileIndex++;
                setTimeout(processBatchFile, 500);
            }
        });
    }
    
    // Completa analisi batch
    function completeBatchAnalysis() {
        isRunningBatch = false;
        clearInterval(timerInterval);
        
        $('#start-batch-analysis').show();
        $('#stop-batch-analysis').hide();
        
        const totalFiles = batchFiles.length;
        const successFiles = successCount;
        const errorFiles = errorCount;
        const successRate = totalFiles > 0 ? Math.round((successFiles / totalFiles) * 100) : 0;
        
        addDebugLog('🎉 ANALISI BATCH COMPLETATA', 'success');
        addDebugLog(`📊 Risultati: ${successFiles} successi, ${errorFiles} errori su ${totalFiles} file`, 'info');
        
        // Notifica e ricarica automatica
        alert(`🎉 Analisi Batch Completata!\n\nSuccessi: ${successFiles}\nErrori: ${errorFiles}\nTasso di successo: ${successRate}%\n\nLa pagina si ricaricherà per mostrare tutti i risultati salvati nel database.`);
        
        // Ricarica la pagina dopo 3 secondi per mostrare i risultati dal database
        setTimeout(function() {
            window.location.reload();
        }, 3000);
    }
    
    // Toggle debug log
    $('#toggle-debug-log').on('click', function() {
        $('#debug-log-container').toggle();
        const isVisible = $('#debug-log-container').is(':visible');
        $(this).text(isVisible ? '👁️ Nascondi Log' : '👁️ Mostra Log');
        
        addDebugLog(`Log debug ${isVisible ? 'mostrato' : 'nascosto'}`, 'info');
    });
    
    // Pulisci debug log
    $('#clear-debug-log').on('click', function() {
        $('#debug-log-container #debug-log').empty();
        addDebugLog('Log debug pulito', 'success');
    });
    
    // Scarica debug log
    $('#download-debug-log').on('click', function() {
        const logContent = $('#debug-log-container #debug-log').text();
        
        if (!logContent.trim()) {
            alert('Nessun log da scaricare');
            return;
        }
        
        const blob = new Blob([logContent], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `disco747-excel-scan-${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.log`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        addDebugLog('💾 Log scaricato', 'success');
    });
    
    // Filtri tabella risultati (aggiornati per nuova struttura colonne)
    $('#apply-filters').on('click', function() {
        const searchTerm = $('#search-analysis').val().toLowerCase();
        const menuFilter = $('#filter-menu').val();
        const successFilter = $('#filter-success').val();
        
        $('.table-row').each(function() {
            const row = $(this);
            let showRow = true;
            
            // Filtro ricerca
            if (searchTerm) {
                const rowText = row.text().toLowerCase();
                if (rowText.indexOf(searchTerm) === -1) {
                    showRow = false;
                }
            }
            
            // Filtro menu - colonna Menu è la 8a
            if (menuFilter) {
                const menuCell = row.find('td:nth-child(8)').text().trim();
                if (menuCell !== menuFilter) {
                    showRow = false;
                }
            }
            
            // Filtro stato - ora è la colonna 14a
            if (successFilter !== '') {
                const statusCell = row.find('td:nth-child(14) .status-badge');
                const isSuccess = statusCell.hasClass('stato-confermato');
                if ((successFilter === '1' && !isSuccess) || (successFilter === '0' && isSuccess)) {
                    showRow = false;
                }
            }
            
            row.toggle(showRow);
        });
        
        addDebugLog(`🔍 Filtri applicati: ricerca="${searchTerm}", menu="${menuFilter}", stato="${successFilter}"`, 'info');
    });
    
    $('#reset-filters').on('click', function() {
        $('#search-analysis').val('');
        $('#filter-menu').val('');
        $('#filter-success').val('');
        $('.table-row').show();
        
        addDebugLog('🔄 Filtri resettati', 'info');
    });
    
    // Inizializzazione
    updateStats();
    addDebugLog('🔧 Interfaccia JavaScript inizializzata', 'success');
    if (batchFiles.length > 0) {
        addDebugLog(`📄 Pronti per l'analisi: ${batchFiles.length} file Excel`, 'info');
    }
    
    // Log della tabella se presente
    const tableRows = $('.table-row').length;
    if (tableRows > 0) {
        addDebugLog(`📊 Tabella risultati caricata: ${tableRows} analisi trovate`, 'info');
    }
    
    console.log('Excel Scanner inizializzazione completata');
});
</script>