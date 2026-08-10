<?php

function load_admin_things() {
    wp_enqueue_script('media-upload');
    wp_enqueue_script('thickbox');
    wp_enqueue_style('thickbox');
}

add_action( 'admin_enqueue_scripts', 'load_admin_things' );

add_action('admin_menu', 'initial_setting_menu');

function initial_setting_menu() {
  add_menu_page('初期設定', '初期設定', 'manage_options', 'initial_setting_menu', 'banner_options_page', '', 1);
  add_action( 'admin_init', 'register_xeory_setting','admin-head');
}


function register_xeory_setting() {
  register_setting( 'xeory-initialize-group', 'blogname' );
  register_setting( 'xeory-initialize-group', 'blogdescription' );
  register_setting( 'xeory-initialize-group', 'meta_keywords' );
  register_setting( 'xeory-initialize-group', 'banner_url' );
  register_setting( 'xeory-initialize-group', 'banner_image' );
  register_setting( 'xeory-initialize-group', 'blog_public' );
  register_setting( 'xeory-initialize-group', 'permalink_structure' );

  //配色
  register_setting( 'xeory-initialize-group', 'color_scheme');

  //トップページのロゴ設定
  register_setting( 'xeory-initialize-group', 'toppage_logo_type' );
  register_setting( 'xeory-initialize-group', 'logo_text' );
  register_setting( 'xeory-initialize-group', 'logo_image' );

  //トップページのメタタグの設定
  //コアから取得

  //Googleツールの設定
  register_setting( 'xeory-initialize-group', 'analytics_tracking_code' );
  register_setting( 'xeory-initialize-group', 'webmaster_tool' );
  register_setting( 'xeory-initialize-group', 'tag_manager_head' );
  register_setting( 'xeory-initialize-group', 'tag_manager_body' );

  //Facebookとの連携
  register_setting( 'xeory-initialize-group', 'facebook_user_id' );
  register_setting( 'xeory-initialize-group', 'facebook_app_id' );
  register_setting( 'xeory-initialize-group', 'facebook_page_url' );

  //その他の設定
  register_setting( 'xeory-initialize-group', 'def_image' );
  register_setting( 'xeory-initialize-group', 'post_layout' );
  register_setting( 'xeory-initialize-group', 'show_social_buttons' );
  register_setting( 'xeory-initialize-group', 'show_like_button' );
  register_setting( 'xeory-initialize-group', 'show_tweet_button' );
  register_setting( 'xeory-initialize-group', 'show_hatena_button' );
  register_setting( 'xeory-initialize-group', 'show_pocket_button' );
  register_setting( 'xeory-initialize-group', 'show_line_button' );

  register_setting( 'xeory-initialize-group', 'twitter_id' );

  //postdate
  register_setting( 'xeory-initialize-group', 'post_date' );

  //thumbnail size
  register_setting( 'xeory-initialize-group', 'thumbnail_size' );

  //.htaccessを更新させる必要がある
  flush_rewrite_rules( true );
}

