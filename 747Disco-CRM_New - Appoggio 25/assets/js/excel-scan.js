/**
 * Excel Scan functionality for Disco747 CRM
 */

(function($) {
    'use strict';
    
    // Check if we have the required data
    if (typeof disco747ExcelScanData === 'undefined') {
        console.error('disco747ExcelScanData not found. Make sure the script is properly localized.');
        return;
    }
    
    const ExcelScan = {
        
        // Configuration
        config: {
            ajaxUrl: disco747ExcelScanData.ajaxurl,
            nonce: disco747ExcelScanData.nonce,
            scanAllButtonId: '#disco747-scan-all-btn',
            scanSingleButtonClass: '.disco747-scan-single-btn',
            progressContainerId: '#disco747-scan-progress',
            resultsContainerId: '#disco747-scan-results',
            loadingClass: 'disco747-loading',
            processingClass: 'disco747-processing'
        },
        
        // Initialize the functionality
        init: function() {
            this.bindEvents();
            this.setupProgressContainer();
        },
        
        // Bind event handlers
        bindEvents: function() {
            // Scan All button
            $(document).on('click', this.config.scanAllButtonId, this.handleScanAll.bind(this));
            
            // Scan Single buttons
            $(document).on('click', this.config.scanSingleButtonClass, this.handleScanSingle.bind(this));
            
            // Dry run toggle if available
            $(document).on('change', '#disco747-dry-run-toggle', this.handleDryRunToggle.bind(this));
        },
        
        // Setup progress container
        setupProgressContainer: function() {
            if ($(this.config.progressContainerId).length === 0) {
                // Create progress container if it doesn't exist
                const progressHtml = `
                    <div id="disco747-scan-progress" class="disco747-progress-container" style="display: none;">
                        <div class="disco747-progress-bar">
                            <div class="disco747-progress-fill"></div>
                        </div>
                        <div class="disco747-progress-text">Scanning...</div>
                        <div class="disco747-progress-details"></div>
                    </div>
                `;
                
                // Insert before results container or at the top of the page
                if ($(this.config.resultsContainerId).length > 0) {
                    $(this.config.resultsContainerId).before(progressHtml);
                } else {
                    $('.wrap').prepend(progressHtml);
                }
            }
        },
        
        // Handle Scan All button click
        handleScanAll: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            
            if ($button.hasClass(this.config.processingClass)) {
                return; // Already processing
            }
            
            this.startScan($button, {
                action: 'disco747_batch_scan_excel',
                nonce: this.config.nonce,
                dry_run: this.isDryRun() ? 1 : 0
            });
        },
        
        // Handle Scan Single button click
        handleScanSingle: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const fileId = $button.data('file-id');
            
            if (!fileId) {
                this.showError('File ID not found');
                return;
            }
            
            if ($button.hasClass(this.config.processingClass)) {
                return; // Already processing
            }
            
            this.startScan($button, {
                action: 'disco747_batch_scan_excel',
                nonce: this.config.nonce,
                dry_run: this.isDryRun() ? 1 : 0,
                file_id: fileId
            });
        },
        
        // Start the scanning process
        startScan: function($button, data) {
            // Disable button and show loading state
            $button.addClass(this.config.processingClass + ' ' + this.config.loadingClass);
            $button.prop('disabled', true);
            
            const originalText = $button.text();
            $button.text(data.file_id ? 'Scanning File...' : 'Scanning All Files...');
            
            // Show progress container
            this.showProgress();
            this.updateProgress('Starting scan...', 0);
            
            // Start the AJAX request
            const startTime = Date.now();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                timeout: 300000, // 5 minutes timeout
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    // Add progress tracking if supported
                    xhr.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            ExcelScan.updateProgress('Processing...', percentComplete);
                        }
                    }, false);
                    return xhr;
                }
            })
            .done(this.handleScanSuccess.bind(this, $button, originalText, startTime))
            .fail(this.handleScanError.bind(this, $button, originalText))
            .always(function() {
                // Re-enable button
                $button.removeClass(ExcelScan.config.processingClass + ' ' + ExcelScan.config.loadingClass);
                $button.prop('disabled', false);
                $button.text(originalText);
            });
        },
        
        // Handle successful scan response
        handleScanSuccess: function($button, originalText, startTime, response) {
            const duration = ((Date.now() - startTime) / 1000).toFixed(1);
            
            try {
                let data;
                if (typeof response === 'string') {
                    data = JSON.parse(response);
                } else {
                    data = response;
                }
                
                if (data.success) {
                    const counters = data.counters || {};
                    const details = data.details || [];
                    
                    // Update progress with results
                    const resultText = this.formatScanResults(counters, details, duration);
                    this.updateProgress(resultText, 100, 'success');
                    
                    // Show success message
                    this.showSuccess(resultText);
                    
                    // Reload page after delay to show fresh results
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                    
                } else {
                    this.handleScanError($button, originalText, null, data.message || 'Scan failed');
                }
                
            } catch (e) {
                this.handleScanError($button, originalText, null, 'Invalid response format');
            }
        },
        
        // Handle scan error
        handleScanError: function($button, originalText, jqXHR, errorMsg) {
            let message = 'Scan failed';
            
            if (typeof errorMsg === 'string') {
                message = errorMsg;
            } else if (jqXHR && jqXHR.responseText) {
                try {
                    const response = JSON.parse(jqXHR.responseText);
                    message = response.message || message;
                } catch (e) {
                    message = 'Network error occurred';
                }
            } else if (jqXHR && jqXHR.statusText) {
                message = jqXHR.statusText;
            }
            
            this.updateProgress(message, 0, 'error');
            this.showError(message);
        },
        
        // Format scan results for display
        formatScanResults: function(counters, details, duration) {
            const parts = [];
            
            if (counters.listed) {
                parts.push(`${counters.listed} files found`);
            }
            
            if (counters.parsed_ok) {
                parts.push(`${counters.parsed_ok} parsed successfully`);
            }
            
            if (counters.saved_ok) {
                parts.push(`${counters.saved_ok} saved to database`);
            }
            
            if (counters.errors) {
                parts.push(`${counters.errors} errors`);
            }
            
            let result = parts.join(', ') + ` (${duration}s)`;
            
            if (details && details.length > 0) {
                result += '\n\nDetails:\n' + details.join('\n');
            }
            
            return result;
        },
        
        // Show progress container
        showProgress: function() {
            $(this.config.progressContainerId).fadeIn(200);
        },
        
        // Hide progress container
        hideProgress: function() {
            $(this.config.progressContainerId).fadeOut(200);
        },
        
        // Update progress display
        updateProgress: function(text, percentage, status) {
            const $container = $(this.config.progressContainerId);
            const $fill = $container.find('.disco747-progress-fill');
            const $text = $container.find('.disco747-progress-text');
            const $details = $container.find('.disco747-progress-details');
            
            // Update progress bar
            if (typeof percentage === 'number') {
                $fill.css('width', Math.min(Math.max(percentage, 0), 100) + '%');
            }
            
            // Update text
            $text.text(text);
            
            // Update status class
            $container.removeClass('success error');
            if (status) {
                $container.addClass(status);
            }
            
            // Show details if multiline
            if (text.includes('\n')) {
                const lines = text.split('\n');
                $text.text(lines[0]);
                $details.html(lines.slice(1).join('<br>'));
            } else {
                $details.empty();
            }
        },
        
        // Check if dry run mode is enabled
        isDryRun: function() {
            const $toggle = $('#disco747-dry-run-toggle');
            return $toggle.length > 0 && $toggle.is(':checked');
        },
        
        // Handle dry run toggle
        handleDryRunToggle: function(e) {
            const $toggle = $(e.currentTarget);
            const isDryRun = $toggle.is(':checked');
            
            // Update button texts to reflect dry run mode
            $(this.config.scanAllButtonId).text(isDryRun ? 'Test Scan All' : 'Scan All');
            $(this.config.scanSingleButtonClass).text(isDryRun ? 'Test Scan' : 'Scan');
            
            // Show/hide dry run notice
            $('.disco747-dry-run-notice').toggle(isDryRun);
        },
        
        // Show success message
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        // Show error message
        showError: function(message) {
            this.showNotice(message, 'error');
        },
        
        // Show notice message
        showNotice: function(message, type) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + 
                             this.escapeHtml(message.replace(/\n/g, '<br>')) + '</p></div>');
            
            // Remove existing notices
            $('.disco747-scan-notice').remove();
            
            // Add new notice
            $notice.addClass('disco747-scan-notice');
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after delay for success messages
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 5000);
            }
            
            // Handle dismiss button
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(() => $notice.remove());
            });
        },
        
        // Escape HTML for safe display
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        // Utility method to refresh page data without full reload
        refreshResults: function() {
            // Reload the results section if we have a specific container
            if ($(this.config.resultsContainerId).length > 0) {
                // This could be enhanced to use AJAX to refresh just the results table
                window.location.reload();
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        ExcelScan.init();
    });
    
    // Expose to global scope for debugging
    window.Disco747ExcelScan = ExcelScan;
    
})(jQuery);