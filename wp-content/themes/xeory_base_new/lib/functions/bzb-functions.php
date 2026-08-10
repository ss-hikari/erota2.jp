<?php


/* add comment..
* ---------------------------------------- */
add_filter('body_class', 'bzb_body_post_layout');

function bzb_body_post_layout($classes){

  // レイアウトの設定
  global $post;

  $post_layout = "";
  if( is_front_page() || is_home() || is_category() || is_archive() || is_search() || is_404() ){

    /*$cf = get_post_meta($post->ID);
    if(isset($cf['bzb_post_layout'][0]) && $cf['bzb_post_layout'][0] !==''){
      $post_layout = $cf['bzb_post_layout'][0];
    }else{*/
      $post_layout = get_option('post_layout');
    //}

  }elseif( is_single() || is_page() || is_page_template('page-lp.php') ){
    $cf = get_post_meta($post->ID);
    if(isset($cf['bzb_post_layout']) && $cf['bzb_post_layout'] !== ''){
      if( is_array( $cf['bzb_post_layout'] ) ){
        $post_layout = reset($cf['bzb_post_layout']);
      }else{
        $post_layout = $cf['bzb_post_layout'];
      }
    }else{
        $post_layout = get_option('post_layout');
    }
  }
  $classes[] = esc_attr($post_layout);

  return $classes;
}


/* add comment..
* ---------------------------------------- */
add_filter('body_class', 'bzb_color_scheme');

function bzb_color_scheme($classes){
  $color_scheme = get_option('color_scheme');
  $classes[] = $color_scheme;

  return $classes;
}


/* add comment..
* ---------------------------------------- */
function bzb_show_facebook_block(){

  $facebook_block = '';

  $facebook_app_id = esc_html(get_option('facebook_app_id'));

  $facebook_block=<<<EOF
  <div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/ja_JP/sdk.js#xfbml=1&version=v2.8&appId={$facebook_app_id}";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>

EOF;
  echo $facebook_block;
}


