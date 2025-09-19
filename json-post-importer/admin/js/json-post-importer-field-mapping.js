(function ($) {
    'use strict';

    // Performance optimization variables
    let fieldExtractionCache = new Map();
    let validationCache = new Map();
    let debouncedValidation = null;
    let performanceMetrics = {
        fieldExtractionTime: 0,
        validationTime: 0,
        renderTime: 0
    };

    // Initialize field mapping when the document is ready
    $(document).ready(function () {
        // Initialize field mapping handlers
        initializeFieldMappingHandlers();

        // Initialize performance monitoring
        initializePerformanceMonitoring();

        // Handle import form submission
        $('#jpi-confirm-import').on('click', function (e) {
            e.preventDefault();

            if (!validateFieldMapping()) {
                return false;
            }

            // Prepare the import data
            const importData = {
                action: 'jpi_start_import',
                nonce: jpi_vars.nonce,
                field_mapping: getFieldMappingData(),
                import_settings: getImportSettings()
            };

            // Start the import process
            startImport(importData);
        });

        // Set up debounced validation
        debouncedValidation = debounce(validateFieldMapping, 300);
    });

    // Initialize field mapping handlers
    function initializeFieldMappingHandlers() {
        // Initialize field selects
        $('.jpi-field-select').each(function () {
            initializeFieldSelectOptimized($(this));
        });

        // Handle field selection changes
        $(document).on('change', '.jpi-field-select', function () {
            const $select = $(this);
            const $preview = $select.closest('tr').find('.jpi-field-preview');
            const selectedOption = $select.find('option:selected');

            if (selectedOption.val()) {
                $preview.text(selectedOption.data('preview') || '');
            } else {
                $preview.text('—');
            }
        });

        // Add custom field
        $(document).on('click', '.jpi-add-custom-field', function () {
            const $table = $('#jpi-custom-fields tbody');
            const rowCount = $table.find('tr:not(.jpi-no-custom-fields)').length;

            // Remove empty message if present
            $table.find('.jpi-no-custom-fields').remove();

            const newRow = `
                <tr class="jpi-custom-field-row">
                    <td>
                        <input type="text" name="jpi_custom_fields[${rowCount}][key]" 
                               class="regular-text jpi-meta-key" 
                               placeholder="${jpi_vars.i18n.custom_field_placeholder || 'custom_field_name'}"
                               required>
                    </td>
                    <td>${renderFieldSelect(`custom_${rowCount}`, window.currentJsonData || [])}</td>
                    <td class="jpi-field-preview">—</td>
                    <td>
                        <button type="button" class="button button-link-delete jpi-remove-field">
                            ${jpi_vars.i18n.remove || 'Remove'}
                        </button>
                    </td>
                </tr>
            `;
            $table.append(newRow);

            // Initialize the new field select
            const $newRow = $table.find('tr:last');
            initializeFieldSelectOptimized($newRow.find('.jpi-field-select'));
        });

        // Add taxonomy mapping
        $(document).on('click', '.jpi-add-taxonomy', function () {
            const $table = $('#jpi-taxonomies tbody');
            const rowCount = $table.find('tr:not(.jpi-no-taxonomies)').length;

            // Remove empty message if present
            $table.find('.jpi-no-taxonomies').remove();

            const taxonomyOptions = renderTaxonomyOptions();
            const newRow = `
                <tr class="jpi-taxonomy-row">
                    <td>
                        <select name="jpi_taxonomies[${rowCount}][taxonomy]" class="jpi-taxonomy-select" required>
                            <option value="">-- ${jpi_vars.i18n.select_taxonomy || 'Select Taxonomy'} --</option>
                            ${taxonomyOptions}
                        </select>
                    </td>
                    <td>${renderFieldSelect(`taxonomy_${rowCount}`, window.currentJsonData || [])}</td>
                    <td class="jpi-field-preview">—</td>
                    <td>
                        <button type="button" class="button button-link-delete jpi-remove-field">
                            ${jpi_vars.i18n.remove || 'Remove'}
                        </button>
                    </td>
                </tr>
            `;
            $table.append(newRow);

            // Initialize the new field select
            const $newRow = $table.find('tr:last');
            initializeFieldSelectOptimized($newRow.find('.jpi-field-select'));
        });

        // Remove field
        $(document).on('click', '.jpi-remove-field', function () {
            const $row = $(this).closest('tr');
            const $table = $row.closest('table');

            $row.remove();

            // Show empty message if no rows left
            const $tbody = $table.find('tbody');
            const remainingRows = $tbody.find('tr:not(.jpi-no-custom-fields, .jpi-no-taxonomies)').length;

            if (remainingRows === 0) {
                const tableId = $table.attr('id');
                const emptyClass = tableId === 'jpi-custom-fields' ? 'jpi-no-custom-fields' : 'jpi-no-taxonomies';
                const message = tableId === 'jpi-custom-fields' ?
                    (jpi_vars.i18n.no_custom_fields || 'No custom fields added yet. Click "Add Custom Field" to get started.') :
                    (jpi_vars.i18n.no_taxonomies || 'No taxonomies added yet. Click "Add Taxonomy" to get started.');

                $tbody.append(`
                    <tr class="${emptyClass}">
                        <td colspan="4" style="text-align: center; color: #666; font-style: italic;">${message}</td>
                    </tr>
                `);
            }
        });

        // Handle mapping preset selection
        $(document).on('change', '#jpi-mapping-preset', function () {
            const presetName = $(this).val();
            if (presetName) {
                applyMappingPreset(presetName);
            }
        });

        // Handle field mapping validation with debouncing
        $(document).on('change', '.jpi-field-select, .jpi-meta-key, .jpi-taxonomy-select', function () {
            // Clear validation cache when fields change
            validationCache.clear();
            debouncedValidation();
        });

        // Handle input events for real-time validation (debounced)
        $(document).on('input', '.jpi-meta-key', debounce(function() {
            validationCache.clear();
            validateFieldMapping();
        }, 500));

        // Handle WordPress formatting
        $(document).on('click', '.jpi-format-wordpress', function () {
            if (window.currentJsonData) {
                formatToWordPressStandard();
            } else {
                alert(jpi_vars.i18n.no_data_loaded || 'No JSON data loaded. Please upload a file first.');
            }
        });

        // Handle clear all mappings
        $(document).on('click', '.jpi-clear-mappings', function () {
            if (confirm(jpi_vars.i18n.confirm_clear || 'Are you sure you want to clear all field mappings?')) {
                clearAllMappings();
                $('#jpi-mapping-preset').val('');
                validateFieldMapping();
            }
        });
    }

    /**
     * Render the field mapping interface (optimized)
     */
    function renderFieldMappingUI(jsonData) {
        const startTime = performance.now();
        const $container = $('#jpi-field-mapping-container');

        try {
            // Store the JSON data for use by other functions
            window.currentJsonData = jsonData;
            
            // Clear caches when new data is loaded
            fieldExtractionCache.clear();
            validationCache.clear();

            // Generate the field mapping HTML
            let html = `
                <div class="jpi-field-mapping">
                    <div class="jpi-mapping-header">
                        <h3>${jpi_vars.i18n.map_fields || 'Map Fields'}</h3>
                        <p class="description">${jpi_vars.i18n.map_fields_desc || 'Map JSON fields to WordPress post fields. Required fields are marked with an asterisk (*)'}</p>
                        
                        <div class="jpi-preset-section">
                            <label for="jpi-mapping-preset">${jpi_vars.i18n.mapping_presets || 'Quick Start Presets:'}</label>
                            <select id="jpi-mapping-preset" class="regular-text">
                                <option value="">-- ${jpi_vars.i18n.select_preset || 'Select a preset'} --</option>
                                ${renderPresetOptions()}
                            </select>
                            <button type="button" class="button button-primary jpi-format-wordpress" style="margin-left: 10px;">
                                <span class="dashicons dashicons-wordpress-alt"></span> ${jpi_vars.i18n.format_wordpress || 'Format to WordPress Standard'}
                            </button>
                            <button type="button" class="button jpi-clear-mappings">${jpi_vars.i18n.clear_all || 'Clear All'}</button>
                        </div>
                    </div>
                    
                    <div id="jpi-validation-feedback"></div>
                    
                    <div class="jpi-mapping-section">
                        <h4>${jpi_vars.i18n.standard_fields || 'Standard WordPress Fields'}</h4>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">${jpi_vars.i18n.wordpress_field || 'WordPress Field'}</th>
                                    <th style="width: 30%;">${jpi_vars.i18n.json_field || 'JSON Field'}</th>
                                    <th style="width: 45%;">${jpi_vars.i18n.preview || 'Preview'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${renderStandardFields(jsonData)}
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="jpi-mapping-section">
                        <div class="jpi-section-header">
                            <h4>${jpi_vars.i18n.custom_fields || 'Custom Fields'}</h4>
                            <button type="button" class="button button-secondary jpi-add-custom-field">
                                <span class="dashicons dashicons-plus-alt"></span> ${jpi_vars.i18n.add_custom_field || 'Add Custom Field'}
                            </button>
                        </div>
                        <table class="wp-list-table widefat fixed striped" id="jpi-custom-fields">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">${jpi_vars.i18n.meta_key || 'Meta Key'}</th>
                                    <th style="width: 30%;">${jpi_vars.i18n.json_field || 'JSON Field'}</th>
                                    <th style="width: 35%;">${jpi_vars.i18n.preview || 'Preview'}</th>
                                    <th style="width: 10%;">${jpi_vars.i18n.actions || 'Actions'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="jpi-no-custom-fields">
                                    <td colspan="4" style="text-align: center; color: #666; font-style: italic;">
                                        ${jpi_vars.i18n.no_custom_fields || 'No custom fields added yet. Click "Add Custom Field" to get started.'}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="jpi-mapping-section">
                        <div class="jpi-section-header">
                            <h4>${jpi_vars.i18n.taxonomies || 'Taxonomies'}</h4>
                            <button type="button" class="button button-secondary jpi-add-taxonomy">
                                <span class="dashicons dashicons-plus-alt"></span> ${jpi_vars.i18n.add_taxonomy || 'Add Taxonomy'}
                            </button>
                        </div>
                        <table class="wp-list-table widefat fixed striped" id="jpi-taxonomies">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">${jpi_vars.i18n.taxonomy || 'Taxonomy'}</th>
                                    <th style="width: 30%;">${jpi_vars.i18n.json_field || 'JSON Field'}</th>
                                    <th style="width: 35%;">${jpi_vars.i18n.preview || 'Preview'}</th>
                                    <th style="width: 10%;">${jpi_vars.i18n.actions || 'Actions'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="jpi-no-taxonomies">
                                    <td colspan="4" style="text-align: center; color: #666; font-style: italic;">
                                        ${jpi_vars.i18n.no_taxonomies || 'No taxonomies added yet. Click "Add Taxonomy" to get started.'}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            $container.html(html);

            // Initialize any interactive elements
            initializeFieldMappingHandlers();
            
            // Initialize field selects with optimization
            $('.jpi-field-select').each(function () {
                initializeFieldSelectOptimized($(this));
            });

            const endTime = performance.now();
            performanceMetrics.renderTime = endTime - startTime;
            console.log(`Field mapping UI rendered in ${performanceMetrics.renderTime.toFixed(2)}ms`);

        } catch (error) {
            console.error('Error rendering field mapping UI:', error);
            $container.html('<div class="notice notice-error"><p>' + (jpi_vars.i18n.error_loading_mapping || 'Error loading field mapping interface.') + '</p></div>');
        }
    }

    /**
     * Debounce function to limit function calls
     */
    function debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    }

    /**
     * Throttle function to limit function calls
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

    /**
     * Optimized field select initialization with virtual scrolling for large datasets
     */
    function initializeFieldSelectOptimized($select) {
        const jsonData = window.currentJsonData || [];
        const fields = extractFieldsFromJsonData(jsonData);
        const fieldEntries = Object.entries(fields);

        // Clear existing options except the first one
        $select.find('option:not(:first)').remove();

        // For large field sets, implement virtual scrolling or pagination
        if (fieldEntries.length > 100) {
            console.log(`Large field set detected (${fieldEntries.length} fields), implementing optimization`);
            
            // Add fields in batches to prevent UI blocking
            const batchSize = 50;
            let currentBatch = 0;
            
            const addBatch = () => {
                const start = currentBatch * batchSize;
                const end = Math.min(start + batchSize, fieldEntries.length);
                
                for (let i = start; i < end; i++) {
                    const [key, preview] = fieldEntries[i];
                    const displayText = preview && preview !== '—' ? `${key} (${preview})` : key;
                    $select.append(`<option value="${escapeHtml(key)}" data-preview="${escapeHtml(preview)}" title="${escapeHtml(preview)}">${escapeHtml(displayText)}</option>`);
                }
                
                currentBatch++;
                
                if (end < fieldEntries.length) {
                    // Use requestAnimationFrame for smooth UI updates
                    requestAnimationFrame(addBatch);
                }
            };
            
            addBatch();
        } else {
            // Add all fields at once for smaller sets
            fieldEntries.forEach(([key, preview]) => {
                const displayText = preview && preview !== '—' ? `${key} (${preview})` : key;
                $select.append(`<option value="${escapeHtml(key)}" data-preview="${escapeHtml(preview)}" title="${escapeHtml(preview)}">${escapeHtml(displayText)}</option>`);
            });
        }

        // Optimized change handler with throttling
        $select.off('change').on('change', throttle(function () {
            const $row = $(this).closest('tr');
            const $preview = $row.find('.jpi-field-preview');

            if ($(this).val()) {
                const preview = $(this).find('option:selected').data('preview') || $(this).find('option:selected').text();
                $preview.html(escapeHtml(preview));
            } else {
                $preview.html('—');
            }
        }, 100));
    }

    /**
     * Memory-efficient object flattening
     */
    function flattenObjectOptimized(obj, prefix = '', maxDepth = 5, currentDepth = 0) {
        if (currentDepth >= maxDepth) {
            return { [prefix || 'deep_object']: `{nested object, depth > ${maxDepth}}` };
        }

        return Object.keys(obj).reduce((acc, k) => {
            const pre = prefix.length ? prefix + '.' : '';

            if (obj[k] !== null && typeof obj[k] === 'object' && !Array.isArray(obj[k])) {
                Object.assign(acc, flattenObjectOptimized(obj[k], pre + k, maxDepth, currentDepth + 1));
            } else {
                acc[pre + k] = obj[k];
            }

            return acc;
        }, {});
    }

    /**
     * Get performance metrics
     */
    function getPerformanceMetrics() {
        return {
            ...performanceMetrics,
            cacheStats: {
                fieldExtractionCacheSize: fieldExtractionCache.size,
                validationCacheSize: validationCache.size
            },
            memoryUsage: performance.memory ? {
                used: Math.round(performance.memory.usedJSHeapSize / 1024 / 1024) + ' MB',
                total: Math.round(performance.memory.totalJSHeapSize / 1024 / 1024) + ' MB',
                limit: Math.round(performance.memory.jsHeapSizeLimit / 1024 / 1024) + ' MB'
            } : 'Not available'
        };
    }

    /**
     * Render standard WordPress fields
     */
    function renderStandardFields(jsonData) {
        const standardFields = [
            {
                id: 'post_title',
                label: jpi_vars.i18n.post_title || 'Post Title',
                required: true
            },
            {
                id: 'post_content',
                label: jpi_vars.i18n.post_content || 'Post Content'
            },
            {
                id: 'post_excerpt',
                label: jpi_vars.i18n.post_excerpt || 'Post Excerpt'
            },
            {
                id: 'post_status',
                label: jpi_vars.i18n.post_status || 'Post Status',
                type: 'select',
                options: {
                    'publish': jpi_vars.i18n.publish || 'Publish',
                    'draft': jpi_vars.i18n.draft || 'Draft',
                    'pending': jpi_vars.i18n.pending || 'Pending Review',
                    'private': jpi_vars.i18n.private || 'Private'
                }
            },
            {
                id: 'post_date',
                label: jpi_vars.i18n.post_date || 'Post Date'
            },
            {
                id: 'post_author',
                label: jpi_vars.i18n.post_author || 'Post Author',
                type: 'select',
                options: jpi_vars.authors || {}
            },
            {
                id: 'post_name',
                label: 'Post Slug',
                description: 'URL-friendly version of the title'
            }
        ];

        let html = '';

        standardFields.forEach(field => {
            const fields = extractFieldsFromJsonData(jsonData);
            const fieldValue = getFieldPreview(fields, field.id);
            const fieldId = `jpi-field-${field.id}`;

            html += `
                <tr class="jpi-mapping-row" data-field-type="standard" data-field-id="${field.id}">
                    <td>
                        <label for="${fieldId}" class="jpi-field-label">
                            ${field.label}
                            ${field.required ? '<span class="required">*</span>' : ''}
                        </label>
                        ${field.description ? `<p class="description">${field.description}</p>` : ''}
                    </td>
                    <td>
                        ${renderFieldSelect(field.id, jsonData, field.type, field.options)}
                    </td>
                    <td class="jpi-preview-cell">
                        <div class="jpi-field-preview">${fieldValue || '—'}</div>
                    </td>
                </tr>
            `;
        });

        return html;
    }

    /**
     * Render a select field for field mapping
     */
    function renderFieldSelect(fieldId, jsonData, type = 'text', options = null) {
        let html = `<select name="jpi_field_mapping[${fieldId}]" class="jpi-field-select" data-type="${type}">
            <option value="">-- ${jpi_vars.i18n.select_field || 'Select Field'} --</option>`;

        // Add field options
        if (options) {
            // Add custom options if provided (for status, author, etc.)
            for (const [value, label] of Object.entries(options)) {
                html += `<option value="${value}">${escapeHtml(label)}</option>`;
            }
        } else {
            // Add fields from the JSON data
            const fields = extractFieldsFromJsonData(jsonData || window.currentJsonData || []);

            for (const [key, preview] of Object.entries(fields)) {
                // Show both field name and preview in the option text
                const displayText = preview && preview !== '—' ? `${key} (${preview})` : key;
                html += `<option value="${escapeHtml(key)}" data-preview="${escapeHtml(preview)}" title="${escapeHtml(preview)}">${escapeHtml(displayText)}</option>`;
            }
        }

        return html + '</select>';
    }

    /**
     * Get a preview of a field value
     */
    function getFieldPreview(fields, fieldId) {
        if (!fields || !fields[fieldId]) return '—';

        const value = fields[fieldId];

        if (value === null || value === undefined) {
            return '—';
        }

        if (typeof value === 'string') {
            return value.length > 50 ? value.substring(0, 50) + '...' : value;
        }

        if (Array.isArray(value)) {
            return '[' + value.length + ' items]';
        }

        if (typeof value === 'object') {
            return '{' + Object.keys(value).length + ' fields}';
        }

        return String(value);
    }

    /**
     * Extract fields from JSON data with sample values (optimized with caching)
     */
    function extractFieldsFromJsonData(jsonData) {
        const startTime = performance.now();
        
        // Create cache key based on data structure
        const cacheKey = generateCacheKey(jsonData);
        
        // Check cache first
        if (fieldExtractionCache.has(cacheKey)) {
            const cachedResult = fieldExtractionCache.get(cacheKey);
            console.log('Using cached field extraction result');
            return cachedResult;
        }

        const fields = {};

        // Ensure we have an array to work with
        const items = Array.isArray(jsonData) ? jsonData : [jsonData];

        if (items.length === 0) {
            console.log('No items found in JSON data');
            return fields;
        }

        // Optimize by processing fewer items for large datasets
        const sampleSize = Math.min(items.length > 1000 ? 3 : 5, items.length);
        console.log(`Processing ${sampleSize} of ${items.length} items for field extraction`);

        // Use requestIdleCallback for non-blocking processing if available
        const processItems = () => {
            items.slice(0, sampleSize).forEach(function (item, index) {
                if (typeof item === 'object' && item !== null) {
                    const flattenedItem = flattenObjectOptimized(item);

                    for (const [key, value] of Object.entries(flattenedItem)) {
                        // If we haven't seen this field before, or if this is a better sample
                        if (!fields[key] || (typeof value === 'string' && value.length > 0)) {
                            fields[key] = generateFieldPreview(value);
                        }
                    }
                }
            });
        };

        // Process items
        processItems();

        // Cache the result
        fieldExtractionCache.set(cacheKey, fields);
        
        // Limit cache size to prevent memory leaks
        if (fieldExtractionCache.size > 10) {
            const firstKey = fieldExtractionCache.keys().next().value;
            fieldExtractionCache.delete(firstKey);
        }

        const endTime = performance.now();
        performanceMetrics.fieldExtractionTime = endTime - startTime;
        
        console.log(`Field extraction completed in ${performanceMetrics.fieldExtractionTime.toFixed(2)}ms`);
        console.log('Extracted fields:', fields);
        
        return fields;
    }

    /**
     * Generate a cache key for JSON data
     */
    function generateCacheKey(jsonData) {
        if (!jsonData) return 'empty';
        
        const items = Array.isArray(jsonData) ? jsonData : [jsonData];
        if (items.length === 0) return 'empty';
        
        // Create a simple hash based on the structure of the first item
        const firstItem = items[0];
        if (typeof firstItem === 'object' && firstItem !== null) {
            const keys = Object.keys(firstItem).sort().join(',');
            return `struct_${keys}_${items.length}`;
        }
        
        return `simple_${typeof firstItem}_${items.length}`;
    }

    /**
     * Generate field preview with optimized formatting
     */
    function generateFieldPreview(value) {
        if (value === null || value === undefined) {
            return '—';
        }

        if (typeof value === 'string') {
            return value.length > 50 ? value.substring(0, 50) + '...' : value;
        }

        if (Array.isArray(value)) {
            return `[${value.length} items]`;
        }

        if (typeof value === 'object') {
            return `{${Object.keys(value).length} fields}`;
        }

        return String(value);
    }

    /**
     * Initialize performance monitoring
     */
    function initializePerformanceMonitoring() {
        // Monitor memory usage if available
        if (performance.memory) {
            setInterval(() => {
                const memoryInfo = performance.memory;
                if (memoryInfo.usedJSHeapSize > memoryInfo.jsHeapSizeLimit * 0.9) {
                    console.warn('High memory usage detected, clearing caches');
                    clearCaches();
                }
            }, 30000); // Check every 30 seconds
        }

        // Log performance metrics periodically
        setInterval(() => {
            if (performanceMetrics.fieldExtractionTime > 0) {
                console.log('Performance metrics:', performanceMetrics);
            }
        }, 60000); // Log every minute
    }

    /**
     * Clear all caches to free memory
     */
    function clearCaches() {
        fieldExtractionCache.clear();
        validationCache.clear();
        console.log('Caches cleared to free memory');
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
     * Validate the field mapping form (optimized with caching)
     */
    function validateFieldMapping() {
        const startTime = performance.now();
        
        // Generate validation cache key based on current form state
        const validationKey = generateValidationCacheKey();
        
        // Check cache first
        if (validationCache.has(validationKey)) {
            const cachedResult = validationCache.get(validationKey);
            applyValidationResult(cachedResult);
            return cachedResult.isValid;
        }

        let isValid = true;
        const errors = [];
        const warnings = [];

        // Clear previous validation states
        $('.jpi-validation-error').removeClass('jpi-validation-error');
        $('.jpi-field-error').removeClass('jpi-field-error');

        // Check if post_title is mapped (required)
        const $titleSelect = $('select[name="jpi_field_mapping[post_title]"]');
        const titleMapping = $titleSelect.val();
        if (!titleMapping) {
            isValid = false;
            errors.push({
                message: jpi_vars.i18n.title_required || 'Post title mapping is required.',
                field: 'post_title',
                type: 'required'
            });
            $titleSelect.closest('tr').addClass('jpi-validation-error jpi-field-error');
        }

        // Check for content mapping (recommended but not required)
        const contentMapping = $('select[name="jpi_field_mapping[post_content]"]').val();
        if (!contentMapping) {
            warnings.push({
                message: jpi_vars.i18n.content_recommended || 'Post content mapping is recommended for complete posts.',
                field: 'post_content',
                type: 'warning'
            });
        }

        // Validate custom field mappings
        let customFieldIndex = 0;
        $('#jpi-custom-fields tbody tr:not(.jpi-no-custom-fields)').each(function () {
            const $row = $(this);
            const $metaKeyInput = $row.find('.jpi-meta-key');
            const $fieldSelect = $row.find('.jpi-field-select');
            const metaKey = $metaKeyInput.val().trim();
            const fieldValue = $fieldSelect.val();

            // Check for incomplete mappings
            if (metaKey && !fieldValue) {
                isValid = false;
                errors.push({
                    message: (jpi_vars.i18n.custom_field_incomplete || 'Custom field "{field}" needs a JSON field mapping.').replace('{field}', metaKey),
                    field: `custom_field_${customFieldIndex}`,
                    type: 'incomplete'
                });
                $row.addClass('jpi-validation-error jpi-field-error');
                $fieldSelect.addClass('jpi-field-error');
            } else if (!metaKey && fieldValue) {
                isValid = false;
                errors.push({
                    message: jpi_vars.i18n.meta_key_required || 'Meta key is required for custom field mapping.',
                    field: `custom_field_${customFieldIndex}`,
                    type: 'incomplete'
                });
                $row.addClass('jpi-validation-error jpi-field-error');
                $metaKeyInput.addClass('jpi-field-error');
            }

            // Check for invalid meta key format
            if (metaKey && !/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(metaKey)) {
                isValid = false;
                errors.push({
                    message: (jpi_vars.i18n.invalid_meta_key || 'Meta key "{field}" contains invalid characters. Use only letters, numbers, and underscores.').replace('{field}', metaKey),
                    field: `custom_field_${customFieldIndex}`,
                    type: 'format'
                });
                $row.addClass('jpi-validation-error jpi-field-error');
                $metaKeyInput.addClass('jpi-field-error');
            }

            // Check for duplicate meta keys
            const duplicateMetaKey = $('#jpi-custom-fields tbody tr:not(.jpi-no-custom-fields)').not($row).find('.jpi-meta-key').filter(function () {
                return $(this).val().trim() === metaKey && metaKey !== '';
            });

            if (duplicateMetaKey.length > 0 && metaKey) {
                isValid = false;
                errors.push({
                    message: (jpi_vars.i18n.duplicate_meta_key || 'Meta key "{field}" is used multiple times. Each meta key must be unique.').replace('{field}', metaKey),
                    field: `custom_field_${customFieldIndex}`,
                    type: 'duplicate'
                });
                $row.addClass('jpi-validation-error jpi-field-error');
                $metaKeyInput.addClass('jpi-field-error');
            }

            customFieldIndex++;
        });

        // Validate taxonomy mappings
        let taxonomyIndex = 0;
        $('#jpi-taxonomies tbody tr:not(.jpi-no-taxonomies)').each(function () {
            const $row = $(this);
            const $taxonomySelect = $row.find('.jpi-taxonomy-select');
            const $fieldSelect = $row.find('.jpi-field-select');
            const taxonomy = $taxonomySelect.val();
            const fieldValue = $fieldSelect.val();

            if (taxonomy && !fieldValue) {
                isValid = false;
                errors.push({
                    message: (jpi_vars.i18n.taxonomy_field_incomplete || 'Taxonomy "{taxonomy}" needs a JSON field mapping.').replace('{taxonomy}', $taxonomySelect.find('option:selected').text()),
                    field: `taxonomy_${taxonomyIndex}`,
                    type: 'incomplete'
                });
                $row.addClass('jpi-validation-error jpi-field-error');
                $fieldSelect.addClass('jpi-field-error');
            } else if (!taxonomy && fieldValue) {
                isValid = false;
                errors.push({
                    message: jpi_vars.i18n.taxonomy_required || 'Taxonomy selection is required for taxonomy mapping.',
                    field: `taxonomy_${taxonomyIndex}`,
                    type: 'incomplete'
                });
                $row.addClass('jpi-validation-error jpi-field-error');
                $taxonomySelect.addClass('jpi-field-error');
            }

            // Check for duplicate taxonomy mappings
            const duplicateTaxonomy = $('#jpi-taxonomies tbody tr:not(.jpi-no-taxonomies)').not($row).find('.jpi-taxonomy-select').filter(function () {
                return $(this).val() === taxonomy && taxonomy !== '';
            });

            if (duplicateTaxonomy.length > 0 && taxonomy) {
                isValid = false;
                errors.push({
                    message: (jpi_vars.i18n.duplicate_taxonomy || 'Taxonomy "{taxonomy}" is mapped multiple times. Each taxonomy should only be mapped once.').replace('{taxonomy}', $taxonomySelect.find('option:selected').text()),
                    field: `taxonomy_${taxonomyIndex}`,
                    type: 'duplicate'
                });
                $row.addClass('jpi-validation-error jpi-field-error');
                $taxonomySelect.addClass('jpi-field-error');
            }

            taxonomyIndex++;
        });

        // Check if any fields are mapped at all
        const hasMappings = $('.jpi-field-select').filter(function () {
            return $(this).val() !== '';
        }).length > 0;

        if (!hasMappings) {
            isValid = false;
            errors.push({
                message: jpi_vars.i18n.no_mappings || 'At least one field mapping is required to proceed with import.',
                field: 'general',
                type: 'required'
            });
        }

        // Create validation result object
        const validationResult = {
            isValid,
            errors,
            warnings,
            timestamp: Date.now()
        };

        // Cache the result
        validationCache.set(validationKey, validationResult);
        
        // Limit cache size
        if (validationCache.size > 5) {
            const firstKey = validationCache.keys().next().value;
            validationCache.delete(firstKey);
        }

        // Apply validation result
        applyValidationResult(validationResult);

        const endTime = performance.now();
        performanceMetrics.validationTime = endTime - startTime;

        return isValid;
    }

    /**
     * Generate validation cache key based on form state
     */
    function generateValidationCacheKey() {
        const formData = [];
        
        // Standard field mappings
        $('.jpi-field-select').each(function() {
            formData.push(`${$(this).attr('name')}:${$(this).val()}`);
        });
        
        // Custom field mappings
        $('#jpi-custom-fields tbody tr:not(.jpi-no-custom-fields)').each(function() {
            const metaKey = $(this).find('.jpi-meta-key').val();
            const fieldValue = $(this).find('.jpi-field-select').val();
            formData.push(`custom:${metaKey}:${fieldValue}`);
        });
        
        // Taxonomy mappings
        $('#jpi-taxonomies tbody tr:not(.jpi-no-taxonomies)').each(function() {
            const taxonomy = $(this).find('.jpi-taxonomy-select').val();
            const fieldValue = $(this).find('.jpi-field-select').val();
            formData.push(`taxonomy:${taxonomy}:${fieldValue}`);
        });
        
        return formData.join('|');
    }

    /**
     * Apply validation result to the UI
     */
    function applyValidationResult(result) {
        const { isValid, errors, warnings } = result;
        
        // Update validation feedback
        updateValidationFeedback(isValid, errors, warnings);

        // Enable/disable import button
        $('.jpi-import-button, #jpi-confirm-import').prop('disabled', !isValid);

        // Update button text based on validation state
        if (isValid) {
            $('.jpi-import-button, #jpi-confirm-import').removeClass('button-disabled').addClass('button-primary');
        } else {
            $('.jpi-import-button, #jpi-confirm-import').removeClass('button-primary').addClass('button-disabled');
        }
    }

    /**
     * Get the field mapping data from the form
     */
    function getFieldMappingData() {
        const mapping = {
            standard: {},
            custom: [],
            taxonomies: []
        };

        // Get standard field mappings
        $('select[name^="jpi_field_mapping"]').each(function () {
            const fieldName = $(this).attr('name').match(/\[([^\]]+)\]/)[1];
            const fieldValue = $(this).val();
            if (fieldValue) {
                mapping.standard[fieldName] = fieldValue;
            }
        });

        // Get custom field mappings
        $('#jpi-custom-fields tbody tr:not(.jpi-no-custom-fields)').each(function () {
            const $row = $(this);
            const metaKey = $row.find('.jpi-meta-key').val().trim();
            const fieldValue = $row.find('.jpi-field-select').val();
            
            if (metaKey && fieldValue) {
                mapping.custom.push({
                    meta_key: metaKey,
                    json_field: fieldValue
                });
            }
        });

        // Get taxonomy mappings
        $('#jpi-taxonomies tbody tr:not(.jpi-no-taxonomies)').each(function () {
            const $row = $(this);
            const taxonomy = $row.find('.jpi-taxonomy-select').val();
            const fieldValue = $row.find('.jpi-field-select').val();
            
            if (taxonomy && fieldValue) {
                mapping.taxonomies.push({
                    taxonomy: taxonomy,
                    json_field: fieldValue
                });
            }
        });

        return mapping;
    }

    /**
     * Get import settings from the form
     */
    function getImportSettings() {
        return {
            post_type: $('#jpi-post-type').val() || 'post',
            post_status: $('#jpi-default-status').val() || 'draft',
            batch_size: parseInt($('#jpi-batch-size').val()) || 10,
            update_existing: $('#jpi-update-existing').is(':checked'),
            skip_duplicates: $('#jpi-skip-duplicates').is(':checked'),
            import_media: $('#jpi-import-media').is(':checked')
        };
    }

    /**
     * Render taxonomy options for dropdown
     */
    function renderTaxonomyOptions() {
        let html = '';
        
        if (jpi_vars.taxonomies) {
            for (const [key, label] of Object.entries(jpi_vars.taxonomies)) {
                html += `<option value="${escapeHtml(key)}">${escapeHtml(label)}</option>`;
            }
        }
        
        return html;
    }

    /**
     * Render preset options for quick mapping
     */
    function renderPresetOptions() {
        const presets = {
            'wordpress_export': jpi_vars.i18n.wordpress_export || 'WordPress Export',
            'blog_posts': jpi_vars.i18n.blog_posts || 'Blog Posts',
            'products': jpi_vars.i18n.products || 'Products',
            'events': jpi_vars.i18n.events || 'Events',
            'custom': jpi_vars.i18n.custom || 'Custom Structure'
        };

        let html = '';
        for (const [key, label] of Object.entries(presets)) {
            html += `<option value="${key}">${escapeHtml(label)}</option>`;
        }
        
        return html;
    }

    /**
     * Apply a mapping preset
     */
    function applyMappingPreset(presetName) {
        // Clear existing mappings first
        clearAllMappings();

        const presets = {
            'wordpress_export': {
                'post_title': 'title',
                'post_content': 'content',
                'post_excerpt': 'excerpt',
                'post_status': 'status',
                'post_date': 'date',
                'post_author': 'author'
            },
            'blog_posts': {
                'post_title': 'title',
                'post_content': 'content',
                'post_excerpt': 'summary',
                'post_status': 'published',
                'post_date': 'published_date'
            },
            'products': {
                'post_title': 'name',
                'post_content': 'description',
                'post_excerpt': 'short_description',
                'post_status': 'status'
            },
            'events': {
                'post_title': 'event_name',
                'post_content': 'description',
                'post_date': 'event_date'
            }
        };

        const preset = presets[presetName];
        if (!preset) return;

        // Apply standard field mappings
        for (const [wpField, jsonField] of Object.entries(preset)) {
            const $select = $(`select[name="jpi_field_mapping[${wpField}]"]`);
            if ($select.length && $select.find(`option[value="${jsonField}"]`).length) {
                $select.val(jsonField).trigger('change');
            }
        }

        // Auto-detect and suggest additional mappings based on field names
        autoDetectFieldMappings();
        
        // Validate after applying preset
        validateFieldMapping();
    }

    /**
     * Auto-detect field mappings based on field names
     */
    function autoDetectFieldMappings() {
        if (!window.currentJsonData || !Array.isArray(window.currentJsonData)) return;

        const fields = extractFieldsFromJsonData(window.currentJsonData);
        const fieldNames = Object.keys(fields);

        // Common field name patterns
        const patterns = {
            'post_title': ['title', 'name', 'heading', 'subject'],
            'post_content': ['content', 'body', 'description', 'text'],
            'post_excerpt': ['excerpt', 'summary', 'abstract', 'intro'],
            'post_date': ['date', 'created', 'published', 'timestamp'],
            'post_author': ['author', 'creator', 'user', 'by']
        };

        // Apply auto-detection for unmapped standard fields
        for (const [wpField, keywords] of Object.entries(patterns)) {
            const $select = $(`select[name="jpi_field_mapping[${wpField}]"]`);
            
            if ($select.length && !$select.val()) {
                // Find best matching field
                const match = fieldNames.find(fieldName => {
                    const lowerFieldName = fieldName.toLowerCase();
                    return keywords.some(keyword => 
                        lowerFieldName.includes(keyword) || 
                        lowerFieldName === keyword
                    );
                });

                if (match) {
                    $select.val(match).trigger('change');
                }
            }
        }
    }

    /**
     * Clear all field mappings
     */
    function clearAllMappings() {
        // Clear standard field mappings
        $('.jpi-field-select').val('').trigger('change');
        
        // Remove custom field rows
        $('#jpi-custom-fields tbody tr:not(.jpi-no-custom-fields)').remove();
        $('#jpi-custom-fields tbody').append(`
            <tr class="jpi-no-custom-fields">
                <td colspan="4" style="text-align: center; color: #666; font-style: italic;">
                    ${jpi_vars.i18n.no_custom_fields || 'No custom fields added yet. Click "Add Custom Field" to get started.'}
                </td>
            </tr>
        `);
        
        // Remove taxonomy rows
        $('#jpi-taxonomies tbody tr:not(.jpi-no-taxonomies)').remove();
        $('#jpi-taxonomies tbody').append(`
            <tr class="jpi-no-taxonomies">
                <td colspan="4" style="text-align: center; color: #666; font-style: italic;">
                    ${jpi_vars.i18n.no_taxonomies || 'No taxonomies added yet. Click "Add Taxonomy" to get started.'}
                </td>
            </tr>
        `);
    }

    /**
     * Update validation feedback display
     */
    function updateValidationFeedback(isValid, errors, warnings) {
        const $feedback = $('#jpi-validation-feedback');
        
        if (errors.length === 0 && warnings.length === 0) {
            $feedback.empty();
            return;
        }

        let html = '';

        // Display errors
        if (errors.length > 0) {
            html += `
                <div class="notice notice-error jpi-validation-notice">
                    <p><strong><span class="dashicons dashicons-warning"></span> ${jpi_vars.i18n.validation_errors || 'Validation Errors'} <span class="jpi-validation-counter">${errors.length}</span></strong></p>
                    <ul class="jpi-error-list">
            `;
            
            errors.forEach(error => {
                html += `<li class="jpi-error-item" data-field="${error.field}"><span class="dashicons dashicons-dismiss"></span> ${error.message}</li>`;
            });
            
            html += '</ul></div>';
        }

        // Display warnings
        if (warnings.length > 0) {
            html += `
                <div class="notice notice-warning jpi-validation-notice">
                    <p><strong><span class="dashicons dashicons-info"></span> ${jpi_vars.i18n.validation_warnings || 'Recommendations'} <span class="jpi-validation-counter warning">${warnings.length}</span></strong></p>
                    <ul class="jpi-warning-list">
            `;
            
            warnings.forEach(warning => {
                html += `<li class="jpi-warning-item" data-field="${warning.field}"><span class="dashicons dashicons-info"></span> ${warning.message}</li>`;
            });
            
            html += '</ul></div>';
        }

        $feedback.html(html);

        // Add click handlers to highlight fields
        $feedback.find('.jpi-error-item, .jpi-warning-item').on('click', function() {
            const fieldId = $(this).data('field');
            highlightField(fieldId);
        });
    }

    /**
     * Highlight a specific field for user attention
     */
    function highlightField(fieldId) {
        // Remove existing highlights
        $('.jpi-field-highlight').removeClass('jpi-field-highlight');

        let $target;

        if (fieldId === 'general') {
            $target = $('.jpi-field-mapping');
        } else if (fieldId.startsWith('custom_field_')) {
            const index = parseInt(fieldId.replace('custom_field_', ''));
            $target = $('#jpi-custom-fields tbody tr:not(.jpi-no-custom-fields)').eq(index);
        } else if (fieldId.startsWith('taxonomy_')) {
            const index = parseInt(fieldId.replace('taxonomy_', ''));
            $target = $('#jpi-taxonomies tbody tr:not(.jpi-no-taxonomies)').eq(index);
        } else {
            $target = $(`select[name="jpi_field_mapping[${fieldId}]"]`).closest('tr');
        }

        if ($target && $target.length) {
            $target.addClass('jpi-field-highlight');
            
            // Scroll to the field
            $('html, body').animate({
                scrollTop: $target.offset().top - 100
            }, 500);

            // Remove highlight after animation
            setTimeout(() => {
                $target.removeClass('jpi-field-highlight');
            }, 3000);
        }
    }

    /**
     * Start the import process
     */
    function startImport(importData) {
        // Show loading state
        $('#jpi-confirm-import').prop('disabled', true).text(jpi_vars.i18n.importing || 'Importing...');
        
        // Create progress container if it doesn't exist
        if (!$('#jpi-import-progress').length) {
            $('#jpi-field-mapping-container').after(`
                <div id="jpi-import-progress" class="jpi-import-progress" style="display: none;">
                    <h4>${jpi_vars.i18n.import_progress || 'Import Progress'}</h4>
                    <div class="jpi-progress-bar">
                        <div class="jpi-progress-fill" style="width: 0%;"></div>
                    </div>
                    <div class="jpi-progress-text">0%</div>
                    <div id="jpi-import-status"></div>
                </div>
            `);
        }
        
        $('#jpi-import-progress').show();

        // Start the AJAX import process
        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: importData,
            success: function(response) {
                if (response.success) {
                    handleImportSuccess(response.data);
                } else {
                    handleImportError(response.data);
                }
            },
            error: function(xhr, status, error) {
                handleImportError({
                    message: jpi_vars.i18n.import_failed || 'Import failed due to a network error.',
                    details: error
                });
            },
            complete: function() {
                $('#jpi-confirm-import').prop('disabled', false).text(jpi_vars.i18n.start_import || 'Start Import');
            }
        });
    }

    /**
     * Handle successful import response
     */
    function handleImportSuccess(data) {
        $('#jpi-import-progress .jpi-progress-fill').css('width', '100%');
        $('#jpi-import-progress .jpi-progress-text').text('100%');
        
        const statusHtml = `
            <div class="notice notice-success">
                <p><strong>${jpi_vars.i18n.import_complete || 'Import Complete!'}</strong></p>
                <ul>
                    <li>${jpi_vars.i18n.posts_created || 'Posts created'}: ${data.created || 0}</li>
                    <li>${jpi_vars.i18n.posts_updated || 'Posts updated'}: ${data.updated || 0}</li>
                    <li>${jpi_vars.i18n.posts_skipped || 'Posts skipped'}: ${data.skipped || 0}</li>
                </ul>
            </div>
        `;
        
        $('#jpi-import-status').html(statusHtml);
    }

    /**
     * Handle import error response
     */
    function handleImportError(data) {
        const statusHtml = `
            <div class="notice notice-error">
                <p><strong>${jpi_vars.i18n.import_error || 'Import Error'}</strong></p>
                <p>${data.message || jpi_vars.i18n.unknown_error || 'An unknown error occurred.'}</p>
                ${data.details ? `<p><small>${data.details}</small></p>` : ''}
            </div>
        `;
        
        $('#jpi-import-status').html(statusHtml);
    }

    /**
     * Format JSON data to WordPress standards
     */
    function formatToWordPressStandard() {
        if (!window.currentJsonData) {
            console.error('No JSON data available for formatting');
            return;
        }

        // Show loading state
        const $button = $('.jpi-format-wordpress');
        const originalText = $button.html();
        $button.prop('disabled', true).html('<span class="spinner is-active"></span> ' + (jpi_vars.i18n.formatting || 'Formatting...'));

        // Get formatting options
        const options = {
            auto_detect_fields: true,
            generate_seo_meta: true,
            create_excerpts: true,
            generate_slugs: true,
            format_content: true,
            detect_featured_images: true,
            process_taxonomies: true,
            add_schema_markup: true
        };

        $.ajax({
            url: jpi_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'jpi_get_wordpress_suggestions',
                nonce: jpi_vars.nonce,
                json_data: JSON.stringify(window.currentJsonData),
                options: options
            },
            success: function(response) {
                if (response.success) {
                    applyWordPressSuggestions(response.data.suggestions);
                    showWordPressFormatNotification('success', response.data.message || 'WordPress formatting applied successfully!');
                } else {
                    showWordPressFormatNotification('error', response.data || 'Failed to format data to WordPress standards.');
                }
            },
            error: function(xhr, status, error) {
                console.error('WordPress formatting error:', error);
                showWordPressFormatNotification('error', 'Network error occurred while formatting data.');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    }

    /**
     * Apply WordPress mapping suggestions
     */
    function applyWordPressSuggestions(suggestions) {
        console.log('Applying WordPress suggestions:', suggestions);

        // Clear existing mappings first
        clearAllMappings();

        // Apply standard field suggestions
        if (suggestions.standard) {
            for (const [wpField, jsonField] of Object.entries(suggestions.standard)) {
                const $select = $(`select[name="jpi_field_mapping[${wpField}]"]`);
                if ($select.length && jsonField) {
                    $select.val(jsonField).trigger('change');
                    console.log(`Mapped ${wpField} to ${jsonField}`);
                }
            }
        }

        // Apply SEO field suggestions as custom fields
        if (suggestions.seo) {
            for (const [seoField, jsonField] of Object.entries(suggestions.seo)) {
                if (jsonField) {
                    addCustomFieldMapping(seoField, jsonField);
                    console.log(`Added SEO mapping: ${seoField} to ${jsonField}`);
                }
            }
        }

        // Apply taxonomy suggestions
        if (suggestions.taxonomies) {
            for (const [taxonomy, jsonField] of Object.entries(suggestions.taxonomies)) {
                if (jsonField) {
                    addTaxonomyMapping(taxonomy, jsonField);
                    console.log(`Added taxonomy mapping: ${taxonomy} to ${jsonField}`);
                }
            }
        }

        // Validate after applying suggestions
        validateFieldMapping();
    }

    /**
     * Add custom field mapping
     */
    function addCustomFieldMapping(metaKey, jsonField) {
        // Click add custom field button
        $('.jpi-add-custom-field').trigger('click');
        
        // Set the values in the last added row
        const $lastRow = $('#jpi-custom-fields tbody tr:last');
        $lastRow.find('.jpi-meta-key').val(metaKey);
        $lastRow.find('.jpi-field-select').val(jsonField).trigger('change');
    }

    /**
     * Add taxonomy mapping
     */
    function addTaxonomyMapping(taxonomy, jsonField) {
        // Click add taxonomy button
        $('.jpi-add-taxonomy').trigger('click');
        
        // Set the values in the last added row
        const $lastRow = $('#jpi-taxonomies tbody tr:last');
        $lastRow.find('.jpi-taxonomy-select').val(taxonomy);
        $lastRow.find('.jpi-field-select').val(jsonField).trigger('change');
    }

    /**
     * Show WordPress formatting notification
     */
    function showWordPressFormatNotification(type, message) {
        const $notification = $(`
            <div class="notice notice-${type} is-dismissible jpi-wordpress-notification">
                <p><strong><span class="dashicons dashicons-wordpress-alt"></span> WordPress Formatting:</strong> ${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);

        // Add the notification to the field mapping container
        $('#jpi-field-mapping-container').prepend($notification);

        // Auto-dismiss after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }

        // Handle manual dismiss
        $notification.on('click', '.notice-dismiss', function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        });
    }

    // Export functions for global access if needed
    window.jpiFieldMapping = {
        renderFieldMappingUI,
        validateFieldMapping,
        getFieldMappingData,
        extractFieldsFromJsonData,
        applyMappingPreset,
        clearAllMappings,
        clearCaches,
        getPerformanceMetrics,
        initializeFieldSelectOptimized,
        formatToWordPressStandard
    };

})(jQuery);