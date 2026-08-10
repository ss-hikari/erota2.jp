<?php get_header(); ?>
<div id="content">
  <div class="wrap">
    <?php bzb_breadcrumb(); ?>
    <div id="main" <?php bzb_layout_main(); ?>>
      <div class="main-inner">
        <section class="cat-content_">
          <header class="cat-header_">
            <h1 class="post-title"><?php bzb_title(); ?></h1>
          </header>
<?php if (is_category()) : ?>
          <div class="cat-content-area">
            <?php bzb_category_description(); echo "\n" ?>
          </div>
<?php endif; ?>
        </section>
<?php
$t_id = get_category( intval( get_query_var('cat') ) )->term_id;
$cat_option = get_option('cat_'.$t_id);
?>
        <div class="post-loop-wrap" id="post-loop">
<?php if (have_posts()) : ?>
<?php while (have_posts()): ?>
<?php the_post(); ?>
          <article id="post-<?php echo the_ID(); ?>" <?php post_class(); ?> itemscope="itemscope" itemtype="http://schema.org/BlogPosting">
<?php if (get_the_post_thumbnail()): ?>
            <div class="post-thumbnail">
              <a href="<?php the_permalink(); ?>" rel="nofollow"><?php the_post_thumbnail(); ?></a>
            </div>
<?php else: ?>
            <img src="/wp-content/uploads/2017/07/noimage.jpg" class="deco" />
<?php endif; ?>
            <header class="post-header">
              <h2 class="post-title" itemprop="headline">
                <a href="<?php the_permalink(); ?>"><?php esc_html(the_title()); ?></a>
              </h2>
              <ul class="post-meta list-inline">
                <li class="date updated" itemprop="datePublished" datetime="<?php the_time('c'); ?>">
                  <i class="fa fa-clock-o"></i> <?php the_time('Y-m-d'); echo "\n" ?>
                </li>
              </ul>
              <ul class="">
                <li class="cat"><i class="fa fa-folder"></i> <?php the_category(', ');?></li>
<?php $posttags = get_the_tags(); ?>
<?php if ($posttags): ?>
                <li class="tag"><i class="fa fa-tag"></i> <?php the_tags('');?></li>
<?php endif; ?>
              </ul>
            </header>
            <section class="post-conten" itemprop="text">
            </section>
          </article>
<?php endwhile; ?>
<?php else: /* has article? */ ?>
          <article id="post-404"class="cotent-none post" itemscope="itemscope" itemtype="http://schema.org/BlogPosting">
            <section class="post-content" itemprop="text">
              <?php echo get_template_part('content', 'none') . "\n" ?>
            </section>
          </article>
<?php endif; /* has article? */ ?>
<?php
if (function_exists('pagination')) {
    pagination($wp_query->max_num_pages);
}
?>
        </div><!-- /post-loop-wrap -->
      </div><!-- /main-inner -->
    </div><!-- /main -->
    <?php get_sidebar(); ?>
  </div><!-- /wrap -->
</div><!-- /content -->
<script>
document.addEventListener("DOMContentLoaded", function(){
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
  var savedMode = localStorage.getItem(STORAGE_KEY);
  if (savedMode === "grid") setView("grid");
  if (btnList) btnList.addEventListener("click", function(){ setView("list"); });
  if (btnGrid) btnGrid.addEventListener("click", function(){ setView("grid"); });
});

document.getElementById('sp-cat-dropdown').addEventListener('change', function() {
    var catId = this.value;
    if (catId && catId !== '0') {
        window.location.href = '/?cat=' + catId;
    }
});
</script>
<?php get_footer(); ?>