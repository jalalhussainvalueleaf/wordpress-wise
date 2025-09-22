(function ($) {
    'use strict';

    // Global variables
    var $form, $fileInput, $fileInfo, $submitBtn, $previewBtn, $spinner, $messageContainer,
        $previewSection, $previewContent, $previewError, $previewLoading, $dropZone,
        $cancelPreviewBtn, $importButtons, $fieldMappings, $importOptions, $errorDetails,
        $progressBar, currentFile, currentJsonData = null;

    // Initialize the plugin when the document is ready
    $(document).ready(function () {
        console.log('JSON Post Importer: Initializing...');

        // Cache DOM elements
        initDOMElements();

        // Initialize all components
        initComponents();

        console.log('JSON Post Importer: Initialization complete');
    });

    /**
     * Initialize DOM elements
     */
    function initDOMElements() {
        $form = $('#jpi-upload-form');
        $fileInput = $('#jpi-json-file');
        $fileInfo = $('#jpi-file-info');
        $submitBtn = $('#jpi-submit');
        $previewBtn = $('#jpi-preview-btn');
        $spinner = $('#jpi-upload-spinner');
        $messageContainer = $('#jpi-message');
        $previewSection = $('#jpi-modal-wrap');
        $previewContent = $('#jpi-json-content');
        $previewError = $('#jpi-preview-error');
        $previewLoading = $('#jpi-preview-loading');
        $dropZone = $('#jpi-drop-zone');
        $cancelPreviewBtn = $('#jpi-cancel-preview');
        $importButtons = $('.jpi-import-button');
        $fieldMappings = $('#jpi-field-mapping-container');
        $importOptions = $('#jpi-import-options');
        $errorDetails = $('#jpi-error-details');
        $progressBar = $('<div class="jpi-upload-progress"><div class="jpi-progress-bar"></div><div class="jpi-progress-text">0%</div></div>');

        console.log('DOM elements cached:', {
            form: $form.length,
            fileInput: $fileInput.length,
            previewBtn: $previewBtn.length,
            importButtons: $importButtons.length,
            modal: $previewSection.length,
            fieldMappings: $fieldMappings.length
        });
    }

    /**
     * Initialize all components
     */
    function initComponents() {
        initTabs();
        initFileInput();
        initFormSubmission();
        initPreviewHandlers();
        initImportOptions();
        initDismissibleNotices();
        initModalHandlers();
        initPreviewUI();
        initHistoryAndLogs();
    }

    /**
     * Initialize tab navigation
     */
    function initTabs() {
        // Modal tab navigation
        $(document).on('click', '.jpi-tab-link', function (e) {
            e.preventDefault();
            var target = $(this).attr('href');

            $('.jpi-tab-link').removeClass('active');
            $('.jpi-tab-pane').removeClass('active');
            $(this).addClass('active');
            $(target).addClass('active');

            // Initialize field mapping when mapping tab is clicked
            if (target === '#jpi-tab-mapping' && window.currentJsonData) {
                setTimeout(function () {
                    initFieldMapping(window.currentJsonData);
                }, 100);
            }

            console.log('Tab switched to:', target);
        });
    }

    /**
     * Initialize file input and drag-drop functionality
     */
    function initFileInput() {
        // Browse files button
        $('.jpi-browse-files').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $fileInput.trigger('click');
        });

        // File input change handler
        $fileInput.on('change', function () {
            handleFileSelection(this.files[0]);
        });

        // Drag and drop handlers
        $dropZone.on('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag-over');
        });

        $dropZone.on('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });

        $dropZone.on('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');

            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelection(files[0]);
            }
        });

        // Drop zone click handler
        $dropZone.on('click', function (e) {
            if (e.target === this || $(e.target).hasClass('jpi-drop-zone-content')) {
                $fileInput.trigger('click');
            }
        });
    }

    /**
     * Handle file selection
     */
    function handleFileSelection(file) {
        if (!file) {
            resetFileInput();
            return;
        }

        // Validate file type
        var isValidFile = (file.type === 'application/json' || file.name.toLowerCase().endsWith('.json'));

        if (!isValidFile) {
            showMessage('error', 'Please select a valid JSON file.');
            resetFileInput();
            return;
        }

        // Update UI
        currentFile = file;
        $fileInfo.text(file.name + ' (' + formatFileSize(file.size) + ')').show();
        $previewBtn.prop('disabled', false).addClass('button-primary');

        console.log('File selected:', file.name, file.size, 'bytes');
    }

    /**
     * Reset file input
     */
    function resetFileInput() {
        $fileInput.val('');
        $fileInfo.text('').hide();
        $previewBtn.prop('disabled', true).removeClass('button-primary');
        currentFile = null;
        resetPreview();
    }

    /**
     * Initialize form submission handlers
     */
    function initFormSubmission() {
        // Main upload form
        $form.on('submit', function (e) {
            e.preventDefault();

            if (!currentFile) {
                showMessage('error', 'Please select a file to upload.');
                return;
            }

            processFileForPreview(currentFile);
        });
    }

    /**
     * Initialize preview handlers
     */
    function initPreviewHandlers() {
        // Preview button click
        $previewBtn.on('click', function (e) {
            e.preventDefault();

            if (!currentFile) {
                showMessage('error', 'Please select a file first.');
                return;
            }

            processFileForPreview(currentFile);
        });

        // Cancel preview button
        $cancelPreviewBtn.on('click', function (e) {
            e.preventDefault();
            closeModal();
            resetPreview();
        });

        // Import button handler using event delegation
        $(document).on('click', '.jpi-import-button', function (e) {
            e.preventDefault();

            if ($(this).prop('disabled')) {
                return false;
            }

            handleImportClick();
        });
    }

    /**
     * Initialize modal handlers
     */
    function initModalHandlers() {
        // Modal close button
        $('#jpi-modal-close').on('click', function (e) {
            e.preventDefault();
            closeModal();
        });

        // Modal backdrop click
        $('#jpi-modal-backdrop').on('click', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Escape key to close modal
        $(document).on('keydown', function (e) {
            if (e.keyCode === 27 && $('#jpi-modal-wrap').is(':visible')) {
                closeModal();
            }
        });
    }

    /**
     * Initialize import options
     */
    function initImportOptions() {
        // Set default values if available
        if (typeof jpi_vars !== 'undefined') {
            if (jpi_vars.default_post_type) {
                $('#jpi-post-type').val(jpi_vars.default_post_type);
            }
            if (jpi_vars.default_post_status) {
                $('#jpi-post-status').val(jpi_vars.default_post_status);
            }
        }
    }

    /**
     * Initialize dismissible notices
     */
    function initDismissibleNotices() {
        $('.notice-dismiss').on('click', function () {
            $(this).closest('.notice').fadeOut();
        });
    }

    /**
     * Initialize preview UI
     */
    function initPreviewUI() {
        // Modal elements should already exist in the DOM
        console.log('Preview UI initialized. Modal elements:', {
            modal: $('#jpi-modal-wrap').length,
            backdrop: $('#jpi-modal-backdrop').length,
            content: $('#jpi-json-content').length
        });
    }

    /**
     * Process file for preview
     */
    function processFileForPreview(file) {
        if (!file) {
            showMessage('error', 'No file selected.');
            return;
        }

        showLoading('Reading file...');

        var reader = new FileReader();

        reader.onload = function (e) {
            try {
                var jsonData = JSON.parse(e.target.result);
                currentJsonData = jsonData;

                console.log('JSON parsed successfully:', jsonData);

                // Show modal with preview
                showModal();
                displayPreview(jsonData);

            } catch (error) {
                console.error('JSON parsing error:', error);
                showMessage('error', 'Invalid JSON file: ' + error.message);
            } finally {
                hideLoading();
            }
        };

        reader.onerror = function () {
            hideLoading();
            showMessage('error', 'Error reading the file. Please try again.');
        };

        reader.readAsText(file);
    }

    /**
     * Display JSON preview in modal
     */
    function displayPreview(jsonData) {
        var $previewTab = $('#jpi-tab-preview');
        var $jsonContent = $('#jpi-json-content');

        if (!$jsonContent.length) {
            $jsonContent = $('<pre><code id="jpi-json-content"></code></pre>');
            $previewTab.find('.jpi-json-preview').html($jsonContent);
        }

        // Format and display JSON
        var formattedJson = JSON.stringify(jsonData, null, 2);
        $jsonContent.text(formattedJson);

        // Store JSON data globally for enhanced field mapping
        window.currentJsonData = jsonData;

        // Trigger enhanced field mapping initialization
        $(document).trigger('jpi:jsonDataLoaded', [jsonData]);

        // Initialize field mapping
        initFieldMapping(jsonData);

        // Show import options
        $('#jpi-import-options').show();

        // Enable import button
        $('.jpi-import-button').prop('disabled', false);

        console.log('Preview displayed successfully');
    }

    /**
     * Initialize field mapping interface
     */
    function initFieldMapping(jsonData) {
        var $mappingContainer = $('#jpi-field-mapping-container');

        if (!$mappingContainer.length) {
            console.error('Field mapping container not found');
            return;
        }

        // Store JSON data globally for field mapping
        window.currentJsonData = jsonData;

        // Trigger event for nested field mapping
        $(document).trigger('jpi:jsonDataLoaded', [jsonData]);

        // Clear any existing content
        $mappingContainer.empty();

        // Check if enhanced field mapping is available first
        if (typeof window.EnhancedFieldMapping !== 'undefined' && window.EnhancedFieldMapping.renderEnhancedFieldMapping) {
            console.log('Using enhanced field mapping');
            window.EnhancedFieldMapping.renderEnhancedFieldMapping(jsonData);
        } else if (typeof renderFieldMappingUI === 'function') {
            console.log('Using standard field mapping UI');
            renderFieldMappingUI(jsonData);

            // Setup real-time validation
            if (typeof setupRealTimeValidation === 'function') {
                setupRealTimeValidation();
            }

            // Validate preview data and show issues
            if (typeof validatePreviewData === 'function') {
                var previewIssues = validatePreviewData(jsonData);
                if (previewIssues.length > 0) {
                    displayPreviewValidation(previewIssues);
                }
            }
        } else {
            console.log('Using fallback field mapping');
            // Fallback to basic field mapping with enhanced nested field detection
            var availableFields = detectJsonFields(jsonData);
            var mappingHtml = generateFieldMappingHTML(availableFields);
            $mappingContainer.html(mappingHtml);
        }

        console.log('Field mapping initialized with JSON data:', jsonData);
    }

    /**
     * Display preview validation issues
     */
    function displayPreviewValidation(issues) {
        var errors = issues.filter(function (issue) { return issue.type === 'error'; });
        var warnings = issues.filter(function (issue) { return issue.type === 'warning'; });

        var html = '<div class="jpi-preview-validation';
        if (errors.length > 0) {
            html += ' has-errors';
        } else if (warnings.length > 0) {
            html += ' has-warnings';
        } else {
            html += ' valid';
        }
        html += '">';

        if (errors.length > 0) {
            html += '<h4><span class="dashicons dashicons-dismiss"></span>' + (jpi_vars.i18n.preview_errors || 'Preview Issues') + '</h4>';
            html += '<ul>';
            errors.forEach(function (error) {
                html += '<li>' + error.message + '</li>';
            });
            html += '</ul>';
        }

        if (warnings.length > 0) {
            html += '<h4><span class="dashicons dashicons-warning"></span>' + (jpi_vars.i18n.preview_warnings || 'Recommendations') + '</h4>';
            html += '<ul>';
            warnings.forEach(function (warning) {
                html += '<li>' + warning.message + '</li>';
            });
            html += '</ul>';
        }

        html += '</div>';

        // Insert after preview content or at the beginning of field mapping
        var $target = $('#jpi-preview-content');
        if ($target.length) {
            $target.after(html);
        } else {
            $('#jpi-field-mapping-container').prepend(html);
        }
    }

    /**
     * Detect fields from JSON data (including nested fields)
     */
    function detectJsonFields(jsonData) {
        var fields = new Set();

        // Ensure we have an array to work with
        var items = Array.isArray(jsonData) ? jsonData : [jsonData];

        console.log('Detecting fields from JSON data:', items);

        // Analyze first few items to detect fields (including nested)
        items.slice(0, 5).forEach(function (item) {
            if (typeof item === 'object' && item !== null) {
                extractFieldPaths(item, '', fields, 0, 4); // Extract up to 4 levels deep
            }
        });

        var fieldArray = Array.from(fields).sort();
        console.log('Detected fields:', fieldArray);
        return fieldArray;
    }

    /**
     * Recursively extract field paths from nested objects
     */
    function extractFieldPaths(obj, prefix, fields, depth, maxDepth) {
        if (depth >= maxDepth || typeof obj !== 'object' || obj === null) {
            return;
        }

        for (var key in obj) {
            if (obj.hasOwnProperty(key)) {
                var currentPath = prefix ? prefix + '.' + key : key;
                var value = obj[key];

                // Always add the current path
                fields.add(currentPath);

                // If the value is an object (but not an array), recurse into it
                if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                    extractFieldPaths(value, currentPath, fields, depth + 1, maxDepth);
                }
            }
        }
    }

    /**
     * Generate field mapping HTML with nested field support and previews
     */
    function generateFieldMappingHTML(availableFields) {
        var html = '<div class="jpi-field-mapping-section">';
        html += '<h4>Standard Fields</h4>';
        html += '<p class="description">Map JSON fields to WordPress post fields. Nested fields are shown with dot notation (e.g., content.title).</p>';
        html += '<table class="form-table">';

        // Standard WordPress fields
        var standardFields = [
            { key: 'post_title', label: 'Post Title', required: true },
            { key: 'post_content', label: 'Post Content', required: false },
            { key: 'post_excerpt', label: 'Post Excerpt', required: false },
            { key: 'post_status', label: 'Post Status', required: false },
            { key: 'post_date', label: 'Post Date', required: false }
        ];

        standardFields.forEach(function (field) {
            html += '<tr>';
            html += '<th scope="row">' + field.label + (field.required ? ' *' : '') + '</th>';
            html += '<td>';
            html += '<select name="field_mapping[standard][' + field.key + ']" class="regular-text field-mapping-select" data-field="' + field.key + '">';
            html += '<option value="">-- Select Field --</option>';

            availableFields.forEach(function (jsonField) {
                var selected = '';
                var fieldLower = field.key.replace('post_', '').toLowerCase();
                var jsonLower = jsonField.toLowerCase();
                var preview = getFieldPreview(jsonField);

                // Enhanced auto-select logic for nested fields
                if (jsonLower === fieldLower ||
                    jsonLower.endsWith('.' + fieldLower) ||
                    (field.key === 'post_title' && (jsonLower.includes('title') || jsonLower.includes('name') || jsonLower.includes('heading'))) ||
                    (field.key === 'post_content' && (jsonLower.includes('content') || jsonLower.includes('body') || jsonLower.includes('text') || jsonLower.includes('description'))) ||
                    (field.key === 'post_excerpt' && (jsonLower.includes('excerpt') || jsonLower.includes('summary') || jsonLower.includes('abstract')))) {
                    selected = ' selected';
                }

                html += '<option value="' + escapeHtml(jsonField) + '"' + selected + '>' + escapeHtml(jsonField) + (preview ? ' (' + escapeHtml(preview) + ')' : '') + '</option>';
            });

            html += '</select>';
            html += '<div class="field-preview" id="preview-' + field.key + '" style="margin-top: 5px; font-style: italic; color: #666;"></div>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</table>';

        // Add custom fields section
        html += '<h4 style="margin-top: 30px;">Custom Meta Fields</h4>';
        html += '<p class="description">Add custom meta field mappings.</p>';
        html += '<div id="custom-fields-container">';
        html += '<button type="button" id="add-custom-field" class="button button-secondary">Add Custom Field</button>';
        html += '</div>';

        html += '</div>';

        // Add JavaScript for field preview updates
        setTimeout(function () {
            initFieldPreviewUpdates();
        }, 100);

        return html;
    }

    /**
     * Get preview value for a JSON field path
     */
    function getFieldPreview(fieldPath) {
        if (!window.currentJsonData) return '';

        var items = Array.isArray(window.currentJsonData) ? window.currentJsonData : [window.currentJsonData];
        if (items.length === 0) return '';

        var sampleItem = items[0];
        var value = getNestedValue(sampleItem, fieldPath);

        if (value === null || value === undefined) return '';

        if (typeof value === 'string') {
            return value.length > 50 ? value.substring(0, 50) + '...' : value;
        }

        if (typeof value === 'object') {
            return Array.isArray(value) ? '[Array]' : '[Object]';
        }

        return String(value);
    }

    /**
     * Get nested value from object using dot notation
     */
    function getNestedValue(obj, path) {
        return path.split('.').reduce(function (current, key) {
            return current && current[key] !== undefined ? current[key] : null;
        }, obj);
    }

    /**
     * Initialize field preview updates
     */
    function initFieldPreviewUpdates() {
        $(document).on('change', '.field-mapping-select', function () {
            var $select = $(this);
            var fieldKey = $select.data('field');
            var selectedPath = $select.val();
            var $preview = $('#preview-' + fieldKey);

            if (selectedPath && window.currentJsonData) {
                var preview = getFieldPreview(selectedPath);
                if (preview) {
                    $preview.html('<strong>Preview:</strong> ' + escapeHtml(preview));
                } else {
                    $preview.html('<em>No preview available</em>');
                }
            } else {
                $preview.html('');
            }
        });

        // Trigger initial preview updates for pre-selected fields
        $('.field-mapping-select').each(function () {
            if ($(this).val()) {
                $(this).trigger('change');
            }
        });
    }

    /**
     * Show message to user
     */
    function showMessage(type, message) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

        // Remove existing notices
        $('.notice').remove();

        // Add new notice
        $('.wrap > h1').after($notice);

        // Auto-dismiss success messages
        if (type === 'success') {
            setTimeout(function () {
                $notice.fadeOut();
            }, 5000);
        }

        // Scroll to top
        $('html, body').animate({ scrollTop: 0 }, 300);
    }

    /**
     * Format file size for display
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';

        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Utility function to escape HTML
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    /**
     * Show the modal
     */
    function showModal() {
        $('#jpi-modal-backdrop').show();
        $('#jpi-modal-wrap').show();
        $('body').addClass('modal-open');

        // Focus management for accessibility
        $('#jpi-modal-wrap').focus();

        console.log('Modal opened');
    }

    /**
     * Close the modal
     */
    function closeModal() {
        $('#jpi-modal-backdrop').hide();
        $('#jpi-modal-wrap').hide();
        $('body').removeClass('modal-open');

        console.log('Modal closed');
    }

    /**
     * Reset preview state
     */
    function resetPreview() {
        $('#jpi-json-content').empty();
        $('#jpi-field-mapping-container').html('<div class="jpi-mapping-placeholder"><p>After uploading a JSON file, you can map the fields to WordPress post fields here.</p></div>');
        $('.jpi-import-button').prop('disabled', true);
        currentJsonData = null;
        window.currentJsonData = null;

        console.log('Preview reset');
    }

    /**
     * Show loading state
     */
    function showLoading(message) {
        message = message || 'Loading...';
        $('#jpi-preview-loading span:last-child').text(message);
        $('#jpi-preview-loading').show();
        $('#jpi-preview-error').hide();
    }

    /**
     * Hide loading state
     */
    function hideLoading() {
        $('#jpi-preview-loading').hide();
    }

    /**
     * Handle import button click
     */
    function handleImportClick() {
        if (!currentJsonData) {
            showMessage('error', 'No JSON data available for import.');
            return;
        }

        // Check if enhanced field mapping is available and valid
        var enhancedMappings = null;
        if (typeof window.validateEnhancedFieldMappings === 'function' &&
            typeof window.getEnhancedFieldMappings === 'function') {

            console.log('Using enhanced field mapping validation...');

            if (!window.validateEnhancedFieldMappings()) {
                showMessage('error', 'Post title mapping is required. Please map the post title field before importing.');
                return;
            }

            enhancedMappings = window.getEnhancedFieldMappings();
            if (!enhancedMappings) {
                showMessage('error', 'Failed to collect field mappings. Please check your field mapping configuration.');
                return;
            }

            console.log('Enhanced field mappings collected:', enhancedMappings);
        } else {
            // Fallback validation for basic field mapping
            console.log('Using fallback field mapping validation...');

            var titleMapping = $('select[name="field_mapping[standard][post_title]"]').val() ||
                $('.enhanced-field-mapping[data-field="post_title"][data-section="standard"]').val();

            if (!titleMapping) {
                showMessage('error', 'Post title mapping is required.');
                return;
            }
        }

        // Collect field mappings and import settings
        var fieldMappings = {};
        var importSettings = {};

        if (enhancedMappings) {
            // Use enhanced field mappings
            fieldMappings = enhancedMappings.field_mappings;
        } else {
            // Fallback: Get standard field mappings from basic interface
            $('select[name^="field_mapping[standard]"], .enhanced-field-mapping[data-section="standard"]').each(function () {
                var fieldName = $(this).data('field') || $(this).attr('name').match(/\[([^\]]+)\]$/)[1];
                var fieldValue = $(this).val();
                if (fieldValue && fieldName) {
                    if (!fieldMappings.standard) fieldMappings.standard = {};
                    fieldMappings.standard[fieldName] = fieldValue;
                }
            });
        }

        // Get import settings using the flexible function
        if (typeof getImportSettings === 'function') {
            importSettings = getImportSettings();
        } else {
            // Fallback to basic settings
            importSettings.post_type = $('#jpi-post-type').val() || 'post';
            importSettings.post_status = $('#jpi-post-status').val() || 'draft';
            importSettings.update_existing = $('#jpi-update-existing').is(':checked');
            importSettings.import_images = $('#jpi-import-images').is(':checked');
            importSettings.create_terms = $('#jpi-create-terms').is(':checked');
        }

        console.log('Starting import with mappings:', fieldMappings);
        console.log('Import settings:', importSettings);

        // Start import process
        startImportProcess(currentJsonData, fieldMappings, importSettings);
    }

    /**
     * Start the import process
     */
    function startImportProcess(jsonData, fieldMappings, importSettings) {
        var $importButton = $('.jpi-import-button');
        var originalText = $importButton.text();

        // Show loading state
        $importButton.prop('disabled', true).html('<span class="spinner is-active"></span> Starting Import...');

        // Prepare data for AJAX request
        var importData = {
            action: 'jpi_start_import',
            nonce: jpi_vars.nonce,
            json_data: JSON.stringify(jsonData),
            field_mappings: JSON.stringify(fieldMappings),
            import_settings: JSON.stringify(importSettings)
        };

        // Make AJAX request to start import
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: importData,
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    // Start batch processing
                    startBatchProcessing(response.data);
                } else {
                    showMessage('error', response.data.message || 'Failed to start import.');
                    $importButton.prop('disabled', false).text(originalText);
                }
            })
            .fail(function (xhr, status, error) {
                console.error('Import start error:', error);
                showMessage('error', 'Failed to start import: ' + error);
                $importButton.prop('disabled', false).text(originalText);
            });
    }

    /**
     * Start batch processing with progress tracking
     */
    function startBatchProcessing(importInfo) {
        var importId = importInfo.import_id;
        var totalBatches = importInfo.total_batches;
        var currentBatch = 0;
        var cancelled = false;

        // Show progress UI
        showProgressUI(importInfo);

        // Process batches sequentially
        function processBatch() {
            if (cancelled || currentBatch >= totalBatches) {
                return;
            }

            $.ajax({
                url: jpi_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'jpi_process_batch',
                    nonce: jpi_vars.nonce,
                    import_id: importId,
                    batch_number: currentBatch
                },
                dataType: 'json'
            })
                .done(function (response) {
                    if (response.success) {
                        updateProgress(response.data.progress);

                        if (response.data.import_complete) {
                            // Import completed
                            showImportResults(importId);
                        } else {
                            // Process next batch
                            currentBatch++;
                            setTimeout(processBatch, 500); // Small delay between batches
                        }
                    } else {
                        showMessage('error', response.data.message || 'Batch processing failed.');
                        hideProgressUI();
                    }
                })
                .fail(function (xhr, status, error) {
                    console.error('Batch processing error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                    
                    let errorMessage = 'Batch processing failed: ' + error;
                    
                    // Try to parse error response for more details
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.data && response.data.message) {
                            errorMessage = 'Batch processing failed: ' + response.data.message;
                        }
                        if (response.data && response.data.debug_info) {
                            console.error('Debug info:', response.data.debug_info);
                        }
                    } catch (e) {
                        // Response is not JSON, use original error
                    }
                    
                    showMessage('error', errorMessage);
                    hideProgressUI();
                });
        }

        // Set up cancel functionality
        $('#jpi-cancel-import').off('click').on('click', function () {
            if (confirm('Are you sure you want to cancel the import?')) {
                cancelled = true;
                cancelImport(importId);
            }
        });

        // Start processing
        processBatch();
    }

    /**
     * Show progress UI
     */
    function showProgressUI(importInfo) {
        var progressHtml = `
            <div id="jpi-progress-container" class="jpi-progress-container">
                <div class="jpi-progress-header">
                    <h3>Import Progress</h3>
                    <button type="button" id="jpi-cancel-import" class="button button-secondary">Cancel Import</button>
                </div>
                <div class="jpi-progress-bar-container">
                    <div class="jpi-progress-bar">
                        <div class="jpi-progress-bar-fill" style="width: 0%"></div>
                    </div>
                    <div class="jpi-progress-text">0% (0 of ${importInfo.total_items} items)</div>
                </div>
                <div class="jpi-progress-stats">
                    <div class="jpi-stat">
                        <span class="jpi-stat-label">Created:</span>
                        <span class="jpi-stat-value" id="jpi-created-count">0</span>
                    </div>
                    <div class="jpi-stat">
                        <span class="jpi-stat-label">Updated:</span>
                        <span class="jpi-stat-value" id="jpi-updated-count">0</span>
                    </div>
                    <div class="jpi-stat">
                        <span class="jpi-stat-label">Skipped:</span>
                        <span class="jpi-stat-value" id="jpi-skipped-count">0</span>
                    </div>
                    <div class="jpi-stat">
                        <span class="jpi-stat-label">Errors:</span>
                        <span class="jpi-stat-value" id="jpi-error-count">0</span>
                    </div>
                </div>
                <div class="jpi-progress-details">
                    <div class="jpi-batch-info">
                        Batch <span id="jpi-current-batch">1</span> of <span id="jpi-total-batches">${importInfo.total_batches}</span>
                    </div>
                </div>
            </div>
        `;

        // Replace modal content with progress UI
        $('.jpi-modal-body').html(progressHtml);
        $('.jpi-modal-footer').hide();
    }

    /**
     * Update progress display
     */
    function updateProgress(progress) {
        $('#jpi-progress-container .jpi-progress-bar-fill').css('width', progress.percentage + '%');
        $('#jpi-progress-container .jpi-progress-text').text(
            progress.percentage + '% (' + progress.processed_items + ' of ' + progress.total_items + ' items)'
        );

        $('#jpi-created-count').text(progress.created_posts);
        $('#jpi-updated-count').text(progress.updated_posts);
        $('#jpi-skipped-count').text(progress.skipped_posts);
        $('#jpi-error-count').text(progress.error_count);
        $('#jpi-current-batch').text(progress.current_batch);
    }

    /**
     * Hide progress UI
     */
    function hideProgressUI() {
        $('#jpi-progress-container').remove();
        $('.jpi-modal-footer').show();
        $('.jpi-import-button').prop('disabled', false).text('Import Selected Items');
    }

    /**
     * Cancel import
     */
    function cancelImport(importId) {
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_cancel_import',
                nonce: jpi_vars.nonce,
                import_id: importId
            },
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    showMessage('info', response.data.message);
                    showImportResults(importId, true);
                } else {
                    showMessage('error', response.data.message || 'Failed to cancel import.');
                }
            })
            .fail(function (xhr, status, error) {
                console.error('Cancel import error:', error);
                showMessage('error', 'Failed to cancel import: ' + error);
            });
    }

    /**
     * Show import results
     */
    function showImportResults(importId, wasCancelled) {
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_get_import_results',
                nonce: jpi_vars.nonce,
                import_id: importId
            },
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    displayImportResults(response.data.results, wasCancelled);
                } else {
                    showMessage('error', response.data.message || 'Failed to get import results.');
                    hideProgressUI();
                }
            })
            .fail(function (xhr, status, error) {
                console.error('Get results error:', error);
                showMessage('error', 'Failed to get import results: ' + error);
                hideProgressUI();
            });
    }

    /**
     * Display import results
     */
    function displayImportResults(results, wasCancelled) {
        var statusClass = results.status === 'completed' ? 'success' :
            results.status === 'cancelled' ? 'warning' : 'error';

        var statusText = results.status === 'completed' ? 'Completed Successfully' :
            results.status === 'cancelled' ? 'Cancelled' : 'Failed';

        var resultsHtml = `
            <div class="jpi-results-container">
                <div class="jpi-results-header">
                    <h3>Import Results</h3>
                    <div class="jpi-status jpi-status-${statusClass}">${statusText}</div>
                </div>
                <div class="jpi-results-summary">
                    <div class="jpi-result-stat">
                        <div class="jpi-result-number">${results.processed_items}</div>
                        <div class="jpi-result-label">Items Processed</div>
                    </div>
                    <div class="jpi-result-stat">
                        <div class="jpi-result-number">${results.created_posts}</div>
                        <div class="jpi-result-label">Posts Created</div>
                    </div>
                    <div class="jpi-result-stat">
                        <div class="jpi-result-number">${results.updated_posts}</div>
                        <div class="jpi-result-label">Posts Updated</div>
                    </div>
                    <div class="jpi-result-stat">
                        <div class="jpi-result-number">${results.skipped_posts}</div>
                        <div class="jpi-result-label">Posts Skipped</div>
                    </div>
                    <div class="jpi-result-stat">
                        <div class="jpi-result-number">${results.error_count}</div>
                        <div class="jpi-result-label">Errors</div>
                    </div>
                </div>
                <div class="jpi-results-details">
                    <p><strong>Import ID:</strong> ${results.import_id}</p>
                    <p><strong>Started:</strong> ${results.start_time}</p>
                    ${results.end_time ? '<p><strong>Completed:</strong> ' + results.end_time + '</p>' : ''}
                    ${results.duration ? '<p><strong>Duration:</strong> ' + results.duration + '</p>' : ''}
                </div>
                ${results.errors && results.errors.length > 0 ? generateErrorsHtml(results.errors) : ''}
            </div>
        `;

        // Replace modal content with results
        $('.jpi-modal-body').html(resultsHtml);

        // Update footer buttons
        $('.jpi-modal-footer').html(`
            <button type="button" id="jpi-view-logs" class="button">View Logs</button>
            <button type="button" id="jpi-close-results" class="button button-primary">Close</button>
        `);

        // Bind close button
        $('#jpi-close-results').on('click', function () {
            closeModal();
            resetPreview();
            showMessage('success', 'Import ' + statusText.toLowerCase() + '!');
        });

        // Bind view logs button
        $('#jpi-view-logs').on('click', function () {
            showImportLogs(results.import_id);
        });
    }

    /**
     * Generate errors HTML
     */
    function generateErrorsHtml(errors) {
        if (!errors || errors.length === 0) {
            return '';
        }

        var errorsHtml = '<div class="jpi-errors-section"><h4>Errors (' + errors.length + ')</h4><div class="jpi-errors-list">';

        errors.slice(0, 10).forEach(function (error, index) {
            errorsHtml += `
                <div class="jpi-error-item">
                    <div class="jpi-error-message">${escapeHtml(error.message)}</div>
                    <div class="jpi-error-details">Item ${error.item_index + 1}</div>
                </div>
            `;
        });

        if (errors.length > 10) {
            errorsHtml += '<div class="jpi-error-more">... and ' + (errors.length - 10) + ' more errors</div>';
        }

        errorsHtml += '</div></div>';
        return errorsHtml;
    }

    /**
     * Initialize history and logs functionality
     */
    function initHistoryAndLogs() {
        // Load import history on page load
        loadImportHistory();

        // Refresh history button
        $('#jpi-refresh-history').on('click', function () {
            loadImportHistory();
        });

        // View logs button
        $('#jpi-view-logs').on('click', function () {
            showLogsModal();
        });

        // Logs modal handlers
        $('#jpi-logs-modal-close, #jpi-close-logs').on('click', function () {
            hideLogsModal();
        });

        $('#jpi-logs-modal-backdrop').on('click', function (e) {
            if (e.target === this) {
                hideLogsModal();
            }
        });

        // Clear logs button
        $('#jpi-clear-logs').on('click', function () {
            if (confirm('Are you sure you want to clear all import logs?')) {
                clearImportLogs();
            }
        });

        // Log filters
        $('#jpi-logs-level-filter, #jpi-logs-search').on('input change', function () {
            filterLogs();
        });
    }

    /**
     * Load import history
     */
    function loadImportHistory() {
        var $container = $('#jpi-history-container');

        $container.html('<div class="jpi-loading-history"><span class="spinner is-active"></span><span>Loading import history...</span></div>');

        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_get_import_history',
                nonce: jpi_vars.nonce
            },
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    displayImportHistory(response.data.history);
                } else {
                    $container.html('<div class="jpi-error"><p>' + (response.data.message || 'Failed to load import history.') + '</p></div>');
                }
            })
            .fail(function (xhr, status, error) {
                console.error('Load history error:', error);
                $container.html('<div class="jpi-error"><p>Failed to load import history: ' + error + '</p></div>');
            });
    }

    /**
     * Display import history
     */
    function displayImportHistory(history) {
        var $container = $('#jpi-history-container');

        if (!history || history.length === 0) {
            $container.html('<div class="jpi-no-history"><p>No import history found. Start your first import above!</p></div>');
            return;
        }

        var historyHtml = '<div class="jpi-history-table-container"><table class="jpi-history-table">';
        historyHtml += '<thead><tr>';
        historyHtml += '<th>Import ID</th>';
        historyHtml += '<th>Date</th>';
        historyHtml += '<th>Status</th>';
        historyHtml += '<th>Items</th>';
        historyHtml += '<th>Created</th>';
        historyHtml += '<th>Updated</th>';
        historyHtml += '<th>Errors</th>';
        historyHtml += '<th>Actions</th>';
        historyHtml += '</tr></thead><tbody>';

        history.forEach(function (item) {
            var statusClass = item.status === 'completed' ? 'success' :
                item.status === 'cancelled' ? 'warning' : 'error';

            var statusText = item.status === 'completed' ? 'Completed' :
                item.status === 'cancelled' ? 'Cancelled' : 'Failed';

            historyHtml += '<tr>';
            historyHtml += '<td><code>' + escapeHtml(item.import_id) + '</code></td>';
            historyHtml += '<td>' + formatDate(item.start_time) + '</td>';
            historyHtml += '<td><span class="jpi-status jpi-status-' + statusClass + '">' + statusText + '</span></td>';
            historyHtml += '<td>' + item.processed_items + ' / ' + item.total_items + '</td>';
            historyHtml += '<td>' + item.created_posts + '</td>';
            historyHtml += '<td>' + item.updated_posts + '</td>';
            historyHtml += '<td>' + (item.error_count > 0 ? '<span class="jpi-error-count">' + item.error_count + '</span>' : '0') + '</td>';
            historyHtml += '<td><button type="button" class="button button-small jpi-view-details" data-import-id="' + escapeHtml(item.import_id) + '">Details</button></td>';
            historyHtml += '</tr>';
        });

        historyHtml += '</tbody></table></div>';

        $container.html(historyHtml);

        // Bind detail buttons
        $('.jpi-view-details').on('click', function () {
            var importId = $(this).data('import-id');
            showImportDetails(importId);
        });
    }

    /**
     * Show import details
     */
    function showImportDetails(importId) {
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_get_import_results',
                nonce: jpi_vars.nonce,
                import_id: importId
            },
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    showModal();
                    displayImportResults(response.data.results, false);
                } else {
                    showMessage('error', response.data.message || 'Failed to load import details.');
                }
            })
            .fail(function (xhr, status, error) {
                console.error('Load details error:', error);
                showMessage('error', 'Failed to load import details: ' + error);
            });
    }

    /**
     * Show logs modal
     */
    function showLogsModal() {
        $('#jpi-logs-modal-backdrop').show();
        $('#jpi-logs-modal-wrap').show();
        $('body').addClass('modal-open');

        loadImportLogs();
    }

    /**
     * Hide logs modal
     */
    function hideLogsModal() {
        $('#jpi-logs-modal-backdrop').hide();
        $('#jpi-logs-modal-wrap').hide();
        $('body').removeClass('modal-open');
    }

    /**
     * Load import logs
     */
    function loadImportLogs() {
        var $container = $('#jpi-logs-container');

        $container.html('<div class="jpi-loading"><span class="spinner is-active"></span><span>Loading logs...</span></div>');

        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_get_import_logs',
                nonce: jpi_vars.nonce
            },
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    displayImportLogs(response.data.logs || []);
                } else {
                    $container.html('<div class="jpi-error"><p>' + (response.data.message || 'Failed to load logs.') + '</p></div>');
                }
            })
            .fail(function (xhr, status, error) {
                console.error('Load logs error:', error);
                $container.html('<div class="jpi-error"><p>Failed to load logs: ' + error + '</p></div>');
            });
    }

    /**
     * Display import logs
     */
    function displayImportLogs(logs) {
        var $container = $('#jpi-logs-container');

        if (!logs || logs.length === 0) {
            $container.html('<div class="jpi-no-logs"><p>No logs found.</p></div>');
            return;
        }

        var logsHtml = '<div class="jpi-logs-list">';

        logs.forEach(function (log) {
            var levelClass = 'jpi-log-' + log.level;
            logsHtml += '<div class="jpi-log-entry ' + levelClass + '" data-level="' + log.level + '">';
            logsHtml += '<div class="jpi-log-header">';
            logsHtml += '<span class="jpi-log-time">' + formatDateTime(log.timestamp) + '</span>';
            logsHtml += '<span class="jpi-log-level jpi-log-level-' + log.level + '">' + log.level.toUpperCase() + '</span>';
            logsHtml += '<span class="jpi-log-import-id">' + escapeHtml(log.import_id) + '</span>';
            logsHtml += '</div>';
            logsHtml += '<div class="jpi-log-message">' + escapeHtml(log.message) + '</div>';
            logsHtml += '</div>';
        });

        logsHtml += '</div>';

        $container.html(logsHtml);
    }

    /**
     * Filter logs
     */
    function filterLogs() {
        var levelFilter = $('#jpi-logs-level-filter').val();
        var searchFilter = $('#jpi-logs-search').val().toLowerCase();

        $('.jpi-log-entry').each(function () {
            var $entry = $(this);
            var level = $entry.data('level');
            var message = $entry.find('.jpi-log-message').text().toLowerCase();
            var importId = $entry.find('.jpi-log-import-id').text().toLowerCase();

            var levelMatch = !levelFilter || level === levelFilter;
            var searchMatch = !searchFilter || message.includes(searchFilter) || importId.includes(searchFilter);

            if (levelMatch && searchMatch) {
                $entry.show();
            } else {
                $entry.hide();
            }
        });
    }

    /**
     * Clear import logs
     */
    function clearImportLogs() {
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_clear_import_logs',
                nonce: jpi_vars.nonce
            },
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success) {
                    loadImportLogs();
                    showMessage('success', 'Import logs cleared successfully.');
                } else {
                    showMessage('error', response.data.message || 'Failed to clear logs.');
                }
            })
            .fail(function (xhr, status, error) {
                console.error('Clear logs error:', error);
                showMessage('error', 'Failed to clear logs: ' + error);
            });
    }

    /**
     * Format date for display
     */
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    /**
     * Format datetime for display
     */
    function formatDateTime(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    /**
     * Show import logs
     */
    function showImportLogs(importId) {
        showLogsModal();
        // Filter logs by import ID after loading
        setTimeout(function () {
            $('#jpi-logs-search').val(importId).trigger('input');
        }, 500);
    }

    // Make some functions globally available for debugging
    window.jpiDebug = {
        currentJsonData: function () { return currentJsonData; },
        showModal: showModal,
        closeModal: closeModal,
        resetPreview: resetPreview,
        processFile: processFileForPreview,
        loadHistory: loadImportHistory,
        showLogs: showLogsModal
    };

})(jQuery);    /**

     * Initialize flexible import options handlers
     */
