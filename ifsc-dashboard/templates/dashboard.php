<?php
/**
 * Main dashboard template file.
 * Contains the HTML structure for the filters, actions, and data table.
 */
?>
<div class="wrap ifsc-dashboard-wrapper">
    <h1>IFSC Code Management</h1>

    <div class="ifsc-section ifsc-filters">
        <div class="ifsc-filter-item">
            <label for="bank_name">Bank Name:</label>
            <select id="bank_name" name="bank_name"><option value="">Select Bank</option></select>
        </div>
        <div class="ifsc-filter-item">
            <label for="state">State:</label>
            <select id="state" name="state" disabled><option value="">Select State</option></select>
        </div>
        <div class="ifsc-filter-item">
            <label for="district">District:</label>
            <select id="district" name="district" disabled><option value="">Select District</option></select>
        </div>
        <div class="ifsc-filter-item">
            <label for="branch">Branch:</label>
            <select id="branch" name="branch" disabled><option value="">Select Branch</option></select>
        </div>
        <div class="ifsc-filter-separator">OR</div>
        <div class="ifsc-ifsc-filter">
             <div class="ifsc-filter-item">
                <label for="ifsc_code_filter">IFSC Code:</label>
                <input type="text" id="ifsc_code_filter" class="regular-text" name="ifsc_code_filter" placeholder="Enter IFSC Code">
            </div>
        </div>

        <div class="ifsc-filter-actions">
            <button id="apply_filters" class="button button-primary">Apply Filter</button>
            <button id="reset_filters" class="button">Reset</button>
        </div>
    </div>

    <div class="ifsc-section ifsc-actions">
        <div class="ifsc-upload-wrapper">
            <label for="csv_upload" class="custom-file-upload button">
                <span id="file_label_text">Select CSV File</span>
            </label>
            <input type="file" id="csv_upload" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" hidden>
            <button id="submit_csv" class="button button-primary" disabled>Upload Data</button>
        </div>
        <div class="ifsc-download-wrapper">
             <button id="download_data" class="button">Download All Data as CSV</button>
        </div>
    </div>

    <div id="upload-progress-container" style="display: none;">
        <p><strong>Uploading... <span id="upload-progress-percentage">0%</span></strong></p>
        <div class="progress-bar-bg">
            <div id="upload-progress-bar" class="progress-bar"></div>
        </div>
        <p id="upload-progress-status"></p>
    </div>

    <div class="ifsc-table-wrapper">
        <table id="ifsc_table" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">Bank Name</th>
                    <th scope="col">Branch</th>
                    <th scope="col">IFSC</th>
                    <th scope="col">MICR</th>
                    <th scope="col">Contact Number</th>
                    <th scope="col">Address</th>
                    <th scope="col">City / State</th>
                    <th scope="col" style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody id="ifsc-table-body">
                </tbody>
        </table>
        <div id="pagination-controls" class="tablenav-pages"></div>
    </div>

    <div id="ifsc-toast" class="ifsc-toast"></div>

    <div id="delete-confirm-modal" class="ifsc-modal" style="display:none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this entry?</p>
            <p><strong>IFSC:</strong> <span id="delete-ifsc-code"></span></p>
            <button id="confirm-delete-btn" class="button button-danger">Yes, Delete</button>
            <button class="button modal-cancel">No, Cancel</button>
        </div>
    </div>

    <div id="duplicate-confirm-modal" class="ifsc-modal" style="display:none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3>Duplicate Entries Found</h3>
            <p>Your file contains <strong id="duplicate-count"></strong> entries with IFSC codes that already exist in the database.</p>
            <p>How would you like to handle these duplicates?</p>
            <div id="duplicate-details"></div>
            <button id="update-duplicates-btn" class="button button-primary">Update Existing</button>
            <button id="skip-duplicates-btn" class="button">Skip Duplicates</button>
        </div>
    </div>
</div>