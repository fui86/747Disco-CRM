<?php
/**
 * Template per la pagina Scansione Excel Auto di 747 Disco CRM
 * Analisi batch file Excel da Google Drive con tabella e azioni
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      11.4.2
 * @version    11.4.2
 * @author     747 Disco Team
 */

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

// Ottieni istanza database per statistiche
$database = null;
$stats = array(
    'total_files' => 0,
    'analyzed_success' => 0,
    'analysis_errors' => 0,
    'confirmed_count' => 0,
    'last_scan' => 'Mai'
);

try {
    $disco747_crm = disco747_crm();
    if ($disco747_crm && $disco747_crm->is_initialized()) {
        $database = $disco747_crm->get_database();
        
        // Carica statistiche dal database se disponibile
        if ($database && method_exists($database, 'count_excel_analysis')) {
            $stats['total_files'] = $database->count_excel_analysis();
            $stats['analyzed_success'] = $database->count_excel_analysis(array('analysis_success' => 1));
            $stats['analysis_errors'] = $database->count_excel_analysis(array('analysis_success' => 0));
            
            // Conta confermati (con acconto > 0)
            global $wpdb;
            $excel_table = $wpdb->prefix . 'disco747_excel_analysis';
            $stats['confirmed_count'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$excel_table} WHERE acconto > 0"
            ));
            
            // Ultima scansione
            $last_record = $wpdb->get_var(
                "SELECT MAX(created_at) FROM {$excel_table}"
            );
            if ($last_record) {
                $stats['last_scan'] = date('d/m/Y H:i', strtotime($last_record));
            }
        }
    }
} catch (Exception $e) {
    error_log('[747Disco-ExcelPage] Errore caricamento stats: ' . $e->getMessage());
}

// Configurazione storage
$storage_type = get_option('disco747_storage_type', 'googledrive');
$storage_configured = false;

if ($storage_type === 'googledrive') {
    // Prova prima il nuovo sistema unificato
    $gd_credentials = get_option('disco747_gd_credentials', array());
    if (!empty($gd_credentials['refresh_token'])) {
        $storage_configured = true;
    } else {
        // Fallback al sistema separato
        $client_id = get_option('disco747_googledrive_client_id');
        $client_secret = get_option('disco747_googledrive_client_secret');
        $refresh_token = get_option('disco747_googledrive_refresh_token');
        $storage_configured = !empty($client_id) && !empty($client_secret) && !empty($refresh_token);
    }
} else {
    $dropbox_credentials = get_option('disco747_dropbox_credentials', array());
    $storage_configured = !empty($dropbox_credentials['refresh_token']);
}

?>

<div class="wrap disco747-excel-scan-page" style="background: #f8f9fa; margin: 0 -20px; padding: 20px;">