/* パンクズリスト
* ---------------------------------------- */
if ( ! function_exists( 'bzb_get_breadcrumb_items' ) ) {
  function bzb_get_breadcrumb_items() {
    global $post;

    // トップページではパンくずを出さない
    if ( is_front_page() ) {
      return array();
    }

    $items = array(
      array(
        'url'      => home_url( '/' ),
        'label'    => 'ホーム',
        'icon'     => 'home',
        'linkable' => true,
      ),
    );

    // 記事一覧ページ
    if ( is_home() ) {
      $posts_page_id = (int) get_option( 'page_for_posts' );
      $items[] = array(
        'url'      => $posts_page_id ? get_permalink( $posts_page_id ) : '',
        'label'    => ' 最新記事一覧',
        'icon'     => 'list-alt',
        'linkable' => false,
      );
      return $items;
    }

    // 検索結果ページ
    if ( is_search() ) {
      $items[] = array(
        'url'      => get_search_link(),
        'label'    => ' 「' . get_search_query() . '」の検索結果',
        'icon'     => 'search',
        'linkable' => false,
      );
      return $items;
    }

    // 404ページ
    if ( is_404() ) {
      $items[] = array(
        'url'      => home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ),
        'label'    => ' ページが見つかりませんでした',
        'icon'     => 'question-circle',
        'linkable' => false,
      );
      return $items;
    }

    // 日付別一覧ページ
    if ( is_date() ) {
      $item_name = '';
      $item_url  = '';

      if ( is_day() ) {
        $item_name .= get_the_time( 'Y' ) . '年 ';
        $item_name .= get_the_time( 'n' ) . '月 ';
        $item_name .= get_the_time( 'j' ) . '日';
        $item_url   = get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
      } elseif ( is_month() ) {
        $item_name .= get_the_time( 'Y' ) . '年 ';
        $item_name .= get_the_time( 'n' ) . '月 ';
        $item_url   = get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
      } elseif ( is_year() ) {
        $item_name .= get_the_time( 'Y' ) . '年 ';
        $item_url   = get_year_link( get_query_var( 'year' ) );
      }

      $items[] = array(
        'url'      => $item_url,
        'label'    => $item_name,
        'icon'     => 'clock-o',
        'linkable' => false,
      );
      return $items;
    }

    // ポストタイプを取得
    $post_type = get_post_type( $post );

    // カスタムポストアーカイブ
    if ( is_post_type_archive() ) {
      $items[] = array(
        'url'      => get_post_type_archive_link( $post_type ),
        'label'    => post_type_archive_title( '', false ),
        'icon'     => 'pencil-square-o',
        'linkable' => false,
      );
      return $items;
    }

    // カテゴリーページ
    if ( is_category() ) {
      $cat = get_queried_object();

      if ( $cat->parent != 0 ) {
        $ancs = array_reverse( get_ancestors( $cat->cat_ID, 'category' ) );

        foreach ( $ancs as $anc ) {
          $items[] = array(
            'url'      => get_category_link( $anc ),
            'label'    => get_cat_name( $anc ),
            'icon'     => 'folder',
            'linkable' => true,
          );
        }
      }

      $items[] = array(
        'url'      => get_category_link( $cat->cat_ID ),
        'label'    => $cat->cat_name,
        'icon'     => 'folder',
        'linkable' => false,
      );
      return $items;
    }

    // タグページ
    if ( is_tag() ) {
      $tag = get_queried_object();
      $items[] = array(
        'url'      => get_tag_link( $tag->term_id ),
        'label'    => single_tag_title( '', false ),
        'icon'     => 'tag',
        'linkable' => false,
      );
      return $items;
    }

    // 著者ページ
    if ( is_author() ) {
      $author_id = get_queried_object_id();
      $items[]   = array(
        'url'      => get_author_posts_url( $author_id ),
        'label'    => get_the_author_meta( 'display_name' ),
        'icon'     => 'user',
        'linkable' => false,
      );
      return $items;
    }

    // ここからは投稿・固定ページなどの記事詳細
    if ( ! is_object( $post ) ) {
      return $items;
    }

    // 添付ファイルページ
    if ( is_attachment() ) {
      if ( $post->post_parent != 0 ) {
        $items[] = array(
          'url'      => get_permalink( $post->post_parent ),
          'label'    => get_the_title( $post->post_parent ),
          'icon'     => 'file-text',
          'linkable' => true,
        );
      }

      $items[] = array(
        'url'      => get_permalink( $post ),
        'label'    => $post->post_title,
        'icon'     => 'picture-o',
        'linkable' => false,
      );
      return $items;
    }

    // 投稿ページ
    if ( is_singular( 'post' ) ) {
      $cats = get_the_category( $post->ID );

      if ( ! empty( $cats ) ) {
        $cat = $cats[0];

        if ( $cat->parent != 0 ) {
          $ancs = array_reverse( get_ancestors( $cat->cat_ID, 'category' ) );

          foreach ( $ancs as $anc ) {
            $items[] = array(
              'url'      => get_category_link( $anc ),
              'label'    => get_cat_name( $anc ),
              'icon'     => 'folder',
              'linkable' => true,
            );
          }
        }

        $items[] = array(
          'url'      => get_category_link( $cat->cat_ID ),
          'label'    => $cat->cat_name,
          'icon'     => 'folder',
          'linkable' => true,
        );
      }

      $items[] = array(
        'url'      => get_permalink( $post->ID ),
        'label'    => $post->post_title,
        'icon'     => 'file-text',
        'linkable' => false,
      );
      return $items;
    }

    // 固定ページ
    if ( is_singular( 'page' ) ) {
      if ( $post->post_parent != 0 ) {
        $ancs = array_reverse( $post->ancestors );

        foreach ( $ancs as $anc ) {
          $items[] = array(
            'url'      => get_permalink( $anc ),
            'label'    => get_the_title( $anc ),
            'icon'     => 'file',
            'linkable' => true,
          );
        }
      }

      $items[] = array(
        'url'      => get_permalink( $post ),
        'label'    => $post->post_title,
        'icon'     => 'file',
        'linkable' => false,
      );
      return $items;
    }

    // カスタムポスト記事ページ
    if ( is_singular( $post_type ) ) {
      $obj = get_post_type_object( $post_type );

      if ( $obj->has_archive == true ) {
        $items[] = array(
          'url'      => get_post_type_archive_link( $post_type ),
          'label'    => $obj->label,
          'icon'     => 'pencil-square-o',
          'linkable' => true,
        );
      }

      $items[] = array(
        'url'      => get_permalink( $post ),
        'label'    => $post->post_title,
        'icon'     => 'file',
        'linkable' => false,
      );
      return $items;
    }

    // その他のページ
    $items[] = array(
      'url'      => get_permalink( $post ),
      'label'    => $post->post_title,
      'icon'     => 'file',
      'linkable' => false,
    );

    return $items;
  }
}


