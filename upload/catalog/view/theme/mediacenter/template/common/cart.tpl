<?php if($this->registry->has('theme_options') == true) { 
$theme_options = $this->registry->get('theme_options');
$config = $this->registry->get('config'); ?>

<?php $cart_info = $theme_options->getCart(); ?>
<!-- Cart block -->
<div id="cart_block" class="dropdown">
	<div class="cart-heading dropdown-toogle" data-toggle="dropdown">
        <div class="basket-item-count">
            <span id="cart_count_ajax">
                <span class="count" id="cart_count"><?php echo $cart_info['total_item']; ?></span>
            </span>
            <img src="catalog/view/theme/mediacenter/img/icon-cart.png" alt="">
        </div>
        <div class="total-price-basket"> 
            <span class="lbl"><?php if($theme_options->get( 'your_cart_text', $config->get( 'config_language_id' ) ) != '') { echo $theme_options->get( 'your_cart_text', $config->get( 'config_language_id' ) ); } else { echo 'Your Cart'; } ?>:</span>
            <span class="total-price" id="total_price_ajax">
                <span class="value" id="total_price"><?php echo $cart_info['total_price']; ?></span>
            </span>
        </div>
	</div>
	
	<div class="dropdown-menu" id="cart_content"><div id="cart_content_ajax">
		<?php if ($products || $vouchers) { ?>
		<div class="mini-cart-info">
		  <table>
		    <?php foreach ($products as $product) { ?>
		    <tr>
		      <td class="image"><?php if ($product['thumb']) { ?>
		        <a href="<?php echo $product['href']; ?>"><img src="<?php echo $product['thumb']; ?>" alt="<?php echo $product['name']; ?>" title="<?php echo $product['name']; ?>" /></a>
		        <?php } ?></td>
		      <td class="name">
                <span class="quantity"><?php echo $product['quantity']; ?>&nbsp;x&nbsp;</span>  
                <a href="<?php echo $product['href']; ?>"><?php echo $product['name']; ?></a>
		        <div>
		          <?php foreach ($product['option'] as $option) { ?>
		          - <small><?php echo $option['name']; ?> <?php echo $option['value']; ?></small><br />
		          <?php } ?>
		          <?php if ($product['recurring']): ?>
		          - <small><?php echo $text_recurring  ?> <?php echo $product['recurring']; ?></small><br />
		          <?php endif; ?>
                  <div class="price"><?php echo $product['total']; ?></div>
		        </div></td>
		      <td class="remove"><a href="javascript:;" onclick="cart.remove('<?php echo $product['cart_id']; ?>');" title="<?php echo $button_remove; ?>"></a></td>
		    </tr>
		    <?php } ?>
		    <?php foreach ($vouchers as $voucher) { ?>
		    <tr>
		      <td class="name" style="padding-left: 30px">
                  <?php echo $voucher['description']; ?>
              </td>
              <td class="total"><div class="price"><?php echo $voucher['amount']; ?></div></td>
		      <td class="remove"><a href="javascript:;" onclick="voucher.remove('<?php echo $voucher['key']; ?>');" title="<?php echo $button_remove; ?>"></a></td>
		    </tr>
		    <?php } ?>
		  </table>
		</div>
		
		<div class="checkout">
            <a href="<?php echo $cart; ?>" class="button btn-view-cart inverse"><?php echo $text_cart; ?></a>
            <a href="<?php echo $checkout; ?>" class="button btn-checkout"><?php echo $text_checkout; ?></a>
        </div>
		<?php } else { ?>
		<div class="empty"><?php echo $text_empty; ?></div>
		<?php } ?>
	</div></div>
</div>
<?php } ?>
