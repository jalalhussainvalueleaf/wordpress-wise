/**
 * JSON Post Importer Admin JS
 * Handles AJAX file uploads, preview, and user feedback
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Cache DOM elements
    var $form = $('#jpi-upload-form');
    var $fileInput = $('#json-file');
    var $fileInfo = $('#jpi-file-info');
    var $submitBtn = $('#jpi-submit');
    var $previewBtn = $('#jpi-preview-btn');
    var $spinner = $('#jpi-upload-spinner');
    var $messageContainer = $('#jpi-message');
    var $previewSection = $('#jpi-preview-section');
    var $previewContent = $('#jpi-preview-content');
    var $previewError = $('#jpi-preview-error');
    var $previewLoading = $('#jpi-preview-loading');
    var $dropZone = $('#jpi-drop-zone');
    var $cancelPreviewBtn = $('#jpi-cancel-preview');
    var $confirmImportBtn = $('#jpi-confirm-import');
    var $fieldMappings = $('#jpi-field-mappings');
    var $importOptions = $('#jpi-import-options');
    var $errorDetails = $('#jpi-error-details');
    
    // State
    var currentFile = null;
    
    // Initialize the file input and drag-drop
    initFileInput();
    initFormSubmission();
    initDismissibleNotices();
    initPreviewHandlers();
    
    // Initialize import options
    initImportOptions();
    
    /**
     * Initialize import options
     */
    function initImportOptions() {
        // Set default post type and status
        if (jpi_vars.default_post_type) {
            $('#jpi-post-type').val(jpi_vars.default_post_type);
        }
        
        if (jpi_vars.default_post_status) {
            $('#jpi-post-status').val(jpi_vars.default_post_status);
        }
    }
    
    // Initialize browse files button
    $('#jpi-browse-files').on('click', function(e) {
        e.preventDefault();
        $fileInput.trigger('click');
    });
    
    // Handle file selection
    $fileInput.on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            handleFileSelection(file);
        }
    });
    
    // Handle drag and drop
    if ($dropZone.length) {
        var dropZone = $dropZone[0];
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        ['dragenter', 'dragover'].forEach(function(eventName) {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(function(eventName) {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        dropZone.addEventListener('drop', handleDrop, false);
    }
    
    // Handle form submission
    $form.on('submit', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Handle preview button click
    $previewBtn.on('click', function(e) {
        e.preventDefault();
        if (currentFile) {
            previewFile(currentFile);
        }
        return false;
    });
    
    // Handle import button click
    $submitBtn.on('click', function(e) {
        e.preventDefault();
        if (currentFile) {
            uploadFile(currentFile);
        }
        return false;
    });
    
    // Handle cancel preview button
    $cancelPreviewBtn.on('click', function(e) {
        e.preventDefault();
        resetPreview();
    });
    
    // Handle confirm import button click
    $confirmImportBtn.on('click', function(e) {
        e.preventDefault();
        startImport();
    });
    
    /**
     * Reset the preview section
     */
    function resetPreview() {
        $previewSection.slideUp();
        $previewContent.empty();
        $previewError.hide();
        $importOptions.hide();
        $fieldMappings.hide();
        $errorDetails.hide();
        $fileInput.val('');
        currentFile = null;
        resetUploadState();
    }
    
    /**
     * Start the import process
     */
    function startImport() {
        // Get field mappings and import options
        var fieldMappings = getFieldMappings();
        var importOptions = {
            update_existing: $('#jpi-update-existing').is(':checked'),
            import_images: $('#jpi-import-images').is(':checked'),
            create_terms: $('#jpi-create-terms').is(':checked'),
            post_status: $('#jpi-post-status').val(),
            post_type: $('#jpi-post-type').val()
        };
        
        // Get the JSON data from the hidden field
        var jsonData = $('#jpi-json-data').val();
        
        if (!jsonData) {
            showMessage('error', 'No JSON data found. Please upload a file first.');
            return;
        }
        
        // Validate required fields are mapped
        var requiredFields = ['post_title'];
        var missingFields = [];
        
        requiredFields.forEach(function(field) {
            var isMapped = Object.keys(fieldMappings).some(function(key) {
                return fieldMappings[key].wp_field === field;
            });
            
            if (!isMapped) {
                missingFields.push(field);
            }
        });
        
        if (missingFields.length > 0) {
            showMessage(
                'error',
                'Required fields are not mapped: ' + missingFields.join(', '),
                'Please map all required fields before importing.'
            );
            return;
        }
        
        // Show loading state
        setImportingState(true);
        $errorDetails.hide().empty();
        
        // Prepare the data to send
        var formData = new FormData();
        formData.append('action', 'jpi_import_content');
        formData.append('security', jpi_vars.import_nonce);
        formData.append('field_mappings', JSON.stringify(fieldMappings));
        formData.append('import_options', JSON.stringify(importOptions));
        formData.append('json_data', jsonData);
        
        // Show progress indicator
        var $progress = $('<div class="jpi-import-progress">' +
            '<div class="jpi-progress-bar"><div class="jpi-progress-bar-fill"></div></div>' +
            '<div class="jpi-progress-text">Preparing import...</div>' +
            '</div>');
            
        $previewContent.before($progress);
        
        // Make the AJAX request
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            timeout: 300000, // 5 minutes
            xhr: function() {
                const xhr = new window.XMLHttpRequest();

                // Upload progress
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        $progress.find('.jpi-progress-bar-fill').css('width', percentComplete + '%');
                        $progress.find('.jpi-progress-text').text(`Uploading: ${percentComplete}%`);
                    }
                }, false);

                // Download progress
                xhr.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        $progress.find('.jpi-progress-text').text('Processing import...');
                    }
                }, false);

                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showMessage('success', 'Import completed successfully!');

                    // Show import results
                    if (response.data && response.data.stats) {
                        var stats = response.data.stats;
                        var message = 'Imported: ' + (stats.imported || 0) + ' items\n';
                        message += 'Updated: ' + (stats.updated || 0) + ' items\n';
                        message += 'Skipped: ' + (stats.skipped || 0) + ' items';

                        if (stats.errors && stats.errors.length > 0) {
                            message += '\n\n' + stats.errors.length + ' errors occurred during import.';
                        }

                        showMessage('info', 'Import Results', message);
                    }

                    // Show error details if any
                    if (response.data && response.data.errors && response.data.errors.length > 0) {
                        var $errorList = $('<ul></ul>');
                        response.data.errors.forEach(function(error) {
                            $errorList.append($('<li></li>').text(error));
                        });
                        $errorDetails.html('<h4>Some items could not be imported:</h4>').append($errorList).show();
                    }

                    // Reset the form after a short delay
                    setTimeout(function() {
                        resetPreview();
                        resetForm();

                        // Refresh the page to show new content
                        window.location.reload();
                    }, 3000);
                } else {
                    // Handle server-side validation errors
                    var errorMessage = 'An unknown error occurred during import.';
                    
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    
                    if (response.data && response.data.errors) {
                        errorMessage += '\n\n' + response.data.errors.join('\n');
                    }
                    
                    showMessage('error', errorMessage);
                    
                    // Show detailed error if available
                    if (response.data && response.data.debug) {
                        $errorDetails.html('<pre>' + JSON.stringify(response.data.debug, null, 2) + '</pre>').show();
                    }
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'An error occurred during the import process. ';
                
                if (status === 'timeout') {
                    errorMessage += 'The request timed out. The server may be processing a large amount of data.';
                } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        errorMessage = response.data && response.data.message 
                            ? response.data.message 
                            : errorMessage;
                    } catch (e) {
                        errorMessage += 'Please try again or check your server error logs.';
                    }
                }
                
                showMessage('error', errorMessage);
                $progress.find('.jpi-progress-bar-fill').css('background-color', '#dc3232');
                $progress.find('.jpi-progress-text').text('Import failed');
            },
            complete: function() {
                setImportingState(false);
            }
        });
    }
    
    /**
     * Handle import error
     */
    function handleImportError(error) {
        var errorMessage = 'An error occurred during import.';
        
        if (typeof error === 'string') {
            errorMessage = error;
        } else if (error && error.message) {
            errorMessage = error.message;
        }
        
        showMessage('error', errorMessage);
        
        if (error && error.debug) {
            console.error('Import Error:', error.debug);
        }
    }
    
    /**
     * Set importing state
     */
    function setImportingState(isImporting) {
        if (isImporting) {
            $submitBtn.prop('disabled', true).text('Importing...');
            $previewBtn.prop('disabled', true);
            $confirmImportBtn.prop('disabled', true);
            $spinner.show();
        } else {
            $submitBtn.prop('disabled', false).text('Upload');
            $previewBtn.prop('disabled', false);
            $confirmImportBtn.prop('disabled', false);
            $spinner.hide();
        }
    }
    
    /**
     * Show a message to the user
     * 
     * @param {string} type Message type (success, error, warning, info)
     * @param {string} message The message to display
     * @param {string} details Optional details to display
     */
    function showMessage(type, message, details) {
        // Clear any existing messages
        $messageContainer.empty();
        
        // Create message element
        var $message = $('<div>').addClass('notice notice-' + type + ' is-dismissible');
        $message.append($('<p>').text(message));
        
        // Add details if provided
        if (details) {
            $message.append($('<p>').addClass('description').text(details));
        }
        
        // Add dismiss button
        $message.append($('<button>').attr('type', 'button').addClass('notice-dismiss')
            .append($('<span>').addClass('screen-reader-text').text('Dismiss this notice.')));
        
        // Add to container and fade in
        $messageContainer.html($message).hide().fadeIn();
        
        // Auto-hide after 10 seconds for success messages
        if (type === 'success') {
            setTimeout(function() {
                $message.fadeOut();
            }, 10000);
        }
    }
    
    /**
     * Show preview error message
     */
    function showPreviewError(message) {
        $previewError.html('<p>' + message + '</p>').show();
        $previewLoading.hide();
        $previewContent.hide();
        
        // Also show in the main message container
        showMessage('error', 'Preview Error: ' + message);
    }
    
    /**
     * Handle file selection
     */
    function handleFileSelection(file) {
        if (validateFile(file)) {
            currentFile = file;
            updateFileInfo(file);
            toggleButtons(true);
        } else {
            resetUploadState();
        }
    }

    /**
     * Initialize file input handling
     */
    function initFileInput() {
        // Handle file selection
        $fileInput.on('change', function() {
            const file = this.files[0];
            if (file) {
                updateFileInfo(file);
                currentFile = file;
                toggleButtons(true);
            }
        });
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            $dropZone.on(eventName, preventDefaults);
            $(document.body).on(eventName, preventDefaults);
        });
        
        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            $dropZone.on(eventName, highlight);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            $dropZone.on(eventName, unhighlight);
        });
        
        // Handle dropped files
        $dropZone.on('drop', handleDrop);
    }
    
    /**
     * Initialize form submission
     */
    function initFormSubmission() {
        $form.on('submit', function(e) {
            e.preventDefault();
            
            const file = $fileInput[0].files[0];
            const validation = validateFile(file);
            
            if (validation.isValid) {
                uploadFile(file);
            } else {
                showMessage(validation.message, 'error');
            }
        });
    }
    
    /**
     * Validate the selected file
     */
    function validateFile(file) {
        // Check if file exists
        if (!file) {
            return {
                isValid: false,
                message: jpi_vars.i18n.no_file_selected
            };
        }
        
        // Check file type
        const isValidType = file.type === 'application/json' || 
                          file.name.toLowerCase().endsWith('.json');
        
        if (!isValidType) {
            return {
                isValid: false,
                message: jpi_vars.i18n.invalid_file_type
            };
        }
        
        // Check file size
        if (file.size > jpi_vars.max_upload_size_bytes) {
            return {
                isValid: false,
                message: jpi_vars.i18n.file_too_large.replace('%s', jpi_vars.max_upload_size)
            };
        }
        
        return { isValid: true };
    }
    
    /**
     * Initialize preview handlers
     */
    function initPreviewHandlers() {
        // Handle field mapping changes
        $(document).on('change', '.jpi-field-select', function() {
            const $field = $(this);
            const fieldName = $field.data('field');
            const selectedValue = $field.val();
            
            // Show/hide custom field name input
            if (selectedValue === 'custom_field') {
                $field.closest('td').find('.custom-field-name').show();
            } else {
                $field.closest('td').find('.custom-field-name').hide();
            }
            
            updateFieldMappings();
        });
        
        // Handle custom field name changes
        $(document).on('input', '.custom-field-name input', function() {
            updateFieldMappings();
        });
    }
    
    /**
     * Preview the selected file
     */
    function previewFile(file) {
        if (!file) {
            console.error('No file provided to preview');
            return;
        }
        
        // Check if file is JSON
        const isJsonFile = file.type === 'application/json' || file.name.toLowerCase().endsWith('.json');
        if (!isJsonFile) {
            const errorMsg = `Invalid file type: ${file.type}. Please upload a JSON file.`;
            console.error(errorMsg);
            showMessage('error', jpi_vars.i18n.invalid_file_type);
            return;
        }
        
        console.log('Reading file:', file.name, 'Size:', file.size, 'bytes');
        
        // Show loading state
        $previewSection.show();
        $previewError.hide();
        $previewContent.hide();
        $previewLoading.show();
        
        // Update progress UI
        updateProgress(0, 'Reading file...');
        
        // Read file content
        const reader = new FileReader();
        const fileSize = file.size;
        let loaded = 0;
        
        // Progress tracking
        reader.onprogress = function(e) {
            if (e.lengthComputable) {
                loaded = e.loaded;
                const percentComplete = Math.round((loaded / fileSize) * 50); // First 50% for loading
                updateProgress(percentComplete, 'Processing file...');
            }
        };
        
        reader.onloadstart = function() {
            updateProgress(0, 'Starting file read...');
        };
        
        reader.onload = function(e) {
            updateProgress(60, 'Parsing JSON data...');
            console.log('File read successfully, parsing JSON...');
            
            // Log the raw response for debugging
            console.log('Raw file content (first 500 chars):', e.target.result.substring(0, 500));
            
            // Simulate processing delay for better UX
            setTimeout(function() {
                try {
                    // Try to parse the JSON
                    const jsonData = JSON.parse(e.target.result);
                    console.log('JSON parsed successfully:', jsonData);
                    
                    updateProgress(90, 'Generating preview...');
                    
                    // Small delay to show 100% before hiding the loader
                    setTimeout(function() {
                        updateProgress(100, 'Preview ready!');
                        setTimeout(function() {
                            $previewLoading.hide();
                            previewJsonData(jsonData);
                            $previewContent.fadeIn();
                        }, 300);
                    }, 500);
                    
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    console.error('Raw response that caused error:', e.target.result);
                    
                    // Try to find where the error might be
                    try {
                        // Try to find the line number where the error occurred
                        const errorLine = e.message.match(/position\s(\d+)/);
                        if (errorLine && errorLine[1]) {
                            const errorPos = parseInt(errorLine[1]);
                            const start = Math.max(0, errorPos - 50);
                            const end = Math.min(e.target.result.length, errorPos + 50);
                            console.error('Error context:', e.target.result.substring(start, end));
                        }
                    } catch (logError) {
                        console.error('Could not extract error context:', logError);
                    }
                    
                    showPreviewError('Invalid JSON: ' + e.message + ' (check browser console for details)');
                }
            }, 300);
        };
        
        reader.onerror = function(error) {
            console.error('Error reading file:', error);
            showPreviewError(jpi_vars.i18n.file_read_error);
        };
        
        reader.onabort = function() {
            console.warn('File reading was aborted');
            showPreviewError('File reading was cancelled');
        };
        
        reader.readAsText(file);
        
        // Helper function to update progress
        function updateProgress(percent, status) {
            $('.jpi-progress-bar-fill').css('width', percent + '%');
            $('.jpi-progress-percentage').text(percent + '%');
            $('.jpi-progress-status').text(status);
            
            if (percent >= 100) {
                $('.jpi-progress-text').text('Preview ready!');
            }
        }
    }
    
    /**
     * Preview JSON data
     */
    function previewJsonData(jsonData) {
        console.log('Sending JSON data for preview...');
        
        // Validate JSON data
        if (!jsonData) {
            console.error('No JSON data to preview');
            showPreviewError('No data to preview');
            return;
        }
        
        // Stringify with error handling
        let jsonString;
        try {
            jsonString = JSON.stringify(jsonData);
            console.log('JSON string length:', jsonString.length, 'characters');
        } catch (e) {
            console.error('Error stringifying JSON:', e);
            showPreviewError('Error preparing data for preview: ' + e.message);
            return;
        }
        
        // Show loading state
        $previewLoading.show();
        $previewSection.hide();
        $previewError.hide();
        
        // Create form data for the request
        const formData = new FormData();
        formData.append('action', 'jpi_preview_json');
        formData.append('security', jpi_vars.nonce);
        formData.append('json_data', jsonString);
        
        console.log('Sending AJAX request to:', jpi_vars.ajax_url);
        console.log('Request data:', {
            action: 'jpi_preview_json',
            security: '***', // Don't log the actual nonce
            json_data_length: jsonString.length
        });
        
        // Add debug headers
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'X-JPI-Debug': '1',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        };
        
        // Send to server for processing and preview
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false, // Don't process the data
            contentType: false, // Let the browser set the content type
            dataType: 'json',
            headers: headers,
            cache: false,
            timeout: 30000, // 30 second timeout
            success: function(response, status, xhr) {
                console.log('AJAX success:', response);
                
                // Check if the response is valid and successful
                if (response && response.success) {
                    // Handle successful response
                    if (response.data && response.data.preview_html) {
                        $previewContent.html(response.data.preview_html);
                        $previewSection.show();
                        $previewLoading.hide();
                        $submitBtn.prop('disabled', false);
                        
                        // Initialize any UI components in the preview
                        if (typeof initPreviewUI === 'function') {
                            initPreviewUI();
                        }
                    } else {
                        console.error('Preview data missing or invalid:', response);
                        showPreviewError('Preview data is missing or invalid. Check console for details.');
                    }
                } else {
                    // Handle error response
                    const errorMsg = (response && response.data && response.data.message) || 
                                   (response && response.message) || 
                                   jpi_vars.i18n.preview_error;
                    console.error('Preview error:', errorMsg, response);
                    showPreviewError(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error, xhr);
                
                let errorMsg = jpi_vars.i18n.preview_error;
                let responseText = xhr.responseText || '';
                
                // Log the first 1000 characters of the response for debugging
                console.log('Raw response (first 1000 chars):', responseText.substring(0, 1000));
                
                // Check if this is a WordPress login page (common cause of issues)
                if (responseText.includes('wp-login.php') || responseText.includes('loginform')) {
                    errorMsg = 'Your session has expired. Please refresh the page and log in again.';
                }
                
                // Try to parse the response as JSON
                try {
                    const jsonResponse = JSON.parse(responseText);
                    if (jsonResponse.data && jsonResponse.data.message) {
                        errorMsg = jsonResponse.data.message;
                    } else if (jsonResponse.message) {
                        errorMsg = jsonResponse.message;
                    }
                } catch (e) {
                    // If we can't parse as JSON, check for common HTML errors
                    if (responseText.includes('loginform') || responseText.includes('wp-login.php')) {
                        errorMsg = 'Session expired. Please refresh the page and log in again.';
                    } else if (responseText.trim().startsWith('<')) {
                        errorMsg = 'Received HTML instead of JSON. This might be a server configuration issue.';
                    }
                }
                
                if (status === 'timeout') {
                    errorMsg = 'Request timed out. The server took too long to respond.';
                } else if (status === 'error') {
                    if (xhr.status === 0) {
                        errorMsg = 'Network error. Please check your internet connection.';
                    } else if (xhr.status === 403) {
                        errorMsg = 'Permission denied. You may need to log in again.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Server error. Please check the server logs for more details.';
                    } else {
                        errorMsg = `Error (${xhr.status}): ${error}`;
                    }
                }
                
                showPreviewError(errorMsg);
            }
        });
    }
    
    /**
     * Show preview error
     */
    function showPreviewError(message) {
        $previewLoading.hide();
        $previewError.html(`<div class="notice notice-error"><p>${message}</p></div>`).show();
        $previewSection.hide();
        $submitBtn.prop('disabled', true);
    }

    /**
     * Upload file via AJAX
     */
    function uploadFile(file) {
        if (!file) return;
        
        const formData = new FormData($form[0]);
        formData.append('action', 'jpi_handle_upload');
        formData.append('nonce', jpi_vars.upload_nonce);
        formData.append('json_file', file);
        
        // Add field mappings to form data
        const fieldMappings = getFieldMappings();
        formData.append('field_mappings', JSON.stringify(fieldMappings));
        
        setUploadingState(true);
        
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: handleUploadSuccess,
            error: handleUploadError,
            complete: function() {
                setUploadingState(false);
            }
        });
    }
    
    /**
     * Get all field mappings from the form
     */
    function getFieldMappings() {
        const mappings = {};
        
        $('.jpi-field-mapping').each(function() {
            const $row = $(this);
            const jsonField = $row.data('field');
            const $select = $row.find('.jpi-field-select');
            const selectedField = $select.val();
            
            if (selectedField) {
                if (selectedField === 'custom_field') {
                    const customFieldName = $row.find('.custom-field-name input').val().trim();
                    if (customFieldName) {
                        mappings[jsonField] = {
                            type: 'custom_field',
                            name: customFieldName
                        };
                    }
                } else {
                    mappings[jsonField] = {
                        type: 'post_field',
                        name: selectedField
                    };
                }
            }
        });
        
        return mappings;
    }
    
    /**
     * Update hidden field with current field mappings
     */
    function updateFieldMappings() {
        const mappings = getFieldMappings();
        $fieldMappings.val(JSON.stringify(mappings));
    }
    
    /**
     * Toggle buttons based on file selection
     */
    function toggleButtons(enable) {
        $previewBtn.prop('disabled', !enable);
        $submitBtn.prop('disabled', !enable);
    }
    
    /**
     * Handle successful upload
     */
    function handleUploadSuccess(response) {
        if (response.success) {
            // Show success message
            showMessage(response.data.message, 'success');
            
            // Show preview if data is available
            if (response.data.preview_html) {
                $previewContent.html(response.data.preview_html);
                
                // Show import options and field mappings
                $importOptions.slideDown();
                
                // Initialize field mappings if available
                if (response.data.field_mappings) {
                    initFieldMappings(response.data.field_mappings);
                }
                
                $fieldMappings.slideDown();
                
                // Show preview section
                $previewSection.slideDown();
                
                // Scroll to preview
                $('html, body').animate({
                    scrollTop: $previewSection.offset().top - 100
                }, 500);
                
                // Store JSON data in a hidden field for import
                if (response.data.json_data) {
                    $('<input>').attr({
                        type: 'hidden',
                        id: 'jpi-json-data',
                        name: 'jpi_json_data',
                        value: JSON.stringify(response.data.json_data)
                    }).appendTo('body');
                }
            }
            $previewSection.slideUp();
            
            // Redirect or refresh the page to show the success message
            window.location.href = addQueryParam(window.location.href, 'jpi_message', 'import_success');
        } else {
            showMessage(response.data && response.data.message ? response.data.message : jpi_vars.i18n.upload_error, 'error');
        }
    }
    
    /**
     * Initialize field mappings in the UI
     */
    function initFieldMappings(mappings) {
        const $container = $('#jpi-field-mappings-container');
        $container.empty();
        
        if (!mappings || !Array.isArray(mappings) || mappings.length === 0) {
            $container.html('<p>No field mappings available. All fields will be imported as post meta.</p>');
            return;
        }
        
        const $table = $('<table class="wp-list-table widefat fixed striped"></table>');
        const $thead = $('<thead><tr><th>JSON Field</th><th>Map to</th></tr></thead>');
        const $tbody = $('<tbody></tbody>');
        
        // Standard WordPress fields
        const wpFields = [
            { value: 'post_title', label: 'Post Title' },
            { value: 'post_content', label: 'Post Content' },
            { value: 'post_excerpt', label: 'Post Excerpt' },
            { value: 'post_status', label: 'Post Status' },
            { value: 'post_date', label: 'Post Date' },
            { value: 'post_author', label: 'Post Author' },
            { value: 'post_name', label: 'Post Slug' },
            { value: 'menu_order', label: 'Menu Order' },
            { value: 'comment_status', label: 'Comment Status' },
            { value: 'ping_status', label: 'Ping Status' },
            { value: 'post_password', label: 'Post Password' },
            { value: 'post_parent', label: 'Post Parent' },
            { value: 'post_mime_type', label: 'MIME Type' },
            { value: 'post_type', label: 'Post Type' },
            { value: 'featured_image', label: 'Featured Image' },
            { value: 'post_category', label: 'Categories' },
            { value: 'tags_input', label: 'Tags' }
        ];
        
        // Add custom fields from the mappings
        mappings.forEach(mapping => {
            if (mapping.field && mapping.label) {
                const $row = $('<tr></tr>');
                
                // Field name
                $row.append($('<td>').text(mapping.label));
                
                // Field mapping select
                const $select = $('<select>').attr({
                    'name': `field_mapping[${mapping.field}]`,
                    'class': 'jpi-field-mapping',
                    'data-field': mapping.field
                });
                
                // Add default option
                $select.append($('<option>').val('').text('-- Select Field --'));
                
                // Add WordPress fields
                const $wpFieldsGroup = $('<optgroup>').attr('label', 'WordPress Fields');
                wpFields.forEach(field => {
                    $wpFieldsGroup.append($('<option>').val(field.value).text(field.label));
                });
                $select.append($wpFieldsGroup);
                
                // Add custom fields option
                const $customFieldsGroup = $('<optgroup>').attr('label', 'Custom Fields');
                $customFieldsGroup.append($('<option>').val('meta:' + mapping.field).text('Custom Field (' + mapping.field + ')'));
                
                // Add taxonomies if available
                if (jpi_vars.taxonomies && Object.keys(jpi_vars.taxonomies).length > 0) {
                    const $taxGroup = $('<optgroup>').attr('label', 'Taxonomies');
                    Object.entries(jpi_vars.taxonomies).forEach(([taxonomy, label]) => {
                        $taxGroup.append($('<option>').val(`tax:${taxonomy}`).text(`${label} (Taxonomy)`));
                    });
                    $select.append($taxGroup);
                }
                
                $select.append($customFieldsGroup);
                
                // Set default value if available
                if (mapping.default_field) {
                    $select.val(mapping.default_field);
                }
                
                $row.append($('<td>').append($select));
                $tbody.append($row);
            }
        });
        
        $table.append($thead).append($tbody);
        $container.append($table);
        
        // Initialize select2 if available
        if ($.fn.select2) {
            $('.jpi-field-mapping').select2({
                width: '100%',
                placeholder: 'Select a field to map to...'
            });
        }
    }
    
    /**
     * Get all field mappings from the form
     */
    function getFieldMappings() {
        const mappings = {};
        
        $('.jpi-field-mapping').each(function() {
            const field = $(this).data('field');
            const value = $(this).val();
            
            if (field && value) {
                mappings[field] = value;
            }
        });
        
        return mappings;
    }
    
    /**
     * Handle upload errors
     */
    function handleUploadError(xhr, status, error) {
        console.error('Upload error:', {xhr, status, error, response: xhr.responseJSON});
        
        let errorMessage = 'An error occurred while uploading the file. Please try again.';
        
        try {
            // Try to parse the response as JSON
            const response = xhr.responseJSON || JSON.parse(xhr.responseText);
            
            if (response && response.data && response.data.message) {
                errorMessage = response.data.message;
            } else if (response && response.message) {
                errorMessage = response.message;
            }
        } catch (e) {
            // If we can't parse as JSON, try to get a useful error message
            if (xhr.status === 403) {
                errorMessage = 'Permission denied. Please ensure you are logged in and have the correct permissions.';
            } else if (xhr.status === 0) {
                errorMessage = 'Network error. Please check your internet connection and try again.';
            } else if (xhr.status === 413) {
                errorMessage = 'File is too large. Please try a smaller file or increase your server\'s upload limit.';
            } else if (xhr.responseText) {
                errorMessage = xhr.responseText.substring(0, 200); // Limit length
            }
        }
        
        showMessage('error', 'Upload Failed', errorMessage);
        setUploadingState(false);
    }
    
    /**
     * Add query parameter to URL
    ```
    function addQueryParam(url, key, value) {
        const separator = url.includes('?') ? '&' : '?';
        return url + separator + encodeURIComponent(key) + '=' + encodeURIComponent(value);
    }
    
    /**
     * Prevent default drag behaviors
     */
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    /**
     * Highlight drop zone
     */
    function highlight() {
        $dropZone.addClass('is-dragover');
    }
    
    /**
     * Unhighlight drop zone
     */
    function unhighlight() {
        $dropZone.removeClass('is-dragover');
    }
    
    /**
     * Handle dropped files
     */
    function handleDrop(e) {
        const dt = e.originalEvent.dataTransfer;
        const files = dt.files;
        
        if (files.length) {
            const file = files[0];
            $fileInput[0].files = files;
            updateFileInfo(file);
            currentFile = file;
            toggleButtons(true);
        }
    }
    
    /**
     * Update the file info display
     */
    function updateFileInfo(file) {
        if (!file) {
            $fileInfo.text('No file selected');
            $dropZone.removeClass('has-file');
            return;
        }
        
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        $fileInfo.html(`
            <strong>${file.name}</strong> (${fileSize} MB)
        `);
        $dropZone.addClass('has-file');
    }
    
    /**
     * Set uploading state
     */
    function setUploadingState(isUploading) {
        if (isUploading) {
            $submitBtn.prop('disabled', true);
            $previewBtn.prop('disabled', true);
            $confirmImportBtn.prop('disabled', true);
            $spinner.addClass('is-active');
        } else {
            $submitBtn.prop('disabled', false);
            $previewBtn.prop('disabled', false);
            $spinner.removeClass('is-active');
        }
    }
    
    /**
     * Reset upload state
     */
    function resetUploadState() {
        $fileInput.val('');
        updateFileInfo(null);
        $previewSection.hide();
        currentFile = null;
        toggleButtons(false);
    }
    
    /**
     * Reset the form
     */
    function resetForm() {
        $form.trigger('reset');
        resetUploadState();
        $previewContent.empty();
    }
    
    /**
     * Initialize dismissible notices
     */
    function initDismissibleNotices() {
        $(document).on('click', '.notice-dismiss', function() {
            $(this).closest('.notice').fadeOut('slow', function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Prevent default drag behaviors
     */
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    /**
     * Highlight drop zone
     */
    function highlight() {
        $(this).addClass('highlight');
    }
    
    /**
     * Unhighlight drop zone
     */
    function unhighlight() {
        $(this).removeClass('highlight');
    }
    
    /**
     * Handle dropped files
     */
    function handleDrop(e) {
        const dt = e.originalEvent.dataTransfer;
        const files = dt.files;
        
        if (files.length) {
            $fileInput[0].files = files;
            updateFileLabel(files[0].name);
            validateFile(files[0]);
        }
    }
});
