<?php

/**
 * StyleSheet
 *
 * @return void
 */
function bzb_register_styles()
{
    wp_register_style('base', get_template_directory_uri() . '/base.css');
    wp_register_style('main', get_template_directory_uri() . '/style.css', ['base']);
    wp_register_style('icon', get_template_directory_uri() . '/lib/css/icon.css');
    wp_register_style('single-lp', get_template_directory_uri() . '/lib/css/single-lp.css');
}
add_action('wp_enqueue_scripts', 'bzb_register_styles');

/**
 * テーマのスタイルシートを読み込む
 *
 * @return void
 */
function bzb_enqueue_styles()
{
    if (!is_admin()) {
        wp_enqueue_style('base');
        wp_enqueue_style('main');
    }

    wp_enqueue_style('icon');

    // 投稿タイプ 'lp' の単一ページでのみ 'single-lp.css' を読み込む
    if (is_singular('lp')) {
        wp_enqueue_style('single-lp');
    }

    // カラースキームに基づいて color.css を読み込む
    $color_scheme = trim(get_option('color_scheme'));
    if ($color_scheme !== 'default') {
        wp_enqueue_style('color-style', get_template_directory_uri() . '/lib/css/color.css');
    }
}
add_action('wp_enqueue_scripts', 'bzb_enqueue_styles', 10);


/* JavaScript
* ---------------------------------------- */

if (!is_admin()) {
  add_action('wp_enqueue_scripts', 'bzb_add_script');
  function bzb_register_script(){
    // トップページへ戻る
    wp_register_script('pagetop', get_template_directory_uri().'/lib/js/jquery.pagetop.js', array('jquery'), false, true );
    wp_register_script('table-scroll', get_template_directory_uri().'/lib/js/jquery.table-scroll.js', array('jquery'), false, true );
  }
  function bzb_add_script() {
    bzb_register_script();
    wp_enqueue_script('pagetop');
    wp_enqueue_script('table-scroll');
  }
}
