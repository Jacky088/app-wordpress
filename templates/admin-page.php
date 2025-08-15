<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Retrieve the plugin instance to access its properties
global $apps_exhibition_plugin_instance;
// Safety check: Ensure the global instance is indeed an object of our plugin class
if ( ! $apps_exhibition_plugin_instance instanceof Apps_Exhibition ) {
    // This should ideally not happen with the corrected global instance setup.
    // But good for defensive programming.
    esc_html_e( '插件初始化错误。', 'apps-exhibition' );
    return;
}

$platform_options = $apps_exhibition_plugin_instance->platform_options;

// Existing settings_errors call for displaying messages
settings_errors( 'apps_exhibition_messages' );
?>
<div class="wrap">
    <h1><?php esc_html_e( '应用页面插件管理', 'apps-exhibition' ); ?></h1>

    <h2><?php echo isset($edit_mode) && $edit_mode
        ? esc_html__( '编辑应用', 'apps-exhibition' )
        : esc_html__( '添加新应用', 'apps-exhibition' ); ?></h2>

    <form method="post" id="apps-exhibition-form">
        <?php wp_nonce_field( 'apps_exhibition_form' ); ?>
        <input type="hidden" name="app_id" value="<?php echo esc_attr( $edit_app['id'] ?? 0 ); ?>" />

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th><label for="app_name"><?php esc_html_e( '应用名称', 'apps-exhibition' ); ?> <span style="color:red;">*</span></label></th>
                    <td><input name="app_name" type="text" id="app_name" value="<?php echo esc_attr( $edit_app['app_name'] ?? '' ); ?>" class="regular-text" required></td>
                </tr>

                <tr>
                    <th><label for="app_description"><?php esc_html_e( '应用描述', 'apps-exhibition' ); ?> <span style="color:red;">*</span></label></th>
                    <td><textarea name="app_description" id="app_description" rows="3" class="large-text" required><?php echo esc_textarea( $edit_app['app_description'] ?? '' ); ?></textarea></td>
                </tr>

                <tr>
                    <th><?php esc_html_e( '应用图标', 'apps-exhibition' ); ?> <span style="color:red;">*</span></th>
                    <td>
                        <input type="hidden" name="app_icon" id="app_icon" value="<?php echo esc_attr( $edit_app['app_icon'] ?? '' ); ?>" required>
                        <div id="app_icon_preview" style="width:100px; height:100px; background-size:contain; background-repeat:no-repeat; background-position:center center; border:1px solid #ddd; margin-bottom:10px; <?php if ( ! empty( $edit_app['app_icon'] ) ) echo 'background-image:url(\'' . esc_url( $edit_app['app_icon'] ) . '\')'; ?>"></div>
                        <button class="button" id="upload_icon_button"><?php esc_html_e( '上传图标', 'apps-exhibition' ); ?></button>
                        <button class="button" id="remove_icon_button"><?php esc_html_e( '移除图标', 'apps-exhibition' ); ?></button>
                        <p class="description"><?php esc_html_e( '请选择应用图标图片。', 'apps-exhibition' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e( '应用平台', 'apps-exhibition' ); ?> <span style="color:red;">*</span></th>
                    <td>
                        <?php foreach ( $platform_options as $option ) : ?>
                            <label style="margin-right: 15px;">
                                <input type="checkbox" name="app_platforms[]" value="<?php echo esc_attr( $option ); ?>" <?php checked( in_array( $option, $edit_app['app_platforms'] ?? [], true ) ); ?>>
                                <?php echo esc_html( $option ); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( '请选择至少一个平台分类。', 'apps-exhibition' ); ?></p>
                    </td>
                </tr>
                
                <!-- Removed the '二级筛选分类' row -->

                <tr>
                    <th><?php esc_html_e( '下载链接', 'apps-exhibition' ); ?> <span style="color:red;">*</span></th>
                    <td>
                        <div id="downloads_container">
                            <?php
                            // Ensure at least one empty download item for new apps if no existing downloads
                            $downloads = is_array( $edit_app['app_downloads'] ?? null ) ? $edit_app['app_downloads'] : [];
                            if ( empty($downloads) ) { // This condition covers both new apps and existing apps with no downloads
                                $downloads = [ [ 'url' => '', 'text' => __( '下载', 'apps-exhibition' ) ] ];
                            }
                            
                            foreach ( $downloads as $index => $dl ) :
                            ?>
                                <div class="download-item" style="margin-bottom:8px; display:flex; gap:8px; align-items:center;">
                                    <input type="url" name="download_url[]" placeholder="<?php esc_attr_e( '下载链接 URL', 'apps-exhibition' ); ?>" value="<?php echo esc_attr( $dl['url'] ?? '' ); ?>" style="width:60%;" required />
                                    <input type="text" name="download_text[]" placeholder="<?php esc_attr_e( '按钮文字', 'apps-exhibition' ); ?>" value="<?php echo esc_attr( $dl['text'] ?? '' ); ?>" style="width:30%;" required />
                                    <button type="button" class="button remove-download-button" style="width:8%;"><?php esc_html_e( '删', 'apps-exhibition' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button" id="add_download_button" style="margin-top:6px;"><?php esc_html_e( '添加下载链接', 'apps-exhibition' ); ?></button>
                        <p class="description"><?php esc_html_e( '请至少填写一个下载链接，支持多个下载按钮。', 'apps-exhibition' ); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button( isset($edit_mode) && $edit_mode ? __( '更新应用', 'apps-exhibition' ) : __( '添加应用', 'apps-exhibition' ), 'primary', 'apps_exhibition_submit' ); ?>

        <?php if ( isset($edit_mode) && $edit_mode ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=apps-exhibition' ) ); ?>" class="button"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></a>
        <?php endif; ?>
    </form>

    <?php if ( ! empty( $apps ) ) : ?>
        <h2><?php esc_html_e( '已添加应用', 'apps-exhibition' ); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( '图标', 'apps-exhibition' ); ?></th>
                    <th><?php esc_html_e( '名称', 'apps-exhibition' ); ?></th>
                    <th><?php esc_html_e( '描述', 'apps-exhibition' ); ?></th>
                    <th><?php esc_html_e( '平台', 'apps-exhibition' ); ?></th>
                    <th><?php esc_html_e( '操作', 'apps-exhibition' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $apps as $app ) : ?>
                    <tr>
                        <td><img src="<?php echo esc_url( $app['app_icon'] ); ?>" alt="" width="48" height="48"></td>
                        <td><?php echo esc_html( $app['app_name'] ); ?></td>
                        <td><?php echo esc_html( wp_trim_words( $app['app_description'], 20 ) ); ?></td>
                        <td><?php echo esc_html( $app['app_platforms'] ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=apps-exhibition&action=edit&id=' . $app['id'] ), 'apps_exhibition_edit_' . $app['id'] ) ); ?>"><?php esc_html_e( '编辑', 'apps-exhibition' ); ?></a> |
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=apps-exhibition&action=delete&id=' . $app['id'] ), 'apps_exhibition_delete_' . $app['id'] ) ); ?>" onclick="return confirm('<?php esc_attr_e( '确定删除吗？', 'apps-exhibition' ); ?>');" style="color:#a00;"><?php esc_html_e( '删除', 'apps-exhibition' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
jQuery(function($) {
    $('#upload_icon_button').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({
            title: '<?php echo esc_js( __( '选择应用图标', 'apps-exhibition' ) ); ?>',
            button: { text: '<?php echo esc_js( __( '选择图标', 'apps-exhibition' ) ); ?>' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#app_icon').val(attachment.url);
            $('#app_icon_preview').css('background-image', 'url(' + attachment.url + ')');
        });
        frame.open();
    });
    $('#remove_icon_button').on('click', function(e) {
        e.preventDefault();
        $('#app_icon').val('');
        $('#app_icon_preview').css('background-image', 'none');
    });
    $('#add_download_button').on('click', function() {
        var item = $('<div class="download-item" style="margin-bottom:8px; display:flex; gap:8px; align-items:center;">' +
            '<input type="url" name="download_url[]" placeholder="<?php echo esc_js( __( '下载链接 URL', 'apps-exhibition' ) ); ?>" required style="width:60%;" />' +
            '<input type="text" name="download_text[]" placeholder="<?php echo esc_js( __( '按钮文字', 'apps-exhibition' ) ); ?>" value="<?php echo esc_js(__( '下载', 'apps-exhibition' )); ?>" required style="width:30%;" />' + // Default text for new items
            '<button type="button" class="button remove-download-button" style="width:8%;"><?php echo esc_js( __( '删', 'apps-exhibition' ) ); ?></button>' +
            '</div>');
        $('#downloads_container').append(item);
    });
    $('#downloads_container').on('click', '.remove-download-button', function() {
        const downloadItems = $('#downloads_container .download-item');
        if (downloadItems.length > 1) { // Only remove if more than one item exists
             $(this).closest('.download-item').remove();
        } else {
            // If only one item, clear its values instead of removing
            $(this).closest('.download-item').find('input[type="url"]').val('');
            $(this).closest('.download-item').find('input[type="text"]').val('<?php echo esc_js(__( '下载', 'apps-exhibition' )); ?>'); // Reset default text
        }
    });

    // Client-side validation for form submission
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
            } else if (url || text) { // If one is filled but not the other
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
</script>