function initFlexibleImportOptions() {
    // Handle dry run option
    $('#jpi-dry-run').on('change', function () {
        const $importButton = $('.jpi-import-button');
        if ($(this).is(':checked')) {
            $importButton.text(jpi_vars.i18n.preview_import || 'Preview Import');
            $importButton.removeClass('button-primary').addClass('button-secondary');
        } else {
            $importButton.text(jpi_vars.i18n.import_posts || 'Import Posts');
            $importButton.removeClass('button-secondary').addClass('button-primary');
        }
    });

    // Handle preserve IDs warning
    $('#jpi-preserve-ids').on('change', function () {
        if ($(this).is(':checked')) {
            if (!$('#jpi-preserve-ids-warning').length) {
                $(this).closest('label').after(
                    '<div id="jpi-preserve-ids-warning" class="notice notice-warning inline" style="margin: 10px 0; padding: 8px 12px;">' +
                    '<p><strong>Warning:</strong> Preserving post IDs may cause conflicts if posts with those IDs already exist.</p>' +
                    '</div>'
                );
            }
        } else {
            $('#jpi-preserve-ids-warning').remove();
        }
    });

    // Handle memory limit validation
    $('#jpi-memory-limit').on('blur', function () {
        const value = $(this).val().trim();
        if (value && !/^\d+[MG]?$/i.test(value)) {
            $(this).addClass('jpi-field-error');
            if (!$('#jpi-memory-limit-error').length) {
                $(this).after('<div id="jpi-memory-limit-error" class="jpi-validation-message error" style="display: block;">Invalid format. Use format like 512M or 1G.</div>');
            }
        } else {
            $(this).removeClass('jpi-field-error');
            $('#jpi-memory-limit-error').remove();
        }
    });

    // Handle batch size validation
    $('#jpi-batch-size').on('change', function () {
        const value = parseInt($(this).val(), 10);
        if (value > 50) {
            if (!$('#jpi-batch-size-warning').length) {
                $(this).after('<div id="jpi-batch-size-warning" class="jpi-validation-message warning" style="display: block;">Large batch sizes may cause timeouts or memory issues.</div>');
            }
        } else {
            $('#jpi-batch-size-warning').remove();
        }
    });

    // Add option status indicators
    $('.jpi-checkbox-options input[type="checkbox"]').each(function () {
        const $checkbox = $(this);
        const $label = $checkbox.closest('label');

        if (!$label.find('.jpi-option-status').length) {
            $label.append('<span class="jpi-option-status"></span>');
        }

        updateOptionStatus($checkbox);
    });

    // Update status indicators on change
    $('.jpi-checkbox-options input[type="checkbox"]').on('change', function () {
        updateOptionStatus($(this));
    });
}

