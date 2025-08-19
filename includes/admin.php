<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 处理表单提交（新增 / 编辑应用）
 *
 * @return bool 返回操作成功与否
 */
function apps_exhibition_handle_form() {
    global $wpdb, $apps_exhibition_plugin_instance;
    if ( ! $apps_exhibition_plugin_instance instanceof Apps_Exhibition ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '插件初始化错误，无法处理表单。', 'apps-exhibition' ), 'error' );
        return false;
    }

    $table = $wpdb->prefix . 'apps_exhibition';

    $id                = isset( $_POST['app_id'] ) ? intval( $_POST['app_id'] ) : 0;
    $app_name          = sanitize_text_field( $_POST['app_name'] ?? '' );
    $app_description   = sanitize_textarea_field( $_POST['app_description'] ?? '' );
    $app_icon          = esc_url_raw( $_POST['app_icon'] ?? '' );

    $platform_options  = $apps_exhibition_plugin_instance->get_platform_categories();

    $app_platforms_raw = $_POST['app_platforms'] ?? [];
    $app_platforms_selected = [];
    if ( is_array( $app_platforms_raw ) ) {
        $app_platforms_selected = array_intersect( array_map( 'sanitize_text_field', $app_platforms_raw ), $platform_options );
    }
    $app_platforms_str = implode( ',', $app_platforms_selected );

    $app_filter_raw = $_POST['app_filter_category'] ?? [];
    $app_filter_selected = [];
    if ( is_array( $app_filter_raw ) ) {
        $app_filter_selected = array_filter( array_map( 'sanitize_text_field', $app_filter_raw ), function($val) { return $val !== ''; } );
    }
    $app_filter_str = implode( ',', $app_filter_selected );

    $download_urls  = $_POST['download_url'] ?? [];
    $download_texts = $_POST['download_text'] ?? [];
    $downloads      = [];

    if ( ! is_array( $download_urls ) ) $download_urls = [];
    if ( ! is_array( $download_texts ) ) $download_texts = [];

    $has_valid_download = false;
    for ( $i = 0; $i < count( $download_urls ); $i++ ) {
        $url  = sanitize_text_field( trim( $download_urls[ $i ] ?? '' ) );
        $text = sanitize_text_field( trim( $download_texts[ $i ] ?? '' ) );

        if ( ! empty( $url ) && ! empty( $text ) ) {
            $downloads[] = [ 'url' => $url, 'text' => $text ];
            $has_valid_download = true;
        } elseif ( ! empty( $url ) || ! empty( $text ) ) {
            add_settings_error( 'apps_exhibition_messages', 'error', sprintf( __( '第%d个下载链接或按钮文字缺失，请填写完整或清除。', 'apps-exhibition' ), $i + 1 ), 'error' );
            return false;
        }
    }

    if ( empty( $app_name ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用名称不能为空。', 'apps-exhibition' ), 'error' );
        return false;
    }
    if ( empty( $app_description ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用描述不能为空。', 'apps-exhibition' ), 'error' );
        return false;
    }
    if ( empty( $app_icon ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用图标不能为空。', 'apps-exhibition' ), 'error' );
        return false;
    }
    if ( empty( $app_platforms_selected ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请至少选择一个平台分类。', 'apps-exhibition' ), 'error' );
        return false;
    }
    if ( empty( $app_filter_selected ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请至少选择一个筛选分类。', 'apps-exhibition' ), 'error' );
        return false;
    }
    if ( ! $has_valid_download && empty( $downloads ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请至少填写一个下载链接。', 'apps-exhibition' ), 'error' );
        return false;
    }

    $data = [
        'app_name'            => $app_name,
        'app_description'     => $app_description,
        'app_icon'            => $app_icon,
        'app_platforms'       => $app_platforms_str,
        'app_filter_category' => $app_filter_str,
        'app_downloads'       => maybe_serialize( $downloads ),
    ];

    $formats = [ '%s', '%s', '%s', '%s', '%s', '%s' ];

    if ( $id > 0 ) {
        $updated = $wpdb->update( $table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
        if ( $updated === false ) {
            error_log( 'WordPress Apps Exhibition Update Error: ' . $wpdb->last_error . ' (SQL: ' . $wpdb->last_query . ')' );
            add_settings_error( 'apps_exhibition_messages', 'error', __( '更新应用失败。', 'apps-exhibition' ), 'error' );
            return false;
        } else {
            add_settings_error( 'apps_exhibition_messages', 'updated', __( '更新应用成功！', 'apps-exhibition' ), 'updated' );
            return true;
        }
    } else {
        $inserted = $wpdb->insert( $table, $data, $formats );
        if ( ! $inserted ) {
            error_log( 'WordPress Apps Exhibition Insert Error: ' . $wpdb->last_error . ' (SQL: ' . $wpdb->last_query . ')' );
            add_settings_error( 'apps_exhibition_messages', 'error', __( '新增应用失败。', 'apps-exhibition' ), 'error' );
            return false;
        } else {
            add_settings_error( 'apps_exhibition_messages', 'inserted', __( '新增应用成功！', 'apps-exhibition' ), 'updated' );
            return true;
        }
    }
}

/**
 * 处理添加/更新表单提交 — admin-post.php钩子回调
 */
function apps_exhibition_handle_form_post() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '无权限访问', 'apps-exhibition' ) );
    }

    check_admin_referer( 'apps_exhibition_form' );

    $result = apps_exhibition_handle_form();

    // 获取跳转地址，默认回管理页面
    $redirect_url = remove_query_arg( ['_wpnonce', 'message'], wp_get_referer() );
    if ( ! $redirect_url ) {
        $redirect_url = admin_url( 'admin.php?page=apps-exhibition' );
    }

    // 加入消息参数根据结果和是新增还是编辑
    if ( $result === true ) {
        $msg_code = ( isset( $_POST['app_id'] ) && intval( $_POST['app_id'] ) > 0 ) ? 'updated' : 'inserted';
    } else {
        $msg_code = 'error';
    }

    $redirect_url = add_query_arg( 'message', $msg_code, $redirect_url );

    wp_safe_redirect( $redirect_url );
    exit;
}

/**
 * 处理删除请求 — admin-post.php钩子回调
 */
function apps_exhibition_handle_delete() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '无权限访问', 'apps-exhibition' ) );
    }

    $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

    if ( ! $id || ! check_admin_referer( 'apps_exhibition_delete_' . $id ) ) {
        wp_die( __( '安全验证失败', 'apps-exhibition' ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'apps_exhibition';

    $deleted = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

    $redirect_url = remove_query_arg( [ 'action', 'id', '_wpnonce', 'message' ], wp_get_referer() );
    if ( ! $redirect_url ) {
        $redirect_url = admin_url( 'admin.php?page=apps-exhibition' );
    }

    $msg_code = ( $deleted === false ) ? 'delete_error' : 'deleted';

    $redirect_url = add_query_arg( 'message', $msg_code, $redirect_url );

    wp_safe_redirect( $redirect_url );
    exit;
}

/**
 * 后台管理页面渲染，仅负责显示，所有增删改跳转独立处理
 */
function apps_exhibition_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '无权限访问', 'apps-exhibition' ) );
    }

    global $wpdb, $apps_exhibition_plugin_instance;

    if ( ! $apps_exhibition_plugin_instance instanceof Apps_Exhibition ) {
        wp_die( __( '插件初始化错误。', 'apps-exhibition' ) );
    }

    $table = $wpdb->prefix . 'apps_exhibition';

    // 以下调用模板时会显示提示

    include APPS_EXHIBITION_PATH . 'templates/admin-page.php';
}