```
<!-- Header 747 Disco Style -->
<div style="background: linear-gradient(135deg, #2b1e1a 0%, #3c3c3c 100%); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.3);">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
        <div>
            <h1 style="margin: 0; font-size: 2.2rem; color: #c28a4d; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                📊 Excel Auto Scan
            </h1>
            <p style="margin: 10px 0 0 0; color: #eeeae6; font-size: 1.1rem;">
                Analisi automatica file Excel da <?php echo $storage_type === 'googledrive' ? 'Google Drive' : 'Dropbox'; ?>
            </p>
        </div>
        <div style="text-align: right;">
            <?php if ($storage_configured): ?>
                <button id="disco747-start-batch-scan" class="button button-primary" style="background: #c28a4d; border: none; padding: 12px 24px; font-size: 16px; border-radius: 8px; box-shadow: 0 4px 15px rgba(194, 138, 77, 0.4);">
                    🔄 Analizza Ora
                </button>
            <?php else: ?>
                <div style="background: rgba(220, 53, 69, 0.2); padding: 15px; border-radius: 10px; border: 2px solid #dc3545;">
                    <div style="color: #dc3545; font-weight: bold;">⚠️ Storage non configurato</div>
                    <div style="font-size: 0.9rem; color: #eeeae6; margin-top: 5px;">
                        <a href="<?php echo admin_url('admin.php?page=disco747-settings'); ?>" style="color: #c28a4d;">Configura ora</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Statistiche in Evidenza -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    
    <div style="background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(194, 138, 77, 0.3);">
        <div style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;" id="stats-total"><?php echo number_format($stats['total_files']); ?></div>
        <div style="font-size: 1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">📄 File Trovati</div>
    </div>
    
    <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);">
        <div style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;" id="stats-success"><?php echo number_format($stats['analyzed_success']); ?></div>
        <div style="font-size: 1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">✅ Analizzati</div>
    </div>
    
    <div style="background: linear-gradient(135deg, #17a2b8 0%, #6610f2 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(23, 162, 184, 0.3);">
        <div style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;" id="stats-confirmed"><?php echo number_format($stats['confirmed_count']); ?></div>
        <div style="font-size: 1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">🎉 Confermati</div>
    </div>
    
    <div style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(220, 53, 69, 0.3);">
        <div style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;" id="stats-errors"><?php echo number_format($stats['analysis_errors']); ?></div>
        <div style="font-size: 1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">❌ Errori</div>
    </div>
</div>

<!-- Progress Bar (nascosta inizialmente) -->
<div id="disco747-scan-progress" style="display: none; background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
        <span style="font-weight: bold; color: #2b1e1a;">🔄 Scansione in corso...</span>
        <span id="scan-progress-text" style="color: #c28a4d;">0%</span>
    </div>
    <div style="background: #e9ecef; border-radius: 10px; height: 8px; overflow: hidden;">
        <div id="scan-progress-bar" style="background: linear-gradient(90deg, #c28a4d 0%, #b8b1b3 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
    </div>
    <div id="scan-status-message" style="margin-top: 10px; font-size: 0.9rem; color: #666;"></div>
</div>

<!-- Sezione Tabella Principale -->
<div style="background: white; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); overflow: hidden;">
    
    <!-- Header Tabella -->
    <div style="background: linear-gradient(135deg, #2b1e1a 0%, #3c3c3c 100%); padding: 20px; border-bottom: 3px solid #c28a4d;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
            <h2 style="margin: 0; color: #c28a4d; font-size: 1.5rem;">
                📋 File Excel Analizzati
            </h2>
            <div style="display: flex; gap: 10px; align-items: center;">
                <!-- Filtri -->
                <select id="filter-menu" style="padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    <option value="">Tutti i Menu</option>
                    <option value="7">Menu 7</option>
                    <option value="74">Menu 74</option>
                    <option value="747">Menu 747</option>
                </select>
                
                <input type="text" id="search-excel" placeholder="Cerca nome, evento..." style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; width: 200px;">
                
                <button id="refresh-table" class="button button-secondary" style="padding: 8px 16px;">
                    🔄 Aggiorna
                </button>
            </div>
        </div>
    </div>

    <!-- Contenuto Tabella -->
    <div style="padding: 20px;">
        <div id="excel-table-container">
            <!-- Tabella verrà caricata via AJAX -->
            <div style="text-align: center; padding: 40px; color: #666;">
                <div style="font-size: 3rem; margin-bottom: 20px;">📊</div>
                <p>Clicca "Analizza Ora" per iniziare la scansione dei file Excel.</p>
                <p style="font-size: 0.9rem; color: #999;">I dati verranno caricati automaticamente dopo la prima scansione.</p>
            </div>
        </div>
        
        <!-- Paginazione -->
        <div id="excel-pagination" style="margin-top: 20px; text-align: center; display: none;">
            <!-- Paginazione verrà inserita via AJAX -->
        </div>
    </div>
</div>

<!-- Sezione Debug (collassabile) -->
<div style="background: white; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); margin-top: 20px; overflow: hidden;">
    <div style="background: #f8f9fa; padding: 15px; border-bottom: 1px solid #e9ecef; cursor: pointer;" onclick="toggleDebugSection()">
        <div style="display: flex; align-items: center; justify-content: between;">
            <h3 style="margin: 0; color: #2b1e1a; font-size: 1.2rem;">
                🔧 Debug & Informazioni Sistema
            </h3>
            <span id="debug-toggle" style="float: right; color: #c28a4d; font-weight: bold;">▼</span>
        </div>
    </div>
    
    <div id="debug-content" style="padding: 20px; display: none;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            
            <!-- Info Ultima Scansione -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; border-left: 5px solid #c28a4d;">
                <h4 style="margin: 0 0 10px 0; color: #2b1e1a;">📅 Ultima Scansione</h4>
                <div id="last-scan-info">
                    <p style="margin: 5px 0;"><strong>Data:</strong> <?php echo esc_html($stats['last_scan']); ?></p>
                    <p style="margin: 5px 0;"><strong>File processati:</strong> <span id="debug-last-files">-</span></p>
                    <p style="margin: 5px 0;"><strong>Tempo impiegato:</strong> <span id="debug-last-time">-</span></p>
                </div>
            </div>
            
            <!-- Info Storage -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; border-left: 5px solid #17a2b8;">
                <h4 style="margin: 0 0 10px 0; color: #2b1e1a;">☁️ Storage</h4>
                <p style="margin: 5px 0;"><strong>Tipo:</strong> <?php echo ucfirst($storage_type); ?></p>
                <p style="margin: 5px 0;"><strong>Stato:</strong> 
                    <span style="color: <?php echo $storage_configured ? '#28a745' : '#dc3545'; ?>; font-weight: bold;">
                        <?php echo $storage_configured ? '✅ Configurato' : '❌ Non configurato'; ?>
                    </span>
                </p>
                <p style="margin: 5px 0;"><strong>Cartella:</strong> /747-Preventivi/</p>
            </div>
            
            <!-- Info Database -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; border-left: 5px solid #28a745;">
                <h4 style="margin: 0 0 10px 0; color: #2b1e1a;">🗄️ Database</h4>
                <p style="margin: 5px 0;"><strong>Tabella:</strong> wp_disco747_excel_analysis</p>
                <p style="margin: 5px 0;"><strong>Record:</strong> <span id="debug-db-records"><?php echo number_format($stats['total_files']); ?></span></p>
                <p style="margin: 5px 0;"><strong>Stato:</strong> 
                    <span style="color: #28a745; font-weight: bold;">✅ Attivo</span>
                </p>
            </div>
        </div>
        
        <!-- Log Attività Recente -->
        <div style="margin-top: 20px; background: #f8f9fa; padding: 15px; border-radius: 10px;">
            <h4 style="margin: 0 0 15px 0; color: #2b1e1a;">📝 Log Attività Recente</h4>
            <div id="activity-log" style="background: #2b1e1a; color: #00ff00; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto;">
                <div>[<?php echo date('Y-m-d H:i:s'); ?>] Excel Scan Page caricata</div>
                <div>[<?php echo date('Y-m-d H:i:s'); ?>] Statistiche aggiornate: <?php echo $stats['total_files']; ?> file trovati</div>
                <div style="color: #c28a4d;">[Sistema] Pronto per nuova scansione</div>
            </div>
        </div>
    </div>
</div>
```

