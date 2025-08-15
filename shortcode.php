<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'apps_exhibition', 'apps_exhibition_shortcode' );

function apps_exhibition_shortcode() {
    global $wpdb;
    $table = $wpdb->prefix . 'apps_exhibition';

    $filter_platform = isset( $_GET['platform'] ) ? sanitize_text_field( wp_unslash( $_GET['platform'] ) ) : '';

    $all_apps = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );

    if ( ! $all_apps ) {
        return '<p>' . esc_html__( '没有可展示的应用', 'apps-exhibition' ) . '</p>';
    }

    $platforms_all = [ 'Android', 'AndroidTV', 'iOS', 'iPadOS', 'macOS', 'Windows' ];

    $platforms_in_use = [];
    foreach ( $all_apps as $app ) {
        $ps = explode( ',', $app['app_platforms'] );
        foreach ( $ps as $p ) {
            if ( $p && ! in_array( $p, $platforms_in_use, true ) ) {
                $platforms_in_use[] = $p;
            }
        }
    }
    sort( $platforms_in_use );

    if ( $filter_platform && in_array( $filter_platform, $platforms_all, true ) ) {
        $apps = array_filter( $all_apps, function( $app ) use ( $filter_platform ) {
            $ps = explode( ',', $app['app_platforms'] );
            return in_array( $filter_platform, $ps, true );
        } );
    } else {
        $apps = $all_apps;
    }

    ob_start();
    ?>

    <div class="apps-exhibition-wrap">
        <div class="apps-exhibition-filter">
            <span><?php esc_html_e( '筛选分类:', 'apps-exhibition' ); ?></span>
            <a class="filter-btn<?php echo $filter_platform === '' ? ' active' : ''; ?>" href="<?php echo esc_url( remove_query_arg( 'platform' ) ); ?>"><?php esc_html_e( '全部', 'apps-exhibition' ); ?></a>
            <?php foreach ( $platforms_in_use as $platform ) : ?>
                <a class="filter-btn<?php echo ( $filter_platform === $platform ) ? ' active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'platform', $platform ) ); ?>"><?php echo esc_html( $platform ); ?></a>
            <?php endforeach; ?>
        </div>

        <div class="apps-exhibition-list">
            <?php if ( empty( $apps ) ) : ?>
                <p><?php esc_html_e( '没有找到符合条件的应用', 'apps-exhibition' ); ?></p>
            <?php else : ?>
                <?php foreach ( $apps as $app ) :
                    $downloads = maybe_unserialize( $app['app_downloads'] );
                    if ( ! is_array( $downloads ) ) $downloads = [];
                    $platforms = explode( ',', $app['app_platforms'] );
                ?>
                    <div class="apps-exhibition-item" title="<?php echo esc_attr( $app['app_name'] ); ?>">
                        <div class="app-icon" style="background-image:url('<?php echo esc_url( $app['app_icon'] ); ?>')"></div>
                        <div class="app-info">
                            <h3 class="app-name"><?php echo esc_html( $app['app_name'] ); ?></h3>
                            <div class="app-desc"><?php echo esc_html( $app['app_description'] ); ?></div>
                            <div class="app-platforms">
                                <?php foreach ( $platforms as $p ) : ?>
                                    <?php if ( ! $p ) continue; ?>
                                    <span class="platform-tag"><?php echo esc_html( $p ); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="app-downloads">
                                <?php foreach ( $downloads as $d ) : ?>
                                    <?php if ( empty( $d['url'] ) || empty( $d['text'] ) ) continue; ?>
                                    <a href="<?php echo esc_url( $d['url'] ); ?>" target="_blank" rel="noopener" class="download-btn"><?php echo esc_html( $d['text'] ); ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
