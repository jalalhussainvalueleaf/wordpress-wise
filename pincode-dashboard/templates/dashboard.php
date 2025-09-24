<?php
// dashboard.php - Full version for Pincode Dashboard
?>
<div class="wrap pincode-dashboard-wrapper">
    <h1>Pincode Management Dashboard</h1>

    <div class="pincode-section pincode-filters">
        <div class="pincode-filter-item">
            <label for="statename">State:</label>
            <select id="statename" name="statename">
                <option value="">Select State</option>
            </select>
        </div>
        <div class="pincode-filter-item">
            <label for="district">District:</label>
            <select id="district" name="district" disabled>
                <option value="">Select District</option>
            </select>
        </div>
        <div class="pincode-filter-item">
            <label for="officename">Post Office:</label>
            <select id="officename" name="officename" disabled>
                <option value="">Select Office</option>
            </select>
        </div>
        <div class="pincode-filter-separator"> <strong>OR</strong></div>
        <div class="pincode-filter-item">
            <label for="pincode_filter">Pincode:</label>
            <input type="text" id="pincode_filter" placeholder="Enter Pincode">
        </div>
        <div class="pincode-filter-actions">
            <button id="apply_filters" class="button button-primary">Apply Filter</button>
            <button id="reset_filters" class="button">Reset</button>
        </div>
    </div>

    <div class="pincode-section pincode-actions pincode-actions-row">
        <div class="pincode-upload-wrapper">
            <label for="csv_upload" class="custom-file-upload button">
                <span id="file_label_text">Select CSV File</span>
            </label>
            <input type="file" id="csv_upload" accept=".csv" hidden>
            <button id="submit_csv" class="button button-primary" disabled>Upload Data</button>
        </div>

        <div class="pincode-download-wrapper">
            <button id="download_data" class="button button-secondary">Download All Data</button>
            <button id="delete_all_data" class="button button-danger">Delete All</button>
        </div>
    </div>

    <div id="upload-progress-container" style="display: none;">
        <p><strong>Uploading... <span id="upload-progress-percentage">0%</span></strong></p>
        <div class="progress-bar-bg">
            <div id="upload-progress-bar" class="progress-bar"></div>
        </div>
        <p id="upload-progress-status"></p>
    </div>

    <div class="pincode-table-wrapper">
        <table id="pincode_table" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Post Office</th>
                    <th>Pincode</th>
                    <th>District</th>
                    <th>State</th>
                    <th>Delivery</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody id="pincode-table-body">
                <tr><td colspan="6">Loading...</td></tr>
            </tbody>
        </table>
        <div id="pagination-controls" class="tablenav-pages"></div>
    </div>

    <div id="pincode-toast" class="pincode-toast"></div>

    <div id="delete-confirm-modal" class="pincode-modal" style="display:none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this entry?</p>
            <p><strong>Pincode:</strong> <span id="delete-pincode-value"></span></p>
            <button id="confirm-delete-btn" class="button button-danger">Yes, Delete</button>
            <button class="button modal-cancel">No, Cancel</button>
        </div>
    </div>

    <div id="bulk-delete-confirm-modal" class="pincode-modal" style="display:none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3>Delete All Data?</h3>
            <p>This will permanently remove all records from the database. Are you sure?</p>
            <button id="confirm-bulk-delete-btn" class="button button-danger">Yes, Delete All</button>
            <button class="button modal-cancel">Cancel</button>
        </div>
    </div>
</div>
