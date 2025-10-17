  <?php 
  $class = 3; 
  $id = rand(0, 5000)*rand(0, 5000); 
  $all = 4; 
  $row = 4; 
  $page_direction = $theme_options->get( 'page_direction' );
  
  if($settings['article_related_per_row'] == 6) { $class = 2; }
  if($settings['article_related_per_row'] == 5) { $class = 25; }
  if($settings['article_related_per_row'] == 3) { $class = 4; }
  if($settings['article_related_per_row'] == 4) { $class = 3; }
  
  if($settings['article_related_per_row'] > 1) {
      $row = $settings['article_related_per_row'];
      $all = $settings['article_related_per_row'];
      } 
  ?>
  <div class="box clearfix">
    <?php if($settings['article_scroll_related'] == 1) : ?>
    <!-- Carousel nav -->
    <a class="next" href="#myCarousel<?php echo $id; ?>" id="myCarousel<?php echo $id; ?>_next"><span></span></a>
    <a class="prev" href="#myCarousel<?php echo $id; ?>" id="myCarousel<?php echo $id; ?>_prev"><span></span></a>
    <?php endif; ?>
  	
    <div class="box-heading"><?php echo $text_related_articles; ?></div>
    <div class="strip-line"></div>
    <div class="box-content blog-related-posts">
      <div class="box-articles">
      	<div id="myCarousel<?php echo $id; ?>" <?php if($settings['article_scroll_related'] == 1) { ?>class="carousel slide"<?php } ?>>
      		<!-- Carousel items -->
      		<div class="carousel-inner">
      			<?php $i = 0; $row_fluid = 0; $item = 0; foreach ($articles as $article) { $row_fluid++; ?>
  	    			<?php if($i == 0) { $item++; echo '<div class="active item"><div class="article-grid"><div class="row news v2">'; } ?>
  	    			<?php $r=$row_fluid-floor($row_fluid/$all)*$all; if($row_fluid>$all && $r == 1) { if($settings['article_scroll_related'] == 1) { echo '</div></div></div><div class="item"><div class="article-grid"><div class="news v2 row">'; $item++; } else { echo '</div><div class="row news v2">'; } } else { $r=$row_fluid-floor($row_fluid/$row)*$row; if($row_fluid>$row && $r == 1) { echo '</div><div class="row news v2">'; } } ?>
  	    			<div class="col-sm-<?php echo $class; ?> col-xs-12">
  	    				<div class="media">
                            <?php if($article['thumb']):?>
                            <div  class="thumb-holder">
                                <img alt="" src="<?php echo $article['thumb'] ?>"></a>
                            </div>
                            <?php endif; ?>

                            <div class="media-body" onclick="window.location.href = '<?php echo $article['href']; ?>'">

                                 <div class="bottom">
                                     <div class="date-published"><?php echo date('d.m.Y', strtotime($article['date_published'])) ?></div>
                                     <h5><a href="<?php echo $article['href']; ?>"><?php echo $article['title'] ?></a></h5>
                                 </div>
                            </div>
                        </div>
  	    			</div>
      			<?php $i++; } ?>
      			<?php if($i > 0) { echo '</div></div></div>'; } ?>
      		</div>
  	  </div>
      </div>
    </div>
  </div>
  
  <?php if($settings['article_scroll_related'] == 1): ?>
  <script type="text/javascript">
  $(document).ready(function() {
    var owl<?php echo $id; ?> = $(".box #myCarousel<?php echo $id; ?> .carousel-inner");
  	
    $("#myCarousel<?php echo $id; ?>_next").click(function(){
        owl<?php echo $id; ?>.trigger('owl.next');
        return false;
      })
    $("#myCarousel<?php echo $id; ?>_prev").click(function(){
        owl<?php echo $id; ?>.trigger('owl.prev');
        return false;
    });
      
    owl<?php echo $id; ?>.owlCarousel({
    	  slideSpeed : 500,
        singleItem:true,
        <?php if($page_direction[$config->get( 'config_language_id' )] == 'RTL'): ?>
        direction: 'rtl'
        <?php endif; ?>
     });
  });
  </script>
  <?php endif ?>
