<?php if($theme_options->get( 'font_status' ) == '1' || $theme_options->get( 'colors_status' ) == '1') { ?>
<style type="text/css">
	<?php if($theme_options->get( 'colors_status' ) == '1') { ?>

        <?php if($theme_options->get( 'main' ) != '') { ?>

                ::selection {
                  background: <?php echo $theme_options->get( 'main' ); ?>;
                  color: #fff;
                }
                ::-moz-selection {
                  background: <?php echo $theme_options->get( 'main' ); ?>;
                  color:#fff;
                }
                
                .logo-svg {
                    fill: <?php echo $theme_options->get( 'main' ); ?>;
                }
    
                .button,
                .le-button,
                .btn{
                    background: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .le-color {
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                
                .dropdown .dropdown-menu {
                    border-top-color: <?php echo $theme_options->get( 'main' ); ?>;
                }


                #top-bar-right .dropdown-menu{
                    border-top-color: <?php echo $theme_options->get( 'main' ); ?>;
                }

                #top .contact-row i {
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }

                .search_form .button-search,
                .search_form .button-search2{
                    background-color: <?php echo $theme_options->get( 'main' ); ?>;
                }

                #top .top-cart-row-container .wishlist-compare-holder a:hover,
                #top .top-cart-row-container .wishlist-compare-holder a:hover i{
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }

                #top #cart_block .cart-heading .basket-item-count .count{
                    background-color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                #top #cart_block .cart-heading .total-price{
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }

                #main #top #cart_block.open > .dropdown-menu{
                    border-top-color: <?php echo $theme_options->get( 'main' ); ?>;
                }

                .mini-cart-info .price {
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }

                /* @end */

                /* @group 3. MegaMenu */
                .megamenu-wrapper {
                    background: <?php echo $theme_options->get( 'main' ); ?>;
                }
                .megamenuToogle-wrapper {
                    background: <?php echo $theme_options->get( 'main' ); ?>;
                }


                ul.megamenu li .sub-menu .content .static-menu a.main-menu {
                    color: <?php echo $theme_options->get( 'main' ); ?> ;
                }
                
                ul.megamenu > li > .sub-menu > .content > .arrow:after {
                    border-bottom-color:  <?php echo $theme_options->get( 'main' ); ?> ;
                }

                ul.megamenu li .sub-menu .content {
                    border-top-color: <?php echo $theme_options->get( 'main' ); ?> ;
                }

                @media (max-width: 991px) {

                    .responsive .megamenu-wrapper {
                        background: <?php echo $theme_options->get( 'main' ); ?> ;
                    }

                }


                /* @group 3. RevolutionSlider */
                .tp-bullets.simplebullets.round .bullet:hover,
                .tp-bullets.simplebullets.round .bullet.selected {
                    background-color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                /* @end */
                
                
                /* @group 4. ProductFilter */
                .filter-product .filter-tabs ul > li.active > a,
                .filter-product .filter-tabs ul > li.active > a:hover,
                .filter-product .filter-tabs ul > li.active > a:focus {
                    background-color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                /* @end */
    
                        
                .carousel-brands .owl-prev:hover,
                .carousel-brands .owl-next:hover{
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .camera_wrap .owl-controls .owl-pagination .active span {
                    background: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                
                .box-product .owl-pagination > div.active,
                .box-product .owl-pagination > div:hover {
                    background: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .product-grid .product:hover .image .quickview a {
                    background: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .product-grid .product:hover .image .quickview a,
                .product-list .row:hover .quickview a {
                    background: <?php echo $theme_options->get( 'main' ); ?>;
                }

                                
                div.pagination-results ul li:hover a,
                div.pagination-results ul li:hover span,
                div.pagination-results ul li.active a,
                div.pagination-results ul li.active span{
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                    border-color:<?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .product-info .thumbnails-carousel:hover .owl-buttons .owl-prev:hover,
                .product-info .thumbnails-carousel:hover .owl-buttons .owl-next:hover {
                    background: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .htabs a.selected {
                    background: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .center-column .tab-content .meta-row span a{
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }

                
                ul.contact-us li span {
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .center-column .list-unstyled li:before {
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .custom-footer h4 i {
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                
                .footer .contact-info .social-icons li a:hover {
                    background-color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .footer h4 {
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                /* ElevateZoom */
                .zoomTint{
                    background-color: <?php echo $theme_options->get( 'main' ); ?> !important;
                }
                
                /* Mega Filter */
                .mfilter-heading-content {
                    color: <?php echo $theme_options->get( 'main' ); ?> 
                }
                
                .mfilter-slider-slider .ui-slider-handle,
                #mfilter-price-slider .ui-slider-handle {
                    color: <?php echo $theme_options->get( 'main' ); ?> !important;
                }

                .mfilter-slider-slider .ui-slider-range,
                #mfilter-price-slider .ui-slider-range {
                    background: <?php echo $theme_options->get( 'main' ); ?> !important;
                }
                
                /* iCheck */
                .icheckbox.checked:before,
                .iradio.checked:before{
                    background-color: <?php echo $theme_options->get( 'main' ); ?>;
                    border-color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .information-contact .our-store a{
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                /* Blog */
                .post .date-published {
                    background: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .posts + .pagination li:hover a,
                .posts + .pagination li:hover span,
                .posts + .pagination li.active a,
                .posts + .pagination li.active span{
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                    border-color:<?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .post .meta-row span a {
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .post .comments-list .author .name{
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .news.v1 .media-body .date-published {
                    color: <?php echo $theme_options->get( 'main' ); ?>;   
                }
                
                .product-grid-template .product-grid .product.product-img-slider .owl-carousel .owl-item .item.active{
                    border-bottom-color:  <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .advanced-grid-latest-blogs .news .article-date-added i {
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                body .popup-module .mfp-close {
                    background:  <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                body .popup-module .newsletter-discount{
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                body .mfp-image-holder .mfp-close,
                body .mfp-iframe-holder .mfp-close {
                    background:  <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                .popup h4 {
                   color: <?php echo $theme_options->get( 'main' ); ?>; 
                }
                
                .category-wall .name a {
                    color: <?php echo $theme_options->get( 'main' ); ?>;
                }
                
                
                
		<?php } ?>
                
        <?php if($theme_options->get( 'main_text' ) != '') { ?>

                .button,
                .le-button,
                .btn,
                .newsletter .button,
                .search_form .button-search,
                .search_form .button-search2,
                #top #cart_block .cart-heading .basket-item-count .count,
                .megamenuToogle-wrapper .container,
                .vertical #menuHeading .megamenuToogle-wrapper .container:before,
                .tp-bullets.simplebullets.round .bullet:hover,
                .tp-bullets.simplebullets.round .bullet.selected,
                .filter-product .filter-tabs ul > li.active > a,
                .filter-product .filter-tabs ul > li.active > a:hover,
                .filter-product .filter-tabs ul > li.active > a:focus,
                .camera_wrap .owl-controls .owl-pagination .active span,
                .box-product .owl-pagination > div.active,
                .box-product .owl-pagination > div:hover,
                .product-grid .product:hover .image .quickview a,
                .product-grid .product:hover .image .quickview a,
                .product-list .row:hover .quickview a,
                .product-info .thumbnails-carousel:hover .owl-buttons .owl-prev:hover,
                .product-info .thumbnails-carousel:hover .owl-buttons .owl-next:hover,
                .htabs a.selected,
                .footer .contact-info .social-icons li a:hover,
                .zoomTint,
                .mfilter-slider-slider .ui-slider-range,
                #mfilter-price-slider .ui-slider-range,
                .icheckbox.checked:before,
                .iradio.checked:before,
                .post .date-published,
                body .popup-module .mfp-close,
                body .mfp-image-holder .mfp-close,
                body .mfp-iframe-holder .mfp-close {
                    color: <?php echo $theme_options->get( 'main_text' ); ?> !important;
                }

                
		<?php } ?>
        <?php if($theme_options->get( 'menu_hover' ) != '') { ?>
                
                    ul.megamenu > li > a:hover,
                    ul.megamenu > li.active > a,
                    ul.megamenu > li:hover > a {
                        background: <?php echo $theme_options->get( 'menu_hover' );  ?> !important;
                    }
                    
                    .vertical ul.megamenu > li:hover > a,
                    .vertical ul.megamenu > li.active > a {
                        border-color: <?php echo $theme_options->get( 'menu_hover' ); ?>
                    }

                    @media (max-width: 991px) {

                        .responsive ul.megamenu > li:hover,
                        .responsive ul.megamenu > li.active {
                            background: <?php echo $theme_options->get( 'menu_hover' ); ?>
                        }
                    }
                    
                    

		<?php } ?>
        <?php if($theme_options->get( 'menu_hover_text' ) != '') { ?>

                    ul.megamenu > li > a:hover,
                    ul.megamenu > li.active > a,
                    ul.megamenu > li:hover > a,
                    .vertical ul.megamenu > li:hover > a,
                    .vertical ul.megamenu > li.active > a,
                    .vertical ul.megamenu > li.click:before,
                    .vertical ul.megamenu > li.hover:before {
                        color: <?php echo $theme_options->get( 'menu_hover_text' );  ?> !important;
                    }

                    @media (max-width: 991px) {

                        .responsive ul.megamenu > li:hover,
                        .responsive ul.megamenu > li.with-sub-menu:hover > .open-menu,
                        .responsive ul.megamenu > li.active .close-menu{
                            color: <?php echo $theme_options->get( 'menu_hover_text' ); ?> !important;
                        }
                    }
                    

		<?php } ?>
        <?php if($theme_options->get( 'button_hover' ) != '') { ?>
                
                .button:hover,
                .le-button:hover,
                .btn:hover,
                .product-grid .product:hover .image .quickview a:hover,
                .product-list .row:hover .quickview a:hover{
                    background: <?php echo $theme_options->get( 'button_hover' ); ?>
                }
    
		<?php } ?>
        <?php if($theme_options->get( 'button_hover_text' ) != '') { ?>
                
                .button:hover,
                .le-button:hover,
                .btn:hover,
                .product-grid .product:hover .image .quickview a:hover,
                .product-list .row:hover .quickview a:hover{
                    color: <?php echo $theme_options->get( 'button_hover_text' ); ?> !important;
                }
    
		<?php } ?>
        <?php if($theme_options->get( 'links_hover' ) != '') { ?>
                
                a:hover {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .tp-leftarrow.default:hover,
                .tp-rightarrow.default:hover {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .btn-add-to-wishlist:hover,
                .btn-add-to-wishlist:hover i,
                .btn-add-to-compare:hover,
                .btn-add-to-compare:hover i{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }

                #top-bar .top-links li a:hover{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                ul.megamenu li .product .name a:hover{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .product-grid .product .name a:hover{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .product-grid .product .only-hover ul li a:hover,
                .product-grid .product .only-hover ul li a:hover:before {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .products-carousel-overflow > .prev:hover span:before, 
                .products-carousel-overflow > .next:hover span:before{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .box > .prev:hover span:before, 
                .box > .next:hover span:before {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .tab-content .prev-button:hover span:before, 
                .tab-content .next-button:hover span:before {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .advanced-grid-products .product .name a:hover{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .col-sm-3 .products .advanced-grid-products .product .name a:hover,
                .col-sm-4 .products .advanced-grid-products .product .name a:hover,
                .col-md-3 .products .advanced-grid-products .product .name a:hover,
                .col-md-4 .products .advanced-grid-products .product .name a:hover {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .col-sm-3 .products .row > div .product .name a:hover,
                .col-sm-4 .products .row > div .product .name a:hover,
                .col-md-3 .products .row > div .product .name a:hover,
                .col-md-4 .products .row > div .product .name a:hover{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .product-info .links a:hover,
                .product-info .links a:hover i{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .product-info .cart .add-to-cart .quantity #q_up:hover,
                .product-info .cart .add-to-cart .quantity #q_down:hover{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .product-filter .options .button-group button:hover,
                .product-filter .options .button-group .active {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .faq-area .faq-section .panel-faq .panel-heading .panel-title:hover a.collapsed,
                .faq-area .faq-section .panel-faq .panel-heading .panel-title a{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                .faq-area .faq-section .panel-faq .panel-heading .panel-title > a:after,
                .faq-area .faq-section .panel-faq .panel-heading .panel-title:hover > a.collapsed:after {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                .footer ul li a:hover {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>
                }
                
                                
                .camera_wrap:hover .owl-controls .owl-buttons .owl-prev:hover:before,
                .camera_wrap:hover .owl-controls .owl-buttons .owl-next:hover:before{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>;
				}
                
                /* Blog */
                .post .meta > li a:hover {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>;
                }
                .posts .pagination li:hover a,
                .posts .pagination li:hover span{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>;
                    border-color:<?php echo $theme_options->get( 'links_hover' ); ?>;
                }
                
                .posts .pagination-ajax .load-more:hover{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>;
                }
                
                .post .post-media .media-slider:hover .owl-next:hover,
                .post .post-media .media-slider:hover .owl-prev:hover{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>;
                }
                
                .post .post-media .media-slider:hover .owl-page.active span,
                .post .post-media .media-slider:hover .owl-page:hover span {
                    background: <?php echo $theme_options->get( 'links_hover' ); ?>;
                }
                

                .post .blog-post-author .media .media-heading:hover a{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>;
                }
                
                
                .blog-categories .box-category ul li a:hover {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>;
                }
                
                .blog-popular-posts .media a:hover h5,
                .blog-related-posts .media a:hover h5,
                .blog-product-related-posts .media a:hover h5,
                .blog-latest-posts .media a:hover h5{
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>;
                }
                
                .blog-tags .tagcloud a:hover {
                    color: <?php echo $theme_options->get( 'links_hover' ); ?>;
                }
			
    
		<?php } ?>

        <?php if($theme_options->get( 'menu_border' ) != '') { ?>

                .megamenu-wrapper ul.megamenu > li{
                  border-right-color: <?php echo $theme_options->get( 'menu_border' ); ?>
                }
                
                .megamenuToogle-wrapper {
                    border-bottom-color: <?php echo $theme_options->get( 'menu_border' ); ?>;
                }
    
		<?php } ?>
        <?php if($theme_options->get( 'mobile_switcher_bg' ) != '') { ?>

                .megamenuToogle-wrapper .container > div {
                    background-color: <?php echo $theme_options->get( 'mobile_switcher_bg' ); ?>
                }
    
		<?php } ?>
        <?php if($theme_options->get( 'mobile_switcher_border' ) != '') { ?>

                .megamenuToogle-wrapper .container > div {
                    border-color: <?php echo $theme_options->get( 'mobile_switcher_border' ); ?>
                }
    
		<?php } ?>
        <?php if($theme_options->get( 'mobile_switcher_text' ) != '') { ?>

                .megamenuToogle-wrapper .container > div > span{
                    background: <?php echo $theme_options->get( 'mobile_switcher_text' ); ?>
                }
    
		<?php } ?>
	<?php } ?>
               
			
	<?php if($theme_options->get( 'font_status' ) == '1') { ?>
		body {
			font-size: <?php echo $theme_options->get( 'body_font_px' ); ?>px;
			font-weight: <?php echo $theme_options->get( 'body_font_weight' )*100; ?>;
			<?php if( $theme_options->get( 'body_font' ) != '' && $theme_options->get( 'body_font' ) != 'standard' ) { ?>
			font-family: <?php echo $theme_options->get( 'body_font' ); ?>;
			<?php } ?>
		}
		
		#top-bar .container, 
		#top .header-links li a,
		.sale,
		.product-grid .product .only-hover ul li a,
		.hover-product .only-hover ul li a {
			font-size: <?php echo $theme_options->get( 'body_font_smaller_px' ); ?>px;
		}
		
		ul.megamenu > li > a strong {
			font-size: <?php echo $theme_options->get( 'categories_bar_px' ); ?>px;
			font-weight: <?php echo $theme_options->get( 'categories_bar_weight' )*100; ?>;
			<?php if( $theme_options->get( 'categories_bar' ) != '' && $theme_options->get( 'categories_bar' ) != 'standard' ) { ?>
			font-family: <?php echo $theme_options->get( 'categories_bar' ); ?>;
			<?php } ?>
		}
		
		.megamenuToogle-wrapper .container {
			font-weight: <?php echo $theme_options->get( 'categories_bar_weight' )*100; ?>;
			<?php if( $theme_options->get( 'categories_bar_font' ) != '' && $theme_options->get( 'categories_bar_font' ) != 'standard' ) { ?>
			font-family: <?php echo $theme_options->get( 'categories_bar_font' ); ?>;
			<?php } ?>
		}
		
		.vertical ul.megamenu > li > a strong {
			font-weight: <?php echo $theme_options->get( 'body_font_weight' )*100; ?>;
		}
		
		.box .box-heading,
		.center-column h1, 
		.center-column h2, 
		.center-column h3, 
		.center-column h4, 
		.center-column h5, 
		.center-column h6,
		.products-carousel-overflow .box-heading {
			font-size: <?php echo $theme_options->get( 'headlines_px' ); ?>px;
			font-weight: <?php echo $theme_options->get( 'headlines_weight' )*100; ?>;
			<?php if( $theme_options->get( 'headlines' ) != '' && $theme_options->get( 'headlines' ) != 'standard' ) { ?>
			font-family: <?php echo $theme_options->get( 'headlines' ); ?>;
			<?php } ?>
		}
		
		.footer h4,
		.custom-footer h4 {
			font-size: <?php echo $theme_options->get( 'footer_headlines_px' ); ?>px;
			font-weight: <?php echo $theme_options->get( 'footer_headlines_weight' )*100; ?>;
			<?php if( $theme_options->get( 'footer_headlines' ) != '' && $theme_options->get( 'footer_headlines' ) != 'standard' ) { ?>
			font-family: <?php echo $theme_options->get( 'footer_headlines' ); ?>;
			<?php } ?>
		}
		
		.breadcrumb .container h1 {
			font-size: <?php echo $theme_options->get( 'page_name_px' ); ?>px;
			font-weight: <?php echo $theme_options->get( 'page_name_weight' )*100; ?>;
			<?php if( $theme_options->get( 'page_name' ) != '' && $theme_options->get( 'page_name' ) != 'standard' ) { ?>
			font-family: <?php echo $theme_options->get( 'page_name' ); ?>;
			<?php } ?>
		}
		
		.button,
		.btn {
			font-size: <?php echo $theme_options->get( 'button_font_px' ); ?>px;
			font-weight: <?php echo $theme_options->get( 'button_font_weight' )*100; ?>;
			<?php if( $theme_options->get( 'button_font' ) != '' && $theme_options->get( 'button_font' ) != 'standard' ) { ?>
			font-family: <?php echo $theme_options->get( 'button_font' ); ?>;
			<?php } ?>
		}
		
		<?php if( $theme_options->get( 'custom_price' ) != '' && $theme_options->get( 'custom_price' ) != 'standard' ) { ?>
		.product-grid .product .price, 
		.hover-product .price, 
		.product-list .actions > div .price, 
		.product-info .price .price-new,
		ul.megamenu li .product .price,
		.advanced-grid-products .product .right .price {
			font-family: <?php echo $theme_options->get( 'custom_price' ); ?>;
		}
		<?php } ?>
		
		.product-grid .product .price,
		.advanced-grid-products .product .right .price {
			font-size: <?php echo $theme_options->get( 'custom_price_px_small' ); ?>px;
			font-weight: <?php echo $theme_options->get( 'custom_price_weight' )*100; ?>;
		}
		
		.product-info .price .price-new {
			font-size: <?php echo $theme_options->get( 'custom_price_px' ); ?>px;
			font-weight: <?php echo $theme_options->get( 'custom_price_weight' )*100; ?>;
		}
		
		.product-list .actions > div .price {
			font-size: <?php echo $theme_options->get( 'custom_price_px_medium' ); ?>px;
			font-weight: <?php echo $theme_options->get( 'custom_price_weight' )*100; ?>;
		}
		
		.price-old {
			font-size: <?php echo $theme_options->get( 'custom_price_px_old_price' ); ?>px;
			font-weight: <?php echo $theme_options->get( 'custom_price_weight' )*100; ?>;
		}
	<?php } ?>
</style>
<?php } ?>

<?php if($theme_options->get( 'background_status' ) == 1) { ?>
<style type="text/css">
	<?php if($theme_options->get( 'body_background_background' ) == '1') { ?> 
	body { background-image:none !important; }
	<?php } ?>
	<?php if($theme_options->get( 'body_background_background' ) == '2') { ?> 
	body { background-image:url(image/<?php echo $theme_options->get( 'body_background' ); ?>);background-position:<?php echo $theme_options->get( 'body_background_position' ); ?>;background-repeat:<?php echo $theme_options->get( 'body_background_repeat' ); ?> !important;background-attachment:<?php echo $theme_options->get( 'body_background_attachment' ); ?> !important; }
	<?php } ?>
	<?php if($theme_options->get( 'body_background_background' ) == '3') { ?> 
	body { background-image:url(image/subtle_patterns/<?php echo $theme_options->get( 'body_background_subtle_patterns' ); ?>);background-position:<?php echo $theme_options->get( 'body_background_position' ); ?>;background-repeat:<?php echo $theme_options->get( 'body_background_repeat' ); ?> !important;background-attachment:<?php echo $theme_options->get( 'body_background_attachment' ); ?> !important; }
	<?php } ?>
	
	<?php if($theme_options->get( 'header_background_background' ) == '1') { ?> 
	header { background-image:none !important; }
	<?php } ?>
	<?php if($theme_options->get( 'header_background_background' ) == '2') { ?> 
	header { background-image:url(image/<?php echo $theme_options->get( 'header_background' ); ?>);background-position:<?php echo $theme_options->get( 'header_background_position' ); ?>;background-repeat:<?php echo $theme_options->get( 'header_background_repeat' ); ?> !important;background-attachment:<?php echo $theme_options->get( 'header_background_attachment' ); ?> !important; }
	<?php } ?>
	<?php if($theme_options->get( 'header_background_background' ) == '3') { ?> 
	header { background-image:url(image/subtle_patterns/<?php echo $theme_options->get( 'header_background_subtle_patterns' ); ?>);background-position:<?php echo $theme_options->get( 'header_background_position' ); ?>;background-repeat:<?php echo $theme_options->get( 'header_background_repeat' ); ?> !important;background-attachment:<?php echo $theme_options->get( 'header_background_attachment' ); ?> !important; }
	<?php } ?>
	
	<?php if($theme_options->get( 'customfooter_background_background' ) == '1') { ?> 
	.custom-footer .pattern { background-image:none !important; }
	<?php } ?>
	<?php if($theme_options->get( 'customfooter_background_background' ) == '2') { ?> 
	.custom-footer .pattern { background-image:url(image/<?php echo $theme_options->get( 'customfooter_background' ); ?>);background-position:<?php echo $theme_options->get( 'customfooter_background_position' ); ?>;background-repeat:<?php echo $theme_options->get( 'customfooter_background_repeat' ); ?> !important;background-attachment:<?php echo $theme_options->get( 'customfooter_background_attachment' ); ?> !important; }
	<?php } ?>
	<?php if($theme_options->get( 'customfooter_background_background' ) == '3') { ?> 
	.custom-footer .pattern { background-image:url(image/subtle_patterns/<?php echo $theme_options->get( 'customfooter_background_subtle_patterns' ); ?>);background-position:<?php echo $theme_options->get( 'customfooter_background_position' ); ?>;background-repeat:<?php echo $theme_options->get( 'customfooter_background_repeat' ); ?> !important;background-attachment:<?php echo $theme_options->get( 'customfooter_background_attachment' ); ?> !important; }
	<?php } ?>
	
	<?php if($theme_options->get( 'content_headlines_background_background' ) == '1') { ?> 
	.box .strip-line,
	.breadcrumb .container .strip-line,
	.products-carousel-overflow .strip-line { background-image:none !important; }
	<?php } ?>
	<?php if($theme_options->get( 'content_headlines_background_background' ) == '2') { ?> 
	.box .strip-line,
	.breadcrumb .container .strip-line,
	.products-carousel-overflow .strip-line { background-image:url(image/<?php echo $theme_options->get( 'content_headlines_background' ); ?>); }
	<?php } ?>
	
	<?php if($theme_options->get( 'footer_headlines_background_background' ) == '1') { ?> 
	.footer .strip-line { background-image:none !important; }
	<?php } ?>
	<?php if($theme_options->get( 'footer_headlines_background_background' ) == '2') { ?> 
	.footer .strip-line { background-image:url(image/<?php echo $theme_options->get( 'footer_headlines_background' ); ?>); }
	<?php } ?>
</style>
<?php } ?>


<style type="text/css">
    <?php if($theme_options->get( 'sale_text_bg' ) != '') { ?>
        .sale.ribbon:after{
            border-top-color: <?php echo $theme_options->get( 'sale_text_bg' )?> !important;
        }
        .label-discount{
            background-color: <?php echo $theme_options->get( 'sale_text_bg' )?> !important;
        }
    <?php } ?>
    <?php if($theme_options->get( 'bestseller_text_bg' ) != '') { ?>
        .bestseller.ribbon:after{
            border-top-color: <?php echo $theme_options->get( 'bestseller_text_bg' )?> !important;
        }
    <?php } ?>
    <?php if($theme_options->get( 'latest_text_bg' ) != '') { ?>
        .latest.ribbon:after{
            border-top-color: <?php echo $theme_options->get( 'latest_text_bg' )?> !important;
        }
    <?php } ?>
        
</style>