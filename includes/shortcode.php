<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'apps_exhibition', 'apps_exhibition_shortcode' );

function apps_exhibition_shortcode() {
    global $wpdb, $apps_exhibition_plugin_instance; // Access plugin instance for options

    // Safety check for plugin instance
    if ( ! $apps_exhibition_plugin_instance instanceof Apps_Exhibition ) {
        return '<p>' . esc_html__( '应用展示插件初始化错误。', 'apps-exhibition' ) . '</p>';
    }

    $table = $wpdb->prefix . 'apps_exhibition';

    // Get filter parameters from URL
    $filter_platform = isset( $_GET['platform'] ) ? sanitize_text_field( wp_unslash( $_GET['platform'] ) ) : '';

    $all_apps = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

    if ( ! $all_apps ) {
        return '<p>' . esc_html__( '没有可展示的应用', 'apps-exhibition' ) . '</p>';
    }

    // Get available platform options from the plugin instance
    $all_possible_platforms  = $apps_exhibition_plugin_instance->platform_options;

    // Filter apps based on selected platform
    $final_filtered_apps = $all_apps;
    if ( $filter_platform && in_array( $filter_platform, $all_possible_platforms, true ) ) {
        $final_filtered_apps = array_filter( $all_apps, function( $app ) use ( $filter_platform ) {
            $platforms_in_app = explode( ',', $app['app_platforms'] );
            return in_array( $filter_platform, $platforms_in_app, true );
        } );
    }

    // Collect all platforms actually in use by the filtered apps for the filter buttons
    $platforms_in_use = [];
    foreach ( $all_apps as $app ) { // Iterate all apps to get all platforms ever, not just filtered ones
        $ps = explode( ',', $app['app_platforms'] );
        foreach ( $ps as $p ) {
            if ( $p && ! in_array( $p, $platforms_in_use, true ) ) {
                $platforms_in_use[] = $p;
            }
        }
    }
    sort( $platforms_in_use );


    ob_start();
    ?>

    <div class="apps-exhibition-wrap">
        <div class="apps-exhibition-filter-group">
            <div class="apps-exhibition-filter">
                <span><?php esc_html_e( '筛选分类:', 'apps-exhibition' ); ?></span>
                <a class="filter-btn<?php echo $filter_platform === '' ? ' active' : ''; ?>" href="<?php echo esc_url( remove_query_arg( 'platform' ) ); ?>"><?php esc_html_e( '全部', 'apps-exhibition' ); ?></a>
                <?php foreach ( $platforms_in_use as $platform ) : ?>
                    <a class="filter-btn<?php echo ( $filter_platform === $platform ) ? ' active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'platform', $platform ) ); ?>"><?php echo esc_html( $platform ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="apps-exhibition-list">
            <?php if ( empty( $final_filtered_apps ) ) : ?>
                <p><?php esc_html_e( '没有找到符合条件的应用。', 'apps-exhibition' ) . '</p>'; ?>
            <?php else : ?>
                <?php foreach ( $final_filtered_apps as $app ) :
                    $downloads  = maybe_unserialize( $app['app_downloads'] );
                    $downloads  = is_array( $downloads ) ? $downloads : []; // Ensure it's an array
                    $platforms  = ! empty( $app['app_platforms'] ) ? explode( ',', $app['app_platforms'] ) : []; // Not displayed on card, but kept for reference
                ?>
                    <div class="apps-exhibition-item" title="<?php echo esc_attr( $app['app_name'] ); ?>">
                        <div class="app-icon" style="background-image:url('<?php echo esc_url( $app['app_icon'] ); ?>')"></div>
                        <div class="app-text-content">
                            <h3 class="app-name"><?php echo esc_html( $app['app_name'] ); ?></h3>
                            <div class="app-desc"><?php echo esc_html( $app['app_description'] ); ?></div>
                        </div>
                        <div class="app-hover-action">
                            <?php // Only display the first download button on hover (or always on mobile)
                            if ( ! empty( $downloads ) && ! empty( $downloads[0]['url'] ) ) : ?>
                                <a href="<?php echo esc_url( $downloads[0]['url'] ); ?>" target="_blank" rel="noopener" class="download-btn"><?php echo esc_html( $downloads[0]['text'] ); ?></a>
                            <?php else : ?>
                                <span class="download-btn download-btn-disabled"><?php esc_html_e( '暂无下载', 'apps-exhibition' ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