/* add comment..
* ---------------------------------------- */
if( !function_exists('bzb_breadcrumb') ){

  function get_bzb_breadcrumb_listitem( $item = array() ) {
    $label = isset( $item['label'] ) ? esc_html( $item['label'] ) : '';
    $icon  = empty( $item['icon'] ) ? '' : '<i class="fa fa-' . esc_attr( $item['icon'] ) . '"></i> ';
    $url   = isset( $item['url'] ) ? $item['url'] : '';
    $linkable = ! empty( $item['linkable'] ) && $url !== '';

    if ( $label === '' ) {
      return '';
    }

    $content = $icon . $label;
    if ( $linkable ) {
      $content = '<a href="' . esc_url( $url ) . '">' . $content . '</a> / ';
    }

    return '<li>' . $content . '</li>';
  }

  function bzb_breadcrumb(){

    $items = bzb_get_breadcrumb_items();
    if ( empty( $items ) ) {
      return;
    }

    $bc  = '<ol class="breadcrumb clearfix">';

    foreach ( $items as $item ) {
      $bc .= get_bzb_breadcrumb_listitem( $item );
    }

    $bc .= '</ol>';

    echo $bc;

  }
}


/* レイアウト
* ---------------------------------------- */
function bzb_layout_main(){
  global $post;

  if( !is_object($post) ){
        return;
  }

  $cf = get_post_meta($post->ID);
  $main_layout = '';
  $post_layout = '';

  if(isset( $cf['bzb_post_layout'] )){
    if( is_array( $cf['bzb_post_layout'] ) ){
      $post_layout = reset($cf['bzb_post_layout']);
    }else{
      $post_layout = $cf['bzb_post_layout'];
    }
  }
  $post_layout = get_option('post_layout');
  if( is_single() || is_page() ){
    if( "right-content" == $post_layout ){
      $main_layout = "col-md-8  col-md-push-4";
    }elseif( "one-column" == $post_layout){
      $main_layout = "col-md-10 col-md-offset-1";
    }else{
      $main_layout = "col-md-8";
    }
  }elseif( "right-content" == $post_layout ){
    $main_layout = "col-md-8  col-md-push-4";
  }elseif( "one-column" == $post_layout ){
    $main_layout = "col-md-10 col-md-offset-1";
  }else {
    $main_layout = "col-md-8";
  }

  echo 'class="'.esc_attr($main_layout).'"';
}

function bzb_layout_side(){
  global $post;

  if( !is_object($post) ){
        return;
  }

  $cf = get_post_meta($post->ID);
  $post_layout = '';

  if(isset($cf['bzb_post_layout'])){
    if( is_array( $cf['bzb_post_layout'] ) ){
      $post_layout = reset($cf['bzb_post_layout']);
    }else{
      $post_layout = $cf['bzb_post_layout'];
    }
  }
  $bzb_option = get_option('bzb_option');
  if( is_single() || is_page() ){
    if( "right-content" == $post_layout ){
      $side_layout = "col-md-4 col-md-pull-8";
    }elseif( "one-column" == $post_layout){
      $side_layout = "display-none";
    }else{
      $side_layout = "col-md-4";
    }
  }elseif( !empty( $bzb_option['post_layout'] ) && "right-content" == $bzb_option['post_layout'] ){
    $side_layout = "col-md-4 col-md-pull-8";
  }elseif( !empty( $bzb_option['post_layout'] ) && "one-column" == $bzb_option['post_layout'] ){
    $side_layout = "display-none";
  }else{
    $side_layout = "col-md-4";
  }

  echo 'class="'.esc_attr($side_layout).'"';
}

