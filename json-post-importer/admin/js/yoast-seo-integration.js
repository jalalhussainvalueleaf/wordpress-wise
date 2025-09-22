/**
 * Yoast SEO Integration JavaScript for JSON Post Importer
 */

(function($) {
    'use strict';

    // Yoast SEO Integration object
    window.JSONPostImporterYoastSEO = {
        
        // Configuration
        config: {
            nonce: jpi_ajax.nonce,
            ajaxUrl: jpi_ajax.ajax_url,
            yoastActive: false,
            yoastFields: {},
            currentMappings: {},
            previewData: {}
        },

        // Initialize the Yoast SEO integration
        init: function() {
            this.bindEvents();
            this.loadYoastFields();
            this.initializeYoastFieldMapping();
        },

        // Bind event handlers
        bindEvents: function() {
            // Auto-detect Yoast fields button
            $(document).on('click', '#auto-detect-yoast-fields', this.autoDetectYoastFields.bind(this));
            
            // Validate Yoast fields button
            $(document).on('click', '#validate-yoast-fields', this.validateYoastFields.bind(this));
            
            // Preview Yoast SEO button
            $(document).on('click', '#preview-yoast-seo', this.previewYoastSEO.bind(this));
            
            // Calculate SEO score button
            $(document).on('click', '#calculate-seo-score', this.calculateSEOScore.bind(this));
            
            // Yoast field mapping changes
            $(document).on('change', '.yoast-field-mapping', this.onYoastFieldMappingChange.bind(this));
            
            // Real-time validation on field input
            $(document).on('input', '.yoast-field-input', this.onYoastFieldInput.bind(this));
            
            // Toggle Yoast SEO section
            $(document).on('click', '#toggle-yoast-section', this.toggleYoastSection.bind(this));
            
            // Migrate Yoast data button
            $(document).on('click', '#migrate-yoast-data', this.migrateYoastData.bind(this));
        },

        // Load available Yoast SEO fields
        loadYoastFields: function() {
            const self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'jpi_get_yoast_fields',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.config.yoastFields = response.data.fields;
                        self.config.yoastActive = response.data.yoast_active;
                        self.renderYoastFieldsInterface();
                        self.updateYoastStatus(response.data.message);
                    } else {
                        self.showError('Failed to load Yoast SEO fields: ' + response.data.message);
                    }
                },
                error: function() {
                    self.showError('Error loading Yoast SEO fields');
                }
            });
        },

        // Initialize Yoast field mapping interface
        initializeYoastFieldMapping: function() {
            // Add Yoast SEO section to field mapping interface if it doesn't exist
            if ($('#yoast-seo-mapping').length === 0) {
                this.createYoastMappingSection();
            }
        },

        // Create Yoast SEO mapping section
        createYoastMappingSection: function() {
            const yoastSection = `
                <div id="yoast-seo-mapping" class="field-mapping-section">
                    <div class="section-header">
                        <h3>
                            <span class="dashicons dashicons-admin-generic"></span>
                            Yoast SEO Fields
                            <button type="button" id="toggle-yoast-section" class="button-link">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                        </h3>
                        <div class="section-actions">
                            <button type="button" id="auto-detect-yoast-fields" class="button button-secondary">
                                Auto-Detect Fields
                            </button>
                            <button type="button" id="validate-yoast-fields" class="button button-secondary">
                                Validate Fields
                            </button>
                            <button type="button" id="preview-yoast-seo" class="button button-secondary">
                                Preview SEO
                            </button>
                        </div>
                    </div>
                    <div class="yoast-status-indicator">
                        <span id="yoast-status-message">Loading Yoast SEO status...</span>
                    </div>
                    <div id="yoast-fields-container" class="fields-container">
                        <!-- Yoast fields will be populated here -->
                    </div>
                    <div id="yoast-preview-container" class="preview-container" style="display: none;">
                        <!-- SEO preview will be shown here -->
                    </div>
                    <div id="yoast-seo-score" class="seo-score-container" style="display: none;">
                        <!-- SEO score will be shown here -->
                    </div>
                </div>
            `;
            
            // Insert after standard field mapping or at the end of field mapping container
            if ($('#standard-field-mapping').length > 0) {
                $('#standard-field-mapping').after(yoastSection);
            } else {
                $('#field-mapping-container').append(yoastSection);
            }
        },

        // Render Yoast fields interface
        renderYoastFieldsInterface: function() {
            const container = $('#yoast-fields-container');
            if (container.length === 0) return;

            let html = '<div class="yoast-fields-grid">';
            
            // Group fields by category
            const fieldCategories = {
                'Basic SEO': ['_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw', '_yoast_wpseo_keywordsynonyms'],
                'Advanced SEO': ['_yoast_wpseo_canonical', '_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_meta-robots-nofollow'],
                'Social Media - Facebook': ['_yoast_wpseo_opengraph-title', '_yoast_wpseo_opengraph-description', '_yoast_wpseo_opengraph-image'],
                'Social Media - Twitter': ['_yoast_wpseo_twitter-title', '_yoast_wpseo_twitter-description', '_yoast_wpseo_twitter-image'],
                'Schema.org': ['_yoast_wpseo_schema_page_type', '_yoast_wpseo_schema_article_type']
            };

            for (const [category, fields] of Object.entries(fieldCategories)) {
                html += `<div class="yoast-field-category">`;
                html += `<h4>${category}</h4>`;
                
                fields.forEach(fieldKey => {
                    if (this.config.yoastFields[fieldKey]) {
                        const field = this.config.yoastFields[fieldKey];
                        html += this.renderYoastFieldMapping(fieldKey, field);
                    }
                });
                
                html += `</div>`;
            }
            
            html += '</div>';
            container.html(html);
        },

        // Render individual Yoast field mapping
        renderYoastFieldMapping: function(fieldKey, fieldConfig) {
            const currentMapping = this.config.currentMappings[fieldKey] || '';
            const fieldId = fieldKey.replace(/[^a-zA-Z0-9]/g, '_');
            
            let html = `
                <div class="yoast-field-row" data-field="${fieldKey}">
                    <div class="field-info">
                        <label for="yoast_${fieldId}">${fieldConfig.label}</label>
                        ${fieldConfig.max_length ? `<span class="field-limit">(max: ${fieldConfig.max_length} chars)</span>` : ''}
                        ${fieldConfig.required ? '<span class="required">*</span>' : ''}
                    </div>
                    <div class="field-mapping">
                        <select name="yoast_${fieldId}" id="yoast_${fieldId}" class="yoast-field-mapping" data-yoast-field="${fieldKey}">
                            <option value="">-- Select JSON Field --</option>
                            <!-- Options will be populated dynamically -->
                        </select>
                    </div>
                    <div class="field-preview">
                        <input type="text" class="yoast-field-input" data-yoast-field="${fieldKey}" placeholder="Preview value..." readonly>
                    </div>
                    <div class="field-validation">
                        <span class="validation-status"></span>
                    </div>
                </div>
            `;
            
            return html;
        },

        // Auto-detect Yoast SEO fields
        autoDetectYoastFields: function() {
            const self = this;
            const jsonData = this.getJSONData();
            const rootPath = this.getRootPath();
            
            if (!jsonData) {
                this.showError('No JSON data available for auto-detection');
                return;
            }

            this.showLoading('Auto-detecting Yoast SEO fields...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'jpi_auto_detect_yoast_fields',
                    nonce: this.config.nonce,
                    json_data: JSON.stringify(jsonData),
                    root_path: rootPath
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        self.config.currentMappings = response.data.mappings;
                        self.updateYoastFieldMappings(response.data.mappings);
                        self.showSuccess(response.data.message);
                    } else {
                        self.showError('Auto-detection failed: ' + response.data.message);
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showError('Error during auto-detection');
                }
            });
        },

        // Validate Yoast SEO fields
        validateYoastFields: function() {
            const self = this;
            const yoastData = this.collectYoastData();
            
            if (Object.keys(yoastData).length === 0) {
                this.showWarning('No Yoast SEO fields mapped for validation');
                return;
            }

            this.showLoading('Validating Yoast SEO fields...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'jpi_validate_yoast_fields',
                    nonce: this.config.nonce,
                    yoast_data: yoastData
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        self.displayValidationResults(response.data.validation);
                        self.showSuccess(response.data.message);
                    } else {
                        self.showError('Validation failed: ' + response.data.message);
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showError('Error during validation');
                }
            });
        },

        // Preview Yoast SEO
        previewYoastSEO: function() {
            const self = this;
            const yoastData = this.collectYoastData();
            const postData = this.collectPostData();

            this.showLoading('Generating SEO preview...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'jpi_preview_yoast_seo',
                    nonce: this.config.nonce,
                    yoast_data: yoastData,
                    post_data: postData
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        self.displaySEOPreview(response.data.preview);
                        self.calculateSEOScore(); // Also calculate score
                    } else {
                        self.showError('Preview generation failed: ' + response.data.message);
                    }
                },
                error: function() {
                    self.hideLoading();
                    self.showError('Error generating preview');
                }
            });
        },

        // Calculate SEO score
        calculateSEOScore: function() {
            const self = this;
            const yoastData = this.collectYoastData();
            const postData = this.collectPostData();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'jpi_calculate_seo_score',
                    nonce: this.config.nonce,
                    yoast_data: yoastData,
                    post_data: postData
                },
                success: function(response) {
                    if (response.success) {
                        self.displaySEOScore(response.data.seo_score);
                    }
                }
            });
        },

        // Display SEO preview
        displaySEOPreview: function(preview) {
            const container = $('#yoast-preview-container');
            
            const html = `
                <div class="seo-preview">
                    <h4>SEO Preview</h4>
                    <div class="search-preview">
                        <div class="search-result">
                            <div class="search-url">${preview.url_preview}</div>
                            <div class="search-title">${preview.seo_title}</div>
                            <div class="search-description">${preview.meta_description}</div>
                        </div>
                    </div>
                    
                    <div class="social-previews">
                        <div class="facebook-preview">
                            <h5>Facebook Preview</h5>
                            <div class="social-card">
                                ${preview.social_preview.facebook.image ? `<img src="${preview.social_preview.facebook.image}" alt="Facebook image">` : ''}
                                <div class="social-content">
                                    <div class="social-title">${preview.social_preview.facebook.title}</div>
                                    <div class="social-description">${preview.social_preview.facebook.description}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="twitter-preview">
                            <h5>Twitter Preview</h5>
                            <div class="social-card">
                                ${preview.social_preview.twitter.image ? `<img src="${preview.social_preview.twitter.image}" alt="Twitter image">` : ''}
                                <div class="social-content">
                                    <div class="social-title">${preview.social_preview.twitter.title}</div>
                                    <div class="social-description">${preview.social_preview.twitter.description}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.html(html).show();
        },

        // Display SEO score
        displaySEOScore: function(seoScore) {
            const container = $('#yoast-seo-score');
            
            const statusClass = seoScore.status === 'good' ? 'score-good' : 
                               seoScore.status === 'ok' ? 'score-ok' : 'score-poor';
            
            let recommendationsHtml = '';
            if (seoScore.recommendations.length > 0) {
                recommendationsHtml = `
                    <div class="seo-recommendations">
                        <h5>Recommendations:</h5>
                        <ul>
                            ${seoScore.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }
            
            const html = `
                <div class="seo-score ${statusClass}">
                    <h4>SEO Score</h4>
                    <div class="score-display">
                        <div class="score-circle">
                            <span class="score-number">${seoScore.percentage}%</span>
                        </div>
                        <div class="score-status">${seoScore.status.toUpperCase()}</div>
                    </div>
                    ${recommendationsHtml}
                </div>
            `;
            
            container.html(html).show();
        },

        // Display validation results
        displayValidationResults: function(validation) {
            $('.validation-status').removeClass('valid invalid warning').empty();
            
            // Show errors
            if (validation.errors.length > 0) {
                validation.errors.forEach(error => {
                    this.showError(error);
                });
            }
            
            // Show warnings
            if (validation.warnings.length > 0) {
                validation.warnings.forEach(warning => {
                    this.showWarning(warning);
                });
            }
            
            // Update field validation status
            Object.keys(validation.processed_data).forEach(field => {
                const fieldRow = $(`.yoast-field-row[data-field="${field}"]`);
                const statusElement = fieldRow.find('.validation-status');
                statusElement.addClass('valid').html('<span class="dashicons dashicons-yes-alt"></span>');
            });
        },

        // Update Yoast field mappings
        updateYoastFieldMappings: function(mappings) {
            Object.entries(mappings).forEach(([yoastField, jsonField]) => {
                const select = $(`.yoast-field-mapping[data-yoast-field="${yoastField}"]`);
                select.val(jsonField);
                this.updateFieldPreview(yoastField, jsonField);
            });
        },

        // Update field preview
        updateFieldPreview: function(yoastField, jsonField) {
            const previewInput = $(`.yoast-field-input[data-yoast-field="${yoastField}"]`);
            const sampleData = this.getSampleFieldValue(jsonField);
            previewInput.val(sampleData);
        },

        // Event handlers
        onYoastFieldMappingChange: function(e) {
            const select = $(e.target);
            const yoastField = select.data('yoast-field');
            const jsonField = select.val();
            
            this.config.currentMappings[yoastField] = jsonField;
            this.updateFieldPreview(yoastField, jsonField);
        },

        onYoastFieldInput: function(e) {
            const input = $(e.target);
            const yoastField = input.data('yoast-field');
            const value = input.val();
            
            // Real-time validation
            this.validateSingleField(yoastField, value);
        },

        // Validate single field
        validateSingleField: function(yoastField, value) {
            const fieldConfig = this.config.yoastFields[yoastField];
            if (!fieldConfig) return;
            
            const fieldRow = $(`.yoast-field-row[data-field="${yoastField}"]`);
            const statusElement = fieldRow.find('.validation-status');
            
            // Length validation
            if (fieldConfig.max_length && value.length > fieldConfig.max_length) {
                statusElement.removeClass('valid warning').addClass('invalid')
                    .html(`<span class="dashicons dashicons-warning"></span> Too long (${value.length}/${fieldConfig.max_length})`);
            } else if (fieldConfig.max_length && value.length > fieldConfig.max_length * 0.8) {
                statusElement.removeClass('valid invalid').addClass('warning')
                    .html(`<span class="dashicons dashicons-info"></span> Approaching limit (${value.length}/${fieldConfig.max_length})`);
            } else if (value.length > 0) {
                statusElement.removeClass('invalid warning').addClass('valid')
                    .html('<span class="dashicons dashicons-yes-alt"></span>');
            } else {
                statusElement.removeClass('valid invalid warning').empty();
            }
        },

        // Toggle Yoast section
        toggleYoastSection: function() {
            const container = $('#yoast-fields-container');
            const button = $('#toggle-yoast-section .dashicons');
            
            container.slideToggle();
            button.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        },

        // Migrate Yoast data
        migrateYoastData: function() {
            // This would be used when Yoast SEO becomes active
            // Implementation depends on specific migration needs
        },

        // Utility methods
        getJSONData: function() {
            // Get JSON data from the current import session
            return window.JSONPostImporter && window.JSONPostImporter.currentData ? 
                   window.JSONPostImporter.currentData : null;
        },

        getRootPath: function() {
            // Get root path from field mapping settings
            return $('#json-root-path').val() || 'content';
        },

        collectYoastData: function() {
            const yoastData = {};
            
            $('.yoast-field-input').each(function() {
                const field = $(this).data('yoast-field');
                const value = $(this).val();
                if (value) {
                    yoastData[field] = value;
                }
            });
            
            return yoastData;
        },

        collectPostData: function() {
            // Collect basic post data for SEO calculations
            return {
                post_title: $('#post-title-preview').val() || '',
                post_content: $('#post-content-preview').val() || '',
                post_excerpt: $('#post-excerpt-preview').val() || ''
            };
        },

        getSampleFieldValue: function(jsonField) {
            // Get sample value from JSON data for preview
            const jsonData = this.getJSONData();
            if (!jsonData || !jsonField) return '';
            
            // Handle nested field paths
            const pathParts = jsonField.split('.');
            let value = jsonData[0] || jsonData; // Use first item if array
            
            for (const part of pathParts) {
                if (value && typeof value === 'object' && value.hasOwnProperty(part)) {
                    value = value[part];
                } else {
                    return '';
                }
            }
            
            return typeof value === 'string' ? value.substring(0, 100) : String(value).substring(0, 100);
        },

        updateYoastStatus: function(message) {
            $('#yoast-status-message').text(message);
            
            if (this.config.yoastActive) {
                $('#yoast-status-message').addClass('yoast-active').removeClass('yoast-inactive');
            } else {
                $('#yoast-status-message').addClass('yoast-inactive').removeClass('yoast-active');
            }
        },

        // UI helper methods
        showLoading: function(message) {
            // Show loading indicator
            $('.yoast-loading').remove();
            $('#yoast-seo-mapping').prepend(`<div class="yoast-loading notice notice-info"><p>${message}</p></div>`);
        },

        hideLoading: function() {
            $('.yoast-loading').remove();
        },

        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        showError: function(message) {
            this.showNotice(message, 'error');
        },

        showWarning: function(message) {
            this.showNotice(message, 'warning');
        },

        showNotice: function(message, type) {
            const noticeClass = `notice-${type}`;
            const notice = $(`<div class="notice ${noticeClass} is-dismissible yoast-notice"><p>${message}</p></div>`);
            
            $('#yoast-seo-mapping').prepend(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize Yoast SEO integration if the field mapping interface exists
        if ($('#field-mapping-container').length > 0) {
            JSONPostImporterYoastSEO.init();
        }
    });

})(jQuery);