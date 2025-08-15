jQuery(document).ready(function($) {
    let mediaUploader;

    $('#upload_icon_button').on('click', function(e) {
        e.preventDefault();

        // 如果已有media frame，直接打开
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // 创建新的media frame
        mediaUploader = wp.media({
            title: '选择应用图标',
            button: {
                text: '使用这个图标'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#app_icon').val(attachment.url);
            $('#app_icon_preview').css('background-image', 'url(' + attachment.url + ')');
        });

        mediaUploader.open();
    });

    $('#remove_icon_button').on('click', function(e) {
        e.preventDefault();
        $('#app_icon').val('');
        $('#app_icon_preview').css('background-image', 'none');
    });

    // 添加新的下载链接表单项
    $('#add_download_button').on('click', function() {
        const item = $('<div class="download-item" style="margin-bottom:8px; display:flex; gap:8px; align-items:center;">' +
            '<input type="url" name="download_url[]" placeholder="下载链接 URL" required style="width:60%;" />' +
            '<input type="text" name="download_text[]" placeholder="按钮文字" required style="width:30%;" />' +
            '<button type="button" class="button remove-download-button" style="width:8%;">删</button>' +
            '</div>');
        $('#downloads_container').append(item);
    });

    // 删除下载链接表单项
    $('#downloads_container').on('click', '.remove-download-button', function() {
        $(this).closest('.download-item').remove();

        // 如果全部删除了，自动添加一个空项
        if ($('#downloads_container .download-item').length === 0) {
            $('#add_download_button').click();
        }
    });

    // 提交表单前检查下载链接和文字必填
    $('#apps-exhibition-form').on('submit', function() {
        let valid = true;
        $('#downloads_container input[type="url"]').each(function() {
            if ($(this).val().trim() === '') {
                valid = false;
                return false; // 退出 each 循环
            }
        });
        if (!valid) {
            alert('请确保所有下载链接和按钮文字均已填写。');
            return false;
        }
        $('#downloads_container input[type="text"]').each(function() {
            if ($(this).val().trim() === '') {
                valid = false;
                return false;
            }
        });
        if (!valid) {
            alert('请确保所有下载链接和按钮文字均已填写。');
            return false;
        }
        return true;
    });
});

