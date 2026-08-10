<?php


/* add comment..
* ---------------------------------------- */
function bzb_show_avatar(){
  global $post;
  $a_id = $post->post_author;

  $original_avatar = get_user_meta($a_id, 'original_avatar', true);

  $avatar_image = '';

  if( isset($original_avatar) && $original_avatar !== ''){
    // 著者の表示名をaltテキストとして取得
    $author_display_name = get_the_author_meta('display_name', $a_id);
    $alt_text = !empty($author_display_name) ? $author_display_name : 'アバター'; // 表示名がない場合のデフォルト値

    // 画像のサイズを取得する
    list($width, $height) = getimagesize($original_avatar);
    $avatar_image = '<img src="'.$original_avatar .'" alt="'.$alt_text.'" width="'.$width.'" height="'.$height.'" loading="lazy" decoding="async">';
  }else{
    $avatar_image = '<img src="'.get_template_directory_uri().'/lib/images/masman.png" alt="masman" width="100" height="100" loading="lazy" decoding="async" />';
  }

  $author_meta_name = get_the_author_meta('display_name');
  $disp_author_description = get_the_author_meta('description');

  // nl2br() 関数を使用して改行を <br> タグに変換
  $disp_author_description = nl2br($disp_author_description);

  $disp_avatar =<<<eof
    <aside class="post-author">
      <div class="clearfix">
        <div class="post-author-img">
          <div class="inner">
          {$avatar_image}
          </div>
        </div>
        <div class="post-author-meta">
          <h2>{$author_meta_name}</h2>
          <p>{$disp_author_description}</p>
        </div>
      </div>
    </aside>
eof;

  echo $disp_avatar;
}
?>