function banner_options_page() {
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


  <div class="wrap">
    <h2>初期設定</h2>

    <form method="post" action="options.php" enctype="multipart/form-data" encoding="multipart/form-data">
      <?php

        settings_fields( 'xeory-initialize-group' );
        do_settings_sections( 'xeory-initialize-group' );
      ?>

<div class="metabox-holder">
<div id="toppage_logo_setting" class="postbox " >
<h3 class='hndle'><span>トップページのロゴ設定</span></h3>
  <div class="inside">
    <div class="main">
      <p class="setting_description">ここではサイトトップページの左上に表示するロゴの設定をします。</p>
      <h4>ロゴタイプの選択</h4>
      <?php
        $toppage_logo_type = trim(get_option('toppage_logo_type'));
        if(isset($toppage_logo_type) && $toppage_logo_type !== ''){
          $toppage_logo_type = trim(get_option('toppage_logo_type'));
        }else{
          $toppage_logo_type = 'logo_text';
        }
      ?>
      <label><input type="radio" name="toppage_logo_type" value="logo_text" <?php checked($toppage_logo_type, 'logo_text'); ?>
      checked ><strong>テキストロゴ</strong></label>
      <p class="setting_description"><small>テキストのロゴを表示します。ロゴに表示したいテキストを下に入力してください。</small></p>
      <p><input type="text" id="logo_text" name="logo_text" class="regular-text" value="<?php echo get_option('logo_text');?>" /></p>


      <label><input type="radio" name="toppage_logo_type" value="logo_image"<?php checked($toppage_logo_type, 'logo_image'); ?> ><strong>画像ロゴ</strong></label>
      <p class="setting_description"><small>ロゴを画像にします。下の「画像をアップロード」ボタンを押して任意の画像を選択してください。このテンプレートでは、300px x 60pxの画像が最も適しています。</small></p>
      <p><input type="text" id="logo_image" name="logo_image" class="regular-text" value="<?php echo get_option('logo_image');?>" /><a class="media-upload" href="JavaScript:void(0);" rel="logo_image"><input class="cmb_upload_button button" type="button" value="画像をアップロードする" /></a>
      </p>

    </div>
  </div>
</div>
</div>

<div class="metabox-holder">
<div id="toppage_meta_setting" class="postbox " >
<h3 class='hndle'><span>トップページのメタタグの設定</span></h3>
  <div class="inside">
    <div class="main">
      <p class="setting_description">ここではトップページのタイトルとメタタグの設定を行います。</p>

      <h4>トップページタイトル</h4>
      <p><input type="text" id="blogname" class="regular-text" name="blogname" value="<?php echo get_option('blogname'); ?>"></p>
      <p class="setting_description"><small>トップページのタイトルを入力して下さい。ここに入力した内容が検索エンジンにも表示されるようになります。<br>効果的なタイトルのつけ方を知りたい方は、『<a href="https://lucy.ne.jp/bazubu/what-is-best-for-wp-title-22931.html" target="_blank">WordPressのタイトルの付け方</a>』をご覧ください。</small></p>

      <h4>トップページの説明（メタディスクリプション）</h4>
      <textarea id="blogdescription" class="regular-text" name="blogdescription" rows="5" cols="60"><?php echo get_option('blogdescription'); ?></textarea>
      <p class="setting_description"><small>トップページの説明文を全角８０文字以内で入力してください。ここに入力した内容が検索エンジンのディスクリプション欄に表示されるようになります。具体的には、『<a href="" target="_blank">メタディスクリプションとは</a>をご覧ください。』</small></p>

      <h4>メタキーワード</h4>
      <input type="text" id="meta_keywords" class="regular-text" name="meta_keywords" value="<?php echo get_option('meta_keywords'); ?>">
      <p class="setting_description"><small>トップページで対策したいキーワードを入力して下さい。メタキーワードは現在SEOには影響力はありませんが、キーワードに対する理解を深めるためにも、メタキーワードは常に意識しておきましょう。</small></p>

    </div>
  </div>
</div>
</div>

<div class="metabox-holder">
<div id="google_tools" class="postbox" >
<h3 class='hndle'><span>Googleツールの設定</span></h3>
  <div class="inside">
    <div class="main">
      <p class="setting_description">Googleアナリティクス・Googleサーチコンソール・Googleタグマネージャーの設定を行います。<br>サイトの効果計測やメンテナンスに必要なので必ず設定しましょう。設定の前に、それぞれのアカウントを取得しておきましょう。</p>

      <h4>Googleアナリティクスの設定</h4>
      <textarea name="analytics_tracking_code" rows="3" cols="50" id="analytics_tracking_code" class="cmb_textarea_code" placeholder="<script>...</script>"><?php echo get_option('analytics_tracking_code'); ?></textarea>
      <p class="setting_description"><small>Googleアナリティクスのコードを入力して下さい。<br>※スクリプトコード以外の内容は、そのまま表示されますのでご注意ください</small></p>

      <h4>Googleサーチコンソールの設定</h4>
      <textarea name="webmaster_tool" rows="3" cols="50" id="webmaster_tool" class="cmb_textarea_code" placeholder="<meta name=...>"><?php echo get_option('webmaster_tool'); ?></textarea>
      <p class="setting_description"><small>Googleサーチコンソールのコードを入力してください。<br>※スクリプト・メタコード以外の内容は、そのまま表示されますのでご注意ください</small></p>

      <h4>Googleタグマネージャーの設定</h4>
      <textarea name="tag_manager_head" rows="5" cols="50" id="tag_manager_head" class="cmb_textarea_code" placeholder="<head>内に出力するコードを入力してください。"><?php echo get_option('tag_manager_head'); ?></textarea>
      <br>
      <textarea name="tag_manager_body" rows="5" cols="50" id="tag_manager_body" class="cmb_textarea_code" placeholder="<body>内に出力するコードを入力してください。"><?php echo get_option('tag_manager_body'); ?></textarea>
      <p class="setting_description"><small>Googleタグマネージャーのコードを入力してください。</small></p>
    </div>
  </div>
</div>
</div>

<div class="metabox-holder">
<div id="facebook_connection" class="postbox " >
<h3 class='hndle'><span>Facebookとの連携</span></h3>
  <div class="inside">
    <div class="main">
      <p class="setting_description">Facebookとの連携を行います。これらを入力していなければ、LikeBOXの表示ができなかったり、シェアされた際の拡散効率が落ちてしまいますので、しっかりと設定しておきましょう。<br>
      （※ 詳しくは<a href="https://xeory.jp/xeory-1st-settings" target="_blank">Xeoryインストール後の初期設定</a>の「３．ソーシャルメディアのリンク設定」と「４．OGPの設定」をご確認ください）</p>
      <h4>FacebookユーザーIDの入力</h4>
      <input type="text" id="facebook_user_id" class="regular-text" name="facebook_user_id" value="<?php echo get_option('facebook_user_id'); ?>">
      <p class="setting_description"><small>FacebookのユーザーIDを入力してください。</small></p>

      <h4>FacebookアプリケーションIDの入力</h4>
      <input type="text" id="facebook_app_id" class="regular-text" name="facebook_app_id" value="<?php echo get_option('facebook_app_id'); ?>">
      <p class="setting_description"><small>FacebookのアプリケーションIDを入力して下さい。</small></p>

      <h4>Facebookページurl</h4>
      <input type="text" id="facebook_page_url" class="regular-text" name="facebook_page_url" value="<?php echo get_option('facebook_page_url'); ?>">

      <h4>デフォルト画像の設定</h4>
      <input type="text" id="def_image" name="def_image" class="regular-text" value="<?php echo get_option('def_image');?>" /><a class="media-upload" href="JavaScript:void(0);" rel="def_image"><input class="cmb_upload_button button" type="button" value="画像をアップロードする" /></a>
      <p class="setting_description"><small>サイトがシェアされた時に表示させたい画像を選択してアップロードボタンを押してください。サイトのトップページや、その他アイキャッチ画像が設定されていないページがシェアされた時には、ここのアップロードした画像が、Facebook上で表示されるようになります。画像のサイズは、1200 px x 630 pxが最も綺麗に表示されます。</small></p>

    </div>
  </div>
</div>
</div>

<div class="metabox-holder">
<div id="twitter_connection" class="postbox " >
<h3 class='hndle'><span>Xとの連携</span></h3>
  <div class="inside">
    <div class="main">
    <h4>X ID</h4>
      <input type="text" id="twitter_id" class="regular-text" name="twitter_id" value="<?php echo get_option('twitter_id'); ?>">
        <p class="setting_description"><small>ヘッダー部と記事下のソーシャルボタンにXのボタンが追加されます。<br>ご自身のXページにアクセスし、アドレスバーに表示されている下記のアンダーライン部分のみご入力ください。<br>https://x.com/<u>xxxxxxx</u><br>
      また、XCardにも利用されます。<br>
      XCardについて詳しく知りたい方は<a href="https://developer.x.com/en/docs/twitter-for-websites/cards/overview/abouts-cards" target="_blank">公式ドキュメント</a>をご覧ください。
      </small></p>

    </div>
  </div>
</div>
</div>

<div class="metabox-holder">
<div id="others" class="postbox " >
<h3 class='hndle'><span>その他の設定</span></h3>
  <div class="inside">
    <div class="main">

     <?php
        $colors = array(
          0 => 'default',
          1 => 'color01',
          2 => 'color02',
          3 => 'color03',
          4 => 'color04',
        );

        $color_scheme = trim(get_option('color_scheme'));
        if(isset($color_scheme) && $color_scheme !== ''){
          $color_scheme = trim(get_option('color_scheme'));
        }else{
          $color_scheme = 'default';
        }

      $active_template_name = basename(get_stylesheet_directory());


    ?>
    <h4>カラー設定</h4>
    <p class="setting_description"><small>ベースとなる配色を選択して下さい。</small></p>
    <ul class="color-radio cmb_id_bzb_color_scheme">
    <li id="color-1" style="display:inline-block;padding-right:18px;">
    <input type="radio" name="color_scheme" id="color_scheme1" value="default" <?php checked($color_scheme, 'default');?> />
    <label for="color_scheme1">デフォルト</label>
    </li>
    <li id="color-2" style="display:inline-block;padding-right:18px;">
    <input type="radio" name="color_scheme" id="color_scheme2" value="color01" <?php checked($color_scheme, $colors[1]);?> />
    <label for="color_scheme2">緑</label>
    </li>
    <li id="color-3" style="display:inline-block;padding-right:18px;">
    <input type="radio" name="color_scheme" id="color_scheme3" value="color02" <?php checked($color_scheme, $colors[2]);?> />
    <label for="color_scheme3">赤</label>
    </li>
    <li id="color-4" style="display:inline-block;padding-right:18px;">
    <input type="radio" name="color_scheme" id="color_scheme4" value="color03" <?php checked($color_scheme, $colors[3]);?> />
    <label for="color_scheme4">オレンジ</label>
    </li>
    <li id="color-5" style="display:inline-block;padding-right:18px;">
    <input type="radio" name="color_scheme" id="color_scheme5" value="color04" <?php echo ($color_scheme == $colors[4]) ? 'checked' : '';?> />
    <label for="color_scheme5">ピンク</label>
    </li>

    </ul>


    <?php

        $show_social_buttons = trim(get_option('show_social_buttons'));
        if(isset($show_social_buttons) && $show_social_buttons !== ''){
          $show_social_buttons = trim(get_option('show_social_buttons'));
        }else{
          $show_social_buttons = '';
        }
      ?>

    <h4>ソーシャルボタン全体表示設定</h4>
    <p>個別表示設定よりも優先されます。</p>
    <label for="show_social_buttons"><input name="show_social_buttons" type="checkbox" id="show_social_buttons" value="none" <?php checked(get_option('show_social_buttons'), 'none');?> />使用しない場合はチェックを入れてください。</label>

    <h4>ソーシャルボタン個別表示設定</h4>
    <p>使用するものにチェックを入れてください</p>
    <label for="show_like_button"><input name="show_like_button" type="checkbox" id="show_like_button" value="1" <?php checked(get_option('show_like_button'), '1');?> />Like</label><br>
    <label for="show_tweet_button"><input name="show_tweet_button" type="checkbox" id="show_tweet_button" value="1" <?php checked(get_option('show_tweet_button'), '1');?> />tweet</label><br>
    <label for="show_hatena_button"><input name="show_hatena_button" type="checkbox" id="show_hatena_button" value="1" <?php checked(get_option('show_hatena_button'), '1');?> />hatena</label><br>
    <label for="show_pocket_button"><input name="show_pocket_button" type="checkbox" id="show_pocket_button" value="1" <?php checked(get_option('show_pocket_button'), '1');?> />pocket</label><br>
    <label for="show_line_button"><input name="show_line_button" type="checkbox" id="show_line_button" value="1" <?php checked(get_option('show_line_button'), '1');?> />line</label><br>




      <?php
        $layouts = array(
          0 => 'left-content',
          1 => 'right-content',
          2 => 'one-column',
        );

        $post_layout = ( get_option('post_layout') != '' ) ? esc_html( get_option('post_layout') ) : $layouts[0];
      ?>

    <h4>レイアウト設定</h4>
    <p class="setting_description"><small>基本のレイアウトを選択して下さい。</small></p>


    <ul class="layout-radio cmb_id_bzb_post_layout">
    <li id="layout-1" style="display:inline-block;padding-right:18px;">
    <input type="radio" name="post_layout" id="post_layout1" value="left-content" <?php echo ($post_layout == $layouts[0]) ? 'checked' : '';?> />
    <label for="post_layout1">left-content</label>
    </li>
    <li id="layout-2" style="display:inline-block;padding-right:18px;">
    <input type="radio" name="post_layout" id="post_layout2" value="right-content" <?php echo ($post_layout == $layouts[1]) ? 'checked' : '';?> />
    <label for="post_layout2">right-content</label>
    </li>
    <li id="layout-3" style="display:inline-block;padding-right:18px;">
    <input type="radio" name="post_layout" id="post_layout3" value="one-column" <?php echo ($post_layout == $layouts[2]) ? 'checked' : '';?> />
    <label for="post_layout3">one-column</label>
    </li>
    </ul>

    <h4>検索エンジンでの表示</h4>
    <label for="blog_public"><input name="blog_public" type="checkbox" id="blog_public" value="0" <?php checked(get_option('blog_public'), 0);?> />
      検索エンジンがサイトをインデックスしないようにする</label>


<script type="text/javascript">
//<![CDATA[
jQuery(document).ready(function() {
  jQuery('.permalink-structure input:radio').change(function() {
    if ( 'custom' == this.value )
      return;
    jQuery('#permalink_structure').val( this.value );
  });
  jQuery('#permalink_structure').focus(function() {
    jQuery("#custom_selection").attr('checked', 'checked');
  });
});
//]]>
</script>

<?php

$permalink_structure = get_option('permalink_structure');
$prefix = $blog_prefix = '';
$structures = array(
  0 => '',
  1 => $prefix . '/%year%/%monthnum%/%day%/%postname%/',
  2 => $prefix . '/%year%/%monthnum%/%postname%/',
  3 => $prefix . '/' . _x( 'archives', 'sample permalink base', 'xeory_base' ) . '/%post_id%',
  4 => $prefix . '/%postname%/',
);

?>

      <h4><?php _e('Common Settings', 'xeory_base'); ?></h4>
      <table class="form-table permalink-structure">
        <tr>
          <th><label><input name="selection" type="radio" value="" <?php checked('', $permalink_structure); ?> /> <?php _e('Default', 'xeory_base'); ?></label></th>
          <td><code><?php echo esc_url( home_url( '/?p=123' ) ); ?></code></td>
        </tr>
        <tr>
          <th><label><input name="selection" type="radio" value="<?php echo esc_attr($structures[1]); ?>" <?php checked($structures[1], $permalink_structure); ?> /> <?php _e('Day and name', 'xeory_base'); ?></label></th>
           <td><code><?php echo esc_url( home_url( $blog_prefix . $prefix . '/' . date('Y') . '/' . date('m') . '/' . date('d') . '/' . _x( 'sample-post', 'sample permalink structure', 'xeory_base' ) . '/' ) ); ?></code></td>
        </tr>
        <tr>
          <th><label><input name="selection" type="radio" value="<?php echo esc_attr($structures[2]); ?>" <?php checked($structures[2], $permalink_structure); ?> /> <?php _e('Month and name', 'xeory_base'); ?></label></th>
          <td><code><?php echo esc_url( home_url( $blog_prefix . $prefix . '/' . date('Y') . '/' . date('m') . '/' . _x( 'sample-post', 'sample permalink structure', 'xeory_base' ) . '/' ) ); ?></code></td>
        </tr>
        <tr>
          <th><label><input name="selection" type="radio" value="<?php echo esc_attr($structures[3]); ?>" <?php checked($structures[3], $permalink_structure); ?> /> <?php _e('Numeric', 'xeory_base'); ?></label></th>
          <td><code><?php echo esc_url( home_url( $blog_prefix . $prefix . '/' . _x( 'archives', 'sample permalink base', 'xeory_base' ) . '/123' ) ); ?></code></td>
        </tr>
        <tr>
          <th><label><input name="selection" type="radio" value="<?php echo esc_attr($structures[4]); ?>" <?php checked($structures[4], $permalink_structure); ?> /> <?php _e('Post name', 'xeory_base'); ?></label></th>
          <td><code><?php echo esc_url( home_url( $blog_prefix . $prefix . '/' . _x( 'sample-post', 'sample permalink structure', 'xeory_base' ) . '/' ) ); ?></code></td>
        </tr>
        <tr>
          <th>
            <label><input name="selection" id="custom_selection" type="radio" value="custom" <?php checked( !in_array($permalink_structure, $structures) ); ?> />
            <?php _e('Custom Structure', 'xeory_base'); ?>
            </label>
          </th>
          <td>
            <code><?php echo esc_url( home_url() ) . $blog_prefix; ?></code>
            <input name="permalink_structure" id="permalink_structure" type="text" value="<?php echo esc_attr($permalink_structure); ?>" class="regular-text code" />
          </td>
        </tr>
      </table>

<?php

$optionDates = array(
  1 => 'option01',
  2 => 'option02',
  3 => 'option03'
);

// デフォルト値を設定（get_option が空の場合）
$post_date_option = trim(get_option('post_date'));
if (empty($post_date_option)) {
  $post_date_option = 'option01'; // デフォルト値
}

?>

<h4><?php _e('日付の表示設定', 'xeory_base'); ?></h4>
<table class="form-table post-date">
  <tr>
    <th>
      <label>
        <input id="post_date_1" name="post_date" type="radio" value="option01" <?php echo checked($post_date_option, $optionDates[1], false); ?> />
        <?php _e('公開日のみ表示', 'xeory_base'); ?>
      </label>
    </th>
  </tr>
  <tr>
    <th>
      <label>
        <input id="post_date_2" name="post_date" type="radio" value="option02" <?php echo checked($post_date_option, $optionDates[2], false); ?> />
        <?php _e('更新日のみ表示', 'xeory_base'); ?>
      </label>
    </th>
  </tr>
  <tr>
    <th>
      <label>
        <input id="post_date_3" name="post_date" type="radio" value="option03" <?php echo checked($post_date_option, $optionDates[3], false); ?> />
        <?php _e('公開日・更新日表示', 'xeory_base'); ?>
      </label>
    </th>
  </tr>
</table>

<?php

$optionSize = array(
  0 => 'default',
  1 => 'option01'
);


$thumbnail_size_option = trim(get_option('thumbnail_size'));
if (isset($thumbnail_size_option) && $thumbnail_size_option !== '') {
  $thumbnail_size_option = trim(get_option('thumbnail_size'));
} else {
  $thumbnail_size_option = 'default'; // デフォルト
}

?>

<h4><?php _e('画像のサイズ', 'xeory_base'); ?></h4>
<table class="form-table">
  <tr>
    <th>
      <label>
        <input id="thumbnail-size-default" name="thumbnail_size" type="radio" value="default" <?php echo checked($thumbnail_size_option, $optionSize[0], false); ?> />
        <?php _e('デフォルト', 'xeory_base'); ?>
      </label>
    </th>
  </tr>
  <tr>
    <th>
      <label>
        <input id="thumbnail-size-1" name="thumbnail_size" type="radio" value="option01" <?php echo checked($thumbnail_size_option, $optionSize[1], false); ?> />
        <?php _e('サムネイルを大きく表示', 'xeory_base'); ?>
      </label>
    </th>
  </tr>
</table>


    </div>
  </div>
</div>
</div>

      <?php submit_button(); ?>
    </form>
  </div>

<?php
}


