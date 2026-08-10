<?php

/* セッティング & 小物系
   WordPress標準の機能はこちらに記入しています。
* ---------------------------------------- */

/* ナビ
* ---------------------------------------- */
// register_nav_menu('primary_nav', 'プライベートナビ（最上部に表示）');
register_nav_menu('global_nav', 'グローバルナビ（ヘッダー領域下に表示）');
register_nav_menu('footer_nav', 'フッターナビ（フッター領域に表示）');

/* ナビにclassを付ける
* ---------------------------------------- */
add_filter( 'nav_menu_css_class', 'bzb_my_nav_menu_css_class', 10, 2 );
function bzb_my_nav_menu_css_class( $classes, $item ) {
  // ページ（固定ページ）
  if ( 'page' === $item->object ) {
    $post = get_post( $item->object_id );
    if ( $post && ! is_wp_error( $post ) && ! empty( $post->post_name ) ) {
      $classes[] = $post->post_name; // スラッグを付与
    }
  }

  // カテゴリ
  elseif ( 'category' === $item->object ) {
    $term = get_term( (int) $item->object_id, 'category' );
    if ( $term && ! is_wp_error( $term ) ) {
      $classes[] = $term->slug; // スラッグを付与
    }
  }

  return $classes;
}


/* アイキャッチ
* ---------------------------------------- */
// add_theme_support( 'post-thumbnails', array( 'post', 'page', 'cta', 'lp' ) );
add_theme_support( 'post-thumbnails' );
set_post_thumbnail_size( 304, 214 ); // 通常の投稿サムネイル
add_image_size( 'post-thumbnail-2nd', 282, 260 ); // 個別投稿、個別の固定ページでのサムネイル

add_action( 'admin_init', 'bzb_my_admin_init' );
function bzb_my_admin_init() {
  add_filter( 'gettext', 'bzb_my_gettext', 10, 3 );
}

function bzb_my_gettext( $translate_text, $text, $domain ) {
  $translate_text = str_replace( 'アイキャッチ画像を設定', '画像をアップロードする', $translate_text );
  $translate_text = str_replace( 'アイキャッチ画像を削除', 'メイン画像を削除する', $translate_text );
  $translate_text = str_replace( 'アイキャッチ画像', 'メイン画像を設定', $translate_text );
  return $translate_text;
}


/* サイドバー
* ---------------------------------------- */
register_sidebar(array(
  'name'          => 'サイドバー',
  'id'            => 'sidebar',
  'description'   => 'サイドバーに入るウィジェットエリアです。',
  'before_widget' => '<div id="%1$s" class="%2$s side-widget"><div class="side-widget-inner">',
  'after_widget'  => '</div></div>',
  'before_title'  => '<h2 class="side-title"><span class="side-title-inner">',
  'after_title'   => '</span></h2>'
));


//ここから
 register_sidebar(array(
 'name' => '投稿記事下',
 'id' => 'under_post_area',
 'description' => '',
 'before_widget' => '<div>',
 'after_widget' => '</div>',
 'before_title' => '<h3>',
 'after_title' => '</h3>'
 ));


/* more-linkのハッシュ消し
* ---------------------------------------- */
add_filter('the_content_more_link', 'bzb_remove_more_jump_link');
function bzb_remove_more_jump_link($link) {
  $offset = strpos($link, '#more-');
  if ( $offset ) {
    $end = strpos($link, '"',$offset);
  }
  if ( $end ) {
    $link = substr_replace($link, '', $offset, $end-$offset);
  }
  return $link;
}


/* more-linkにnofollow
* ---------------------------------------- */
add_filter('the_content', 'bzb_nofollow_more_link');
function bzb_nofollow_more_link($content) {
	return preg_replace("@class=\"more-link\"@", "class=\"more-link\" rel=\"nofollow\"", $content);
}


/* user_setting
* ---------------------------------------- */
add_filter('user_contactmethods', 'bzb_my_user_meta', 10, 1);
function bzb_my_user_meta($bzb_user_info){
  //項目の削除
  //unset($bzb_user_info['xxx']);

  //項目の追加
  $bzb_user_info['facebook'] = 'facebook';

  return $bzb_user_info;
}


/* add comment..
* ---------------------------------------- */
add_action( 'user_edit_form_tag', 'bzb_add_enctype_attr2user_edit_form_tag' );
function bzb_add_enctype_attr2user_edit_form_tag() {
  echo ' enctype="multipart/form-data"';
}


