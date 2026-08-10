<?php

/**
 * Plugin Name: FANZA Sync
 * Description: FANZA(DMM)アフィリエイトAPIから商品情報を取得し、投稿として同期する
 */

if (! defined('ABSPATH')) {
    exit;
}

// ==============================
// 設定(本番運用時は wp-config.php に定数定義するか、環境変数推奨)
// ==============================


// ==============================
// カスタム投稿タイプ登録
// ==============================
add_action('init', 'fanza_sync_register_post_type');
function fanza_sync_register_post_type()
{
    $labels = array(
        'name'          => 'FANZA商品',
        'singular_name' => 'FANZA商品',
        'menu_name'     => 'FANZA商品',
        'all_items'     => 'すべてのFANZA商品',
        'add_new_item'  => '新規FANZA商品を追加',
        'edit_item'     => 'FANZA商品を編集',
    );

    register_post_type('fanza_item', [
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'exclude_from_search' => false,
        'show_ui'             => true,
        'show_in_rest'        => true,
        'has_archive'         => true,
        'rewrite'             => ['slug' => 'post'],
        'supports'            => ['title', 'editor', 'thumbnail'],
    ]);
}

// ==============================
// 女優タクソノミー登録
// ==============================
add_action('init', 'fanza_sync_register_actress_taxonomy');
function fanza_sync_register_actress_taxonomy()
{
    register_taxonomy('actress', 'fanza_item', [
        'label'             => '女優',
        'hierarchical'      => false,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'rewrite'           => ['slug' => 'actress'],
        'show_in_rest'      => true,
    ]);
}
// ==============================
// ジャンルタクソノミー登録
// ==============================
add_action('init', function () {
    register_taxonomy('fanza_genre', ['fanza_item'], [
        'labels' => [
            'name'          => 'ジャンル',
            'singular_name' => 'ジャンル',
            'search_items'  => 'ジャンルを検索',
            'all_items'     => 'すべてのジャンル',
            'edit_item'     => 'ジャンルを編集',
            'add_new_item'  => '新しいジャンルを追加',
            'menu_name'     => 'ジャンル',
        ],
        'hierarchical'      => false, // フラットな一覧でOKなら false
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => ['slug' => 'genre'],
    ]);
});

// ==============================
// Action Scheduler: 毎日1回のジョブ登録
// ==============================
add_action('init', 'fanza_sync_schedule_job');
function fanza_sync_schedule_job()
{
    if (! function_exists('as_schedule_recurring_action')) {
        return; // Action Schedulerプラグインが無効な場合の保険
    }
    if (false === as_next_scheduled_action('fanza_sync_daily_event')) {
        as_schedule_recurring_action(
            strtotime('today 00:00:00'),
            DAY_IN_SECONDS,
            'fanza_sync_daily_event'
        );
    }
}

add_action('fanza_sync_daily_event', 'fanza_sync_run');

// ==============================
// メイン同期処理
// ==============================
function fanza_sync_run($cid = null, $is_all = false, $gte_date = null)
{
    if (! defined('FANZA_SYNC_API_ID') || ! defined('FANZA_SYNC_AFFILIATE_ID')) {
        error_log('FANZA Sync: API ID or Affiliate ID is not defined.');
        return;
    }

    $offset  = 1;
    $hits    = FANZA_SYNC_HITS;
    $has_more = true;

    $stats = array(
        'fetched' => 0,
        'created' => 0,
        'updated' => 0,
        'deleted' => 0,
        'skipped' => 0,
        'error'   => 0,
    );

    //DBG
    // $hits = 1;

    while ($has_more) {
        $items = fanza_sync_fetch_items($offset, $hits, $cid, $is_all, $gte_date);

        if (empty($items)) {
            break;
        }

        $stats['fetched'] += count($items);

        foreach ($items as $item) {
            $result = fanza_sync_upsert_post($item);
            if (isset($stats[$result])) {
                $stats[$result]++;
            }
        }

        // 取得件数がhits未満なら最終ページ
        $has_more = (count($items) === $hits);
        $offset  += $hits;

        //DBG
        // $has_more = false;

        // API負荷を考慮して1秒待機
        sleep(1);
    }

    //カテゴリを更新
    if ($cid === null) {
        fanza_update_release_categories();
    }

    // 同期完了後にキャッシュを再構築
    fanza_new_list_rebuild_cache();

    return $stats;
}

