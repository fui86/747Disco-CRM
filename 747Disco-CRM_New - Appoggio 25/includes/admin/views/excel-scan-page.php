<?php
/**
 * Template pagina scansione Excel da Google Drive
 * VERSIONE CORRETTA - Usa le credenziali del plugin principale
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      11.8.0-FIXED
 */

if (!defined('ABSPATH')) {
    exit;
}

// Inizializza la classe di sincronizzazione
require_once plugin_dir_path(dirname(__FILE__)) . 'storage/class-disco747-googledrive-sync.php';
$gdrive_sync = new Disco747_GoogleDrive_Sync();

// Verifica configurazione Google Drive
$gdrive_configured = $gdrive_sync->is_configured();
$test_result = null;
$excel_files_list = array();
$analysis_results = array();

if ($gdrive_configured) {
    // Test connessione
    $test_result = $gdrive_sync->test_connection();
    
    if ($test_result['success']) {
        // Ottieni lista file Excel
        $excel_files_list = $gdrive_sync->get_all_excel_files();
    }
}

// Gestione azioni AJAX (se necessario)
$action = isset($_REQUEST['excel_action']) ? sanitize_text_field($_REQUEST['excel_action']) : '';

?>

<div class="wrap disco747-excel-scan">
    <h1>📊 Scansione File Excel da Google Drive</h1>

```
<!-- STATO CONNESSIONE -->
<div class="disco747-card" style="margin-bottom: 30px;">
    <div class="disco747-card-header">
        🔌 Stato Connessione Google Drive
    </div>
    <div class="disco747-card-content">
        <?php if (!$gdrive_configured): ?>
            <div class="disco747-notice error">
                <p><strong>❌ Google Drive non configurato</strong></p>
                <p>Per utilizzare questa funzione devi prima configurare Google Drive nelle impostazioni.</p>
                <a href="<?php echo admin_url('admin.php?page=disco747-settings'); ?>" class="button button-primary">
                    Vai alle Impostazioni
                </a>
            </div>
        <?php else: ?>
            <?php if ($test_result && $test_result['success']): ?>
                <div class="disco747-notice success">
                    <p><strong>✅ Google Drive connesso</strong></p>
                    <p>Account: <?php echo esc_html($test_result['user'] ?? 'Sconosciuto'); ?></p>
                </div>
            <?php else: ?>
                <div class="disco747-notice warning">
                    <p><strong>⚠️ Problema di connessione</strong></p>
                    <p><?php echo esc_html($test_result['message'] ?? 'Errore sconosciuto'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=disco747-settings'); ?>" class="button">
                        Verifica Impostazioni
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($gdrive_configured && $test_result && $test_result['success']): ?>
    
    <!-- LISTA FILE EXCEL -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-header">
            📁 File Excel Trovati
            <span class="count"><?php echo count($excel_files_list); ?> file</span>
        </div>
        <div class="disco747-card-content">
            <?php if (empty($excel_files_list)): ?>
                <div class="disco747-notice warning">
                    <p>Nessun file Excel trovato nella cartella 747-Preventivi.</p>
                </div>
            <?php else: ?>
                <div class="file-list-container" style="max-height: 400px; overflow-y: auto;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="35%">Nome File</th>
                                <th width="25%">Percorso</th>
                                <th width="15%">Dimensione</th>
                                <th width="15%">Ultima Modifica</th>
                                <th width="5%">Azioni</th>
                            </tr>
                        </thead>
                        <tbody id="excel-files-tbody">
                            <?php foreach ($excel_files_list as $index => $file): ?>
                                <tr data-file-id="<?php echo esc_attr($file['id']); ?>" 
                                    data-file-name="<?php echo esc_attr($file['name']); ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo esc_html($file['name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo esc_html($file['folder_path'] ?? '/'); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $size = isset($file['size']) ? intval($file['size']) : 0;
                                        echo $size > 0 ? size_format($size) : '-';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (isset($file['modifiedTime'])) {
                                            echo date('d/m/Y H:i', strtotime($file['modifiedTime']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button class="button button-small analyze-single-file" 
                                                data-file-id="<?php echo esc_attr($file['id']); ?>"
                                                data-file-name="<?php echo esc_attr($file['name']); ?>">
                                            🔍
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- AZIONI BATCH -->
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                    <h3>🚀 Azioni Batch</h3>
                    <div class="disco747-notice info">
                        <p><strong>Analisi Batch:</strong> Analizza tutti i file Excel trovati in una sola operazione.</p>
                        <p>⚠️ Questa operazione può richiedere diversi minuti per completarsi.</p>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <button id="analyze-all-files" class="button button-primary button-large">
                            📊 Analizza Tutti i File (<?php echo count($excel_files_list); ?>)
                        </button>
                        
                        <button id="export-analysis" class="button button-secondary button-large" style="display: none;">
                            📥 Esporta Risultati
                        </button>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div id="batch-progress" style="display: none; margin-top: 20px;">
                        <div style="background: #f0f0f0; border-radius: 5px; overflow: hidden;">
                            <div id="progress-bar" style="background: #c28a4d; height: 30px; width: 0%; transition: width 0.3s;">
                                <span id="progress-text" style="display: block; line-height: 30px; color: white; text-align: center;">0%</span>
                            </div>
                        </div>
                        <p id="progress-status" style="margin-top: 10px;">Preparazione...</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- RISULTATI ANALISI -->
    <div id="analysis-results" class="disco747-card" style="display: none;">
        <div class="disco747-card-header">
            📈 Risultati Analisi
        </div>
        <div class="disco747-card-content">
            <div id="results-container">
                <!-- Popolato via JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- LOG DEBUG -->
    <div class="disco747-card" style="margin-top: 30px;">
        <div class="disco747-card-header">
            🔧 Log Debug
            <button id="toggle-log" class="button button-small" style="float: right;">Mostra/Nascondi</button>
        </div>
        <div class="disco747-card-content">
            <div id="debug-log" style="display: none; background: #f5f5f5; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto;">
                <!-- Log popolato via JavaScript -->
            </div>
        </div>
    </div>
    
<?php endif; ?>
```