/* オリジナルアバター
* ---------------------------------------- */
add_action( 'show_password_fields', 'bzb_add_original_avatar_form' );
function bzb_add_original_avatar_form( $bool) {
  global $profileuser;
  if ( preg_match( '/^(profile\.php|user-edit\.php)/', basename( $_SERVER['REQUEST_URI'] ) ) ) {
?>

<script type="text/javascript">

jQuery('document').ready(function(){
  jQuery('.media-upload').each(function(){
    var rel = jQuery(this).attr("rel");

    jQuery(this).click(function(){
      window.send_to_editor = function(html) {
        html = '<a>' + html + '</a>';
        imgurl = jQuery('img', html).attr('src');
        jQuery('#'+rel).val(imgurl);
        tb_remove();
      }
      formfield = jQuery('#'+rel).attr('name');
      tb_show(null, 'media-upload.php?post_id=0&type=image&TB_iframe=true');
      return false;
    });
  });
});
</script>

          <tr>
            <th><label for="original_avatar">オリジナルアバター</label></th>
            <td>
              <?php
              // $profileuser がオブジェクトで、ID プロパティを持っていることを確認
              if ( isset($_GET['user_id']) && is_numeric($_GET['user_id']) ) {
                  $user_id = intval($_GET['user_id']);
              } elseif ( is_object($profileuser) && isset($profileuser->ID) ) {
                  $user_id = $profileuser->ID;
              } else {
                  $current_user = wp_get_current_user();
                  $user_id = $current_user->ID;
              }
              ?>
              <input type="text" id="original_avatar" name="original_avatar" class="regular-text" value="<?php echo esc_attr(get_user_meta($user_id, 'original_avatar', true)); ?>" />
              <a class="media-upload" href="JavaScript:void(0);" rel="original_avatar">
                <input class="cmb_upload_button button" type="button" value="画像をアップロードする" />
              </a>
            </td>
          </tr>
<?php
  }
  return $bool;
}

add_action( 'profile_update', 'bzb_update_original_avatar', 10, 2 );
function bzb_update_original_avatar( $user_id, $old_user_data ) {
    if ( isset( $_POST['original_avatar'] ) && $old_user_data->original_avatar != $_POST['original_avatar'] ) {
        $original_avatar = sanitize_text_field( $_POST['original_avatar'] );
        $original_avatar = wp_filter_kses( $original_avatar );
        $original_avatar = _wp_specialchars( $original_avatar );
        update_user_meta( $user_id, 'original_avatar', $original_avatar );
    }
}


/* add comment..
* ---------------------------------------- */
if( !function_exists('pagination') ){
  function pagination($pages = '', $range = 4){
    $showitems = ($range * 2)+1;

    global $paged;

    if( empty($paged) ){
      $paged = 1;
    }

    if( $pages == '' ){
      global $wp_query;
      $pages = $wp_query->max_num_pages;
      if(!$pages){
        $pages = 1;
      }
    }

    if(1 != $pages){
      echo "<div class=\"pagination\">";
      if( $paged > 2 && $paged > $range+1 && $showitems < $pages ){
        echo "<a href='".get_pagenum_link(1)."'><i class='fa fa-angle-double-left'></i></a>";
      }
      if( $paged > 1 && $showitems < $pages ){
        echo "<a href='".get_pagenum_link($paged - 1)."'><i class='fa fa-angle-left'></i></a>";
      }
      for ( $i=1; $i <= $pages; $i++ ){
        if ( 1 != $pages && ( !($i >= $paged+$range+1 || $i <= $paged-$range-1) || $pages <= $showitems ) ){
          echo ($paged == $i)? "<span class=\"current\">".$i."</span>":"<a href='".get_pagenum_link($i)."' class=\"inactive\">" . $i . "</a>";
        }
      }

      if ( $paged < $pages && $showitems < $pages ){
        echo "<a href=\"".get_pagenum_link($paged + 1)."\"><i class='fa fa-angle-right'></i></a>";
      }
      if ( $paged < $pages-1 &&  $paged+$range-1 < $pages && $showitems < $pages ){
        echo "<a href='".get_pagenum_link($pages)."'><i class='fa fa-angle-double-right'></i></a>";
      }
      echo "</div>\n";
    }
  }
}


/* 検索フォーム
* ---------------------------------------- */
// Search form template is located in searchform.php
// Use get_search_form() in templates to display the search form


/* メインクエリ
* ---------------------------------------- */
add_action( 'pre_get_posts', 'bzb_customize_main_query' );
if( !function_exists('bzb_customize_main_query') ){
  function bzb_customize_main_query($query) {

    if ( is_admin() || ! $query->is_main_query() )
        return;

    if ( $query->is_home() ) {
        $query->set(
           'meta_query',
           array(
             array(  'key'=>'bzb_show_toppage_flag',
                       'compare' => 'NOT EXISTS'
             ),
             array(  'key'=>'bzb_show_toppage_flag',
                       'value'=>'none',
                       'compare'=>'!='
             ),
            'relation'=>'OR'
          )
        );
        $query->set('order','DESC');
    }

   if ( !is_admin() && $query->is_main_query() && $query->is_search() ) {
    $query->set( 'post_type', 'post' );
   }
  }
}

/* Show Tag Manager
* ---------------------------------------- */
add_action( 'wp_body_open', 'bzb_show_tag_manager_body' );
if( !function_exists('bzb_show_tag_manager_body') ){
  function bzb_show_tag_manager_body() {

    $inputTag = get_option("tag_manager_body");
    if ( $inputTag && $inputTag !== '') {
        echo get_option("tag_manager_body");
    }
  }
}