/**
 * Update option status indicator
 */
function updateOptionStatus($checkbox) {
    const $status = $checkbox.closest('label').find('.jpi-option-status');

    if ($checkbox.is(':checked')) {
        $status.removeClass('disabled warning').addClass('enabled');
    } else {
        $status.removeClass('enabled warning').addClass('disabled');
    }

    // Special handling for certain options
    if ($checkbox.attr('id') === 'jpi-preserve-ids' && $checkbox.is(':checked')) {
        $status.removeClass('enabled').addClass('warning');
    }
}

/**
 * Reset import options to defaults
 */
function resetImportOptions() {
    $('#jpi-post-type').val('post');
    $('#jpi-post-status').val('draft');
    $('#jpi-batch-size').val(10);
    $('#jpi-default-author').val(jpi_vars.current_user_id || 1);
    $('#jpi-date-format').val('Y-m-d H:i:s');
    $('#jpi-timeout').val(30);
    $('#jpi-memory-limit').val('');

    // Reset checkboxes to defaults
    $('#jpi-update-existing, #jpi-import-images, #jpi-create-terms, #jpi-import-meta').prop('checked', true);
    $('#jpi-preserve-ids, #jpi-dry-run, #jpi-skip-duplicates, #jpi-enable-revisions').prop('checked', false);

    // Update status indicators
    $('.jpi-checkbox-options input[type="checkbox"]').each(function () {
        updateOptionStatus($(this));
    });

    // Remove any warnings
    $('.jpi-validation-message, #jpi-preserve-ids-warning').remove();
    $('.jpi-field-error').removeClass('jpi-field-error');
}

// Initialize flexible options when document is ready
$(document).ready(function () {
    initFlexibleImportOptions();

    // Add reset button to advanced options
    if ($('.jpi-advanced-content').length && !$('#jpi-reset-options').length) {
        $('.jpi-advanced-content').append(
            '<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e1e1e1;">' +
            '<button type="button" id="jpi-reset-options" class="button">' +
            (jpi_vars.i18n.reset_options || 'Reset to Defaults') +
            '</button></div>'
        );

        $('#jpi-reset-options').on('click', function () {
            if (confirm(jpi_vars.i18n.confirm_reset_options || 'Are you sure you want to reset all import options to their defaults?')) {
                resetImportOptions();
            }
        });
    }
});