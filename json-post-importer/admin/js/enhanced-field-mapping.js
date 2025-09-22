/**
 * Enhanced Field Mapping UI for JSON Post Importer
 * Integrates nested structure, Yoast SEO, wrapper metadata, presets, validation, and preview
 */

(function ($) {
    'use strict';

    // Enhanced Field Mapping object
    window.EnhancedFieldMapping = {

        // Configuration
        config: {
            nonce: jpi_ajax?.nonce || '',
            ajaxUrl: jpi_ajax?.ajax_url || '',
            currentJsonData: null,
            currentMappings: {},
            presets: {},
            validationRules: {},
            previewData: {},
            autoSaveEnabled: true,
            autoSaveInterval: 30000 // 30 seconds
        },

        // Initialize enhanced field mapping
        init: function () {
            console.log('Enhanced Field Mapping: Initializing...');
            this.bindEvents();
            this.loadPresets();
            this.loadSavedMappings();
            this.initAutoSave();
            this.setupValidation();
            console.log('Enhanced Field Mapping: Initialization complete');
        },

        // Bind event handlers
        bindEvents: function () {
            // Main field mapping events
            $(document).on('jpi:jsonDataLoaded', this.onJsonDataLoaded.bind(this));
            $(document).on('change', '.enhanced-field-mapping', this.onFieldMappingChange.bind(this));
            $(document).on('input', '.enhanced-field-mapping', this.debounce(this.validateMapping.bind(this), 300));

            // Import process integration
            $(document).on('click', '#jpi-import-selected', this.handleImportClick.bind(this));
            $(document).on('jpi:beforeImport', this.onBeforeImport.bind(this));

            // Preset management
            $(document).on('click', '#load-mapping-preset', this.showPresetSelector.bind(this));
            $(document).on('click', '#save-mapping-preset', this.showPresetSaver.bind(this));
            $(document).on('click', '.preset-item', this.loadPreset.bind(this));
            $(document).on('click', '.delete-preset', this.deletePreset.bind(this));

            // Import/Export functionality
            $(document).on('click', '#export-field-mappings', this.exportMappings.bind(this));
            $(document).on('click', '#import-field-mappings', this.showImportDialog.bind(this));
            $(document).on('change', '#mapping-import-file', this.importMappings.bind(this));

            // Preview functionality
            $(document).on('click', '#preview-field-mappings', this.showMappingPreview.bind(this));
            $(document).on('click', '.preview-field-value', this.showFieldValuePreview.bind(this));

            // Validation and feedback
            $(document).on('click', '#validate-all-mappings', this.validateAllMappings.bind(this));
            $(document).on('click', '.fix-mapping-issue', this.fixMappingIssue.bind(this));

            // Auto-detection
            $(document).on('click', '#auto-detect-mappings', this.autoDetectMappings.bind(this));
            $(document).on('click', '#smart-mapping-suggestions', this.showSmartSuggestions.bind(this));

            // Wrapper metadata
            $(document).on('change', '#enable-wrapper-metadata', this.toggleWrapperMetadata.bind(this));
            $(document).on('click', '#configure-wrapper-fields', this.configureWrapperFields.bind(this));
        },

        // Handle JSON data loaded event
        onJsonDataLoaded: function (event, jsonData) {
            this.config.currentJsonData = jsonData;
            this.renderEnhancedFieldMapping(jsonData);
            this.autoDetectMappings();
        },

        // Render the enhanced field mapping interface
        renderEnhancedFieldMapping: function (jsonData) {
            console.log('Enhanced Field Mapping: Rendering with data', jsonData);
            const $container = $('#jpi-field-mapping-container');

            if (!$container.length) {
                console.warn('Field mapping container not found');
                return;
            }

            // Clear existing content
            $container.empty();

            // Render the enhanced interface
            const html = this.buildEnhancedInterface(jsonData);
            $container.html(html);

            // Initialize components
            this.initializeComponents();

            // Show the enhanced interface
            $container.show();
            $('#jpi-import-options').show();
        },

        // Build the enhanced interface HTML
        buildEnhancedInterface: function (jsonData) {
            return `
                <div class="enhanced-field-mapping-container">
                    ${this.buildToolbar()}
                    ${this.buildValidationFeedback()}
                    ${this.buildMappingSections(jsonData)}
                    ${this.buildPreviewSection()}
                    ${this.buildPresetManager()}
                    ${this.buildImportExportSection()}
                </div>
            `;
        },

        // Build toolbar section
        buildToolbar: function () {
            return `
                <div class="field-mapping-toolbar">
                    <div class="toolbar-section">
                        <h3>Enhanced Field Mapping</h3>
                        <div class="toolbar-actions">
                            <button type="button" id="auto-detect-mappings" class="button button-secondary">
                                <span class="dashicons dashicons-search"></span>
                                Auto-Detect
                            </button>
                            <button type="button" id="smart-mapping-suggestions" class="button button-secondary">
                                <span class="dashicons dashicons-lightbulb"></span>
                                Smart Suggestions
                            </button>
                            <button type="button" id="validate-all-mappings" class="button button-secondary">
                                <span class="dashicons dashicons-yes-alt"></span>
                                Validate All
                            </button>
                            <button type="button" id="preview-field-mappings" class="button button-secondary">
                                <span class="dashicons dashicons-visibility"></span>
                                Preview
                            </button>
                        </div>
                    </div>
                    <div class="toolbar-section">
                        <div class="preset-actions">
                            <button type="button" id="load-mapping-preset" class="button">
                                <span class="dashicons dashicons-download"></span>
                                Load Preset
                            </button>
                            <button type="button" id="save-mapping-preset" class="button">
                                <span class="dashicons dashicons-upload"></span>
                                Save Preset
                            </button>
                        </div>
                        <div class="import-export-actions">
                            <button type="button" id="export-field-mappings" class="button">
                                <span class="dashicons dashicons-migrate"></span>
                                Export
                            </button>
                            <button type="button" id="import-field-mappings" class="button">
                                <span class="dashicons dashicons-upload"></span>
                                Import
                            </button>
                        </div>
                    </div>
                </div>
            `;
        },

        // Build validation feedback section
        buildValidationFeedback: function () {
            return `
                <div id="field-mapping-validation" class="validation-feedback-section" style="display: none;">
                    <div class="validation-summary">
                        <div class="validation-stats">
                            <span class="stat-item">
                                <span class="stat-label">Valid:</span>
                                <span id="valid-mappings-count" class="stat-value valid">0</span>
                            </span>
                            <span class="stat-item">
                                <span class="stat-label">Warnings:</span>
                                <span id="warning-mappings-count" class="stat-value warning">0</span>
                            </span>
                            <span class="stat-item">
                                <span class="stat-label">Errors:</span>
                                <span id="error-mappings-count" class="stat-value error">0</span>
                            </span>
                        </div>
                        <div class="validation-actions">
                            <button type="button" id="fix-all-issues" class="button button-secondary">
                                Fix All Issues
                            </button>
                        </div>
                    </div>
                    <div id="validation-details" class="validation-details"></div>
                </div>
            `;
        },

        // Build mapping sections
        buildMappingSections: function (jsonData) {
            return `
                <div class="mapping-sections-container">
                    <div class="mapping-sections-nav">
                        <ul class="section-tabs">
                            <li><a href="#standard-mapping" class="section-tab active">Standard Fields</a></li>
                            <li><a href="#yoast-seo-mapping" class="section-tab">Yoast SEO</a></li>
                            <li><a href="#custom-mapping" class="section-tab">Custom Fields</a></li>
                            <li><a href="#taxonomy-mapping" class="section-tab">Taxonomies</a></li>
                            <li><a href="#wrapper-mapping" class="section-tab">Wrapper Data</a></li>
                        </ul>
                    </div>
                    <div class="mapping-sections-content">
                        ${this.buildStandardFieldsSection(jsonData)}
                        ${this.buildYoastSeoSection(jsonData)}
                        ${this.buildCustomFieldsSection(jsonData)}
                        ${this.buildTaxonomySection(jsonData)}
                        ${this.buildWrapperMetadataSection(jsonData)}
                    </div>
                </div>
            `;
        },

        // Build standard fields section
        buildStandardFieldsSection: function (jsonData) {
            const fields = this.getAvailableJsonFields(jsonData);
            const standardFields = {
                'post_title': { label: 'Post Title', required: true, type: 'text' },
                'post_content': { label: 'Post Content', required: false, type: 'textarea' },
                'post_excerpt': { label: 'Post Excerpt', required: false, type: 'textarea' },
                'post_status': { label: 'Post Status', required: false, type: 'select' },
                'post_date': { label: 'Post Date', required: false, type: 'date' },
                'post_author': { label: 'Post Author', required: false, type: 'number' },
                'post_name': { label: 'Post Slug', required: false, type: 'text' },
                '_thumbnail_id': { label: 'Featured Image', required: false, type: 'media' }
            };

            let html = `
                <div id="standard-mapping" class="mapping-section active">
                    <div class="section-header">
                        <h4>Standard WordPress Fields</h4>
                        <p class="section-description">Map JSON fields to standard WordPress post fields.</p>
                    </div>
                    <div class="fields-grid">
            `;

            for (const [fieldKey, fieldConfig] of Object.entries(standardFields)) {
                html += this.buildFieldMappingRow(fieldKey, fieldConfig, fields, 'standard');
            }

            html += `
                    </div>
                </div>
            `;

            return html;
        },

        // Build Yoast SEO section
        buildYoastSeoSection: function (jsonData) {
            const fields = this.getAvailableJsonFields(jsonData);
            const yoastFields = {
                '_yoast_wpseo_title': { label: 'SEO Title', required: false, type: 'text', maxLength: 60 },
                '_yoast_wpseo_metadesc': { label: 'Meta Description', required: false, type: 'textarea', maxLength: 160 },
                '_yoast_wpseo_focuskw': { label: 'Focus Keyword', required: false, type: 'text' },
                '_yoast_wpseo_keywordsynonyms': { label: 'Keyword Synonyms', required: false, type: 'text' },
                '_yoast_wpseo_canonical': { label: 'Canonical URL', required: false, type: 'url' },
                '_yoast_wpseo_opengraph-title': { label: 'OpenGraph Title', required: false, type: 'text' },
                '_yoast_wpseo_opengraph-description': { label: 'OpenGraph Description', required: false, type: 'textarea' },
                '_yoast_wpseo_twitter-title': { label: 'Twitter Title', required: false, type: 'text' },
                '_yoast_wpseo_twitter-description': { label: 'Twitter Description', required: false, type: 'textarea' }
            };

            let html = `
                <div id="yoast-seo-mapping" class="mapping-section">
                    <div class="section-header">
                        <h4>
                            <span class="dashicons dashicons-admin-generic"></span>
                            Yoast SEO Fields
                        </h4>
                        <p class="section-description">Map JSON fields to Yoast SEO meta fields for better search engine optimization.</p>
                        <div class="yoast-status">
                            <span id="yoast-plugin-status">Checking Yoast SEO status...</span>
                        </div>
                    </div>
                    <div class="fields-grid">
            `;

            for (const [fieldKey, fieldConfig] of Object.entries(yoastFields)) {
                html += this.buildFieldMappingRow(fieldKey, fieldConfig, fields, 'yoast_seo');
            }

            html += `
                    </div>
                    <div class="yoast-actions">
                        <button type="button" id="preview-yoast-seo" class="button button-secondary">
                            Preview SEO
                        </button>
                        <button type="button" id="validate-yoast-fields" class="button button-secondary">
                            Validate SEO Fields
                        </button>
                    </div>
                </div>
            `;

            return html;
        },

        // Build custom fields section
        buildCustomFieldsSection: function (jsonData) {
            const fields = this.getAvailableJsonFields(jsonData);

            return `
                <div id="custom-mapping" class="mapping-section">
                    <div class="section-header">
                        <h4>Custom Meta Fields</h4>
                        <p class="section-description">Create custom field mappings for post meta data.</p>
                        <button type="button" id="add-custom-field" class="button button-secondary">
                            <span class="dashicons dashicons-plus-alt"></span>
                            Add Custom Field
                        </button>
                    </div>
                    <div id="custom-fields-container" class="custom-fields-container">
                        <div class="custom-field-template" style="display: none;">
                            ${this.buildCustomFieldRow(fields)}
                        </div>
                    </div>
                </div>
            `;
        },

        // Build taxonomy section
        buildTaxonomySection: function (jsonData) {
            const fields = this.getAvailableJsonFields(jsonData);
            const taxonomies = jpi_vars?.taxonomies || {
                'category': 'Categories',
                'post_tag': 'Tags'
            };

            let html = `
                <div id="taxonomy-mapping" class="mapping-section">
                    <div class="section-header">
                        <h4>Taxonomy Mappings</h4>
                        <p class="section-description">Map JSON fields to WordPress taxonomies (categories, tags, etc.).</p>
                    </div>
                    <div class="fields-grid">
            `;

            for (const [taxonomyKey, taxonomyLabel] of Object.entries(taxonomies)) {
                const fieldConfig = { label: taxonomyLabel, required: false, type: 'taxonomy' };
                html += this.buildFieldMappingRow(taxonomyKey, fieldConfig, fields, 'taxonomy');
            }

            html += `
                    </div>
                </div>
            `;

            return html;
        },

        // Build wrapper metadata section
        buildWrapperMetadataSection: function (jsonData) {
            const wrapperFields = this.detectWrapperFields(jsonData);

            let html = `
                <div id="wrapper-mapping" class="mapping-section">
                    <div class="section-header">
                        <h4>Wrapper Metadata</h4>
                        <p class="section-description">Import wrapper metadata (domain_name, user_id, email, etc.) as post meta fields.</p>
                        <label class="wrapper-toggle">
                            <input type="checkbox" id="enable-wrapper-metadata" checked>
                            Enable wrapper metadata import
                        </label>
                    </div>
                    <div id="wrapper-fields-container" class="wrapper-fields-container">
            `;

            if (wrapperFields.length > 0) {
                for (const field of wrapperFields) {
                    html += this.buildWrapperFieldRow(field);
                }
            } else {
                html += '<p class="no-wrapper-fields">No wrapper fields detected in the JSON structure.</p>';
            }

            html += `
                    </div>
                    <button type="button" id="configure-wrapper-fields" class="button button-secondary">
                        Configure Wrapper Fields
                    </button>
                </div>
            `;

            return html;
        },

        // Build field mapping row
        buildFieldMappingRow: function (fieldKey, fieldConfig, availableFields, section) {
            const fieldId = `${section}_${fieldKey.replace(/[^a-zA-Z0-9]/g, '_')}`;
            const currentMapping = this.config.currentMappings[section]?.[fieldKey] || '';

            return `
                <div class="field-mapping-row" data-field="${fieldKey}" data-section="${section}">
                    <div class="field-info">
                        <label for="${fieldId}">
                            ${fieldConfig.label}
                            ${fieldConfig.required ? '<span class="required">*</span>' : ''}
                        </label>
                        ${fieldConfig.maxLength ? `<span class="field-limit">(max: ${fieldConfig.maxLength} chars)</span>` : ''}
                        <span class="field-type">${fieldConfig.type}</span>
                    </div>
                    <div class="field-mapping">
                        <select name="${fieldId}" id="${fieldId}" class="enhanced-field-mapping" 
                                data-field="${fieldKey}" data-section="${section}">
                            <option value="">-- Select JSON Field --</option>
                            ${this.buildFieldOptions(availableFields, currentMapping)}
                        </select>
                    </div>
                    <div class="field-preview">
                        <button type="button" class="preview-field-value button button-small" 
                                data-field="${fieldKey}" data-section="${section}">
                            Preview
                        </button>
                        <span class="preview-value"></span>
                    </div>
                    <div class="field-validation">
                        <span class="validation-indicator"></span>
                    </div>
                </div>
            `;
        },

        // Build custom field row
        buildCustomFieldRow: function (availableFields) {
            return `
                <div class="custom-field-row">
                    <div class="custom-field-info">
                        <input type="text" class="custom-field-key" placeholder="meta_key_name" 
                               pattern="[a-zA-Z0-9_]+" title="Use only letters, numbers, and underscores">
                        <select class="custom-field-type">
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="url">URL</option>
                            <option value="email">Email</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    <div class="custom-field-mapping">
                        <select class="enhanced-field-mapping custom-field-source">
                            <option value="">-- Select JSON Field --</option>
                            ${this.buildFieldOptions(availableFields)} -1
                        </select>
                    </div>
                    <div class="custom-field-actions">
                        <button type="button" class="preview-custom-field button button-small">Preview</button>
                        <button type="button" class="remove-custom-field button button-small">Remove</button>
                    </div>
                </div>
            `;
        },

        // Build wrapper field row
        buildWrapperFieldRow: function (field) {
            return `
                <div class="wrapper-field-row" data-wrapper-field="${field.key}">
                    <div class="wrapper-field-info">
                        <span class="wrapper-field-key">${field.key}</span>
                        <span class="wrapper-field-preview">${field.preview}</span>
                    </div>
                    <div class="wrapper-field-mapping">
                        <input type="text" class="wrapper-meta-key" 
                               value="_${field.key}" placeholder="meta_key_name">
                        <label>
                            <input type="checkbox" class="wrapper-field-enabled" checked>
                            Import this field
                        </label>
                    </div>
                </div>
            `;
        },

        // Build field options for select elements
        buildFieldOptions: function (fields, selectedValue = '') {
            let html = '';

            for (const field of fields) {
                const selected = field.path === selectedValue ? 'selected' : '';
                html += `<option value="${field.path}" ${selected}>${field.path} (${field.preview})</option>`;
            }

            return html;
        },

        // Build preview section
        buildPreviewSection: function () {
            return `
                <div id="mapping-preview-section" class="preview-section" style="display: none;">
                    <div class="preview-header">
                        <h4>Field Mapping Preview</h4>
                        <button type="button" id="close-preview" class="button button-secondary">Close</button>
                    </div>
                    <div id="mapping-preview-content" class="preview-content">
                        <!-- Preview content will be populated here -->
                    </div>
                </div>
            `;
        },

        // Build preset manager
        buildPresetManager: function () {
            return `
                <div id="preset-manager" class="preset-manager" style="display: none;">
                    <div class="preset-header">
                        <h4>Field Mapping Presets</h4>
                        <button type="button" id="close-preset-manager" class="button button-secondary">Close</button>
                    </div>
                    <div class="preset-content">
                        <div class="preset-list">
                            <div id="preset-items-container">
                                <!-- Preset items will be populated here -->
                            </div>
                        </div>
                        <div class="preset-actions">
                            <div class="preset-save-form" style="display: none;">
                                <input type="text" id="preset-name" placeholder="Preset name">
                                <textarea id="preset-description" placeholder="Description (optional)"></textarea>
                                <button type="button" id="save-preset" class="button button-primary">Save Preset</button>
                                <button type="button" id="cancel-save-preset" class="button">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        // Build import/export section
        buildImportExportSection: function () {
            return `
                <div id="import-export-section" class="import-export-section" style="display: none;">
                    <div class="import-export-header">
                        <h4>Import/Export Field Mappings</h4>
                        <button type="button" id="close-import-export" class="button button-secondary">Close</button>
                    </div>
                    <div class="import-export-content">
                        <div class="export-section">
                            <h5>Export Current Mappings</h5>
                            <p>Download your current field mappings as a JSON file.</p>
                            <button type="button" id="download-mappings" class="button button-primary">
                                Download Mappings
                            </button>
                        </div>
                        <div class="import-section">
                            <h5>Import Mappings</h5>
                            <p>Upload a previously exported field mappings file.</p>
                            <input type="file" id="mapping-import-file" accept=".json" style="display: none;">
                            <button type="button" id="select-import-file" class="button button-secondary">
                                Select File
                            </button>
                            <div id="import-file-info" style="display: none;"></div>
                        </div>
                    </div>
                </div>
            `;
        },

        // Get available JSON fields from data
        getAvailableJsonFields: function (jsonData) {
            if (!jsonData) return [];

            const items = Array.isArray(jsonData) ? jsonData : [jsonData];
            if (items.length === 0) return [];

            const sampleItem = items[0];
            return this.extractFieldPaths(sampleItem, '', 0, 5);
        },

        // Extract field paths recursively
        extractFieldPaths: function (obj, prefix = '', depth = 0, maxDepth = 5) {
            const fields = [];

            if (depth >= maxDepth || typeof obj !== 'object' || obj === null) {
                return fields;
            }

            for (const [key, value] of Object.entries(obj)) {
                const currentPath = prefix ? `${prefix}.${key}` : key;

                if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                    // Add the object itself as a field option
                    fields.push({
                        path: currentPath,
                        preview: `{${Object.keys(value).length} fields}`,
                        type: 'object'
                    });

                    // Recursively extract nested fields
                    fields.push(...this.extractFieldPaths(value, currentPath, depth + 1, maxDepth));
                } else {
                    // Leaf value
                    fields.push({
                        path: currentPath,
                        preview: this.formatPreview(value),
                        type: this.detectFieldType(value)
                    });
                }
            }

            return fields;
        },

        // Format preview value
        formatPreview: function (value) {
            if (value === null || value === undefined) {
                return '—';
            }

            if (typeof value === 'string') {
                return value.length > 30 ? value.substring(0, 30) + '...' : value;
            }

            if (Array.isArray(value)) {
                if (value.length === 0) return '[empty array]';
                const firstItem = value[0];
                if (typeof firstItem === 'string') {
                    return `[${value.length} items] "${firstItem.substring(0, 20)}..."`;
                }
                return `[${value.length} items]`;
            }

            if (typeof value === 'object') {
                const keys = Object.keys(value);
                if (keys.length === 0) return '{empty object}';

                // Show first few key-value pairs for better preview
                const preview = keys.slice(0, 2).map(key => {
                    const val = value[key];
                    if (typeof val === 'string') {
                        return `${key}: "${val.substring(0, 15)}..."`;
                    } else if (typeof val === 'object') {
                        return `${key}: {object}`;
                    }
                    return `${key}: ${val}`;
                }).join(', ');

                return `{${keys.length} fields} ${preview}`;
            }

            return String(value);
        },

        // Detect field type from value
        detectFieldType: function (value) {
            if (typeof value === 'string') {
                if (value.match(/^\d{4}-\d{2}-\d{2}/)) return 'date';
                if (value.match(/^https?:\/\//)) return 'url';
                if (value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) return 'email';
                if (value.length > 100) return 'textarea';
                return 'text';
            }

            if (typeof value === 'number') return 'number';
            if (typeof value === 'boolean') return 'checkbox';
            if (Array.isArray(value)) return 'array';
            if (typeof value === 'object') return 'json';
            return 'text';
        },

        // Detect wrapper fields
        detectWrapperFields: function (jsonData) {
            if (!jsonData) return [];

            const items = Array.isArray(jsonData) ? jsonData : [jsonData];
            if (items.length === 0) return [];

            const sampleItem = items[0];
            const wrapperFields = [];
            const commonWrapperKeys = ['domain_name', 'user_id', 'email', 'domain_lang', 'type', 'source', 'timestamp'];

            for (const key of commonWrapperKeys) {
                if (sampleItem.hasOwnProperty(key)) {
                    wrapperFields.push({
                        key: key,
                        preview: this.formatPreview(sampleItem[key]),
                        type: this.detectFieldType(sampleItem[key])
                    });
                }
            }

            return wrapperFields;
        },

        // Initialize components after rendering
        initializeComponents: function () {
            this.initSectionTabs();
            this.initCustomFields();
            this.initValidation();
            this.checkYoastStatus();
        },

        // Initialize section tabs
        initSectionTabs: function () {
            $(document).on('click', '.section-tab', function (e) {
                e.preventDefault();
                const target = $(this).attr('href');

                $('.section-tab').removeClass('active');
                $('.mapping-section').removeClass('active');

                $(this).addClass('active');
                $(target).addClass('active');
            });
        },

        // Initialize custom fields functionality
        initCustomFields: function () {
            $(document).on('click', '#add-custom-field', () => {
                const template = $('.custom-field-template').html();
                $('#custom-fields-container').append(`<div class="custom-field-item">${template}</div>`);
            });

            $(document).on('click', '.remove-custom-field', function () {
                $(this).closest('.custom-field-item').remove();
            });
        },

        // Initialize validation
        initValidation: function () {
            this.config.validationRules = {
                required: ['post_title'],
                maxLength: {
                    '_yoast_wpseo_title': 60,
                    '_yoast_wpseo_metadesc': 160
                },
                format: {
                    'post_date': 'date',
                    '_yoast_wpseo_canonical': 'url'
                }
            };
        },

        // Check Yoast SEO status
        checkYoastStatus: function () {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'jpi_check_yoast_status',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const status = response.data.yoast_active ?
                            '<span class="yoast-active">✓ Yoast SEO is active</span>' :
                            '<span class="yoast-inactive">⚠ Yoast SEO is not active</span>';
                        $('#yoast-plugin-status').html(status);
                    }
                }
            });
        },

        // Auto-detect field mappings
        autoDetectMappings: function () {
            if (!this.config.currentJsonData) return;

            console.log('Auto-detecting field mappings for data:', this.config.currentJsonData);

            const mappings = this.performAutoDetection(this.config.currentJsonData);
            console.log('Auto-detected mappings:', mappings);

            this.applyMappings(mappings);
            this.validateAllMappings();

            // Update previews for all mapped fields
            $('.enhanced-field-mapping').each((index, element) => {
                this.updateFieldPreview($(element));
            });

            this.showSuccess('Field mappings auto-detected successfully');
        },

        // Perform auto-detection logic
        performAutoDetection: function (jsonData) {
            const mappings = {
                standard: {},
                yoast_seo: {},
                custom: [],
                taxonomies: [],
                wrapper: {}
            };

            const fields = this.getAvailableJsonFields(jsonData);

            // Auto-detect standard fields
            const standardMappings = {
                'post_title': ['title', 'heading', 'name', 'post_title', 'short_headline', 'headline', 'content.title', 'content.heading', 'content.short_headline'],
                'post_content': ['content', 'body', 'description', 'text', 'post_content', 'long_description', 'content.description', 'content.content', 'content.body', 'content.long_description'],
                'post_excerpt': ['excerpt', 'summary', 'short_description', 'content.excerpt', 'content.summary', 'content.short_description'],
                'post_date': ['date', 'created_at', 'published_at', 'post_date', 'publish_date', 'content.date', 'content.publish_date'],
                'post_status': ['status', 'post_status', 'content.status'],
                '_thumbnail_id': ['featured_image', 'image', 'thumbnail', 'featured_img', 'content.featured_image', 'content.image']
            };

            console.log('Available fields for auto-detection:', fields.map(f => f.path));

            for (const [wpField, possiblePaths] of Object.entries(standardMappings)) {
                for (const path of possiblePaths) {
                    const field = fields.find(f => f.path === path || f.path.endsWith('.' + path));
                    if (field) {
                        mappings.standard[wpField] = field.path;
                        console.log(`Mapped ${wpField} to ${field.path}`);
                        break;
                    }
                }
            }

            // Auto-detect Yoast SEO fields
            const yoastMappings = {
                '_yoast_wpseo_title': ['seo_title', 'yoast_title', 'meta_title', 'content.yoast_seo_title'],
                '_yoast_wpseo_metadesc': ['seo_description', 'meta_description', 'yoast_description', 'content.yoast_seo_description'],
                '_yoast_wpseo_focuskw': ['focus_keyword', 'keyword', 'seo_keyword', 'content.yoast_focus_keyword'],
                '_yoast_wpseo_keywordsynonyms': ['keywords', 'synonyms', 'content.yoast_keywords']
            };

            for (const [yoastField, possiblePaths] of Object.entries(yoastMappings)) {
                for (const path of possiblePaths) {
                    const field = fields.find(f => f.path === path || f.path.endsWith('.' + path));
                    if (field) {
                        mappings.yoast_seo[yoastField] = field.path;
                        break;
                    }
                }
            }

            // Auto-detect taxonomies
            const taxonomyMappings = {
                'category': ['categories', 'category', 'content.categories'],
                'post_tag': ['tags', 'tag', 'content.tags']
            };

            for (const [taxonomy, possiblePaths] of Object.entries(taxonomyMappings)) {
                for (const path of possiblePaths) {
                    const field = fields.find(f => f.path === path);
                    if (field) {
                        mappings.taxonomies.push({
                            taxonomy: taxonomy,
                            field: field.path
                        });
                        break;
                    }
                }
            }

            // Auto-detect wrapper metadata
            const wrapperFields = this.detectWrapperFields(jsonData);
            for (const wrapperField of wrapperFields) {
                mappings.wrapper[wrapperField.key] = `_${wrapperField.key}`;
            }

            return mappings;
        },

        // Apply mappings to the interface
        applyMappings: function (mappings) {
            // Apply standard field mappings
            for (const [field, path] of Object.entries(mappings.standard)) {
                $(`.enhanced-field-mapping[data-field="${field}"][data-section="standard"]`).val(path);
            }

            // Apply Yoast SEO mappings
            for (const [field, path] of Object.entries(mappings.yoast_seo)) {
                $(`.enhanced-field-mapping[data-field="${field}"][data-section="yoast_seo"]`).val(path);
            }

            // Apply taxonomy mappings
            for (const mapping of mappings.taxonomies) {
                $(`.enhanced-field-mapping[data-field="${mapping.taxonomy}"][data-section="taxonomy"]`).val(mapping.field);
            }

            // Apply wrapper mappings
            for (const [field, metaKey] of Object.entries(mappings.wrapper)) {
                $(`.wrapper-meta-key[data-wrapper-field="${field}"]`).val(metaKey);
            }

            // Store current mappings
            this.config.currentMappings = mappings;
        },

        // Validate all mappings
        validateAllMappings: function () {
            const validation = {
                valid: 0,
                warnings: 0,
                errors: 0,
                details: []
            };

            // Validate required fields
            for (const requiredField of this.config.validationRules.required) {
                const mapping = $(`.enhanced-field-mapping[data-field="${requiredField}"]`).val();
                if (!mapping) {
                    validation.errors++;
                    validation.details.push({
                        type: 'error',
                        field: requiredField,
                        message: 'Required field is not mapped'
                    });
                } else {
                    validation.valid++;
                }
            }

            // Validate field lengths
            for (const [field, maxLength] of Object.entries(this.config.validationRules.maxLength)) {
                const mapping = $(`.enhanced-field-mapping[data-field="${field}"]`).val();
                if (mapping) {
                    const sampleValue = this.getSampleValue(mapping);
                    if (sampleValue && sampleValue.length > maxLength) {
                        validation.warnings++;
                        validation.details.push({
                            type: 'warning',
                            field: field,
                            message: `Sample value exceeds recommended length (${sampleValue.length}/${maxLength})`
                        });
                    }
                }
            }

            this.displayValidationResults(validation);
        },

        // Display validation results
        displayValidationResults: function (validation) {
            $('#valid-mappings-count').text(validation.valid);
            $('#warning-mappings-count').text(validation.warnings);
            $('#error-mappings-count').text(validation.errors);

            const $details = $('#validation-details');
            $details.empty();

            for (const detail of validation.details) {
                const className = detail.type === 'error' ? 'validation-error' : 'validation-warning';
                $details.append(`
                    <div class="validation-item ${className}">
                        <strong>${detail.field}:</strong> ${detail.message}
                        <button type="button" class="fix-mapping-issue button button-small" 
                                data-field="${detail.field}" data-type="${detail.type}">Fix</button>
                    </div>
                `);
            }

            $('#field-mapping-validation').show();
        },

        // Get sample value for a field path
        getSampleValue: function (path) {
            if (!this.config.currentJsonData) return '';

            const items = Array.isArray(this.config.currentJsonData) ?
                this.config.currentJsonData : [this.config.currentJsonData];
            if (items.length === 0) return '';

            const pathParts = path.split('.');
            let value = items[0];

            for (const part of pathParts) {
                if (value && typeof value === 'object' && value.hasOwnProperty(part)) {
                    value = value[part];
                } else {
                    return '';
                }
            }

            return typeof value === 'string' ? value : String(value);
        },

        // Show mapping preview
        showMappingPreview: function () {
            const mappings = this.collectCurrentMappings();
            const previewData = this.generatePreviewData(mappings);
            const html = this.buildPreviewHTML(previewData);

            $('#mapping-preview-content').html(html);
            $('#mapping-preview-section').show();
        },

        // Collect current mappings from the interface
        collectCurrentMappings: function () {
            const mappings = {
                standard: {},
                yoast_seo: {},
                custom: [],
                taxonomies: [],
                wrapper: {}
            };

            // Collect standard mappings
            $('.enhanced-field-mapping[data-section="standard"]').each(function () {
                const field = $(this).data('field');
                const value = $(this).val();
                if (value) {
                    mappings.standard[field] = value;
                }
            });

            // Collect Yoast SEO mappings
            $('.enhanced-field-mapping[data-section="yoast_seo"]').each(function () {
                const field = $(this).data('field');
                const value = $(this).val();
                if (value) {
                    mappings.yoast_seo[field] = value;
                }
            });

            // Collect custom field mappings
            $('.custom-field-item').each(function () {
                const metaKey = $(this).find('.custom-field-key').val();
                const jsonField = $(this).find('.custom-field-source').val();
                const fieldType = $(this).find('.custom-field-type').val();

                if (metaKey && jsonField) {
                    mappings.custom.push({
                        meta_key: metaKey,
                        field: jsonField,
                        type: fieldType
                    });
                }
            });

            // Collect taxonomy mappings
            $('.enhanced-field-mapping[data-section="taxonomy"]').each(function () {
                const taxonomy = $(this).data('field');
                const value = $(this).val();
                if (value) {
                    mappings.taxonomies.push({
                        taxonomy: taxonomy,
                        field: value
                    });
                }
            });

            // Collect wrapper mappings
            $('.wrapper-field-row').each(function () {
                const wrapperField = $(this).data('wrapper-field');
                const metaKey = $(this).find('.wrapper-meta-key').val();
                const enabled = $(this).find('.wrapper-field-enabled').is(':checked');

                if (enabled && metaKey) {
                    mappings.wrapper[wrapperField] = metaKey;
                }
            });

            return mappings;
        },

        // Generate preview data
        generatePreviewData: function (mappings) {
            const preview = {
                standard: {},
                yoast_seo: {},
                custom: {},
                taxonomies: {},
                wrapper: {}
            };

            // Generate standard field previews
            for (const [field, path] of Object.entries(mappings.standard)) {
                preview.standard[field] = {
                    path: path,
                    value: this.getSampleValue(path),
                    type: this.detectFieldType(this.getSampleValue(path))
                };
            }

            // Generate Yoast SEO previews
            for (const [field, path] of Object.entries(mappings.yoast_seo)) {
                preview.yoast_seo[field] = {
                    path: path,
                    value: this.getSampleValue(path),
                    type: this.detectFieldType(this.getSampleValue(path))
                };
            }

            // Generate custom field previews
            for (const customField of mappings.custom) {
                preview.custom[customField.meta_key] = {
                    path: customField.field,
                    value: this.getSampleValue(customField.field),
                    type: customField.type
                };
            }

            // Generate taxonomy previews
            for (const taxonomyMapping of mappings.taxonomies) {
                preview.taxonomies[taxonomyMapping.taxonomy] = {
                    path: taxonomyMapping.field,
                    value: this.getSampleValue(taxonomyMapping.field),
                    type: 'taxonomy'
                };
            }

            // Generate wrapper previews
            for (const [field, metaKey] of Object.entries(mappings.wrapper)) {
                preview.wrapper[metaKey] = {
                    path: field,
                    value: this.getSampleValue(field),
                    type: 'wrapper'
                };
            }

            return preview;
        },

        // Build preview HTML
        buildPreviewHTML: function (previewData) {
            let html = '<div class="preview-sections">';

            // Standard fields preview
            if (Object.keys(previewData.standard).length > 0) {
                html += '<div class="preview-section-block">';
                html += '<h5>Standard WordPress Fields</h5>';
                html += '<div class="preview-fields">';
                for (const [field, data] of Object.entries(previewData.standard)) {
                    html += `
                        <div class="preview-field">
                            <strong>${field}:</strong>
                            <span class="preview-path">${data.path}</span>
                            <span class="preview-value">${this.escapeHtml(data.value)}</span>
                        </div>
                    `;
                }
                html += '</div></div>';
            }

            // Yoast SEO fields preview
            if (Object.keys(previewData.yoast_seo).length > 0) {
                html += '<div class="preview-section-block">';
                html += '<h5>Yoast SEO Fields</h5>';
                html += '<div class="preview-fields">';
                for (const [field, data] of Object.entries(previewData.yoast_seo)) {
                    html += `
                        <div class="preview-field">
                            <strong>${field}:</strong>
                            <span class="preview-path">${data.path}</span>
                            <span class="preview-value">${this.escapeHtml(data.value)}</span>
                        </div>
                    `;
                }
                html += '</div></div>';
            }

            // Custom fields preview
            if (Object.keys(previewData.custom).length > 0) {
                html += '<div class="preview-section-block">';
                html += '<h5>Custom Meta Fields</h5>';
                html += '<div class="preview-fields">';
                for (const [metaKey, data] of Object.entries(previewData.custom)) {
                    html += `
                        <div class="preview-field">
                            <strong>${metaKey}:</strong>
                            <span class="preview-path">${data.path}</span>
                            <span class="preview-value">${this.escapeHtml(data.value)}</span>
                        </div>
                    `;
                }
                html += '</div></div>';
            }

            // Taxonomies preview
            if (Object.keys(previewData.taxonomies).length > 0) {
                html += '<div class="preview-section-block">';
                html += '<h5>Taxonomy Mappings</h5>';
                html += '<div class="preview-fields">';
                for (const [taxonomy, data] of Object.entries(previewData.taxonomies)) {
                    html += `
                        <div class="preview-field">
                            <strong>${taxonomy}:</strong>
                            <span class="preview-path">${data.path}</span>
                            <span class="preview-value">${this.escapeHtml(data.value)}</span>
                        </div>
                    `;
                }
                html += '</div></div>';
            }

            // Wrapper metadata preview
            if (Object.keys(previewData.wrapper).length > 0) {
                html += '<div class="preview-section-block">';
                html += '<h5>Wrapper Metadata</h5>';
                html += '<div class="preview-fields">';
                for (const [metaKey, data] of Object.entries(previewData.wrapper)) {
                    html += `
                        <div class="preview-field">
                            <strong>${metaKey}:</strong>
                            <span class="preview-path">${data.path}</span>
                            <span class="preview-value">${this.escapeHtml(data.value)}</span>
                        </div>
                    `;
                }
                html += '</div></div>';
            }

            html += '</div>';
            return html;
        },

        // Load presets
        loadPresets: function () {
            const savedPresets = localStorage.getItem('jpi_field_mapping_presets');
            if (savedPresets) {
                try {
                    this.config.presets = JSON.parse(savedPresets);
                } catch (e) {
                    console.warn('Failed to load field mapping presets:', e);
                    this.config.presets = {};
                }
            }

            // Add default presets
            this.addDefaultPresets();
        },

        // Add default presets
        addDefaultPresets: function () {
            const defaultPresets = {
                'wordpress-standard': {
                    name: 'WordPress Standard',
                    description: 'Standard WordPress post fields mapping',
                    mappings: {
                        standard: {
                            'post_title': 'title',
                            'post_content': 'content',
                            'post_excerpt': 'excerpt',
                            'post_date': 'date',
                            'post_status': 'status'
                        }
                    }
                },
                'nested-content': {
                    name: 'Nested Content Structure',
                    description: 'For JSON with content wrapper',
                    mappings: {
                        standard: {
                            'post_title': 'content.title',
                            'post_content': 'content.description',
                            'post_excerpt': 'content.excerpt',
                            'post_date': 'content.date'
                        },
                        wrapper: {
                            'domain_name': '_domain_name',
                            'user_id': '_source_user_id',
                            'email': '_source_email'
                        }
                    }
                },
                'yoast-seo-complete': {
                    name: 'Complete Yoast SEO',
                    description: 'Full Yoast SEO fields mapping',
                    mappings: {
                        standard: {
                            'post_title': 'content.title',
                            'post_content': 'content.description'
                        },
                        yoast_seo: {
                            '_yoast_wpseo_title': 'content.yoast_seo_title',
                            '_yoast_wpseo_metadesc': 'content.yoast_seo_description',
                            '_yoast_wpseo_focuskw': 'content.yoast_focus_keyword',
                            '_yoast_wpseo_keywordsynonyms': 'content.yoast_keywords'
                        }
                    }
                }
            };

            // Merge with existing presets
            this.config.presets = { ...defaultPresets, ...this.config.presets };
        },

        // Save presets to localStorage
        savePresets: function () {
            localStorage.setItem('jpi_field_mapping_presets', JSON.stringify(this.config.presets));
        },

        // Show preset selector
        showPresetSelector: function () {
            this.renderPresetList();
            $('#preset-manager').show();
        },

        // Render preset list
        renderPresetList: function () {
            const $container = $('#preset-items-container');
            $container.empty();

            for (const [key, preset] of Object.entries(this.config.presets)) {
                const html = `
                    <div class="preset-item" data-preset="${key}">
                        <div class="preset-info">
                            <h6>${preset.name}</h6>
                            <p>${preset.description || 'No description'}</p>
                        </div>
                        <div class="preset-actions">
                            <button type="button" class="load-preset button button-primary" data-preset="${key}">Load</button>
                            <button type="button" class="delete-preset button button-secondary" data-preset="${key}">Delete</button>
                        </div>
                    </div>
                `;
                $container.append(html);
            }
        },

        // Load preset
        loadPreset: function (e) {
            const presetKey = $(e.target).data('preset');
            const preset = this.config.presets[presetKey];

            if (preset && preset.mappings) {
                this.applyMappings(preset.mappings);
                this.validateAllMappings();
                $('#preset-manager').hide();
                this.showSuccess(`Preset "${preset.name}" loaded successfully`);
            }
        },

        // Show preset saver
        showPresetSaver: function () {
            $('.preset-save-form').show();
            $('#preset-name').focus();
        },

        // Save current mappings as preset
        saveCurrentAsPreset: function () {
            const name = $('#preset-name').val().trim();
            const description = $('#preset-description').val().trim();

            if (!name) {
                this.showError('Please enter a preset name');
                return;
            }

            const mappings = this.collectCurrentMappings();
            const presetKey = name.toLowerCase().replace(/[^a-z0-9]/g, '-');

            this.config.presets[presetKey] = {
                name: name,
                description: description,
                mappings: mappings,
                created: new Date().toISOString()
            };

            this.savePresets();
            this.renderPresetList();
            $('.preset-save-form').hide();
            $('#preset-name').val('');
            $('#preset-description').val('');
            this.showSuccess(`Preset "${name}" saved successfully`);
        },

        // Delete preset
        deletePreset: function (e) {
            e.stopPropagation();
            const presetKey = $(e.target).data('preset');
            const preset = this.config.presets[presetKey];

            if (confirm(`Are you sure you want to delete the preset "${preset.name}"?`)) {
                delete this.config.presets[presetKey];
                this.savePresets();
                this.renderPresetList();
                this.showSuccess(`Preset "${preset.name}" deleted`);
            }
        },

        // Export mappings
        exportMappings: function () {
            const mappings = this.collectCurrentMappings();
            const exportData = {
                version: '1.0',
                exported: new Date().toISOString(),
                mappings: mappings
            };

            const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `field-mappings-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            this.showSuccess('Field mappings exported successfully');
        },

        // Show import dialog
        showImportDialog: function () {
            $('#import-export-section').show();
            $(document).on('click', '#select-import-file', function () {
                $('#mapping-import-file').click();
            });
        },

        // Import mappings
        importMappings: function (e) {
            const file = e.target.files[0];
            if (!file) return;

            if (file.type !== 'application/json') {
                this.showError('Please select a valid JSON file');
                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                try {
                    const importData = JSON.parse(event.target.result);
                    if (importData.mappings) {
                        this.applyMappings(importData.mappings);
                        this.validateAllMappings();
                        $('#import-export-section').hide();
                        this.showSuccess('Field mappings imported successfully');
                    } else {
                        this.showError('Invalid mapping file format');
                    }
                } catch (error) {
                    this.showError('Failed to parse mapping file: ' + error.message);
                }
            };

            reader.readAsText(file);
        },

        // Initialize auto-save
        initAutoSave: function () {
            if (this.config.autoSaveEnabled) {
                setInterval(() => {
                    this.autoSaveMappings();
                }, this.config.autoSaveInterval);
            }
        },

        // Auto-save current mappings
        autoSaveMappings: function () {
            const mappings = this.collectCurrentMappings();
            localStorage.setItem('jpi_auto_saved_mappings', JSON.stringify({
                timestamp: new Date().toISOString(),
                mappings: mappings
            }));
        },

        // Load saved mappings
        loadSavedMappings: function () {
            const saved = localStorage.getItem('jpi_auto_saved_mappings');
            if (saved) {
                try {
                    const data = JSON.parse(saved);
                    // Only load if saved within last 24 hours
                    const savedTime = new Date(data.timestamp);
                    const now = new Date();
                    const hoursDiff = (now - savedTime) / (1000 * 60 * 60);

                    if (hoursDiff < 24) {
                        this.config.currentMappings = data.mappings;
                    }
                } catch (e) {
                    console.warn('Failed to load auto-saved mappings:', e);
                }
            }
        },

        // Setup validation
        setupValidation: function () {
            // Real-time validation on field changes
            $(document).on('change', '.enhanced-field-mapping', (e) => {
                this.validateSingleField($(e.target));
            });
        },

        // Validate single field
        validateSingleField: function ($field) {
            const field = $field.data('field');
            const section = $field.data('section');
            const value = $field.val();
            const $indicator = $field.closest('.field-mapping-row').find('.validation-indicator');

            let isValid = true;
            let message = '';

            // Check if required field is mapped
            if (this.config.validationRules.required.includes(field) && !value) {
                isValid = false;
                message = 'Required field';
            }

            // Check field length limits
            if (value && this.config.validationRules.maxLength[field]) {
                const sampleValue = this.getSampleValue(value);
                const maxLength = this.config.validationRules.maxLength[field];
                if (sampleValue.length > maxLength) {
                    isValid = false;
                    message = `Exceeds max length (${sampleValue.length}/${maxLength})`;
                }
            }

            // Update validation indicator
            $indicator.removeClass('valid invalid warning');
            if (!value) {
                $indicator.addClass('empty').html('');
            } else if (isValid) {
                $indicator.addClass('valid').html('<span class="dashicons dashicons-yes-alt"></span>');
            } else {
                $indicator.addClass('invalid').html(`<span class="dashicons dashicons-warning"></span> ${message}`);
            }
        },

        // Event handlers
        onFieldMappingChange: function (e) {
            const $field = $(e.target);
            const field = $field.data('field');
            const section = $field.data('section');
            const value = $field.val();

            // Update current mappings
            if (!this.config.currentMappings[section]) {
                this.config.currentMappings[section] = {};
            }
            this.config.currentMappings[section][field] = value;

            // Update preview
            this.updateFieldPreview($field);

            // Validate field
            this.validateSingleField($field);
        },

        // Update field preview
        updateFieldPreview: function ($field) {
            const value = $field.val();
            const $preview = $field.closest('.field-mapping-row').find('.preview-value');

            if (value) {
                const sampleValue = this.getSampleValue(value);
                if (sampleValue) {
                    // Handle different types of values for better preview
                    let previewText = '';
                    if (typeof sampleValue === 'object') {
                        previewText = JSON.stringify(sampleValue).substring(0, 50) + '...';
                    } else {
                        previewText = String(sampleValue).substring(0, 50);
                        if (String(sampleValue).length > 50) {
                            previewText += '...';
                        }
                    }
                    $preview.text(previewText);
                } else {
                    $preview.text('No sample data');
                }
            } else {
                $preview.text('');
            }
        },

        // Utility functions
        debounce: function (func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showSuccess: function (message) {
            this.showNotice(message, 'success');
        },

        showError: function (message) {
            this.showNotice(message, 'error');
        },

        showWarning: function (message) {
            this.showNotice(message, 'warning');
        },

        showNotice: function (message, type) {
            const $notice = $(`<div class="notice notice-${type} is-dismissible enhanced-mapping-notice"><p>${message}</p></div>`);
            $('.enhanced-field-mapping-container').prepend($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        },

        // Validate mapping before import
        validateMapping: function () {
            const mappings = this.collectCurrentMappings();

            console.log('Validating mappings:', mappings);

            // Check if post_title is mapped (required field)
            if (!mappings.standard || !mappings.standard.post_title) {
                console.error('Post title mapping validation failed');
                this.showError('Post title mapping is required. Please select a JSON field for the post title.');

                // Highlight the post title field
                const $titleField = $('.enhanced-field-mapping[data-field="post_title"][data-section="standard"]');
                if ($titleField.length) {
                    $titleField.addClass('validation-error').focus();
                    setTimeout(() => {
                        $titleField.removeClass('validation-error');
                    }, 3000);
                }

                return false;
            }

            // Validate that the mapped field actually exists in the JSON data
            const titleValue = this.getSampleValue(mappings.standard.post_title);
            if (!titleValue) {
                console.error('Post title field has no sample data');
                this.showWarning('The selected post title field appears to be empty in the sample data. Please verify your selection.');
            }

            console.log('Mapping validation passed');
            return true;
        },

        // Get current mappings for import
        getCurrentMappingsForImport: function () {
            if (!this.validateMapping()) {
                return null;
            }

            const mappings = this.collectCurrentMappings();

            // Format mappings for server
            const formattedMappings = {
                field_mappings: {
                    standard: mappings.standard || {},
                    yoast_seo: mappings.yoast_seo || {},
                    custom: mappings.custom || [],
                    taxonomies: mappings.taxonomies || [],
                    wrapper: mappings.wrapper || {}
                }
            };

            console.log('Formatted mappings for import:', formattedMappings);
            return formattedMappings;
        },

        showFieldValuePreview: function () {
            // Placeholder for field value preview
        },

        fixMappingIssue: function () {
            // Placeholder for fixing mapping issues
        },

        showSmartSuggestions: function () {
            // Placeholder for smart suggestions
        },

        toggleWrapperMetadata: function () {
            // Placeholder for wrapper metadata toggle
        },

        configureWrapperFields: function () {
            // Placeholder for wrapper fields configuration
        },

        // Handle import button click
        handleImportClick: function (e) {
            console.log('Import button clicked, validating field mappings...');

            const mappings = this.getCurrentMappingsForImport();
            if (!mappings) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

            // Store mappings in a hidden field or trigger custom event
            if ($('#field-mappings-data').length === 0) {
                $('body').append('<input type="hidden" id="field-mappings-data" />');
            }
            $('#field-mappings-data').val(JSON.stringify(mappings));

            // Trigger custom event with mappings
            $(document).trigger('jpi:fieldMappingsReady', [mappings]);

            console.log('Field mappings validated and ready for import');
        },

        // Handle before import event
        onBeforeImport: function (event, importData) {
            const mappings = this.getCurrentMappingsForImport();
            if (mappings && importData) {
                // Merge field mappings into import data
                Object.assign(importData, mappings);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        // Initialize enhanced field mapping if the container exists
        if ($('#jpi-field-mapping-container').length > 0) {
            window.EnhancedFieldMapping.init();
        }
    });

    // Listen for JSON data loaded events
    $(document).on('jpi:jsonDataLoaded', function (event, jsonData) {
        if (window.EnhancedFieldMapping) {
            window.EnhancedFieldMapping.onJsonDataLoaded(event, jsonData);
        }
    });

    // Global function to get current field mappings (for integration with main import process)
    window.getEnhancedFieldMappings = function () {
        if (window.EnhancedFieldMapping) {
            return window.EnhancedFieldMapping.getCurrentMappingsForImport();
        }
        return null;
    };

    // Global function to validate field mappings
    window.validateEnhancedFieldMappings = function () {
        if (window.EnhancedFieldMapping) {
            return window.EnhancedFieldMapping.validateMapping();
        }
        return false;
    };

})(jQuery);