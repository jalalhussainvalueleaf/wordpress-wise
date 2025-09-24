jQuery(document).ready(function($) {
    // --- SETUP --- //
    const ajaxurl = ifsc_ajax_obj.ajax_url;
    const nonce = ifsc_ajax_obj.nonce;
    let currentPage = 1;
    let uploadFile = null;
    let uploadFileToken = null;
    let totalUploadRows = 0;

    // --- UTILITY FUNCTIONS --- //
    function showToast(msg, type = 'success') {
        const toast = $('#ifsc-toast');
        toast.text(msg).removeClass('success error').addClass(type).addClass('show');
        setTimeout(() => toast.removeClass('show'), 4000);
    }

    function toggleModal(modalId, show) {
        $(modalId).fadeToggle(200, show ? 'swing' : 'linear');
    }
    $('.modal-close, .modal-cancel').on('click', function() {
        $(this).closest('.ifsc-modal').fadeOut(200);
    });

    // --- DATA TABLE & PAGINATION --- //
    function refreshTable(page = 1) {
        currentPage = page;
        const ifscFilterValue = $('#ifsc_code_filter').val().trim();

        let payload = {
            action: 'get_filtered_data',
            nonce: nonce,
            page: currentPage
        };

        // If IFSC filter has a value, prioritize it
        if (ifscFilterValue) {
            payload.ifsc_code = ifscFilterValue;
        } else {
            // Otherwise, use the dropdown filters
            payload.bank_name = $('#bank_name').val();
            payload.state = $('#state').val();
            payload.district = $('#district').val();
            payload.branch = $('#branch').val();
        }

        $('#ifsc-table-body').html('<tr><td colspan="6">Loading...</td></tr>');

        $.post(ajaxurl, payload, function(res) {
            const tbody = $('#ifsc-table-body').empty();
            if (res.success && res.data.rows.length) {
                res.data.rows.forEach(r => {
                    tbody.append(`
                        <tr data-id="${r.id}">
                            <td data-col="bank_name" data-original="${r.bank_name}">${r.bank_name}</td>
                            <td data-col="branch" data-original="${r.branch}">${r.branch}</td>
                            <td data-col="ifsc" data-original="${r.ifsc}">${r.ifsc}</td>
                            <td data-col="micr" data-original="${r.micr}">${r.micr}</td>
                            <td data-col="contact_number" data-original="${r.contact_number}">${r.contact_number}</td>
                            <td data-col="address" data-original="${r.address}">${r.address}</td>
                            <td data-col="city_state" data-original="${r.city}, ${r.state}">${r.city}, ${r.state}</td>
                            <td class="ifsc-table-actions">
                                <button class="button edit-btn" data-id="${r.id}">Edit</button>
                                <button class="button delete-btn" data-id="${r.id}" data-ifsc="${r.ifsc}">Delete</button>
                            </td>
                        </tr>`);
                });
                renderPagination(res.data.pagination);
            } else {
                tbody.append('<tr><td colspan="6">No data found for the selected criteria.</td></tr>');
                $('#pagination-controls').empty();
            }
        });
    }

    function renderPagination(pagination) {
        const container = $('#pagination-controls').empty();
        if (pagination.total_pages <= 1) return;
        container.append(`<span class="displaying-num">${pagination.total_items} items</span>`);
        const nav = $('<span class="pagination-links"></span>');
        nav.append(`<a class="first-page button" ${pagination.current_page === 1 ? 'disabled' : ''} href="#" data-page="1">&laquo;</a>`);
        nav.append(`<a class="prev-page button" ${pagination.current_page === 1 ? 'disabled' : ''} href="#" data-page="${pagination.current_page - 1}">&lsaquo;</a>`);
        nav.append(`<span class="paging-input"><label for="current-page-selector" class="screen-reader-text">Current Page</label><input class="current-page" id="current-page-selector" type="text" name="paged" value="${pagination.current_page}" size="2" aria-describedby="table-paging"> of <span class="total-pages">${pagination.total_pages}</span></span>`);
        nav.append(`<a class="next-page button" ${pagination.current_page === pagination.total_pages ? 'disabled' : ''} href="#" data-page="${pagination.current_page + 1}">&rsaquo;</a>`);
        nav.append(`<a class="last-page button" ${pagination.current_page === pagination.total_pages ? 'disabled' : ''} href="#" data-page="${pagination.total_pages}">&raquo;</a>`);
        container.append(nav);
    }
    
    $(document).on('click', '#pagination-controls a', function(e) {
        e.preventDefault();
        if ($(this).is('[disabled]')) return;
        refreshTable(parseInt($(this).data('page')));
    });
    
    $(document).on('keypress', '#pagination-controls .current-page', function(e) {
        if (e.which === 13) {
            refreshTable(parseInt($(this).val()));
        }
    });

    // --- DYNAMIC FILTERING --- //
    function populateDropdown(selector, filterType, dependencies = {}) {
        const select = $(selector);
        select.prop('disabled', true).html('<option value="">Loading...</option>');
        $.post(ajaxurl, {
            action: 'get_ifsc_filter_options', nonce: nonce, filter: filterType, dependencies: dependencies
        }, function(res) {
            select.empty().append(`<option value="">Select ${filterType.charAt(0).toUpperCase() + filterType.slice(1, -1)}</option>`);
            if (res.success && res.data.length) {
                res.data.forEach(v => select.append(`<option value="${v}">${v}</option>`));
                select.prop('disabled', false);
            } else {
                select.html(`<option value="">No options found</option>`);
                select.prop('disabled', true);
            }
        });
    }

    function resetFilters(levels) {
        levels.forEach(level => {
            const select = $(`#${level}`);
            select.prop('disabled', true).html(`<option value="">Select ${level.charAt(0).toUpperCase() + level.slice(1)}</option>`);
        });
    }

    // Cascading filter logic
    $('#bank_name').on('change', function() {
        const bankName = $(this).val();
        resetFilters(['state', 'district', 'branch']);
        
        if (bankName) {
            // Clear IFSC filter when dropdown is used
            $('#ifsc_code_filter').val('');
            populateDropdown('#state', 'states', { bank_name: bankName });
        }
    });

    $('#state').on('change', function() {
        const bankName = $('#bank_name').val();
        const stateName = $(this).val();
        resetFilters(['district', 'branch']);
        
        if (stateName && bankName) {
            populateDropdown('#district', 'districts', { bank_name: bankName, state: stateName });
        }
    });

    $('#district').on('change', function() {
        const bankName = $('#bank_name').val();
        const stateName = $('#state').val();
        const districtName = $(this).val();
        resetFilters(['branch']);
        
        if (districtName && bankName && stateName) {
            populateDropdown('#branch', 'branches', { bank_name: bankName, state: stateName, district: districtName });
        }
    });

    // Handle IFSC code filter input
    $('#ifsc_code_filter').on('input', function() {
        if ($(this).val().trim()) {
            // Clear all dropdown filters when IFSC is typed
            $('#bank_name').val('');
            $('#state').val('');
            $('#district').val('');
            $('#branch').val('');
            resetFilters(['state', 'district', 'branch']);
        }
    });

    // Handle dropdown focus to clear IFSC filter
    $('#bank_name, #state, #district, #branch').on('focus', function() {
        $('#ifsc_code_filter').val('');
    });

    // Apply and Reset filters
    $('#apply_filters').on('click', function() {
        refreshTable(1);
    });

    $('#reset_filters').on('click', function() {
        // Clear all filters
        $('#bank_name').val('');
        $('#state').val('');
        $('#district').val('');
        $('#branch').val('');
        $('#ifsc_code_filter').val('');
        
        // Reset dropdown states
        resetFilters(['state', 'district', 'branch']);
        
        // Refresh table with no filters
        refreshTable(1);
    });

    // --- ROW ACTIONS (EDIT, SAVE, DELETE) --- //
    $(document).on('click', '.edit-btn', function() {
        const row = $(this).closest('tr');
        row.find('td[data-col]').each(function() {
            if ($(this).data('col') !== 'city_state') {
                $(this).html(`<input type="text" class="widefat" value="${$(this).data('original')}">`);
            }
        });
        $(this).parent().html(`<button class="button button-primary save-btn" data-id="${row.data('id')}">Save</button> <button class="button cancel-edit-btn">Cancel</button>`);
    });

    $(document).on('click', '.cancel-edit-btn', function() {
        refreshTable(currentPage);
    });

    $(document).on('click', '.save-btn', function() {
        const row = $(this).closest('tr');
        const payload = {
            action: 'edit_ifsc_row', nonce: nonce, id: $(this).data('id'),
            bank_name: row.find('td[data-col="bank_name"] input').val(),
            branch: row.find('td[data-col="branch"] input').val(),
            ifsc: row.find('td[data-col="ifsc"] input').val(),
            address: row.find('td[data-col="address"] input').val(),
        };
        $.post(ajaxurl, payload, function(res) {
            showToast(res.data.message, res.success ? 'success' : 'error');
            if (res.success) refreshTable(currentPage);
        });
    });

    // Delete functionality
    let deleteId = null;
    $(document).on('click', '.delete-btn', function() {
        deleteId = $(this).data('id');
        $('#delete-ifsc-code').text($(this).data('ifsc'));
        toggleModal('#delete-confirm-modal', true);
    });

    $('#confirm-delete-btn').on('click', function() {
        if (!deleteId) return;
        $.post(ajaxurl, { action: 'delete_ifsc_row', nonce: nonce, id: deleteId }, function(res) {
            toggleModal('#delete-confirm-modal', false);
            showToast(res.data.message, res.success ? 'success' : 'error');
            if (res.success) refreshTable(currentPage);
            deleteId = null;
        });
    });

    // --- FILE UPLOAD LOGIC --- //
    $('#csv_upload').on('change', function() {
        uploadFile = this.files[0];
        if (uploadFile) {
            $('#file_label_text').text(uploadFile.name);
            $('#submit_csv').prop('disabled', false);
        } else {
            $('#file_label_text').text('Select CSV/XLSX File');
            $('#submit_csv').prop('disabled', true);
        }
    });

    $('#submit_csv').on('click', function() {
        if (!uploadFile) return showToast('Please select a file.', 'error');
        const formData = new FormData();
        formData.append('action', 'upload_ifsc_file_check');
        formData.append('nonce', nonce);
        formData.append('csv_file', uploadFile);
        $(this).prop('disabled', true).text('Checking...');
        
        $.ajax({
            url: ajaxurl, type: 'POST', data: formData, contentType: false, processData: false,
            success: function(res) {
                if (res.success) {
                    uploadFileToken = res.data.file_token;
                    totalUploadRows = res.data.total_rows;
                    processFileChunk(0, 'skip'); // No duplicates, proceed with 'skip' (becomes insert)
                } else {
                    if (res.data.status === 'duplicates_found') {
                        $('#duplicate-count').text(res.data.duplicates.length);
                        $('#duplicate-details').text(res.data.duplicates.slice(0, 20).join(', '));
                        toggleModal('#duplicate-confirm-modal', true);
                    } else { 
                        showToast(res.data.message, 'error'); 
                    }
                }
            },
            error: function() {
                showToast('File check failed. The file may be too large or invalid.', 'error');
            },
            complete: function() {
                $('#submit_csv').prop('disabled', false).text('Upload Data');
            }
        });
    });

    $('#update-duplicates-btn').on('click', function() {
        handleDuplicateAction('update');
    });

    $('#skip-duplicates-btn').on('click', function() {
        handleDuplicateAction('skip');
    });

    function handleDuplicateAction(action) {
        toggleModal('#duplicate-confirm-modal', false);
        showToast(`Proceeding... Duplicates will be ${action}ed.`, 'success');
        processFileChunk(0, action);
    }
    
    function processFileChunk(offset, duplicateAction) {
        if (offset === 0) $('#upload-progress-container').fadeIn(200); // Show on first chunk

        $.post(ajaxurl, {
            action: 'process_ifsc_file_chunk',
            nonce: nonce,
            file_token: uploadFileToken,
            offset: offset,
            duplicate_action: duplicateAction
        }, function(res) {
            if (res.success) {
                const processed = res.data.processed_count;
                const total = res.data.total_rows;
                const percentage = total > 0 ? Math.round((processed / total) * 100) : 100;

                $('#upload-progress-bar').css('width', percentage + '%');
                $('#upload-progress-percentage').text(percentage + '%');
                $('#upload-progress-status').text(`Processed ${processed} of ${total} rows.`);

                if (res.data.status === 'processing' && processed < total) {
                    processFileChunk(processed, duplicateAction);
                } else {
                    $('#upload-progress-status').text('Import complete! Refreshing page...');
                    setTimeout(() => location.reload(), 2000);
                }
            } else {
                showToast(res.data.message, 'error');
                $('#upload-progress-container').fadeOut(400);
            }
        }).fail(function() {
            showToast('A server error occurred during processing.', 'error');
            $('#upload-progress-container').fadeOut(400);
        });
    }

    // --- DOWNLOAD CSV --- //
    $('#download_data').on('click', function () {
        $(this).text('Preparing...').prop('disabled', true);
        $.post(ajaxurl, { action: 'download_all_data', nonce: nonce }, function (res) {
            if (!res.success || !res.data.length) {
                showToast(res.data.message || 'No data to download.', 'error');
                return;
            }
            const headers = Object.keys(res.data[0]);
            let csvContent = headers.join(",") + "\n";
            res.data.forEach(function(obj) {
                let row = headers.map(function(header) {
                    return `"${String(obj[header] || '').replace(/"/g, '""')}"`;
                }).join(",");
                csvContent += row + "\n";
            });
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'ifsc_data_export.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }).always(function() {
            $(this).text('Download All Data as CSV').prop('disabled', false);
        }.bind(this));
    });

    // --- INITIALIZATION --- //
    populateDropdown('#bank_name', 'banks');
    resetFilters(['state', 'district', 'branch']);
    refreshTable();
});