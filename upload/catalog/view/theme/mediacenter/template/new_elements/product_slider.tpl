<?php
$theme_options = $this->registry->get('theme_options');
$config = $this->registry->get('config');
$page_direction = $theme_options->get( 'page_direction' );

$product_detail = $theme_options->getDataProduct( $product['product_id'] );
$text_sale = 'Sale';
if($theme_options->get( 'sale_text', $config->get( 'config_language_id' ) ) != '') {
    $text_sale = $theme_options->get( 'sale_text', $config->get( 'config_language_id' ) );
} 
$text_bestseller = 'Bestseller';
if($theme_options->get( 'bestseller_text', $config->get( 'config_language_id' ) ) != '') {
    $text_bestseller = $theme_options->get( 'bestseller_text', $config->get( 'config_language_id' ) );
} 
$text_new = 'New';
if($theme_options->get( 'new_text', $config->get( 'config_language_id' ) ) != '') {
    $text_new = $theme_options->get( 'new_text', $config->get( 'config_language_id' ) );
} 
$product['thumb'] =  $theme_options->productImageThumb($product['product_id'], 430, 320);
$images = $theme_options->getProductImages($product['product_id'], 60,60, 430, 320);
?>
<!-- Product -->
<div class="product product-img-slider clearfix <?php if($theme_options->get( 'hover_product' ) != '0') { echo 'product-hover'; } ?>">
	<div class="left">
		<?php if ($product['thumb']) { ?>
			<?php if($product['special'] && $theme_options->get( 'display_text_sale' ) != '0') { ?>

				<?php if($theme_options->get( 'type_sale' ) ==  '0') { ?>
                    <div class="ribbon red sale"><span><?php echo $text_sale; ?></span></div>
				<?php } ?>
			<?php } ?>
                    
            <?php if($product_detail['is_bestseller'] && $theme_options->get( 'display_text_bestseller' ) != '0'):?>
                <div class="ribbon green bestseller"><span><?php echo $text_bestseller; ?></span></div>
            <?php endif; ?>
            <?php if($product_detail['is_latest'] && $theme_options->get( 'display_text_latest' ) != '0'):?>
                <div class="ribbon blue latest"><span><?php echo $text_new; ?></span></div>
            <?php endif; ?>
			
			<div class="image <?php if($theme_options->get( 'product_image_effect' ) == '1') { echo 'image-swap-effect'; } ?>">
				<?php if($theme_options->get( 'quick_view' ) == 1) { ?>
				<div class="quickview">
					<a href="index.php?route=product/quickview&product_id=<?php echo $product['product_id']; ?>" title="<?php echo $product['name']; ?>"><?php if($theme_options->get( 'quickview_text', $config->get( 'config_language_id' ) ) != '') { echo html_entity_decode($theme_options->get( 'quickview_text', $config->get( 'config_language_id' ) )); } else { echo 'QUICKVIEW'; } ?></a>
				</div>
				<?php } ?>
				
				<a href="<?php echo $product['href']; ?>">
					<?php if($theme_options->get( 'product_image_effect' ) == '1') {
						$nthumb = str_replace(' ', "%20", ($product['thumb']));
						$nthumb = str_replace(HTTP_SERVER, "", $nthumb);
						$image_size = getimagesize($nthumb);
						$image_swap = $theme_options->productImageSwap($product['product_id'], $image_size[0], $image_size[1]);
						if($image_swap != '') echo '<img src="' . $image_swap . '" alt="' . $product['name'] . '" class="swap-image" />';
					} ?> 
					<?php if($theme_options->get( 'lazy_loading_images' ) != '0') { ?>
					<img src="image/catalog/blank.gif"  id="advanced-product-slider" data-image="<?php echo $product['thumb']; ?>" data-echo="<?php echo $product['thumb']; ?>" alt="<?php echo $product['name']; ?>" class="<?php if($theme_options->get( 'product_image_effect' ) == '2') { echo 'zoom-image-effect'; } ?>" />
					<?php } else { ?>
					<img src="<?php echo $product['thumb']; ?>" id="advanced-product-slider" alt="<?php echo $product['name']; ?>"  data-image="<?php echo $product['thumb']; ?>" class="<?php if($theme_options->get( 'product_image_effect' ) == '2') { echo 'zoom-image-effect'; } ?>" />
					<?php } ?>
                    </a>
                    <?php if ($images): ?>
                    <div class="col-sm-12">
                        <div class="thumbnails thumbnails-left clearfix">
                            <div class="thumbnails-carousel owl-carousel">
                                <div class="item active" data-image="<?php echo $product['thumb']; ?>"><img src="<?php echo  $theme_options->productImageThumb($product['product_id'], 60, 60);; ?>" title="<?php echo $product['name']; ?>" alt="<?php echo $product['name']; ?>" /></div>
                               <?php foreach ($images as $image): ?>
                                   <div class="item" data-image="<?php echo $image['large']; ?>"><img src="<?php echo $image['thumb']; ?>" title="<?php echo $product['name']; ?>" alt="<?php echo $product['name']; ?>" /></div>
                               <?php endforeach ?>
                            </div>
                        </div>

                        <script type="text/javascript">
                            $(document).ready(function() {
                                $(".thumbnails-carousel").owlCarousel({
                                    autoPlay: 6000, //Set AutoPlay to 3 seconds
                                    navigation: true,
                                    navigationText: ['', ''],
                                    itemsCustom : [
                                      [0, 4],
                                      [450, 6],
                                      [550, 6],
                                      [768, 5],
                                      [1200, 6]
                                    ],
                                    <?php if($page_direction[$config->get( 'config_language_id' )] == 'RTL'): ?>
                                    direction: 'rtl'
                                    <?php endif; ?>
                                });

                                $('.product-grid-template .thumbnails .item, .product-grid-template .thumbnails-carousel .item').click(function() {
                                    
                                    $(this).parent().parent().find('.item').removeClass('active');
                                    $(this).addClass('active');
                                    
                                    var image = $(this).attr('data-image');
                                    
                                    $(this).parent().parent().parent().parent().parent().parent().parent().find('#advanced-product-slider').attr('src', image);  
                                    $(this).parent().parent().parent().parent().parent().parent().parent().find('#advanced-product-slider').attr('href', image);  
                                    return false;
                                });
                            });
                        </script>
                    </div>
                <?php endif; ?> 
				
			</div>
		<?php } else { ?>
			<div class="image">
				<?php if($theme_options->get( 'quick_view' ) == 1) { ?>
				<div class="quickview">
					<a href="index.php?route=product/quickview&product_id=<?php echo $product['product_id']; ?>" title="<?php echo $product['name']; ?>"><?php if($theme_options->get( 'quickview_text', $config->get( 'config_language_id' ) ) != '') { echo html_entity_decode($theme_options->get( 'quickview_text', $config->get( 'config_language_id' ) )); } else { echo 'QUICKVIEW'; } ?></a>
				</div>
				<?php } ?>
				
				<a href="<?php echo $product['href']; ?>"><img src="image/no_image.jpg"  style="width: <?php echo  $product['img_width'] ?>px; height: <?php echo $product['img_height']; ?>px"  alt="<?php echo $product['name']; ?>" <?php if($theme_options->get( 'product_image_effect' ) == '2') { echo 'class="zoom-image-effect"'; } ?> /></a>
			</div>
		<?php } ?>
                
		<?php if($theme_options->get( 'display_specials_countdown' ) == '1' && $product['special']) { $countdown = rand(0, 5000)*rand(0, 5000); 
		          $date_end = $product_detail['date_end'];
		          if($date_end != '0000-00-00' && $date_end) { ?>
               		<script>
               		$(function () {
               			var austDay = new Date();
               			austDay = new Date(<?php echo date("Y", strtotime($date_end)); ?>, <?php echo date("m", strtotime($date_end)); ?> - 1, <?php echo date("d", strtotime($date_end)); ?>);
               			$('#countdown<?php echo $countdown; ?>').countdown({until: austDay});
               		});
               		</script>
               		<div id="countdown<?php echo $countdown; ?>" class="clearfix"></div>
     		     <?php } ?>
		<?php } ?>        
	</div>
	<div class="right">
		<div class="name">
            <div class="label-discount green sale<?php echo ($product['special'] && $theme_options->get( 'type_sale' ) == '1' ? '' : 'clear')?>">
                <?php if($product['special'] && $theme_options->get( 'display_text_sale' ) != '0') { ?>
                    <?php if($theme_options->get( 'type_sale' ) == '1') { ?>
                        <?php 
                        $roznica_ceny = $product_detail['price']-$product_detail['special'];
                        $procent = ($roznica_ceny*100)/$product_detail['price']; ?>
                        -<?php echo $procent ?>% <?php echo $text_sale ?>
                <?php } ?>
                <?php } ?>
            </div>
            <a href="<?php echo $product['href']; ?>"><?php echo $product['name']; ?></a>
            <div class="brand"><?php echo $product_detail['manufacturer']; ?></div>
            <?php if ($product['rating'] && $theme_options->get( 'display_rating' ) != '0') { ?>
            <div class="rating"><i class="fa fa-star<?php if($product['rating'] >= 1) { echo ' active'; } ?>"></i><i class="fa fa-star<?php if($product['rating'] >= 2) { echo ' active'; } ?>"></i><i class="fa fa-star<?php if($product['rating'] >= 3) { echo ' active'; } ?>"></i><i class="fa fa-star<?php if($product['rating'] >= 4) { echo ' active'; } ?>"></i><i class="fa fa-star<?php if($product['rating'] >= 5) { echo ' active'; } ?>"></i></div>
            <?php } ?>
        </div>
		<div class="price">
			<?php if (!$product['special']) { ?>
			<?php echo $product['price']; ?>
			<?php } else { ?>
			<span class="price-old"><?php echo $product['price']; ?></span> <span class="price-new"><?php echo $product['special']; ?></span>
			<?php } ?>
		</div>
		<?php if($theme_options->get( 'display_add_to_compare' ) != '0' || $theme_options->get( 'display_add_to_wishlist' ) != '0' || $theme_options->get( 'display_add_to_cart' ) != '0') { ?>
		<div class="add-to-cart">
            <?php if($theme_options->get( 'display_add_to_cart' ) != '0') { ?>
			     <?php $enquiry = false; if($config->get( 'product_blocks_module' ) != '') { $enquiry = $theme_options->productIsEnquiry($product['product_id']); }
			     if(is_array($enquiry)) { ?>
			     <a href="javascript:openPopup('<?php echo $enquiry['popup_module']; ?>', '<?php echo $product['product_id']; ?>')" class="button button-enquiry">
			          <?php if($enquiry['icon'] != '' && $enquiry['icon_position'] == 'left') { echo '<img src="image/' . $enquiry['icon']. '" align="left" class="icon-enquiry" alt="Icon">'; } ?>
			          <span class="text-enquiry"><?php echo $enquiry['block_name']; ?></span>
			          <?php if($enquiry['icon'] != '' && $enquiry['icon_position'] == 'right') { echo '<img src="image/' . $enquiry['icon']. '" align="right" class="icon-enquiry" alt="Icon">'; } ?>
			     </a>
			     <?php } else { ?>
			     <a onclick="cart.add('<?php echo $product['product_id']; ?>');" class="button button-large"><?php echo $button_cart; ?></a>
			     <?php } ?>
			<?php } ?>
		</div>
		<?php } ?>
	</div>
</div>