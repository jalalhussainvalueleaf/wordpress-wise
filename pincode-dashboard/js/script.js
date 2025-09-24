// script.js – Enhanced Pincode Dashboard Logic

jQuery(document).ready(function ($) {
  const ajaxurl = pincode_ajax_obj.ajax_url;
  const nonce = pincode_ajax_obj.nonce;
  let currentPage = 1;
  let uploadFile = null;
  let fileToken = null;
  let totalUploadRows = 0;
  let deleteId = null;

  function buildPagination(current, total, totalItems) {
    const container = $('#pagination-controls').empty();
    if (total <= 1) return;

    container.append(`<span class="displaying-num">${totalItems} items</span>`);

    const nav = $('<span class="pagination-links"></span>');

    nav.append(`<a class="first-page button" ${current === 1 ? 'disabled' : ''} href="#" data-page="1">&laquo;</a>`);
    nav.append(`<a class="prev-page button" ${current === 1 ? 'disabled' : ''} href="#" data-page="${current - 1}">&lsaquo;</a>`);

    nav.append(`
      <span class="paging-input">
        <label for="current-page-selector" class="screen-reader-text">Current Page</label>
        <input class="current-page" id="current-page-selector" type="text" name="paged" value="${current}" size="2" aria-describedby="table-paging"> 
        of <span class="total-pages">${total}</span>
      </span>
    `);

    nav.append(`<a class="next-page button" ${current === total ? 'disabled' : ''} href="#" data-page="${current + 1}">&rsaquo;</a>`);
    nav.append(`<a class="last-page button" ${current === total ? 'disabled' : ''} href="#" data-page="${total}">&raquo;</a>`);

    container.append(nav);
  }

  $(document).on('click', '#pagination-controls a', function(e) {
    e.preventDefault();
    if ($(this).is('[disabled]')) return;
    const page = parseInt($(this).data('page'));
    if (!isNaN(page)) {
      refreshTable(page);
    }
  });

  $(document).on('keypress', '#pagination-controls .current-page', function(e) {
    if (e.which === 13) {
      const inputVal = parseInt($(this).val());
      if (!isNaN(inputVal)) {
        refreshTable(inputVal);
      }
    }
  });





// function bindPaginationEvents() {
//   $(document).on('click', '.prev-page, .next-page', function (e) {
//     e.preventDefault();
//     const selectedPage = parseInt($(this).data('page'));
//     if (!isNaN(selectedPage) && selectedPage !== currentPage) {
//       refreshTable(selectedPage);
//     }
//   });

//   $(document).on('keypress', '#current-page-selector', function (e) {
//     if (e.which === 13) {
//       e.preventDefault();
//       const page = parseInt($(this).val());
//       if (!isNaN(page)) {
//         refreshTable(page);
//       }
//     }
//   });
// }



  function loadStates() {
    $.post(ajaxurl, { action: 'get_pincode_states', nonce }, function (res) {
      const dropdown = $('#statename');
      dropdown.empty().append('<option value="">Select State</option>');
      if (res.success && Array.isArray(res.data)) {
        res.data.forEach(state => dropdown.append(`<option value="${state}">${state}</option>`));
      }
    });
  }

  function showToast(msg, type = 'success') {
    const toast = $('#pincode-toast');
    toast.text(msg).removeClass('success error').addClass(type).addClass('show');
    setTimeout(() => toast.removeClass('show'), 4000);
  }

  function toggleModal(modalId, show = true) {
    $(modalId).fadeToggle(200, show ? 'swing' : 'linear');
  }

  $('.modal-close, .modal-cancel').on('click', function () {
    $(this).closest('.pincode-modal').fadeOut(200);
  });

  function refreshTable(page = 1) { 
    currentPage = page;
    const pincode = $('#pincode_filter').val().trim();
    const statename = $('#statename').val();
    const district = $('#district').val();
    const officename = $('#officename').val();

    const payload = { action: 'get_pincode_data', nonce, page: currentPage };
    if (pincode) payload.pincode = pincode;
    else {
      if (statename) payload.statename = statename;
      if (district) payload.district = district;
      if (officename) payload.officename = officename;
    }

    $('#pincode-table-body').html('<tr><td colspan="6">Loading...</td></tr>');

    $.post(ajaxurl, payload, function (res) {
      const tbody = $('#pincode-table-body').empty();
      if (res.success && res.data.rows.length) {
        res.data.rows.forEach(r => {
          tbody.append(`
            <tr data-id="${r.id}">
              <td data-col="officename" data-original="${r.officename}">${r.officename}</td>
              <td data-col="pincode" data-original="${r.pincode}">${r.pincode}</td>
              <td data-col="district" data-original="${r.district}">${r.district}</td>
              <td data-col="statename" data-original="${r.statename}">${r.statename}</td>
              <td data-col="delivery" data-original="${r.delivery}">${r.delivery}</td>
              <td class="pincode-table-actions">
                <button class="button edit-btn">Edit</button>
                <button class="button delete-btn" data-id="${r.id}" data-pin="${r.pincode}">Delete</button>
              </td>
            </tr>`);
        });
        if (res.success && res.data.pagination) {
          buildPagination(
            res.data.pagination.current_page,
            res.data.pagination.total_pages,
            res.data.pagination.total_items
          );
        }


      } else {
        tbody.append('<tr><td colspan="6">No data found.</td></tr>');
      }
    });
  }

  $('#statename').on('change', function () {
    $('#district').prop('disabled', true).html('<option>Loading...</option>');
    $.post(ajaxurl, { action: 'get_pincode_districts', nonce, statename: $(this).val() }, function (res) {
      const dist = $('#district').empty();
      if (res.success) {
        dist.append('<option value="">Select District</option>');
        res.data.forEach(d => dist.append(`<option>${d}</option>`));
        dist.prop('disabled', false);
      }
    });
  });

  $('#district').on('change', function () {
    $('#officename').prop('disabled', true).html('<option>Loading...</option>');
    $.post(ajaxurl, {
      action: 'get_pincode_offices',
      nonce,
      statename: $('#statename').val(),
      district: $(this).val()
    }, function (res) {
      const off = $('#officename').empty();
      if (res.success) {
        off.append('<option value="">Select Office</option>');
        res.data.forEach(o => off.append(`<option>${o}</option>`));
        off.prop('disabled', false);
      }
    });
  });

  $('#apply_filters').on('click', function () {
    refreshTable();
  });

  $('#reset_filters').on('click', function () {
    $('#statename, #district, #officename').val('').prop('disabled', true);
    $('#pincode_filter').val('');
    $('#file_label_text').text('Select CSV File');
    $('#csv_upload').val('');
    $('#submit_csv').prop('disabled', true);
    refreshTable();
  });

  $('#pincode_filter').on('input', function () {
    if ($(this).val()) {
      $('#statename, #district, #officename').val('').prop('disabled', true);
    } else {
      $('#statename').prop('disabled', false);
    }
  });

  $(document).on('click', '.edit-btn', function () {
    const row = $(this).closest('tr');
    row.find('td[data-col]').each(function () {
      $(this).html(`<input type="text" class="widefat" value="${$(this).data('original')}">`);
    });
    $(this).parent().html(`<button class="button button-primary save-btn">Save</button> <button class="button cancel-edit-btn">Cancel</button>`);
  });

  $(document).on('click', '.cancel-edit-btn', function () {
    refreshTable();
  });

  $(document).on('click', '.save-btn', function () {
    const row = $(this).closest('tr');
    const id = row.data('id');
    const payload = {
      action: 'edit_pincode_row',
      nonce,
      id,
      circlename: '',
      regionname: '',
      divisionname: '',
      officename: row.find('td[data-col="officename"] input').val(),
      pincode: row.find('td[data-col="pincode"] input').val(),
      officetype: '',
      delivery: row.find('td[data-col="delivery"] input').val(),
      district: row.find('td[data-col="district"] input').val(),
      statename: row.find('td[data-col="statename"] input').val(),
      latitude: '',
      longitude: ''
    };

    $.post(ajaxurl, payload, function (res) {
      showToast(res.data.message, res.success ? 'success' : 'error');
      if (res.success) refreshTable(currentPage);
    });
  });

  $(document).on('click', '.delete-btn', function () {
    deleteId = $(this).data('id');
    $('#delete-pincode-value').text($(this).data('pin'));
    toggleModal('#delete-confirm-modal');
  });

  $('#confirm-delete-btn').on('click', function () {
    $.post(ajaxurl, { action: 'delete_pincode_row', nonce, id: deleteId }, function (res) {
      showToast(res.data.message, res.success ? 'success' : 'error');
      toggleModal('#delete-confirm-modal', false);
      refreshTable();
    });
  });

  $('#delete_all_data').on('click', function () {
    if (confirm('Are you sure you want to delete all records?')) {
      $.post(ajaxurl, { action: 'delete_all_pincode_data', nonce }, function (res) {
        showToast(res.data.message, res.success ? 'success' : 'error');
        refreshTable();
      });
    }
  });

  $('#csv_upload').on('change', function () {
    uploadFile = this.files[0];
    if (uploadFile) {
      $('#file_label_text').text(uploadFile.name);
      $('#submit_csv').prop('disabled', false);
    } else {
      $('#file_label_text').text('Select CSV File');
      $('#submit_csv').prop('disabled', true);
    }
  });

  $('#submit_csv').on('click', function () {
    if (!uploadFile) return showToast('Please select a file.', 'error');
    const formData = new FormData();
    formData.append('action', 'upload_pincode_file_check');
    formData.append('nonce', nonce);
    formData.append('csv_file', uploadFile);
    $(this).prop('disabled', true).text('Checking...');

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function (res) {
        if (res.success) {
          fileToken = res.data.file_token;
          totalUploadRows = res.data.total_rows;
          processFileChunk(0);
        } else {
          showToast(res.data.message || 'Upload failed', 'error');
        }
      },
      complete: function () {
        $('#submit_csv').prop('disabled', false).text('Upload Data');
      }
    });
  });

  function processFileChunk(offset) {
    if (offset === 0) $('#upload-progress-container').fadeIn(200);

    $.post(ajaxurl, {
      action: 'process_pincode_file_chunk',
      nonce,
      file_token: fileToken,
      offset: offset
    }, function (res) {
      if (res.success) {
        const processed = res.data.processed_count;
        const total = res.data.total_rows;
        const percentage = total > 0 ? Math.round((processed / total) * 100) : 100;

        $('#upload-progress-bar').css('width', percentage + '%');
        $('#upload-progress-percentage').text(percentage + '%');
        $('#upload-progress-status').text(`Processed ${processed} of ${total} rows.`);

        if (res.data.status === 'processing' && processed < total) {
          processFileChunk(processed);
        } else {
          $('#upload-progress-status').text('Import complete! Refreshing page...');
          setTimeout(() => location.reload(), 2000);
        }
      } else {
        showToast(res.data.message, 'error');
        $('#upload-progress-container').fadeOut(400);
        
      }
    });
  }

  $('#download_data').on('click', function () {
    $(this).text('Preparing...').prop('disabled', true);
    $.post(ajaxurl, { action: 'download_pincode_data', nonce }, function (res) {
      if (!res.success || !res.data.length) {
        showToast(res.data.message || 'No data to download.', 'error');
        return;
      }
      const headers = Object.keys(res.data[0]);
      let csvContent = headers.join(',') + "\n";
      res.data.forEach(function (obj) {
        let row = headers.map(header => `"${String(obj[header] || '').replace(/"/g, '""')}"`).join(',');
        csvContent += row + "\n";
      });
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'pincode_data_export.csv';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }).always(function () {
      $(this).text('Download All Data').prop('disabled', false);
    }.bind(this));
  });

  loadStates();
  refreshTable();
  bindPaginationEvents();

});
