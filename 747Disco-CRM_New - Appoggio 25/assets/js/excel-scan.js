/**
 * JavaScript per Scansione Excel Auto - 747 Disco CRM
 * 
 * Percorso: /assets/js/excel-scan.js
 * 
 * Gestisce l'interfaccia completa della pagina di scansione Excel con:
 * - Lista file da Google Drive con ricerca e paginazione
 * - Analisi file Excel con debug dettagliato
 * - Visualizzazione risultati e template detection
 * - Export dati e gestione log
 * 
 * @package    Disco747_CRM
 * @subpackage Assets/JS
 * @since      11.5.9-EXCEL-SCAN
 * @version    1.0.0
 */

(function($) {
    'use strict';

    /**
     * Oggetto principale per gestire la scansione Excel
     */
    const ExcelScanner = {
        
        // Variabili di stato
        currentPage: 1,
        totalPages: 1,
        currentSearch: '',
        isLoading: false,
        lastAnalysisData: null,
        analysisHistory: [],

        // Configurazione
        config: {
            maxRetries: 3,
            retryDelay: 1000,
            maxLogLines: 1000,
            autoRefreshInterval: 30000 // 30 secondi
        },

        /**
         * Inizializzazione completa
         */
        init: function() {
            this.log('Inizializzazione ExcelScanner v1.0.0');
            
            // Verifica requisiti
            if (!this.checkRequirements()) {
                return;
            }

            this.bindEvents();
            this.checkGDriveStatus();
            this.initUI();
            
            // Carica file se Google Drive disponibile
            if (window.disco747ExcelScanData?.gdriveAvailable) {
                this.loadFiles();
                this.startAutoRefresh();
            }

            this.log('ExcelScanner inizializzato con successo');
        },

        /**
         * Verifica requisiti minimi
         */
        checkRequirements: function() {
            if (typeof window.disco747ExcelScanData === 'undefined') {
                console.error('747 Disco Excel Scanner: Dati di configurazione mancanti');
                this.showError('Configurazione mancante. Ricarica la pagina.');
                return false;
            }

            if (typeof $ === 'undefined') {
                console.error('747 Disco Excel Scanner: jQuery non trovato');
                this.showError('jQuery richiesto per il funzionamento.');
                return false;
            }

            return true;
        },

        /**
         * Inizializza interfaccia utente
         */
        initUI: function() {
            // Imposta stato iniziale
            this.updateUIState();
            
            // Inizializza tooltip se disponibili
            if (typeof $.fn.tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }

            // Imposta focus iniziale
            if (window.disco747ExcelScanData?.gdriveAvailable) {
                $('#excel-search').focus();
            }
        },

        /**
         * Aggiorna stato UI basato su disponibilità Google Drive
         */
        updateUIState: function() {
            const available = window.disco747ExcelScanData?.gdriveAvailable;
            
            if (!available) {
                $('#excel-search, #manual-file-id').prop('disabled', true);
                $('button[id*="btn"]:not(#refresh-all-btn)').prop('disabled', true);
                $('#files-count').text('N/D - Google Drive non configurato');
            }
        },

        /**
         * Binding eventi UI completo
         */
        bindEvents: function() {
            // Pulsanti principali
            $('#search-files-btn').on('click', () => this.searchFiles());
            $('#refresh-files-btn').on('click', () => this.refreshFiles());
            $('#refresh-all-btn').on('click', () => this.refreshAll());
            $('#analyze-manual-btn').on('click', () => this.analyzeManualId());
            $('#clear-results-btn').on('click', () => this.clearResults());
            
            // Gestione log
            $('#toggle-log-btn').on('click', () => this.toggleLog());
            $('#copy-log-btn').on('click', () => this.copyLogToClipboard());
            $('#download-log-btn').on('click', () => this.downloadLog());
            
            // Export risultati
            $('#export-results-btn').on('click', () => this.exportResults());
            
            // Paginazione
            $('#prev-page-btn').on('click', () => this.prevPage());
            $('#next-page-btn').on('click', () => this.nextPage());
            
            // Gestione input con Enter
            $('#excel-search').on('keypress', (e) => {
                if (e.which === 13) this.searchFiles();
            });

            $('#manual-file-id').on('keypress', (e) => {
                if (e.which === 13) this.analyzeManualId();
            });

            // Input validation real-time
            $('#manual-file-id').on('input', (e) => {
                this.validateFileId($(e.target).val());
            });

            // Auto-save ricerca
            $('#excel-search').on('input', debounce(() => {
                localStorage.setItem('disco747_excel_search', $('#excel-search').val());
            }, 500));

            // Ripristina ricerca salvata
            const savedSearch = localStorage.getItem('disco747_excel_search');
            if (savedSearch) {
                $('#excel-search').val(savedSearch);
            }

            // Gestione click alert per chiuderli
            $(document).on('click', '.alert', function() {
                $(this).fadeOut(() => $(this).remove());
            });

            // Gestione resize per responsive
            $(window).on('resize', debounce(() => {
                this.handleResize();
            }, 250));

            // Gestione visibilità pagina per stop/resume auto-refresh
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.stopAutoRefresh();
                } else {
                    this.startAutoRefresh();
                }
            });

            this.log('Eventi UI collegati');
        },

        /**
         * Validazione File ID in tempo reale
         */
        validateFileId: function(fileId) {
            const btn = $('#analyze-manual-btn');
            const input = $('#manual-file-id');
            
            if (!fileId || fileId.length < 10) {
                btn.prop('disabled', true);
                input.removeClass('valid').addClass('invalid');
                return false;
            }
            
            // Pattern Google Drive File ID (circa 28-44 caratteri alfanumerici)
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

        /**
         * Verifica status Google Drive con retry
         */
        checkGDriveStatus: function() {
            const statusEl = $('#gdrive-status');
            statusEl.removeClass('connected error').addClass('checking').text('Verificando connessione...');
            
            // Simula check con possibilità di implementare vera verifica AJAX
            this.makeRequest('disco747_test_storage', {}, {
                timeout: 10000,
                success: (response) => {
                    if (response.success) {
                        statusEl.removeClass('checking').addClass('connected').text('Connesso e operativo');
                        this.log('Google Drive: Connessione verificata');
                    } else {
                        statusEl.removeClass('checking').addClass('error').text('Errore connessione');
                        this.log('Google Drive: Errore verifica - ' + (response.data || 'Unknown'), 'error');
                    }
                },
                error: () => {
                    // Fallback basato su configurazione
                    if (window.disco747ExcelScanData?.gdriveAvailable) {
                        statusEl.removeClass('checking').addClass('connected').text('Configurato');
                    } else {
                        statusEl.removeClass('checking').addClass('error').text('Non configurato');
                    }
                }
            });
        },

        /**
         * Refresh completo di tutto
         */
        refreshAll: function() {
            this.log('Refresh completo richiesto');
            this.clearResults();
            this.checkGDriveStatus();
            this.currentSearch = '';
            this.currentPage = 1;
            $('#excel-search').val('');
            localStorage.removeItem('disco747_excel_search');
            this.loadFiles();
        },

        /**
         * Avvia ricerca file
         */
        searchFiles: function() {
            const searchTerm = $('#excel-search').val().trim();
            this.log(`Ricerca file: "${searchTerm}"`);
            
            this.currentSearch = searchTerm;
            this.currentPage = 1;
            this.loadFiles();
        },

        /**
         * Refresh lista file
         */
        refreshFiles: function() {
            this.log('Refresh lista file');
            this.currentPage = 1;
            this.loadFiles();
        },

        /**
         * Carica lista file Excel con gestione errori avanzata
         */
        loadFiles: function(retryCount = 0) {
            if (this.isLoading) {
                this.log('Caricamento già in corso, skip');
                return;
            }
            
            if (!window.disco747ExcelScanData?.gdriveAvailable) {
                this.log('Google Drive non disponibile, skip caricamento file');
                return;
            }
            
            this.isLoading = true;
            const container = $('#excel-files-container');
            
            // Mostra loading con animazione
            container.html(this.getLoadingHTML('Scansione Google Drive in corso...'));
            this.log(`Caricamento file - pagina ${this.currentPage}, ricerca: "${this.currentSearch}"`);

            this.makeRequest('disco747_list_excel_files', {
                search: this.currentSearch,
                page: this.currentPage
            }, {
                success: (response) => {
                    this.isLoading = false;
                    
                    if (response.success) {
                        this.renderFilesList(response.data.files, response.data.total);
                        this.updatePagination(response.data.page, response.data.total_pages, response.data.total);
                        this.log(`File caricati: ${response.data.files.length} di ${response.data.total}`);
                    } else {
                        this.handleLoadError(response.data || 'Errore sconosciuto caricamento file');
                    }
                },
                error: (xhr, status, error) => {
                    this.isLoading = false;
                    
                    // Retry logic
                    if (retryCount < this.config.maxRetries && status !== 'abort') {
                        this.log(`Retry caricamento file (tentativo ${retryCount + 1}/${this.config.maxRetries})`);
                        
                        setTimeout(() => {
                            this.loadFiles(retryCount + 1);
                        }, this.config.retryDelay * (retryCount + 1));
                        
                        return;
                    }
                    
                    this.handleLoadError(`Errore connessione: ${error} (${status})`);
                }
            });
        },

        /**
         * Gestisce errori di caricamento con UI migliorata
         */
        handleLoadError: function(errorMsg) {
            const container = $('#excel-files-container');
            
            container.html(`
                <div class="loading-message error-state">
                    <div class="error-icon">⚠️</div>
                    <p class="error-title">Errore Caricamento File</p>
                    <p class="error-message">${this.escapeHtml(errorMsg)}</p>
                    <div class="error-actions">
                        <button type="button" class="disco747-btn disco747-btn-secondary" onclick="ExcelScanner.refreshFiles()">
                            🔄 Riprova
                        </button>
                        <button type="button" class="disco747-btn disco747-btn-small" onclick="ExcelScanner.checkGDriveStatus()">
                            🔧 Verifica Connessione
                        </button>
                    </div>
                </div>
            `);
            
            this.showError(errorMsg);
            this.log(`Errore caricamento file: ${errorMsg}`, 'error');
        },

        /**
         * Renderizza lista file con funzionalità avanzate
         */
        renderFilesList: function(files, total) {
            const container = $('#excel-files-container');
            
            if (!files || files.length === 0) {
                container.html(this.getEmptyStateHTML());
                $('#files-count').text('0');
                return;
            }

            let html = '<div class="excel-files-list">';
            files.forEach((file, index) => {
                html += this.renderFileItem(file, index);
            });
            html += '</div>';
            
            container.html(html);
            $('#files-count').text(total);

            // Bind eventi per pulsanti analisi con throttling
            $('.analyze-file-btn').on('click', throttle((e) => {
                const fileId = $(e.target).data('file-id');
                const fileName = $(e.target).data('file-name');
                this.analyzeFile(fileId, fileName);
            }, 1000));

            this.log(`Lista file renderizzata: ${files.length} file`);
        },

        /**
         * Renderizza singolo file con informazioni dettagliate
         */
        renderFileItem: function(file, index) {
            const sizeClass = this.getFileSizeClass(file.size);
            const modifiedClass = this.getModifiedTimeClass(file.modified);
            
            return `
                <div class="file-item" data-file-id="${file.id}">
                    <div class="file-info">
                        <div class="file-name">
                            📄 ${this.escapeHtml(file.name)}
                            <span class="file-index">#${index + 1}</span>
                        </div>
                        <div class="file-details">
                            <span class="detail-item">
                                📁 <span class="path">${this.escapeHtml(file.path)}</span>
                            </span>
                            <span class="detail-item ${modifiedClass}">
                                📅 <span class="modified">${this.escapeHtml(file.modified)}</span>
                            </span>
                            <span class="detail-item ${sizeClass}">
                                📊 <span class="size">${this.escapeHtml(file.size)}</span>
                            </span>
                        </div>
                    </div>
                    <div class="file-actions">
                        <button type="button" 
                                class="disco747-btn disco747-btn-primary analyze-file-btn" 
                                data-file-id="${file.id}"
                                data-file-name="${this.escapeHtml(file.name)}"
                                title="Analizza file Excel">
                            🔍 Analizza
                        </button>
                    </div>
                </div>
            `;
        },

        /**
         * Determina classe CSS per dimensione file
         */
        getFileSizeClass: function(sizeString) {
            if (sizeString.includes('MB')) {
                const size = parseFloat(sizeString);
                if (size > 10) return 'size-large';
                if (size > 1) return 'size-medium';
            }
            return 'size-small';
        },

        /**
         * Determina classe CSS per data modifica
         */
        getModifiedTimeClass: function(modifiedString) {
            if (modifiedString === 'N/D') return 'modified-unknown';
            
            try {
                const parts = modifiedString.split(/[/ :]/);
                if (parts.length >= 3) {
                    const fileDate = new Date(parts[2], parts[1] - 1, parts[0]);
                    const now = new Date();
                    const diffDays = Math.floor((now - fileDate) / (1000 * 60 * 60 * 24));
                    
                    if (diffDays <= 7) return 'modified-recent';
                    if (diffDays <= 30) return 'modified-medium';
                    return 'modified-old';
                }
            } catch (e) {
                return 'modified-unknown';
            }
            
            return 'modified-unknown';
        },

        /**
         * HTML per stato loading avanzato
         */
        getLoadingHTML: function(message = 'Caricamento...') {
            return `
                <div class="loading-message">
                    <div class="disco747-spinner"></div>
                    <p class="loading-text">${message}</p>
                    <div class="loading-progress">
                        <div class="progress-bar"></div>
                    </div>
                </div>
            `;
        },

        /**
         * HTML per stato vuoto migliorato
         */
        getEmptyStateHTML: function() {
            const hasSearch = this.currentSearch.trim().length > 0;
            
            return `
                <div class="loading-message empty-state">
                    <div class="empty-icon">📂</div>
                    <p class="empty-title">
                        ${hasSearch ? 'Nessun risultato trovato' : 'Nessun file Excel trovato'}
                    </p>
                    <p class="empty-subtitle">
                        ${hasSearch 
                            ? `Nessun file corrisponde alla ricerca "${this.escapeHtml(this.currentSearch)}"` 
                            : 'La cartella Google Drive 747-Preventivi sembra vuota'
                        }
                    </p>
                    <div class="empty-actions">
                        ${hasSearch ? `
                            <button type="button" class="disco747-btn disco747-btn-secondary" onclick="ExcelScanner.refreshAll()">
                                🔄 Mostra tutti i file
                            </button>
                        ` : `
                            <button type="button" class="disco747-btn disco747-btn-secondary" onclick="ExcelScanner.refreshFiles()">
                                🔄 Ricarica
                            </button>
                        `}
                    </div>
                </div>
            `;
        },

        /**
         * Aggiorna paginazione con info dettagliate
         */
        updatePagination: function(page, totalPgs, totalFiles) {
            this.currentPage = page;
            this.totalPages = totalPgs;

            const startItem = ((page - 1) * 10) + 1;
            const endItem = Math.min(page * 10, totalFiles);

            $('#page-info').html(`
                Pagina <strong>${page}</strong> di <strong>${totalPgs}</strong>
                <br><small>Elementi ${startItem}-${endItem} di ${totalFiles}</small>
            `);

            $('#prev-page-btn').prop('disabled', page <= 1);
            $('#next-page-btn').prop('disabled', page >= totalPgs);
            
            const paginationContainer = $('#files-pagination');
            if (totalPgs > 1) {
                paginationContainer.show();
                this.log(`Paginazione aggiornata: pagina ${page}/${totalPgs}`);
            } else {
                paginationContainer.hide();
            }
        },

        /**
         * Pagina precedente
         */
        prevPage: function() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.loadFiles();
                this.log(`Pagina precedente: ${this.currentPage}`);
            }
        },

        /**
         * Pagina successiva  
         */
        nextPage: function() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.loadFiles();
                this.log(`Pagina successiva: ${this.currentPage}`);
            }
        },

        /**
         * Analizza file da input manuale con validazione
         */
        analyzeManualId: function() {
            const fileId = $('#manual-file-id').val().trim();
            
            if (!this.validateFileId(fileId)) {
                this.showError(window.disco747ExcelScanData?.strings?.noFileId || 'Inserisci un File ID valido');
                $('#manual-file-id').focus().select();
                return;
            }
            
            this.log(`Analisi manuale richiesta per File ID: ${fileId}`);
            this.analyzeFile(fileId, `Manual ID: ${fileId.substring(0, 8)}...`);
        },

        /**
         * Analizza file Excel con gestione completa errori e progress
         */
        analyzeFile: function(fileId, fileName = '') {
            // Validazione input
            if (!fileId || !fileId.trim()) {
                this.showError('File ID non valido');
                return;
            }

            const trimmedId = fileId.trim();
            
            // Check se già in corso
            if (this.isLoading) {
                this.showWarning('Un\'analisi è già in corso. Attendi il completamento.');
                return;
            }

            // UI feedback con progress
            this.showInfo(`Analisi in corso per ${fileName || 'file selezionato'}...`);
            this.clearResults();
            this.setAnalysisState(true);
            
            // Aggiungi alla cronologia
            this.analysisHistory.unshift({
                fileId: trimmedId,
                fileName: fileName,
                timestamp: new Date(),
                status: 'in_progress'
            });

            this.log(`Inizio analisi file: ${fileName} (${trimmedId})`);

            this.makeRequest('disco747_analyze_excel_file', {
                file_id: trimmedId
            }, {
                timeout: 120000, // 2 minuti per file grandi
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(`Eccellente! File analizzato correttamente: ${fileName || 'File Excel'}`);
                        this.renderAnalysisResults(response.data.data, response.data.log, fileName);
                        this.lastAnalysisData = {
                            data: response.data.data,
                            log: response.data.log,
                            fileName: fileName,
                            fileId: trimmedId,
                            timestamp: new Date()
                        };
                        
                        // Aggiorna cronologia
                        this.analysisHistory[0].status = 'success';
                        this.log(`Analisi completata con successo per: ${fileName}`);
                        
                        // Clear input manuale dopo successo
                        $('#manual-file-id').val('');
                        
                    } else {
                        const errorMsg = response.data?.message || 'Errore sconosciuto durante analisi';
                        this.showError(`Errore analisi: ${errorMsg}`);
                        
                        if (response.data?.log) {
                            this.renderDebugLog(response.data.log);
                        }
                        
                        this.analysisHistory[0].status = 'error';
                        this.analysisHistory[0].error = errorMsg;
                        this.log(`Analisi fallita: ${errorMsg}`, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    const errorMsg = `Errore di connessione durante analisi: ${error} (${status})`;
                    this.showError(errorMsg);
                    
                    this.analysisHistory[0].status = 'error';
                    this.analysisHistory[0].error = errorMsg;
                    this.log(`Errore di rete durante analisi: ${errorMsg}`, 'error');
                    
                    console.error('Analysis AJAX Error:', {
                        status: xhr.status,
                        responseText: xhr.responseText,
                        error: error
                    });
                }
            }).always(() => {
                this.setAnalysisState(false);
            });
        },

        /**
         * Imposta stato UI durante analisi
         */
        setAnalysisState: function(isAnalyzing) {
            const analyzeText = isAnalyzing ? '🔄 Analizzando...' : '🔍 Analizza';
            const manualText = isAnalyzing ? '🔄 Analizzando...' : '🔍 Analizza File ID';
            
            $('.analyze-file-btn').prop('disabled', isAnalyzing).text(analyzeText);
            $('#analyze-manual-btn').prop('disabled', isAnalyzing).text(manualText);
            
            // Disabilita anche altri controlli durante analisi
            if (isAnalyzing) {
                $('#excel-search, #search-files-btn, #refresh-files-btn').prop('disabled', true);
            } else {
                if (window.disco747ExcelScanData?.gdriveAvailable) {
                    $('#excel-search, #search-files-btn, #refresh-files-btn').prop('disabled', false);
                }
            }
        },

        /**
         * Renderizza risultati analisi con dashboard avanzata
         */
        renderAnalysisResults: function(data, log, fileName = '') {
            const dashboard = $('#extracted-data-dashboard');
            const summary = $('#analysis-summary');
            
            let html = '<div class="data-dashboard">';
            
            // Header risultati
            if (fileName) {
                html += `
                    <div class="analysis-header" colspan="100%">
                        <h3>📄 ${this.escapeHtml(fileName)}</h3>
                        <small>Analizzato il ${new Date().toLocaleString('it-IT')}</small>
                    </div>
                `;
            }

            // Badge template con info
            if (data.template) {
                const templateClass = data.template === 'nuovo' ? 'template-nuovo' : 'template-vecchio';
                const templateIcon = data.template === 'nuovo' ? '🆕' : '📰';
                
                html += this.renderDataItem(
                    `${templateIcon} Template`,
                    `${data.template.toUpperCase()}<span class="template-badge ${templateClass}">${data.template.toUpperCase()}</span>`,
                    'template'
                );
            }

            // Campi dati principali
            const fields = [
                { key: 'menu', label: 'Menu', icon: '🍽️', type: 'menu' },
                { key: 'data_evento', label: 'Data Evento', icon: '📅', type: 'date' },
                { key: 'tipo_evento', label: 'Tipo Evento', icon: '🎉', type: 'text' },
                { key: 'numero_invitati', label: 'N° Invitati', icon: '👥', type: 'number' },
                { key: 'importo_totale', label: 'Importo Totale', icon: '💰', type: 'currency' },
                { key: 'acconto', label: 'Acconto', icon: '💳', type: 'currency' }
            ];

            fields.forEach(field => {
                if (data.hasOwnProperty(field.key)) {
                    let value = data[field.key];
                    const formattedValue = this.formatFieldValue(value, field.type);
                    
                    html += this.renderDataItem(
                        `${field.icon} ${field.label}`,
                        formattedValue,
                        field.type,
                        value
                    );
                }
            });

            html += '</div>';
            dashboard.html(html);
            
            // Riassunto analisi
            this.renderAnalysisSummary(data, summary);
            
            // Mostra sezioni risultati
            $('#analysis-results').show();
            
            // Mostra log se fornito
            if (log && log.length > 0) {
                this.renderDebugLog(log);
            }

            // Scroll verso risultati
            this.scrollToResults();
            
            this.log('Risultati analisi renderizzati');
        },

        /**
         * Formatta valore campo in base al tipo
         */
        formatFieldValue: function(value, type) {
            if (!value || value === '' || value === 'Non specificato') {
                return '<span class="value-empty">Non specificato</span>';
            }

            switch (type) {
                case 'currency':
                    const numValue = parseFloat(value);
                    if (!isNaN(numValue)) {
                        return `<span class="value-currency">€ ${numValue.toLocaleString('it-IT', {minimumFractionDigits: 2})}</span>`;
                    }
                    break;
                    
                case 'number':
                    const num = parseInt(value);
                    if (!isNaN(num)) {
                        return `<span class="value-number">${num.toLocaleString('it-IT')}</span>`;
                    }
                    break;
                    
                case 'date':
                    return `<span class="value-date">${this.escapeHtml(value)}</span>`;
                    
                case 'menu':
                    return `<span class="value-menu">${this.escapeHtml(value)}</span>`;
                    
                case 'template':
                    return value; // già formattato
                    
                default:
                    return `<span class="value-text">${this.escapeHtml(value)}</span>`;
            }
            
            return `<span class="value-text">${this.escapeHtml(value)}</span>`;
        },

        /**
         * Renderizza singolo dato con styling avanzato
         */
        renderDataItem: function(label, value, type = 'text', rawValue = null) {
            const hasValue = rawValue !== null && rawValue !== '' && rawValue !== 'Non specificato';
            const itemClass = hasValue ? 'has-value' : 'empty-value';
            
            return `
                <div class="data-item ${itemClass}" data-type="${type}">
                    <div class="data-label">${label}</div>
                    <div class="data-value">${value}</div>
                </div>
            `;
        },

        /**
         * Renderizza riassunto analisi
         */
        renderAnalysisSummary: function(data, summaryContainer) {
            const validFields = Object.keys(data).filter(key => 
                data[key] && data[key] !== '' && data[key] !== 'Non specificato'
            );
            
            const completeness = Math.round((validFields.length / 6) * 100); // 6 campi principali
            
            let summaryHtml = `
                <div class="summary-stats">
                    <div class="stat-item">
                        <span class="stat-label">Campi compilati:</span>
                        <span class="stat-value">${validFields.length}/6</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Completezza:</span>
                        <span class="stat-value completeness-${this.getCompletenessClass(completeness)}">${completeness}%</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Template:</span>
                        <span class="stat-value">${data.template || 'Sconosciuto'}</span>
                    </div>
                </div>
            `;
            
            // Suggerimenti miglioramento
            const suggestions = this.generateSuggestions(data);
            if (suggestions.length > 0) {
                summaryHtml += `
                    <div class="suggestions">
                        <h4>💡 Suggerimenti per migliorare i dati:</h4>
                        <ul>${suggestions.map(s => `<li>${s}</li>`).join('')}</ul>
                    </div>
                `;
            }
            
            summaryContainer.html(summaryHtml).show();
        },

        /**
         * Genera suggerimenti basati sui dati
         */
        generateSuggestions: function(data) {
            const suggestions = [];
            
            if (!data.data_evento || data.data_evento === 'Non specificato') {
                suggestions.push('Specifica la data dell\'evento nel file Excel');
            }
            
            if (!data.importo_totale || data.importo_totale === '0') {
                suggestions.push('Inserisci l\'importo totale del preventivo');
            }
            
            if (!data.numero_invitati || data.numero_invitati === '0') {
                suggestions.push('Indica il numero di invitati previsti');
            }
            
            if (data.importo_totale && data.acconto) {
                const totale = parseFloat(data.importo_totale);
                const acconto = parseFloat(data.acconto);
                if (!isNaN(totale) && !isNaN(acconto) && acconto > totale) {
                    suggestions.push('L\'acconto non può essere superiore al totale');
                }
            }
            
            return suggestions;
        },

        /**
         * Determina classe completezza
         */
        getCompletenessClass: function(percentage) {
            if (percentage >= 80) return 'high';
            if (percentage >= 50) return 'medium';
            return 'low';
        },

        /**
         * Renderizza log debug con funzionalità avanzate
         */
        renderDebugLog: function(log) {
            const logContent = $('#debug-log-content');
            const logStats = $('#log-stats');
            
            let logText = '';
            if (Array.isArray(log)) {
                logText = log.join('\n');
            } else {
                logText = String(log);
            }
            
            // Limita righe log per performance
            const lines = logText.split('\n');
            if (lines.length > this.config.maxLogLines) {
                const truncated = lines.slice(-this.config.maxLogLines);
                logText = `... (Mostrate ultime ${this.config.maxLogLines} righe di ${lines.length})\n\n` + 
                         truncated.join('\n');
            }
            
            logContent.text(logText);
            
            // Statistiche log
            const stats = this.analyzeLogStats(logText);
            logStats.show();
            $('#log-lines-count').text(lines.length);
            $('#log-errors-count').text(stats.errors);
            $('#log-warnings-count').text(stats.warnings);
            
            $('#debug-log-section').show();
            
            this.log(`Log debug renderizzato: ${lines.length} righe`);
        },

        /**
         * Analizza statistiche log
         */
        analyzeLogStats: function(logText) {
            const lines = logText.split('\n');
            let errors = 0, warnings = 0;
            
            lines.forEach(line => {
                if (line.includes('ERROR') || line.includes('EXCEPTION')) errors++;
                if (line.includes('WARNING') || line.includes('HINT')) warnings++;
            });
            
            return { errors, warnings, lines: lines.length };
        },

        /**
         * Export risultati in JSON
         */
        exportResults: function() {
            if (!this.lastAnalysisData) {
                this.showWarning('Nessun dato di analisi da esportare');
                return;
            }
            
            const exportData = {
                metadata: {
                    exportedAt: new Date().toISOString(),
                    pluginVersion: window.disco747ExcelScanData?.pluginVersion || '1.0.0',
                    fileName: this.lastAnalysisData.fileName,
                    fileId: this.lastAnalysisData.fileId,
                    analysisTimestamp: this.lastAnalysisData.timestamp
                },
                extractedData: this.lastAnalysisData.data,
                debugLog: this.lastAnalysisData.log
            };
            
            const jsonString = JSON.stringify(exportData, null, 2);
            const blob = new Blob([jsonString], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `disco747_analisi_${this.sanitizeFilename(this.lastAnalysisData.fileName)}_${Date.now()}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            this.showSuccess('Dati esportati con successo');
            this.log('Export risultati completato');
        },

        /**
         * Sanitizza nome file per download
         */
        sanitizeFilename: function(filename) {
            return filename.replace(/[^a-zA-Z0-9-_.]/g, '_').substring(0, 50);
        },

        /**
         * Pulisce risultati analisi
         */
        clearResults: function() {
            $('#analysis-results').hide();
            $('#debug-log-section').hide();
            $('#extracted-data-dashboard').empty();
            $('#debug-log-content').empty();
            $('#analysis-summary').hide();
            $('.log-stats').hide();
            this.clearAlerts();
            this.lastAnalysisData = null;
            this.log('Risultati puliti');
        },

        /**
         * Toggle visibilità log
         */
        toggleLog: function() {
            const logContent = $('#debug-log-content');
            const toggleBtn = $('#toggle-log-btn');
            
            logContent.toggle();
            const isVisible = logContent.is(':visible');
            toggleBtn.text(isVisible ? '👁️ Nascondi' : '👁️ Mostra');
            
            this.log(`Log debug ${isVisible ? 'mostrato' : 'nascosto'}`);
        },

        /**
         * Copia log negli appunti
         */
        copyLogToClipboard: function() {
            const logContent = $('#debug-log-content').text();
            
            if (!logContent) {
                this.showWarning('Nessun log da copiare');
                return;
            }
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(logContent).then(() => {
                    this.showSuccess('Log copiato negli appunti');
                    this.log('Log copiato negli appunti');
                }).catch(() => {
                    this.fallbackCopyToClipboard(logContent);
                });
            } else {
                this.fallbackCopyToClipboard(logContent);
            }
        },

        /**
         * Fallback per copia negli appunti
         */
        fallbackCopyToClipboard: function(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showSuccess('Log copiato negli appunti');
                this.log('Log copiato negli appunti (fallback)');
            } catch (e) {
                this.showError('Impossibile copiare automaticamente. Seleziona e copia manualmente.');
            }
            
            document.body.removeChild(textArea);
        },

        /**
         * Download log come file
         */
        downloadLog: function() {
            const logContent = $('#debug-log-content').text();
            
            if (!logContent) {
                this.showWarning('Nessun log da scaricare');
                return;
            }
            
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const filename = `disco747_debug_log_${timestamp}.txt`;
            
            const blob = new Blob([logContent], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            this.showSuccess('Log scaricato con successo');
            this.log('Download log completato');
        },

        /**
         * Scroll verso i risultati
         */
        scrollToResults: function() {
            const resultsEl = $('#analysis-results');
            if (resultsEl.length && resultsEl.is(':visible')) {
                $('html, body').animate({
                    scrollTop: resultsEl.offset().top - 20
                }, 500);
            }
        },

        /**
         * Gestione resize per responsive
         */
        handleResize: function() {
            // Adatta layout per mobile
            const isMobile = $(window).width() < 768;
            
            if (isMobile) {
                $('.data-dashboard').addClass('mobile-layout');
            } else {
                $('.data-dashboard').removeClass('mobile-layout');
            }
        },

        /**
         * Auto-refresh lista file
         */
        startAutoRefresh: function() {
            if (this.autoRefreshInterval) {
                clearInterval(this.autoRefreshInterval);
            }
            
            this.autoRefreshInterval = setInterval(() => {
                if (!document.hidden && !this.isLoading && window.disco747ExcelScanData?.gdriveAvailable) {
                    this.log('Auto-refresh lista file');
                    this.loadFiles();
                }
            }, this.config.autoRefreshInterval);
        },

        /**
         * Stop auto-refresh
         */
        stopAutoRefresh: function() {
            if (this.autoRefreshInterval) {
                clearInterval(this.autoRefreshInterval);
                this.autoRefreshInterval = null;
                this.log('Auto-refresh fermato');
            }
        },

        /**
         * Helper per richieste AJAX con retry e timeout
         */
        makeRequest: function(action, data, options = {}) {
            const defaultOptions = {
                timeout: 30000,
                retries: 0,
                retryDelay: 1000
            };
            
            const opts = $.extend({}, defaultOptions, options);
            
            const requestData = $.extend({
                action: action,
                nonce: window.disco747ExcelScanData?.nonce
            }, data);

            return $.post({
                url: window.disco747ExcelScanData?.ajaxurl,
                data: requestData,
                timeout: opts.timeout
            }).done(opts.success || function() {})
            .fail(opts.error || function() {});
        },

        /**
         * Sistema di alert migliorato
         */
        showAlert: function(message, type, icon = '', duration = 5000) {
            const alertsContainer = $('#disco747-alerts');
            const alertId = 'alert-' + Date.now();
            
            const alert = $(`
                <div id="${alertId}" class="alert alert-${type}" style="display: none;">
                    <div class="alert-content">
                        <span class="alert-icon">${icon}</span>
                        <span class="alert-message">${this.escapeHtml(message)}</span>
                        <button type="button" class="alert-close" onclick="$('#${alertId}').fadeOut(() => $('#${alertId}').remove())" aria-label="Chiudi">×</button>
                    </div>
                </div>
            `);
            
            alertsContainer.append(alert);
            alert.slideDown();
            
            // Auto-remove
            if (duration > 0) {
                setTimeout(() => {
                    alert.slideUp(() => alert.remove());
                }, duration);
            }
            
            return alertId;
        },

        showSuccess: function(message, duration = 5000) {
            return this.showAlert(message, 'success', '✅', duration);
        },

        showError: function(message, duration = 8000) {
            return this.showAlert(message, 'error', '❌', duration);
        },

        showInfo: function(message, duration = 4000) {
            return this.showAlert(message, 'info', 'ℹ️', duration);
        },

        showWarning: function(message, duration = 6000) {
            return this.showAlert(message, 'warning', '⚠️', duration);
        },

        /**
         * Pulisce tutti gli alert
         */
        clearAlerts: function() {
            $('#disco747-alerts').empty();
        },

        /**
         * Escape HTML per sicurezza
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Logging interno con livelli
         */
        log: function(message, level = 'info') {
            const timestamp = new Date().toLocaleTimeString('it-IT');
            const logMessage = `[${timestamp}] [ExcelScanner] ${message}`;
            
            switch (level.toLowerCase()) {
                case 'error':
                    console.error(logMessage);
                    break;
                case 'warn':
                case 'warning':
                    console.warn(logMessage);
                    break;
                default:
                    console.log(logMessage);
            }
        }
    };

    // ========================================================================
    // UTILITY FUNCTIONS
    // ========================================================================

    /**
     * Debounce function per limitare chiamate frequenti
     */
    function debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    /**
     * Throttle function per limitare frequenza esecuzione
     */
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // ========================================================================
    // INIZIALIZZAZIONE GLOBALE
    // ========================================================================

    // Espone oggetto globalmente per debug e accesso esterno
    window.ExcelScanner = ExcelScanner;

    // Inizializzazione quando DOM è pronto
    $(document).ready(function() {
        // Verifica che le variabili globali siano disponibili
        if (typeof window.disco747ExcelScanData === 'undefined') {
            console.error('747 Disco Excel Scanner: Variabili di configurazione non trovate');
            $('#disco747-alerts').html(
                '<div class="alert alert-error">❌ Errore di configurazione. Ricarica la pagina.</div>'
            );
            return;
        }

        // Inizializza l'applicazione
        ExcelScanner.init();

        // Log di inizializzazione
        console.log('🎵 747 Disco Excel Scanner inizializzato', {
            version: '1.0.0',
            gdriveAvailable: window.disco747ExcelScanData?.gdriveAvailable,
            pluginVersion: window.disco747ExcelScanData?.pluginVersion,
            ajaxurl: window.disco747ExcelScanData?.ajaxurl,
            timestamp: new Date().toISOString()
        });
    });

})(jQuery);