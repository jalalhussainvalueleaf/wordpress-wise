/**
 * Cluster Manager Admin Scripts
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Initialize cluster admin functionality
        clusterAdmin.init();

    });

    var clusterAdmin = {

        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },

        bindEvents: function() {
            // Add event listeners here
        },

        initializeComponents: function() {
            this.initializeShortcodeGenerator();
            this.initializePreview();
        },

        initializeShortcodeGenerator: function() {
            // Add shortcode generator button to TinyMCE
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                tinymce.get('content').addButton('cluster_shortcode', {
                    title: 'Insert Cluster Shortcode',
                    icon: 'dashicons dashicons-groups',
                    onclick: function() {
                        clusterAdmin.openShortcodeModal();
                    }
                });
            }
        },

        initializePreview: function() {
            // Auto-refresh preview when content changes
            $('#title, #content, #excerpt').on('input', function() {
                clusterAdmin.debounce(clusterAdmin.refreshPreview, 1000)();
            });
        },

        openShortcodeModal: function() {
            var modalHtml = `
                <div id="cluster-shortcode-modal" style="display: none;">
                    <div class="cluster-modal-content">
                        <div class="cluster-modal-header">
                            <h2>Insert Cluster Shortcode</h2>
                            <button type="button" class="cluster-modal-close">&times;</button>
                        </div>
                        <div class="cluster-modal-body">
                            <p>Select a cluster to insert its shortcode:</p>
                            <select id="cluster-select">
                                <option value="">Select a cluster...</option>
                                ${this.getClusterOptions()}
                            </select>
                            <div id="shortcode-preview" style="margin-top: 15px; padding: 10px; background: #f1f1f1; border: 1px solid #ddd; display: none;">
                                <strong>Shortcode:</strong><br>
                                <code id="shortcode-code"></code>
                            </div>
                        </div>
                        <div class="cluster-modal-footer">
                            <button type="button" class="button" id="insert-shortcode">Insert Shortcode</button>
                            <button type="button" class="button button-secondary cluster-modal-close">Cancel</button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            $('#cluster-shortcode-modal').fadeIn();

            this.bindModalEvents();
        },

        getClusterOptions: function() {
            var options = '';
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_clusters_list',
                    nonce: cluster_manager.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        response.data.forEach(function(cluster) {
                            options += `<option value="${cluster.id}">${cluster.title}</option>`;
                        });
                        $('#cluster-select').html(options);
                    }
                }
            });
            return options;
        },

        bindModalEvents: function() {
            var self = this;

            $('#cluster-select').on('change', function() {
                var clusterId = $(this).val();
                if (clusterId) {
                    var shortcode = `[cluster id="${clusterId}"]`;
                    $('#shortcode-code').text(shortcode);
                    $('#shortcode-preview').show();
                } else {
                    $('#shortcode-preview').hide();
                }
            });

            $('#insert-shortcode').on('click', function() {
                var clusterId = $('#cluster-select').val();
                if (clusterId) {
                    var shortcode = `[cluster id="${clusterId}"]`;
                    if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                        tinymce.get('content').insertContent(shortcode);
                    } else {
                        // Fallback for other editors
                        var content = $('#content').val();
                        $('#content').val(content + '\n' + shortcode);
                    }
                    self.closeModal();
                }
            });

            $('.cluster-modal-close').on('click', function() {
                self.closeModal();
            });

            $('#cluster-shortcode-modal').on('click', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
        },

        closeModal: function() {
            $('#cluster-shortcode-modal').fadeOut(function() {
                $(this).remove();
            });
        },

        refreshPreview: function() {
            var postId = $('#post_ID').val();
            if (postId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'refresh_cluster_preview',
                        post_id: postId,
                        nonce: cluster_manager.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            $('.cluster-preview-content').html(response.data);
                        }
                    }
                });
            }
        },

        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

})(jQuery);
