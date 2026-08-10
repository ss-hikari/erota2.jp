<footer id="footer">
<?php if( has_nav_menu( 'footer_nav' ) ){ ?>
  <div class="footer-01">
    <div class="wrap">
    
    <!--
        <?php
        wp_nav_menu(
          array(
            'theme_location'  => 'footer_nav',
            'menu_class'      => '',
            'menu_id'         => 'fnav',
            'container'       => 'nav',
            'items_wrap'      => '<ul id="footer-nav" class="%2$s">%3$s</ul>'
          )
        );?>-->
        
        
<div id="footer-widget" class="clearfloat">
    <div id="footerleft" class="clearfloat">
        <?php if(function_exists('dynamic_sidebar') ) dynamic_sidebar(footerleft);?>
    </div>
    <div id="footercenter" class="clearfloat">
        <?php if(function_exists('dynamic_sidebar') ) dynamic_sidebar(footercenter);?>
    </div>
    <div id="footerright" class="clearfloat">
        <?php if(function_exists('dynamic_sidebar') ) dynamic_sidebar(footerright);?>
    </div>
</div>




        
    </div><!-- /wrap -->
  </div><!-- /footer-01 -->
<?php } //if footer_nav ?>
  <div class="footer-02">

<nav class="footer-menu">
  <ul>
    <li><a href="https://erota2.jp/">トップページ</a></li>
    <li><a href="https://erota2.jp/当サイトについて">エロ達について</a></li>
    <li><a href="https://erota2.jp/%E3%81%8A%E5%95%8F%E3%81%84%E5%90%88%E3%82%8F%E3%81%9B">お問い合わせ</a></li>
  </ul>
</nav>

<style>
.footer-menu ul {
  list-style: none;
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 1.5em;
  margin: 1em 0;
  padding: 0;
}
.footer-menu li { margin: 0; }
.footer-menu a {
  color: inherit;
  text-decoration: none;
  font-size: 0.9em;
}
.footer-menu a,
.footer-menu a:link,
.footer-menu a:visited {
  color: #fff !important;
  text-decoration: none;
  font-size: 0.9em;
}
.footer-menu a:hover {
  color: #fff !important;
 text-decoration: underline; 
}
</style>




      <p class="footer-add">
当ウェブサイトは、外部のウェブサイトへのリンクを提供する場合がありますが、弊社は、外部のウェブサイトを管理する立場にありません。
外部のウェブサイトのコンテンツについていかなる責任も負わず、外部のウェブサイト上の広告、製品、その他の素材について推奨するものでもありません。
      </p>
    <div class="wrap">


		
		
      <p class="footer-copy">
        © Copyright <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>. All rights reserved.
      </p>
    </div><!-- /wrap -->
  </div><!-- /footer-02 -->
  <?php
  // }
  ?>
</footer>
<a href="#" class="pagetop"><span><i class="fa fa-angle-up"></i></span></a>
<?php wp_footer(); ?>


<script
  src="https://blogparts.gcolle.net/v1/blogparts.js"
  charset="UTF-8"
  async
></script>



<script>
(function($){
$(function(){
  $(".sub-menu").css("display","none");
  $("#gnav-ul li").hover(function(){
    $(this).children("ul").fadeIn("fast");
  }, function(){
    $(this).children("ul").fadeOut("fast");
  });
  $("#gnav").removeClass("active");
  $("#header-menu-tog a").click(function(){
    $("#gnav").toggleClass("active");
  });
});
})(jQuery);
</script>
</body>
</html>