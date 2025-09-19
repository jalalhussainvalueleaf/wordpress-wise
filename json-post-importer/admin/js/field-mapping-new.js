(function($) {
    'use strict';

    // Field Mapping Module
    const FieldMapping = {
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
            const self = this;
            
            // Add custom field
            this.$addCustomFieldBtn.on('click', function(e) {
                e.preventDefault();
                self.addCustomField();
            });
            
            // Remove field
            this.$container.on('click', '.jpi-remove-field', function(e) {
                e.preventDefault();
                self.removeField(this);
            });
            
            // Save mapping
            this.$saveButton.on('click', function(e) {
                e.preventDefault();
                self.saveMapping();
            });
            
            // Field selection change
            this.$container.on('change', '.jpi-field-select', function() {
                self.updateFieldPreview(this);
            });
            
            // Custom field key input
            this.$container.on('input', '.jpi-custom-field-key', function() {
                self.handleCustomFieldKeyInput(this);
            });
            
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
                const $row = $(this.createFieldRow(field));
                this.$standardFieldsTable.append($row);
            });
            
            // Initialize any custom fields
            this.renderCustomFields();
        },
        
        /**
         * Render custom fields
         */
        renderCustomFields: function() {
            if (!this.$customFieldsTable.length) return;
            
            // For now, we'll start with an empty custom fields table
            this.$customFieldsTable.html('<tr><td colspan="4" class="jpi-no-custom-fields">' +
                'No custom fields added yet. Click "Add Custom Field" to get started.' +
                '</td></tr>');
        },
        
        /**
         * Create a field row HTML element
         */
        createFieldRow: function(field) {
            const isCustom = !this.standardFields.includes(field.id);
            const fieldOptions = this.getFieldOptions();
            
            return `
                <tr class="jpi-field-row" data-field-id="${field.id}">
                    <td class="jpi-field-label">
                        ${isCustom ? 
                            `<input type="text" class="jpi-custom-field-name" value="${field.label}" 
                                  placeholder="Enter field name">` : 
                            field.label
                        }
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
        getFieldOptions: function() {
            if (!this.jsonData) return '';
            
            let options = '';
            
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
        removeField: function(button) {
            const $row = $(button).closest('tr');
            $row.fadeOut(300, () => {
                $row.remove();
                
                // Show "no custom fields" message if this was the last custom field
                if (!this.$customFieldsTable.find('tr').length) {
                    this.$customFieldsTable.html(
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
            this.$standardFieldsTable.find('tr').each((index, row) => {
                const $row = $(row);
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
            this.$customFieldsTable.find('tr').each((index, row) => {
                const $row = $(row);
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
            const self = this;
            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    // Show success message
                    self.showNotice('Field mappings saved successfully!', 'success');
                    
                    // If the response includes a redirect URL, redirect to it
                    if (response.data && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                } else {
                    // Show error message
                    const errorMessage = response.data && response.data.message 
                        ? response.data.message 
                        : 'Failed to save field mappings.';
                    self.showError(errorMessage);
                }
            })
            .fail(function(xhr) {
                const errorMessage = xhr.responseJSON && xhr.responseJSON.data 
                    ? xhr.responseJSON.data.message 
                    : 'Failed to save field mappings. Please try again.';
                self.showError('Error: ' + errorMessage);
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
        updateFieldPreview: function(select) {
            const $select = $(select);
            const $row = $select.closest('tr');
            const $typeSelect = $row.find('.jpi-field-type-select');
            
            if ($typeSelect.length) {
                const selectedOption = $select.find('option:selected');
                const dataType = selectedOption.data('type');
                
                if (dataType) {
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
        handleCustomFieldKeyInput: function(input) {
            const $input = $(input);
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
        },
        
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
        }
    };
    
    // Initialize the field mapping when the document is ready
    $(function() {
        if ($('#jpi-field-mapping-container').length) {
            window.fieldMapping = Object.create(FieldMapping);
            window.fieldMapping.init();
        }
    });
    
})(jQuery);
