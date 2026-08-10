<?php

/*
プレビュー時body_class追加
----------------------------------------------- */
add_filter('body_class', 'bzb_add_preview');

function bzb_add_preview($classes){
  if(is_preview()){
    $classes[] = 'cta-preview';
  }
  return $classes;
}
?>
