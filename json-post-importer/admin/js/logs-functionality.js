(function ($) {
    'use strict';

    // Initialize logs functionality when document is ready
    $(document).ready(function () {
        initializeLogsInterface();
    });

    /**
     * Initialize the logs interface
     */
    function initializeLogsInterface() {
        // Load initial log stats and files
        loadLogStats();
        loadLogFiles();
        
        // Set up event handlers
        setupEventHandlers();
        
        // Auto-refresh logs every 30 seconds if debug mode is enabled
        setInterval(function() {
            if ($('#jpi-debug-mode').is(':checked')) {
                refreshCurrentLog();
            }
        }, 30000);
    }

    /**
     * Set up event handlers for log interface
     */
    function setupEventHandlers() {
        // Debug mode toggle
        $(document).on('change', '#jpi-debug-mode', function() {
            toggleDebugMode($(this).is(':checked'));
        });

        // Log file selection
        $(document).on('click', '.jpi-view-log', function(e) {
            e.preventDefault();
            const filename = $(this).data('filename');
            viewLogFile(filename);
        });

        // Clear specific log file
        $(document).on('click', '.jpi-clear-log', function(e) {
            e.preventDefault();
            const filename = $(this).data('filename');
            if (confirm(jpi_vars.i18n.confirm_clear_log || 'Are you sure you want to clear this log file?')) {
                clearLogFile(filename);
            }
        });

        // Download log file
        $(document).on('click', '.jpi-download-log', function(e) {
            e.preventDefault();
            const filename = $(this).data('filename');
            downloadLogFile(filename);
        });

        // Clear all logs
        $(document).on('click', '#jpi-clear-all-logs', function(e) {
            e.preventDefault();
            if (confirm(jpi_vars.i18n.confirm_clear_all_logs || 'Are you sure you want to clear all log files? This action cannot be undone.')) {
                clearAllLogs();
            }
        });

        // Refresh logs
        $(document).on('click', '#jpi-refresh-logs', function(e) {
            e.preventDefault();
            refreshLogs();
        });

        // Log level filter
        $(document).on('change', '#jpi-log-level-filter', function() {
            filterLogEntries();
        });

        // Log search
        $(document).on('input', '#jpi-log-search', debounce(function() {
            filterLogEntries();
        }, 300));

        // Lines to show selector
        $(document).on('change', '#jpi-log-lines', function() {
            const currentFile = $('.jpi-log-viewer').data('current-file');
            if (currentFile) {
                viewLogFile(currentFile);
            }
        });

        // Auto-scroll toggle
        $(document).on('change', '#jpi-auto-scroll', function() {
            const $logContent = $('.jpi-log-content');
            if ($(this).is(':checked') && $logContent.length) {
                $logContent.scrollTop($logContent[0].scrollHeight);
            }
        });
    }

    /**
     * Load log statistics
     */
    function loadLogStats() {
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_get_log_stats',
                nonce: jpi_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateLogStats(response.data);
                } else {
                    showLogError('Failed to load log statistics: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showLogError('Network error loading log statistics: ' + error);
            }
        });
    }

    /**
     * Update log statistics display
     */
    function updateLogStats(stats) {
        $('#jpi-log-files-count').text(stats.total_files || 0);
        $('#jpi-log-total-size').text(formatFileSize(stats.total_size || 0));
        $('#jpi-debug-mode-status').text(stats.debug_mode ? 'Enabled' : 'Disabled');
        
        // Update debug mode checkbox
        $('#jpi-debug-mode').prop('checked', stats.debug_mode);
        
        // Update debug toggle styling
        $('.jpi-debug-toggle').toggleClass('enabled', stats.debug_mode);
        
        // Update oldest/newest entry info if available
        if (stats.oldest_entry) {
            $('#jpi-oldest-entry').text(formatDate(stats.oldest_entry));
        }
        if (stats.newest_entry) {
            $('#jpi-newest-entry').text(formatDate(stats.newest_entry));
        }
    }

    /**
     * Load log files list
     */
    function loadLogFiles() {
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_get_logs',
                nonce: jpi_vars.nonce,
                list_files: true
            },
            success: function(response) {
                if (response.success) {
                    updateLogFilesList(response.data);
                } else {
                    showLogError('Failed to load log files: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showLogError('Network error loading log files: ' + error);
            }
        });
    }

    /**
     * Update log files list display
     */
    function updateLogFilesList(files) {
        const $container = $('#jpi-log-files-list');
        
        if (!files || files.length === 0) {
            $container.html(`
                <div class="jpi-logs-empty">
                    <p>${jpi_vars.i18n.no_log_files || 'No log files found.'}</p>
                </div>
            `);
            return;
        }

        let html = '';
        files.forEach(function(file) {
            html += `
                <div class="jpi-log-file-item">
                    <div class="jpi-log-file-info">
                        <div class="jpi-log-file-name">${escapeHtml(file.name)}</div>
                        <div class="jpi-log-file-meta">
                            ${formatFileSize(file.size)} • Modified: ${formatDate(file.modified)}
                        </div>
                    </div>
                    <div class="jpi-log-file-actions">
                        <button type="button" class="button button-small jpi-view-log" data-filename="${escapeHtml(file.name)}">
                            ${jpi_vars.i18n.view || 'View'}
                        </button>
                        <button type="button" class="button button-small jpi-download-log" data-filename="${escapeHtml(file.name)}">
                            ${jpi_vars.i18n.download || 'Download'}
                        </button>
                        <button type="button" class="button button-small button-link-delete jpi-clear-log" data-filename="${escapeHtml(file.name)}">
                            ${jpi_vars.i18n.clear || 'Clear'}
                        </button>
                    </div>
                </div>
            `;
        });

        $container.html(html);
    }

    /**
     * View a specific log file
     */
    function viewLogFile(filename) {
        const lines = parseInt($('#jpi-log-lines').val()) || 100;
        
        showLogLoading();
        
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_get_logs',
                nonce: jpi_vars.nonce,
                filename: filename,
                lines: lines
            },
            success: function(response) {
                if (response.success) {
                    displayLogContent(filename, response.data);
                } else {
                    showLogError('Failed to load log file: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showLogError('Network error loading log file: ' + error);
            }
        });
    }

    /**
     * Display log content in the viewer
     */
    function displayLogContent(filename, content) {
        const $viewer = $('.jpi-log-viewer');
        const $content = $('.jpi-log-content');
        
        // Update viewer title
        $('.jpi-log-viewer-title').text(filename);
        
        // Store current file for reference
        $viewer.data('current-file', filename);
        
        if (!content || content.trim() === '') {
            $content.html('<div class="jpi-logs-empty">This log file is empty.</div>').removeClass('empty').addClass('empty');
            return;
        }

        // Parse and format log entries
        const formattedContent = formatLogContent(content);
        $content.html(formattedContent).removeClass('empty');
        
        // Apply current filters
        filterLogEntries();
        
        // Auto-scroll to bottom if enabled
        if ($('#jpi-auto-scroll').is(':checked')) {
            $content.scrollTop($content[0].scrollHeight);
        }
        
        // Show the viewer
        $viewer.show();
    }

    /**
     * Format log content with syntax highlighting
     */
    function formatLogContent(content) {
        const lines = content.split('\n');
        let formattedLines = [];

        lines.forEach(function(line) {
            if (line.trim() === '') return;

            // Parse log entry format: [timestamp] LEVEL [User: X] | URI | message | Context
            const logPattern = /^\[([^\]]+)\]\s+(\w+)(\[User:[^\]]+\])?\s*\|\s*([^|]+)\s*\|\s*([^|]+)(\|.*)?$/;
            const match = line.match(logPattern);

            if (match) {
                const [, timestamp, level, user, uri, message, context] = match;
                const levelClass = level.toLowerCase();
                
                let formattedLine = `
                    <div class="jpi-log-entry ${levelClass}" data-level="${levelClass}" data-timestamp="${timestamp}">
                        <span class="jpi-log-timestamp">${escapeHtml(timestamp)}</span>
                        <span class="jpi-log-level ${levelClass}">${escapeHtml(level)}</span>
                        ${user ? `<span class="jpi-log-user">${escapeHtml(user)}</span>` : ''}
                        <span class="jpi-log-uri">${escapeHtml(uri.trim())}</span>
                        <div class="jpi-log-message">${escapeHtml(message.trim())}</div>
                        ${context ? `<div class="jpi-log-context">${escapeHtml(context.trim())}</div>` : ''}
                    </div>
                `;
                
                formattedLines.push(formattedLine);
            } else {
                // Handle non-standard log lines
                formattedLines.push(`
                    <div class="jpi-log-entry" data-level="info">
                        <div class="jpi-log-message">${escapeHtml(line)}</div>
                    </div>
                `);
            }
        });

        return formattedLines.join('');
    }

    /**
     * Filter log entries based on level and search term
     */
    function filterLogEntries() {
        const levelFilter = $('#jpi-log-level-filter').val();
        const searchTerm = $('#jpi-log-search').val().toLowerCase();
        const $entries = $('.jpi-log-entry');

        $entries.each(function() {
            const $entry = $(this);
            const level = $entry.data('level');
            const text = $entry.text().toLowerCase();
            
            let show = true;
            
            // Apply level filter
            if (levelFilter && levelFilter !== 'all' && level !== levelFilter) {
                show = false;
            }
            
            // Apply search filter
            if (searchTerm && !text.includes(searchTerm)) {
                show = false;
            }
            
            $entry.toggle(show);
        });

        // Update visible count
        const visibleCount = $entries.filter(':visible').length;
        const totalCount = $entries.length;
        
        $('.jpi-log-viewer-controls .jpi-log-count').text(
            `${visibleCount} of ${totalCount} entries`
        );
    }

    /**
     * Toggle debug mode
     */
    function toggleDebugMode(enabled) {
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_toggle_debug_mode',
                nonce: jpi_vars.nonce,
                enabled: enabled ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    $('.jpi-debug-toggle').toggleClass('enabled', enabled);
                    $('#jpi-debug-mode-status').text(enabled ? 'Enabled' : 'Disabled');
                    
                    // Show notification
                    showLogNotification(
                        enabled ? 
                        (jpi_vars.i18n.debug_enabled || 'Debug mode enabled') : 
                        (jpi_vars.i18n.debug_disabled || 'Debug mode disabled'),
                        'success'
                    );
                } else {
                    // Revert checkbox state on error
                    $('#jpi-debug-mode').prop('checked', !enabled);
                    showLogError('Failed to toggle debug mode: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                // Revert checkbox state on error
                $('#jpi-debug-mode').prop('checked', !enabled);
                showLogError('Network error toggling debug mode: ' + error);
            }
        });
    }

    /**
     * Clear a specific log file
     */
    function clearLogFile(filename) {
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_clear_logs',
                nonce: jpi_vars.nonce,
                filename: filename
            },
            success: function(response) {
                if (response.success) {
                    showLogNotification(jpi_vars.i18n.log_cleared || 'Log file cleared successfully', 'success');
                    
                    // Refresh the interface
                    loadLogStats();
                    loadLogFiles();
                    
                    // Clear viewer if showing this file
                    const currentFile = $('.jpi-log-viewer').data('current-file');
                    if (currentFile === filename) {
                        $('.jpi-log-content').html('<div class="jpi-logs-empty">This log file is empty.</div>').addClass('empty');
                    }
                } else {
                    showLogError('Failed to clear log file: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showLogError('Network error clearing log file: ' + error);
            }
        });
    }

    /**
     * Clear all log files
     */
    function clearAllLogs() {
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_clear_logs',
                nonce: jpi_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    showLogNotification(jpi_vars.i18n.all_logs_cleared || 'All log files cleared successfully', 'success');
                    
                    // Refresh the interface
                    loadLogStats();
                    loadLogFiles();
                    
                    // Clear viewer
                    $('.jpi-log-content').html('<div class="jpi-logs-empty">No logs to display.</div>').addClass('empty');
                    $('.jpi-log-viewer').hide();
                } else {
                    showLogError('Failed to clear log files: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showLogError('Network error clearing log files: ' + error);
            }
        });
    }

    /**
     * Download a log file
     */
    function downloadLogFile(filename) {
        const form = $('<form>', {
            method: 'POST',
            action: jpi_vars.admin_url + 'admin-post.php'
        });

        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'jpi_download_log'
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: jpi_vars.nonce
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'filename',
            value: filename
        }));

        $('body').append(form);
        form.submit();
        form.remove();
    }

    /**
     * Refresh logs interface
     */
    function refreshLogs() {
        showLogNotification(jpi_vars.i18n.refreshing_logs || 'Refreshing logs...', 'info');
        
        loadLogStats();
        loadLogFiles();
        
        // Refresh current log view if any
        const currentFile = $('.jpi-log-viewer').data('current-file');
        if (currentFile) {
            viewLogFile(currentFile);
        }
    }

    /**
     * Refresh current log content
     */
    function refreshCurrentLog() {
        const currentFile = $('.jpi-log-viewer').data('current-file');
        if (currentFile) {
            viewLogFile(currentFile);
        }
    }

    /**
     * Show loading state in log viewer
     */
    function showLogLoading() {
        $('.jpi-log-content').html('<div class="jpi-logs-loading">Loading log content...</div>').removeClass('empty');
    }

    /**
     * Show log error message
     */
    function showLogError(message) {
        $('.jpi-log-content').html(`
            <div class="jpi-logs-error">
                <strong>Error:</strong> ${escapeHtml(message)}
            </div>
        `).removeClass('empty');
    }

    /**
     * Show log notification
     */
    function showLogNotification(message, type = 'info') {
        const $notification = $(`
            <div class="notice notice-${type} is-dismissible jpi-log-notification">
                <p>${escapeHtml(message)}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);

        $('.jpi-logs-container').prepend($notification);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Handle manual dismiss
        $notification.on('click', '.notice-dismiss', function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        });
    }

    /**
     * Format file size for display
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    /**
     * Format date for display
     */
    function formatDate(timestamp) {
        const date = new Date(timestamp * 1000);
        return date.toLocaleString();
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Debounce function for search input
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Export functions for global access
    window.jpiLogs = {
        loadLogStats,
        loadLogFiles,
        viewLogFile,
        toggleDebugMode,
        clearLogFile,
        clearAllLogs,
        refreshLogs
    };

})(jQuery);