function bzb_layout_side_lp(){
  global $post;
  $cf = get_post_meta($post->ID);
  $post_layout = "";
  if(isset($cf['bzb_post_layout'])){
    if( is_array( $cf['bzb_post_layout'] ) ){
      $post_layout = reset($cf['bzb_post_layout']);
    }else{
      $post_layout = $cf['bzb_post_layout'];
    }
  }
  if( "right-content" == $post_layout ){
    $side_layout = "col-md-4 col-md-pull-8";
  }elseif( "one-column" == $post_layout){
    $side_layout = "display-none";
  }else{
    $side_layout = "col-md-4";
  }

  echo 'class="'.esc_attr($side_layout).'"';
}



/* add comment..
* ---------------------------------------- */
function bzb_get_cta($pid = ""){
  global $post;
  $check_cta = '';
  $org_button_cvtag = '';
  $cp_id = '';
  $bzb_cta = get_post_meta($post->ID, 'bzb_cta', true);
  $select_button = $select_button_url = $select_button_cvtag = '';
  if(is_array($bzb_cta)){
    extract($bzb_cta);
  }

  if( 'none' == $check_cta || '' == $check_cta ) {
    return false;
    //nothing
  }elseif($check_cta == 'custompost'){
    $cp_id =  $cta_select;
    $bzb_cta = get_post_meta($cp_id, 'bzb_cta', true);

    extract($bzb_cta);//select_button,select_button_url

    $customposts = get_post($cp_id);

    $bzb_cta['title'] = ($customposts->post_title);

    $bzb_cta['content'] = apply_filters('the_content', stripslashes($customposts->post_content));

    $bzb_cta['button_text'] = ($select_button);
    $bzb_cta['button_url'] = esc_url($select_button_url);
    $bzb_cta['button_cvtag'] = esc_html($select_button_cvtag);

    $thumbnail_id = get_post_thumbnail_id($cp_id);
    $image = wp_get_attachment_image_src( $thumbnail_id, 'medium' );
    if ( $image ){
      $bzb_cta['img_src'] = $image[0];
      $width = $image[1];
      $height = $image[2];
      $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
      $bzb_cta['image_alt'] = $alt_text ? $alt_text : ''; // altテキストを取得

      $bzb_cta['image'] = '<img src="' . $bzb_cta['img_src'] . '" alt="' . esc_attr($bzb_cta['image_alt']) . '" width="' . $width . '" height="' . $height . '" loading="lazy" decoding="async">';
    }
  }elseif($check_cta == 'pageorg'){//オリジナルはエスケープ処理を入れている
    $cta_title = ($org_title);
    $bzb_cta['title'] = esc_html($cta_title);
    $bzb_cta['content'] = apply_filters('the_content', stripslashes($org_content));
    $bzb_cta['img_src'] = $org_image;
    $bzb_cta['image'] = '<img src="' . esc_url($org_image) . '">';
    $bzb_cta['button_text'] = ($org_button_text);
    $bzb_cta['button_url'] = esc_url($org_button_url);
    $bzb_cta['button_cvtag'] = esc_html($org_button_cvtag);
  }//if

  if(isset($bzb_cta['title']) && $bzb_cta['title'] !== '' && isset($bzb_cta['content']) && $bzb_cta['content'] !== '' ){
    bzb_make_cta_block($bzb_cta, $cp_id);
  }

}//func