// ==============================
// FANZA API呼び出し
// ==============================
function fanza_sync_fetch_items($offset = 1, $hits = 100, $cid = null, $is_all = false, $gte_date = null)
{

    $endpoint = 'https://api.dmm.com/affiliate/v3/ItemList';

    $tz = wp_timezone();

    $is_first_run = ! get_option('fanza_sync_initial_done');

    if ($is_all) {
        $date = new DateTime('2025-07-10', $tz);
    } else {
        if ($gte_date) {
            $date = new DateTime($gte_date, $tz);
        } else {
            $date = new DateTime('yesterday', $tz);
        }
        // $date = new DateTime('yesterday', $tz);
    }
    $date->setTime(0, 0, 0);
    $gte_date = $date->format('Y-m-d\TH:i:s');

    $params = array(
        'api_id'       => FANZA_SYNC_API_ID,
        'affiliate_id' => FANZA_SYNC_AFFILIATE_ID,
        'site'         => FANZA_SYNC_SITE,
        'service'      => FANZA_SYNC_SERVICE,
        'hits'         => $hits,
        'offset'       => $offset,
        'sort'         => 'date',
        'output'       => 'json',
        'gte_date'     => $gte_date,
        // 'lte_date'     => $lte_date,
    );

    if ($cid) {
        $params = array(
            'api_id'       => FANZA_SYNC_API_ID,
            'affiliate_id' => FANZA_SYNC_AFFILIATE_ID,
            'site'         => FANZA_SYNC_SITE,
            'service'      => FANZA_SYNC_SERVICE,
            'output'       => 'json',
            'cid'     => $cid,
            // 'lte_date'     => $lte_date,
        );
    }

    $url = add_query_arg($params, $endpoint);

    $response = wp_remote_get($url, array('timeout' => 30));

    if (is_wp_error($response)) {
        error_log('FANZA Sync API Error: ' . $response->get_error_message());
        return array();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data['result']['items'])) {
        return array();
    }

    if ($is_first_run) {
        update_option('fanza_sync_initial_done', true);
    }

    return $data['result']['items'];
}

// ==============================
// フィールド保存ヘルパー
// ACFの管理画面(サンプル画像URL一覧など)で作成済みのフィールドがあれば
// update_field() で保存し、ACFが無い/未登録の場合は通常のpost metaとして保存する
// ==============================
function fanza_sync_save_field($selector, $value, $post_id)
{
    if (function_exists('update_field')) {
        update_field($selector, $value, $post_id);
    } else {
        update_post_meta($post_id, $selector, $value);
    }
}

// ==============================
// 投稿の新規作成 / 更新
// ==============================
function fanza_sync_upsert_post($item)
{
    $cid = isset($item['content_id']) ? $item['content_id'] : null;

    if (empty($cid)) {
        return 'error';
    }

    // 既存投稿の重複チェック
    $existing = get_posts(array(
        'post_type'      => 'fanza_item',
        'meta_key'       => '_fanza_cid',
        'meta_value'     => $cid,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ));

    $is_new = empty($existing);

    $post_content = '';
    $post_content .= isset($item['product_id']) ? '品番: ' . $item['product_id'] . "\n" : '';
    $post_content .= isset($item['iteminfo']['maker'][0]['name']) ? 'メーカー: ' . $item['iteminfo']['maker'][0]['name'] . "\n" : '';
    $post_content .= isset($item['volume']) ? '収録時間: ' . $item['volume'] . "分\n" : '';
    $post_content .= isset($item['iteminfo']['actress']) && !empty($item['iteminfo']['actress'])
        ? '出演者: ' . implode(', ', array_column($item['iteminfo']['actress'], 'name')) . "\n"
        : '';

    $post_data = array(
        'post_type'   => 'fanza_item',
        'post_title'  => isset($item['title']) ? $item['title'] : '',
        'post_status' => 'publish',
    );

    //dbg
    $is_new = true;

    // 新規作成時のみ本文をセット
    if ($is_new) {
        $post_data['post_content'] = $post_content;
    }

    if (! empty($existing)) {
        $post_data['ID'] = $existing[0];
        $post_id = wp_update_post($post_data);
    } else {
        $post_id = wp_insert_post($post_data);
    }

    if (is_wp_error($post_id) || ! $post_id) {
        error_log('FANZA Sync: post save failed for cid=' . $cid);
        return 'error';
    }

    // 女優タームの紐付け(写真はActressSearch APIから別途取得)
    fanza_sync_process_actresses($post_id, $item);

    // メタデータ保存
    update_post_meta($post_id, '_fanza_cid', $cid);
    update_post_meta($post_id, '_fanza_price', isset($item['prices']['price']) ? $item['prices']['price'] : '');
    update_post_meta($post_id, '_fanza_url', isset($item['affiliateURL']) ? $item['affiliateURL'] : '');
    update_post_meta($post_id, '_fanza_sample_image', isset($item['sampleImageURL']['sample_l']['image'][0]) ? $item['sampleImageURL']['sample_l']['image'][0] : '');

    // ACFフィールド保存(JSON項目名をそのままフィールド名に対応させる)
    fanza_sync_save_field('fanza_product_id', isset($item['product_id']) ? $item['product_id'] : '', $post_id);
    fanza_sync_save_field('fanza_affiliateurl', isset($item['affiliateURL']) ? $item['affiliateURL'] : '', $post_id);
    fanza_sync_save_field('fanza_samplemovieurl', isset($item['sampleMovieURL']['size_720_480']) ? $item['sampleMovieURL']['size_720_480'] : '', $post_id);
    fanza_sync_save_field('fanza_date', isset($item['date']) ? $item['date'] : '', $post_id);
    fanza_sync_save_field('fanza_volume', isset($item['volume']) ? $item['volume'] : '', $post_id);


    $sample_images_s = array();
    $sample_images_l = array();
    if (! empty($item['sampleImageURL']['sample_s']['image']) && is_array($item['sampleImageURL']['sample_s']['image'])) {
        $sample_images_s = $item['sampleImageURL']['sample_s']['image'];
    }
    if (! empty($item['sampleImageURL']['sample_l']['image']) && is_array($item['sampleImageURL']['sample_l']['image'])) {
        $sample_images_l = $item['sampleImageURL']['sample_l']['image'];
    }
    fanza_sync_save_field('fanza_sampleimageurl', implode("\n", $sample_images_s), $post_id);
    fanza_sync_save_field('fanza_sampleimageurl_l', implode("\n", $sample_images_l), $post_id);

    $genres = array();
    if (! empty($item['iteminfo']['genre'])) {
        foreach ($item['iteminfo']['genre'] as $genre) {
            $genres[] = $genre['name'];
        }
    }
    wp_set_object_terms($post_id, $genres, 'fanza_genre', false);

    $actresses = array();
    if (! empty($item['iteminfo']['actress'])) {
        foreach ($item['iteminfo']['actress'] as $actress) {
            $actresses[] = $actress['name'];
        }
    }
    update_post_meta($post_id, '_fanza_actress', implode(',', $actresses));

    // パッケージ画像をアイキャッチとして設定(新規時のみ)
    // large が無ければ small にフォールバックする
    $featured_image_url = ! empty($item['imageURL']['large'])
        ? $item['imageURL']['large']
        : (! empty($item['imageURL']['small']) ? $item['imageURL']['small'] : '');

    if (! has_post_thumbnail($post_id) && ! empty($featured_image_url)) {
        fanza_sync_set_featured_image($post_id, $featured_image_url);
    }

    //Meta Descriptionを自動生成して保存
    $meta_description = '';
    $meta_description .= isset($item['title']) ? $item['title'] : '';

    if (! empty($actresses)) {
        $meta_description .= ' | ' . implode(',', $actresses);
    }

    $meta_description .= 'のAV作品情報。';

    $parts = array();
    if (! empty($item['date'])) {
        $parts[] = '発売日 ' . $item['date'];
    }
    if (! empty($item['iteminfo']['maker'][0]['name'])) {
        $parts[] = 'メーカー: ' . $item['iteminfo']['maker'][0]['name'];
    }
    $meta_description .= implode('、', $parts);

    $meta_description .= '。サンプル動画・画像あり';

    // 120文字でカット
    $meta_description = mb_substr($meta_description, 0, 120);

    update_post_meta($post_id, 'the_page_meta_description', $meta_description);

    return $is_new ? 'created' : 'updated';
}