// 投稿画面の項目を非表示にする
function remove_default_post_screen_metaboxes() {
 if (!current_user_can('level_10')) { // level10以下のユーザーの場合メニューをremoveする
 remove_meta_box( 'postcustom','post','normal' ); // カスタムフィールド
 remove_meta_box( 'postexcerpt','post','normal' ); // 抜粋
 //remove_meta_box( 'commentstatusdiv','post','normal' ); // ディスカッション
 remove_meta_box( 'commentsdiv','post','normal' ); // コメント
 remove_meta_box( 'trackbacksdiv','post','normal' ); // トラックバック
 remove_meta_box( 'authordiv','post','normal' ); // 作成者
 //remove_meta_box( 'slugdiv','post','normal' ); // スラッグ
 remove_meta_box( 'revisionsdiv','post','normal' ); // リビジョン
 }
 }

add_action('admin_menu','remove_default_post_screen_metaboxes');
 
 // 初期表示で投稿画面の「ページ固有のJavascript/CSS」項目を非表示にする
add_filter( 'default_hidden_meta_boxes', 'bzb_default_hidden_meta_boxes', 10, 2 );
function bzb_default_hidden_meta_boxes( $hidden, $screen ) {
  if ( ( $found = array_search( 'bzb_post_asset', $hidden ) ) == false )
    array_push($hidden,'bzb_post_asset');
  return $hidden;
}

