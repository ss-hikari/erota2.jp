<?php

// テーマサポート機能の追加
function xeory_base_setup() {
    // タイトルタグの自動生成をサポート
    add_theme_support( 'title-tag' );
    
    // その他のテーマサポート機能
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script'
    ) );
}
add_action( 'after_setup_theme', 'xeory_base_setup' );

// ファンクション
if (is_admin()) {
    require_once('lib/admin/init.php');
    require_once('lib/admin/manual.php');
    require_once('lib/functions/category-custom-fields.php');
    require_once('lib/functions/custom-fields.php');
}
require_once('lib/functions/asset.php');
require_once('lib/functions/head.php');
require_once('lib/functions/custom-header.php');
require_once('lib/functions/custom-post.php');
require_once('lib/functions/bzb-functions.php');
require_once('lib/functions/setting.php');
require_once('lib/functions/widget.php');
//require_once('lib/functions/lang.php');
require_once('lib/functions/postviews.php');
require_once('lib/functions/json-ld.php');
require_once('lib/functions/social_btn.php');
require_once('lib/functions/show_avatar.php');
require_once('lib/functions/shortcode.php');
require_once('lib/functions/rss.php');

add_action('wp', 'load_preview_specific_file');

//previewのみでの読み込み
function load_preview_specific_file() {
    if (is_preview()) {
        require_once('lib/admin/preview.php');
    }
}
# ここに全カスタマイズコードを貼る

// テーマのメタタグ出力を無効化（The SEO Frameworkに一本化）
remove_action('wp_head', 'bzb_header_meta', 1);



// 字数を100文字に指定する
function my_excerpt_mblength($length) {
    return 100;
}
add_filter('excerpt_mblength', 'my_excerpt_mblength');

// 本文からの抜粋末尾の文字列を指定する
function my_auto_excerpt_more($more) {
    return '・・・';
}
add_filter('excerpt_more', 'my_auto_excerpt_more');

function disable_auto_complete_redirect( $redirect_url ) {
    if ( is_404() ) return false;
    return $redirect_url;
}
add_filter( 'redirect_canonical', 'disable_auto_complete_redirect' );

//自動補完リダイレクト機能機能を停止
add_filter( 'do_redirect_guess_404_permalink', '__return_false' );

function move_scripts(){
  remove_action('wp_head', 'wp_print_scripts');
  remove_action('wp_head', 'wp_print_head_scripts', 9);
  remove_action('wp_head', 'wp_enqueue_scripts', 1);
  add_action('wp_footer', 'wp_print_scripts', 5);
  add_action('wp_footer', 'wp_print_head_scripts', 5);
  add_action('wp_footer', 'wp_enqueue_scripts', 5);
}
add_action( 'wp_enqueue_scripts', 'move_scripts' );