// ==============================
// 女優情報取得(DMM ActressSearch API)
// 商品情報APIにはid・nameしか含まれないため、写真(thumbnail)・フリガナは別途取得する
// ==============================
function fanza_fetch_actress_info(string $actressId): ?array
{
    $params = [
        'api_id'       => FANZA_SYNC_API_ID,
        'affiliate_id' => FANZA_SYNC_AFFILIATE_ID,
        'actress_id'   => $actressId,
        'output'       => 'json',
    ];

    $url = 'https://api.dmm.com/affiliate/v3/ActressSearch?' . http_build_query($params);
    $response = wp_remote_get($url, ['timeout' => 10]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($data['result']['actress'][0])) {
        return null;
    }

    $actress = $data['result']['actress'][0];

    return [
        'name'       => $actress['name'] ?? '',
        'ruby'       => $actress['ruby'] ?? '',
        // 写真は外部URLのまま保存する(メディアライブラリへの取り込みはしない)
        'thumbnail'  => $actress['imageURL']['large'] ?? $actress['imageURL']['small'] ?? '',
        'bust'       => $actress['bust'] ?? '',
        'cup'        => $actress['cup'] ?? '',
        'waist'      => $actress['waist'] ?? '',
        'hip'        => $actress['hip'] ?? '',
        'height'     => $actress['height'] ?? '',
        'birthday'   => $actress['birthday'] ?? '',
        'blood_type' => $actress['blood_type'] ?? '',
        'hobby'      => $actress['hobby'] ?? '',
    ];
}

