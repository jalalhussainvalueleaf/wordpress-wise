<?php
/*
Plugin Name: MAD Custom API Endpoint
Description: Provides a custom REST API endpoint for posts, pages, search, categories, and tags with extensible features.
Version: 1.0
Author: Jalal Hussain
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_action('rest_api_init', function() {
    // Posts endpoint
    register_rest_route('customapi/v1', '/posts/', [
        'methods' => 'GET',
        'callback' => function($request) {
            
            $page = max(1, intval($request->get_param('page')));
            //$per_page = max(1, min(100, intval($request->get_param('per_page') ?: 10)));
            
                // Allow per_page up to 50000, default to 100 if not set
    	$per_page = intval($request->get_param('per_page'));
            
            	if ($per_page <= 0) {
        $per_page = 12; // default
    } elseif ($per_page > 50000) {
        $per_page = 50000; // cap
    }
            
            
          // $per_page = max(1, min(100, intval($request->get_param('per_page') ?: 10)));
            $args = [
                'post_type' => 'post',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'post_status' => 'publish',
            ];
            $category = $request->get_param('category');
            if ($category) {
                if (is_numeric($category)) {
                    $args['cat'] = intval($category);
                } else {
                    $args['category_name'] = sanitize_title($category);
                }
            }
            $query = new WP_Query($args);
            $posts = [];
            foreach ($query->posts as $post) {
                // Featured image with alt text
                $featured_image_id = get_post_thumbnail_id($post->ID);
                $featured_image = [
                    'url' => get_the_post_thumbnail_url($post, 'full'),
                    'alt' => $featured_image_id ? get_post_meta($featured_image_id, '_wp_attachment_image_alt', true) : ''
                ];
                
                // Process content images to include alt text
                $content = apply_filters('the_content', $post->post_content);
                $dom = new DOMDocument();
                @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                $images = $dom->getElementsByTagName('img');
                
                $processed_images = [];
                foreach ($images as $img) {
                    $src = $img->getAttribute('src');
                    $alt = $img->getAttribute('alt');
                    $processed_images[] = [
                        'src' => $src,
                        'alt' => $alt,
                        'width' => $img->getAttribute('width'),
                        'height' => $img->getAttribute('height')
                    ];
                }
                
                // Slug
                $slug = $post->post_name;
                // Yoast SEO fields
                $yoast_title = get_post_meta($post->ID, '_yoast_wpseo_title', true);
                $yoast_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
                $yoast_canonical = get_post_meta($post->ID, '_yoast_wpseo_canonical', true);
                $yoast_focuskw = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
                // Schema.org JSON-LD (basic Article)
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Article',
                    'headline' => get_the_title($post),
                    'image' => [
                        '@type' => 'ImageObject',
                        'url' => $featured_image['url'],
                        'width' => 1200,
                        'height' => 630,
                        'alt' => $featured_image['alt']
                    ],
                    'author' => [
                        '@type' => 'Person',
                        'name' => get_the_author_meta('user_login', $post->post_author),
                        'display_name' => get_the_author_meta('display_name', $post->post_author),  
                    ],
                    'datePublished' => get_the_date('c', $post),
                    'dateModified' => get_the_modified_date('c', $post),
                    'mainEntityOfPage' => get_permalink($post),
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => get_bloginfo('name'),
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => get_site_icon_url(),
                        ],
                    ],
                    'description' => get_the_excerpt($post),
                ];
                // Open Graph fields
                $og = [
                    'og:title' => get_the_title($post),
                    'og:description' => get_the_excerpt($post),
                    'og:url' => get_permalink($post),
                    'og:type' => 'article',
                    'og:image' => $featured_image['url'],
                    'og:site_name' => get_bloginfo('name'),
                ];
                // Categories
                $categories = [];
                foreach (wp_get_post_categories($post->ID) as $cat_id) {
                    $cat = get_category($cat_id);
                    if ($cat) {
                        $categories[] = [
                            'id' => $cat->term_id,
                            'name' => $cat->name,
                            'slug' => $cat->slug,
                        ];
                    }
                }
                // Tags
                $tags = [];
                foreach (wp_get_post_tags($post->ID) as $tag) {
                    $tags[] = [
                        'id' => $tag->term_id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                }
                $author_name = get_the_author_meta('user_login', $post->post_author);
                $author_display_name = get_the_author_meta('display_name', $post->post_author);
                $redirection_enabled = get_post_meta($post->ID, '_enable_redirection', true) === '1';
                if ($redirection_enabled) {
                    continue;
                }
                
                // Initialize plugin data struct    ure
                $plugin_data = [
                    'loan_types' => new stdClass(), // Empty object as in the example
                    'repeater' => [
                        'enabled' => true,
                        'data' => [
                            'mad_custom_card_repeater' => [],
                            'mad_custom_card_location' => [],
                            'mad_clusters_data' => []
                        ]
                    ]
                ];
                
                // Get MAD Custom Card Repeater data if it exists
                $mad_crf_data = get_post_meta($post->ID, '_mad_crf_data', true);
                if (!empty($mad_crf_data) && is_array($mad_crf_data)) {
                    $plugin_data['repeater']['data']['mad_custom_card_repeater'] = $mad_crf_data;
                }
                
                // Get MAD Custom Card Location data if it exists
                $mad_crfl_data = get_post_meta($post->ID, '_mad_crfl_data', true);
                if (!empty($mad_crfl_data) && is_array($mad_crfl_data)) {
                    $plugin_data['repeater']['data']['mad_custom_card_location'] = $mad_crfl_data;
                }
                
                // Get MAD Clusters data if it exists
                $mad_clusters_data = get_post_meta($post->ID, '_mad_clusters_data', true);
                if (!empty($mad_clusters_data) && is_array($mad_clusters_data)) {
                    $plugin_data['repeater']['data']['mad_clusters_data'] = $mad_clusters_data;
                }
                
                $posts[] = [
                    'id' => $post->ID,
                    'title' => get_the_title($post),
                    'excerpt' => get_the_excerpt($post),
                    'content' => apply_filters('the_content', $post->post_content),
                    'slug' => $slug,
                    'link' => get_permalink($post),
                    'featured_image' => $featured_image,
                    'author' => $author_name,
                    'yoast' => [
                        'title' => $yoast_title,
                        'description' => $yoast_desc,
                        'canonical' => $yoast_canonical,
                        'focus_keyword' => $yoast_focuskw,
                    ],
                    'schema' => $schema,
                    'og' => $og,
                    'categories' => $categories,
                    'tags' => $tags,
                    'redirection_enabled' => $redirection_enabled,
                    'plugin_data' => $plugin_data, // Add the plugin data here
                ];
            }
            return [
                'posts' => $posts,
                'total' => intval($query->found_posts),
                'total_pages' => intval($query->max_num_pages),
                'page' => $page,
                'per_page' => $per_page,
            ];
        },
        'permission_callback' => '__return_true',
    ]);
    // Pages endpoint
    register_rest_route('customapi/v1', '/pages/', [
        'methods' => 'GET',
        'callback' => function($request) {
            $args = [
                'post_type' => 'page',
                'posts_per_page' => 10,
                'post_status' => 'publish',
            ];
            $query = new WP_Query($args);
            $pages = [];
            foreach ($query->posts as $post) {
                // Get featured image URL
                $featured_image = get_the_post_thumbnail_url($post, 'full');
                // Get categories
                $categories = [];
                foreach (wp_get_post_categories($post->ID) as $cat_id) {
                    $cat = get_category($cat_id);
                    if ($cat) {
                        $categories[] = [
                            'id' => $cat->term_id,
                            'name' => $cat->name,
                            'slug' => $cat->slug,
                        ];
                    }
                }
                $redirection_enabled = get_post_meta($post->ID, '_enable_redirection', true) === '1';
                if ($redirection_enabled) {
                    continue;
                }
                $pages[] = [
                    'id' => $post->ID,
                    'title' => get_the_title($post),
                    'excerpt' => get_the_excerpt($post),
                    'link' => get_permalink($post),
                    'categories' => $categories,
                    'featured_image' => $featured_image,
                    'content_images' => $processed_images,
                    'author' => get_the_author_meta('user_login', $post->post_author),
                    'display_name' => get_the_author_meta('display_name', $post->post_author),
                    'datePublished' => get_the_date('c', $post),
                    'redirection_enabled' => $redirection_enabled,
                ];
            }
            return $pages;
        },
        'permission_callback' => '__return_true',
    ]);
    // Search endpoint
    register_rest_route('customapi/v1', '/search/', [
        'methods' => 'GET',
        'callback' => function($request) {
            $s = sanitize_text_field($request->get_param('s'));
            $args = [
                's' => $s,
                'posts_per_page' => 10,
                'post_status' => 'publish',
                'post_type' => 'post', // Only search posts, exclude pages
            ];
            $query = new WP_Query($args);
            $results = [];
            foreach ($query->posts as $post) {
                $slug = $post->post_name;
                // Get featured image URL
                $featured_image = get_the_post_thumbnail_url($post, 'full');
                // Get categories
                $categories = [];
                foreach (wp_get_post_categories($post->ID) as $cat_id) {
                    $cat = get_category($cat_id);
                    if ($cat) {
                        $categories[] = [
                            'id' => $cat->term_id,
                            'name' => $cat->name,
                            'slug' => $cat->slug,
                        ];
                    }
                }
                $author_name = get_the_author_meta('user_login', $post->post_author);
                $author_display_name = get_the_author_meta('display_name', $post->post_author);
                $schema = [
                    'datePublished' => get_the_date('c', $post),
                ];
                $redirection_enabled = get_post_meta($post->ID, '_enable_redirection', true) === '1';
                if ($redirection_enabled) {
                    continue;
                }
                $results[] = [
                    'id' => $post->ID,
                    'title' => get_the_title($post),
                    'excerpt' => get_the_excerpt($post),
                    'content' => apply_filters('the_content', $post->post_content),
                    'slug' => $slug,
                    'link' => get_permalink($post),
                    'featured_image' => $featured_image,
                    'author' => $author_name,
                    'display_name' => $author_display_name,
                    'schema' => $schema,
                    'categories' => $categories,
                    'redirection_enabled' => $redirection_enabled,
                ];
            }
            return $results;
        },
        'permission_callback' => '__return_true',
    ]);
    // Categories endpoint
    register_rest_route('customapi/v1', '/categories/', [
        'methods' => 'GET',
        'callback' => function() {
            $terms = get_terms([
                'taxonomy' => 'category', 
                'hide_empty' => false,
                'orderby' => 'count',
                'order' => 'DESC',
                'exclude' => [1] // Exclude Uncategorized category (ID 1)
            ]);
            $categories = [];
            foreach ($terms as $term) {
                $categories[] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'count' => $term->count,
                ];
            }
            return $categories;
        },
        'permission_callback' => '__return_true',
    ]);
    // Tags endpoint
    register_rest_route('customapi/v1', '/tags/', [
        'methods' => 'GET',
        'callback' => function() {
            $terms = get_terms([
                'taxonomy' => 'post_tag',
                'hide_empty' => false,
                'orderby' => 'count',
                'order' => 'DESC',
                'number' => 10,
            ]);
            $tags = [];
            foreach ($terms as $term) {
                $tags[] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'count' => $term->count,
                ];
            }
            return $tags;
        },
        'permission_callback' => '__return_true',
    ]);
   
    // Single post by ID or slug endpoint
    register_rest_route('customapi/v1', '/posts/(?P<value>[a-zA-Z0-9-_]+)/', [
    'methods' => 'GET',
    'callback' => function($request) {
        $value = $request['value'];
        if (is_numeric($value)) {
            $post = get_post((int)$value);
        } else {
            $post = get_page_by_path($value, OBJECT, 'post');
        }
        if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'post') {
            return new WP_Error('not_found', 'Post not found', ['status' => 404]);
        }
        // Featured image with alt text
        $featured_image_id = get_post_thumbnail_id($post->ID);
        $featured_image = [
            'url' => get_the_post_thumbnail_url($post, 'full'),
            'alt' => get_post_meta($featured_image_id, '_wp_attachment_image_alt', true) ?? '',
            'title' => $attachment ? $attachment->post_title : ''
        ];
        
        // Process content images to include alt text
        $content = apply_filters('the_content', $post->post_content);
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $images = $dom->getElementsByTagName('img');
        
        $processed_images = [];
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if ($src && strpos($src, 'http') !== 0) {
                $src = site_url($src);
            }
            $processed_images[] = [
                'src' => $src,
                'alt' => $img->getAttribute('alt') ?: '',
                'title' => $img->getAttribute('title') ?: '',
                'width' => $img->getAttribute('width') ? (int)$img->getAttribute('width') : null,
                'height' => $img->getAttribute('height') ? (int)$img->getAttribute('height') : null,
                'class' => $img->getAttribute('class') ?: '',
                'id' => $img->getAttribute('id') ?: ''
            ];
        }
        // Slug
        $slug = $post->post_name;
        // Yoast SEO fields
        $yoast_title = get_post_meta($post->ID, '_yoast_wpseo_title', true);
        $yoast_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        $yoast_canonical = get_post_meta($post->ID, '_yoast_wpseo_canonical', true);
        $yoast_focuskw = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
        
        $yoast = [];
         if (class_exists('WPSEO_Options')) {
            $yoast = [
                'title' => get_post_meta($post->ID, '_yoast_wpseo_title', true),
                'description' => get_post_meta($post->ID, '_yoast_wpseo_metadesc', true),
                'canonical' => get_post_meta($post->ID, '_yoast_wpseo_canonical', true),
                'focuskw' => get_post_meta($post->ID, '_yoast_wpseo_focuskw', true),
                'opengraph' => [
                    'title' => get_post_meta($post->ID, '_yoast_wpseo_opengraph-title', true),
                    'description' => get_post_meta($post->ID, '_yoast_wpseo_opengraph-description', true),
                    'image' => get_post_meta($post->ID, '_yoast_wpseo_opengraph-image', true)
                ],
                'twitter' => [
                    'title' => get_post_meta($post->ID, '_yoast_wpseo_twitter-title', true),
                    'description' => get_post_meta($post->ID, '_yoast_wpseo_twitter-description', true),
                    'image' => get_post_meta($post->ID, '_yoast_wpseo_twitter-image', true)
                ]
            ];
            
            // Get schema data if available (Yoast SEO 11.0+)
            if (class_exists('WPSEO_Schema_Generator')) {
                $schema_generator = new WPSEO_Schema_Generator();
                $yoast['schema'] = $schema_generator->generate($post->ID);
            }
        }
        
        // Custom Plugin Data - Dynamically collect all custom fields
        $custom_data = [];
        $all_meta = get_post_meta($post->ID);
        $custom_patterns = [
            'faq' => ['faq_', 'faq-', '_faq', 'faq_manager'],
            'calculator' => ['calc_', 'calculator_', 'loan_', 'loan-', 'mortgage_'],
            'repeater' => ['repeater_', 'repeat_', '_repeater', 'repeater'],
            'custom' => ['custom_', '_custom', 'field_', '_field', 'meta_']
        ];       
        foreach ($all_meta as $meta_key => $meta_values) {
            if (strpos($meta_key, '_') === 0 && !in_array($meta_key, ['_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_canonical', '_yoast_wpseo_focuskw'])) {
                continue;
            }
            $is_custom = false;
            $plugin_type = 'other';                
            foreach ($custom_patterns as $type => $patterns) {
                foreach ($patterns as $pattern) {
                    if (strpos($meta_key, $pattern) !== false) {
                        $is_custom = true;
                        $plugin_type = $type;
                        break 2;
                    }
                }
            }
            
            if ($is_custom || !in_array($meta_key, ['_edit_lock', '_edit_last', '_thumbnail_id'])) {
                $meta_value = maybe_unserialize($meta_values[0]);
                if (is_array($meta_value)) {
                    $custom_data[$plugin_type][$meta_key] = $meta_value;
                } else {
                    $custom_data[$plugin_type][$meta_key] = $meta_value;
                }
            }
        }
        // Plugin-specific data (FAQ, loan, repeater, ACF, etc.)
        $plugin_specific_data = [];
        $faq_enabled = get_post_meta($post->ID, '_faq_manager_enabled', true);
        if ($faq_enabled === '1') {
            $faq_data = get_post_meta($post->ID, '_faq_manager_data', true);
            if (!empty($faq_data)) {
                $plugin_specific_data['faq'] = [
                    'enabled' => true,
                    'data' => $faq_data
                ];
            }
        }
        $loan_type_enabled = get_post_meta($post->ID, '_loan_type_enabled', true);
        if ($loan_type_enabled === '1') {
            $loan_type_data = get_post_meta($post->ID, '_loan_type_data', true);
            if (!empty($loan_type_data)) {
                $plugin_specific_data['loan_types'] = [
                    'enabled' => true,
                    'data' => $loan_type_data
                ];
            }
        }
        $loan_fields = [];
        $loan_patterns = ['loan_amount', 'interest_rate', 'loan_term', 'monthly_payment', 'total_payment', 'emi_', 'calculator_'];
        foreach ($loan_patterns as $pattern) {
            $value = get_post_meta($post->ID, $pattern, true);
            if (!empty($value)) {
                $loan_fields[$pattern] = $value;
            }
        }
        if (!empty($loan_fields)) {
            $plugin_specific_data['loan_calculator'] = [
                'enabled' => true,
                'data' => $loan_fields
            ];
        }
        $repeater_fields = [];
        $repeater_patterns = ['repeater_', 'repeat_', '_repeater'];
        foreach ($all_meta as $meta_key => $meta_values) {
            foreach ($repeater_patterns as $pattern) {
                if (strpos($meta_key, $pattern) !== false) {
                    $value = maybe_unserialize($meta_values[0]);
                    $repeater_fields[$meta_key] = $value;
                    break;
                }
            }
        }
        $mad_crf_data = get_post_meta($post->ID, '_mad_crf_data', true);
        if (!empty($mad_crf_data) && is_array($mad_crf_data)) {
            foreach ($mad_crf_data as &$item) {
                if (isset($item['image']) && $item['image']) {
                    $item['image'] = wp_get_attachment_url($item['image']);
                }
            }
            unset($item);
            $repeater_fields['mad_custom_card_repeater'] = $mad_crf_data;
        }

        $mad_crfl_data = get_post_meta($post->ID, '_mad_crfl_data', true);
        if (!empty($mad_crfl_data) && is_array($mad_crfl_data)) {
            foreach ($mad_crfl_data as &$item) {
                if (isset($item['image']) && $item['image']) {
                    $item['image'] = wp_get_attachment_url($item['image']);
                }
            }
            unset($item);
            $repeater_fields['mad_custom_card_location'] = $mad_crfl_data;
        }

        $mad_clusters_data = get_post_meta($post->ID, '_mad_clusters_data', true);
        if (!empty($mad_clusters_data) && is_array($mad_clusters_data)) {
            foreach ($mad_clusters_data as &$item) {
                if (isset($item['image']) && $item['image']) {
                    $item['image'] = wp_get_attachment_url($item['image']);
                }
            }
            unset($item);
            $repeater_fields['mad_clusters_data'] = $mad_clusters_data;
        }


        if (!empty($repeater_fields)) {
            $plugin_specific_data['repeater'] = [
                'enabled' => true,
                'data' => $repeater_fields
            ];
        }
        if (function_exists('get_fields')) {
            $acf_fields = get_fields($page->ID);
            if ($acf_fields) {
                $plugin_specific_data['acf'] = [
                    'enabled' => true,
                    'data' => $acf_fields
                ];
            }
        }
        // Check for any other custom meta that might be from plugins
        $other_custom_meta = [];
        $processed_meta_keys = [
            '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_canonical', '_yoast_wpseo_focuskw',
            '_faq_manager_enabled', '_faq_manager_data',
            '_loan_type_enabled', '_loan_type_data',
            '_edit_lock', '_edit_last', '_thumbnail_id'
        ];
        
        foreach ($all_meta as $meta_key => $meta_values) {
            // Skip WordPress core, Yoast, and already processed fields
            if (strpos($meta_key, '_') === 0 && !in_array($meta_key, $processed_meta_keys)) {
                $value = maybe_unserialize($meta_values[0]);
                if (!empty($value)) {
                    // Try to categorize based on meta key patterns
                    $category = 'other';
                    if (strpos($meta_key, 'calculator') !== false || strpos($meta_key, 'calc') !== false) {
                        $category = 'calculator';
                    } elseif (strpos($meta_key, 'loan') !== false) {
                        $category = 'loan';
                    } elseif (strpos($meta_key, 'repeater') !== false || strpos($meta_key, 'repeat') !== false) {
                        $category = 'repeater';
                    } elseif (strpos($meta_key, 'faq') !== false) {
                        $category = 'faq';
                    } elseif (strpos($meta_key, 'custom') !== false) {
                        $category = 'custom';
                    }
                    
                    if (!isset($other_custom_meta[$category])) {
                        $other_custom_meta[$category] = [];
                    }
                    $other_custom_meta[$category][$meta_key] = $value;
                }
            }
        }
        if (!empty($other_custom_meta)) {
            $plugin_specific_data['other_plugins'] = [
                'enabled' => true,
                'data' => $other_custom_meta
            ];
        }
        
        // Schema.org JSON-LD (basic Article)
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title($post),
            'image' => [
                '@type' => 'ImageObject',
                'url' => $featured_image['url'],
                'width' => 1200, // Default width, adjust as needed
                'height' => 630, // Default height, adjust as needed
                'alt' => $featured_image['alt']
            ],
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author_meta('user_login', $post->post_author),
                'display_name' => get_the_author_meta('display_name', $post->post_author),
            ],
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'mainEntityOfPage' => get_permalink($post),
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url(),
                ],
            ],
            'description' => get_the_excerpt($post),
        ];
        // Open Graph fields
        $og = [
            'og:title' => get_the_title($post),
            'og:description' => get_the_excerpt($post),
            'og:url' => get_permalink($post),
            'og:type' => 'article',
            'og:image' => $featured_image,
            'og:site_name' => get_bloginfo('name'),
        ];
        // Categories
        $categories = [];
        foreach (wp_get_post_categories($post->ID) as $cat_id) {
            $cat = get_category($cat_id);
            if ($cat) {
                $categories[] = [
                    'id' => $cat->term_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                ];
            }
        }
        // Tags
        $tags = [];
        foreach (wp_get_post_tags($post->ID) as $tag) {
            $tags[] = [
                'id' => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ];
        }
        // Related articles (by category or tag)
        $related_args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'post__not_in' => [$post->ID],
            'ignore_sticky_posts' => 1,
            'tax_query' => [],
        ];
        $cat_ids = wp_get_post_categories($post->ID);
        $tag_ids = wp_get_post_tags($post->ID, ['fields' => 'ids']);
        $tax_query = [];
        if (!empty($cat_ids)) {
            $tax_query[] = [
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $cat_ids,
            ];
        }
        if (!empty($tag_ids)) {
            $tax_query[] = [
                'taxonomy' => 'post_tag',
                'field' => 'term_id',
                'terms' => $tag_ids,
            ];
        }
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'OR';
        }
        if (!empty($tax_query)) {
            $related_args['tax_query'] = $tax_query;
        }
        $related_query = new WP_Query($related_args);
        $related = [];
        foreach ($related_query->posts as $rel_post) {
            $related[] = [
                'id' => $rel_post->ID,
                'title' => get_the_title($rel_post),
                'link' => get_permalink($rel_post),
                'slug' => $rel_post->post_name,
                'featured_image' => get_the_post_thumbnail_url($rel_post, 'full'),
                'excerpt' => get_the_excerpt($rel_post),
            ];
        }
        return [
            'id' => $post->ID,
            'title' => get_the_title($post),
            'excerpt' => get_the_excerpt($post),
            'content' => apply_filters('the_content', $post->post_content),
            'slug' => $slug,
            'link' => get_permalink($post),
            'featured_image' => $featured_image,
            'content_images' => $processed_images,
            'name' => get_the_author_meta('user_login', $post->post_author),
            'display_name' => get_the_author_meta('display_name', $post->post_author),
            'yoast' => [
                'title' => $yoast_title,
                'description' => $yoast_desc,
                'canonical' => $yoast_canonical,
                'focus_keyword' => $yoast_focuskw,
            ],
            'yoast_json_head' => $yoast,
            'schema' => $schema,
            'og' => $og,
            'categories' => $categories,
            'tags' => $tags,
            'related' => $related,
            'custom_data' => $custom_data,
            'plugin_data' => $plugin_specific_data,
            'redirection_enabled' => get_post_meta($post->ID, '_enable_redirection', true) === '1',
            'redirection_url' => get_post_meta($post->ID, '_enable_redirection', true) === '1' ? (get_post_meta($post->ID, '_redirection_url', true) ?: '/blog') : '',
            'robots' =>
              (get_post_meta($post->ID, '_robots_index', true) === '0' ? 'index' : 'noindex') . ', ' .
              (get_post_meta($post->ID, '_robots_follow', true) === '0' ? 'follow' : 'nofollow'),
        ];
    },
    'permission_callback' => '__return_true',
    ]);


    // Authors endpoint
    register_rest_route('customapi/v1', '/authors/', [
            'methods' => 'GET',
            'callback' => function($request) {
                $per_page = max(1, min(100, intval($request->get_param('per_page') ?: 10)));
                $page = max(1, intval($request->get_param('page') ?: 1));
                $offset = ($page - 1) * $per_page;
    
                $args = [
                    'number' => $per_page,
                    'offset' => $offset,
                    'orderby' => 'display_name',
                    'order' => 'ASC',
                    'has_published_posts' => true,
                    'fields' => 'all_with_meta'
                ];
    
                $user_query = new WP_User_Query($args);
                $authors = [];
                $total_authors = $user_query->total_users;
    
                foreach ($user_query->get_results() as $user) {
                    $author_id = $user->ID;
                    $author_meta = get_user_meta($author_id);
                    
                    // Get author's posts count
                    $posts_count = count_user_posts($author_id, 'post', true);
                    
                    // Get author's avatar
                    $avatar_url = get_avatar_url($author_id, ['size' => 300]);
                    
                    // Get author's social media links if available
                    $social_links = [
                        'website' => $user->user_url,
                        'facebook' => get_the_author_meta('facebook', $author_id),
                        'twitter' => get_the_author_meta('twitter', $author_id),
                        'linkedin' => get_the_author_meta('linkedin', $author_id),
                        'instagram' => get_the_author_meta('instagram', $author_id),
                    ];
                    
                    // Get author's bio/description
                    $bio = get_the_author_meta('description', $author_id);
                    
                    $authors[] = [
                        'id' => $author_id,
                        'username' => $user->user_login,
                        'name' => $user->display_name,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'nickname' => $user->nickname,
                        'email' => $user->user_email,
                        'url' => $user->user_url,
                        'bio' => $bio,
                        'registered_date' => $user->user_registered,
                        'posts_count' => $posts_count,
                        'avatar_url' => $avatar_url,
                        'roles' => $user->roles,
                        'social_links' => array_filter($social_links),
                        'meta' => $author_meta
                    ];
                }
    
                return [
                    'authors' => $authors,
                    'total' => (int) $total_authors,
                    'total_pages' => ceil($total_authors / $per_page),
                    'page' => $page,
                    'per_page' => $per_page,
                ];
            },
            'permission_callback' => '__return_true',
    ]);
    
    // Single Author endpoint - can use ID or display_name
  register_rest_route('customapi/v1', '/authors/(?P<identifier>[^/]+)', [
    'methods' => 'GET',
    'callback' => function($request) {
        $identifier = $request['identifier'];
        $user = null;
        
        // Try to find user by different fields
        if (is_numeric($identifier)) {
            // Try by ID first if numeric
            $user = get_user_by('id', intval($identifier));
        }
		
		        // If not found by ID, try by username
        if (!$user) {
            $user = get_user_by('login', sanitize_user($identifier));
        }
        
        // If still not found, try by email
        if (!$user && is_email($identifier)) {
            $user = get_user_by('email', $identifier);
        }
        
        
        // If still not found, try by display name or nickname
        if (!$user) {
            $users = get_users([
                'meta_query' => [
                    'relation' => 'OR',
                    ['display_name' => $identifier],
                    ['nickname' => $identifier]
                ]
            ]);
            if (!empty($users)) {
                $user = $users[0];
            }
        }
		
		

        if (!$user || !user_can($user->ID, 'edit_posts')) {
            return new WP_Error('author_not_found', 'Author not found', ['status' => 404]);
        }
        
        $author_id = $user->ID;

        // Get author's posts
        $posts = get_posts([
            'author' => $author_id,
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        $author_posts = [];
        foreach ($posts as $post) {
            $author_posts[] = [
                'id' => $post->ID,
                'title' => get_the_title($post),
                'slug' => $post->post_name,
                'excerpt' => get_the_excerpt($post),
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'link' => get_permalink($post),
                'featured_image' => get_the_post_thumbnail_url($post, 'full')
            ];
        }

        // Get author's avatar
        $avatar_url = get_avatar_url($author_id, ['size' => 300]);
        
        // Get author's social media links
        $social_links = [
            'website' => $user->user_url,
            'facebook' => get_the_author_meta('facebook', $author_id),
            'twitter' => get_the_author_meta('twitter', $author_id),
            'linkedin' => get_the_author_meta('linkedin', $author_id),
            'instagram' => get_the_author_meta('instagram', $author_id),
            'youtube' => get_the_author_meta('youtube', $author_id),
        ];
        
        // Get author's bio/description
        $bio = get_the_author_meta('description', $author_id);
        
        // Get author's posts count
        $posts_count = count_user_posts($author_id, 'post', true);
        
        // Get all author meta
        $author_meta = get_user_meta($author_id);

        $author_data = [
            'id' => $user->ID,
            'username' => $user->user_login,
            'name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'nickname' => $user->nickname,
            'email' => $user->user_email,
            'url' => $user->user_url,
            'bio' => $bio,
            'registered_date' => $user->user_registered,
            'posts_count' => $posts_count,
            'avatar_url' => $avatar_url,
            'roles' => $user->roles,
            'social_links' => array_filter($social_links),
            'meta' => $author_meta,
            'recent_posts' => $author_posts
        ];

        return $author_data;
    },
    'permission_callback' => '__return_true',
]);



    // Recent articles endpoint
    register_rest_route('customapi/v1', '/recent/', [
        'methods' => 'GET',
        'callback' => function($request) {
            $args = [
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 10,
                'orderby' => 'date',
                'order' => 'DESC',
            ];
            $query = new WP_Query($args);
            $recent = [];
            foreach ($query->posts as $post) {
                $redirection_enabled = get_post_meta($post->ID, '_enable_redirection', true) === '1';
                if ($redirection_enabled) {
                    continue;
                }
                $recent[] = [
                    'id' => $post->ID,
                    'title' => get_the_title($post),
                    'link' => get_permalink($post),
                    'slug' => $post->post_name,
                    'featured_image' => get_the_post_thumbnail_url($post, 'full'),
                    'excerpt' => get_the_excerpt($post),
                    'redirection_enabled' => $redirection_enabled,
                ];
            }
            return $recent;
        },
        'permission_callback' => '__return_true',
    ]);
    // Single page by ID or slug endpoint
    register_rest_route('customapi/v1', '/pages/(?P<value>[a-zA-Z0-9-_]+)/', [
        'methods' => 'GET',
        'callback' => function($request) {
            $value = $request['value'];
            if (is_numeric($value)) {
                $page = get_post((int)$value);
            } else {
                $page = get_page_by_path($value, OBJECT, 'page');
            }
            if (!$page || $page->post_status !== 'publish' || $page->post_type !== 'page') {
                return new WP_Error('not_found', 'Page not found', ['status' => 404]);
            }
            // Featured image with alt text
            $featured_image_id = get_post_thumbnail_id($page->ID);
            $featured_image = [
                'url' => get_the_post_thumbnail_url($page, 'full'),
                'alt' => $featured_image_id ? get_post_meta($featured_image_id, '_wp_attachment_image_alt', true) : ''
            ];
            
            // Process content images to include alt text
            $content = apply_filters('the_content', $page->post_content);
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $images = $dom->getElementsByTagName('img');
            
            $processed_images = [];
            foreach ($images as $img) {
                $src = $img->getAttribute('src');
                $alt = $img->getAttribute('alt');
                $processed_images[] = [
                    'src' => $src,
                    'alt' => $alt,
                    'width' => $img->getAttribute('width'),
                    'height' => $img->getAttribute('height')
                ];
            }
            
            // Slug
            $slug = $page->post_name;
            // Yoast SEO fields
            $yoast_title = get_post_meta($page->ID, '_yoast_wpseo_title', true);
            $yoast_desc = get_post_meta($page->ID, '_yoast_wpseo_metadesc', true);
            $yoast_canonical = get_post_meta($page->ID, '_yoast_wpseo_canonical', true);
            $yoast_focuskw = get_post_meta($page->ID, '_yoast_wpseo_focuskw', true);

            // Custom Plugin Data - Dynamically collect all custom fields
            $custom_data = [];
            $all_meta = get_post_meta($page->ID);
            $custom_patterns = [
                'faq' => ['faq_', 'faq-', '_faq', 'faq_manager'],
                'calculator' => ['calc_', 'calculator_', 'loan_', 'loan-', 'mortgage_'],
                'repeater' => ['repeater_', 'repeat_', '_repeater', 'repeater'],
                'custom' => ['custom_', '_custom', 'field_', '_field', 'meta_']
            ];
            foreach ($all_meta as $meta_key => $meta_values) {
                if (strpos($meta_key, '_') === 0 && !in_array($meta_key, ['_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_canonical', '_yoast_wpseo_focuskw'])) {
                    continue;
                }
                $is_custom = false;
                $plugin_type = 'other';
                foreach ($custom_patterns as $type => $patterns) {
                    foreach ($patterns as $pattern) {
                        if (strpos($meta_key, $pattern) !== false) {
                            $is_custom = true;
                            $plugin_type = $type;
                            break 2;
                        }
                    }
                }
                if ($is_custom || !in_array($meta_key, ['_edit_lock', '_edit_last', '_thumbnail_id'])) {
                    $meta_value = maybe_unserialize($meta_values[0]);
                    if (is_array($meta_value)) {
                        $custom_data[$plugin_type][$meta_key] = $meta_value;
                    } else {
                        $custom_data[$plugin_type][$meta_key] = $meta_value;
                    }
                }
            }
            // Plugin-specific data (FAQ, loan, repeater, ACF, etc.)
            $plugin_specific_data = [];
            $faq_enabled = get_post_meta($page->ID, '_faq_manager_enabled', true);
            if ($faq_enabled === '1') {
                $faq_data = get_post_meta($page->ID, '_faq_manager_data', true);
                if (!empty($faq_data)) {
                    $plugin_specific_data['faq'] = [
                        'enabled' => true,
                        'data' => $faq_data
                    ];
                }
            }
            $loan_type_enabled = get_post_meta($page->ID, '_loan_type_enabled', true);
            if ($loan_type_enabled === '1') {
                $loan_type_data = get_post_meta($page->ID, '_loan_type_data', true);
                if (!empty($loan_type_data)) {
                    $plugin_specific_data['loan_types'] = [
                        'enabled' => true,
                        'data' => $loan_type_data
                    ];
                }
            }
            $loan_fields = [];
            $loan_patterns = ['loan_amount', 'interest_rate', 'loan_term', 'monthly_payment', 'total_payment', 'emi_', 'calculator_'];
            foreach ($loan_patterns as $pattern) {
                $value = get_post_meta($page->ID, $pattern, true);
                if (!empty($value)) {
                    $loan_fields[$pattern] = $value;
                }
            }
            if (!empty($loan_fields)) {
                $plugin_specific_data['loan_calculator'] = [
                    'enabled' => true,
                    'data' => $loan_fields
                ];
            }
            $repeater_fields = [];
            $repeater_patterns = ['repeater_', 'repeat_', '_repeater'];
            foreach ($all_meta as $meta_key => $meta_values) {
                foreach ($repeater_patterns as $pattern) {
                    if (strpos($meta_key, $pattern) !== false) {
                        $value = maybe_unserialize($meta_values[0]);
                        $repeater_fields[$meta_key] = $value;
                        break;
                    }
                }
            }
            $mad_crf_data = get_post_meta($page->ID, '_mad_crf_data', true);
            if (!empty($mad_crf_data) && is_array($mad_crf_data)) {
                foreach ($mad_crf_data as &$item) {
                    if (isset($item['image']) && $item['image']) {
                        $item['image'] = wp_get_attachment_url($item['image']);
                    }
                }
                unset($item);
                $repeater_fields['mad_custom_card_repeater'] = $mad_crf_data;
            }

            $mad_crfl_data = get_post_meta($page->ID, '_mad_crfl_data', true);
            if (!empty($mad_crfl_data) && is_array($mad_crfl_data)) {
                foreach ($mad_crfl_data as &$item) {
                    if (isset($item['image']) && $item['image']) {
                        $item['image'] = wp_get_attachment_url($item['image']);
                    }
                }
                unset($item);
                $repeater_fields['mad_custom_card_location'] = $mad_crfl_data;
            }

            $mad_clusters_data = get_post_meta($page->ID, '_mad_clusters_data', true);
            if (!empty($mad_clusters_data) && is_array($mad_clusters_data)) {
                foreach ($mad_clusters_data as &$item) {
                    if (isset($item['image']) && $item['image']) {
                        $item['image'] = wp_get_attachment_url($item['image']);
                    }
                }
                unset($item);
                $repeater_fields['mad_clusters_data'] = $mad_clusters_data;
            }


            if (!empty($repeater_fields)) {
                $plugin_specific_data['repeater'] = [
                    'enabled' => true,
                    'data' => $repeater_fields
                ];
            }
            if (function_exists('get_fields')) {
                $acf_fields = get_fields($page->ID);
                if ($acf_fields) {
                    $plugin_specific_data['acf'] = [
                        'enabled' => true,
                        'data' => $acf_fields
                    ];
                }
            }
            $other_custom_meta = [];
            $processed_meta_keys = [
                '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_canonical', '_yoast_wpseo_focuskw',
                '_faq_manager_enabled', '_faq_manager_data',
                '_loan_type_enabled', '_loan_type_data',
                '_edit_lock', '_edit_last', '_thumbnail_id'
            ];
            foreach ($all_meta as $meta_key => $meta_values) {
                if (strpos($meta_key, '_') === 0 && !in_array($meta_key, $processed_meta_keys)) {
                    $value = maybe_unserialize($meta_values[0]);
                    if (!empty($value)) {
                        $category = 'other';
                        if (strpos($meta_key, 'calculator') !== false || strpos($meta_key, 'calc') !== false) {
                            $category = 'calculator';
                        } elseif (strpos($meta_key, 'loan') !== false) {
                            $category = 'loan';
                        } elseif (strpos($meta_key, 'repeater') !== false || strpos($meta_key, 'repeat') !== false) {
                            $category = 'repeater';
                        } elseif (strpos($meta_key, 'faq') !== false) {
                            $category = 'faq';
                        } elseif (strpos($meta_key, 'custom') !== false) {
                            $category = 'custom';
                        }
                        if (!isset($other_custom_meta[$category])) {
                            $other_custom_meta[$category] = [];
                        }
                        $other_custom_meta[$category][$meta_key] = $value;
                    }
                }
            }
            if (!empty($other_custom_meta)) {
                $plugin_specific_data['other_plugins'] = [
                    'enabled' => true,
                    'data' => $other_custom_meta
                ];
            }
            // Schema.org JSON-LD (basic WebPage)
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'headline' => get_the_title($page),
                'image' => [
                    '@type' => 'ImageObject',
                    'url' => $featured_image['url'],
                    'width' => 1200,
                    'height' => 630,
                    'alt' => $featured_image['alt']
                ],
                'author' => [
                    '@type' => 'Person',
                    'name' => get_the_author_meta('user_login', $post->post_author),
                    'display_name' => get_the_author_meta('display_name', $post->post_author),
                ],
                'datePublished' => get_the_date('c', $page),
                'dateModified' => get_the_modified_date('c', $page),
                'mainEntityOfPage' => get_permalink($page),
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => get_bloginfo('name'),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => get_site_icon_url(),
                    ],
                ],
                'description' => get_the_excerpt($page),
            ];
            // Open Graph fields
            $og = [
                'og:title' => get_the_title($page),
                'og:description' => get_the_excerpt($page),
                'og:url' => get_permalink($page),
                'og:type' => 'article',
                'og:image' => $featured_image['url'],
                'og:image:alt' => $featured_image['alt'],
                'og:site_name' => get_bloginfo('name'),
            ];
            // Parent/child pages
            $parent = $page->post_parent ? get_post($page->post_parent) : null;
            $children_query = new WP_Query([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_parent' => $page->ID,
                'posts_per_page' => 10,
            ]);
            $children = [];
            foreach ($children_query->posts as $child) {
                $children[] = [
                    'id' => $child->ID,
                    'title' => get_the_title($child),
                    'slug' => $child->post_name,
                    'link' => get_permalink($child),
                ];
            }
            return [
                'id' => $page->ID,
                'title' => get_the_title($page),
                'excerpt' => get_the_excerpt($page),
                'content' => apply_filters('the_content', $page->post_content),
                'slug' => $slug,
                'link' => get_permalink($page),
                'featured_image' => $featured_image,
                'content_images' => $processed_images,
                'name' => get_the_author_meta('user_login', $post->post_author),
                'display_name' => get_the_author_meta('display_name', $post->post_author),
                'yoast' => [
                    'title' => $yoast_title,
                    'description' => $yoast_desc,
                    'canonical' => $yoast_canonical,
                    'focus_keyword' => $yoast_focuskw,
                ],
                'schema' => $schema,
                'og' => $og,
                'parent' => $parent ? [
                    'id' => $parent->ID,
                    'title' => get_the_title($parent),
                    'slug' => $parent->post_name,
                    'link' => get_permalink($parent),
                ] : null,
                'children' => $children,
                'custom_data' => $custom_data,
                'plugin_data' => $plugin_specific_data,
                'redirection_enabled' => get_post_meta($page->ID, '_enable_redirection', true) === '1',
                'redirection_url' => get_post_meta($page->ID, '_enable_redirection', true) === '1' ? (get_post_meta($page->ID, '_redirection_url', true) ?: '/blog') : '',
                'robots' =>
                  (get_post_meta($page->ID, '_robots_index', true) === '0' ? 'index' : 'noindex') . ', ' .
                  (get_post_meta($page->ID, '_robots_follow', true) === '0' ? 'follow' : 'nofollow'),
            ];
        },
        'permission_callback' => '__return_true',
    ]);
}); 

// Admin settings page for Custom API Endpoints
add_action('admin_menu', function() {
    add_options_page(
        'Custom API Endpoints',
        'Custom API Endpoints',
        'manage_options',
        'custom-api-endpoints',
        'custom_api_endpoints_settings_page'
    );
});

function custom_api_endpoints_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $base_url = get_rest_url(null, 'customapi/v1/');
    $endpoints = [
        'Posts'      => $base_url . 'posts/',
        'Single Post (by ID or Slug)' => $base_url . 'posts/{id}/ OR {slug}/',
        'Recent Articles' => $base_url . 'recent/',
        'Pages'      => $base_url . 'pages/',
        'Single Page (by ID or Slug)' => $base_url . 'pages/{id}/ OR {slug}/',
        'Search'     => $base_url . 'search/?s=example',
        'Categories' => $base_url . 'categories/',
        'Tags'       => $base_url . 'tags/',
        'Authors' => $base_url . 'authors/',
        'Single Author' => $base_url . 'authors/{id}',
    ];
    ?>
<div class="wrap">
    <h1>Custom API Endpoints</h1>
    <p>Below are the available custom API endpoints provided by this plugin:</p>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>Endpoint</th>
                <th>URL</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($endpoints as $name => $url): ?>
            <tr>
                <td><?php echo esc_html($name); ?></td>
                <td><code><?php echo esc_html($url); ?></code></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
} 

?>