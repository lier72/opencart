<?php
if($this->registry->has('theme_options') == false) { 
 header("location: themeinstall/index.php"); 
 exit; 
}
$theme_options = $this->registry->get('theme_options');
?>
<?php if(!empty($articles)):?> 
<div class="box blog-module blog-product-related-posts">
    <div class="box-heading"><?php echo $heading_title; ?></div>
    <div class="strip-line"></div>
    <div class="box-content box-product-related-posts">
        <ul>
            <?php foreach($articles as $article):?>
            <li>
                <div class="media">
                    <a href="<?php echo $article['href']; ?>">
                        <?php if($article['thumb']):?>
                        <div  class="thumb-holder pull-left">
                            <img alt="" src="<?php echo $article['thumb'] ?>">
                        </div>
                        <?php endif; ?>
                        <div class="media-body">
                            <h5><?php echo $article['title'] ?></h5>
                            <div class="date-published"><?php echo date('m d Y', strtotime($article['date_published'])) ?></div>
                        </div>
                    </a>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