// ==============================
// 女優タームの紐付け(fanza_sync_upsert_postから呼び出す)
// 新規女優(タームが未登録)の場合のみActressSearch APIを叩く
// ==============================
function fanza_sync_process_actresses(int $post_id, array $item): void
{
    if (empty($item['iteminfo']['actress'])) {
        // 女優情報が無い場合はタームを空にしておく
        wp_set_object_terms($post_id, [], 'actress');
        return;
    }

    $termIds = [];

    foreach ($item['iteminfo']['actress'] as $actressData) {
        $actressId = $actressData['id'] ?? null;
        if (! $actressId) {
            continue;
        }

        $slug = 'actress-' . $actressId; // DMM IDをスラッグ化して一意性を担保
        $term = get_term_by('slug', $slug, 'actress');

        if (! $term) {
            $info = fanza_fetch_actress_info($actressId);
            if ($info === null) {
                continue; // API取得失敗時はスキップ(商品同期自体は継続)
            }

            $result = wp_insert_term($info['name'], 'actress', ['slug' => $slug]);
            if (is_wp_error($result)) {
                continue;
            }
            $termId = $result['term_id'];

            update_field('actress_ruby', $info['ruby'], 'actress_' . $termId);
            update_field('actress_dmm_id', $actressId, 'actress_' . $termId);
            update_field('actress_thumbnail', $info['thumbnail'], 'actress_' . $termId);
            update_field('actress_bust', $info['bust'], 'actress_' . $termId);
            update_field('actress_cup', $info['cup'], 'actress_' . $termId);
            update_field('actress_waist', $info['waist'], 'actress_' . $termId);
            update_field('actress_hip', $info['hip'], 'actress_' . $termId);
            update_field('actress_height', $info['height'], 'actress_' . $termId);
            update_field('actress_birthday', $info['birthday'], 'actress_' . $termId);
            update_field('actress_blood_type', $info['blood_type'], 'actress_' . $termId);
            update_field('actress_hobby', $info['hobby'], 'actress_' . $termId);
        } else {
            $termId = $term->term_id;
        }

        $termIds[] = (int) $termId;
    }

    wp_set_object_terms($post_id, $termIds, 'actress');
    fanza_actress_title_update($termIds);
}

function fanza_actress_title_update($term_ids)
{

    foreach ($term_ids as $term_id) {

        $term = get_term($term_id, 'actress');
        if (! $term) {
            return;
        }

        $actress_name = $term->name;

        // ==============================
        // The SEO Framework タイトル設定
        // ==============================
        $tsf_settings = get_term_meta($term_id, 'autodescription-term-settings', true);

        if (!is_array($tsf_settings)) {
            $tsf_settings = [];
        }

        $tsf_settings = array_merge([
            'doctitle'           => '',
            'title_no_blog_name' => 1,
            'description'        => '',
            'og_title'           => '',
            'og_description'     => '',
            'tw_title'           => '',
            'tw_description'     => '',
            'tw_card_type'       => '',
            'social_image_url'   => '',
            'social_image_id'    => 0,
            'canonical'          => '',
            'noindex'            => 0,
            'nofollow'           => 0,
            'noarchive'          => 0,
            'redirect'           => '',
        ], $tsf_settings);

        $tsf_settings['doctitle'] = sprintf(
            '女優: %s の動画・新作AV一覧（プロフィール付き）｜えろたつJP',
            $actress_name
        );

        $tsf_settings['title_no_blog_name'] = 1;

        update_term_meta(
            $term_id,
            'autodescription-term-settings',
            $tsf_settings
        );
    }
}

// ==============================
// アイキャッチ画像の取り込み
// ==============================
function fanza_sync_set_featured_image($post_id, $image_url)
{
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_sideload_image($image_url, $post_id, null, 'id');

    if (! is_wp_error($attachment_id)) {
        set_post_thumbnail($post_id, $attachment_id);
    } else {
        error_log('FANZA Sync: image sideload failed - ' . $attachment_id->get_error_message());
    }
}

// ==============================
// トップページのクエリにfanza_itemを追加
// ==============================
add_action('pre_get_posts', 'fanza_sync_add_to_main_query');
function fanza_sync_add_to_main_query($query)
{
    if (is_admin() || ! $query->is_main_query()) {
        return;
    }
    if ($query->is_home() || $query->is_feed()) {
        $query->set('post_type', array('post', 'fanza_item'));
    }
    if ($query->is_category()) {
        $query->set('post_type', ['post', 'fanza_item']);
    }
}

// ==============================
// 最近の投稿ウィジェットにfanza_itemを追加
// ==============================
add_filter('widget_posts_args', 'fanza_sync_widget_posts_args');
function fanza_sync_widget_posts_args($args)
{
    $args['post_type'] = array('post', 'fanza_item');
    return $args;
}

// ==============================
// 管理画面に手動実行ボタンを追加
// ==============================
add_action('admin_menu', function () {
    add_management_page(
        'FANZA Sync 手動実行',
        'FANZA Sync 手動実行',
        'manage_options',
        'fanza-sync-manual',
        'fanza_sync_manual_page'
    );
});

