<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 后台管理页面渲染
 */
function apps_exhibition_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '无权限访问', 'apps-exhibition' ) );
    }

    global $wpdb, $apps_exhibition_plugin_instance; // Access the global plugin instance
    if ( ! $apps_exhibition_plugin_instance instanceof Apps_Exhibition ) {
        wp_die( __( '插件初始化错误。', 'apps-exhibition' ) ); // Safety check
    }

    $table = $wpdb->prefix . 'apps_exhibition';

    // Handle delete action
    if (
        isset( $_GET['action'], $_GET['id'] )
        && $_GET['action'] === 'delete'
        && check_admin_referer( 'apps_exhibition_delete_' . intval( $_GET['id'] ) )
    ) {
        $id = intval( $_GET['id'] );
        $deleted = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

        if ( $deleted === false ) {
            add_settings_error( 'apps_exhibition_messages', 'delete_error', __( '删除失败：', 'apps-exhibition' ) . $wpdb->last_error, 'error' );
        } else {
            $redirect_url = remove_query_arg( [ 'action', 'id', '_wpnonce', 'message' ] );
            $redirect_url = add_query_arg( 'message', 'deleted', $redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    // Handle add/edit form submission
    if ( isset( $_POST['apps_exhibition_submit'] ) ) {
        check_admin_referer( 'apps_exhibition_form' );

        $is_update = ! empty( $_POST['app_id'] ) && intval( $_POST['app_id'] ) > 0;
        $result    = apps_exhibition_handle_form(); // This function returns true/false

        if ( $result === true ) {
            $redirect_url = remove_query_arg( [ 'action', 'id', '_wpnonce', 'message' ] );
            $message      = $is_update ? 'updated' : 'added';
            $redirect_url = add_query_arg( 'message', $message, $redirect_url );

            wp_safe_redirect( $redirect_url );
            exit;
        }
        // If result is false, error message is already set by apps_exhibition_handle_form().
        // Fall through to display errors and the form again.
    }

    // Fetch all applications for display
    $apps = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

    // Initialize data for the form (either new app or editing existing)
    $edit_mode = false;
    $edit_app  = [
        'id'             => 0,
        'app_name'       => '',
        'app_description'=> '',
        'app_icon'       => '',
        'app_platforms'  => [], // Will hold array of selected platforms
        'app_downloads'  => [], // Will hold array of download {url, text}
    ];

    // Populate $edit_app if in edit mode
    if (
        isset( $_GET['action'], $_GET['id'] )
        && $_GET['action'] === 'edit'
        && check_admin_referer( 'apps_exhibition_edit_' . intval( $_GET['id'] ) )
    ) {
        $id = intval( $_GET['id'] );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A );
        if ( $row ) {
            $edit_mode = true;
            $edit_app = $row;
            // Unserialize and explode fields for display in form
            $edit_app['app_platforms'] = $row['app_platforms'] ? explode( ',', $row['app_platforms'] ) : [];
            $edit_app['app_downloads'] = maybe_unserialize( $row['app_downloads'] );
            if ( ! is_array( $edit_app['app_downloads'] ) ) {
                $edit_app['app_downloads'] = [];
            }
        } else {
            // App not found, redirect to main page with error
            add_settings_error( 'apps_exhibition_messages', 'not_found', __( '编辑的应用不存在。', 'apps-exhibition' ), 'error' );
            $redirect_url = remove_query_arg( [ 'action', 'id', '_wpnonce' ] );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    // Display messages after redirects or initial page load
    if ( isset( $_GET['message'] ) ) {
        switch ( sanitize_text_field( $_GET['message'] ) ) {
            case 'added':
                add_settings_error( 'apps_exhibition_messages', 'added', __( '添加应用成功！', 'apps-exhibition' ), 'updated' );
                break;
            case 'updated':
                add_settings_error( 'apps_exhibition_messages', 'updated', __( '更新应用成功！', 'apps-exhibition' ), 'updated' );
                break;
            case 'deleted':
                add_settings_error( 'apps_exhibition_messages', 'deleted', __( '删除成功！', 'apps-exhibition' ), 'updated' );
                break;
        }
    }

    // Include the admin-page template to render the form and list
    include APPS_EXHIBITION_PATH . 'templates/admin-page.php';
}

/**
 * Handles form submission logic for adding or updating an app.
 *
 * @return bool True on success, false on error.
 */
function apps_exhibition_handle_form() {
    global $wpdb, $apps_exhibition_plugin_instance; // Access the global plugin instance
    if ( ! $apps_exhibition_plugin_instance instanceof Apps_Exhibition ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '插件初始化错误，无法处理表单。', 'apps-exhibition' ), 'error' );
        return false;
    }

    $table = $wpdb->prefix . 'apps_exhibition';

    $id                = isset( $_POST['app_id'] ) ? intval( $_POST['app_id'] ) : 0;
    $app_name          = sanitize_text_field( $_POST['app_name'] ?? '' );
    $app_description   = sanitize_textarea_field( $_POST['app_description'] ?? '' );
    $app_icon          = esc_url_raw( $_POST['app_icon'] ?? '' );

    // Get valid platform options from plugin instance
    $platform_options  = $apps_exhibition_plugin_instance->platform_options;

    // Sanitize and validate app_platforms
    $app_platforms_raw = $_POST['app_platforms'] ?? [];
    $app_platforms_selected = [];
    if ( is_array( $app_platforms_raw ) ) {
        $app_platforms_selected = array_intersect( array_map( 'sanitize_text_field', $app_platforms_raw ), $platform_options );
    }
    $app_platforms_str = implode( ',', $app_platforms_selected );

    // Process download links
    $download_urls  = $_POST['download_url'] ?? [];
    $download_texts = $_POST['download_text'] ?? [];
    $downloads      = [];

    if ( ! is_array( $download_urls ) ) $download_urls = [];
    if ( ! is_array( $download_texts ) ) $download_texts = [];

    $has_valid_download = false;
    for ( $i = 0; $i < count( $download_urls ); $i++ ) {
        $url  = esc_url_raw( trim( $download_urls[ $i ] ?? '' ) );
        $text = sanitize_text_field( trim( $download_texts[ $i ] ?? '' ) );

        // Only add if both URL and text are provided
        if ( ! empty( $url ) && ! empty( $text ) ) {
            $downloads[] = [ 'url' => $url, 'text' => $text ];
            $has_valid_download = true;
        } elseif ( ! empty( $url ) || ! empty( $text ) ) {
            // One field is empty, but the other is not - invalid entry
            add_settings_error( 'apps_exhibition_messages', 'error', sprintf( __( '第%d个下载链接或按钮文字缺失，请填写完整或清除。', 'apps-exhibition' ), $i + 1 ), 'error' );
            return false;
        }
    }

    // Server-side validation for required fields
    if ( empty( $app_name ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用名称不能为空。', 'apps-exhibition' ), 'error' ); return false;
    }
    if ( empty( $app_description ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用描述不能为空。', 'apps-exhibition' ), 'error' ); return false;
    }
    if ( empty( $app_icon ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用图标不能为空。', 'apps-exhibition' ), 'error' ); return false;
    }
    if ( empty( $app_platforms_selected ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请至少选择一个平台分类。', 'apps-exhibition' ), 'error' ); return false;
    }
    if ( ! $has_valid_download && empty( $downloads ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请至少填写一个下载链接。', 'apps-exhibition' ), 'error' ); return false;
    }

    // Data array for database insertion/update
    $data = [
        'app_name'       => $app_name,
        'app_description'=> $app_description,
        'app_icon'       => $app_icon,
        'app_platforms'  => $app_platforms_str,
        'app_downloads'  => maybe_serialize( $downloads ),
    ];

    // Formats for wpdb->insert/update. Must match data types.
    // There are 5 fields in $data: app_name, app_description, app_icon, app_platforms, app_downloads
    $formats = [ '%s', '%s', '%s', '%s', '%s' ];

    if ( $id > 0 ) {
        // Update existing app
        $updated = $wpdb->update( $table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
        // $updated returns number of affected rows on success, false on error, 0 if no change
        if ( $updated === false && !empty($wpdb->last_error) ) {
            // Log the actual SQL error for debugging
            error_log( 'WordPress Apps Exhibition Update Error: ' . $wpdb->last_error . ' (SQL: ' . $wpdb->last_query . ')' );
            add_settings_error( 'apps_exhibition_messages', 'error', __( '应用更新失败。', 'apps-exhibition' ) . ' ' . $wpdb->last_error, 'error' );
            return false;
        }
    } else {
        // Insert new app
        $inserted = $wpdb->insert( $table, $data, $formats );
        if ( ! $inserted ) { // $inserted returns 0 on failure, 1 on success
            // Log the actual SQL error for debugging
            error_log( 'WordPress Apps Exhibition Insert Error: ' . $wpdb->last_error . ' (SQL: ' . $wpdb->last_query . ')' );
            add_settings_error( 'apps_exhibition_messages', 'error', __( '应用添加失败。', 'apps-exhibition' ) . ' ' . $wpdb->last_error, 'error' );
            return false;
        }
    }

    return true; // Operation successful
}