/* add comment..
* ---------------------------------------- */
function bzb_make_cta_block($bzb_cta, $cp_id = 0){

  $title = '';
  $cta_content = '';
  $title = (isset($bzb_cta['title']) && $bzb_cta['title'] !== '') ? $bzb_cta['title'] : "";
  $cta_content = (isset($bzb_cta['content']) && $bzb_cta['content'] !== '') ? $bzb_cta['content'] : "";
  $button_text = (isset($bzb_cta['button_text']) && $bzb_cta['button_text'] !== '') ? $bzb_cta['button_text'] : "";
  $button_url = (isset($bzb_cta['button_url']) && $bzb_cta['button_url'] !== '') ? $bzb_cta['button_url'] : "";
  $button_cvtag = (isset($bzb_cta['button_cvtag']) && $bzb_cta['button_cvtag'] !== '') ? $bzb_cta['button_cvtag'] : "";
  $image = (isset($bzb_cta['image']) && $bzb_cta['img_src'] != '' ) ? '<div class="post-cta-img">' . $bzb_cta['image'] . '</div>' : "";

  $button = '';

 if(!empty($button_url) && !empty($button_text)){
  $button = '<br clear="both"><p class="post-cta-btn"><a class="button" href="' . $button_url . '"';
          if( !empty($button_cvtag) ):
            $button .= "onClick=\"javascript:ga('send', 'pageview', '/" . $button_cvtag . "');\"";
          endif;
  $button .= '>' . $button_text . '</a></p>';
 }

  $source_html=<<<eof
<!-- CTA BLOCK -->
<div class="post-cta post-cta-{$cp_id}">
<h2 class="cta-post-title">{$title}</h2>
<div class="post-cta-inner">
  <div class="cta-post-content clearfix">


    <div class="post-cta-cont">
      {$image}
      {$cta_content}
      {$button}
    </div>

  </div>
</div>
</div>
<!-- END OF CTA BLOCK -->
eof;

  echo $source_html;


}//func

// [bartag foo="foo-value"]
function bzb_put_cta( $atts ) {
    global $post;

    $cta_id = shortcode_atts( array(
        'id' => ''
    ), $atts );

    $bzb_cta = get_post_meta($cta_id['id'], 'bzb_cta', true);

    extract($bzb_cta);//select_button,select_button_url

    $customposts = get_post($cta_id['id']);

    $bzb_cta['title'] = ($customposts->post_title);

    $bzb_cta['content'] = apply_filters('the_content', stripslashes($customposts->post_content));

    $bzb_cta['button_text'] = ($select_button);
    $bzb_cta['button_url'] = esc_url($select_button_url);
    $bzb_cta['button_cvtag'] = esc_html($select_button_cvtag);

    $thumbnail_id = get_post_thumbnail_id($cta_id['id']);
    $image = wp_get_attachment_image_src( $thumbnail_id, 'medium' );
    $src = $image[0];
    $width = $image[1];
    $height = $image[2];

    $bzb_cta['image'] = '<img src="' . $src . '" width="' . $width . '" height="' . $height . '">';

  bzb_make_cta_block($bzb_cta, $cta_id['id']);

}
add_shortcode( 'cta', 'bzb_put_cta' );


function add_custom_post_columns_shortcode($columns) {
$columns['post_modified'] = "ショートコード";
return $columns;
}
function add_post_column_shortcode($column_name, $post_id) {
if( $column_name == 'post_modified' ) {
  echo '[cta id="' . $post_id . '"]<br>' . 'echo do_shortcode( \'[cta id="' . $post_id . '"]\' );';
}
}
add_filter( 'manage_edit-cta_columns', 'add_custom_post_columns_shortcode' );
add_action( 'manage_posts_custom_column', 'add_post_column_shortcode', 10, 2 );



/* add comment..
* ---------------------------------------- */
/* 固定ページと記事ページのprev/nextの削除 */
remove_action('wp_head','adjacent_posts_rel_link_wp_head',10);

// 一番最初の投稿にクラスを付与
add_filter('post_class', 'bzb_firstpost');
function bzb_firstpost($class) {
  global $post, $posts;
  if (  is_home() && !is_paged() && ($post == $posts[0]) ||
        is_category() && !is_paged() && ($post == $posts[0]) ||
        is_archive() && !is_paged() && ($post == $posts[0]) ||
        is_tag() && !is_paged() && ($post == $posts[0]) ) {
    $class[] = 'firstpost';
  }
  return $class;
}