function fanza_sync_manual_page()
{
    if (isset($_POST['fanza_sync_run_now']) && check_admin_referer('fanza_sync_manual_action')) {
        $stats = fanza_sync_run();
        echo '<div class="notice notice-success"><p>同期を実行しました。</p>'
            . '<ul style="list-style:disc;margin-left:20px;">'
            . '<li>取得件数: ' . intval($stats['fetched']) . '</li>'
            . '<li>新規作成: ' . intval($stats['created']) . '</li>'
            . '<li>更新: ' . intval($stats['updated']) . '</li>'
            . '<li>削除(動画URL消失): ' . intval($stats['deleted']) . '</li>'
            . '<li>スキップ(動画URLなし・新規): ' . intval($stats['skipped']) . '</li>'
            . '<li>エラー: ' . intval($stats['error']) . '</li>'
            . '</ul></div>';
    }

    if (isset($_POST['fanza_category_rebuild_run']) && check_admin_referer('fanza_category_rebuild_action')) {
        $cat_stats = fanza_update_release_categories(true);
        echo '<div class="notice notice-success"><p>カテゴリを全件再生成しました。</p>'
            . '<ul style="list-style:disc;margin-left:20px;">'
            . '<li>fanza_dateあり: ' . intval($cat_stats['with_date']) . '</li>'
            . '<li>発売前: ' . intval($cat_stats['nonrelease']) . '</li>'
            . '<li>新着: ' . intval($cat_stats['new']) . '</li>'
            . '<li>該当なし(8日以上前): ' . intval($cat_stats['none']) . '</li>'
            . '<li>fanza_dateなし(カテゴリ除去): ' . intval($cat_stats['no_date']) . '</li>'
            . '</ul></div>';
    }
?>
    <div class="wrap">
        <h1>FANZA Sync 手動実行</h1>
        <form method="post">
            <?php wp_nonce_field('fanza_sync_manual_action'); ?>
            <input type="submit" name="fanza_sync_run_now" class="button button-primary" value="今すぐ同期を実行">
        </form>

        <hr>

        <h2>カテゴリ再生成(発売前 / 新着)</h2>
        <p>更新日を問わず全件のfanza_dateを見て「発売前」「新着」カテゴリを再判定します。</p>
        <form method="post">
            <?php wp_nonce_field('fanza_category_rebuild_action'); ?>
            <input type="submit" name="fanza_category_rebuild_run" class="button button-secondary" value="全件カテゴリ再生成">
        </form>
    </div>
<?php
}

// ==============================
// フロント表示:fanza_item本文に動画・サンプル画像・アフィリエイトリンクを追加
// ==============================
add_filter('the_content', 'fanza_sync_append_content');
function fanza_sync_append_content($content)
{
    if (! is_singular('fanza_item') || ! in_the_loop() || ! is_main_query()) {
        return $content;
    }

    $affiliate_url = get_field('fanza_affiliateurl');


    $video_html = '';
    $output = '';

    $movie_url  = get_field('fanza_samplemovieurl');
    if ($movie_url) {
        $video_html = '<div class="fanza-video-wrapper"><iframe src="' . esc_url($movie_url) . '" frameborder="0" scrolling="no" allowfullscreen loading="lazy"></iframe></div>';
    } else {
        $thumb_url = get_the_post_thumbnail_url(get_the_ID(), 'large');
        if ($affiliate_url) {
            $video_html = '<a href="' . esc_url($affiliate_url) . '" target="_blank" rel="nofollow noopener"><img src="' . esc_url($thumb_url) . '" class="fanza-video-thumb" alt="" loading="lazy"></a>';
        } else {
            $video_html = '<img src="' . esc_url($thumb_url) . '" class="fanza-video-thumb" alt="" loading="lazy">';
        }
        // $video_html = '<img src="' . esc_url($thumb_url) . '" class="fanza-video-thumb" alt="" loading="lazy">';
    }

    $video_html .= '<br><br>';

    if ($affiliate_url) {
        $output .= '<p class="fanza-affiliate-link"><a href="' . esc_url($affiliate_url) . '" target="_blank" rel="nofollow noopener">FANZAで詳細を見る</a></p>';
    }    



    $sale_start_date = get_field('fanza_date');
    if ($sale_start_date) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $sale_start_date);
        if ($date_obj) {
            $today = new DateTime('today');
            $label = ($date_obj > $today) ? '発売予定日' : '発売日';
            $formatted_date = $date_obj->format('Y年m月d日');
            $content .= '<p align="right" class="fanza-sale-date">' . esc_html($label) . ': ' . esc_html($formatted_date) . '</p>';
        }
    }

    

    $sample_images_raw = get_field('fanza_sampleimageurl');
    $sample_images_l_raw = get_field('fanza_sampleimageurl_l');

    if ($sample_images_raw) {
        $sample_images = array_filter(array_map('trim', explode("\n", $sample_images_raw)));
        $sample_images_l = $sample_images_l_raw
            ? array_filter(array_map('trim', explode("\n", $sample_images_l_raw)))
            : [];
        // インデックスを振り直す(array_filterで欠番になるのを防ぐ)
        $sample_images = array_values($sample_images);
        $sample_images_l = array_values($sample_images_l);

        if ($sample_images) {
            $output .= '<div class="fanza-sample-images">';
            $output .= "<br>サンプル画像<hr>";
            foreach ($sample_images as $i => $img_url) {
                $large_url = $sample_images_l[$i] ?? $img_url; // lが無ければsにフォールバック
                $output .= '<img src="' . esc_url($img_url) . '" data-large="' . esc_url($large_url) . '" class="fanza-sample-thumb" loading="lazy" alt="">';
            }
            $output .= '</div>';

            // レイヤー(ライトボックス)用のマークアップ
            $output .= '<div id="fanza-sample-overlay" class="fanza-sample-overlay">';
            $output .= '<span id="fanza-sample-prev" class="fanza-sample-arrow fanza-sample-prev">&#10094;</span>';
            $output .= '<img id="fanza-sample-overlay-img" src="" alt="">';
            $output .= '<span id="fanza-sample-next" class="fanza-sample-arrow fanza-sample-next">&#10095;</span>';
            $output .= '</div>';
        }
    }

    return $video_html . $output . $content;
}