add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css' );
});
//「Gcolle専用：動画エラー自動削除 兼 サムネイル動画風加工プログラム」
function auto_fix_gcolle_final_v4($content) {
    if (!is_single() || is_admin()) return $content;

    $post_title = get_the_title();

    // 1. 【空白の元凶を完全に削除】
    // iframeだけでなく、それを取り囲む「padding-bottom:56.25%」などのスタイルを持つdivやpを丸ごと消去します。
    // これにより、動画エリアがあった場所の「巨大な隙間」が消滅します。
    $has_video = (preg_match('/gcolle\.net\/sample_video_preview\.php/i', $content) === 1);
    
    // パターン1: divで囲まれた複雑な構造を削除
    $content = preg_replace('/<div[^>]*style="[^"]*padding-bottom:56\.25%[^"]*"[^>]*>.*?<iframe[^>]+gcolle\.net\/sample_video_preview\.php[^>]*><\/iframe>.*?<\/div>/is', '', $content);
    // パターン2: 単体のiframeやそれを囲むpタグを削除
    $content = preg_replace('/<p[^>]*>\s*<iframe[^>]+gcolle\.net\/sample_video_preview\.php[^>]*><\/iframe>\s*<\/p>/i', '', $content);
    $content = preg_replace('/<iframe[^>]+gcolle\.net\/sample_video_preview\.php[^>]*><\/iframe>/i', '', $content);

    // 2. 本文内からアフィリエイト用URLを探す
    $url_pattern = '/https:\/\/gcolle\.net\/product_info\.php[^\s"\'<>]+/i';
    
    if (preg_match($url_pattern, $content, $url_matches)) {
        $aff_url = $url_matches[0];
        
        // 3. 画像タグで分解して処理
        $parts = preg_split('/(<img[^>]+>)/is', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $new_content = '';
        $is_first_image = true;

        foreach ($parts as $part) {
            if (preg_match('/^<img[^>]+>$/i', trim($part))) {
                
                // alt属性の書き換え
                $part = preg_replace('/alt\s*=\s*["\'][^"\']*["\']/i', '', $part);
                $part = str_replace('<img', '<img alt="' . esc_attr($post_title) . '"', $part);

                if ($is_first_image) {
                    // --- 1枚目の画像処理（中央寄せ） ---
                    // marginを調整して、上下の隙間を自然な広さに制御します
                    $new_part = '<div class="gcolle-img-container" style="text-align:center; margin: 0 0 1.5em 0; line-height: 0;">';
                    $new_part .= '<a href="' . esc_url($aff_url) . '" target="_blank" rel="sponsored nofollow" style="display:inline-block; position:relative; text-decoration:none;">';
                    $new_part .= $part; // <img>タグ
                    
                    // 動画(iframe)があった場合のみ、再生ボタンを表示
                    if ($has_video) {
                        $new_part .= '<div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width:60px; height:60px; background:rgba(0,0,0,0.6); border-radius:50%; display:flex; align-items:center; justify-content:center; pointer-events:none;">';
                        $new_part .= '<div style="width:0; height:0; border-top:15px solid transparent; border-bottom:15px solid transparent; border-left:25px solid #fff; margin-left:5px;"></div>';
                        $new_part .= '</div>';
                    }
                    
                    $new_part .= '</a></div>';
                    $part = $new_part;
                    $is_first_image = false; 
                }
            }
            $new_content .= $part;
        }
        return $new_content;
    }
    
    return $content;
}

add_filter('the_content', 'auto_fix_gcolle_final_v4', 9999);
// =====================================================
// ▼ 売れ筋ランキング（販売者）表示
// =====================================================
function gcolle_ranking_html() {
    $json = get_option('gcolle_maker_ranking', '');
    if (!$json) {
        $file = get_template_directory() . '/ranking_cache.json';
        if (file_exists($file)) $json = file_get_contents($file);
    }
    if (!$json) return '';
    $ranking = json_decode($json, true);
    if (!$ranking || !is_array($ranking)) return '';

    $rank_labels = ['', '1位', '2位', '3位', '4位', '5位', '6位', '7位', '8位', '9位', '10位'];
    $rank_colors = ['', '#c8a84b', '#9ea7b3', '#b87333', '#888', '#888', '#888', '#888', '#888', '#888', '#888'];

    $html  = '<div class="gcolle-ranking-wrap">';
    $html .= '<div class="gcolle-ranking-header"><span class="gcolle-ranking-dot"></span> 売れ筋ランキング <a class="gcolle-ranking-more" href="https://gcolle.net/ranking_manufacturer.php/ref/14266" target="_blank" rel="nofollow noopener">もっと見る &rsaquo;</a></div>';
    $html .= '<div class="gcolle-ranking-slider-wrap"><div class="gcolle-ranking-slider">';

    foreach ($ranking as $item) {
        $rank  = intval($item['rank']);
        $name  = esc_html($item['name']);
        $imgs  = isset($item['images']) ? $item['images'] : [];
        $label = isset($rank_labels[$rank]) ? $rank_labels[$rank] : $rank . '位';
        $color = isset($rank_colors[$rank]) ? $rank_colors[$rank] : '#888';
        $top3  = $rank <= 3 ? 'top3' : '';
        $tag   = get_term_by('name', $item['name'], 'post_tag');
        if ($tag) {
            $url = esc_url(get_tag_link($tag->term_id));
            $target = '_self'; $rel = '';
        } else {
            $url = esc_url($item['url']);
            $target = '_blank'; $rel = 'rel="nofollow noopener"';
        }
        $html .= '<div class="gcolle-rank-item ' . $top3 . '">';
        $html .= '<a href="' . $url . '" target="' . $target . '" ' . $rel . '>';
        $html .= '<div class="gcolle-rank-label" style="background:' . $color . ';">' . $label . '</div>';
        $html .= '<div class="gcolle-rank-imgs">';
        foreach (array_slice($imgs, 0, 4) as $img) {
            $html .= '<img src="' . esc_url($img) . '" loading="lazy" alt="' . $name . '">';
        }
        $html .= '</div><div class="gcolle-rank-name">' . $name . '</div></a></div>';
    }

    $html .= '</div>';
    $html .= '<div class="gcolle-ranking-dots">';
    for ($d = 0; $d < 5; $d++) { $html .= '<span></span>'; }
    $html .= '</div></div></div>';
    return $html;
}

// =====================================================
// ▼ 月間ランキング表示
// =====================================================
function gcolle_monthly_ranking_html() {
    $json = get_option('gcolle_monthly_ranking', '');
    if (!$json) {
        $file = get_template_directory() . '/monthly_ranking_cache.json';
        if (file_exists($file)) $json = file_get_contents($file);
    }
    if (!$json) return '';
    $ranking = json_decode($json, true);
    if (!$ranking || !is_array($ranking)) return '';

    $year = ''; $month = '';
    foreach ($ranking as $item) {
        if (!$item['deleted'] && isset($item['year'])) {
            $year = $item['year']; $month = $item['month']; break;
        }
    }
    $ranking_url = "https://gcolle.net/ranking.php/year/{$year}/month/{$month}";
    $rank_labels = ['', '1位', '2位', '3位', '4位', '5位', '6位', '7位', '8位', '9位', '10位'];
    $rank_colors = ['', '#c8a84b', '#9ea7b3', '#b87333', '#888', '#888', '#888', '#888', '#888', '#888', '#888'];

    $html  = '<div class="gcolle-ranking-wrap">';
    $html .= '<div class="gcolle-ranking-header"><span class="gcolle-ranking-dot"></span> ';
    $html .= $year && $month ? "{$year}年{$month}月 月間ランキング" : "月間ランキング";
    $html .= '<a class="gcolle-ranking-more" href="' . esc_url($ranking_url) . '" target="_blank" rel="nofollow noopener">もっと見る &rsaquo;</a></div>';
    $html .= '<div class="gcolle-ranking-slider-wrap"><div class="gcolle-ranking-slider">';

    foreach ($ranking as $item) {
        $rank    = intval($item['rank']);
        $label   = isset($rank_labels[$rank]) ? $rank_labels[$rank] : $rank . '位';
        $color   = isset($rank_colors[$rank]) ? $rank_colors[$rank] : '#888';
        $top3    = $rank <= 3 ? 'top3' : '';
        $deleted = $item['deleted'];
        $url     = $deleted ? esc_url($ranking_url) : esc_url($item['url']);
        $image   = !empty($item['image']) ? esc_url($item['image']) : '';

        $html .= '<div class="gcolle-rank-item ' . $top3 . '">';
        $html .= '<a href="' . $url . '" target="_blank" rel="nofollow noopener">';
        $html .= '<div class="gcolle-rank-label" style="background:' . $color . ';">' . $label . '</div>';
        $html .= '<div class="gcolle-rank-imgs">';
        if ($deleted || !$image) {
            $html .= '<div style="width:100%;aspect-ratio:248/198;background:#ddd;display:flex;align-items:center;justify-content:center;"><span style="color:#999;font-size:12px;">削除済</span></div>';
        } else {
            $html .= '<img src="' . $image . '" loading="lazy" alt="' . esc_attr($item['title']) . '">';
        }
        $html .= '</div>';
        $name = $deleted ? '削除済' : esc_html(mb_strimwidth($item['title'], 0, 20, '…'));
        $html .= '<div class="gcolle-rank-name">' . $name . '</div></a></div>';
    }

    $html .= '</div>';
    $html .= '<div class="gcolle-ranking-dots">';
    for ($d = 0; $d < 5; $d++) { $html .= '<span></span>'; }
    $html .= '</div></div></div>';
    return $html;
}

// =====================================================
// ▼ ランキング共通CSS・JS
// =====================================================
add_action('wp_head', function() {
    echo '<style>
.gcolle-ranking-wrap{background:#fff;padding:12px 0 16px;margin-bottom:18px;border-bottom:1px solid #eee;}
.gcolle-ranking-header{font-weight:bold;font-size:15px;padding:0 12px 10px;display:flex;align-items:center;gap:6px;}
.gcolle-ranking-dot{display:inline-block;width:12px;height:12px;background:#2581c4;border-radius:50%;}
.gcolle-ranking-more{margin-left:auto;font-size:12px;color:#555;text-decoration:none;font-weight:normal;}
.gcolle-ranking-slider-wrap{position:relative;padding:0 12px;}
.gcolle-ranking-slider{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:16px;scrollbar-width:none;}
.gcolle-ranking-slider::-webkit-scrollbar{display:none;}
.gcolle-rank-item{flex:0 0 180px;text-align:center;scroll-snap-align:start;}
.gcolle-rank-item a{text-decoration:none;color:#333;display:block;}
.gcolle-rank-label{color:#fff;font-weight:bold;font-size:15px;padding:5px 0;border-radius:4px 4px 0 0;}
.gcolle-rank-imgs{display:block;width:100%;background:#ddd;overflow:hidden;}
.gcolle-rank-imgs img{width:100%;height:auto;aspect-ratio:248/198;object-fit:cover;display:block;}
.gcolle-rank-name{font-size:11px;padding:5px 4px;background:#f5f5f5;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;}
.gcolle-ranking-dots{display:flex;justify-content:center;gap:6px;padding:6px 0 0;}
.gcolle-ranking-dots span{display:inline-block;width:8px;height:8px;border-radius:50%;background:#ccc;cursor:pointer;transition:background .2s;}
.gcolle-ranking-dots span.active{background:#555;}
@media(max-width:600px){.gcolle-rank-item{flex:0 0 140px;}}

/* 表示切替ボタン */
.view-toggle-wrap{display:flex;gap:8px;padding:8px 0 12px;justify-content:flex-end;}
.view-btn{padding:6px 14px;border:1px solid #ccc;background:#fff;border-radius:4px;cursor:pointer;font-size:13px;color:#555;}
.view-btn.active{background:#333;color:#fff;border-color:#333;}

/* グリッド表示 */
#post-loop.grid-view{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
#post-loop.grid-view .monthly-ranking-break{grid-column:1/-1;width:100%;}
.monthly-ranking-break{width:100%;}
#post-loop.grid-view article{margin-bottom:0 !important;padding-bottom:0 !important;border:none;}
#post-loop.grid-view .post-thumbnail{margin:0 !important;}
#post-loop.grid-view .post-thumbnail img,
#post-loop.grid-view .post-thumbnail a img{width:100% !important;height:200px !important;object-fit:cover !important;border-radius:4px !important;}
#post-loop.grid-view img.deco{width:100% !important;height:200px !important;object-fit:cover !important;margin:0 !important;max-width:100% !important;}
#post-loop.grid-view .post-header{padding:4px 2px;}
#post-loop.grid-view h2.post-title{font-size:12px !important;margin:4px 0 2px !important;line-height:1.4 !important;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
#post-loop.grid-view h2.post-title a{font-size:12px !important;}
#post-loop.grid-view .post-meta{display:none !important;}
#post-loop.grid-view ul.post-meta{display:none !important;}
#post-loop.grid-view ul:not(.post-meta){display:none !important;}
#post-loop.grid-view .maker{display:block !important;font-size:11px;color:#555;padding:2px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
#post-loop.grid-view .maker a{color:#555;text-decoration:none;}
#post-loop.grid-view .post-conten{display:none !important;}
/* グリッド用会員名 */
.maker-grid{display:none;font-size:11px;color:#555;padding:2px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.maker-grid a{color:#555;text-decoration:none;}
#post-loop.grid-view .maker-grid{display:block !important;}
#post-loop.grid-view strong.saishin,
#post-loop.grid-view strong.pickup{font-size:10px !important;}

/* PC幅ではグリッド表示を完全に無効化 */
@media(min-width:769px){
  #post-loop.grid-view{display:block !important;}
  #post-loop.grid-view article{margin-bottom:20px !important;padding-bottom:15px !important;border:revert;}
  #post-loop.grid-view .post-thumbnail{margin:revert !important;}
  #post-loop.grid-view .post-thumbnail img,
  #post-loop.grid-view .post-thumbnail a img{width:auto !important;height:auto !important;object-fit:revert !important;border-radius:revert !important;}
  #post-loop.grid-view img.deco{width:100% !important;height:auto !important;margin:0 -15px !important;max-width:calc(100% + 30px) !important;}
  #post-loop.grid-view .post-meta{display:flex !important;}
  #post-loop.grid-view ul.post-meta{display:flex !important;}
  #post-loop.grid-view ul:not(.post-meta){display:block !important;}
  #post-loop.grid-view .post-conten{display:block !important;}
  #post-loop.grid-view h2.post-title{font-size:revert !important;-webkit-line-clamp:unset;display:block;}
  #post-loop.grid-view h2.post-title a{font-size:revert !important;}
  #post-loop.grid-view .maker-grid{display:none !important;}
  .view-toggle-wrap{display:none !important;}
}
@media(max-width:768px){
  #post-loop.grid-view{grid-template-columns:repeat(2,1fr);gap:8px;}
  #post-loop.grid-view .post-thumbnail img,
  #post-loop.grid-view .post-thumbnail a img{height:130px !important;}
  #post-loop.grid-view img.deco{height:130px !important;}
}

/* スマホ表示調整 */
@media (max-width: 768px) {
    .post-thumbnail{margin:0 -15px !important;}
    .post-thumbnail img,.post-thumbnail a img{width:100% !important;height:auto !important;display:block !important;border-radius:0 !important;}
    img.deco{width:100% !important;margin:0 -15px !important;max-width:calc(100% + 30px) !important;}
    .post-title a,h2.post-title a{font-size:15px !important;line-height:1.5 !important;}
    h2.post-title{font-size:15px !important;margin:8px 0 4px !important;}
    .post-meta li,.post-meta .date{font-size:11px !important;}
    .cat,.tag,.maker{font-size:11px !important;}
    article{margin-bottom:20px !important;padding-bottom:15px !important;}
    /* ロゴを中央揃え・サイズ縮小 */
    #logo{text-align:center !important;display:block !important;float:none !important;}
    #logo a,#logo img{margin:0 auto !important;display:block !important;float:none !important;width:60% !important;height:auto !important;max-width:200px !important;}
    .blog_title{display:none !important;}
}
</style>
<script>
document.addEventListener("DOMContentLoaded", function(){
  // 表示切替ボタン
  var btnList = document.getElementById("btn-list");
  var btnGrid = document.getElementById("btn-grid");
  var loop    = document.getElementById("post-loop");
  var STORAGE_KEY = "ushi_view_mode";

  function setView(mode) {
    if (!loop) return;
    if (mode === "grid") {
      loop.classList.add("grid-view");
      if(btnList) btnList.classList.remove("active");
      if(btnGrid) btnGrid.classList.add("active");
    } else {
      loop.classList.remove("grid-view");
      if(btnList) btnList.classList.add("active");
      if(btnGrid) btnGrid.classList.remove("active");
    }
    localStorage.setItem(STORAGE_KEY, mode);
  }

  // 前回の表示モードを復元
  var savedMode = localStorage.getItem(STORAGE_KEY);
  if (savedMode === "grid") setView("grid");

  if (btnList) btnList.addEventListener("click", function(){ setView("list"); });
  if (btnGrid) btnGrid.addEventListener("click", function(){ setView("grid"); });

  // ランキングスライダー
  document.querySelectorAll(".gcolle-ranking-slider").forEach(function(slider){
    var wrap = slider.closest(".gcolle-ranking-slider-wrap");
    var dots = wrap ? wrap.querySelectorAll(".gcolle-ranking-dots span") : [];
    if(!dots.length) return;
    slider.addEventListener("scroll", function(){
      var idx = Math.round(slider.scrollLeft / (slider.scrollWidth / dots.length));
      dots.forEach(function(d,i){ d.classList.toggle("active", i===idx); });
    });
    dots.forEach(function(d,i){
      d.addEventListener("click", function(){
        slider.scrollTo({left: i * slider.scrollWidth / dots.length, behavior:"smooth"});
      });
    });
    if(dots[0]) dots[0].classList.add("active");
  });
});
</script>';
});

// =====================================================
// ▼ WP-Cron（週1回）
// =====================================================

// =====================================================
// ▼ 詳細ページ：メディア直後にボタンを自動挿入
// =====================================================
add_filter('the_content', function($content) {
    if (!is_single()) return $content;

    $btn_marker = 'この作品のフル動画をチェックする';

    // すでに2つ以上ボタンがある場合はスキップ
    if (substr_count($content, $btn_marker) >= 2) return $content;

    // アフィリエイトURLを取得
    if (!preg_match('/href="(https:\/\/gcolle\.net\/product_info\.php[^"]+)"/', $content, $m)) {
        return $content;
    }
    $aff_url = esc_url($m[1]);

    $btn_html = '<div style="text-align:center; margin: 16px 0;">'
        . '<a href="' . $aff_url . '" target="_blank" rel="nofollow noopener noreferrer" '
        . 'style="display:inline-block; background:#2581c4; color:#fff; padding:12px 28px; '
        . 'text-decoration:none; font-weight:bold; border-radius:30px; font-size:16px;">'
        . '&#9654; ' . $btn_marker . '</a>'
        . '</div>';

    // あらすじ or レビューセクションの前に挿入
    $patterns = ['/<h3>作品内容・あらすじ<\/h3>/', '/<h3>管理人のレビュー/', '/<div style="background:#fffcf4/'];
    foreach ($patterns as $pat) {
        if (preg_match($pat, $content, $match, PREG_OFFSET_CAPTURE)) {
            $pos = $match[0][1];
            return substr($content, 0, $pos) . $btn_html . "
" . substr($content, $pos);
        }
    }

    return $content;
}, 20);

// =====================================================
// ▼ 詳細ページ：1枚目画像をタイトル直下に表示（既存投稿のみ）
// 新規投稿はPythonで処理済みのため、先頭に画像がある場合はスキップ
// =====================================================
add_filter('the_content', function($content) {
    if (!is_single()) return $content;

    // h2「プレビュー動画・画像」より前にすでに画像がある場合はスキップ（新規投稿）
    $h2_pos_check = strpos($content, 'のプレビュー動画・画像</h2>');
    if ($h2_pos_check !== false) {
        $before_h2 = substr($content, 0, $h2_pos_check);
        if (preg_match('/<img[^>]+>/i', $before_h2)) {
            return $content; // すでに画像が先頭にある新規投稿はスキップ
        }
    }

    // h2「プレビュー動画・画像」セクションを探す
    if (!preg_match('/<h2>[^<]*のプレビュー動画・画像<\/h2>
?/', $content, $h2_match, PREG_OFFSET_CAPTURE)) {
        return $content;
    }
    $h2_pos = $h2_match[0][1];
    $after_h2 = substr($content, $h2_pos + strlen($h2_match[0][0]));

    // h2以降の最初のimgタグを含むpタグを取得
    if (!preg_match('/<p[^>]*>\s*<img[^>]+>\s*<\/p>
?/', $after_h2, $img_match, PREG_OFFSET_CAPTURE)) {
        return $content;
    }
    $first_img = $img_match[0][0];
    $img_pos   = $img_match[0][1];

    // 元の位置から削除
    $after_h2_removed = substr($after_h2, 0, $img_pos) . substr($after_h2, $img_pos + strlen($first_img));
    $content_removed  = substr($content, 0, $h2_pos + strlen($h2_match[0][0])) . $after_h2_removed;

    // JSON-LDスクリプトの後 or 先頭に挿入
    if (preg_match('/<\/script>
/', $content_removed, $sc_match, PREG_OFFSET_CAPTURE)) {
        $insert_pos = $sc_match[0][1] + strlen($sc_match[0][0]);
    } else {
        $insert_pos = 0;
    }

    return substr($content_removed, 0, $insert_pos)
         . $first_img
         . substr($content_removed, $insert_pos);
}, 5);
// --- ここから RSS 修正用コード ---
function uishi_rss_enclosure_fixed() {
    global $post;
    if (has_post_thumbnail($post->ID)) {
        $img_id = get_post_thumbnail_id($post->ID);
        $img_url = get_the_post_thumbnail_url($post->ID, 'full');
        $img_path = get_attached_file($img_id);
        $img_size = (file_exists($img_path)) ? filesize($img_path) : 0;
        $img_type = get_post_mime_type($img_id);
        printf('<enclosure url="%s" length="%s" type="%s" />', esc_url($img_url), $img_size, esc_attr($img_type));
    }
}
add_action('rss2_item', 'uishi_rss_enclosure_fixed');

function rss_post_thumbnail_to_content($content) {
    global $post;
    if (has_post_thumbnail($post->ID)) {
        $content = '<div>' . get_the_post_thumbnail($post->ID, 'medium') . '</div>' . $content;
    }
    // 生存確認用デバッグ文字列（後で消してOK）
    return "" . $content;
}
add_filter('the_excerpt_rss', 'rss_post_thumbnail_to_content', 999);
add_filter('the_content_feed', 'rss_post_thumbnail_to_content', 999);
// --- ここまで ---
// テーマのメタタグ出力を無効化（The SEO Frameworkに一本化）
// 本文からの抜粋末尾の文字列を指定する
