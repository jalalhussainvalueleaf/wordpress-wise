/**
 * Debug script for field mapping functionality
 * Add this to the browser console on the JSON Post Importer admin page to debug field mapping issues
 */

console.log('=== JSON Post Importer Field Mapping Debug ===');

// Test data with nested structure
var testData = [
    {
        "domain_name": "example.com",
        "user_id": "123",
        "email": "test@example.com",
        "domain_lang": "en",
        "type": "test-import",
        "content": {
            "title": "Test Post 1 - Enhanced Processing",
            "description": "This is a test post to verify enhanced batch processing functionality.",
            "excerpt": "Test excerpt for enhanced processing",
            "post_date": "2023-01-01 12:00:00",
            "featured_image": "https://via.placeholder.com/800x600.jpg",
            "yoast_seo_title": "Enhanced SEO Title for Test Post 1",
            "yoast_seo_description": "This is an enhanced SEO description for testing purposes.",
            "yoast_focus_keyword": "enhanced processing",
            "categories": ["Technology", "Testing"],
            "tags": ["enhanced", "batch", "processing", "test"],
            "custom_fields": {
                "test_field_1": "Custom value 1",
                "test_field_2": "Custom value 2"
            }
        }
    }
];

console.log('1. Test Data:', testData);

// Check if jQuery is available
if (typeof jQuery !== 'undefined') {
    console.log('✓ jQuery is available');
} else {
    console.error('✗ jQuery is not available');
}

// Check if enhanced field mapping is available
if (typeof window.EnhancedFieldMapping !== 'undefined') {
    console.log('✓ Enhanced Field Mapping is available');
    console.log('Enhanced Field Mapping object:', window.EnhancedFieldMapping);
} else {
    console.log('✗ Enhanced Field Mapping is not available');
}

// Check if standard field mapping functions are available
if (typeof renderFieldMappingUI === 'function') {
    console.log('✓ renderFieldMappingUI function is available');
} else {
    console.log('✗ renderFieldMappingUI function is not available');
}

if (typeof extractFieldsFromJsonData === 'function') {
    console.log('✓ extractFieldsFromJsonData function is available');
} else {
    console.log('✗ extractFieldsFromJsonData function is not available');
}

// Check if the field mapping container exists
var $container = jQuery('#jpi-field-mapping-container');
if ($container.length > 0) {
    console.log('✓ Field mapping container found');
    console.log('Container content:', $container.html());
} else {
    console.log('✗ Field mapping container not found');
}

// Test the field extraction functions from admin.js
if (typeof detectJsonFields === 'function') {
    console.log('✓ detectJsonFields function is available');
    
    try {
        var detectedFields = detectJsonFields(testData);
        console.log('2. Detected Fields:', detectedFields);
        
        // Check if nested fields are detected
        var hasNestedFields = detectedFields.some(function(field) {
            return field.includes('.');
        });
        
        if (hasNestedFields) {
            console.log('✓ Nested fields detected successfully');
            
            // Show some examples
            var nestedFields = detectedFields.filter(function(field) {
                return field.includes('.');
            });
            console.log('Nested fields examples:', nestedFields.slice(0, 10));
        } else {
            console.log('✗ No nested fields detected - this is the problem!');
        }
        
    } catch (error) {
        console.error('Error in detectJsonFields:', error);
    }
} else {
    console.log('✗ detectJsonFields function is not available');
}

// Test the field extraction from the field mapping module
if (typeof extractFieldsFromJsonData === 'function') {
    console.log('✓ extractFieldsFromJsonData function is available');
    
    try {
        var extractedFields = extractFieldsFromJsonData(testData);
        console.log('3. Extracted Fields (from field mapping module):', extractedFields);
        
        // Check if nested fields are extracted
        var hasNestedInExtracted = Object.keys(extractedFields).some(function(field) {
            return field.includes('.');
        });
        
        if (hasNestedInExtracted) {
            console.log('✓ Nested fields extracted successfully by field mapping module');
        } else {
            console.log('✗ No nested fields extracted by field mapping module');
        }
        
    } catch (error) {
        console.error('Error in extractFieldsFromJsonData:', error);
    }
}

// Test manual field extraction
console.log('4. Testing manual field extraction...');

function extractFieldPathsDebug(obj, prefix, fields, depth, maxDepth) {
    console.log('Extracting from:', obj, 'prefix:', prefix, 'depth:', depth);
    
    if (depth >= maxDepth || typeof obj !== 'object' || obj === null) {
        console.log('Stopping extraction - depth limit or invalid object');
        return;
    }

    for (var key in obj) {
        if (obj.hasOwnProperty(key)) {
            var currentPath = prefix ? prefix + '.' + key : key;
            var value = obj[key];
            
            console.log('Processing key:', key, 'path:', currentPath, 'value type:', typeof value);
            
            // Always add the current path
            fields.add(currentPath);
            
            // If the value is an object (but not an array), recurse into it
            if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                console.log('Recursing into object:', currentPath);
                extractFieldPathsDebug(value, currentPath, fields, depth + 1, maxDepth);
            }
        }
    }
}

var manualFields = new Set();
extractFieldPathsDebug(testData[0], '', manualFields, 0, 4);
var manualFieldsArray = Array.from(manualFields).sort();
console.log('Manual extraction result:', manualFieldsArray);

// Test if the current JSON data is set
if (typeof window.currentJsonData !== 'undefined') {
    console.log('✓ window.currentJsonData is set:', window.currentJsonData);
} else {
    console.log('✗ window.currentJsonData is not set');
}

// Test triggering the field mapping manually
console.log('5. Testing manual field mapping trigger...');

try {
    // Set the test data as current JSON data
    window.currentJsonData = testData;
    
    // Trigger the JSON data loaded event
    if (typeof jQuery !== 'undefined') {
        jQuery(document).trigger('jpi:jsonDataLoaded', [testData]);
        console.log('✓ Triggered jpi:jsonDataLoaded event');
    }
    
    // Try to call the field mapping functions directly
    if (typeof renderFieldMappingUI === 'function') {
        console.log('Attempting to call renderFieldMappingUI...');
        renderFieldMappingUI(testData);
        console.log('✓ renderFieldMappingUI called successfully');
    }
    
} catch (error) {
    console.error('Error in manual field mapping trigger:', error);
}

console.log('=== Debug Complete ===');
console.log('If nested fields are not being detected, the issue is in the field extraction logic.');
console.log('If nested fields are detected but not shown in the UI, the issue is in the rendering logic.');