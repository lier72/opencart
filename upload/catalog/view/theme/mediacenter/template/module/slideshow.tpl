<div class="container">
     <div class="camera_slider">
     	<div class="camera_wrap" id="camera_wrap_<?php echo $module+20; ?>">
     		<?php foreach ($banners as $banner) { ?>
     		<?php if ($banner['link']) { ?>
     		<a href="<?php echo $banner['link']; ?>"><img src="<?php echo $banner['image']; ?>" alt="<?php echo $banner['title']; ?>" /></a>
     		<?php } else { ?>
     		<img src="<?php echo $banner['image']; ?>" alt="<?php echo $banner['title']; ?>" />
     		<?php } ?>
     		<?php } ?>
     	</div>
	</div>
</div>

<script type="text/javascript">
$(document).ready(function() {
  var camera_slider = $("#camera_wrap_<?php echo $module+20; ?>");
    
  camera_slider.owlCarousel({
    slideSpeed : 300,
    lazyLoad : true,
    singleItem: true,
    autoPlay: 7000,
    stopOnHover: true,
    navigation: true
   });
});
</script>