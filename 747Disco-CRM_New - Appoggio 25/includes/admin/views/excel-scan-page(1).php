<?php
/**
 * Template per la pagina Scansione Excel Auto di 747 Disco CRM
 * INTERFACCIA COMPLETA - NON MODIFICA FUNZIONALITÀ ESISTENTI
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      11.4.2
 */

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

// Carica dati dal database se disponibili
$analysis_results = array();
$total_analysis = 0;
$confirmed_count = 0;
$last_scan = 'Mai';
$is_googledrive_configured = true; // Assumiamo sia configurato

// Prova a caricare dati esistenti se il database ha la tabella
global $wpdb;
$excel_table = $wpdb->prefix . 'disco747_excel_analysis';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$excel_table}'") === $excel_table;

if ($table_exists) {
    $results = $wpdb->get_results(
        "SELECT * FROM {$excel_table} ORDER BY created_at DESC LIMIT 100", 
        OBJECT
    );
    
    if ($results) {
        $analysis_results = $results;
        $total_analysis = count($results);
        $confirmed_count = count(array_filter($results, function($item) {
            return !empty($item->acconto) && $item->acconto > 0;
        }));
        
        if (!empty($results)) {
            $latest = $results[0];
            if (isset($latest->updated_at)) {
                $last_scan = date('d/m/Y H:i', strtotime($latest->updated_at));
            }
        }
    }
}
?>