</div>

<!-- Script JavaScript inline per gestione interattiva -->

<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Toggle log debug
    $('#toggle-log').on('click', function() {
        $('#debug-log').toggle();
    });
    
    // Analisi singolo file
    $('.analyze-single-file').on('click', function() {
        const fileId = $(this).data('file-id');
        const fileName = $(this).data('file-name');
        
        if (!confirm('Analizzare il file ' + fileName + '?')) {
            return;
        }
        
        $(this).prop('disabled', true).text('⏳');
        addLog('Inizio analisi file: ' + fileName);
        
        $.post(ajaxurl, {
            action: 'disco747_analyze_excel_file',
            file_id: fileId,
            file_name: fileName,
            _ajax_nonce: '<?php echo wp_create_nonce('disco747_excel_scan'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                addLog('✅ File analizzato con successo: ' + fileName);
                showSingleResult(response.data);
            } else {
                addLog('❌ Errore analisi: ' + response.data.message);
                alert('Errore: ' + response.data.message);
            }
        })
        .fail(function() {
            addLog('❌ Errore di comunicazione con il server');
            alert('Errore di comunicazione con il server');
        })
        .always(function() {
            $('.analyze-single-file[data-file-id="' + fileId + '"]')
                .prop('disabled', false)
                .text('🔍');
        });
    });
    
    // Analisi batch di tutti i file
    $('#analyze-all-files').on('click', function() {
        if (!confirm('Analizzare tutti i ' + <?php echo count($excel_files_list); ?> + ' file?\n\nQuesta operazione potrebbe richiedere diversi minuti.')) {
            return;
        }
        
        const files = <?php echo json_encode($excel_files_list); ?>;
        const totalFiles = files.length;
        let processedFiles = 0;
        let results = [];
        
        $(this).prop('disabled', true);
        $('#batch-progress').show();
        $('#analysis-results').hide();
        
        addLog('=== INIZIO ANALISI BATCH ===');
        addLog('File totali da analizzare: ' + totalFiles);
        
        // Processa file uno alla volta
        function processNextFile() {
            if (processedFiles >= totalFiles) {
                // Completato
                updateProgress(100, 'Analisi completata!');
                $('#analyze-all-files').prop('disabled', false);
                $('#export-analysis').show();
                showBatchResults(results);
                addLog('=== ANALISI BATCH COMPLETATA ===');
                return;
            }
            
            const currentFile = files[processedFiles];
            const percentComplete = Math.round((processedFiles / totalFiles) * 100);
            
            updateProgress(percentComplete, 'Analisi file ' + (processedFiles + 1) + ' di ' + totalFiles + ': ' + currentFile.name);
            addLog('Analisi file [' + (processedFiles + 1) + '/' + totalFiles + ']: ' + currentFile.name);
            
            $.post(ajaxurl, {
                action: 'disco747_analyze_excel_file',
                file_id: currentFile.id,
                file_name: currentFile.name,
                _ajax_nonce: '<?php echo wp_create_nonce('disco747_excel_scan'); ?>'
            })
            .done(function(response) {
                if (response.success) {
                    results.push({
                        file: currentFile.name,
                        path: currentFile.folder_path || '/',
                        data: response.data.data,
                        success: true
                    });
                    addLog('✅ OK: ' + currentFile.name);
                } else {
                    results.push({
                        file: currentFile.name,
                        path: currentFile.folder_path || '/',
                        error: response.data.message,
                        success: false
                    });
                    addLog('⚠️ Errore: ' + currentFile.name + ' - ' + response.data.message);
                }
            })
            .fail(function() {
                results.push({
                    file: currentFile.name,
                    path: currentFile.folder_path || '/',
                    error: 'Errore di comunicazione',
                    success: false
                });
                addLog('❌ Errore comunicazione: ' + currentFile.name);
            })
            .always(function() {
                processedFiles++;
                setTimeout(processNextFile, 500); // Pausa 500ms tra file
            });
        }
        
        // Avvia elaborazione
        processNextFile();
    });
    
    // Esporta risultati
    $('#export-analysis').on('click', function() {
        const resultsHtml = $('#results-container').html();
        if (!resultsHtml) {
            alert('Nessun risultato da esportare');
            return;
        }
        
        // Crea CSV dai risultati
        let csv = 'Nome File,Percorso,Nome Cliente,Telefono,Email,Data Evento,Tipo Evento,Numero Invitati,Importo Totale,Acconto,Stato\n';
        
        $('.result-item').each(function() {
            const data = $(this).data('result');
            if (data && data.success && data.data) {
                csv += '"' + data.file + '",';
                csv += '"' + data.path + '",';
                csv += '"' + (data.data.nome_cliente || '') + '",';
                csv += '"' + (data.data.telefono || '') + '",';
                csv += '"' + (data.data.email || '') + '",';
                csv += '"' + (data.data.data_evento || '') + '",';
                csv += '"' + (data.data.tipo_evento || '') + '",';
                csv += '"' + (data.data.numero_invitati || '0') + '",';
                csv += '"' + (data.data.importo_totale || '0') + '",';
                csv += '"' + (data.data.acconto_versato || '0') + '",';
                csv += '"OK"\n';
            } else {
                csv += '"' + data.file + '",';
                csv += '"' + data.path + '",';
                csv += '"-","-","-","-","-","-","-","-",';
                csv += '"ERRORE: ' + (data.error || 'Sconosciuto') + '"\n';
            }
        });
        
        // Download CSV
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'analisi_preventivi_' + new Date().toISOString().slice(0,10) + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        addLog('📥 Export CSV completato');
    });
    
    // Funzioni helper
    function updateProgress(percent, status) {
        $('#progress-bar').css('width', percent + '%');
        $('#progress-text').text(percent + '%');
        $('#progress-status').text(status);
    }
    
    function addLog(message) {
        const timestamp = new Date().toLocaleTimeString();
        $('#debug-log').append('[' + timestamp + '] ' + message + '\n').scrollTop($('#debug-log')[0].scrollHeight);
    }
    
    function showSingleResult(data) {
        $('#analysis-results').show();
        let html = '<div class="result-item" style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px;">';
        html += '<h4>' + data.file_name + '</h4>';
        
        if (data.data) {
            html += '<table class="widefat">';
            for (let key in data.data) {
                if (data.data[key]) {
                    html += '<tr><td><strong>' + key.replace(/_/g, ' ').toUpperCase() + ':</strong></td>';
                    html += '<td>' + data.data[key] + '</td></tr>';
                }
            }
            html += '</table>';
        } else {
            html += '<p style="color: red;">Errore: ' + (data.errors ? data.errors.join(', ') : 'Sconosciuto') + '</p>';
        }
        
        html += '</div>';
        $('#results-container').html(html);
    }
    
    function showBatchResults(results) {
        $('#analysis-results').show();
        
        let successCount = results.filter(r => r.success).length;
        let errorCount = results.filter(r => !r.success).length;
        
        let html = '<div class="summary" style="background: #e8f4f8; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        html += '<h3>Riepilogo Analisi</h3>';
        html += '<p>✅ File analizzati con successo: <strong>' + successCount + '</strong></p>';
        html += '<p>❌ File con errori: <strong>' + errorCount + '</strong></p>';
        html += '<p>📊 Totale file processati: <strong>' + results.length + '</strong></p>';
        html += '</div>';
        
        html += '<div class="results-list">';
        results.forEach(function(result, index) {
            html += '<div class="result-item" data-result=\'' + JSON.stringify(result) + '\' style="background: ' + (result.success ? '#f0f8f0' : '#fff0f0') + '; padding: 10px; border-left: 3px solid ' + (result.success ? '#28a745' : '#dc3545') + '; margin-bottom: 10px;">';
            html += '<strong>#' + (index + 1) + ' - ' + result.file + '</strong> (' + result.path + ')<br>';
            
            if (result.success && result.data) {
                html += 'Cliente: ' + (result.data.nome_cliente || 'N/D') + ' | ';
                html += 'Data: ' + (result.data.data_evento || 'N/D') + ' | ';
                html += 'Importo: €' + (result.data.importo_totale || '0');
            } else {
                html += '<span style="color: red;">Errore: ' + (result.error || 'Sconosciuto') + '</span>';
            }
            
            html += '</div>';
        });
        html += '</div>';
        
        $('#results-container').html(html);
    }
});
</script>

<style>
.disco747-excel-scan {
    max-width: 1200px;
}

.disco747-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.disco747-card-header {
    background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%);
    color: white;
    padding: 15px 20px;
    font-size: 18px;
    font-weight: bold;
    border-radius: 7px 7px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.disco747-card-header .count {
    background: rgba(255,255,255,0.2);
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 14px;
}

.disco747-card-content {
    padding: 20px;
}

.disco747-notice {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.disco747-notice.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.disco747-notice.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.disco747-notice.warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.disco747-notice.info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}
</style>