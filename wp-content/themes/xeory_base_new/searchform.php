<?php
/**
 * Template for displaying search forms
 *
 * @package xeory_base
 */
?>

<form method="get" id="searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
  <label>
    <span class="screen-reader-text">検索:</span>
    <input type="search" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" id="s" aria-label="検索フォーム" placeholder="<?php esc_attr_e( 'フリーワード検索', 'textdomain' ); ?>" />
  </label>
  <button type="submit" id="searchsubmit" aria-label="検索フォームボタン"></button>
</form> 
