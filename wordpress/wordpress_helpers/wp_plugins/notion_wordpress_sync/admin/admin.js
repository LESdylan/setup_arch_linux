jQuery(document).ready(function($) {
    // System Check
    $('#run-system-check').on('click', function() {
        var $button = $(this);
        var $results = $('#system-check-results');
        
        $button.prop('disabled', true).text('Running...');
        
        $.ajax({
            url: notionWpSync.ajax_url,
            type: 'POST',
            data: {
                action: 'notion_wp_sync_run_system_check',
                nonce: notionWpSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Process each section
                    processCheckSection('wordpress', data.wordpress);
                    processCheckSection('plugin', data.plugin);
                    processCheckSection('database', data.database);
                    processCheckSection('api', data.api);
                    processCheckSection('php', data.php);
                    
                    $results.slideDown();
                } else {
                    alert('Error running system check: ' + response.data);
                }
                $button.prop('disabled', false).text('Run System Check');
            },
            error: function() {
                alert('Error running system check');
                $button.prop('disabled', false).text('Run System Check');
            }
        });
    });
    
    function processCheckSection(id, data) {
        var $section = $('#' + id + '-check .results-content');
        var html = '';
        
        // Add status badge
        var statusClass = '';
        var statusIcon = '';
        
        if (data.status === 'success') {
            statusClass = 'success';
            statusIcon = '✅';
        } else if (data.status === 'warning') {
            statusClass = 'warning';
            statusIcon = '⚠️';
        } else {
            statusClass = 'error';
            statusIcon = '❌';
        }
        
        html += '<div class="status-badge ' + statusClass + '">' + statusIcon + ' ' + data.message + '</div>';
        
        // Add details
        if (data.details) {
            html += '<div class="details">';
            html += '<h5>Details</h5>';
            html += '<ul>';
            
            for (var key in data.details) {
                if (data.details.hasOwnProperty(key)) {
                    var value = data.details[key];
                    
                    // Format boolean values
                    if (value === true) value = 'Yes';
                    if (value === false) value = 'No';
                    
                    // Format arrays
                    if (Array.isArray(value)) {
                        if (value.length === 0) {
                            value = 'None';
                        } else {
                            value = value.join(', ');
                        }
                    }
                    
                    // Format objects
                    if (typeof value === 'object' && value !== null) {
                        var objStr = '';
                        for (var objKey in value) {
                            if (value.hasOwnProperty(objKey)) {
                                var objVal = value[objKey];
                                if (objVal === true) objVal = 'Yes';
                                if (objVal === false) objVal = 'No';
                                objStr += objKey + ': ' + objVal + '<br>';
                            }
                        }
                        value = objStr;
                    }
                    
                    var keyLabel = key.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                    html += '<li><strong>' + keyLabel + ':</strong> ' + value + '</li>';
                }
            }
            
            html += '</ul>';
            html += '</div>';
        }
        
        $section.html(html);
    }

    // Test Connection button
    $('#notion-wp-test-connection').on('click', function() {
        var $button = $(this);
        var $result = $('#notion-connection-result');
        
        $button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: notionWpSync.ajax_url,
            type: 'POST',
            data: {
                action: 'notion_wp_test_connection',
                nonce: notionWpSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span class="connection-status success"><span class="dashicons dashicons-yes"></span> Successfully connected to Notion API</span>');
                } else {
                    $result.html('<span class="connection-status error"><span class="dashicons dashicons-no"></span> Failed to connect to Notion API: ' + response.data + '</span>');
                }
                $button.prop('disabled', false).text('Test Connection');
            },
            error: function() {
                $result.html('<span class="connection-status error"><span class="dashicons dashicons-no"></span> Error testing connection</span>');
                $button.prop('disabled', false).text('Test Connection');
            }
        });
    });

    // Manual sync button
    $('#notion-wp-sync-manual-sync-button').on('click', function() {
        var $button = $(this);
        var $status = $('#notion-wp-sync-sync-status');
        
        $button.prop('disabled', true).text('Syncing...');
        $status.html('<p>Syncing content from Notion...</p>');
        
        $.ajax({
            url: notionWpSync.ajax_url,
            type: 'POST',
            data: {
                action: 'notion_wp_sync_manual_sync',
                nonce: notionWpSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<p class="success">✅ ' + response.data + '</p>');
                } else {
                    $status.html('<p class="error">❌ ' + response.data + '</p>');
                }
                $button.prop('disabled', false).text('Sync Now');
                
                // Reload page after 2 seconds to refresh logs
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            },
            error: function() {
                $status.html('<p class="error">❌ An error occurred during sync</p>');
                $button.prop('disabled', false).text('Sync Now');
            }
        });
    });
    
    // Refresh Notion resources
    $('#refresh-notion-resources').on('click', function() {
        location.reload();
    });

    // Database mappings
    $('form').on('submit', function() {
        var databases = [];
        
        $('input[name="database_selected[]"]:checked').each(function() {
            var dbId = $(this).val();
            var postType = $('select[name="database_post_type[' + dbId + ']"]').val();
            
            databases.push({
                id: dbId,
                post_type: postType
            });
        });
        
        $('#notion_wp_sync_databases').val(JSON.stringify(databases));
    });
    
    // Individual pages
    var $pagesTable = $('#notion-pages-table');
    var $pagesBody = $('#notion-pages');
    var pageIndex = $pagesBody.find('tr:not(.page-template)').length;
    
    // Add new page row
    $('#add-page').on('click', function() {
        var $template = $pagesTable.find('.page-template').clone();
        $template.removeClass('page-template').show();
        
        // Replace placeholder index
        $template.html($template.html().replace(/__INDEX__/g, pageIndex));
        
        $pagesBody.append($template);
        pageIndex++;
    });
    
    // Remove page row
    $pagesTable.on('click', '.remove-page', function() {
        $(this).closest('tr').remove();
    });
});
