<?php //子テーマ用関数
if (!defined('ABSPATH')) exit;

//子テーマ用のビジュアルエディタースタイルを適用
add_editor_style();

//以下に子テーマ用の関数を書く
add_shortcode('fanza_new_list', function ($atts) {
    $atts  = shortcode_atts(['count' => 5], $atts);
    $count = (int) $atts['count'];

    // キャッシュからID一覧を取得。無ければ保険で再生成
    $ids = get_transient('fanza_new_list_ids');
    if ($ids === false) {
        $ids = fanza_new_list_rebuild_cache();
    }

    $total = count($ids);
    if ($total === 0) {
        return '<p>該当記事がありません。</p>';
    }

    // 分単位でオフセットを進めてローテーション表示
    $minute_index = (int) floor(current_time('timestamp') / 60);
    $offset = ($minute_index * $count) % $total;

    $page_ids = [];
    for ($i = 0; $i < $count; $i++) {
        $page_ids[] = $ids[($offset + $i) % $total];
    }

    $query = new WP_Query([
        'post_type'      => 'fanza_item',
        'post_status'    => 'publish',
        'post__in'       => $page_ids,
        'orderby'        => 'post__in', // 取得順を維持
        'posts_per_page' => $count,
    ]);

    if (!$query->have_posts()) {
        return '<p>該当記事がありません。</p>';
    }

    $out = '<div class="popular-entry-cards widget-entry-cards no-icon cf">';
    $i = 0;
    while ($query->have_posts()) {
        $query->the_post();
        $i++;
        $post_id = get_the_ID();

        $out .= '<a href="' . esc_url(get_permalink()) . '" class="popular-entry-card-link widget-entry-card-link a-wrap no-' . $i . '" title="' . esc_attr(get_the_title()) . '">';
        $out .= '<div class="post-' . $post_id . ' popular-entry-card widget-entry-card e-card cf fanza_item type-fanza_item status-publish has-post-thumbnail hentry">';

        $out .= '<figure class="popular-entry-card-thumb widget-entry-card-thumb card-thumb">';
        if (has_post_thumbnail()) {
            $out .= get_the_post_thumbnail($post_id, 'thumb120');
        }
        $out .= '</figure><!-- /.popular-entry-card-thumb -->';

        $out .= '<div class="popular-entry-card-content widget-entry-card-content card-content">';
        $out .= '<div class="popular-entry-card-title widget-entry-card-title card-title">' . esc_html(get_the_title()) . '</div>';

        $out .= '<div class="popular-entry-card-meta widget-entry-card-meta card-meta">';
        $out .= '<div class="popular-entry-card-info widget-entry-card-info card-info">';
        $out .= '<div class="popular-entry-card-date widget-entry-card-date display-none">';
        $out .= '<span class="popular-entry-card-post-date widget-entry-card-post-date post-date"><span class="fa fa-clock-o" aria-hidden="true"></span><span class="entry-date">' . esc_html(get_the_date('Y.m.d')) . '</span></span>';

        if (get_the_modified_date('Y-m-d') !== get_the_date('Y-m-d')) {
            $out .= '<span class="popular-entry-card-update-date widget-entry-card-update-date post-update"><span class="fa fa-history" aria-hidden="true"></span><span class="entry-date">' . esc_html(get_the_modified_date('Y.m.d')) . '</span></span>';
        }

        $out .= '</div></div></div>';
        $out .= '</div><!-- /.popular-entry-content -->';
        $out .= '</div><!-- /.popular-entry-card -->';
        $out .= '</a><!-- /.popular-entry-card-link -->';
    }
    wp_reset_postdata();

    return $out . '</div>';
});


// TOP・アーカイブの2ページ目以降を noindex,follow
add_action('wp_head', function () {
    if (is_paged()) {
        echo '<meta name="robots" content="noindex,follow">' . "\n";
    }
}, 1);

// フロントページのみ：H1を独自テキストに変更し、サイト名ロゴはdiv化＋直下に説明文を追加
add_filter('the_site_logo_tag', function ($all_tag, $is_header) {
    if (!$is_header || !is_front_page()) {
        return $all_tag;
    }

    $all_tag = preg_replace('/^<h1(\s|>)/', '<div$1', $all_tag, 1);
    $all_tag = preg_replace('/<\/h1>$/', '</div>', $all_tag, 1);

    return $all_tag;
}, 10, 2);

//女優作品一覧の場合のタイトルを変更
add_filter(
    'the_seo_framework_generated_archive_title_items',
    function ($items, $object) {

        if ($object instanceof WP_Term && $object->taxonomy === 'actress') {

            $items[0] = sprintf(
                '女優: %s の動画・新作AV一覧（プロフィール付き）',
                $object->name
            );

            // 接頭辞を消す
            $items[1] = '';

            // 接頭辞なしタイトル
            $items[2] = $items[0];
        }

        return $items;
    },
    10,
    2
);
