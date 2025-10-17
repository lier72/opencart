<?php
    $theme_options = $this->registry->get('theme_options');
    $products = $module['content']['products'];
    if(!empty($products)){
        $last_product = array_pop($products);
    }
?>
<div class="box">
 <div class="box-heading"><?php echo $module['content']['title']; ?></div>
  <div class="strip-line"></div>
  <?php if(!empty($products)):?>
  <div class="box-content products product-grid-template">
    <div class="box-product">
        <div class="product-grid">
            <div class="row">
                <div class="col-md-7 grid-block">
                    <div class="row">		
                        <?php $i = 0; ?>
                        <?php foreach ($products as $product):?>
                            <div class="block col-sm-6 col-md-4 col-xs-12 ">
                                <?php include('catalog/view/theme/'.$config->get('config_template').'/template/new_elements/product.tpl'); ?>
                            </div>
                            <?php $i++; ?>
                        <?php endforeach ?>
                    </div>
                </div>
                <div class="col-md-5 grid-block">
                    <div class="row">
                        <div class="block col-sm-12">
                            <?php $product = $last_product; ?>
                            <?php include('catalog/view/theme/'.$config->get('config_template').'/template/new_elements/product_slider.tpl'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="clearfix"></div>
</div>

<script>
    
    function setBlockHeight<?php echo $id; ?>()
    {
        var blocks = $('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-7.grid-block .block').length;
        var rows = Math.ceil((blocks) / 3);
        if(rows == 2){
            
            $('.advanced-grid-<?php echo $id; ?> .product-grid .name').matchHeight('remove');
            $('.advanced-grid-<?php echo $id; ?> .product-grid .grid-block').matchHeight();
            $('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-7.grid-block .block .product .name').css('height',"auto");
            $('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-5.grid-block .block .product .name').css('height',"auto");
        
            
            if(parseInt($('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-5.grid-block > div').innerHeight()) >  parseInt($('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-7.grid-block > div').innerHeight())){
                var nameHeightSmall = parseInt($('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-7.grid-block').innerHeight())  - 2*(parseInt($('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-7.grid-block .product').innerHeight()) - parseInt($('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-7.grid-block .product .name').innerHeight()));
                nameHeightSmall = nameHeightSmall / 2;
                $('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-7.grid-block .block .product .name').css('height', nameHeightSmall + "px");
                   
            }else{
                var nameHeight = parseInt($('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-5.grid-block').innerHeight()) - (parseInt($('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-5.grid-block .product').innerHeight()) - parseInt($('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-5.grid-block .product .name').innerHeight()));
                $('.advanced-grid-<?php echo $id; ?> .product-grid .col-md-5.grid-block .product .name').css('height', nameHeight + 'px');
            }
        }

    }
        
        
    $('.advanced-grid-<?php echo $id; ?>').bind('wowShow', function(){
        if($(window).width() > 992 || !$('html').hasClass('responsive')){

            var timer = setInterval( function() {
                if( !$('.advanced-grid-<?php echo $id; ?>').parent().hasClass( 'animated' )) {
                    setBlockHeight<?php echo $id; ?>();
                    clearTimeout(timer);
                }
            }, 100 );
        }
    })

    $('.advanced-grid-<?php echo $id; ?> img').bind('echoShow', function(){
        if($(window).width() > 992 || !$('html').hasClass('responsive')){
            $(this).one("load", function() {
                setBlockHeight<?php echo $id; ?>();
            }).each(function() {
                if(this.complete) $(this).load();
            });
            
        }
    })


    $(window).load(function(){
        if($(window).width() > 992 || !$('html').hasClass('responsive')){
            setBlockHeight<?php echo $id; ?>();
        }
    })

</script>