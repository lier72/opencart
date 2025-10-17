<?php
if($this->registry->has('theme_options') == false) { 
 header("location: themeinstall/index.php"); 
 exit; 
}
$theme_options = $this->registry->get('theme_options');
?>
<div class="box blog-module box-no-advanced">
    <div class="box-heading"><?php echo $heading_title; ?></div>
    <div class="strip-line"></div>
    <div class="box-content">
        <?php if(!empty($articles)):?> 
        <div class="news v2 row">
            <?php foreach($articles as $article):?>
            <div class="col-md-4 col-sm-6 col-xs-12">
                <div class="media">
                    <?php if($article['thumb']):?>
                    <div  class="thumb-holder">
                        <img alt="" src="<?php echo $article['thumb'] ?>"></a>
                    </div>
                    <?php endif; ?>

                    <div class="media-body" onclick="window.location.href = '<?php echo $article['href']; ?>'">
                         <div class="bottom">
                             <div class="date-published"><?php echo date('d.m.Y', strtotime($article['date_published'])) ?></div>
                             <h5><?php echo $article['title'] ?></h5>
                         </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>