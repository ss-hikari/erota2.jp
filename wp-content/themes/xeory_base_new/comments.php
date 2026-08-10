<?php
if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area">

	<?php if ( have_comments() ) : ?>

	<h2 class="comments-title">
		<?php
			//printf( _n( 'One thought on &ldquo;%2$s&rdquo;', '%1$s thoughts on &ldquo;%2$s&rdquo;', get_comments_number(), 'xeory_base' ),
			//	number_format_i18n( get_comments_number() ), get_the_title() );
		?>
		<i class="fa fa-comments-o"></i> コメント
	</h2>

	<?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : ?>
	<nav id="comment-nav-above" class="navigation comment-navigation" role="navigation">
		<h1 class="screen-reader-text"><?php _e( 'Comment navigation', 'xeory_base' ); ?></h1>
		<div class="nav-previous"><?php previous_comments_link( __( '&larr; Older Comments', 'xeory_base' ) ); ?></div>
		<div class="nav-next"><?php next_comments_link( __( 'Newer Comments &rarr;', 'xeory_base' ) ); ?></div>
	</nav><!-- #comment-nav-above -->
	<?php endif; // Check for comment navigation. ?>

	<ol class="comment-list">
		<?php
			wp_list_comments( array(
				'style'      => 'ol',
				'short_ping' => true,
				'avatar_size'=> 48,
				'type' => 'comment'
			) );
		?>
	</ol><!-- .comment-list -->

	<?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : ?>
	<nav id="comment-nav-below" class="navigation comment-navigation" role="navigation">
		<h1 class="screen-reader-text"><?php _e( 'Comment navigation', 'xeory_base' ); ?></h1>
		<div class="nav-previous"><?php previous_comments_link( __( '&larr; Older Comments', 'xeory_base' ) ); ?></div>
		<div class="nav-next"><?php next_comments_link( __( 'Newer Comments &rarr;', 'xeory_base' ) ); ?></div>
	</nav><!-- #comment-nav-below -->
	<?php endif; // Check for comment navigation. ?>

	<?php if ( ! comments_open() ) : ?>
	<p class="no-comments"><?php _e( 'Comments are closed.', 'xeory_base' ); ?></p>
	<?php endif; ?>

	<?php endif; // have_comments() ?>

	<?php 
	$com_args = array( 
	'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">',
	'title_reply_after'  => '</h2>',
     'comment_notes_before' => '',/* ここで「メールアドレスが公開されることはありません」を削除 */
     'fields' => array(
          'author' => '<p class="comment-form-author">' . '<label for="author">' . __( 'Name', 'xeory_base' ) . '</label> ' . ( $req ? '<span class="required">*</span>' : '' ) .
                 '<br /><input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30" aria-label="名前" /></p>',/* ここは投稿者名のフォーム */
          'email'  => '<p class="comment-form-email"><label for="email">' . __( 'メールアドレス（公開はされません。）', 'xeory_base' ) . '</label> ' . ( $req ? '<span class="required">*</span>' : '' ) .
                 '<br /><input id="email" name="email" type="text" value="' . esc_attr(  $commenter['comment_author_email'] ) . '" size="30" aria-label="メールアドレス" /></p>',/* ここはメールアドレスのフォーム。文言を変えてます。 */
          'url'    => '',),/* ここはURLのフォーム。削除している。 */
	'comment_field'        =>  '<p class="comment-form-comment"><label for="comment">コメント <span class="required">※</span></label> <textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required="required" aria-label="コメント"></textarea></p>',
);
	comment_form( $com_args ); ?>

</div><!-- #comments -->
