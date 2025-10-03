/**
 * JavaScript per Scansione Excel Auto - 747 Disco CRM
 * DEBUG VERSION per iPhone - traccia ogni passaggio
 * 
 * @version 1.0.3-FIX-SELECTOR
 */

(function($) {
    'use strict';

    // ========================================================================
    // SISTEMA DEBUG VISIVO PER iPHONE
    // ========================================================================
    
    const DebugPanel = {
        initialized: false,
        $panel: null,
        $content: null,
        logCount: 0,
        
        init: function() {
            if (this.initialized) return;
            
            // Crea pannello debug fisso in cima
            const panelHTML = `
                <div id="iphone-debug-panel" style="
                    position: fixed;
                    top: 46px;
                    left: 0;
                    right: 0;
                    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
                    color: #00ff00;
                    padding: 15px;
                    font-family: 'Courier New', monospace;
                    font-size: 13px;
                    z-index: 999999;
                    max-height: 250px;
                    overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
                    border-bottom: 3px solid #00ff00;
                ">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #444; padding-bottom: 8px;">
                        <strong style="font-size: 16px; color: #00ff00;">🔍 DEBUG CONSOLE (iPhone)</strong>
                        <div>
                            <button id="debug-clear-btn" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; margin-right: 5px; font-size: 12px;">🧹 Pulisci</button>
                            <button id="debug-toggle-btn" style="background: #ffc107; color: black; border: none; padding: 5px 10px; border-radius: 4px; font-size: 12px;">👁️ Nascondi</button>
                        </div>
                    </div>
                    <div id="iphone-debug-content" style="line-height: 1.6;">
                        <div style="color: #00ff00;">✅ Pannello debug inizializzato</div>
                    </div>
                </div>
            `;
            
            $('body').prepend(panelHTML);
            this.$panel = $('#iphone-debug-panel');
            this.$content = $('#iphone-debug-content');
            
            // Binding pulsanti
            $('#debug-clear-btn').on('click', () => {
                this.$content.html('<div style="color: #ffc107;">🧹 Log pulito</div>');
                this.logCount = 0;
            });
            
            $('#debug-toggle-btn').on('click', () => {
                const $content = this.$content;
                if ($content.is(':visible')) {
                    $content.hide();
                    $('#debug-toggle-btn').text('👁️ Mostra');
                } else {
                    $content.show();
                    $('#debug-toggle-btn').text('👁️ Nascondi');
                }
            });
            
            this.initialized = true;
            this.log('DEBUG', 'Pannello debug pronto', 'success');
        },
        
        log: function(category, message, type = 'info') {
            if (!this.initialized) this.init();
            
            this.logCount++;
            const timestamp = new Date().toLocaleTimeString('it-IT', { hour12: false });
            
            const colors = {
                'info': '#00bfff',
                'success': '#00ff00',
                'warning': '#ffc107',
                'error': '#ff4444',
                'click': '#ff00ff',
                'ajax': '#00ffff'
            };
            
            const icons = {
                'info': 'ℹ️',
                'success': '✅',
                'warning': '⚠️',
                'error': '❌',
                'click': '🖱️',
                'ajax': '📡'
            };
            
            const color = colors[type] || '#ffffff';
            const icon = icons[type] || '📌';
            
            const logEntry = `
                <div style="margin-bottom: 3px; padding: 6px 8px; background: rgba(255,255,255,0.05); border-left: 3px solid ${color}; border-radius: 3px;">
                    <span style="color: #888; font-size: 11px;">[${timestamp}]</span>
                    <span style="color: ${color}; font-weight: bold; margin-left: 8px;">${icon} ${category}</span>
                    <span style="color: #ccc; margin-left: 8px;">${message}</span>
                </div>
            `;
            
            this.$content.append(logEntry);
            
            // Auto-scroll
            this.$content.scrollTop(this.$content[0].scrollHeight);
            
            // Limita log (max 100 righe)
            if (this.logCount > 100) {
                this.$content.find('div').first().remove();
                this.logCount--;
            }
            
            // Log anche in console normale
            console.log(`[${timestamp}] [${category}] ${message}`);
        },
        
        logStep: function(stepNumber, description) {
            this.log(`STEP ${stepNumber}`, description, 'success');
        },
        
        logError: function(context, error) {
            this.log(context, `ERRORE: ${error}`, 'error');
        },
        
        logClick: function(selector) {
            this.log('CLICK', `Click rilevato su: ${selector}`, 'click');
        },
        
        logAjax: function(action, status) {
            this.log('AJAX', `${action} - ${status}`, 'ajax');
        }
    };

    // ========================================================================
    // OGGETTO EXCEL SCANNER CON DEBUG
    // ========================================================================

    const ExcelScanner = {
        
        currentPage: 1,
        totalPages: 1,
        currentSearch: '',
        isLoading: false,
        isBatchScanning: false,

        config: {
            maxRetries: 3,
            retryDelay: 1000,
            maxLogLines: 1000,
            autoRefreshInterval: 30000
        },

        init: function() {
            DebugPanel.logStep(1, 'Inizializzazione ExcelScanner...');
            
            if (!this.checkRequirements()) {
                DebugPanel.logError('INIT', 'Requisiti mancanti');
                return;
            }

            DebugPanel.logStep(2, 'Requisiti OK - Binding eventi...');
            this.bindEvents();
            
            DebugPanel.logStep(3, 'Eventi bindati - Inizializzazione UI...');
            this.initUI();
            
            DebugPanel.logStep(4, 'ExcelScanner pronto!');
        },

        checkRequirements: function() {
            if (typeof window.disco747ExcelScanData === 'undefined') {
                DebugPanel.logError('REQUISITI', 'window.disco747ExcelScanData non trovato');
                return false;
            }

            if (typeof $ === 'undefined') {
                DebugPanel.logError('REQUISITI', 'jQuery non trovato');
                return false;
            }

            DebugPanel.log('REQUISITI', 'Tutti i requisiti soddisfatti', 'success');
            DebugPanel.log('CONFIG', `ajaxurl: ${window.disco747ExcelScanData?.ajaxurl || 'N/D'}`, 'info');
            DebugPanel.log('CONFIG', `nonce: ${window.disco747ExcelScanData?.nonce ? 'presente' : 'MANCANTE'}`, window.disco747ExcelScanData?.nonce ? 'success' : 'error');
            DebugPanel.log('CONFIG', `gdriveAvailable: ${window.disco747ExcelScanData?.gdriveAvailable}`, 'info');
            
            return true;
        },

        initUI: function() {
            this.updateUIState();
            
            if (typeof $.fn.tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }

            if (window.disco747ExcelScanData?.gdriveAvailable) {
                $('#excel-search').focus();
            }
            
            DebugPanel.log('UI', 'Interfaccia inizializzata', 'success');
        },

        updateUIState: function() {
            const available = window.disco747ExcelScanData?.gdriveAvailable;
            
            if (!available) {
                $('#excel-search, #manual-file-id').prop('disabled', true);
                $('button[id*="btn"]:not(#refresh-all-btn)').prop('disabled', true);
                $('#files-count').text('N/D - Google Drive non configurato');
                DebugPanel.log('UI', 'Google Drive NON disponibile - UI disabilitata', 'warning');
            } else {
                DebugPanel.log('UI', 'Google Drive disponibile - UI attiva', 'success');
            }
        },

        bindEvents: function() {
            DebugPanel.log('BINDING', 'Inizio binding eventi...', 'info');
            
            // ✅ CORRETTO: Pulsante batch scan con ID reale dalla pagina
            const $batchBtn = $('#disco747-start-batch-scan');
            if ($batchBtn.length === 0) {
                DebugPanel.logError('BINDING', '#disco747-start-batch-scan NON TROVATO nel DOM!');
            } else {
                DebugPanel.log('BINDING', `#disco747-start-batch-scan TROVATO (${$batchBtn.length} elementi)`, 'success');
                
                $batchBtn.on('click', (e) => {
                    DebugPanel.logClick('#disco747-start-batch-scan');
                    DebugPanel.log('EVENT', 'Handler batch scan invocato', 'success');
                    this.startBatchScan();
                });
                
                DebugPanel.log('BINDING', 'Handler click collegato a #disco747-start-batch-scan', 'success');
            }
            
            // Altri pulsanti
            $('#search-files-btn').on('click', () => this.searchFiles());
            $('#refresh-files-btn').on('click', () => this.refreshFiles());
            $('#refresh-all-btn').on('click', () => this.refreshAll());
            $('#analyze-manual-btn').on('click', () => this.analyzeManualId());
            $('#clear-results-btn').on('click', () => this.clearResults());
            
            $('#toggle-log-btn').on('click', () => this.toggleLog());
            $('#copy-log-btn').on('click', () => this.copyLogToClipboard());
            $('#download-log-btn').on('click', () => this.downloadLog());
            
            $('#export-results-btn').on('click', () => this.exportResults());
            
            $('#prev-page-btn').on('click', () => this.prevPage());
            $('#next-page-btn').on('click', () => this.nextPage());
            
            $('#excel-search').on('keypress', (e) => {
                if (e.which === 13) this.searchFiles();
            });

            $('#manual-file-id').on('keypress', (e) => {
                if (e.which === 13) this.analyzeManualId();
            });

            $('#manual-file-id').on('input', (e) => {
                this.validateFileId($(e.target).val());
            });

            DebugPanel.log('BINDING', 'Tutti gli eventi collegati con successo', 'success');
        },

        /**
         * FUNZIONE PRINCIPALE: Batch Scan
         */
        startBatchScan: function() {
            DebugPanel.logStep('BATCH-1', 'Avvio batch scan...');
            
            if (this.isBatchScanning) {
                DebugPanel.logError('BATCH', 'Batch scan già in corso');
                alert('⚠️ Scansione batch già in corso');
                return;
            }
            
            if (!window.disco747ExcelScanData?.gdriveAvailable) {
                DebugPanel.logError('BATCH', 'Google Drive non configurato');
                alert('❌ Google Drive non configurato');
                return;
            }
            
            DebugPanel.logStep('BATCH-2', 'Stato verificato - preparazione dati AJAX...');
            
            // Stato UI
            this.isBatchScanning = true;
            $('#disco747-start-batch-scan').prop('disabled', true).text('🔄 Scansione in corso...');
            
            DebugPanel.logStep('BATCH-3', 'UI aggiornata - pulsante disabilitato');
            
            // Mostra progress se presente
            const $progress = $('#batch-scan-progress');
            if ($progress.length) {
                $progress.show().find('.progress-bar').css('width', '0%');
                DebugPanel.log('BATCH', 'Progress bar mostrata', 'info');
            }
            
            // Preparazione dati AJAX
            const ajaxData = {
                action: 'disco747_scan_drive_batch',
                nonce: window.disco747ExcelScanData?.nonce || '',
                _wpnonce: window.disco747ExcelScanData?.nonce || ''
            };
            
            DebugPanel.logStep('BATCH-4', 'Dati AJAX preparati');
            DebugPanel.log('AJAX-DATA', `action: ${ajaxData.action}`, 'ajax');
            DebugPanel.log('AJAX-DATA', `nonce: ${ajaxData.nonce ? 'presente' : 'MANCANTE'}`, ajaxData.nonce ? 'success' : 'error');
            
            const ajaxUrl = window.disco747ExcelScanData?.ajaxurl || ajaxurl;
            DebugPanel.logStep('BATCH-5', `Invio richiesta AJAX a: ${ajaxUrl}`);
            DebugPanel.logAjax('disco747_scan_drive_batch', 'INVIO...');
            
            // Chiamata AJAX
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: ajaxData,
                timeout: 300000, // 5 minuti
                beforeSend: function(xhr) {
                    DebugPanel.log('AJAX', 'beforeSend - headers inviati', 'ajax');
                },
                success: (response) => {
                    DebugPanel.logAjax('disco747_scan_drive_batch', 'SUCCESSO');
                    DebugPanel.log('RESPONSE', JSON.stringify(response).substring(0, 200), 'success');
                    this.handleBatchScanResponse(response);
                },
                error: (xhr, status, error) => {
                    DebugPanel.logAjax('disco747_scan_drive_batch', 'ERRORE');
                    DebugPanel.logError('AJAX', `status: ${status}, error: ${error}`);
                    DebugPanel.logError('AJAX', `HTTP Status: ${xhr.status}`);
                    DebugPanel.logError('AJAX', `Response: ${xhr.responseText.substring(0, 300)}`);
                    this.handleBatchScanError(xhr, status, error);
                },
                complete: () => {
                    this.isBatchScanning = false;
                    $('#disco747-start-batch-scan').prop('disabled', false).text('🔄 Analizza Ora');
                    DebugPanel.log('BATCH', 'Batch scan completato (complete callback)', 'info');
                }
            });
            
            DebugPanel.logStep('BATCH-6', 'Richiesta AJAX inviata - in attesa risposta...');
        },

        handleBatchScanResponse: function(response) {
            DebugPanel.logStep('RESPONSE-1', 'Gestione risposta...');
            DebugPanel.log('RESPONSE', `success: ${response.success}`, response.success ? 'success' : 'error');
            
            if (response.success) {
                const data = response.data || {};
                const found = data.found || 0;
                const processed = data.processed || 0;
                const inserted = data.inserted || 0;
                const updated = data.updated || 0;
                const errors = data.errors || 0;
                
                DebugPanel.log('RISULTATI', `Trovati: ${found}`, 'success');
                DebugPanel.log('RISULTATI', `Processati: ${processed}`, 'success');
                DebugPanel.log('RISULTATI', `Inseriti: ${inserted}`, 'success');
                DebugPanel.log('RISULTATI', `Aggiornati: ${updated}`, 'success');
                DebugPanel.log('RISULTATI', `Errori: ${errors}`, errors > 0 ? 'warning' : 'success');
                
                const message = `✅ Batch scan completato!\n\nFile trovati: ${found}\nProcessati: ${processed}\nNuovi: ${inserted}\nAggiornati: ${updated}\nErrori: ${errors}`;
                
                alert(message);
                
                // Mostra messaggi dettagliati se presenti
                if (data.messages && Array.isArray(data.messages)) {
                    data.messages.forEach(msg => {
                        DebugPanel.log('SERVER-MSG', msg, 'info');
                    });
                }
                
                // Ricarica la pagina
                DebugPanel.log('RELOAD', 'Ricaricamento pagina tra 2 secondi...', 'info');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
                
            } else {
                const errorMsg = response.data || 'Errore sconosciuto durante il batch scan';
                DebugPanel.logError('BATCH', errorMsg);
                alert('❌ Errore batch scan: ' + errorMsg);
            }
        },

        handleBatchScanError: function(xhr, status, error) {
            let errorMessage = 'Errore di rete durante il batch scan';
            
            if (xhr.status === 403) {
                errorMessage = 'Accesso negato - verifica i permessi';
            } else if (xhr.status === 404) {
                errorMessage = 'Endpoint non trovato';
            } else if (xhr.status === 500) {
                errorMessage = 'Errore del server';
            } else if (status === 'timeout') {
                errorMessage = 'Timeout - operazione troppo lunga';
            }
            
            DebugPanel.logError('ERROR-DETAIL', errorMessage);
            alert('❌ ' + errorMessage);
        },

        // ========================================================================
        // FUNZIONI UTILITY (semplificate per debug)
        // ========================================================================

        validateFileId: function(fileId) {
            const btn = $('#analyze-manual-btn');
            const input = $('#manual-file-id');
            
            if (!fileId || fileId.length < 10) {
                btn.prop('disabled', true);
                input.removeClass('valid').addClass('invalid');
                return false;
            }
            
            const gdFileIdPattern = /^[a-zA-Z0-9_-]{25,50}$/;
            
            if (gdFileIdPattern.test(fileId)) {
                btn.prop('disabled', false);
                input.removeClass('invalid').addClass('valid');
                return true;
            } else {
                btn.prop('disabled', true);
                input.removeClass('valid').addClass('invalid');
                return false;
            }
        },

        analyzeManualId: function() {
            const fileId = $('#manual-file-id').val().trim();
            
            if (!this.validateFileId(fileId)) {
                alert('Inserisci un File ID valido');
                $('#manual-file-id').focus().select();
                return;
            }
            
            DebugPanel.log('MANUAL', `Analisi manuale File ID: ${fileId}`, 'info');
        },

        searchFiles: function() {
            const searchTerm = $('#excel-search').val().trim();
            DebugPanel.log('SEARCH', `Ricerca: "${searchTerm}"`, 'info');
            this.currentSearch = searchTerm;
            this.currentPage = 1;
        },

        refreshFiles: function() {
            DebugPanel.log('REFRESH', 'Refresh lista file', 'info');
            this.currentPage = 1;
        },

        refreshAll: function() {
            DebugPanel.log('REFRESH-ALL', 'Refresh completo', 'info');
            this.currentSearch = '';
            this.currentPage = 1;
            $('#excel-search').val('');
        },

        clearResults: function() {
            $('#analysis-results').hide();
            $('#extracted-data-dashboard').empty();
            DebugPanel.log('CLEAR', 'Risultati puliti', 'info');
        },

        toggleLog: function() {
            const $logContent = $('#debug-log-content');
            if ($logContent.length) {
                $logContent.toggle();
            }
        },

        copyLogToClipboard: function() {
            DebugPanel.log('CLIPBOARD', 'Tentativo copia log', 'info');
        },

        downloadLog: function() {
            DebugPanel.log('DOWNLOAD', 'Download log', 'info');
        },

        exportResults: function() {
            DebugPanel.log('EXPORT', 'Export risultati', 'info');
        },

        prevPage: function() {
            if (this.currentPage > 1) {
                this.currentPage--;
                DebugPanel.log('PAGINATION', `Pagina precedente: ${this.currentPage}`, 'info');
            }
        },

        nextPage: function() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                DebugPanel.log('PAGINATION', `Pagina successiva: ${this.currentPage}`, 'info');
            }
        }
    };

    // ========================================================================
    // INIZIALIZZAZIONE GLOBALE
    // ========================================================================

    // Espone oggetto globalmente
    window.ExcelScanner = ExcelScanner;
    window.DebugPanel = DebugPanel;

    // Inizializzazione quando DOM è pronto
    $(document).ready(function() {
        DebugPanel.init();
        DebugPanel.logStep(0, 'jQuery Document Ready - START');
        
        // Verifica configurazione
        if (typeof window.disco747ExcelScanData === 'undefined') {
            DebugPanel.logError('FATAL', 'window.disco747ExcelScanData NON DEFINITO');
            $('#disco747-alerts').html(
                '<div class="alert alert-error">❌ Errore configurazione. Ricarica la pagina.</div>'
            );
            return;
        }

        // Inizializza l'applicazione
        ExcelScanner.init();

        // Test presenza pulsante con ID corretto
        const $batchBtn = $('#disco747-start-batch-scan');
        if ($batchBtn.length === 0) {
            DebugPanel.logError('DOM-CHECK', '#disco747-start-batch-scan NON TROVATO dopo init');
        } else {
            DebugPanel.log('DOM-CHECK', `#disco747-start-batch-scan presente (${$batchBtn.length} elementi)`, 'success');
            DebugPanel.log('DOM-CHECK', `Testo pulsante: "${$batchBtn.text()}"`, 'info');
            DebugPanel.log('DOM-CHECK', `Pulsante visibile: ${$batchBtn.is(':visible')}`, $batchBtn.is(':visible') ? 'success' : 'warning');
            DebugPanel.log('DOM-CHECK', `Pulsante abilitato: ${!$batchBtn.prop('disabled')}`, !$batchBtn.prop('disabled') ? 'success' : 'warning');
        }

        // Log completo configurazione
        DebugPanel.log('FINAL-CHECK', '🎵 747 Disco Excel Scanner inizializzato', 'success');
        DebugPanel.log('VERSION', '1.0.3-FIX-SELECTOR', 'info');
        DebugPanel.log('TIMESTAMP', new Date().toISOString(), 'info');
    });

})(jQuery);