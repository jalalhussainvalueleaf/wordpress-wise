/**
 * Fix for nested field detection in JSON Post Importer
 * This script ensures that nested fields like content.title are properly detected and displayed
 */

(function($) {
    'use strict';

    // Wait for the document to be ready
    $(document).ready(function() {
        console.log('Nested Fields Fix: Initializing...');

        // Override the detectJsonFields function to ensure nested field detection works
        if (typeof window.detectJsonFields === 'function') {
            console.log('Nested Fields Fix: Overriding detectJsonFields function');
            
            // Store the original function
            window.originalDetectJsonFields = window.detectJsonFields;
            
            // Override with enhanced version
            window.detectJsonFields = function(jsonData) {
                console.log('Enhanced detectJsonFields called with:', jsonData);
                
                var fields = new Set();
                var items = Array.isArray(jsonData) ? jsonData : [jsonData];

                console.log('Processing items:', items);
                
                // Analyze first few items to detect fields (including nested)
                items.slice(0, 5).forEach(function (item) {
                    if (typeof item === 'object' && item !== null) {
                        extractFieldPathsEnhanced(item, '', fields, 0, 4);
                    }
                });

                var fieldArray = Array.from(fields).sort();
                console.log('Enhanced detected fields:', fieldArray);
                return fieldArray;
            };
        }

        // Enhanced field path extraction function
        window.extractFieldPathsEnhanced = function(obj, prefix, fields, depth, maxDepth) {
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
                        extractFieldPathsEnhanced(value, currentPath, fields, depth + 1, maxDepth);
                    }
                }
            }
        };

        // Enhanced field preview function
        window.getNestedValueEnhanced = function(obj, path) {
            return path.split('.').reduce(function(current, key) {
                return current && current[key] !== undefined ? current[key] : null;
            }, obj);
        };

        // Enhanced field preview generation
        window.getFieldPreviewEnhanced = function(fieldPath) {
            if (!window.currentJsonData) return '';
            
            var items = Array.isArray(window.currentJsonData) ? window.currentJsonData : [window.currentJsonData];
            if (items.length === 0) return '';
            
            var sampleItem = items[0];
            var value = getNestedValueEnhanced(sampleItem, fieldPath);
            
            if (value === null || value === undefined) return '';
            
            if (typeof value === 'string') {
                return value.length > 50 ? value.substring(0, 50) + '...' : value;
            }
            
            if (typeof value === 'object') {
                return Array.isArray(value) ? '[Array with ' + value.length + ' items]' : '[Object]';
            }
            
            return String(value);
        };

        // Listen for JSON data loaded events and ensure nested fields are processed
        $(document).on('jpi:jsonDataLoaded', function(event, jsonData) {
            console.log('Nested Fields Fix: JSON data loaded event received', jsonData);
            
            // Test the nested field detection
            if (jsonData && jsonData.length > 0) {
                var testFields = window.detectJsonFields(jsonData);
                console.log('Nested Fields Fix: Test field detection result:', testFields);
                
                var nestedFields = testFields.filter(function(field) {
                    return field.includes('.');
                });
                
                if (nestedFields.length > 0) {
                    console.log('Nested Fields Fix: ✓ Nested fields detected:', nestedFields);
                } else {
                    console.log('Nested Fields Fix: ✗ No nested fields detected');
                }
            }
        });

        // Override the field mapping generation if needed
        if (typeof window.generateFieldMappingHTML === 'function') {
            console.log('Nested Fields Fix: Enhancing generateFieldMappingHTML function');
            
            // Store the original function
            window.originalGenerateFieldMappingHTML = window.generateFieldMappingHTML;
            
            // Create enhanced version
            window.generateFieldMappingHTML = function(availableFields) {
                console.log('Enhanced generateFieldMappingHTML called with fields:', availableFields);
                
                var html = '<div class="jpi-field-mapping-section">';
                html += '<h4>Standard Fields</h4>';
                html += '<p class="description">Map JSON fields to WordPress post fields. Nested fields are shown with dot notation (e.g., content.title).</p>';
                html += '<table class="form-table">';

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
                        var preview = getFieldPreviewEnhanced(jsonField);

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
                html += '</div>';

                // Add JavaScript for field preview updates
                setTimeout(function() {
                    initFieldPreviewUpdatesEnhanced();
                }, 100);

                return html;
            };
        }

        // Enhanced field preview updates
        window.initFieldPreviewUpdatesEnhanced = function() {
            $(document).off('change', '.field-mapping-select').on('change', '.field-mapping-select', function() {
                var $select = $(this);
                var fieldKey = $select.data('field');
                var selectedPath = $select.val();
                var $preview = $('#preview-' + fieldKey);
                
                if (selectedPath && window.currentJsonData) {
                    var preview = getFieldPreviewEnhanced(selectedPath);
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
            $('.field-mapping-select').each(function() {
                if ($(this).val()) {
                    $(this).trigger('change');
                }
            });
        };

        // Utility function for HTML escaping
        if (typeof window.escapeHtml !== 'function') {
            window.escapeHtml = function(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            };
        }

        console.log('Nested Fields Fix: Initialization complete');
    });

})(jQuery);