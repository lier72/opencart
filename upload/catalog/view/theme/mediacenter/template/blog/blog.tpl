<?php echo $header; 
$theme_options = $this->registry->get('theme_options');
$config = $this->registry->get('config'); 
$page_direction = $theme_options->get( 'page_direction' );
include('catalog/view/theme/' . $config->get('config_template') . '/template/new_elements/wrapper_top.tpl'); ?>


<?php if(!empty($articles)):?>
<div class="posts <?php if($template == 'grid.tpl'):?> posts-grid <?php endif; ?>">
    
    <?php
    include('catalog/view/theme/' . $config->get('config_template') . '/template/blog/article_list/'. $settings['article_list_template']); 
    ?>
    
</div>
<?php if($settings['pagination_type'] == 'STANDARD'): ?>
   <?php echo $pagination; ?>
<?php else: ?>
<?php if($is_more):?>
<div class="pagination-ajax">
    <div class="load-more" id="load-more" data-page="2"><i class="fa fa-plus"></i> <?php echo $button_load_more ?> </div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
    
<script>
    
    function initSlider()
    {
        $('.posts .media-slider').each(function(){
            if(!$(this).hasClass('owl-carousel')){
                $(this).owlCarousel({
                    navigation : true, // Show next and prev buttons
                    slideSpeed : 300,
                    paginationSpeed : 400,
                    singleItem:true,
                    pagination: true,
                    autoHeight : true,
                    lazyLoad: true,
                    navigationText: ['<i class="fa fa-angle-left"></i>', '<i class="fa fa-angle-right"></i>'],
                    <?php if($page_direction[$config->get( 'config_language_id' )] == 'RTL'): ?>
                    direction: 'rtl'
                    <?php endif; ?>
                });
            }
        })
    }
    
    function isMore(page)
    {
        $.ajax({
            url: location.href,
            type: 'get',
            dataType: 'html',
            data: { ajax_request: 1, page: page },
            success: function(data) {
                if(data == ''){
                    $('.pagination-ajax').hide();
                };
            },
            error: function(){
                $('.pagination-ajax').hide();
            }
        });
    }
    
    var $grid = {};
    
    function initMasonry(){
         $grid = $('.posts').masonry({
            itemSelector: '.post',
            
          })
        $grid.imagesLoaded().progress( function() {
            $grid.masonry('layout');
            initSlider();
        });
    }
    
    $(function(){
        initSlider();
        
        <?php if($template == 'grid.tpl'):?>
        initMasonry();
        <?php endif; ?>
                
        
        $('#load-more').click(function(){
            var page = $(this).attr('data-page');
            $.ajax({
                url: location.href,
                type: 'get',
                dataType: 'html',
                data: { ajax_request: 1, page: page },
                beforeSend: function() {
                    $('#load-more').button('loading');
                },
                complete: function() {
                    $('#load-more').button('reset');
                },
                success: function(data) {
                    if(data == ''){
                        $('.pagination-ajax').fadeOut();
                        return;
                    }
                    
                    <?php if($template == 'grid.tpl'):?>
                        // prepend items to grid
                        //$grid.imagesLoaded().progress( function() {
                            $('.posts').append( data);
                            initSlider();
                            $('.posts').masonry( 'reloadItems' ).masonry({ sortBy: 'original-order' });
                            setTimeout(function(){ $('.posts').masonry( 'reloadItems' ).masonry({ sortBy: 'original-order' });}, 500);
                       // });
                   <?php else: ?>
                        $('.posts').append('<div class="ajax-content" style="display: none">' + data + '</div>');
                    
                        $('.posts .ajax-content').last().fadeIn('slow', function(){
                            initSlider();
                        });
                   <?php endif; ?>
                    
                    
                    $('#load-more').attr('data-page', parseInt(++page));
                    isMore(page);
                }
            });
        })
    })
</script>

<?php include('catalog/view/theme/' . $config->get('config_template') . '/template/new_elements/wrapper_bottom.tpl'); ?>
<?php echo $footer; ?>