// ==============================
// fanza_item投稿のみ、本文上のアイキャッチを非表示にする
// (動画サムネイルと2重表示になるため)
// ==============================
add_action('wp_head', 'fanza_sync_hide_eye_catch');
function fanza_sync_hide_eye_catch()
{
    if (is_singular('fanza_item')) {
        echo '<style>.eye-catch-wrap { display: none; }</style>';
    }
}

// ==============================
// カテゴリータクソノミーをfanza_itemに紐付ける
// ==============================

add_action('init', function () {
    register_taxonomy_for_object_type('category', 'fanza_item');
}, 20); // CPTとcategoryタクソノミーの登録が終わった後に実行する

/**
 * fanza_item投稿のfanza_dateを見て「発売前」「新着」カテゴリをセットする
 * fanza_dateが空の場合はカテゴリなし(発売前/新着を除去)にする
 *
 * @param bool $all trueなら更新日を問わず全件を対象にする(手動での全件再生成用)
 * @return array 集計結果 [with_date, nonrelease, new, none, no_date]
 */
function fanza_update_release_categories($all = false)
{

    $today     = current_time('Y-m-d');
    $seven_ago = date('Y-m-d', strtotime($today . ' -7 days'));

    $args = [
        'post_type'      => 'fanza_item',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ];

    if (! $all) {
        $args['date_query'] = [
            [
                'column' => 'post_modified',
                'after'  => '1 day ago',
            ],
        ];
    }

    $counts = [
        'with_date'      => 0,
        'nonrelease' => 0,
        'new'            => 0,
        'none'           => 0,
        'no_date'        => 0,
    ];

    $query = new WP_Query($args);

    if (empty($query->posts)) {
        return $counts;
    }

    foreach ($query->posts as $post_id) {
        try {

            $fanza_date = get_post_meta($post_id, 'fanza_date', true);
            $target_cat = null;

            if (empty($fanza_date)) {
                // fanza_dateが無い → カテゴリなし
                $counts['no_date']++;
            } else {
                $counts['with_date']++;
                $fanza_date_ymd = date('Y-m-d', strtotime($fanza_date));

                if ($fanza_date_ymd > $today) {
                    $target_cat = 'nonrelease';
                    $counts['nonrelease']++;
                } elseif ($fanza_date_ymd >= $seven_ago) {
                    $target_cat = 'new';
                    $counts['new']++;
                } else {
                    $counts['none']++;
                }
            }

            // 対象になった投稿は必ず棚卸しする(fanza_date空でも発売前/新着を外す)
            $current_terms = wp_get_post_terms($post_id, 'category', [
                'fields' => 'ids'
            ]);

            $remove_ids = [];

            foreach (['nonrelease', 'new'] as $name) {
                $term = get_term_by('slug', $name, 'category');
                if ($term) {
                    $remove_ids[] = (int)$term->term_id;
                }
            }

            if (is_wp_error($current_terms)) {
                throw new Exception(
                    'wp_get_post_terms: ' . $current_terms->get_error_message()
                );
            }

            $keep_terms    = array_diff($current_terms, $remove_ids);

            if ($target_cat) {
                $term = get_term_by('slug', $target_cat, 'category');
                if ($term) {
                    $keep_terms[] = (int)$term->term_id;
                }
            }
            $keep_terms = array_unique($keep_terms);

            $new_terms = wp_set_post_terms($post_id, $keep_terms, 'category', false);

            if (is_wp_error($new_terms)) {
                throw new Exception(
                    'wp_set_post_terms: ' . $new_terms->get_error_message()
                );
            }
        } catch (Exception $e) {
            file_put_contents(
                ABSPATH . 'fanza_error.log',
                sprintf(
                    "[%s] post=%d\n%s\nFile:%s\nLine:%d\nTrace:\n%s\n\n",
                    date('Y-m-d H:i:s'),
                    $post_id,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString()
                ),
                FILE_APPEND | LOCK_EX
            );
            $counts['set_error']++;
            continue;
        }
    }

    return $counts;
}

// ==============================
// 投稿一覧 / 女優一覧 切り替えタブ
// ==============================
function fanza_sync_render_switch_tabs($active = 'posts')
{
    $actress_page = get_page_by_path('actress-list');
    $actress_url  = $actress_page ? get_permalink($actress_page) : '';

    if (! $actress_url) {
        // echo '<!-- fanza debug: actress-list page not found -->';
        return;
    }
?>
    <div class="fanza-switch-tabs">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="fanza-tab <?php echo $active === 'posts' ? 'is-active' : ''; ?>">
            動画投稿一覧
        </a>
        <a href="<?php echo esc_url($actress_url); ?>" class="fanza-tab <?php echo $active === 'actress' ? 'is-active' : ''; ?>">
            女優一覧
        </a>
    </div>
<?php
}

// トップページの投稿一覧の直前に表示
add_action('loop_start', 'fanza_sync_show_switch_tabs_on_front');
function fanza_sync_show_switch_tabs_on_front($query)
{
    static $done = false;

    if ($done || is_admin() || ! is_front_page() || ! $query->is_main_query()) {
        return;
    }

    $done = true;
    fanza_sync_render_switch_tabs('posts');
}