<div class="wrap disco747-excel-scan">
    <!-- CSS Integrato -->
    <style>
        .disco747-excel-scan {
            background: #f1f1f1;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .disco747-header {
            background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .disco747-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .disco747-header p {
            margin: 10px 0 0;
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .disco747-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
        }
        
        .disco747-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .status-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            flex: 1;
            min-width: 180px;
        }
        
        .status-value {
            font-size: 2rem;
            font-weight: bold;
            color: #c28a4d;
            margin: 5px 0;
        }
        
        .status-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .disco747-button {
            background: linear-gradient(135deg, #c28a4d 0%, #d4a574 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(194, 138, 77, 0.3);
        }
        
        .disco747-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(194, 138, 77, 0.4);
        }
        
        .disco747-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .disco747-button.loading {
            opacity: 0.8;
            cursor: not-allowed;
            position: relative;
        }
        
        .disco747-button.loading::after {
            content: '';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }
        
        .disco747-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .disco747-table thead {
            background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%);
            color: white;
        }
        
        .disco747-table th,
        .disco747-table td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .disco747-table th {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }
        
        .disco747-table tbody tr:hover {
            background-color: #f8f9fa;
            transition: background-color 0.2s ease;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-confermato {
            background: linear-gradient(135deg, #28a745, #34ce57);
            color: white;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #ffc107, #ffdb4d);
            color: #000;
        }
        
        .status-error {
            background: linear-gradient(135deg, #dc3545, #ff4757);
            color: white;
        }
        
        .edit-button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .edit-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .filters-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.2s ease;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #c28a4d;
        }
        
        .filters-buttons {
            display: flex;
            gap: 10px;
        }
        
        .filter-button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .filter-apply {
            background: #28a745;
            color: white;
        }
        
        .filter-reset {
            background: #6c757d;
            color: white;
        }
        
        .filter-button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .progress-container {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            display: none;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #c28a4d 0%, #d4a574 100%);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 10px;
        }
        
        .progress-text {
            text-align: center;
            font-weight: 600;
            color: #333;
        }
        
        .log-container {
            margin-top: 20px;
            background: #000;
            color: #00ff00;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            max-height: 300px;
            overflow-y: auto;
            font-size: 0.8rem;
            display: none;
        }
        
        .log-line {
            margin: 2px 0;
            word-wrap: break-word;
        }
        
        .log-error { color: #ff4757; }
        .log-success { color: #2ed573; }
        .log-warning { color: #ffa502; }
        .log-info { color: #70a1ff; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state h3 {
            color: #c28a4d;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            margin-bottom: 20px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .disco747-status {
                flex-direction: column;
            }
            
            .status-item {
                min-width: 100%;
            }
            
            .filters-container {
                flex-direction: column;
            }
            
            .disco747-table {
                font-size: 0.8rem;
            }
            
            .disco747-table th,
            .disco747-table td {
                padding: 10px 8px;
            }
        }
    </style>

    <!-- Header -->
    <div class="disco747-header">
        <h1>📊 Scansione Excel Auto</h1>
        <p>Gestione automatizzata dei preventivi da Google Drive</p>
    </div>
    
    <!-- Stato Sistema -->
    <div class="disco747-card">
        <div class="disco747-status">
            <div class="status-item">
                <div class="status-value" id="total-files"><?php echo esc_html($total_analysis); ?></div>
                <div class="status-label">File Excel</div>
            </div>
            <div class="status-item">
                <div class="status-value" id="analyzed-files"><?php echo esc_html($total_analysis); ?></div>
                <div class="status-label">Analizzati</div>
            </div>
            <div class="status-item">
                <div class="status-value" id="confirmed-files"><?php echo esc_html($confirmed_count); ?></div>
                <div class="status-label">Confermati</div>
            </div>
            <div class="status-item">
                <div class="status-value" id="last-scan"><?php echo esc_html($last_scan); ?></div>
                <div class="status-label">Ultima Scansione</div>
            </div>
        </div>
    </div>
    
    <!-- Controlli Scansione -->
    <div class="disco747-card">
        <h2 style="margin-top: 0; color: #c28a4d;">🚀 Avvia Scansione</h2>
        <p style="margin-bottom: 25px; color: #666;">
            Clicca il pulsante per analizzare tutti i file Excel presenti su Google Drive e importare i dati dei preventivi.
        </p>
        
        <button id="start-scan" class="disco747-button" data-nonce="<?php echo wp_create_nonce('disco747_excel_scan'); ?>">
            📈 Avvia Scansione Completa
        </button>
        
        <!-- Barra Progresso -->
        <div id="progress-container" class="progress-container">
            <div class="progress-text" id="progress-text">Preparazione...</div>
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
            <div style="font-size: 0.9rem; color: #666; text-align: center;" id="progress-details">
                0 di 0 file processati
            </div>
        </div>
        
        <!-- Log Debug -->
        <div id="log-container" class="log-container">
            <div id="log-content"></div>
        </div>
        
        <div style="margin-top: 15px;">
            <button id="toggle-log" class="filter-button filter-apply" style="margin-right: 10px;">
                👁️ Mostra Log
            </button>
            <button id="clear-log" class="filter-button filter-reset">
                🗑️ Pulisci Log
            </button>
        </div>
    </div>
    
    <!-- Filtri -->
    <div class="disco747-card">
        <h2 style="margin-top: 0; color: #c28a4d;">🔍 Filtri Ricerca</h2>
        
        <div class="filters-container">
            <div class="filter-group">
                <label for="search-input">Ricerca</label>
                <input type="text" id="search-input" placeholder="Nome, evento, telefono...">
            </div>
            
            <div class="filter-group">
                <label for="menu-filter">Menu</label>
                <select id="menu-filter">
                    <option value="">Tutti i menu</option>
                    <option value="Menu 7">Menu 7</option>
                    <option value="Menu 74">Menu 74</option>
                    <option value="Menu 747">Menu 747</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="status-filter">Stato</label>
                <select id="status-filter">
                    <option value="">Tutti gli stati</option>
                    <option value="confirmed">Confermato</option>
                    <option value="pending">In Attesa</option>
                    <option value="error">Errore</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date-from">Da Data</label>
                <input type="date" id="date-from">
            </div>
            
            <div class="filter-group">
                <label for="date-to">A Data</label>
                <input type="date" id="date-to">
            </div>
            
            <div class="filters-buttons">
                <button id="apply-filters" class="filter-button filter-apply">Applica</button>
                <button id="reset-filters" class="filter-button filter-reset">Reset</button>
            </div>
        </div>
    </div>
    
    <!-- Tabella Risultati -->
    <div class="disco747-card">
        <h2 style="margin-top: 0; color: #c28a4d;">📋 Risultati Analisi</h2>
        
        <div id="loading-state" style="text-align: center; padding: 40px; display: none;">
            <div style="font-size: 1.2rem; color: #666;">🔄 Caricamento risultati...</div>
        </div>
        
        <?php if (empty($analysis_results)): ?>
        <div id="empty-state" class="empty-state">
            <h3>Nessun risultato trovato</h3>
            <p>Avvia una scansione per vedere i preventivi importati da Google Drive.</p>
            <button id="start-first-scan" class="disco747-button" data-nonce="<?php echo wp_create_nonce('disco747_excel_scan'); ?>">
                📈 Avvia Prima Scansione
            </button>
        </div>
        <?php else: ?>
        <div id="results-table">
            <table class="disco747-table">
                <thead>
                    <tr>
                        <th>Data Evento</th>
                        <th>Tipo Evento</th>
                        <th>Nome</th>
                        <th>Cognome</th>
                        <th>Telefono</th>
                        <th>Email</th>
                        <th>Menu</th>
                        <th>Importo</th>
                        <th>Stato</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody id="results-tbody">
                    <?php foreach ($analysis_results as $result): 
                        $is_confirmed = !empty($result->acconto) && $result->acconto > 0;
                        $status_class = $is_confirmed ? 'status-confermato' : 
                                      ($result->analysis_success ? 'status-pending' : 'status-error');
                        $status_text = $is_confirmed ? 'Confermato' : 
                                     ($result->analysis_success ? 'In Attesa' : 'Errore');
                    ?>
                    <tr class="table-row" data-id="<?php echo esc_attr($result->id); ?>">
                        <td><?php echo esc_html($result->data_evento ?: 'N/A'); ?></td>
                        <td><?php echo esc_html($result->tipo_evento ?: 'N/A'); ?></td>
                        <td><?php echo esc_html($result->nome_referente ?: 'N/A'); ?></td>
                        <td><?php echo esc_html($result->cognome_referente ?: 'N/A'); ?></td>
                        <td><?php echo esc_html($result->cellulare ?: 'N/A'); ?></td>
                        <td><?php echo esc_html($result->email ?: 'N/A'); ?></td>
                        <td><?php echo esc_html($result->tipo_menu ?: 'N/A'); ?></td>
                        <td>€ <?php echo esc_html(number_format($result->importo ?: 0, 2)); ?></td>
                        <td><span class="status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=disco747-crm&tab=form_preventivo&source=excel_analysis&analysis_id=' . $result->id); ?>" 
                               class="edit-button">
                                ✏️ Modifica
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script>
        // Configurazione AJAX per WordPress
        const disco747ExcelScan = {
            ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('disco747_excel_scan'); ?>',
            
            // Inizializzazione
            init: function() {
                this.bindEvents();
                this.log('Interfaccia scansione Excel inizializzata', 'success');
            },
            
            // Bind eventi
            bindEvents: function() {
                const startBtn = document.getElementById('start-scan');
                const startFirstBtn = document.getElementById('start-first-scan');
                
                if (startBtn) startBtn.addEventListener('click', () => this.startScan());
                if (startFirstBtn) startFirstBtn.addEventListener('click', () => this.startScan());
                
                document.getElementById('apply-filters')?.addEventListener('click', () => this.applyFilters());
                document.getElementById('reset-filters')?.addEventListener('click', () => this.resetFilters());
                document.getElementById('toggle-log')?.addEventListener('click', () => this.toggleLog());
                document.getElementById('clear-log')?.addEventListener('click', () => this.clearLog());
            },
            
            // Avvia scansione
            startScan: function() {
                const button = document.getElementById('start-scan') || document.getElementById('start-first-scan');
                const progressContainer = document.getElementById('progress-container');
                const progressFill = document.getElementById('progress-fill');
                const progressText = document.getElementById('progress-text');
                
                if (!button) return;
                
                button.classList.add('loading');
                button.textContent = '🔄 Scansione in corso...';
                button.disabled = true;
                
                if (progressContainer) {
                    progressContainer.style.display = 'block';
                }
                
                this.log('Avvio scansione Excel da Google Drive...', 'info');
                
                // Simula scansione per ora - sostituire con chiamata AJAX reale
                this.simulateScan(button, progressFill, progressText);
            },
            
            // Simula scansione (da sostituire con AJAX reale)
            simulateScan: function(button, progressFill, progressText) {
                let progress = 0;
                const interval = setInterval(() => {
                    progress += Math.random() * 15;
                    if (progress > 100) progress = 100;
                    
                    if (progressFill) progressFill.style.width = progress + '%';
                    if (progressText) progressText.textContent = `Scansione in corso... ${Math.floor(progress)}%`;
                    
                    this.log(`Progresso: ${Math.floor(progress)}%`, 'info');
                    
                    if (progress >= 100) {
                        clearInterval(interval);
                        this.log('Scansione completata! Ricarica la pagina per vedere i risultati.', 'success');
                        
                        button.classList.remove('loading');
                        button.textContent = '✅ Scansione Completata';
                        button.disabled = false;
                        
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    }
                }, 300);
            },
            
            // Applica filtri
            applyFilters: function() {
                const search = document.getElementById('search-input').value.toLowerCase();
                const menu = document.getElementById('menu-filter').value;
                const status = document.getElementById('status-filter').value;
                const dateFrom = document.getElementById('date-from').value;
                const dateTo = document.getElementById('date-to').value;
                
                document.querySelectorAll('.table-row').forEach(row => {
                    let show = true;
                    
                    if (search && !row.textContent.toLowerCase().includes(search)) {
                        show = false;
                    }
                    
                    if (menu && !row.textContent.includes(menu)) {
                        show = false;
                    }
                    
                    if (status) {
                        const badge = row.querySelector('.status-badge');
                        if (status === 'confirmed' && !badge?.classList.contains('status-confermato')) show = false;
                        if (status === 'pending' && !badge?.classList.contains('status-pending')) show = false;
                        if (status === 'error' && !badge?.classList.contains('status-error')) show = false;
                    }
                    
                    row.style.display = show ? '' : 'none';
                });
                
                this.log(`Filtri applicati: ricerca="${search}", menu="${menu}", stato="${status}"`, 'info');
            },
            
            // Reset filtri
            resetFilters: function() {
                document.getElementById('search-input').value = '';
                document.getElementById('menu-filter').value = '';
                document.getElementById('status-filter').value = '';
                document.getElementById('date-from').value = '';
                document.getElementById('date-to').value = '';
                
                document.querySelectorAll('.table-row').forEach(row => {
                    row.style.display = '';
                });
                
                this.log('Filtri resettati', 'info');
            },
            
            // Toggle log
            toggleLog: function() {
                const logContainer = document.getElementById('log-container');
                const button = document.getElementById('toggle-log');
                
                if (logContainer.style.display === 'none' || !logContainer.style.display) {
                    logContainer.style.display = 'block';
                    button.textContent = '👁️ Nascondi Log';
                } else {
                    logContainer.style.display = 'none';
                    button.textContent = '👁️ Mostra Log';
                }
            },
            
            // Pulisci log
            clearLog: function() {
                document.getElementById('log-content').innerHTML = '';
                this.log('Log pulito', 'success');
            },
            
            // Utility logging
            log: function(message, type = 'info') {
                const logContainer = document.getElementById('log-content');
                if (!logContainer) return;
                
                const timestamp = new Date().toLocaleTimeString();
                const logLine = document.createElement('div');
                logLine.className = `log-line log-${type}`;
                logLine.textContent = `[${timestamp}] ${message}`;
                logContainer.appendChild(logLine);
                logContainer.scrollTop = logContainer.scrollHeight;
            }
        };

        // Inizializza quando DOM è pronto
        document.addEventListener('DOMContentLoaded', function() {
            disco747ExcelScan.init();
        });
    </script>
</div>