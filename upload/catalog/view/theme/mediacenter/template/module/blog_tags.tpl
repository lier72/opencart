<?php
if($this->registry->has('theme_options') == false) { 
 header("location: themeinstall/index.php"); 
 exit; 
}
$theme_options = $this->registry->get('theme_options');
?>
<div class="box blog-module blog-tags">
    <div class="box-heading"><?php echo $heading_title; ?></div>
    <div class="strip-line"></div>
    <div class="box-content box-tags">
        <?php if(!empty($tags)):?>
        <div class="tagcloud">
            <?php foreach($tags as $tag): ?>
			<a href="<?php echo $tag['href'] ?>" rel="<?php echo $tag['value'] ?>"><?php echo $tag['tag'] ?></a> 
            <?php endforeach; ?>
		</div>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript" src="catalog/view/theme/<?php echo $config->get( 'config_template' ); ?>/js/jquery.tagcloud.js"></script>
<script>
    $(function(){
        $.fn.tagcloud.defaults = {
            size: {start: 8, end: 20, unit: "pt"}
        };
        
        $(function () {
            $('.tagcloud a').tagcloud();
        });
    })
</script>