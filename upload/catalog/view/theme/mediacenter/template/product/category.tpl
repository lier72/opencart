<?php echo $header; 
if(isset($mfilter_json)) {
	if(!empty($mfilter_json)) { 
		echo '<div id="mfilter-json" style="display:none">' . base64_encode( $mfilter_json ) . '</div>'; 
	} 
}

$theme_options = $this->registry->get('theme_options');
$config = $this->registry->get('config'); 
include('catalog/view/theme/'.$config->get('config_template').'/template/new_elements/wrapper_top.tpl');

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


$text_stock = 'Availability';
if($theme_options->get( 'availability_text', $config->get( 'config_language_id' ) ) != '') {
    $text_stock = $theme_options->get( 'availability_text', $config->get( 'config_language_id' ) );
} 
?>
<div id="mfilter-content-container">
  <?php if ($thumb || $description) { ?>
  <div class="category-info clearfix">
    <?php if ($thumb) { ?>
    <div class="image"><img src="<?php echo $thumb; ?>" alt="<?php echo $heading_title; ?>" /></div>
    <?php } ?>
    <?php if ($description) { ?>
    <?php echo $description; ?>
    <?php } ?>
  </div>
  <?php } ?>
  <?php if ($categories && $theme_options->get('refine_search_style') != '2') { ?>
  <h2 class="refine_search"><?php echo $text_refine; ?></h2>
  <div class="category-list<?php if ($theme_options->get('refine_search_style') == '1') { echo ' category-list-text-only'; } ?>">
  	<div class="row">
  	  <?php 
  	  $class = 3; 
  	  $row = 4; 
  	  
  	  if($theme_options->get( 'refine_search_number' ) == 2) { $class = 62; }
  	  if($theme_options->get( 'refine_search_number' ) == 5) { $class = 25; }
  	  if($theme_options->get( 'refine_search_number' ) == 3) { $class = 4; }
  	  if($theme_options->get( 'refine_search_number' ) == 6) { $class = 2; }
  	  
  	  if($theme_options->get( 'refine_search_number' ) > 1) { $row = $theme_options->get( 'refine_search_number' ); } 
  	  ?>
	  <?php $row_fluid = 0; foreach ($theme_options->refineSearch() as $category) { $row_fluid++; ?>
	  	<?php 
	  	if ($theme_options->get('refine_search_style') != '1') {
	  		$width = 250;
	  		$height = 250;
	  		if($theme_options->get( 'refine_image_width' ) > 20) $width = $theme_options->get( 'refine_image_width' );
	  		if($theme_options->get( 'refine_image_height' ) > 20) $height = $theme_options->get( 'refine_image_height' );
	  		$model_tool_image = $this->registry->get('model_tool_image');
		  	if($category['thumb'] != '') { 
		  		$image = $model_tool_image->resize($category['thumb'], $width, $height); 
		  	} else { 
		  		$image = $model_tool_image->resize('no_image.jpg', $width, $height); 
		  	} 
	  	}
	  	?>
	  	<?php $r=$row_fluid-floor($row_fluid/$row)*$row; if($row_fluid>$row && $r == 1) { echo '</div><div class="row">'; } ?>
	  	<div class="col-sm-<?php echo $class; ?> col-xs-6">
	  		<?php if ($theme_options->get('refine_search_style') != '1') { ?>
		  	<a href="<?php echo $category['href']; ?>"><img src="<?php echo $image; ?>" alt="<?php echo $category['name']; ?>" /></a>
		  	<?php } ?>
		  	<a href="<?php echo $category['href']; ?>"><?php echo $category['name']; ?></a>
	  	</div>
	  <?php } ?>
	</div>
  </div>
  <?php } ?>
  <?php if ($products) { ?>
  <!-- Filter -->
  <div class="product-filter clearfix">
  	<div class="options">
  		<div class="product-compare"><a href="<?php echo $compare; ?>" id="compare-total"><?php echo $text_compare; ?></a></div>
  		
  		<div class="button-group display" data-toggle="buttons-radio">
  			<button id="grid" <?php if($theme_options->get('default_list_grid') == '1') { echo 'class="active"'; } ?> rel="tooltip" title="Grid" onclick="display('grid');"><i class="fa fa-th-large"></i></button>
  			<button id="list" <?php if($theme_options->get('default_list_grid') != '1') { echo 'class="active"'; } ?> rel="tooltip" title="List" onclick="display('list');"><i class="fa fa-th-list"></i></button>
  		</div>
  	</div>
  	
  	<div class="list-options">
  		<div class="sort">
  			<?php echo $text_sort; ?>
  			<select onchange="location = this.value;">
  			  <?php foreach ($sorts as $sorts) { ?>
  			  <?php if ($sorts['value'] == $sort . '-' . $order) { ?>
  			  <option value="<?php echo $sorts['href']; ?>" selected="selected"><?php echo $sorts['text']; ?></option>
  			  <?php } else { ?>
  			  <option value="<?php echo $sorts['href']; ?>"><?php echo $sorts['text']; ?></option>
  			  <?php } ?>
  			  <?php } ?>
  			</select>
  		</div>
  		
  		<div class="limit">
  			<?php echo $text_limit; ?>
  			<select onchange="location = this.value;">
  			  <?php foreach ($limits as $limits) { ?>
  			  <?php if ($limits['value'] == $limit) { ?>
  			  <option value="<?php echo $limits['href']; ?>" selected="selected"><?php echo $limits['text']; ?></option>
  			  <?php } else { ?>
  			  <option value="<?php echo $limits['href']; ?>"><?php echo $limits['text']; ?></option>
  			  <?php } ?>
  			  <?php } ?>
  			</select>
  		</div>
  	</div>
  </div>
  
  <!-- Products list -->
  <div class="product-list"<?php if(!($theme_options->get('default_list_grid') == '1')) { echo ' class="active"'; } ?>>
  	<?php foreach ($products as $product) { ?>
        <?php $product_detail = $theme_options->getDataProduct( $product['product_id'] ); ?>
  	<!-- Product -->
  	<div>
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
  		<div class="row">

  			<div class="image col-sm-4">
  				
  				<?php if($theme_options->get( 'quick_view' ) == 1) { ?>
  				<div class="quickview">
  					<a href="index.php?route=product/quickview&product_id=<?php echo $product['product_id']; ?>" title="<?php echo $product['name']; ?>"><?php if($theme_options->get( 'quickview_text', $config->get( 'config_language_id' ) ) != '') { echo html_entity_decode($theme_options->get( 'quickview_text', $config->get( 'config_language_id' ) )); } else { echo 'QUICKVIEW'; } ?></a>
  				</div>
  				<?php } ?>
  				
  				<?php if($product['thumb']) { ?>
  					<?php if($theme_options->get( 'lazy_loading_images' ) != '0') { ?>
  					<a href="<?php echo $product['href']; ?>"><img src="image/catalog/blank.gif" data-echo="<?php echo $product['thumb']; ?>" alt="<?php echo $product['name']; ?>" /></a>
  					<?php } else { ?>
  					<a href="<?php echo $product['href']; ?>"><img src="<?php echo $product['thumb']; ?>" alt="<?php echo $product['name']; ?>" /></a>
  					<?php } ?>
  				<?php } else { ?>
  				<a href="<?php echo $product['href']; ?>"><img src="image/no_image.jpg" alt="<?php echo $product['name']; ?>" /></a>
  				<?php } ?>
  			</div>
  			
  			<div class="name-desc col-sm-5">
                <?php if ($product['rating']) { ?>
                    <div class="rating-reviews clearfix">
                        <div class="rating"><i class="fa fa-star<?php if($product['rating'] >= 1) { echo ' active'; } ?>"></i><i class="fa fa-star<?php if($product['rating'] >= 2) { echo ' active'; } ?>"></i><i class="fa fa-star<?php if($product['rating'] >= 3) { echo ' active'; } ?>"></i><i class="fa fa-star<?php if($product['rating'] >= 4) { echo ' active'; } ?>"></i><i class="fa fa-star<?php if($product['rating'] >= 5) { echo ' active'; } ?>"></i></div>
                    </div>
                <?php } ?>
  				<div class="name">
                    <a href="<?php echo $product['href']; ?>"><?php echo $product['name']; ?></a>
                    <div class="brand"><?php echo $product_detail['manufacturer']; ?></div>
                </div>
  				<div class="description"><?php echo $product['description']; ?></div>

  			</div>
  			
  			<div class="actions col-sm-3">
  				<div>
                    <?php if($product['special'] && $theme_options->get( 'display_text_sale' ) != '0') { ?>
                        <?php if($theme_options->get( 'type_sale' ) == '1') { ?>
                        <?php
                        $roznica_ceny = $product_detail['price']-$product_detail['special'];
                        $procent = ($roznica_ceny*100)/$product_detail['price']; ?>
                        <div class="label-discount sale"><span>-<?php echo round($procent); ?>%</span></div>
                        <?php } ?>
                    <?php } ?>
  					<div class="price">
  						<?php if (!$product['special']) { ?>
  						<?php echo $product['price']; ?>
  						<?php } else { ?>
  						<span class="price-old"><?php echo $product['price']; ?></span> <span class="price-new"><?php echo $product['special']; ?></span>
  						<?php } ?>
  					</div>
                     <div class="availability">
                         <?php echo $text_stock; ?> <span class="<?php echo ($product_detail['is_available'] ? 'available' : 'no-available'); ?>"><?php echo $product_detail['stock']; ?></span>
                     </div>
  					<div class="add-to-cart">
  					     <?php $enquiry = false; if($config->get( 'product_blocks_module' ) != '') { $enquiry = $theme_options->productIsEnquiry($product['product_id']); }
  					     if(is_array($enquiry)) { ?>
  					     <a href="javascript:openPopup('<?php echo $enquiry['popup_module']; ?>', '<?php echo $product['product_id']; ?>')" class="button button-enquiry">
  					          <?php if($enquiry['icon'] != '' && $enquiry['icon_position'] == 'left') { echo '<img src="image/' . $enquiry['icon']. '" align="left" class="icon-enquiry" alt="Icon">'; } ?>
  					          <span class="text-enquiry"><?php echo $enquiry['block_name']; ?></span>
  					          <?php if($enquiry['icon'] != '' && $enquiry['icon_position'] == 'right') { echo '<img src="image/' . $enquiry['icon']. '" align="right" class="icon-enquiry" alt="Icon">'; } ?>
  					     </a>
  					     <?php } else { ?>
  					     <a onclick="cart.add('<?php echo $product['product_id']; ?>');" class="button"><?php echo $button_cart; ?></a>
  					     <?php } ?>
  					</div>
  					
  					<ul class="add-list">
  						<li><a onclick="compare.add('<?php echo $product['product_id']; ?>');" class="btn-add-to-compare"><i class="fa fa-heart"></i><?php if($theme_options->get( 'add_to_compare_text', $config->get( 'config_language_id' ) ) != '') { echo $theme_options->get( 'add_to_compare_text', $config->get( 'config_language_id' ) ); } else { echo 'Add to compare'; } ?></a></li>
  						<li><a onclick="wishlist.add('<?php echo $product['product_id']; ?>');" class="btn-add-to-wishlist"><i class="fa fa-exchange"></i><?php if($theme_options->get( 'add_to_wishlist_text', $config->get( 'config_language_id' ) ) != '') { echo $theme_options->get( 'add_to_wishlist_text', $config->get( 'config_language_id' ) ); } else { echo 'Add to wishlist'; } ?></a></li>
  					</ul>
  				</div>
  			</div>
  		</div>
  	</div>
  	<?php } ?>
  </div>
  
  <!-- Products grid -->
  <?php 
  $class = 3; 
  $row = 4; 
  
  if($theme_options->get( 'product_per_pow2' ) == 6) { $class = 2; }
  if($theme_options->get( 'product_per_pow2' ) == 5) { $class = 25; }
  if($theme_options->get( 'product_per_pow2' ) == 3) { $class = 4; }
  
  if($theme_options->get( 'product_per_pow2' ) > 1) { $row = $theme_options->get( 'product_per_pow2' ); } 
  ?>
  <div class="product-grid"<?php if($theme_options->get('default_list_grid') == '1') { echo ' class="active"'; } ?>>
  	<div class="row">
	  	<?php $row_fluid = 0; foreach ($products as $product) { $row_fluid++; ?>
		  	<?php $r=$row_fluid-floor($row_fluid/$row)*$row; if($row_fluid>$row && $r == 1) { echo '</div><div class="row">'; } ?>
		  	<div class="col-sm-<?php echo $class; ?> col-xs-6">
		  	    <?php include('catalog/view/theme/'.$config->get('config_template').'/template/new_elements/product.tpl'); ?>
		  	</div>
	    <?php } ?>
    </div>
  </div>
  
  <div class="row pagination-results">
    <div class="col-sm-6 text-left"><?php echo $pagination; ?></div>
    <div class="col-sm-6 text-right"><?php echo $results; ?></div>
  </div>
  <?php } ?>
  <?php if (!$categories && !$products) { ?>
  <p style="padding-top: 6px"><?php echo $text_empty; ?></p>
  <div class="buttons">
    <div class="pull-right"><a href="<?php echo $continue; ?>" class="btn btn-primary"><?php echo $button_continue; ?></a></div>
  </div>
  <?php } ?>
<script type="text/javascript"><!--
function display(view) {

	if (view == 'list') {
		$('.product-grid').removeClass("active");
		$('.product-list').addClass("active");

		$('.display').html('<button id="grid" rel="tooltip" title="Grid" onclick="display(\'grid\');"><i class="fa fa-th-large"></i></button> <button class="active" id="list" rel="tooltip" title="List" onclick="display(\'list\');"><i class="fa fa-th-list"></i></button>');
		
		localStorage.setItem('display', 'list');
	} else {
	
		$('.product-grid').addClass("active");
		$('.product-list').removeClass("active");
					
		$('.display').html('<button class="active" id="grid" rel="tooltip" title="Grid" onclick="display(\'grid\');"><i class="fa fa-th-large"></i></button> <button id="list" rel="tooltip" title="List" onclick="display(\'list\');"><i class="fa fa-th-list"></i></button>');
		
		localStorage.setItem('display', 'grid');
	}
}

if (localStorage.getItem('display') == 'list') {
	display('list');
} else {
	display('grid');
}
//--></script> 
</div>
<?php include('catalog/view/theme/'.$config->get('config_template').'/template/new_elements/wrapper_bottom.tpl'); ?>
<?php echo $footer; ?>