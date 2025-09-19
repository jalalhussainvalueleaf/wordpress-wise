(function($) {
    'use strict';

    // Field Mapping Module
    window.FieldMapping = {
        // Default field types
        fieldTypes: {
            'post_title': 'text',
            'post_content': 'textarea',
            'post_excerpt': 'textarea',
            'post_status': 'select',
            'post_date': 'datetime',
            'post_author': 'select',
            'post_category': 'category',
            'post_tag': 'tag',
            'featured_image': 'media',
            'custom_field': 'text'
        },

        // Default WordPress post fields
        standardFields: [
            'post_title',
            'post_content',
            'post_excerpt',
            'post_status',
            'post_date',
            'post_author',
            'post_category',
            'post_tag',
            'featured_image'
        ],

        // Current JSON data
        jsonData: null,

        /**
         * Initialize the field mapping functionality
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initializeFieldMapping();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$container = $('#jpi-field-mapping-container');
            this.$standardFieldsTable = $('#jpi-standard-fields tbody');
            this.$customFieldsTable = $('#jpi-custom-fields tbody');
            this.$saveButton = $('#jpi-save-mapping');
            this.$addCustomFieldBtn = $('#jpi-add-custom-field');
            this.$loading = $('#jpi-field-mapping-loading');
            this.$mappingContent = $('#jpi-field-mapping-content');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add custom field
            this.$addCustomFieldBtn.on('click', this.addCustomField.bind(this));
            
            // Remove field
            this.$container.on('click', '.jpi-remove-field', this.removeField.bind(this));
            
            // Save mapping
            this.$saveButton.on('click', this.saveMapping.bind(this));
            
            // Field selection change
            this.$container.on('change', '.jpi-field-select', this.updateFieldPreview.bind(this));
            
            // Custom field key input
            this.$container.on('input', '.jpi-custom-field-key', this.handleCustomFieldKeyInput.bind(this));
            
            // Initialize with JSON data if available
            if (window.currentJsonData) {
                this.jsonData = window.currentJsonData;
                this.initializeFieldMapping();
            }
        },

        /**
         * Initialize field mapping with JSON data
         */
        initializeFieldMapping: function() {
            if (!this.jsonData || !Object.keys(this.jsonData).length) {
                console.error('No JSON data available for field mapping');
                return;
            }
            
            this.$loading.show();
            this.$mappingContent.hide();
            
            try {
                // Render standard fields
                this.renderStandardFields();
                
                // Render any existing custom fields
                this.renderCustomFields();
                
                // Show the mapping content
                this.$mappingContent.slideDown();
                
            } catch (error) {
                console.error('Error initializing field mapping:', error);
                this.showError('Failed to initialize field mapping. Please check the console for details.');
            } finally {
                this.$loading.hide();
            }
        },
        
        /**
         * Render standard WordPress fields
         */
        renderStandardFields: function() {
            if (!this.$standardFieldsTable.length) return;
            
            const fields = this.standardFields.map(field => ({
                id: field,
                label: this.formatFieldLabel(field),
                type: this.fieldTypes[field] || 'text',
                required: ['post_title', 'post_content'].includes(field)
            }));
            
            // Clear existing rows
            this.$standardFieldsTable.empty();
            
            // Add rows for each standard field
            fields.forEach(field => {
                const $row = this.createFieldRow(field);
                this.$standardFieldsTable.append($row);
            });
        },
        
        /**
         * Render custom fields
         */
        renderCustomFields: function() {
            if (!this.$customFieldsTable.length) return;
            
            // For now, we'll start with an empty custom fields table
            // Custom fields will be added by the user
            this.$customFieldsTable.html('<tr><td colspan="4" class="jpi-no-custom-fields">' +
                'No custom fields added yet. Click "Add Custom Field" to get started.' +
                '</td></tr>');
        },
        
        /**
         * Create a field row HTML element
         */
        createFieldRow: function(field) {
            const isCustom = !this.standardFields.includes(field.id);
            const fieldOptions = this.getFieldOptions(field);
            
            return `
                <tr class="jpi-field-row" data-field-id="${field.id}">
                    <td class="jpi-field-label">
                        ${field.label}
                        ${field.required ? '<span class="required">*</span>' : ''}
                    </td>
                    <td class="jpi-field-type">
                        ${this.getFieldTypeSelect(field.id, field.type, isCustom)}
                    </td>
                    <td class="jpi-field-source">
                        <select class="jpi-field-select" name="field_source[${field.id}]" 
                                data-field="${field.id}">
                            <option value="">— Select —</option>
                            ${fieldOptions}
                        </select>
                    </td>
                    <td class="jpi-field-actions">
                        ${isCustom ? 
                            '<button type="button" class="button-link jpi-remove-field" ' + 
                            'title="Remove Field"><span class="dashicons dashicons-trash"></span></button>' : 
                            ''
                        }
                    </td>
                </tr>`;
        },
        
        /**
         * Get field options based on JSON data
         */
        getFieldOptions: function(field) {
            if (!this.jsonData) return '';
            
            let options = '<option value="">— Select —</option>';
            
            // Add direct properties
            Object.keys(this.jsonData).forEach(key => {
                const value = this.jsonData[key];
                const type = Array.isArray(value) ? 'array' : typeof value;
                options += `<option value="${key}" data-type="${type}">${key} (${type})</option>`;
            });
            
            return options;
        },
        
        /**
         * Get field type select HTML
         */
        getFieldTypeSelect: function(fieldId, selectedType, isCustom) {
            if (!isCustom) {
                // For standard fields, show the type as text (not editable)
                return `<span class="jpi-field-type-label">${selectedType}</span>`;
            }
            
            // For custom fields, show a select dropdown
            let options = '';
            const fieldTypes = {
                'text': 'Text',
                'textarea': 'Text Area',
                'number': 'Number',
                'email': 'Email',
                'url': 'URL',
                'date': 'Date',
                'datetime': 'Date/Time',
                'select': 'Dropdown',
                'checkbox': 'Checkbox',
                'radio': 'Radio Button',
                'media': 'Media',
                'taxonomy': 'Taxonomy',
                'user': 'User',
                'custom_field': 'Custom Field'
            };
            
            for (const [value, label] of Object.entries(fieldTypes)) {
                options += `<option value="${value}" ${selectedType === value ? 'selected' : ''}>${label}</option>`;
            }
            
            return `<select name="field_type[${fieldId}]" class="jpi-field-type-select">${options}</select>`;
        },
        
        /**
         * Format field label from field ID
         */
        formatFieldLabel: function(fieldId) {
            return fieldId
                .replace(/^post_/, '')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, l => l.toUpperCase());
        },
        
        /**
         * Show error message
         */
        /**
         * Show error message
         */
        showError: function(message) {
            // Remove any existing error messages
            $('.jpi-error-message').remove();
            
            // Create and show the error message
            const $error = $(`<div class="notice notice-error jpi-error-message">
                <p>${message}</p>
            </div>`);
            
            this.$container.prepend($error);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $error.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Add a new custom field
         */
        addCustomField: function() {
            const fieldId = 'custom_field_' + Date.now();
            const field = {
                id: fieldId,
                label: 'Custom Field',
                type: 'text',
                required: false
            };
            
            const $row = $(this.createFieldRow(field));
            
            // Remove the "no custom fields" message if it exists
            if (this.$customFieldsTable.find('.jpi-no-custom-fields').length) {
                this.$customFieldsTable.empty();
            }
            
            this.$customFieldsTable.append($row);
            
            // Focus on the field name input
            $row.find('.jpi-custom-field-name').focus();
        },
        
        /**
         * Remove a field
         */
        removeField: function(e) {
            const $row = $(e.target).closest('tr');
            $row.fadeOut(300, () => {
                $row.remove();
                
                // Show "no custom fields" message if this was the last custom field
                if (!this.$customFieldsTable.find('tr').length) {
                    this.$customFieldsTable.html(
                    FieldMapping.$customFieldsTable.html(
                        '<tr><td colspan="4" class="jpi-no-custom-fields">' +
                        'No custom fields added yet. Click "Add Custom Field" to get started.' +
                        '</td></tr>'
                    );
                }
            });
        },
        
        /**
         * Save field mappings
         */
        saveMapping: function() {
            const $button = $(this);
            const $spinner = $button.siblings('.spinner');
            
            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            // Collect all field mappings
            const mappings = {};
            
            // Get standard field mappings
            this.$standardFieldsTable.find('tr').each(function() {
                const $row = $(this);
                const fieldId = $row.data('field-id');
                const source = $row.find('.jpi-field-select').val();
                
                if (source) {
                    mappings[fieldId] = {
                        source: source,
                        type: $row.find('.jpi-field-type-select').val() || 'text',
                        isCustom: false
                    };
                }
            });
            
            // Get custom field mappings
            this.$customFieldsTable.find('tr').each(function() {
                const $row = $(this);
                const fieldId = $row.data('field-id');
                const fieldName = $row.find('.jpi-custom-field-name').val() || 'custom_field';
                const source = $row.find('.jpi-field-select').val();
                
                if (source) {
                    mappings[fieldId] = {
                        name: fieldName,
                        source: source,
                        type: $row.find('.jpi-field-type-select').val() || 'text',
                        isCustom: true
                    };
                }
            });
            
            // Prepare data for AJAX request
            const data = {
                action: 'jpi_save_field_mapping',
                nonce: window.jpi_ajax.nonce,
                mappings: mappings,
                json_data: JSON.stringify(this.jsonData)
            };
            
            // Send AJAX request
            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    // Show success message
                    FieldMapping.showNotice('Field mappings saved successfully!', 'success');
                    
                    // If the response includes a redirect URL, redirect to it
                    if (response.data && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                } else {
                    // Show error message
                    const errorMessage = response.data && response.data.message 
                        ? response.data.message 
                        : 'Failed to save field mappings.';
                    FieldMapping.showError(errorMessage);
                }
            })
            .fail(function(xhr, status, error) {
                FieldMapping.showError('Error: ' + (xhr.responseJSON && xhr.responseJSON.data ? 
                    xhr.responseJSON.data.message : 'Failed to save field mappings.'));
            })
            .always(function() {
                // Reset button state
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        },
        
        /**
         * Update field preview when source changes
         */
        updateFieldPreview: function(e) {
            const $select = $(e.target);
            const fieldId = $select.data('field');
            const selectedOption = $select.find('option:selected');
            const dataType = selectedOption.data('type');
            
            // Update the field type based on the selected data type
            if (dataType) {
                const $row = $select.closest('tr');
                const $typeSelect = $row.find('.jpi-field-type-select');
                
                if ($typeSelect.length) {
                    // For custom fields, suggest a field type based on the data type
                    let suggestedType = 'text';
                    
                    switch (dataType) {
                        case 'number':
                            suggestedType = 'number';
                            break;
                        case 'boolean':
                            suggestedType = 'checkbox';
                            break;
                        case 'array':
                            suggestedType = 'select';
                            break;
                        case 'object':
                            suggestedType = 'textarea';
                            break;
                    }
                    
                    $typeSelect.val(suggestedType);
                }
            }
        },
        
        /**
         * Handle custom field key input
         */
        handleCustomFieldKeyInput: function(e) {
            const $input = $(e.target);
            const value = $input.val();
            
            // Sanitize the input to be a valid meta key
            const sanitized = value
                .toLowerCase()
                .replace(/[^a-z0-9_]/g, '_')
                .replace(/_{2,}/g, '_')
                .replace(/^_+|_+$/g, '');
            
            if (value !== sanitized) {
                $input.val(sanitized);
            }
        },
        
        /**
         * Show success notice
         */
        showNotice: function(message, type = 'success') {
            // Remove any existing notices
            $('.jpi-notice').remove();
            
            // Create and show the notice
            const $notice = $(`<div class="notice notice-${type} jpi-notice is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>`);
            
            this.$container.prepend($notice);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Initialize the field mapping when the document is ready
    $(function() {
        if ($('#jpi-field-mapping-container').length) {
            window.fieldMapping = Object.create(FieldMapping);
            window.fieldMapping.init();
        }
    });

        /**
         * Initialize the field mapping interface
         */
        initializeFieldMapping: function() {
            if (!window.currentJsonData) {
                console.error('No JSON data available for field mapping');
                return;
            }

            // Render standard fields
            this.renderStandardFields();
            
            // Initialize Select2 if available
            this.initializeSelect2();
            
            // Load any saved mappings
            this.loadSavedMappings();
        },

        /**
         * Render standard WordPress fields
         */
        renderStandardFields: function() {
            const standardFields = [
                { 
                    id: 'post_title', 
                    label: jpi_vars.i18n.post_title || 'Post Title',
                    required: true,
                    description: jpi_vars.i18n.post_title_desc || 'The title of the post'
                },
                { 
                    id: 'post_content', 
                    label: jpi_vars.i18n.post_content || 'Post Content',
                    description: jpi_vars.i18n.post_content_desc || 'The main content of the post'
                },
                { 
                    id: 'post_excerpt', 
                    label: jpi_vars.i18n.post_excerpt || 'Post Excerpt',
                    description: jpi_vars.i18n.post_excerpt_desc || 'A short excerpt or summary of the post'
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
                    },
                    description: jpi_vars.i18n.post_status_desc || 'The status of the post'
                },
                { 
                    id: 'post_date', 
                    label: jpi_vars.i18n.post_date || 'Post Date',
                    description: jpi_vars.i18n.post_date_desc || 'The publication date of the post'
                },
                { 
                    id: 'post_author', 
                    label: jpi_vars.i18n.post_author || 'Post Author',
                    type: 'select',
                    options: jpi_vars.authors || {},
                    description: jpi_vars.i18n.post_author_desc || 'The author of the post'
                },
                { 
                    id: 'post_name', 
                    label: jpi_vars.i18n.post_slug || 'Post Slug',
                    description: jpi_vars.i18n.post_slug_desc || 'URL-friendly version of the title'
                }
            ];

            let html = '';
            
            standardFields.forEach(field => {
                const fieldValue = this.getFieldPreview(window.currentJsonData, field.id);
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
                            ${this.renderFieldSelect(field.id, window.currentJsonData, field.type, field.options)}
                        </td>
                        <td class="jpi-preview-cell">
                            <div class="jpi-field-preview">${fieldValue || '—'}</div>
                        </td>
                    </tr>
                `;
            });
            
            this.$standardFieldsTable.html(html);
        },

        /**
         * Render a select field for field mapping
         */
        renderFieldSelect: function(fieldId, fields, type = 'text', options = null) {
            let html = `<select id="jpi-field-${fieldId}" class="jpi-field-select" data-field-id="${fieldId}">`;
            
            // Add default option
            html += '<option value="">— ' + (jpi_vars.i18n.select_field || 'Select') + ' —</option>';
            
            // Add options for select type
            if (type === 'select' && options) {
                Object.entries(options).forEach(([value, label]) => {
                    html += `<option value="${value}">${label}</option>`;
                });
                return html + '</select>';
            }
            
            // Add available fields from JSON data
            if (fields && typeof fields === 'object') {
                Object.keys(fields).forEach(key => {
                    if (fields[key] !== null && typeof fields[key] !== 'object') {
                        html += `<option value="${key}">${key}</option>`;
                    }
                });
            }
            
            return html + '</select>';
        },

        /**
         * Get a preview of a field value
         */
        getFieldPreview: function(fields, fieldId) {
            if (!fields || !fields[fieldId]) return '—';
            
            const value = fields[fieldId];
            
            if (value === null || value === undefined) {
                return '—';
            }
            
            if (typeof value === 'string') {
                return value.length > 50 ? value.substring(0, 50) + '...' : value;
            }
            
            if (Array.isArray(value)) {
                return '[' + value.slice(0, 3).map(String).join(', ') + (value.length > 3 ? ', ...' : '') + ']';
            }
            
            if (typeof value === 'object') {
                return '{' + Object.keys(value).slice(0, 3).join(', ') + (Object.keys(value).length > 3 ? ', ...' : '') + '}';
            }
            
            return String(value);
        },

        /**
         * Add a new custom field row
         */
        addCustomField: function() {
            const fieldId = 'custom_field_' + Date.now();
            
            // Remove 'no fields' message if present
            this.$customFieldsTable.find('.jpi-no-custom-fields').remove();
            
            const row = `
                <tr class="jpi-mapping-row" data-field-type="custom" data-field-id="${fieldId}">
                    <td>
                        <input type="text" class="regular-text jpi-custom-field-key" 
                               placeholder="${jpi_vars.i18n.meta_key_placeholder || 'meta_key'}" />
                    </td>
                    <td>
                        ${this.renderFieldSelect(fieldId, window.currentJsonData)}
                    </td>
                    <td class="jpi-preview-cell">
                        <div class="jpi-field-preview">—</div>
                    </td>
                    <td>
                        <button type="button" class="button-link jpi-remove-field" 
                                title="${jpi_vars.i18n.remove || 'Remove'}">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </td>
                </tr>
            `;
            
            this.$customFieldsTable.append(row);
            this.initializeSelect2($(`#jpi-field-${fieldId}`));
            this.enableSaveButton();
        },

        /**
         * Remove a field mapping row
         */
        removeField: function(e) {
            $(e.target).closest('tr').remove();
            this.updateCustomFieldsVisibility();
            this.enableSaveButton();
        },

        /**
         * Update field preview when selection changes
         */
        updateFieldPreview: function(e) {
            const $select = $(e.target);
            const $row = $select.closest('tr');
            const fieldId = $select.val();
            
            if (!fieldId) {
                $row.find('.jpi-field-preview').html('—');
                return;
            }
            
            const preview = this.getFieldPreview(window.currentJsonData, fieldId);
            $row.find('.jpi-field-preview').html(preview || '—');
            this.enableSaveButton();
        },

        /**
         * Handle custom field key input
         */
        handleCustomFieldKeyInput: function() {
            this.enableSaveButton();
        },

        /**
         * Update visibility of custom fields table
         */
        updateCustomFieldsVisibility: function() {
            const hasCustomFields = this.$customFieldsTable.find('tr.jpi-mapping-row').length > 0;
            
            if (!hasCustomFields) {
                this.$customFieldsTable.append(`
                    <tr class="jpi-no-custom-fields">
                        <td colspan="4">
                            ${jpi_vars.i18n.no_custom_fields || 'No custom fields mapped yet. Click "Add Custom Field" to get started.'}
                        </td>
                    </tr>
                `);
            }
        },

        /**
         * Save field mappings
         */
        saveMapping: function() {
            const mappings = this.getMappings();
            
            // Show saving state
            const $spinner = this.$saveButton.next('.spinner');
            const $message = this.$saveButton.nextAll('.jpi-mapping-message');
            
            $spinner.addClass('is-active');
            $message.removeClass('error success').text(jpi_vars.i18n.saving || 'Saving...');
            
            // Simulate AJAX save
            setTimeout(() => {
                // In a real implementation, this would be an AJAX call
                console.log('Saving mappings:', mappings);
                
                // Save to localStorage for demo purposes
                if (typeof Storage !== 'undefined') {
                    localStorage.setItem('jpi_field_mappings', JSON.stringify(mappings));
                }
                
                // Update UI
                $spinner.removeClass('is-active');
                $message.addClass('success').text(jpi_vars.i18n.saved || 'Mappings saved successfully!');
                this.$saveButton.prop('disabled', true);
                
                // Hide message after 3 seconds
                setTimeout(() => {
                    $message.fadeOut(500, () => {
                        $message.removeClass('success').show();
                    });
                }, 3000);
                
            }, 500);
        },

        /**
         * Get all field mappings
         */
        getMappings: function() {
            const mappings = {
                standard: {},
                custom: {}
            };
            
            // Get standard field mappings
            this.$standardFieldsTable.find('tr.jpi-mapping-row').each(function() {
                const $row = $(this);
                const fieldId = $row.data('field-id');
                const $select = $row.find('.jpi-field-select');
                const selectedField = $select.val();
                
                if (selectedField) {
                    mappings.standard[fieldId] = {
                        source: selectedField,
                        type: 'post_field'
                    };
                }
            });
            
            // Get custom field mappings
            this.$customFieldsTable.find('tr.jpi-mapping-row').each(function() {
                const $row = $(this);
                const fieldId = $row.data('field-id');
                const $keyInput = $row.find('.jpi-custom-field-key');
                const $select = $row.find('.jpi-field-select');
                const metaKey = $keyInput.val().trim();
                const sourceField = $select.val();
                
                if (metaKey && sourceField) {
                    mappings.custom[fieldId] = {
                        meta_key: metaKey,
                        source: sourceField,
                        type: 'custom_field'
                    };
                }
            });
            
            return mappings;
        },

        /**
         * Load saved mappings
         */
        loadSavedMappings: function() {
            // In a real implementation, this would load from the server
            if (typeof Storage === 'undefined') return;
            
            const savedMappings = localStorage.getItem('jpi_field_mappings');
            if (!savedMappings) return;
            
            try {
                const mappings = JSON.parse(savedMappings);
                this.applyMappings(mappings);
            } catch (e) {
                console.error('Error loading saved mappings:', e);
            }
        },

        /**
         * Apply saved mappings to the UI
         */
        applyMappings: function(mappings) {
            if (!mappings) return;
            
            // Apply standard field mappings
            if (mappings.standard) {
                Object.entries(mappings.standard).forEach(([fieldId, mapping]) => {
                    const $select = $(`#jpi-field-${fieldId}`);
                    if ($select.length && mapping.source) {
                        $select.val(mapping.source).trigger('change');
                    }
                });
            }
            
            // Apply custom field mappings
            if (mappings.custom) {
                Object.entries(mappings.custom).forEach(([fieldId, mapping]) => {
                    this.addCustomField();
                    const $row = $(`tr[data-field-id="${fieldId}"]`);
                    if ($row.length) {
                        $row.find('.jpi-custom-field-key').val(mapping.meta_key);
                        $row.find('.jpi-field-select').val(mapping.source).trigger('change');
                    }
                });
            }
            
            // Disable save button since we just loaded the saved state
            this.$saveButton.prop('disabled', true);
        },

        /**
         * Enable the save button
         */
        enableSaveButton: function() {
            this.$saveButton.prop('disabled', false);
        },

        /**
         * Initialize Select2 for select elements
         */
        initializeSelect2: function($element = null) {
            if (typeof $.fn.select2 !== 'function') return;
            
            const elements = $element || $('.jpi-field-select');
            
            elements.select2({
                width: '100%',
                placeholder: jpi_vars.i18n.select_field || 'Select a field',
                allowClear: true,
                dropdownParent: this.$container
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize if we're on the field mapping tab
        if ($('#jpi-field-mapping-container').length) {
            FieldMapping.init();
        }
    });

})(jQuery);
