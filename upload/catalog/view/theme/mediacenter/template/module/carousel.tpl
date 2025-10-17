<?php
if($this->registry->has('theme_options') == false) { 
 header("location: themeinstall/index.php"); 
 exit; 
}
$theme_options = $this->registry->get('theme_options');
$config = $this->registry->get('config');
$page_direction = $theme_options->get( 'page_direction' );
?>
<div class="box clearfix carousel-brands">
  <!-- Carousel nav -->
  <a class="next" href="#carousel<?php echo $module; ?>" id="carousel<?php echo $module; ?>_next"><span></span></a>
  <a class="prev" href="#carousel<?php echo $module; ?>" id="carousel<?php echo $module; ?>_prev"><span></span></a>
  
  <script type="text/javascript">
  $(document).ready(function() {
    var owl<?php echo $module; ?> = $(".box #carousel<?php echo $module; ?>");
  	
    $("#carousel<?php echo $module; ?>_next").click(function(){
        owl<?php echo $module; ?>.trigger('owl.next');
        return false;
      })
    $("#carousel<?php echo $module; ?>_prev").click(function(){
        owl<?php echo $module; ?>.trigger('owl.prev');
        return false;
    });
    
    owl<?php echo $module; ?>.owlCarousel({
        items: 6,
        autoPlay: 3000,
        navigation: true,
        navigationText: false,
        pagination: true,
        <?php if($page_direction[$config->get( 'config_language_id' )] == 'RTL'): ?>
        direction: 'rtl'
        <?php endif; ?>
    });

  });
  </script>
<div class="box-heading"><?php echo $theme_options->get( 'top_brands_text', $config->get( 'config_language_id' ) ) != '' ? $theme_options->get( 'top_brands_text', $config->get( 'config_language_id' ) ) : 'Top Brands';  ?></div>
<div class="strip-line"></div>
<div id="carousel<?php echo $module; ?>" class="owl-carousel">
  <?php foreach ($banners as $banner) { ?>
  <div class="item text-center">
    <?php if ($banner['link']) { ?>
    <a href="<?php echo $banner['link']; ?>"><img src="<?php echo $banner['image']; ?>" alt="<?php echo $banner['title']; ?>" class="img-responsive" /></a>
    <?php } else { ?>
    <img src="<?php echo $banner['image']; ?>" alt="<?php echo $banner['title']; ?>" class="img-responsive" />
    <?php } ?>
  </div>
  <?php } ?>
</div>
</div>
<script type="text/javascript"><!--
    $(document).ready(function() {

    });
--></script>