// 最初の投稿の条件分岐
function is_bzb_firstpost(){
  global $wp_query;
  return ($wp_query->current_post === 0);
}


/* add comment..
* ---------------------------------------- */
function bzb_get_nav_menu_name(){

  global $wpdb;
  //このSQLはテーブル名一覧を返却するSQLです。
  $sql = "SELECT distinct(A.name) FROM (" . $wpdb->prefix . "terms A left join " . $xpdb->prefix . "term_relationships B on A.term_id = B.term_taxonomy_id) left join xeory_posts C ON B.object_id = C.ID WHERE post_type = 'nav_menu_item';";

  $results = $wpdb->get_results($sql);

  $menu_title = bzb_object2array($results);
  echo $menu_title[0]['name'];

}


/* add comment..
* ---------------------------------------- */
function bzb_object2array($data){

  if (is_object($data)) {
    $data = (array)$data;
  }

  if (is_array($data)) {
    foreach ($data as $key => $value) {
      $key1 = (string)$key;
      $key2 = preg_replace('/\W/', ':', $key1);

      if (is_object($value) or is_array($value)) {
        $data[$key2] = bzb_object2array($value);
      } else {
        $data[$key2] = (string)$value;
      }

      if ($key1 != $key2) {
        unset($data[$key1]);
      }
    }
  }

  return $data;
}


/* add comment..
* ---------------------------------------- */
function bzb_category_title(){
  global $post;

  $t_id = get_category( intval( get_query_var('cat') ) )->term_id;
  $cat_class = get_category($t_id);
  $cat_option = get_option('cat_'.$t_id);

  if(isset($cat_option['bzb_meta_title']) && $cat_option['bzb_meta_title'] !== '' ){
    $category_title = $cat_option['bzb_meta_title'];
  }else{
    $category_title = $cat_class->name;
  }
  echo esc_html($category_title);
}


/* add comment..
* ---------------------------------------- */
function bzb_category_description(){
  global $post;

  $t_id = get_category( intval( get_query_var('cat') ) )->term_id;
  $cat_class = get_category($t_id);
  $cat_option = get_option('cat_'.$t_id);

  if(is_array($cat_option)){
    $cat_option = array_merge(array('cont'=>''),$cat_option);
  }

  if ( !empty($cat_option['bzb_meta_content']) ){
    $content = apply_filters( 'the_content', stripslashes($cat_option['bzb_meta_content']), 10 );
  }

  if ( !empty($content) ){
    echo '<div class="cat-content-area">'.$content.'</div>';
  }
}


/* 抜粋
----------------------------------------------- */
function bzb_excerpt($length) {
  global $post;
  $content = mb_substr(strip_tags($post->post_excerpt),0,$length);

  if(!$content){
    $content =  $post->post_content;
    $content =  strip_shortcodes($content);
    $content =  strip_tags($content);
    $content =  str_replace("&nbsp;","",$content);
    $content =  html_entity_decode($content,ENT_QUOTES,"UTF-8");
    $content =  mb_substr($content,0,$length);
  }
  return $content;
}

/**
 * SEOプラグイン有効時は、テーマのmeta/OGP出力を停止する
 */
add_action('wp', function () {

  // 管理画面は関係ないので無視（任意）
  if (is_admin()) return;

  $seo_active = false;

  // Yoast SEO
  if (defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend')) {
    $seo_active = true;
  }

  // Rank Math
  if (defined('RANK_MATH_VERSION') || class_exists('RankMath\\FrontEnd\\Head')) {
    $seo_active = true;
  }

  // All in One SEO（AIOSEO）
  if (defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin\\AIOSEO')) {
    $seo_active = true;
  }

  // The SEO Framework
  if (defined('THE_SEO_FRAMEWORK_VERSION') || class_exists('The_SEO_Framework\\Load')) {
    $seo_active = true;
  }

  if ($seo_active) {
    remove_action('wp_head', 'bzb_header_meta', 1); // ← meta/OGP停止
  }
}, 1);
