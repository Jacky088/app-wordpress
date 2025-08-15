<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 后台管理页面渲染
 */
function apps_exhibition_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '无权限访问', 'apps-exhibition' ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'apps_exhibition';

    // 处理删除
    if (
        isset( $_GET['action'], $_GET['id'] )
        && $_GET['action'] === 'delete'
        && check_admin_referer( 'apps_exhibition_delete_' . intval( $_GET['id'] ) )
    ) {
        $id = intval( $_GET['id'] );
        $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

        $redirect_url = remove_query_arg( [ 'action', 'id', '_wpnonce', 'message' ] );
        $redirect_url = add_query_arg( 'message', 'deleted', $redirect_url );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    // 处理添加/编辑提交
    if ( isset( $_POST['apps_exhibition_submit'] ) ) {
        check_admin_referer( 'apps_exhibition_form' );

        $is_update = ! empty( $_POST['app_id'] ) && intval( $_POST['app_id'] ) > 0;
        $result    = apps_exhibition_handle_form();

        if ( $result === true ) {
            $redirect_url = remove_query_arg( [ 'action', 'id', '_wpnonce', 'message' ] );
            $message      = $is_update ? 'updated' : 'added';
            $redirect_url = add_query_arg( 'message', $message, $redirect_url );

            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    // 获取所有应用
    $apps = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );

    // 编辑数据初始化
    $edit_mode = false;
    $edit_app  = [
        'id'             => 0,
        'app_name'       => '',
        'app_description'=> '',
        'app_icon'       => '',
        'app_platforms'  => [],
        'app_downloads'  => [],
    ];

    if (
        isset( $_GET['action'], $_GET['id'] )
        && $_GET['action'] === 'edit'
        && check_admin_referer( 'apps_exhibition_edit_' . intval( $_GET['id'] ) )
    ) {
        $id = intval( $_GET['id'] );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", $id ), ARRAY_A );
        if ( $row ) {
            $edit_mode = true;
            $edit_app = $row;
            $edit_app['app_platforms'] = explode( ',', $row['app_platforms'] );
            $edit_app['app_downloads'] = maybe_unserialize( $row['app_downloads'] );
            if ( ! is_array( $edit_app['app_downloads'] ) ) {
                $edit_app['app_downloads'] = [];
            }
        }
    }

    // 页面消息提示
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

    include APPS_EXHIBITION_PATH . 'templates/admin-page.php';
}

/**
 * 处理表单提交逻辑，新增或修改
 * @return bool true on success, false on error
 */
function apps_exhibition_handle_form() {
    global $wpdb;
    $table = $wpdb->prefix . 'apps_exhibition';

    $id = isset( $_POST['app_id'] ) ? intval( $_POST['app_id'] ) : 0;
    $app_name = sanitize_text_field( $_POST['app_name'] ?? '' );
    $app_description = sanitize_textarea_field( $_POST['app_description'] ?? '' );
    $app_icon = esc_url_raw( $_POST['app_icon'] ?? '' );

    $platform_options = [ 'Android', 'AndroidTV', 'iOS', 'iPadOS', 'macOS', 'Windows' ];

    $app_platforms_raw = $_POST['app_platforms'] ?? [];
    if ( ! is_array( $app_platforms_raw ) ) {
        $app_platforms_raw = [];
    }
    $app_platforms_selected = array_intersect( $app_platforms_raw, $platform_options );
    $app_platforms_str = implode( ',', $app_platforms_selected );

    $download_urls = $_POST['download_url'] ?? [];
    $download_texts = $_POST['download_text'] ?? [];

    if ( ! is_array( $download_urls ) ) $download_urls = [];
    if ( ! is_array( $download_texts ) ) $download_texts = [];

    $downloads = [];

    for ( $i = 0; $i < count( $download_urls ); $i++ ) {
        $url = esc_url_raw( trim( $download_urls[ $i ] ?? '' ) );
        $text = sanitize_text_field( trim( $download_texts[ $i ] ?? '' ) );

        if ( empty( $url ) && empty( $text ) ) {
            continue;
        }

        if ( empty( $url ) ) {
            add_settings_error( 'apps_exhibition_messages', 'error', sprintf( __( '第%d个下载链接URL不能为空', 'apps-exhibition' ), $i + 1 ), 'error' );
            return false;
        }
        if ( empty( $text ) ) {
            add_settings_error( 'apps_exhibition_messages', 'error', sprintf( __( '第%d个下载按钮文字不能为空', 'apps-exhibition' ), $i + 1 ), 'error' );
            return false;
        }

        $downloads[] = [ 'url' => $url, 'text' => $text ];
    }

    if ( empty( $app_name ) || empty( $app_description ) || empty( $app_icon ) || empty( $app_platforms_selected ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请填写所有必填项。', 'apps-exhibition' ), 'error' );
        return false;
    }

    if ( empty( $downloads ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请至少填写一个下载链接。', 'apps-exhibition' ), 'error' );
        return false;
    }

    $data = [
        'app_name'       => $app_name,
        'app_description'=> $app_description,
        'app_icon'       => $app_icon,
        'app_platforms'  => $app_platforms_str,
        'app_downloads'  => maybe_serialize( $downloads ),
    ];

    $formats = [ '%s', '%s', '%s', '%s', '%s' ];

    if ( $id > 0 ) {
        $updated = $wpdb->update( $table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
        if ( $updated === false ) {
            add_settings_error( 'apps_exhibition_messages', 'error', __( '应用更新失败。', 'apps-exhibition' ), 'error' );
            return false;
        }
    } else {
        $inserted = $wpdb->insert( $table, $data, $formats );
        if ( ! $inserted ) {
            add_settings_error( 'apps_exhibition_messages', 'error', __( '应用添加失败。', 'apps-exhibition' ), 'error' );
            return false;
        }
    }

    return true;
}