</div>

<!-- Modal per Errori Dettagliati (nascosto inizialmente) -->

<div id="error-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; max-width: 600px; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #2b1e1a;">🚨 Dettagli Errore</h3>
            <button onclick="closeErrorModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
        </div>
        <div id="error-details-content" style="background: #f8f9fa; padding: 15px; border-radius: 10px; font-family: monospace; max-height: 300px; overflow-y: auto;">
            <!-- Contenuto errore verrà inserito via JS -->
        </div>
        <div style="margin-top: 20px; text-align: right;">
            <button onclick="closeErrorModal()" class="button button-primary">Chiudi</button>
        </div>
    </div>
</div>

<script>
// Variabili globali
let isScanning = false;
let currentPage = 1;
let totalPages = 1;

// Toggle sezione debug
function toggleDebugSection() {
    const content = document.getElementById('debug-content');
    const toggle = document.getElementById('debug-toggle');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        toggle.textContent = '▲';
    } else {
        content.style.display = 'none';
        toggle.textContent = '▼';
    }
}

// Chiudi modal errore
function closeErrorModal() {
    document.getElementById('error-details-modal').style.display = 'none';
}

// Mostra errori dettagliati
function showErrorDetails(errors) {
    const modal = document.getElementById('error-details-modal');
    const content = document.getElementById('error-details-content');
    
    if (typeof errors === 'string') {
        content.innerHTML = '<div style="color: #dc3545;">' + errors + '</div>';
    } else {
        content.innerHTML = '<pre style="white-space: pre-wrap; color: #dc3545;">' + JSON.stringify(errors, null, 2) + '</pre>';

```
}

modal.style.display = 'block';
```

}

// Aggiungi log attività
function addActivityLog(message, type = ‘info’) {
const log = document.getElementById(‘activity-log’);
const timestamp = new Date().toLocaleString(‘it-IT’);
const color = type === ‘error’ ? ‘#ff6b6b’ : type === ‘success’ ? ‘#51cf66’ : ‘#00ff00’;

```
const logEntry = document.createElement('div');
logEntry.style.color = color;
logEntry.innerHTML = `[${timestamp}] ${message}`;

log.appendChild(logEntry);
log.scrollTop = log.scrollHeight;
```

}

// Aggiorna statistiche
function updateStats(data) {
if (data.stats) {
document.getElementById(‘stats-total’).textContent = Number(data.stats.total_files || 0).toLocaleString();
document.getElementById(‘stats-success’).textContent = Number(data.stats.analyzed_success || 0).toLocaleString();
document.getElementById(‘stats-confirmed’).textContent = Number(data.stats.confirmed_count || 0).toLocaleString();
document.getElementById(‘stats-errors’).textContent = Number(data.stats.analysis_errors || 0).toLocaleString();

```
    // Aggiorna debug
    document.getElementById('debug-db-records').textContent = Number(data.stats.total_files || 0).toLocaleString();
}
```

}

// Inizializzazione quando il DOM è pronto
document.addEventListener(‘DOMContentLoaded’, function() {
// Carica tabella iniziale
loadExcelTable();

```
// Event listeners
setupEventListeners();

addActivityLog('Interfaccia Excel Scan inizializzata', 'success');
```

});

// Setup event listeners
function setupEventListeners() {
// Pulsante scansione principale
const scanButton = document.getElementById(‘disco747-start-batch-scan’);
if (scanButton) {
scanButton.addEventListener(‘click’, startBatchScan);
}

```
// Filtri e ricerca
document.getElementById('filter-menu')?.addEventListener('change', loadExcelTable);
document.getElementById('search-excel')?.addEventListener('input', debounce(loadExcelTable, 500));
document.getElementById('refresh-table')?.addEventListener('click', loadExcelTable);
```

}

// Debounce function per la ricerca
function debounce(func, wait) {
let timeout;
return function executedFunction(…args) {
const later = () => {
clearTimeout(timeout);
func(…args);
};
clearTimeout(timeout);
timeout = setTimeout(later, wait);
};
}

// Placeholder per le funzioni AJAX - verranno implementate nel file JS separato
function startBatchScan() {
console.log(‘Batch scan avviato - implementazione nel file excel-scan.js’);
addActivityLog(‘Scansione batch richiesta - caricamento excel-scan.js…’, ‘info’);
}

function loadExcelTable() {
console.log(‘Caricamento tabella richiesto - implementazione nel file excel-scan.js’);
}
</script>

<style>
/* Stili CSS specifici per la pagina Excel Scan */
.disco747-excel-scan-page .button:hover {
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

.disco747-excel-scan-page table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.disco747-excel-scan-page table th,
.disco747-excel-scan-page table td {
    padding: 12px 8px;
    border-bottom: 1px solid #e9ecef;
    text-align: left;
}

.disco747-excel-scan-page table th {
    background: #f8f9fa;
    font-weight: bold;
    color: #2b1e1a;
    border-bottom: 2px solid #c28a4d;
}

.disco747-excel-scan-page table tr:hover {
    background: #f8f9fa;
}

.disco747-excel-scan-page .status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.disco747-excel-scan-page .status-conf {
    background: #d4edda;
    color: #155724;
}

.disco747-excel-scan-page .status-no {
    background: #f8d7da;
    color: #721c24;
}

.disco747-excel-scan-page .status-pending {
    background: #fff3cd;
    color: #856404;
}

.disco747-excel-scan-page .btn-modifica {
    background: #c28a4d;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    text-decoration: none;
}

.disco747-excel-scan-page .btn-modifica:hover {
    background: #a67b42;
    color: white;
}

@media (max-width: 768px) {
    .disco747-excel-scan-page table {
        font-size: 12px;
    }
    
    .disco747-excel-scan-page table th,
    .disco747-excel-scan-page table td {
        padding: 8px 4px;
    }
}
</style>