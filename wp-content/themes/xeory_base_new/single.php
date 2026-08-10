<?php get_header(); ?>
<div id="content">
<div class="wrap">
  
  <div id="main" <?php bzb_layout_main(); ?> role="main" itemprop="mainContentOfPage" itemscope="itemscope" itemtype="http://schema.org/Blog">
    
    <div class="main-inner">
    <?php
      if ( have_posts() ) :
        while ( have_posts() ) : the_post();
        
        ?>
<?php edit_post_link('Edit','[',']'); ?>
        
    <?php 
    global $post;
    $cf = get_post_meta($post->ID);
    ?>
    <article id="post-<?php the_id(); ?>" <?php post_class(); ?> itemscope="itemscope" itemtype="http://schema.org/BlogPosting">
      <header class="post-header">
        <h1 class="post-title entry-title" itemprop="headline"><?php the_title(); ?></h1>
        <ul class="post-meta list-inline">
          <li class="date updated" itemprop="datePublished" datetime="<?php the_time('c');?>"><i class="fa fa-clock-o"></i> <?php the_time('Y.m.d');?></li>
        </ul>
<!--    <?php bzb_breadcrumb(); ?>  -->
      </header>
      <section class="post-content" itemprop="text">
        <?php the_content(); ?>
        <ul class="">
          <?php
          // カテゴリー：メンズ・動画・写真 は非表示
          $hide_cats = ['メンズ', '動画', '写真'];
          $cats = get_the_category();
          $show_cats = array_filter($cats, function($c) use ($hide_cats) {
              return !in_array($c->name, $hide_cats);
          });
          if ($show_cats) {
              $cat_links = array_map(function($c) {
                  return '<a href="' . esc_url(get_category_link($c->term_id)) . '">' . esc_html($c->name) . '</a>';
              }, $show_cats);
              echo '<li class="cat"><i class="fa fa-folder"></i> ' . implode(', ', $cat_links) . '</li>';
          }
          ?>
          <?php 
          $posttags = get_the_tags();
          if($posttags){ ?>
          <li class="tag"><i class="fa fa-tag"></i> <?php the_tags('');?></li>
          <?php } ?>
        </ul>
      </section>
      <footer class="post-footer">
<!-- del sns  -->
      </footer>
    </article>

    <?php
    // 前のページ・次のページナビゲーション
    $prev_post = get_previous_post();
    $next_post = get_next_post();
    if ($prev_post || $next_post) :
    ?>
    <nav class="post-nav">
      <div class="post-nav-inner">
        <?php if ($prev_post) : ?>
        <div class="post-nav-prev">
          <a href="<?php echo get_permalink($prev_post); ?>">
            <span class="post-nav-label">&#9664; 前の記事</span>
            <div class="post-nav-content">
              <?php $prev_thumb = get_the_post_thumbnail($prev_post, 'thumbnail'); ?>
              <?php if ($prev_thumb) : ?>
              <div class="post-nav-img"><?php echo $prev_thumb; ?></div>
              <?php endif; ?>
              <span class="post-nav-title"><?php echo esc_html(mb_strimwidth(get_the_title($prev_post), 0, 40, '…')); ?></span>
            </div>
          </a>
        </div>
        <?php endif; ?>
        <?php if ($next_post) : ?>
        <div class="post-nav-next">
          <a href="<?php echo get_permalink($next_post); ?>">
            <span class="post-nav-label">次の記事 &#9654;</span>
            <div class="post-nav-content post-nav-content-right">
              <?php $next_thumb = get_the_post_thumbnail($next_post, 'thumbnail'); ?>
              <?php if ($next_thumb) : ?>
              <div class="post-nav-img"><?php echo $next_thumb; ?></div>
              <?php endif; ?>
              <span class="post-nav-title"><?php echo esc_html(mb_strimwidth(get_the_title($next_post), 0, 40, '…')); ?></span>
            </div>
          </a>
        </div>
        <?php endif; ?>
      </div>
    </nav>
    <style>
    .post-nav{margin:20px 0;padding:12px 0;border-top:1px solid #eee;border-bottom:1px solid #eee;}
    .post-nav-inner{display:flex;justify-content:space-between;gap:16px;}
    .post-nav-prev,.post-nav-next{flex:1;}
    .post-nav-next{text-align:right;}
    .post-nav-prev a,.post-nav-next a{text-decoration:none;color:#333;display:block;}
    .post-nav-label{display:block;font-size:17px;color:#999;margin-bottom:6px;font-weight:bold;}
    .post-nav-content{display:flex;align-items:center;gap:10px;}
    .post-nav-content-right{justify-content:flex-end;}
    .post-nav-img img{width:80px;height:60px;object-fit:cover;border-radius:4px;flex-shrink:0;}
    .post-nav-title{display:block;font-size:15px;line-height:1.5;}
    .post-nav-prev a:hover,.post-nav-next a:hover{color:#e53935;}
    @media(max-width:600px){
      .post-nav-img img{width:60px;height:45px;}
      .post-nav-title{font-size:13px;}
    }
    </style>
    <?php endif; ?>


    <?php
        endwhile;
      else :
    ?>
    
    <p>投稿が見つかりません。</p>
        
    <?php
      endif;
    ?>
    </div><!-- /main-inner -->
  </div><!-- /main -->
  
<?php get_sidebar(); ?>
</div><!-- /wrap -->
</div><!-- /content -->
<script>
document.addEventListener("DOMContentLoaded", function(){
  // 赤いボタン（background:#e53935）を含むdivを探す
  var allDivs = document.querySelectorAll('div[style*="text-align:center"]');
  var targetDiv = null;
  allDivs.forEach(function(div) {
    var a = div.querySelector('a[style*="e53935"]');
    if (a) targetDiv = div;
  });
  if (!targetDiv) return;

  var nav = document.querySelector('.post-nav');
  if (!nav) return;
  var navClone = nav.cloneNode(true);
  targetDiv.parentNode.insertBefore(navClone, targetDiv.nextSibling);
});
</script>
<?php get_footer(); ?>