/**
 * 初期画面設定でデフォルト値を設定する
 *
 * get_optionで指定したオプション自体が存在しない場合はfalseが返る
 * update_optionの「1」はtrueで有効にするという意味
 */
function bzb_set_default_value() {

  // 「ソーシャルボタン個別表示設定」： デフォルトでlike,tweet, googleplus,hatenaのボタンを有効にする
  if (false === get_option('show_like_button')) {
    update_option('show_like_button', 1);
  }
  if (false === get_option('show_tweet_button')) {
    update_option('show_tweet_button', 1);
  }
  if (false === get_option('show_hatena_button')) {
    update_option('show_hatena_button', 1);
  }

}
add_action( 'load-toplevel_page_initial_setting_menu', 'bzb_set_default_value' );

/*お知らせ表示*/
add_action('wp_dashboard_setup', 'bzb_my_custom_dashboard_widgets');

function bzb_my_custom_dashboard_widgets() {
  global $wp_meta_boxes;
  wp_add_dashboard_widget('custom_help_widget', 'Xeoryからのお知らせ', 'bzb_dashboard_text');
}

function bzb_dashboard_text() {
  echo '<iframe src="https://xeory.jp/if-news/" width="100%" height="300"></iframe><img src="https://xeory.jp/images/xeory.gif" alt="">';
}

/* admin
* ---------------------------------------- */
add_action('admin_enqueue_scripts', 'bzb_admin_asset');
function bzb_admin_asset(){

  // CSSファイルを登録
  wp_register_style( 'bzb_admin_css', get_template_directory_uri().'/style_admin.css' );
  // CSSファイルを表示
  wp_enqueue_style( 'bzb_admin_css' );

  // JSファイルを登録
  wp_register_script( 'bzb_admin_js', get_template_directory_uri().'/lib/js/bzb-admin.js', array('jquery') );
  //JSファイルを表示
  wp_enqueue_script('bzb_admin_js');
  wp_enqueue_script('jquery-ui-core');
  wp_enqueue_script('jquery-ui-tabs');
}
?>
