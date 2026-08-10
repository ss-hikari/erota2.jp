<?php
add_action('wp_head', 'theme_json_ld', 20);

function theme_get_breadcrumb_schema() {
  if ( ! function_exists( 'bzb_get_breadcrumb_items' ) ) return null;

  $items = bzb_get_breadcrumb_items();
  if ( empty( $items ) ) return null;

  $list_elements = array();
  $position = 1;

  foreach ( $items as $item ) {
    $name = isset( $item['label'] ) ? trim( wp_strip_all_tags( $item['label'] ) ) : '';
    $url  = isset( $item['url'] ) ? (string) $item['url'] : '';

    if ( $name === '' ) continue;

    $element = array(
      '@type'    => 'ListItem',
      'position' => $position++,
      'name'     => $name,
    );

    if ( $url !== '' ) {
      $element['item'] = esc_url_raw( $url );
    }

    $list_elements[] = $element;
  }

  if ( empty( $list_elements ) ) return null;

  return array(
    '@type'            => 'BreadcrumbList',
    'itemListElement'  => $list_elements,
  );
}

function theme_json_ld() {

  // SEOプラグインが有効ならテーマ側は何も出さない
  if (
    defined('WPSEO_VERSION') ||
    defined('RANK_MATH_VERSION') ||
    defined('AIOSEO_VERSION') ||
    class_exists('All_in_One_SEO_Pack')
  ) return;

  $breadcrumb_schema = theme_get_breadcrumb_schema();

  // ===== front-page =====
  if (is_front_page()) {

    $schemas = [];

    // WebSite
    $schemas[] = [
      '@type' => 'WebSite',
      '@id' => home_url('/#website'),
      'name' => get_bloginfo('name'),
      'url'  => home_url('/'),
      'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => [
          '@type' => 'EntryPoint',
          'urlTemplate' => home_url('/?s={search_term_string}'),
        ],
        'query-input' => 'required name=search_term_string',
      ],
    ];

    // WebPage（トップ）
    $schemas[] = [
      '@type' => 'WebPage',
      '@id' => home_url('/#webpage'),
      'url' => home_url('/'),
      'name' => get_bloginfo('name'),
      'description' => get_bloginfo('description'),
      'isPartOf' => [
        '@id' => home_url('/#website'),
      ],
    ];

    if ( $breadcrumb_schema ) {
      $schemas[] = $breadcrumb_schema;
    }

    $output = [
      '@context' => 'https://schema.org',
      '@graph'   => $schemas,
    ];
    // 1つのscriptタグにまとめて出力
    echo "\n<script type=\"application/ld+json\">" .
      wp_json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
    "</script>\n";

    return;
  }

  $schemas = array();

  if ( $breadcrumb_schema ) {
    $schemas[] = $breadcrumb_schema;
  }

  // ===== single / 固定ページ =====
  if ( is_singular() && ! is_singular('lp') ) {

    $post_id = get_queried_object_id();
    if (!$post_id) return;

    $post = get_post($post_id);
    if (!$post) return;

// 投稿タイプで schema type を分岐
$type = is_single() ? 'BlogPosting' : 'WebPage';

// image（アイキャッチがない場合はデフォルト画像）
$image_url = has_post_thumbnail($post_id)
  ? get_the_post_thumbnail_url($post_id, 'full')
  : get_theme_file_uri('/assets/images/default-thumbnail.png');

// description（ACF → 本文）
$desc = '';
if (function_exists('get_field')) {
  $desc = (string) get_field('meta_description', $post_id);
}
if ($desc === '') {
  $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
  $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
  $content = preg_replace('/\s+/', ' ', $content); // 改行・連続スペースを1スペースに
  $content = trim($content);
  $desc = mb_strimwidth($content, 0, 160, '…', 'UTF-8');
}

// ロゴ情報を取得
$logo_url = get_option('logo_image');
$logo_width = 600;  // デフォルト値
$logo_height = 60;

if (!empty($logo_url)) {
  $logo_id = attachment_url_to_postid($logo_url);

  if ($logo_id) {
    $logo_data = wp_get_attachment_image_src($logo_id, 'full');
    if ($logo_data) {
      $logo_width = $logo_data[1];
      $logo_height = $logo_data[2];
    }
  }
} else {
  // ロゴ未登録時のフォールバック
  $logo_url = get_theme_file_uri('/assets/images/logo.png');
}

$schema = [
  '@type' => $type,
  '@id'   => get_permalink($post_id),
  'url'   => get_permalink($post_id),
  'mainEntityOfPage' => [
    '@type' => 'WebPage',
    '@id' => get_permalink($post_id),
  ],
  'headline' => get_the_title($post_id),
  'description' => $desc,
  'image' => [$image_url],
  'datePublished' => get_the_date('c', $post_id),
  'dateModified'  => get_the_modified_date('c', $post_id),
  'author' => [
    '@type' => 'Person',
    'name' => get_the_author_meta('display_name', (int) $post->post_author),
    'url' => get_author_posts_url((int) $post->post_author),
    'description' => mb_strimwidth(
      preg_replace('/\r\n|\r|\n/', ' ', get_the_author_meta('description', (int) $post->post_author)),
      0, 160, '…', 'UTF-8'
    ),
  ],
  'publisher' => [
    '@type' => 'Organization',
    'name' => get_bloginfo('name'),
    'logo' => [
      '@type' => 'ImageObject',
      'url' => $logo_url,
      'width' => $logo_width,
      'height' => $logo_height,
    ],
  ],
];

// BlogPostingの場合はWebSiteとの関連を追加
if ($type === 'BlogPosting') {
  $schema['isPartOf'] = [
    '@id' => home_url('/#website'),
  ];
}

// WebPageの場合は name も補完
if ($type === 'WebPage') {
  $schema['name'] = get_the_title($post_id);
}

    $schemas[] = $schema;
  }

  if ( empty( $schemas ) ) return;

  $output = [
    '@context' => 'https://schema.org',
    '@graph'   => $schemas,
  ];

echo "\n<script type=\"application/ld+json\">" .
  wp_json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
"</script>\n";
}