// ==============================
// 女優タクソノミーの出演作品一覧ページ上部に女優プロフィールを表示
// ==============================
add_action('loop_start', 'fanza_sync_show_actress_profile');
function fanza_sync_show_actress_profile($query)
{
    static $done = false;

    if ($done || is_admin() || ! is_tax('actress') || ! $query->is_main_query()) {
        return;
    }

    $done = true;

    $term = get_queried_object();
    if (! $term || is_wp_error($term) || empty($term->term_id)) {
        return;
    }

    $ruby       = get_term_meta($term->term_id, 'actress_ruby', true);
    $thumbnail  = get_term_meta($term->term_id, 'actress_thumbnail', true);
    $bust       = get_term_meta($term->term_id, 'actress_bust', true);
    $cup        = get_term_meta($term->term_id, 'actress_cup', true);
    $waist      = get_term_meta($term->term_id, 'actress_waist', true);
    $hip        = get_term_meta($term->term_id, 'actress_hip', true);
    $height     = get_term_meta($term->term_id, 'actress_height', true);
    $birthday   = get_term_meta($term->term_id, 'actress_birthday', true);
    $blood_type = get_term_meta($term->term_id, 'actress_blood_type', true);
    $hobby      = get_term_meta($term->term_id, 'actress_hobby', true);

    $age = null;
    if (! empty($birthday)) {
        $birth_date = DateTime::createFromFormat('Y-m-d', $birthday);
        if ($birth_date) {
            $today = new DateTime('today');
            $age = $today->diff($birth_date)->y;
        }
    }
?>
    <div class="actress-profile">
        <?php if (! empty($thumbnail)): ?>
            <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($term->name); ?>" class="actress-profile-thumb" loading="lazy">
        <?php endif; ?>
        <div class="actress-profile-body">
            <p class="actress-profile-name"><?php echo esc_html($term->name); ?></p>
            <?php if (! empty($ruby)): ?>
                <p class="actress-profile-ruby"><?php echo esc_html($ruby); ?></p>
            <?php endif; ?>
            <ul class="actress-profile-meta">
                <?php if (! empty($bust) || ! empty($waist) || ! empty($hip)): ?>
                    <li>
                        B <?php echo esc_html($bust ?: '-'); ?>
                        <?php if (! empty($cup)): ?>(<?php echo esc_html($cup); ?>)<?php endif; ?>
                        / W <?php echo esc_html($waist ?: '-'); ?>
                        / H <?php echo esc_html($hip ?: '-'); ?>
                    </li>
                <?php endif; ?>
                <?php if (! empty($height)): ?>
                    <li>身長 <?php echo esc_html($height); ?>cm</li>
                <?php endif; ?>
                <?php if (! empty($birthday)): ?>
                    <li>生年月日 <?php echo esc_html($birthday); ?><?php echo $age !== null ? ' (' . esc_html($age) . '歳)' : ''; ?></li>
                <?php endif; ?>
                <?php if (! empty($blood_type)): ?>
                    <li>血液型 <?php echo esc_html($blood_type); ?>型</li>
                <?php endif; ?>
                <?php if (! empty($hobby)): ?>
                    <li>趣味 <?php echo esc_html($hobby); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
<?php
}

//新着データ取得時にデータキャッシュを作成する
function fanza_new_list_rebuild_cache()
{
    $since = date('Y-m-d 00:00:00', strtotime('-1 day', current_time('timestamp')));

    $ids = get_posts([
        'post_type'      => 'fanza_item',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'date_query'     => [
            ['after' => $since, 'inclusive' => true],
        ],
        'meta_query'     => [
            [
                'key'     => 'fanza_samplemovieurl',
                'value'   => '',
                'compare' => '!=',
            ],
        ],
    ]);

    set_transient('fanza_new_list_ids', $ids, 25 * HOUR_IN_SECONDS);

    return $ids;
}



//temp
function update_title($term_id)
{
    $term_ids = [$term_id];
    fanza_actress_title_update($term_ids);
}

function actress_update()
// function fanza_backfill_actress_profile_fields(): array
{
    $term_ids = get_terms([
        'taxonomy'   => 'actress',
        'hide_empty' => false,
        'fields'     => 'ids',
    ]);

    fanza_actress_title_update($term_ids);
}


// ==============================
// バックフィル処理の進捗ログ出力
// WP-CLI実行時はWP_CLI::logで、それ以外はecho(CLIならそのまま標準出力、管理画面ならHTMLコメントとして出力)
// ==============================
function fanza_backfill_cli_log(string $message): void
{
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::log($message);
        return;
    }

    if (php_sapi_name() === 'cli') {
        echo $message . "\n";
        return;
    }

    echo '<!-- ' . esc_html($message) . " -->\n";
}

