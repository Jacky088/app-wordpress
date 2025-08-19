<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'apps_exhibition', 'apps_exhibition_shortcode' );

function apps_exhibition_shortcode() {
    global $wpdb, $apps_exhibition_plugin_instance;

    if ( ! $apps_exhibition_plugin_instance instanceof Apps_Exhibition ) {
        return '<p>' . esc_html__( '应用展示插件初始化错误。', 'apps-exhibition' ) . '</p>';
    }

    $table = $wpdb->prefix . 'apps_exhibition';

    $filter_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';

    $filter_categories = $apps_exhibition_plugin_instance->get_filter_categories();

    $all_apps = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

    if ( ! $all_apps ) {
        return '<p>' . esc_html__( '没有可展示的应用', 'apps-exhibition' ) . '</p>';
    }

    // 过滤符合筛选条件的应用
    $final_filtered_apps = $all_apps;
    if ( $filter_category && in_array( $filter_category, $filter_categories, true ) ) {
        $final_filtered_apps = array_filter( $all_apps, function( $app ) use ( $filter_category ) {
            $cats_in_app = explode( ',', $app['app_filter_category'] );
            return in_array( $filter_category, $cats_in_app, true );
        } );
    }

    // 获取所有实际使用过的筛选分类（用于按钮）
    $categories_in_use = [];
    foreach ( $all_apps as $app ) {
        $cs = explode( ',', $app['app_filter_category'] );
        foreach ( $cs as $c ) {
            $c = trim($c);
            if ( $c && ! in_array( $c, $categories_in_use, true ) ) {
                $categories_in_use[] = $c;
            }
        }
    }
    sort( $categories_in_use );

    // 获取首页海报配置，包含下载链接和按钮文字
    $home_posters = get_option( 'home_posters', [] );
    if ( ! is_array( $home_posters ) ) {
        $home_posters = [];
    }

    // 加载Swiper资源
    wp_enqueue_style( 'swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css', [], '10' );
    wp_enqueue_script( 'swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js', [], '10', true );

    ob_start();
    ?>

    <div class="apps-exhibition-wrap">

        <?php if ( ! empty( $home_posters ) ) : ?>
            <div class="home-posters-container swiper">
                <div class="swiper-wrapper">
                    <?php foreach ( $home_posters as $poster ) :
                        if ( ! isset( $poster['url'] ) ) continue;
                        $download_url = isset( $poster['download_url'] ) ? $poster['download_url'] : '';
                        $download_text = isset( $poster['download_text'] ) ? $poster['download_text'] : '';
                    ?>
                    <div class="swiper-slide" style="position:relative;">
                        <img src="<?php echo esc_url( $poster['url'] ); ?>" alt="" style="width:100%; border-radius:12px;"/>
                        <?php if ( $download_url && $download_text ) : ?>
                            <a href="<?php echo esc_url( $download_url ); ?>" target="_blank" rel="noopener" class="download-btn slide-download-btn-position"><?php echo esc_html( $download_text ); ?></a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                new Swiper('.home-posters-container', {
                    loop: true,
                    autoplay: { delay: 4000, disableOnInteraction: false },
                    pagination: { el: '.swiper-pagination', clickable: true },
                    navigation: false,
                });
            });
            </script>
        <?php endif; ?>

        <div class="apps-exhibition-filter-group">
            <div class="apps-exhibition-filter">
                <span><?php esc_html_e( '筛选分类:', 'apps-exhibition' ); ?></span>
                <a class="filter-btn<?php echo $filter_category === '' ? ' active' : ''; ?>" href="<?php echo esc_url( remove_query_arg( 'category' ) ); ?>"><?php esc_html_e( '全部', 'apps-exhibition' ); ?></a>

                <?php foreach ( $categories_in_use as $category ) : ?>
                    <a class="filter-btn<?php echo ( $filter_category === $category ) ? ' active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'category', $category ) ); ?>"><?php echo esc_html( $category ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="apps-exhibition-list">
            <?php if ( empty( $final_filtered_apps ) ) : ?>
                <p><?php esc_html_e( '没有找到符合条件的应用。', 'apps-exhibition' ); ?></p>
            <?php else : ?>
                <?php foreach ( $final_filtered_apps as $app ) :
                    $downloads  = maybe_unserialize( $app['app_downloads'] );
                    $downloads  = is_array( $downloads ) ? $downloads : [];
                    $platforms  = explode(',', $app['app_platforms']);
                    ?>
                    <div class="apps-exhibition-item" title="<?php echo esc_attr( $app['app_name'] ); ?>">
                        <div class="app-icon" style="background-image:url('<?php echo esc_url( $app['app_icon'] ); ?>')"></div>
                        <div class="app-text-content">
                            <h3 class="app-name"><?php echo esc_html( $app['app_name'] ); ?></h3>
                            <div class="app-desc"><?php echo esc_html( $app['app_description'] ); ?></div>
                            <div class="app-platform-tags">
                                <?php foreach ($platforms as $plat): if(trim($plat)): ?>
                                    <span class="platform-tag"><?php echo esc_html($plat); ?></span>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                        <div class="app-hover-action">
                            <?php if ( ! empty( $downloads ) ) : ?>
                                <?php foreach ( $downloads as $download ) : 
                                    if ( ! empty( $download['url'] ) && ! empty( $download['text'] ) ) : ?>
                                        <a href="<?php echo esc_url( $download['url'] ); ?>" target="_blank" rel="noopener" class="download-btn" style="margin-left:8px;"><?php echo esc_html( $download['text'] ); ?></a>
                                <?php endif; endforeach; ?>
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
