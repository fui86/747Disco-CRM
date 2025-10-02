<?php
/**
 * Pagina Scansione Excel da Google Drive
 * VERSIONE 12.0.0-PROGRESS-TABLE - Con barra avanzamento e tabella dati
 * 
 * @package Disco747_CRM
 * @since 12.0.0
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// Ottieni istanza database per statistiche
$disco747_crm = disco747_crm();
$database = $disco747_crm ? $disco747_crm->get_database() : null;

// Statistiche rapide
$stats = array(
    'total' => 0,
    'confermati' => 0,
    'attivi' => 0,
    'annullati' => 0
);

if ($database) {
    $stats = $database->get_stats();
}
?>

<div class="wrap disco747-scan-excel-page">
    <h1>
        <span class="dashicons dashicons-cloud" style="font-size: 32px; width: 32px; height: 32px;"></span>
        Sincronizzazione Google Drive
    </h1>

    <div class="disco747-scan-container">
        
        <!-- Statistiche rapide -->
        <div class="disco747-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
            <div class="disco747-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Totale Preventivi</div>
                <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo $stats['total']; ?></div>
            </div>
            <div class="disco747-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Confermati</div>
                <div style="font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo $stats['confermati']; ?></div>
            </div>
            <div class="disco747-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Attivi</div>
                <div style="font-size: 32px; font-weight: bold; color: #f0b849;"><?php echo $stats['attivi']; ?></div>
            </div>
            <div class="disco747-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Annullati</div>
                <div style="font-size: 32px; font-weight: bold; color: #d63638;"><?php echo $stats['annullati']; ?></div>
            </div>
        </div>

        <!-- Card principale scansione -->
        <div class="disco747-scan-card" style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            
            <div class="disco747-scan-header" style="margin-bottom: 20px;">
                <h2 style="margin: 0 0 10px 0;">Analisi File Excel da Google Drive</h2>
                <p style="color: #666; margin: 0;">Sincronizza automaticamente tutti i preventivi presenti su Google Drive</p>
            </div>

            <!-- Pulsante Analisi -->
            <div class="disco747-scan-actions" style="margin-bottom: 20px;">
                <button 
                    id="disco747-start-scan" 
                    class="button button-primary button-hero"
                    style="padding: 15px 40px; font-size: 16px; height: auto;">
                    <span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
                    Avvia Sincronizzazione
                </button>
            </div>

            <!-- Barra di avanzamento -->
            <div id="disco747-progress-container" style="display: none; margin-top: 20px;">
                <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span id="disco747-progress-text" style="font-weight: 600; color: #2271b1;">Preparazione...</span>
                    <span id="disco747-progress-percentage" style="font-weight: 600; color: #2271b1;">0%</span>
                </div>
                <div style="background: #f0f0f1; border-radius: 4px; height: 30px; overflow: hidden; position: relative;">
                    <div id="disco747-progress-bar" style="background: linear-gradient(90deg, #2271b1 0%, #135e96 100%); height: 100%; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                        <span id="disco747-progress-count">0/0</span>
                    </div>
                </div>
                <div id="disco747-current-file" style="margin-top: 10px; font-size: 13px; color: #666; font-style: italic;"></div>
            </div>

            <!-- Risultati -->
            <div id="disco747-scan-results" style="display: none; margin-top: 20px;"></div>

        </div>

        <!-- Tabella preventivi sincronizzati -->
        <div class="disco747-table-card" style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Preventivi Sincronizzati</h2>
                <button id="disco747-refresh-table" class="button">
                    <span class="dashicons dashicons-update-alt"></span>
                    Aggiorna
                </button>
            </div>

            <!-- Filtri -->
            <div class="disco747-filters" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Cerca</label>
                    <input type="text" id="filter-search" class="regular-text" placeholder="Nome cliente, email, telefono...">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Stato</label>
                    <select id="filter-stato" class="regular-text">
                        <option value="">Tutti</option>
                        <option value="attivo">Attivo</option>
                        <option value="confermato">Confermato</option>
                        <option value="annullato">Annullato</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Menu</label>
                    <select id="filter-menu" class="regular-text">
                        <option value="">Tutti</option>
                        <option value="Menu 7">Menu 7</option>
                        <option value="Menu 74">Menu 74</option>
                        <option value="Menu 747">Menu 747</option>
                        <option value="Menu 7-4">Menu 7-4</option>
                        <option value="Menu 7-4-7">Menu 7-4-7</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Anno</label>
                    <select id="filter-anno" class="regular-text">
                        <option value="">Tutti</option>
                        <option value="2025">2025</option>
                        <option value="2024">2024</option>
                        <option value="2023">2023</option>
                    </select>
                </div>
            </div>

            <!-- Tabella -->
            <div id="disco747-table-container" style="overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped" style="min-width: 1200px;">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th style="width: 100px;">Data Evento</th>
                            <th>Cliente</th>
                            <th>Tipo Evento</th>
                            <th style="width: 100px;">Menu</th>
                            <th style="width: 80px;">Invitati</th>
                            <th style="width: 100px;">Importo</th>
                            <th style="width: 100px;">Acconto</th>
                            <th style="width: 100px;">Stato</th>
                            <th style="width: 120px;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="disco747-table-body">
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px; color: #666;">
                                <span class="dashicons dashicons-update" style="font-size: 48px; opacity: 0.3;"></span>
                                <p>Caricamento dati in corso...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginazione -->
            <div id="disco747-pagination" style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div id="disco747-showing-text" style="color: #666; font-size: 13px;"></div>
                <div id="disco747-pagination-buttons"></div>
            </div>

        </div>

    </div>
</div>

<style>
.disco747-scan-excel-page {
    margin-top: 20px;
}

.disco747-stat-card:hover {
    transform: translateY(-2px);
    transition: transform 0.2s ease;
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
}

.button-hero {
    transition: all 0.2s ease;
}

.button-hero:hover {
    transform: scale(1.02);
}

#disco747-progress-bar {
    position: relative;
}

.disco747-status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-attivo {
    background: #fef7e0;
    color: #976b00;
}

.status-confermato {
    background: #d5f4e6;
    color: #007017;
}

.status-annullato {
    background: #fde7e9;
    color: #a00;
}

.disco747-filters input,
.disco747-filters select {
    width: 100%;
}
</style>

<script>
jQuery(document).ready(function($) {
    
    let currentPage = 1;
    let totalItems = 0;
    let itemsPerPage = 20;
    let allData = [];
    
    // Carica dati iniziali
    loadTableData();
    
    // Pulsante avvia scan
    $('#disco747-start-scan').on('click', function() {
        startBatchScan();
    });
    
    // Refresh tabella
    $('#disco747-refresh-table').on('click', function() {
        loadTableData();
    });
    
    // Filtri
    $('#filter-search, #filter-stato, #filter-menu, #filter-anno').on('change keyup', function() {
        filterTableData();
    });
    
    /**
     * Avvia batch scan con progress bar
     */
    function startBatchScan() {
        const $button = $('#disco747-start-scan');
        const $progressContainer = $('#disco747-progress-container');
        const $results = $('#disco747-scan-results');
        
        // Disabilita pulsante
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear;"></span> Sincronizzazione in corso...');
        
        // Mostra progress
        $progressContainer.show();
        $results.hide().html('');
        
        updateProgress(0, 'Connessione a Google Drive...', 0, 0);
        
        // Chiamata AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_scan_drive_batch',
                nonce: '<?php echo wp_create_nonce("disco747_scan_drive"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Simula progress (in produzione dovrebbe essere real-time via WebSocket o polling)
                    simulateProgress(data.found, function() {
                        showResults(data);
                        $button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Avvia Sincronizzazione');
                        
                        // Ricarica tabella
                        setTimeout(function() {
                            loadTableData();
                        }, 1000);
                    });
                } else {
                    showError('Errore: ' + (response.data || 'Errore sconosciuto'));
                    $button.prop('disabled', false').html('<span class="dashicons dashicons-update"></span> Avvia Sincronizzazione');
                }
            },
            error: function() {
                showError('Errore di connessione al server');
                $button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Avvia Sincronizzazione');
            }
        });
    }
    
    /**
     * Simula progress bar (in produzione sostituire con polling real-time)
     */
    function simulateProgress(total, callback) {
        let current = 0;
        const interval = setInterval(function() {
            current += Math.floor(Math.random() * 3) + 1;
            if (current > total) current = total;
            
            const percentage = Math.floor((current / total) * 100);
            updateProgress(percentage, `Analisi file ${current}/${total}...`, current, total);
            
            if (current >= total) {
                clearInterval(interval);
                setTimeout(callback, 500);
            }
        }, 300);
    }
    
    /**
     * Aggiorna progress bar
     */
    function updateProgress(percentage, text, current, total) {
        $('#disco747-progress-bar').css('width', percentage + '%');
        $('#disco747-progress-percentage').text(percentage + '%');
        $('#disco747-progress-text').text(text);
        $('#disco747-progress-count').text(current + '/' + total);
    }
    
    /**
     * Mostra risultati
     */
    function showResults(data) {
        let html = '<div style="background: #d5f4e6; border-left: 4px solid #00a32a; padding: 15px; border-radius: 4px;">';
        html += '<h3 style="margin: 0 0 10px 0; color: #007017;">Sincronizzazione completata!</h3>';
        html += '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">';
        html += '<div><strong>File trovati:</strong> ' + data.found + '</div>';
        html += '<div><strong>Processati:</strong> ' + data.processed + '</div>';
        html += '<div><strong>Nuovi:</strong> ' + data.inserted + '</div>';
        html += '<div><strong>Aggiornati:</strong> ' + data.updated + '</div>';
        html += '</div>';
        
        if (data.errors > 0) {
            html += '<div style="margin-top: 10px; color: #d63638;"><strong>Errori:</strong> ' + data.errors + '</div>';
        }
        
        html += '</div>';
        
        $('#disco747-scan-results').html(html).show();
    }
    
    /**
     * Mostra errore
     */
    function showError(message) {
        let html = '<div style="background: #fde7e9; border-left: 4px solid #d63638; padding: 15px; border-radius: 4px; color: #a00;">';
        html += '<strong>Errore:</strong> ' + message;
        html += '</div>';
        $('#disco747-scan-results').html(html).show();
        $('#disco747-progress-container').hide();
    }
    
    /**
     * Carica dati tabella
     */
    function loadTableData() {
        $('#disco747-table-body').html('<tr><td colspan="10" style="text-align: center; padding: 40px;"><span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear; font-size: 32px;"></span><p>Caricamento...</p></td></tr>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_get_preventivi_table',
                nonce: '<?php echo wp_create_nonce("disco747_table"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    allData = response.data.preventivi;
                    totalItems = allData.length;
                    filterTableData();
                } else {
                    $('#disco747-table-body').html('<tr><td colspan="10" style="text-align: center; padding: 40px; color: #d63638;">Errore caricamento dati</td></tr>');
                }
            },
            error: function() {
                $('#disco747-table-body').html('<tr><td colspan="10" style="text-align: center; padding: 40px; color: #d63638;">Errore di connessione</td></tr>');
            }
        });
    }
    
    /**
     * Filtra dati tabella
     */
    function filterTableData() {
        const search = $('#filter-search').val().toLowerCase();
        const stato = $('#filter-stato').val();
        const menu = $('#filter-menu').val();
        const anno = $('#filter-anno').val();
        
        let filtered = allData.filter(function(item) {
            // Filtro ricerca
            if (search && !item.nome_cliente.toLowerCase().includes(search) && 
                !item.email.toLowerCase().includes(search) && 
                !item.telefono.includes(search)) {
                return false;
            }
            
            // Filtro stato
            if (stato && item.stato !== stato) {
                return false;
            }
            
            // Filtro menu
            if (menu && item.tipo_menu !== menu) {
                return false;
            }
            
            // Filtro anno
            if (anno && !item.data_evento.startsWith(anno)) {
                return false;
            }
            
            return true;
        });
        
        renderTable(filtered);
    }
    
    /**
     * Renderizza tabella
     */
    function renderTable(data) {
        if (data.length === 0) {
            $('#disco747-table-body').html('<tr><td colspan="10" style="text-align: center; padding: 40px; color: #666;">Nessun preventivo trovato</td></tr>');
            $('#disco747-showing-text').text('');
            $('#disco747-pagination-buttons').html('');
            return;
        }
        
        // Paginazione
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageData = data.slice(start, end);
        const totalPages = Math.ceil(data.length / itemsPerPage);
        
        // Renderizza righe
        let html = '';
        pageData.forEach(function(item) {
            const statusClass = 'status-' + item.stato;
            const statusLabel = item.stato.charAt(0).toUpperCase() + item.stato.slice(1);
            
            html += '<tr>';
            html += '<td><strong>#' + item.id + '</strong></td>';
            html += '<td>' + formatDate(item.data_evento) + '</td>';
            html += '<td><strong>' + item.nome_cliente + '</strong><br><small style="color: #666;">' + item.email + '</small></td>';
            html += '<td>' + (item.tipo_evento || '-') + '</td>';
            html += '<td>' + item.tipo_menu + '</td>';
            html += '<td>' + item.numero_invitati + '</td>';
            html += '<td><strong>€ ' + parseFloat(item.importo_totale).toFixed(2) + '</strong></td>';
            html += '<td>€ ' + parseFloat(item.acconto).toFixed(2) + '</td>';
            html += '<td><span class="disco747-status-badge ' + statusClass + '">' + statusLabel + '</span></td>';
            html += '<td>';
            html += '<a href="' + item.googledrive_url + '" target="_blank" class="button button-small" title="Apri su Google Drive"><span class="dashicons dashicons-cloud"></span></a> ';
            html += '<button class="button button-small disco747-view-details" data-id="' + item.id + '" title="Dettagli"><span class="dashicons dashicons-visibility"></span></button>';
            html += '</td>';
            html += '</tr>';
        });
        
        $('#disco747-table-body').html(html);
        
        // Testo "Showing X of Y"
        $('#disco747-showing-text').text('Visualizzati ' + (start + 1) + '-' + Math.min(end, data.length) + ' di ' + data.length + ' preventivi');
        
        // Pulsanti paginazione
        renderPagination(totalPages);
    }
    
    /**
     * Renderizza paginazione
     */
    function renderPagination(totalPages) {
        if (totalPages <= 1) {
            $('#disco747-pagination-buttons').html('');
            return;
        }
        
        let html = '';
        
        // Precedente
        if (currentPage > 1) {
            html += '<button class="button disco747-page-btn" data-page="' + (currentPage - 1) + '">« Precedente</button> ';
        }
        
        // Numeri pagina
        for (let i = 1; i <= Math.min(totalPages, 5); i++) {
            const active = i === currentPage ? ' button-primary' : '';
            html += '<button class="button disco747-page-btn' + active + '" data-page="' + i + '">' + i + '</button> ';
        }
        
        // Successiva
        if (currentPage < totalPages) {
            html += '<button class="button disco747-page-btn" data-page="' + (currentPage + 1) + '">Successiva »</button>';
        }
        
        $('#disco747-pagination-buttons').html(html);
    }
    
    /**
     * Click paginazione
     */
    $(document).on('click', '.disco747-page-btn', function() {
        currentPage = parseInt($(this).data('page'));
        filterTableData();
    });
    
    /**
     * Formatta data
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        const day = ('0' + date.getDate()).slice(-2);
        const month = ('0' + (date.getMonth() + 1)).slice(-2);
        const year = date.getFullYear();
        return day + '/' + month + '/' + year;
    }
    
});

// Animazione rotazione
const style = document.createElement('style');
style.textContent = '@keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
document.head.appendChild(style);
</script>