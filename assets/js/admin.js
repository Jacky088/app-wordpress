jQuery(document).ready(function($) {
    let mediaUploader;

    $('#upload_icon_button').on('click', function(e) {
        e.preventDefault();

        // If the media frame already exists, reopen it.
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Create a new media frame
        mediaUploader = wp.media({
            title: '<?php echo esc_js(__( '选择应用图标', 'apps-exhibition' )); ?>',
            button: {
                text: '<?php echo esc_js(__( '使用这个图标', 'apps-exhibition' )); ?>'
            },
            multiple: false
        });

        // When a file is selected, grab the URL and set it as the icon value/preview
        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#app_icon').val(attachment.url);
            $('#app_icon_preview').css('background-image', 'url(' + attachment.url + ')');
        });

        // Open the uploader dialog
        mediaUploader.open();
    });

    $('#remove_icon_button').on('click', function(e) {
        e.preventDefault();
        $('#app_icon').val('');
        $('#app_icon_preview').css('background-image', 'none');
    });

    // Add new download link form item
    $('#add_download_button').on('click', function() {
        // Use PHP's esc_js for default text and placeholders for proper internationalization and escaping
        const item = $('<div class="download-item" style="margin-bottom:8px; display:flex; gap:8px; align-items:center;">' +
            '<input type="url" name="download_url[]" placeholder="<?php echo esc_js(__( '下载链接 URL', 'apps-exhibition' )); ?>" required style="width:60%;" />' +
            '<input type="text" name="download_text[]" placeholder="<?php echo esc_js(__( '按钮文字', 'apps-exhibition' )); ?>" value="<?php echo esc_js(__( '下载', 'apps-exhibition' )); ?>" required style="width:30%;" />' +
            '<button type="button" class="button remove-download-button" style="width:8%;"><?php echo esc_js(__( '删', 'apps-exhibition' )); ?></button>' +
            '</div>');
        $('#downloads_container').append(item);
    });

    // Remove download link form item or clear if it's the last one
    $('#downloads_container').on('click', '.remove-download-button', function() {
        const downloadItems = $('#downloads_container .download-item');
        if (downloadItems.length > 1) {
            // If there's more than one download item, remove the current one
            $(this).closest('.download-item').remove();
        } else {
            // If it's the last item, instead of removing, just clear its values
            // and reset the default text for the '下载' button
            $(this).closest('.download-item').find('input[name="download_url[]"]').val('');
            $(this).closest('.download-item').find('input[name="download_text[]"]').val('<?php echo esc_js(__( '下载', 'apps-exhibition' )); ?>');
        }
    });

    // Client-side validation before form submission
    $('#apps-exhibition-form').on('submit', function() {
        let valid = true;

        // General required fields
        if (!$('#app_name').val().trim() || !$('#app_description').val().trim() || !$('#app_icon').val().trim()) {
            alert('<?php echo esc_js(__( '请填写所有必填项 (应用名称、描述、图标)。', 'apps-exhibition' )); ?>');
            return false;
        }

        // Platform checkboxes validation
        if ($('input[name="app_platforms[]"]:checked').length === 0) {
            alert('<?php echo esc_js(__( '请至少选择一个平台分类。', 'apps-exhibition' )); ?>');
            return false;
        }
        
        // Download links validation
        let hasValidDownloadEntry = false;
        $('#downloads_container .download-item').each(function() {
            const url = $(this).find('input[name="download_url[]"]').val().trim();
            const text = $(this).find('input[name="download_text[]"]').val().trim();

            if (url && text) {
                // Found at least one complete download entry
                hasValidDownloadEntry = true;
            } else if (url || text) { // If one field is present but the other is not
                alert('<?php echo esc_js(__( '请确保所有下载链接和按钮文字均已填写，或者留空以便删除。', 'apps-exhibition' )); ?>');
                valid = false;
                return false; // Exit the .each loop
            }
        });

        if (!hasValidDownloadEntry) {
            alert('<?php echo esc_js(__( '请至少填写一个完整下载链接（URL和按钮文字）。', 'apps-exhibition' )); ?>');
            return false;
        }

        return valid; // Allow form submission if all client-side checks pass
    });
});