// ==============================
// アイキャッチ未設定の投稿を抽出し、APIを再取得してアイキャッチを設定し直す
// _fanza_cidが登録済みの投稿のみ対象。API負荷を考慮し1件ごとに待機する。
// ==============================
function fanza_backfill_missing_featured_images(): array
{
    $stats = [
        'total'   => 0,
        'updated' => 0,
        'skipped' => 0,
        'error'   => 0,
    ];

    $post_ids = get_posts([
        'post_type'      => 'fanza_item',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => '_thumbnail_id',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ]);

    if (empty($post_ids)) {
        fanza_backfill_cli_log('対象件数: 0件(アイキャッチ未設定の投稿はありません)');
        return $stats;
    }

    $stats['total'] = count($post_ids);

    fanza_backfill_cli_log('対象件数: ' . $stats['total'] . '件');

    foreach ($post_ids as $post_id) {
        $cid = get_post_meta($post_id, '_fanza_cid', true);

        if (empty($cid)) {
            $stats['skipped']++;
            continue;
        }

        $items = fanza_sync_fetch_items(1, 1, $cid);

        if (empty($items[0])) {
            $stats['error']++;
            sleep(1);
            continue;
        }

        $item = $items[0];

        $featured_image_url = ! empty($item['imageURL']['large'])
            ? $item['imageURL']['large']
            : (! empty($item['imageURL']['small']) ? $item['imageURL']['small'] : '');

        if (empty($featured_image_url)) {
            $stats['skipped']++;
            sleep(1);
            continue;
        }

        fanza_sync_set_featured_image($post_id, $featured_image_url);

        if (has_post_thumbnail($post_id)) {
            $stats['updated']++;
        } else {
            $stats['error']++;
        }

        // API負荷を考慮して1秒待機
        sleep(1);
    }

    return $stats;
}

/*
* fanza_item の VideoObject / Article JSON-LD を出力
 * mu-plugin (fanza-sync.php など) の末尾に追加、または別ファイルでrequireする
 */

add_filter(
    'the_seo_framework_schema_graph_data',
    function ( $graph, $args ) {

        if ( ! tsf()->query()->is_singular( 'fanza_item' ) ) {
            return $graph;
        }

        $post_id = tsf()->query()->get_the_real_id();

        // embed_url は既存のACFフィールドから直接取得(iframe側と同じソース)
        $embed_url = get_field( 'fanza_samplemovieurl', $post_id );
        if ( empty( $embed_url ) ) {
            return $graph;
        }

        $thumbnail_url = get_the_post_thumbnail_url( $post_id, 'full' );
        if ( empty( $thumbnail_url ) ) {
            $thumbnail_url = get_field( 'fanza_sampleimageurl', $post_id );
        }

        $description = get_field( 'fanza_description', $post_id );
        if ( empty( $description ) ) {
            $description = wp_strip_all_tags( get_the_excerpt( $post_id ) );
        }

        $volume_minutes = get_field( 'fanza_volume', $post_id );
        $duration_iso   = fanza_minutes_to_iso8601( $volume_minutes );

        $actors = [];
        $terms  = get_the_terms( $post_id, 'actress' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $actors[] = [
                    '@type' => 'Person',
                    'name'  => $term->name,
                ];
            }
        }

        foreach ( $graph as &$entity ) {
            if ( in_array( 'WebPage', (array) ( $entity['@type'] ?? [] ), true ) ) {

                $video = [
                    '@type'            => 'VideoObject',
                    'name'             => tsf()->sanitize()->metadata_content( get_the_title( $post_id ) ),
                    'description'      => tsf()->sanitize()->metadata_content( $description ),
                    'thumbnailUrl'     => $thumbnail_url,
                    'uploadDate'       => get_the_date( 'c', $post_id ),
                    'embedUrl'         => esc_url( $embed_url ),
                    'isFamilyFriendly' => 'https://schema.org/False',
                    'regionsAllowed'   => 'JP',
                ];

                if ( $duration_iso ) {
                    $video['duration'] = $duration_iso;
                }
                if ( $actors ) {
                    $video['actor'] = $actors;
                }

                $entity['video'] = $video;
                break;
            }
        }

        return $graph;
    },
    10,
    2
);

/**
 * fanza_volume の値をISO 8601 duration に変換
 * 確認されているパターン:
 *   "0:44:00"  → H:MM:SS
 *   "44:00"    → M:SS (念のため対応)
 *   "44"       → 分数のみ (念のため対応)
 */
function fanza_minutes_to_iso8601( $value ) {
    if ( empty( $value ) ) {
        return '';
    }

    $value = trim( $value );

    // "0:44:00" のような H:MM:SS 形式(コロン2つ)
    if ( preg_match( '/^(\d+):(\d{2}):(\d{2})$/', $value, $m ) ) {
        $total_minutes = ( (int) $m[1] * 60 ) + (int) $m[2];
        return $total_minutes > 0 ? sprintf( 'PT%dM', $total_minutes ) : '';
    }

    // "44:00" のような M:SS 形式(コロン1つ)
    if ( preg_match( '/^(\d+):(\d{2})$/', $value, $m ) ) {
        $minutes = (int) $m[1];
        return $minutes > 0 ? sprintf( 'PT%dM', $minutes ) : '';
    }

    // "44" のような純粋な分数
    if ( preg_match( '/^\d+$/', $value ) ) {
        $minutes = (int) $value;
        return $minutes > 0 ? sprintf( 'PT%dM', $minutes ) : '';
    }

    return '';
}