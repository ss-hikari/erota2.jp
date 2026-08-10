<?php get_header(); ?>
<div id="content">
<div class="wrap">
    
    <?php 
    if( !( is_home() || is_front_page() ) ){
      // パンくず
      bzb_breadcrumb();
      
    } ?>
  <div id="main01" <?php bzb_layout_main(); ?> role="main" itemprop="mainContentOfPage" itemscope="itemscope" itemtype="http://schema.org/Blog">
    <div class="main-inner">


<?php if (is_home() || is_front_page()) : ?>
    <div class="view-toggle-wrap">
        <div class="sp-cat-select">
           <div class="sp-cat-select">
              <?php wp_dropdown_categories([
                'show_option_none' => 'カテゴリを選択',
                'option_none_value' => '0',
                'name'             => 'cat',
                'hierarchical'     => true,
                'hide_empty'       => true,
                'id'               => 'sp-cat-dropdown',
              ]); ?>
          </div>
        </div>
        <button id="btn-list" class="view-btn active" title="リスト表示">&#9776; リスト</button>
        <button id="btn-grid" class="view-btn" title="グリッド表示">&#9881; グリッド</button>
    </div>
<?php endif; ?>

    <div class="post-loop-wrap" id="post-loop">
    <?php
    $post_count = 0;
    if ( have_posts() ) :
        while ( have_posts() ) : the_post();
        $post_count++;
    ?>
    <article id="post-<?php echo the_ID(); ?>" <?php post_class(); ?> itemscope="itemscope" itemtype="http://schema.org/BlogPosting">
    
    <?php if( get_the_post_thumbnail() ) { ?>
        <div class="post-thumbnail">
            <a href="<?php the_permalink(); ?>" rel="nofollow" aria-label="images"><?php the_post_thumbnail(); ?></a>
        </div>
        <?php }else{ echo '<img src="/wp-content/uploads/2017/07/noimage.jpg" class="deco" />';
    } ?>
      <header class="post-header">
 
        <h2 class="post-title" itemprop="headline"><a href="<?php the_permalink(); ?>">
<?php if(is_sticky()) : ?>
    <?php echo '<strong class="pickup">PickUp!</strong>'; ?>
<?php else : ?>
    <?php $hours = 26; //時間
    $today = date_i18n('U');
    $entry = get_the_time('U');
    $kiji = date('U',($today - $entry)) / 3600 ;
    if( $hours > $kiji ){
    echo '<strong class="saishin">New!</strong>'; }
    ?>
<?php endif; ?>
       <?php esc_html(the_title()); ?></a></h2>
        
        <?php
        // アップロード会員名を取得（日付横に表示するため先に取得）
        global $post;
        $post_content = $post->post_content;
        $maker_name = '';
        if (preg_match('/アップロード会員.*?<td[^>]*>\s*(.*?)\s*<\/td>/s', $post_content, $m)) {
            $maker_name = trim(strip_tags($m[1]));
        }
        ?>
        <ul class="post-meta list-inline">
          <li class="date updated" itemprop="datePublished" datetime="<?php the_time('c');?>"><i class="fa fa-clock-o"></i> <?php the_time('Y-m-d');?></li>
          <?php
          // 日付の右に会員名を並べて表示
          if ($maker_name) {
              $tag = get_term_by('name', $maker_name, 'post_tag');
              if ($tag) {
                  $maker_url = get_tag_link($tag->term_id);
                  echo '<li class="maker">👤 <a href="' . esc_url($maker_url) . '">' . esc_html($maker_name) . '</a></li>';
              } else {
                  echo '<li class="maker">👤 ' . esc_html($maker_name) . '</li>';
              }
          }
          ?>
        </ul>
        <ul class="">
			  <?php
  if ($maker_name) {
      $tag = get_term_by('name', $maker_name, 'post_tag');
      if ($tag) {
          $maker_url = get_tag_link($tag->term_id);
          echo '<li class="maker maker-pc">👤 <a href="' . esc_url($maker_url) . '">' . esc_html($maker_name) . '</a></li>';
      } else {
          echo '<li class="maker maker-pc">👤 ' . esc_html($maker_name) . '</li>';
      }
  }
  ?>
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
          // タグ：会員名と同じタグは除外・5つまで表示
          $posttags = get_the_tags();
          if ($posttags) {
              $filtered_tags = array_filter($posttags, function($t) use ($maker_name) {
                  return $t->name !== $maker_name;
              });
              $filtered_tags = array_slice(array_values($filtered_tags), 0, 5);
              if ($filtered_tags) {
                  $tag_links = array_map(function($t) {
                      return '<a href="' . esc_url(get_tag_link($t->term_id)) . '" rel="tag">' . esc_html($t->name) . '</a>';
                  }, $filtered_tags);
                  echo '<li class="tag"><i class="fa fa-tag"></i> ' . implode(', ', $tag_links) . '</li>';
              }
          }
          ?>
        </ul>
          <?php
          // グリッド表示用：会員名をulの外に独立して出力
          if ($maker_name) {
              $tag = get_term_by('name', $maker_name, 'post_tag');
              if ($tag) {
                  $maker_url = get_tag_link($tag->term_id);
                  echo '<div class="maker-grid">👤 <a href="' . esc_url($maker_url) . '">' . esc_html($maker_name) . '</a></div>';
              } else {
                  echo '<div class="maker-grid">👤 ' . esc_html($maker_name) . '</div>';
              }
          }
          ?>
      </header>
      <section class="post-conten" itemprop="text">
        <?php 
            if(is_home()) {
                // the_excerpt();
            } else {
                the_content();
            }
        ?>
      </section>
    </article>

<?php
    if ((is_home() || is_front_page()) && $post_count === 4) {
        // 4件目後に挿入（グリッド時もリスト時もここで表示）
        echo '<div class="monthly-ranking-break">' . gcolle_monthly_ranking_html() . '</div>';
    }
?>

    <?php
        endwhile;
    else :
    ?>
    
    <article id="post-404"class="cotent-none post" itemscope="itemscope" itemtype="http://schema.org/BlogPosting">
      <section class="post-content" itemprop="text">
        <?php echo get_template_part('content', 'none'); ?>
      </section>
    </article>
            
    <?php
        endif;
    ?>
    </div><!-- /post-loop-wrap -->
<?php if (function_exists("pagination")) {
    pagination($wp_query->max_num_pages);
} ?>
    </div><!-- /main-inner -->
  </div><!-- /main -->
  
<?php get_sidebar(); ?>
</div><!-- /wrap -->

<script>
// PC時：post-meta内のmakerを非表示
if (window.innerWidth >= 768) {
    document.querySelectorAll('li.maker:not(.maker-pc)').forEach(function(el) {
        el.style.setProperty('display', 'none', 'important');
    });
}
document.getElementById('sp-cat-dropdown').addEventListener('change', function() {
    var catId = this.value;
    if (catId && catId !== '0') {
        window.location.href = '/?cat=' + catId;
    }
});
</script>
  
</div><!-- /content -->
<?php get_footer(); ?>
