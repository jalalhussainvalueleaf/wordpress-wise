(function ($) {
    'use strict';

    // Nested field mapping functionality
    window.NestedFieldMapping = {
        // Configuration
        config: {
            maxDepth: 5,
            defaultRootPath: 'content',
            expandedNodes: new Set(),
            selectedPaths: new Map()
        },

        // Initialize nested field mapping
        init: function() {
            this.bindEvents();
            this.loadConfiguration();
        },

        // Bind event handlers
        bindEvents: function() {
            // Root path configuration
            $(document).on('change', '#jpi-json-root-path', this.handleRootPathChange.bind(this));
            
            // Tree view interactions
            $(document).on('click', '.jpi-tree-toggle', this.handleTreeToggle.bind(this));
            $(document).on('click', '.jpi-tree-node', this.handleNodeSelection.bind(this));
            
            // Field mapping with nested paths
            $(document).on('change', '.jpi-nested-field-select', this.handleNestedFieldChange.bind(this));
            
            // Validation
            $(document).on('input change', '.jpi-field-mapping input, .jpi-field-mapping select', 
                this.debounce(this.validateNestedPaths.bind(this), 300));
        },

        // Handle root path configuration change
        handleRootPathChange: function(e) {
            const rootPath = $(e.target).val();
            this.config.defaultRootPath = rootPath;
            
            // Regenerate field mapping with new root path
            if (window.currentJsonData) {
                this.renderNestedFieldMapping(window.currentJsonData);
            }
            
            // Save configuration
            this.saveConfiguration();
        },

        // Handle tree node toggle (expand/collapse)
        handleTreeToggle: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $toggle = $(e.target);
            const $node = $toggle.closest('.jpi-tree-node');
            const path = $node.data('path');
            
            if ($node.hasClass('expanded')) {
                $node.removeClass('expanded');
                $node.find('.jpi-tree-children').slideUp(200);
                this.config.expandedNodes.delete(path);
            } else {
                $node.addClass('expanded');
                $node.find('.jpi-tree-children').slideDown(200);
                this.config.expandedNodes.add(path);
            }
            
            this.saveConfiguration();
        },

        // Handle tree node selection for field mapping
        handleNodeSelection: function(e) {
            const $node = $(e.target).closest('.jpi-tree-node');
            const path = $node.data('path');
            const isLeaf = $node.hasClass('leaf-node');
            
            if (isLeaf) {
                // For leaf nodes, show selection options
                this.showFieldMappingOptions(path, $node);
            }
        },

        // Show field mapping options for selected path
        showFieldMappingOptions: function(path, $node) {
            const preview = $node.find('.jpi-node-preview').text();
            
            // Create or update mapping option
            const mappingHtml = `
                <div class="jpi-path-mapping" data-path="${this.escapeHtml(path)}">
                    <div class="jpi-path-info">
                        <strong>Path:</strong> ${this.escapeHtml(path)}
                        <span class="jpi-path-preview">${this.escapeHtml(preview)}</span>
                    </div>
                    <div class="jpi-mapping-controls">
                        <select class="jpi-mapping-type">
                            <option value="">-- Select Field Type --</option>
                            <option value="standard">Standard WordPress Field</option>
                            <option value="yoast_seo">Yoast SEO Field</option>
                            <option value="custom">Custom Meta Field</option>
                            <option value="taxonomy">Taxonomy</option>
                        </select>
                        <div class="jpi-mapping-target" style="display: none;"></div>
                        <button type="button" class="button button-small jpi-remove-mapping">Remove</button>
                    </div>
                </div>
            `;
            
            // Add to selected mappings container
            const $container = $('#jpi-selected-mappings');
            if ($container.find(`[data-path="${path}"]`).length === 0) {
                $container.append(mappingHtml);
            }
            
            // Bind events for new mapping
            this.bindMappingEvents($container.find(`[data-path="${path}"]`));
        },

        // Bind events for mapping controls
        bindMappingEvents: function($mapping) {
            const $typeSelect = $mapping.find('.jpi-mapping-type');
            const $targetContainer = $mapping.find('.jpi-mapping-target');
            const $removeBtn = $mapping.find('.jpi-remove-mapping');
            
            $typeSelect.on('change', (e) => {
                const type = $(e.target).val();
                this.renderMappingTarget(type, $targetContainer);
            });
            
            $removeBtn.on('click', () => {
                $mapping.remove();
                this.validateNestedPaths();
            });
        },

        // Render mapping target based on type
        renderMappingTarget: function(type, $container) {
            let html = '';
            
            switch (type) {
                case 'standard':
                    html = this.renderStandardFieldOptions();
                    break;
                case 'yoast_seo':
                    html = this.renderYoastSeoFieldOptions();
                    break;
                case 'custom':
                    html = this.renderCustomFieldOptions();
                    break;
                case 'taxonomy':
                    html = this.renderTaxonomyOptions();
                    break;
            }
            
            $container.html(html).show();
            this.validateNestedPaths();
        },

        // Render standard WordPress field options
        renderStandardFieldOptions: function() {
            const fields = {
                'post_title': 'Post Title *',
                'post_content': 'Post Content',
                'post_excerpt': 'Post Excerpt',
                'post_status': 'Post Status',
                'post_date': 'Post Date',
                'post_author': 'Post Author',
                'post_name': 'Post Slug'
            };
            
            let html = '<select class="jpi-target-field" name="standard_field">';
            html += '<option value="">-- Select WordPress Field --</option>';
            
            for (const [value, label] of Object.entries(fields)) {
                html += `<option value="${value}">${label}</option>`;
            }
            
            return html + '</select>';
        },

        // Render Yoast SEO field options
        renderYoastSeoFieldOptions: function() {
            const fields = {
                '_yoast_wpseo_title': 'SEO Title',
                '_yoast_wpseo_metadesc': 'Meta Description',
                '_yoast_wpseo_focuskw': 'Focus Keyword',
                '_yoast_wpseo_keywordsynonyms': 'Keyword Synonyms',
                '_yoast_wpseo_canonical': 'Canonical URL',
                '_yoast_wpseo_opengraph-title': 'OpenGraph Title',
                '_yoast_wpseo_opengraph-description': 'OpenGraph Description'
            };
            
            let html = '<select class="jpi-target-field" name="yoast_field">';
            html += '<option value="">-- Select Yoast SEO Field --</option>';
            
            for (const [value, label] of Object.entries(fields)) {
                html += `<option value="${value}">${label}</option>`;
            }
            
            return html + '</select>';
        },

        // Render custom field options
        renderCustomFieldOptions: function() {
            return `
                <input type="text" class="jpi-target-field regular-text" 
                       name="custom_field" placeholder="custom_field_name" 
                       pattern="[a-zA-Z0-9_]+" title="Use only letters, numbers, and underscores">
            `;
        },

        // Render taxonomy options
        renderTaxonomyOptions: function() {
            const taxonomies = jpi_vars.taxonomies || {};
            
            let html = '<select class="jpi-target-field" name="taxonomy">';
            html += '<option value="">-- Select Taxonomy --</option>';
            
            for (const [value, label] of Object.entries(taxonomies)) {
                html += `<option value="${value}">${label}</option>`;
            }
            
            return html + '</select>';
        },

        // Render nested field mapping interface
        renderNestedFieldMapping: function(jsonData) {
            const $container = $('#jpi-nested-field-mapping');
            
            if (!$container.length) {
                return;
            }
            
            // Extract nested structure
            const nestedStructure = this.extractNestedStructure(jsonData);
            
            // Render configuration section
            const configHtml = this.renderConfigurationSection();
            
            // Render tree view
            const treeHtml = this.renderTreeView(nestedStructure);
            
            // Render selected mappings section
            const mappingsHtml = this.renderSelectedMappingsSection();
            
            const html = `
                <div class="jpi-nested-mapping-container">
                    ${configHtml}
                    <div class="jpi-nested-content">
                        <div class="jpi-tree-section">
                            <h4>JSON Structure</h4>
                            <div class="jpi-tree-container">
                                ${treeHtml}
                            </div>
                        </div>
                        <div class="jpi-mappings-section">
                            <h4>Field Mappings</h4>
                            <div id="jpi-selected-mappings">
                                ${mappingsHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $container.html(html);
            
            // Restore expanded state
            this.restoreExpandedState();
        },

        // Render configuration section
        renderConfigurationSection: function() {
            return `
                <div class="jpi-config-section">
                    <div class="jpi-config-row">
                        <label for="jpi-json-root-path">JSON Root Path:</label>
                        <input type="text" id="jpi-json-root-path" class="regular-text" 
                               value="${this.config.defaultRootPath}" 
                               placeholder="content">
                        <p class="description">
                            Specify the root path for content data in nested JSON structures. 
                            Leave empty to use the entire JSON object.
                        </p>
                    </div>
                    <div class="jpi-config-row">
                        <label>
                            <input type="checkbox" id="jpi-import-wrapper-meta" checked>
                            Import wrapper metadata (domain_name, user_id, email, etc.)
                        </label>
                    </div>
                </div>
            `;
        },

        // Extract nested structure from JSON data
        extractNestedStructure: function(jsonData) {
            const items = Array.isArray(jsonData) ? jsonData : [jsonData];
            
            if (items.length === 0) {
                return {};
            }
            
            // Use first item as structure template
            const sampleItem = items[0];
            
            return this.buildNestedStructure(sampleItem, '', 0);
        },

        // Build nested structure recursively
        buildNestedStructure: function(obj, prefix = '', depth = 0) {
            const structure = {};
            
            if (depth >= this.config.maxDepth || typeof obj !== 'object' || obj === null) {
                return structure;
            }
            
            for (const [key, value] of Object.entries(obj)) {
                const currentPath = prefix ? `${prefix}.${key}` : key;
                
                if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                    // Nested object
                    structure[key] = {
                        type: 'object',
                        path: currentPath,
                        children: this.buildNestedStructure(value, currentPath, depth + 1),
                        preview: `{${Object.keys(value).length} fields}`
                    };
                } else {
                    // Leaf value
                    structure[key] = {
                        type: 'leaf',
                        path: currentPath,
                        preview: this.formatPreview(value)
                    };
                }
            }
            
            return structure;
        },

        // Render tree view HTML
        renderTreeView: function(structure, depth = 0) {
            let html = '';
            
            for (const [key, node] of Object.entries(structure)) {
                const isExpanded = this.config.expandedNodes.has(node.path);
                const hasChildren = node.type === 'object' && Object.keys(node.children || {}).length > 0;
                
                html += `
                    <div class="jpi-tree-node ${node.type === 'leaf' ? 'leaf-node' : ''} ${isExpanded ? 'expanded' : ''}" 
                         data-path="${this.escapeHtml(node.path)}" data-depth="${depth}">
                        <div class="jpi-node-content">
                            ${hasChildren ? `<span class="jpi-tree-toggle">${isExpanded ? '▼' : '▶'}</span>` : '<span class="jpi-tree-spacer"></span>'}
                            <span class="jpi-node-key">${this.escapeHtml(key)}</span>
                            <span class="jpi-node-preview">${this.escapeHtml(node.preview)}</span>
                            ${node.type === 'leaf' ? '<button type="button" class="button button-small jpi-map-field">Map Field</button>' : ''}
                        </div>
                        ${hasChildren ? `
                            <div class="jpi-tree-children" style="${isExpanded ? '' : 'display: none;'}">
                                ${this.renderTreeView(node.children, depth + 1)}
                            </div>
                        ` : ''}
                    </div>
                `;
            }
            
            return html;
        },

        // Render selected mappings section
        renderSelectedMappingsSection: function() {
            return '<p class="description">Select fields from the JSON structure above to create field mappings.</p>';
        },

        // Format preview value
        formatPreview: function(value) {
            if (value === null || value === undefined) {
                return '—';
            }
            
            if (typeof value === 'string') {
                return value.length > 30 ? value.substring(0, 30) + '...' : value;
            }
            
            if (Array.isArray(value)) {
                return `[${value.length} items]`;
            }
            
            if (typeof value === 'object') {
                return `{${Object.keys(value).length} fields}`;
            }
            
            return String(value);
        },

        // Validate nested field paths
        validateNestedPaths: function() {
            const errors = [];
            const warnings = [];
            const mappings = this.getFieldMappings();
            
            // Check for required mappings
            if (!mappings.standard || !mappings.standard.post_title) {
                errors.push('Post title mapping is required');
            }
            
            // Validate path formats
            for (const [type, fields] of Object.entries(mappings)) {
                if (type === 'custom') {
                    for (const field of fields) {
                        if (!this.validatePathFormat(field.field)) {
                            errors.push(`Invalid path format: ${field.field}`);
                        }
                        if (!this.validateMetaKey(field.meta_key)) {
                            errors.push(`Invalid meta key: ${field.meta_key}`);
                        }
                    }
                } else if (typeof fields === 'object') {
                    for (const [wpField, jsonPath] of Object.entries(fields)) {
                        if (jsonPath && !this.validatePathFormat(jsonPath)) {
                            errors.push(`Invalid path format for ${wpField}: ${jsonPath}`);
                        }
                    }
                }
            }
            
            // Display validation results
            this.displayValidationResults(errors, warnings);
            
            return errors.length === 0;
        },

        // Validate path format
        validatePathFormat: function(path) {
            if (!path) return true; // Empty paths are allowed
            
            // Check for valid characters and format
            return /^[a-zA-Z0-9_\.]+$/.test(path) && 
                   !path.includes('..') && 
                   !path.startsWith('.') && 
                   !path.endsWith('.');
        },

        // Validate meta key format
        validateMetaKey: function(key) {
            if (!key) return false;
            
            // WordPress meta key validation
            return /^[a-zA-Z0-9_]+$/.test(key);
        },

        // Get current field mappings
        getFieldMappings: function() {
            const mappings = {
                standard: {},
                yoast_seo: {},
                custom: [],
                taxonomies: []
            };
            
            $('#jpi-selected-mappings .jpi-path-mapping').each(function() {
                const $mapping = $(this);
                const path = $mapping.data('path');
                const type = $mapping.find('.jpi-mapping-type').val();
                const target = $mapping.find('.jpi-target-field').val();
                
                if (!type || !target) return;
                
                switch (type) {
                    case 'standard':
                        mappings.standard[target] = path;
                        break;
                    case 'yoast_seo':
                        mappings.yoast_seo[target] = path;
                        break;
                    case 'custom':
                        mappings.custom.push({
                            meta_key: target,
                            field: path
                        });
                        break;
                    case 'taxonomy':
                        mappings.taxonomies.push({
                            taxonomy: target,
                            field: path
                        });
                        break;
                }
            });
            
            return mappings;
        },

        // Display validation results
        displayValidationResults: function(errors, warnings) {
            const $feedback = $('#jpi-nested-validation-feedback');
            
            if (!$feedback.length) {
                $('#jpi-nested-field-mapping').prepend('<div id="jpi-nested-validation-feedback"></div>');
            }
            
            let html = '';
            
            if (errors.length > 0) {
                html += '<div class="notice notice-error"><ul>';
                errors.forEach(error => {
                    html += `<li>${this.escapeHtml(error)}</li>`;
                });
                html += '</ul></div>';
            }
            
            if (warnings.length > 0) {
                html += '<div class="notice notice-warning"><ul>';
                warnings.forEach(warning => {
                    html += `<li>${this.escapeHtml(warning)}</li>`;
                });
                html += '</ul></div>';
            }
            
            if (errors.length === 0 && warnings.length === 0) {
                html = '<div class="notice notice-success"><p>Field mapping validation passed!</p></div>';
            }
            
            $('#jpi-nested-validation-feedback').html(html);
        },

        // Restore expanded state from configuration
        restoreExpandedState: function() {
            this.config.expandedNodes.forEach(path => {
                const $node = $(`.jpi-tree-node[data-path="${path}"]`);
                if ($node.length) {
                    $node.addClass('expanded');
                    $node.find('.jpi-tree-children').show();
                }
            });
        },

        // Save configuration to localStorage
        saveConfiguration: function() {
            const config = {
                defaultRootPath: this.config.defaultRootPath,
                expandedNodes: Array.from(this.config.expandedNodes)
            };
            
            localStorage.setItem('jpi_nested_config', JSON.stringify(config));
        },

        // Load configuration from localStorage
        loadConfiguration: function() {
            const saved = localStorage.getItem('jpi_nested_config');
            
            if (saved) {
                try {
                    const config = JSON.parse(saved);
                    this.config.defaultRootPath = config.defaultRootPath || 'content';
                    this.config.expandedNodes = new Set(config.expandedNodes || []);
                } catch (e) {
                    console.warn('Failed to load nested mapping configuration:', e);
                }
            }
        },

        // Utility: Escape HTML
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // Utility: Debounce function
        debounce: function(func, wait) {
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

        // Detect if JSON has nested structure that would benefit from nested field mapping
        detectNestedStructure: function(jsonData) {
            if (!jsonData) return false;
            
            const items = Array.isArray(jsonData) ? jsonData : [jsonData];
            if (items.length === 0) return false;
            
            const sampleItem = items[0];
            if (typeof sampleItem !== 'object' || sampleItem === null) return false;
            
            // Check for common nested patterns
            const hasContentWrapper = sampleItem.hasOwnProperty('content') && 
                                    typeof sampleItem.content === 'object';
            
            const hasWrapperMetadata = ['domain_name', 'user_id', 'email', 'domain_lang', 'type']
                                     .some(field => sampleItem.hasOwnProperty(field));
            
            const hasNestedObjects = Object.values(sampleItem)
                                   .some(value => typeof value === 'object' && value !== null && !Array.isArray(value));
            
            const hasDeepNesting = this.checkDeepNesting(sampleItem, 0, 2);
            
            return hasContentWrapper || hasWrapperMetadata || hasNestedObjects || hasDeepNesting;
        },

        // Check for deep nesting beyond a certain level
        checkDeepNesting: function(obj, currentDepth, maxDepth) {
            if (currentDepth >= maxDepth) return true;
            
            for (const value of Object.values(obj)) {
                if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                    if (this.checkDeepNesting(value, currentDepth + 1, maxDepth)) {
                        return true;
                    }
                }
            }
            
            return false;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        window.NestedFieldMapping.init();
        
        // Integrate with existing field mapping
        if (window.currentJsonData) {
            window.NestedFieldMapping.renderNestedFieldMapping(window.currentJsonData);
        }
        
        // Listen for JSON data updates from main admin script
        $(document).on('jpi:jsonDataLoaded', function(event, jsonData) {
            if (window.NestedFieldMapping.detectNestedStructure(jsonData)) {
                $('#jpi-nested-field-mapping').show();
                window.NestedFieldMapping.renderNestedFieldMapping(jsonData);
            } else {
                $('#jpi-nested-field-mapping').hide();
            }
        });
    });

})(jQuery);