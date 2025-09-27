/**
 * Excel Scan JavaScript Handler
 * 
 * @package    Disco747_CRM
 * @subpackage Assets/JS
 * @version    11.5.9-EXCEL-SCAN
 */

(function($) {
    'use strict';
    
    // Main Excel Scan Manager
    window.Disco747ExcelScan = {
        
        // Configuration
        config: {
            ajaxUrl: typeof disco747ExcelScan !== 'undefined' ? disco747ExcelScan.ajaxurl : ajaxurl,
            nonce: typeof disco747ExcelScan !== 'undefined' ? disco747ExcelScan.nonce : '',
            strings: typeof disco747ExcelScan !== 'undefined' ? disco747ExcelScan.strings : {
                processing: 'Elaborazione in corso...',
                success: 'Operazione completata',
                error: 'Si è verificato un errore'
            }
        },
        
        // State
        state: {
            isScanning: false,
            isPaused: false,
            currentIndex: 0,
            totalFiles: 0,
            successCount: 0,
            errorCount: 0,
            skipCount: 0,
            startTime: null,
            elapsedTimer: null,
            files: [],
            results: [],
            abortController: null
        },
        
        // Debug levels
        debugLevels: {
            ERROR: 'error',
            WARNING: 'warning',
            INFO: 'info',
            SUCCESS: 'success',
            DEBUG: 'debug'
        },
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.loadStoredState();
            this.initializeUI();
            this.log('Excel Scan Manager inizializzato', this.debugLevels.SUCCESS);
        },
        
        // Bind events
        bindEvents: function() {
            const self = this;
            
            // Main batch scan button
            $(document).on('click', '#start-batch, #start-batch-analysis', function(e) {
                e.preventDefault();
                self.startBatchScan();
            });
            
            // Stop batch scan
            $(document).on('click', '#stop-batch, #stop-batch-analysis', function(e) {
                e.preventDefault();
                self.stopBatchScan();
            });
            
            // Pause/Resume
            $(document).on('click', '#pause-batch', function(e) {
                e.preventDefault();
                self.togglePauseScan();
            });
            
            // Single file scan
            $(document).on('click', '.scan-single-file', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                const fileName = $(this).data('file-name');
                self.scanSingleFile(fileId, fileName);
            });
            
            // Refresh table
            $(document).on('click', '#refresh-table, #refresh-analysis-table', function(e) {
                e.preventDefault();
                self.refreshAnalysisTable();
            });
            
            // Debug controls
            $(document).on('click', '#toggle-debug', function() {
                $('#debug-content, #debug-log-container').slideToggle();
            });
            
            $(document).on('click', '#clear-debug, #clear-debug-log', function() {
                self.clearDebugLog();
            });
            
            $(document).on('click', '#download-debug-log', function() {
                self.downloadDebugLog();
            });
            
            // Filter controls
            $(document).on('input', '#search-analysis', function() {
                self.filterTable();
            });
            
            $(document).on('change', '#filter-menu, #filter-stato', function() {
                self.filterTable();
            });
            
            // Auto-save state every 30 seconds during scan
            setInterval(function() {
                if (self.state.isScanning) {
                    self.saveState();
                }
            }, 30000);
        },
        
        // Initialize UI
        initializeUI: function() {
            // Check if we're on the Excel scan page
            if ($('#batch-progress, .batch-progress-container').length === 0) {
                return;
            }
            
            // Set initial values
            this.updateProgressUI();
            
            // Load any pending batch
            if (this.state.files.length > 0 && !this.state.isScanning) {
                this.showResumeBatchPrompt();
            }
        },
        
        // Start batch scan
        startBatchScan: function() {
            const self = this;
            
            if (this.state.isScanning) {
                this.log('Scansione già in corso', this.debugLevels.WARNING);
                return;
            }
            
            // Get files list from page
            if (typeof batchFiles !== 'undefined' && batchFiles.length > 0) {
                this.state.files = batchFiles;
            } else {
                // Try to get from data attribute
                const filesData = $('#excel-files-data').data('files');
                if (filesData && filesData.length > 0) {
                    this.state.files = filesData;
                } else {
                    this.log('Nessun file da scansionare', this.debugLevels.ERROR);
                    alert('Nessun file Excel trovato per la scansione');
                    return;
                }
            }
            
            // Reset state
            this.state.currentIndex = 0;
            this.state.totalFiles = this.state.files.length;
            this.state.successCount = 0;
            this.state.errorCount = 0;
            this.state.skipCount = 0;
            this.state.results = [];
            this.state.startTime = Date.now();
            this.state.isScanning = true;
            this.state.isPaused = false;
            
            // Create abort controller for cancellation
            if (window.AbortController) {
                this.state.abortController = new AbortController();
            }
            
            this.log('=== INIZIO SCANSIONE BATCH ===', this.debugLevels.INFO);
            this.log('File da processare: ' + this.state.totalFiles, this.debugLevels.INFO);
            
            // Update UI
            $('#start-batch, #start-batch-analysis').hide();
            $('#stop-batch, #stop-batch-analysis').show();
            $('#pause-batch').show();
            $('#batch-progress, .batch-progress-container').slideDown();
            
            // Start elapsed timer
            this.startElapsedTimer();
            
            // Save initial state
            this.saveState();
            
            // Process first file
            this.processNextFile();
        },
        
        // Process next file
        processNextFile: function() {
            const self = this;
            
            // Check if stopped or completed
            if (!this.state.isScanning || this.state.currentIndex >= this.state.totalFiles) {
                this.completeBatchScan();
                return;
            }
            
            // Check if paused
            if (this.state.isPaused) {
                setTimeout(function() {
                    self.processNextFile();
                }, 1000);
                return;
            }
            
            const file = this.state.files[this.state.currentIndex];
            const fileNumber = this.state.currentIndex + 1;
            
            this.log(`[${fileNumber}/${this.state.totalFiles}] Analizzando: ${file.name}`, this.debugLevels.INFO);
            
            // Update UI
            this.updateProgressUI();
            $('#batch-status, #batch-current-file').text(`Analizzando: ${file.name}`);
            
            // Make AJAX request
            const ajaxOptions = {
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'disco747_batch_scan_excel',
                    nonce: this.config.nonce,
                    file_id: file.id,
                    file_name: file.name,
                    file_path: file.path || '',
                    current_index: this.state.currentIndex,
                    total_files: this.state.totalFiles
                },
                timeout: 30000, // 30 seconds timeout
                success: function(response) {
                    self.handleFileSuccess(response, file, fileNumber);
                },
                error: function(xhr, status, error) {
                    self.handleFileError(error, file, fileNumber, status);
                },
                complete: function() {
                    self.state.currentIndex++;
                    
                    // Small delay between files to avoid overwhelming server
                    setTimeout(function() {
                        self.processNextFile();
                    }, 500);
                }
            };
            
            // Add abort signal if available
            if (this.state.abortController) {
                ajaxOptions.signal = this.state.abortController.signal;
            }
            
            $.ajax(ajaxOptions);
        },
        
        // Handle file success
        handleFileSuccess: function(response, file, fileNumber) {
            if (response.success && response.data && response.data.ok) {
                this.state.successCount++;
                
                const result = {
                    file: file,
                    success: true,
                    data: response.data.data,
                    database_id: response.data.database_id
                };
                
                this.state.results.push(result);
                
                this.log(`✅ File ${fileNumber} analizzato con successo`, this.debugLevels.SUCCESS);
                
                if (response.data.data) {
                    const data = response.data.data;
                    this.log(`  📊 Menu: ${data.tipo_menu || 'N/A'}, Invitati: ${data.numero_invitati || 'N/A'}, Importo: €${data.importo || '0'}`, this.debugLevels.INFO);
                    
                    // Update table row if exists
                    this.updateTableRow(file.id, data, 'success');
                }
                
                // Log any additional messages
                if (response.data.log && Array.isArray(response.data.log)) {
                    response.data.log.forEach(logEntry => {
                        this.log(`  ${logEntry}`, this.debugLevels.DEBUG);
                    });
                }
                
            } else {
                this.state.errorCount++;
                
                const errorMsg = response.data ? (response.data.error || 'Errore sconosciuto') : 'Risposta non valida';
                
                const result = {
                    file: file,
                    success: false,
                    error: errorMsg
                };
                
                this.state.results.push(result);
                
                this.log(`❌ File ${fileNumber} fallito: ${errorMsg}`, this.debugLevels.ERROR);
                
                // Update table row if exists
                this.updateTableRow(file.id, null, 'error');
            }
        },
        
        // Handle file error
        handleFileError: function(error, file, fileNumber, status) {
            this.state.errorCount++;
            
            let errorMsg = error;
            if (status === 'timeout') {
                errorMsg = 'Timeout - il file richiede troppo tempo';
            } else if (status === 'abort') {
                errorMsg = 'Operazione annullata';
                this.state.skipCount++;
                this.state.errorCount--; // Don't count aborted as errors
            }
            
            const result = {
                file: file,
                success: false,
                error: errorMsg
            };
            
            this.state.results.push(result);
            
            this.log(`❌ Errore file ${fileNumber}: ${errorMsg}`, this.debugLevels.ERROR);
            
            // Update table row if exists
            this.updateTableRow(file.id, null, 'error');
        },
        
        // Complete batch scan
        completeBatchScan: function() {
            this.state.isScanning = false;
            this.stopElapsedTimer();
            
            const duration = Math.floor((Date.now() - this.state.startTime) / 1000);
            const minutes = Math.floor(duration / 60);
            const seconds = duration % 60;
            
            this.log('=== SCANSIONE COMPLETATA ===', this.debugLevels.INFO);
            this.log(`Durata: ${minutes}m ${seconds}s`, this.debugLevels.INFO);
            this.log(`Risultati: ${this.state.successCount} successi, ${this.state.errorCount} errori, ${this.state.skipCount} saltati`, this.debugLevels.INFO);
            
            // Update UI
            $('#batch-status, #batch-current-file').text('Scansione completata!');
            $('#stop-batch, #stop-batch-analysis').hide();
            $('#pause-batch').hide();
            $('#start-batch, #start-batch-analysis').show();
            
            // Show summary
            this.showScanSummary();
            
            // Clear saved state
            this.clearState();
            
            // Refresh table after a delay
            const self = this;
            setTimeout(function() {
                self.refreshAnalysisTable();
            }, 2000);
        },
        
        // Stop batch scan
        stopBatchScan: function() {
            if (!this.state.isScanning) return;
            
            this.log('Scansione interrotta dall\'utente', this.debugLevels.WARNING);
            
            this.state.isScanning = false;
            
            // Abort current request if possible
            if (this.state.abortController) {
                this.state.abortController.abort();
            }
            
            this.stopElapsedTimer();
            
            // Update UI
            $('#batch-status, #batch-current-file').text('Scansione interrotta');
            $('#stop-batch, #stop-batch-analysis').hide();
            $('#pause-batch').hide();
            $('#start-batch, #start-batch-analysis').show();
            
            // Show partial summary
            this.showScanSummary();
            
            // Clear saved state
            this.clearState();
        },
        
        // Toggle pause scan
        togglePauseScan: function() {
            this.state.isPaused = !this.state.isPaused;
            
            if (this.state.isPaused) {
                this.log('Scansione in pausa', this.debugLevels.WARNING);
                $('#pause-batch').text('▶️ Riprendi');
                $('#batch-status').text('In pausa...');
            } else {
                this.log('Scansione ripresa', this.debugLevels.INFO);
                $('#pause-batch').text('⏸️ Pausa');
            }
        },
        
        // Scan single file
        scanSingleFile: function(fileId, fileName) {
            const self = this;
            
            this.log(`Scansione singola file: ${fileName}`, this.debugLevels.INFO);
            
            const $button = $(`.scan-single-file[data-file-id="${fileId}"]`);
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Scansione...');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'disco747_batch_scan_excel',
                    nonce: this.config.nonce,
                    file_id: fileId,
                    file_name: fileName,
                    file_path: '',
                    current_index: 0,
                    total_files: 1
                },
                timeout: 30000,
                success: function(response) {
                    if (response.success && response.data.ok) {
                        self.log(`✅ File analizzato con successo`, self.debugLevels.SUCCESS);
                        self.showNotification('File analizzato con successo', 'success');
                        
                        // Refresh table
                        setTimeout(function() {
                            self.refreshAnalysisTable();
                        }, 1000);
                    } else {
                        const error = response.data ? response.data.error : 'Errore sconosciuto';
                        self.log(`❌ Errore: ${error}`, self.debugLevels.ERROR);
                        self.showNotification(`Errore: ${error}`, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.log(`❌ Errore AJAX: ${error}`, self.debugLevels.ERROR);
                    self.showNotification(`Errore: ${error}`, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        // Update progress UI
        updateProgressUI: function() {
            const progress = this.state.totalFiles > 0 
                ? (this.state.currentIndex / this.state.totalFiles) * 100 
                : 0;
            
            $('#batch-current').text(this.state.currentIndex);
            $('#batch-total').text(this.state.totalFiles);
            $('#batch-success').text(this.state.successCount);
            $('#batch-errors').text(this.state.errorCount);
            $('#batch-progress-bar, .batch-progress-fill').css('width', progress + '%');
            
            // Update progress text
            $('.batch-progress-text').text(`${this.state.currentIndex} / ${this.state.totalFiles} file processati`);
        },
        
        // Update table row
        updateTableRow: function(fileId, data, status) {
            const $row = $(`#batch-results-table tr[data-file-id="${fileId}"], .batch-results-table tr[data-file-id="${fileId}"]`);
            
            if ($row.length === 0) return;
            
            // Update status column
            const $statusCell = $row.find('.status-cell, td:last-child');
            
            let statusBadge = '';
            switch(status) {
                case 'success':
                    statusBadge = '<span class="badge badge-success">✅ OK</span>';
                    break;
                case 'error':
                    statusBadge = '<span class="badge badge-danger">❌ Errore</span>';
                    break;
                case 'processing':
                    statusBadge = '<span class="badge badge-warning">⏳ In corso</span>';
                    break;
            }
            
            $statusCell.html(statusBadge);
            
            // Update data columns if available
            if (data) {
                if (data.data_evento) $row.find('.data-evento-cell').text(data.data_evento);
                if (data.tipo_evento) $row.find('.tipo-evento-cell').text(data.tipo_evento);
                if (data.tipo_menu) $row.find('.menu-cell').text(data.tipo_menu);
                if (data.numero_invitati) $row.find('.invitati-cell').text(data.numero_invitati);
                if (data.importo) $row.find('.importo-cell').text('€ ' + data.importo);
            }
        },
        
        // Start elapsed timer
        startElapsedTimer: function() {
            const self = this;
            
            this.state.elapsedTimer = setInterval(function() {
                if (!self.state.isPaused) {
                    const elapsed = Math.floor((Date.now() - self.state.startTime) / 1000);
                    const minutes = Math.floor(elapsed / 60);
                    const seconds = elapsed % 60;
                    
                    const timeStr = (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                    $('#batch-time, .batch-elapsed-time').text(timeStr);
                }
            }, 1000);
        },
        
        // Stop elapsed timer
        stopElapsedTimer: function() {
            if (this.state.elapsedTimer) {
                clearInterval(this.state.elapsedTimer);
                this.state.elapsedTimer = null;
            }
        },
        
        // Show scan summary
        showScanSummary: function() {
            const summaryHtml = `
                <div class="scan-summary" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h3 style="margin-top: 0;">📊 Riepilogo Scansione</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                        <div style="text-align: center;">
                            <div style="font-size: 2em; font-weight: bold; color: #28a745;">${this.state.successCount}</div>
                            <div>Successi</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 2em; font-weight: bold; color: #dc3545;">${this.state.errorCount}</div>
                            <div>Errori</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 2em; font-weight: bold; color: #6c757d;">${this.state.skipCount}</div>
                            <div>Saltati</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 2em; font-weight: bold; color: #17a2b8;">${this.state.totalFiles}</div>
                            <div>Totali</div>
                        </div>
                    </div>
                </div>
            `;
            
            // Insert summary after progress bar
            if ($('#scan-summary').length === 0) {
                $('#batch-progress, .batch-progress-container').after('<div id="scan-summary"></div>');
            }
            
            $('#scan-summary').html(summaryHtml);
        },
        
        // Show notification
        showNotification: function(message, type = 'info') {
            const typeClasses = {
                success: 'notice-success',
                error: 'notice-error',
                warning: 'notice-warning',
                info: 'notice-info'
            };
            
            const notificationHtml = `
                <div class="notice ${typeClasses[type]} is-dismissible disco747-notification" style="display: none;">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Chiudi</span>
                    </button>
                </div>
            `;
            
            const $notification = $(notificationHtml);
            
            $('.wrap h1, .disco747-excel-scan-wrapper > div:first').after($notification);
            
            $notification.slideDown();
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notification.slideUp(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Bind dismiss button
            $notification.find('.notice-dismiss').on('click', function() {
                $notification.slideUp(function() {
                    $(this).remove();
                });
            });
        },
        
        // Refresh analysis table
        refreshAnalysisTable: function() {
            this.log('Aggiornamento tabella analisi...', this.debugLevels.INFO);
            
            // Simple reload for now - could be enhanced with AJAX
            location.reload();
        },
        
        // Filter table
        filterTable: function() {
            const searchTerm = $('#search-analysis').val().toLowerCase();
            const menuFilter = $('#filter-menu').val();
            const statoFilter = $('#filter-stato').val();
            
            $('#analysis-tbody tr, #analysis-table tbody tr').each(function() {
                const $row = $(this);
                const text = $row.text().toLowerCase();
                const menu = $row.find('td:eq(3)').text().trim();
                const stato = $row.find('td:eq(7) .badge').text().trim();
                
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
            
            // Update count
            const visibleRows = $('#analysis-tbody tr:visible, #analysis-table tbody tr:visible').length;
            const totalRows = $('#analysis-tbody tr, #analysis-table tbody tr').length;
            
            this.log(`Filtro applicato: ${visibleRows} / ${totalRows} righe visibili`, this.debugLevels.INFO);
        },
        
        // Log message
        log: function(message, level = this.debugLevels.INFO) {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = `[${timestamp}] ${message}`;
            
            // Console log
            switch(level) {
                case this.debugLevels.ERROR:
                    console.error(logEntry);
                    break;
                case this.debugLevels.WARNING:
                    console.warn(logEntry);
                    break;
                case this.debugLevels.SUCCESS:
                case this.debugLevels.INFO:
                    console.log(logEntry);
                    break;
                case this.debugLevels.DEBUG:
                    console.debug(logEntry);
                    break;
            }
            
            // Add to UI log
            const $logContainer = $('#debug-log, #debug-log-content');
            if ($logContainer.length > 0) {
                const levelClass = level.replace('/', '-');
                const $logEntry = $(`<div class="log-entry ${levelClass}">${logEntry}</div>`);
                
                $logContainer.append($logEntry);
                
                // Auto-scroll if enabled
                if ($('#auto-scroll-log').is(':checked') !== false) {
                    $logContainer.scrollTop($logContainer[0].scrollHeight);
                }
                
                // Limit log entries to prevent memory issues
                const $entries = $logContainer.find('.log-entry');
                if ($entries.length > 1000) {
                    $entries.slice(0, $entries.length - 1000).remove();
                }
            }
        },
        
        // Clear debug log
        clearDebugLog: function() {
            $('#debug-log, #debug-log-content').html('<div class="log-entry info">[' + new Date().toLocaleTimeString() + '] Log cleared</div>');
            this.log('Log cleared', this.debugLevels.INFO);
        },
        
        // Download debug log
        downloadDebugLog: function() {
            const $log = $('#debug-log, #debug-log-content');
            const logContent = $log.text();
            
            const blob = new Blob([logContent], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            
            a.href = url;
            a.download = 'disco747-excel-scan-' + new Date().toISOString().slice(0, 10) + '.log';
            
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            
            window.URL.revokeObjectURL(url);
            
            this.log('Log scaricato', this.debugLevels.INFO);
        },
        
        // Save state to localStorage
        saveState: function() {
            const stateToSave = {
                currentIndex: this.state.currentIndex,
                totalFiles: this.state.totalFiles,
                successCount: this.state.successCount,
                errorCount: this.state.errorCount,
                skipCount: this.state.skipCount,
                files: this.state.files,
                results: this.state.results,
                startTime: this.state.startTime
            };
            
            try {
                localStorage.setItem('disco747_excel_scan_state', JSON.stringify(stateToSave));
            } catch(e) {
                this.log('Impossibile salvare lo stato: ' + e.message, this.debugLevels.WARNING);
            }
        },
        
        // Load stored state
        loadStoredState: function() {
            try {
                const savedState = localStorage.getItem('disco747_excel_scan_state');
                if (savedState) {
                    const parsedState = JSON.parse(savedState);
                    
                    // Merge with current state
                    $.extend(this.state, parsedState);
                    
                    this.log('Stato precedente caricato', this.debugLevels.INFO);
                }
            } catch(e) {
                this.log('Impossibile caricare lo stato: ' + e.message, this.debugLevels.WARNING);
            }
        },
        
        // Clear state
        clearState: function() {
            try {
                localStorage.removeItem('disco747_excel_scan_state');
            } catch(e) {
                // Ignore errors
            }
            
            // Reset state
            this.state = {
                isScanning: false,
                isPaused: false,
                currentIndex: 0,
                totalFiles: 0,
                successCount: 0,
                errorCount: 0,
                skipCount: 0,
                startTime: null,
                elapsedTimer: null,
                files: [],
                results: [],
                abortController: null
            };
        },
        
        // Show resume batch prompt
        showResumeBatchPrompt: function() {
            const remainingFiles = this.state.totalFiles - this.state.currentIndex;
            
            const message = `È stata trovata una scansione incompleta con ${remainingFiles} file rimanenti. Vuoi riprenderla?`;
            
            if (confirm(message)) {
                this.state.isScanning = true;
                this.state.isPaused = false;
                
                // Update UI
                $('#start-batch, #start-batch-analysis').hide();
                $('#stop-batch, #stop-batch-analysis').show();
                $('#batch-progress, .batch-progress-container').slideDown();
                
                this.startElapsedTimer();
                this.processNextFile();
                
                this.log('Scansione ripresa dal file ' + (this.state.currentIndex + 1), this.debugLevels.INFO);
            } else {
                this.clearState();
                this.log('Scansione precedente annullata', this.debugLevels.WARNING);
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        window.Disco747ExcelScan.init();
    });
    
})(jQuery);