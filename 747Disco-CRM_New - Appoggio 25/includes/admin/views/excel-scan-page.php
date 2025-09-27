<?php
/**
 * Template per pagina Scansione Excel Auto
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @version    11.5.9-EXCEL-SCAN
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// Variabili disponibili dal controller
$is_googledrive_configured = isset($is_googledrive_configured) ? $is_googledrive_configured : false;
$excel_files_list = isset($excel_files_list) ? $excel_files_list : array();
$analysis_results = isset($analysis_results) ? $analysis_results : array();
$total_analysis = isset($total_analysis) ? $total_analysis : 0;
$last_analysis_date = isset($last_analysis_date) ? $last_analysis_date : 'Mai';
?>

<div class="disco747-excel-scan-wrapper" style="max-width: 1600px; margin: 0 auto; padding: 20px;">

    <!-- Header -->
    <div style="background: linear-gradient(135deg, #d4af37, #f4e797); padding: 30px; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); margin-bottom: 30px;">
        <h1 style="margin: 0 0 10px 0; color: #2b1e1a; font-size: 2.2rem; font-weight: 700;">
            📊 Scansione Excel Automatica
        </h1>
        <p style="margin: 0; color: #856404; font-size: 1.1rem;">
            Analizza automaticamente i file Excel dalla cartella Google Drive /747-Preventivi/
        </p>
        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.3);">
            <a href="<?php echo admin_url('admin.php?page=disco747-crm'); ?>" style="color: #856404; text-decoration: none;">
                🏠 Dashboard
            </a>
            <span style="color: #856404; margin: 0 10px;">→</span>
            <span style="color: #2b1e1a; font-weight: 600;">📊 Excel Scan</span>
        </div>
    </div>

    <!-- Stato Sistema -->
    <div class="disco747-card" style="margin-bottom: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div class="disco747-card-header" style="background: linear-gradient(135deg, #2b1e1a, #3d2f2a); color: white; padding: 20px; border-radius: 10px 10px 0 0;">
            ⚙️ Stato Sistema
        </div>
        <div class="disco747-card-content" style="padding: 25px;">
            <?php if ($is_googledrive_configured): ?>
                <div style="background: #d4edda; border-left: 5px solid #28a745; padding: 15px; margin-bottom: 20px;">
                    <strong>✅ Google Drive configurato correttamente!</strong><br>
                    La scansione automatica dei file Excel è attiva.
                </div>
            <?php else: ?>
                <div style="background: #f8d7da; border-left: 5px solid #dc3545; padding: 15px; margin-bottom: 20px;">
                    <strong>❌ Google Drive non configurato</strong><br>
                    <a href="<?php echo admin_url('admin.php?page=disco747-settings'); ?>">Configura le credenziali OAuth</a> per attivare la scansione.
                </div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2rem; font-weight: bold; color: #d4af37;"><?php echo count($excel_files_list); ?></div>
                    <div style="color: #666;">File Excel Disponibili</div>
                </div>
                <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2rem; font-weight: bold; color: #28a745;"><?php echo $total_analysis; ?></div>
                    <div style="color: #666;">Analisi Completate</div>
                </div>
                <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 1rem; color: #666;">Ultima Analisi</div>
                    <div style="font-weight: bold;"><?php echo esc_html($last_analysis_date); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Debug Log -->
    <div class="disco747-card" style="margin-bottom: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div class="disco747-card-header" style="background: linear-gradient(135deg, #2b1e1a, #3d2f2a); color: white; padding: 20px; border-radius: 10px 10px 0 0;">
            🐛 Debug e Log
            <div style="float: right;">
                <button type="button" id="toggle-debug" class="button button-secondary" style="margin-right: 10px;">
                    Mostra/Nascondi
                </button>
                <button type="button" id="clear-debug" class="button button-secondary">
                    Pulisci Log
                </button>
            </div>
        </div>
        <div class="disco747-card-content" id="debug-content" style="padding: 25px; display: none;">
            <div id="debug-log" style="background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto;">
                <div class="log-entry" data-timestamp="<?php echo current_time('Y-m-d H:i:s'); ?>">
                    [<?php echo current_time('H:i:s'); ?>] Sistema inizializzato
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($excel_files_list) && $is_googledrive_configured): ?>
    <!-- Scansione Batch -->
    <div class="disco747-card" style="margin-bottom: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div class="disco747-card-header" style="background: linear-gradient(135deg, #2b1e1a, #3d2f2a); color: white; padding: 20px; border-radius: 10px 10px 0 0;">
            🚀 Scansione Batch
            <span style="font-size: 0.9em; font-weight: normal; margin-left: 10px;">
                (<?php echo count($excel_files_list); ?> file pronti)
            </span>
        </div>
        <div class="disco747-card-content" style="padding: 25px;">
            <div style="background: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; margin-bottom: 20px;">
                <strong>⚠️ Attenzione:</strong> La scansione batch analizzerà tutti i file Excel trovati. 
                Il processo può richiedere diversi minuti.
            </div>
            
            <!-- Progress Container -->
            <div id="batch-progress" style="display: none; margin-bottom: 20px;">
                <div style="margin-bottom: 10px;">
                    <span id="batch-status">Preparazione...</span>
                    <span style="float: right;">
                        <span id="batch-current">0</span> / <span id="batch-total">0</span> file
                    </span>
                </div>
                <div style="background: #e9ecef; border-radius: 10px; overflow: hidden; height: 30px;">
                    <div id="batch-progress-bar" style="background: linear-gradient(90deg, #28a745, #20c997); height: 100%; width: 0%; transition: width 0.3s;"></div>
                </div>
                <div style="margin-top: 10px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center;">
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px;">
                        ✅ Successi: <span id="batch-success">0</span>
                    </div>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px;">
                        ❌ Errori: <span id="batch-errors">0</span>
                    </div>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px;">
                        ⏱️ Tempo: <span id="batch-time">00:00</span>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center;">
                <button type="button" id="start-batch" class="button button-primary button-hero">
                    🚀 Avvia Scansione Batch
                </button>
                <button type="button" id="stop-batch" class="button button-secondary button-hero" style="display: none; margin-left: 10px;">
                    ⏹️ Ferma Scansione
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabella Risultati -->
    <div class="disco747-card" style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div class="disco747-card-header" style="background: linear-gradient(135deg, #2b1e1a, #3d2f2a); color: white; padding: 20px; border-radius: 10px 10px 0 0;">
            📋 Risultati Analisi Excel (<?php echo $total_analysis; ?> record)
            <div style="float: right;">
                <button type="button" id="refresh-table" class="button button-secondary">
                    🔄 Aggiorna
                </button>
            </div>
        </div>
        <div class="disco747-card-content" style="padding: 25px;">
            
            <!-- Filtri -->
            <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" id="search-analysis" placeholder="🔍 Cerca..." style="flex: 1; min-width: 200px; padding: 8px;">
                <select id="filter-menu" style="padding: 8px;">
                    <option value="">Tutti i Menu</option>
                    <option value="Menu 7">Menu 7</option>
                    <option value="Menu 74">Menu 74</option>
                    <option value="Menu 747">Menu 747</option>
                </select>
                <select id="filter-stato" style="padding: 8px;">
                    <option value="">Tutti gli Stati</option>
                    <option value="CONF">Confermato</option>
                    <option value="NO">Annullato</option>
                    <option value="Neutro">In Attesa</option>
                </select>
            </div>
            
            <!-- Tabella -->
            <div style="overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped" id="analysis-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 100px;">Data Evento</th>
                            <th>Tipo Evento</th>
                            <th style="width: 80px;">Menu</th>
                            <th>Cliente</th>
                            <th style="width: 80px;">Invitati</th>
                            <th style="width: 100px;">Importo</th>
                            <th style="width: 80px;">Stato</th>
                            <th style="width: 120px;">File</th>
                            <th style="width: 100px;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <?php if (!empty($analysis_results)): ?>
                            <?php foreach ($analysis_results as $row): ?>
                                <tr data-id="<?php echo esc_attr($row['id']); ?>">
                                    <td><?php echo esc_html($row['id']); ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($row['data_evento'])) {
                                            echo date('d/m/Y', strtotime($row['data_evento']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($row['tipo_evento'] ?? '-'); ?></td>
                                    <td><?php echo esc_html($row['tipo_menu'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                        $nome_completo = trim(($row['nome_referente'] ?? '') . ' ' . ($row['cognome_referente'] ?? ''));
                                        echo esc_html($nome_completo ?: '-');
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($row['numero_invitati'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($row['importo'])) {
                                            echo '€ ' . number_format($row['importo'], 2, ',', '.');
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $stato = 'Neutro';
                                        if (!empty($row['filename'])) {
                                            $filename_upper = strtoupper($row['filename']);
                                            if (strpos($filename_upper, 'CONF') === 0) {
                                                $stato = 'CONF';
                                            } elseif (strpos($filename_upper, 'NO') === 0) {
                                                $stato = 'NO';
                                            }
                                        }
                                        
                                        $badge_class = 'badge-secondary';
                                        if ($stato === 'CONF') $badge_class = 'badge-success';
                                        if ($stato === 'NO') $badge_class = 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>" style="padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                            <?php echo esc_html($stato); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['file_id'])): ?>
                                            <a href="https://drive.google.com/file/d/<?php echo esc_attr($row['file_id']); ?>/view" 
                                               target="_blank" 
                                               title="<?php echo esc_attr($row['filename'] ?? 'Apri in Drive'); ?>"
                                               style="color: #d4af37; text-decoration: none;">
                                                📁 Drive
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=disco747-crm&action=form_preventivo&source=excel_analysis&analysis_id=' . $row['id']); ?>" 
                                           class="button button-small"
                                           title="Modifica preventivo">
                                            ✏️ Modifica
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 20px;">
                                    Nessuna analisi trovata. Avvia una scansione batch per popolare la tabella.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
</div>

<style>
.disco747-card {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.badge {
    display: inline-block;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-success { background: #28a745; color: white; }
.badge-danger { background: #dc3545; color: white; }
.badge-secondary { background: #6c757d; color: white; }

.log-entry {
    padding: 2px 0;
    border-bottom: 1px solid #dee2e6;
}

.log-entry.error { color: #dc3545; }
.log-entry.success { color: #28a745; }
.log-entry.warning { color: #ffc107; }
.log-entry.info { color: #17a2b8; }
</style>

<script>
jQuery(document).ready(function($) {
    // Debug log functions
    function addDebugLog(message, type = 'info') {
        const time = new Date().toLocaleTimeString();
        const logEntry = $('<div class="log-entry ' + type + '">[' + time + '] ' + message + '</div>');
        $('#debug-log').append(logEntry);
        
        // Auto scroll
        const debugLog = $('#debug-log')[0];
        if (debugLog) {
            debugLog.scrollTop = debugLog.scrollHeight;
        }
    }
    
    // Toggle debug
    $('#toggle-debug').on('click', function() {
        $('#debug-content').slideToggle();
        addDebugLog('Debug panel toggled');
    });
    
    // Clear debug
    $('#clear-debug').on('click', function() {
        $('#debug-log').html('<div class="log-entry info">[' + new Date().toLocaleTimeString() + '] Log cleared</div>');
    });
    
    // Batch scanning
    let batchFiles = <?php echo json_encode($excel_files_list); ?>;
    let currentIndex = 0;
    let isScanning = false;
    let batchStartTime = null;
    let batchTimer = null;
    let successCount = 0;
    let errorCount = 0;
    
    $('#start-batch').on('click', function() {
        if (isScanning) return;
        
        addDebugLog('=== INIZIO SCANSIONE BATCH ===', 'info');
        addDebugLog('File da processare: ' + batchFiles.length, 'info');
        
        isScanning = true;
        currentIndex = 0;
        successCount = 0;
        errorCount = 0;
        batchStartTime = Date.now();
        
        $('#start-batch').hide();
        $('#stop-batch').show();
        $('#batch-progress').slideDown();
        
        $('#batch-total').text(batchFiles.length);
        
        // Start timer
        batchTimer = setInterval(function() {
            const elapsed = Math.floor((Date.now() - batchStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            $('#batch-time').text(
                (minutes < 10 ? '0' : '') + minutes + ':' + 
                (seconds < 10 ? '0' : '') + seconds
            );
        }, 1000);
        
        // Process next file
        processNextFile();
    });
    
    $('#stop-batch').on('click', function() {
        addDebugLog('Scansione fermata dall\'utente', 'warning');
        stopBatch();
    });
    
    function processNextFile() {
        if (!isScanning || currentIndex >= batchFiles.length) {
            completeBatch();
            return;
        }
        
        const file = batchFiles[currentIndex];
        const fileNum = currentIndex + 1;
        
        $('#batch-current').text(fileNum);
        $('#batch-status').text('Analizzando: ' + file.name);
        
        const progress = (fileNum / batchFiles.length) * 100;
        $('#batch-progress-bar').css('width', progress + '%');
        
        addDebugLog('[' + fileNum + '/' + batchFiles.length + '] Analizzando: ' + file.name);
        
        $.ajax({
            url: disco747ExcelScan.ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_batch_scan_excel',
                nonce: disco747ExcelScan.nonce,
                file_id: file.id,
                file_name: file.name,
                file_path: file.path || '',
                current_index: currentIndex,
                total_files: batchFiles.length
            },
            success: function(response) {
                if (response.success && response.data.ok) {
                    successCount++;
                    $('#batch-success').text(successCount);
                    addDebugLog('✅ File analizzato con successo', 'success');
                    
                    if (response.data.data) {
                        const data = response.data.data;
                        addDebugLog('  Menu: ' + (data.tipo_menu || 'N/A') + 
                                  ', Invitati: ' + (data.numero_invitati || 'N/A') +
                                  ', Importo: €' + (data.importo || '0'), 'info');
                    }
                } else {
                    errorCount++;
                    $('#batch-errors').text(errorCount);
                    const errorMsg = response.data ? response.data.error : 'Errore sconosciuto';
                    addDebugLog('❌ Errore: ' + errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                errorCount++;
                $('#batch-errors').text(errorCount);
                addDebugLog('❌ Errore AJAX: ' + error, 'error');
            },
            complete: function() {
                currentIndex++;
                setTimeout(processNextFile, 500); // Small delay between files
            }
        });
    }
    
    function completeBatch() {
        isScanning = false;
        clearInterval(batchTimer);
        
        $('#batch-status').text('Scansione completata!');
        $('#stop-batch').hide();
        $('#start-batch').show();
        
        addDebugLog('=== SCANSIONE COMPLETATA ===', 'info');
        addDebugLog('Successi: ' + successCount + ', Errori: ' + errorCount, 'info');
        
        // Reload table
        setTimeout(function() {
            $('#refresh-table').click();
        }, 1000);
    }
    
    function stopBatch() {
        isScanning = false;
        clearInterval(batchTimer);
        
        $('#batch-status').text('Scansione interrotta');
        $('#stop-batch').hide();
        $('#start-batch').show();
    }
    
    // Refresh table
    $('#refresh-table').on('click', function() {
        addDebugLog('Aggiornamento tabella...', 'info');
        location.reload();
    });
    
    // Table filters
    let filterTimeout = null;
    
    function filterTable() {
        const searchTerm = $('#search-analysis').val().toLowerCase();
        const menuFilter = $('#filter-menu').val();
        const statoFilter = $('#filter-stato').val();
        
        $('#analysis-tbody tr').each(function() {
            const $row = $(this);
            const text = $row.text().toLowerCase();
            const menu = $row.find('td:eq(3)').text();
            const stato = $row.find('td:eq(7) .badge').text();
            
            let show = true;
            
            if (searchTerm && !text.includes(searchTerm)) {
                show = false;
            }
            
            if (menuFilter && !menu.includes(menuFilter)) {
                show = false;
            }
            
            if (statoFilter && stato !== statoFilter) {
                show = false;
            }
            
            $row.toggle(show);
        });
    }
    
    $('#search-analysis, #filter-menu, #filter-stato').on('input change', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(filterTable, 300);
    });
    
    // Initial log
    addDebugLog('Sistema Excel Scan inizializzato', 'success');
    addDebugLog('File disponibili: ' + batchFiles.length, 'info');
});
</script>