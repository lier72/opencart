<!-- HEADER
	================================================== -->
<?php
$search_cats = $theme_options->getAllCategories();
$category_id = $theme_options->getCurrentCategory();
?>

<header>
	<div class="background-header"></div>
	<div class="slider-header">
		<!-- Top Bar -->
		<div id="top-bar" class="<?php if($theme_options->get( 'top_bar_layout' ) == 2) { echo 'fixed'; } else { echo 'full-width'; } ?>">
			<div class="background-top-bar"></div>
			<div class="background">
				<div class="shadow"></div>
				<div class="pattern">
					<div class="container">
						<div class="row">
							<!-- Top Bar Left -->
							<div class="col-xs-12 col-sm-6" id="top-bar-left">
								<!-- Top Links -->
								<ul class="top-links">
									<li><a href="<?php echo $home; ?>" class="link"><?php echo $text_home; ?></a></li>
									<li><a href="<?php echo $contact; ?>" class="link"><?php echo $theme_options->get( 'contact_text', $config->get( 'config_language_id' ) ) != '' ? $theme_options->get( 'contact_text', $config->get( 'config_language_id' ) ) : 'Contact';  ?></a></li>
                                    <li><a href="<?php echo $account; ?>" class="link"><?php echo $text_account; ?></a></li>
								</ul>
							</div>
							
							<!-- Top Bar Right -->
							<div class="col-xs-12 col-sm-6" id="top-bar-right">
								<ul class="top-links">
                                    <?php echo $language.$currency; ?>
                                    <?php if (!$logged): ?>
									<li><a href="<?php echo $register; ?>" class="link"><?php echo $text_register; ?></a></li>
									<li><a href="<?php echo $login; ?>" class="link"><?php echo $text_login; ?></a></li>
                                    <?php else: ?>
                                    <li><a href="<?php echo $logout; ?>" class="link"><?php echo $text_logout; ?></a></li>
                                    <?php endif; ?>
                                </ul>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Top of pages -->
		<div id="top" class="<?php if($theme_options->get( 'header_layout' ) == 2) { echo 'fixed'; } else { echo 'full-width'; } ?>">
			<div class="background-top"></div>
			<div class="background">
				<div class="shadow"></div>
				<div class="pattern">
					<div class="container">
						<div class="row">
							<!-- Header Left -->
							<div class="col-xs-12 col-md-3" id="header-left">
								<?php if ($logo) { ?>
								<!-- Logo -->
								<div class="logo"><a href="<?php echo $home; ?>"><img src="<?php echo $logo; ?>" title="<?php echo $name; ?>" alt="<?php echo $name; ?>" /></a></div>
                                <?php } else{ ?> 
                                <div class="logo">
                                    <a href="<?php echo $home; ?>">
                                    <svg width="233px" height="54px" viewBox="0 0 233 54" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                        <path fill="#ffffff" d=" M 0.00 0.00 L 233.00 0.00 L 233.00 54.00 L 0.00 54.00 L 0.00 0.00 Z"></path>
                                        <path class="logo-svg" d=" M 104.82 6.46 C 107.74 5.13 110.66 3.80 113.58 2.46 C 113.59 18.71 113.59 34.95 113.59 51.20 C 110.65 51.20 107.71 51.20 104.78 51.20 C 104.84 50.67 104.96 49.62 105.02 49.09 C 100.02 53.56 91.66 52.69 87.37 47.67 C 84.80 44.83 83.96 40.97 83.20 37.33 C 75.63 37.37 68.05 37.26 60.47 37.40 C 61.41 39.88 62.49 42.75 65.24 43.71 C 69.03 45.31 73.10 43.58 75.89 40.91 C 77.67 42.73 79.47 44.54 81.22 46.40 C 75.60 52.47 65.66 53.95 58.77 49.23 C 53.06 45.18 51.58 37.52 52.30 30.95 C 52.75 25.29 55.84 19.51 61.29 17.27 C 66.83 15.00 73.85 15.40 78.54 19.37 C 81.58 21.92 82.87 25.85 83.50 29.64 C 84.32 24.24 87.32 18.69 92.71 16.75 C 96.83 15.07 101.64 15.89 104.93 18.89 C 104.77 14.75 104.83 10.60 104.82 6.46 Z"></path>
                                        <path class="logo-svg" d=" M 118.44 3.44 C 121.37 1.09 126.26 3.21 126.52 6.97 C 127.17 10.89 122.39 14.12 119.00 12.03 C 115.74 10.46 115.39 5.41 118.44 3.44 Z"></path>
                                        <path class="logo-svg" d=" M 10.22 19.63 C 14.71 14.58 23.51 14.56 27.42 20.34 C 33.58 13.24 48.54 14.42 50.33 24.80 C 51.41 33.54 50.55 42.41 50.82 51.20 C 47.88 51.20 44.94 51.20 42.00 51.20 C 41.92 44.09 42.14 36.97 41.93 29.87 C 41.95 27.41 40.48 24.57 37.76 24.43 C 34.66 23.72 30.88 25.51 30.74 29.01 C 30.35 36.39 30.71 43.80 30.59 51.20 C 27.67 51.20 24.75 51.20 21.82 51.20 C 21.74 44.12 21.98 37.04 21.73 29.96 C 21.79 27.24 19.97 24.22 16.95 24.37 C 13.91 23.84 10.58 25.90 10.49 29.15 C 10.13 36.49 10.47 43.85 10.35 51.20 C 7.43 51.20 4.51 51.20 1.59 51.20 C 1.59 39.69 1.59 28.18 1.59 16.67 C 4.53 16.67 7.47 16.67 10.41 16.67 C 10.36 17.41 10.27 18.89 10.22 19.63 Z"></path>
                                        <path class="logo-svg" d=" M 129.25 19.84 C 135.49 16.15 143.23 14.75 150.23 16.89 C 154.92 18.35 157.47 23.34 157.42 28.02 C 157.56 35.75 157.42 43.47 157.47 51.20 C 154.57 51.20 151.68 51.20 148.79 51.20 C 148.84 50.65 148.93 49.56 148.98 49.01 C 144.10 52.49 137.26 53.09 132.09 49.91 C 126.05 46.27 125.51 36.43 131.16 32.19 C 136.26 28.22 143.30 28.77 149.13 30.64 C 148.62 28.53 148.91 25.65 146.65 24.49 C 141.77 22.26 136.27 24.40 131.93 26.90 C 131.04 24.55 130.14 22.19 129.25 19.84 Z"></path>
                                        <path class="logo-svg" d=" M 117.06 16.67 C 119.98 16.67 122.90 16.67 125.82 16.67 C 125.82 28.18 125.82 39.69 125.82 51.20 C 122.90 51.20 119.98 51.20 117.06 51.20 C 117.06 39.69 117.06 28.18 117.06 16.67 Z"></path>
                                        <path fill="#3a3a3a" d=" M 201.86 17.62 C 202.52 17.30 203.82 16.67 204.48 16.35 C 204.48 18.10 204.48 19.84 204.48 21.59 C 205.23 21.59 206.73 21.60 207.48 21.60 C 207.48 22.17 207.48 23.30 207.48 23.86 C 206.73 23.87 205.22 23.89 204.47 23.91 C 204.49 26.42 204.34 28.95 204.62 31.45 C 205.86 32.02 207.13 32.53 208.42 32.99 C 206.71 34.03 204.25 35.64 202.51 33.72 C 201.29 30.65 202.08 27.15 201.88 23.91 C 201.39 23.89 200.42 23.87 199.94 23.86 C 199.94 23.30 199.94 22.18 199.94 21.62 C 200.43 21.60 201.41 21.57 201.90 21.56 C 201.88 20.24 201.87 18.93 201.86 17.62 Z"></path>
                                        <path fill="#3a3a3a" d=" M 169.01 34.60 C 161.80 34.48 161.85 21.38 169.03 21.30 C 171.56 20.91 173.24 22.99 174.55 24.80 C 172.38 25.03 170.35 23.99 168.21 24.05 C 165.19 25.78 165.69 32.04 169.72 32.24 C 171.04 30.86 172.68 30.26 174.58 30.81 C 173.29 32.73 171.68 35.03 169.01 34.60 Z"></path>
                                        <path fill="#3a3a3a" d=" M 176.14 24.15 C 177.71 20.91 182.63 20.34 185.06 22.88 C 186.61 24.47 186.37 26.86 186.72 28.88 C 183.63 28.96 180.54 28.94 177.46 29.07 C 178.33 30.06 178.74 31.71 180.15 32.08 C 182.15 31.99 184.30 31.15 185.94 32.84 C 183.84 33.87 181.50 35.30 179.09 34.28 C 175.09 32.89 174.46 27.52 176.14 24.15 Z"></path>
                                        <path fill="#3a3a3a" d=" M 188.27 21.67 C 191.44 22.12 195.65 20.04 197.89 23.12 C 199.40 26.61 198.50 30.58 198.71 34.28 C 197.84 34.27 196.97 34.27 196.10 34.26 C 196.04 31.26 196.36 28.21 195.84 25.24 C 195.30 22.95 190.93 23.34 191.03 25.84 C 190.71 28.63 190.93 31.46 190.90 34.27 C 190.25 34.27 188.94 34.27 188.28 34.27 C 188.28 30.07 188.28 25.87 188.27 21.67 Z"></path>
                                        <path fill="#3a3a3a" d=" M 209.34 24.33 C 210.88 20.80 216.32 20.24 218.67 23.23 C 219.85 24.84 219.70 26.96 220.06 28.84 C 216.93 28.95 213.79 28.96 210.67 29.17 C 211.42 30.17 212.14 31.20 213.03 32.08 C 214.53 32.45 215.99 31.66 217.44 31.37 C 217.81 31.81 218.56 32.68 218.93 33.11 C 216.82 34.02 214.49 35.28 212.18 34.20 C 208.39 32.73 207.86 27.62 209.34 24.33 Z"></path>
                                        <path fill="#3a3a3a" d=" M 221.57 21.68 C 224.18 22.17 229.35 19.73 229.26 23.99 C 227.69 24.37 225.10 23.31 224.48 25.41 C 223.89 28.32 224.25 31.33 224.17 34.27 C 223.52 34.27 222.23 34.27 221.58 34.27 C 221.58 30.07 221.58 25.88 221.57 21.68 Z"></path>
                                        <path fill="#ffffff" d=" M 177.47 26.72 C 178.35 25.45 179.09 23.45 180.99 23.60 C 182.85 23.48 183.54 25.46 184.45 26.68 C 182.12 26.79 179.79 26.80 177.47 26.72 Z"></path>
                                        <path fill="#ffffff" d=" M 210.63 26.63 C 211.70 25.38 212.59 23.21 214.62 23.63 C 216.40 23.58 216.80 25.59 217.66 26.73 C 215.32 26.80 212.97 26.77 210.63 26.63 Z"></path>
                                        <path fill="#ffffff" d=" M 60.57 29.87 C 61.45 26.41 64.15 23.26 68.04 23.58 C 71.91 23.17 74.23 26.62 75.30 29.84 C 70.39 29.99 65.48 29.97 60.57 29.87 Z"></path>
                                        <path fill="#ffffff" d=" M 96.52 24.70 C 99.50 23.55 102.54 25.02 104.87 26.85 C 104.80 31.61 104.80 36.38 104.86 41.14 C 102.46 43.09 99.18 44.42 96.17 43.02 C 92.82 41.46 91.99 37.36 91.96 34.01 C 91.89 30.51 92.82 26.06 96.52 24.70 Z"></path>
                                        <path fill="#ffffff" d=" M 137.58 37.63 C 141.09 35.82 145.16 36.85 148.82 37.59 C 148.82 38.98 148.80 40.38 148.79 41.78 C 145.51 43.89 141.25 45.34 137.54 43.42 C 135.23 42.33 135.28 38.72 137.58 37.63 Z"></path>
                                        <path class="logo-svg" d=" M 163.30 39.16 C 165.64 38.00 168.47 38.66 171.01 38.49 C 172.96 38.53 176.17 38.23 176.35 40.94 C 176.51 46.79 170.77 51.96 165.05 51.93 C 161.43 51.79 162.41 47.39 162.23 44.97 C 162.49 43.09 161.71 40.56 163.30 39.16 Z"></path>
                                    </svg>
                                    </a>
                                </div>
                                <?php } ?>
							</div>
							
							<!-- Header Center -->
							<div class="col-xs-12 col-md-6 no-margin" id="header-center">									
								<!-- Search -->
                                <?php $header_block = $modules->getModules('header_block'); ?>
                                <?php 
                                if( count($header_block) ) {
                                    foreach ($header_block as $module) {
                                        echo $module;
                                    }
                                }
                                ?>
								<div class="search_form">
									<div class="button-search"></div>
									<input type="text" class="input-block-level search-query" name="search" placeholder="<?php echo $theme_options->get( 'search_for_products_text', $config->get( 'config_language_id' ) ) != '' ? $theme_options->get( 'search_for_products_text', $config->get( 'config_language_id' ) ) : 'Search for products';  ?>" id="search_query" value="" />
                                    <div class="input-layer"></div>
                                    <div class="search-cat">
                                        <select name="category_id" class="form-control">
                                          <option value="0"><?php echo $theme_options->get( 'all_categories_text', $config->get( 'config_language_id' ) ) != '' ? $theme_options->get( 'all_categories_text', $config->get( 'config_language_id' ) ) : 'All categories';  ?></option>
                                          <?php foreach ($search_cats as $category_1) { ?>
                                          <?php if ($category_1['category_id'] == $category_id) { ?>
                                          <option value="<?php echo $category_1['category_id']; ?>" selected="selected"><?php echo $category_1['name']; ?></option>
                                          <?php } else { ?>
                                          <option value="<?php echo $category_1['category_id']; ?>"><?php echo $category_1['name']; ?></option>
                                          <?php } ?>
                                          <?php foreach ($category_1['children'] as $category_2) { ?>
                                          <?php if ($category_2['category_id'] == $category_id) { ?>
                                          <option value="<?php echo $category_2['category_id']; ?>" selected="selected">&nbsp;&nbsp;<?php echo $category_2['name']; ?></option>
                                          <?php } else { ?>
                                          <option value="<?php echo $category_2['category_id']; ?>">&nbsp;&nbsp;<?php echo $category_2['name']; ?></option>
                                          <?php } ?>
                                          <?php foreach ($category_2['children'] as $category_3) { ?>
                                          <?php if ($category_3['category_id'] == $category_id) { ?>
                                          <option value="<?php echo $category_3['category_id']; ?>" selected="selected">&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $category_3['name']; ?></option>
                                          <?php } else { ?>
                                          <option value="<?php echo $category_3['category_id']; ?>">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $category_3['name']; ?></option>
                                          <?php } ?>
                                          <?php } ?>
                                          <?php } ?>
                                          <?php } ?>
                                        </select>
                                    </div>
									<?php if($theme_options->get( 'quick_search_autosuggest' ) != '0') { ?>
										<div id="autocomplete-results" class="autocomplete-results"></div>
										
										<script type="text/javascript">
										$(document).ready(function() {
                                            
											$('#search_query').autocomplete({
												delay: 0,
												appendTo: "#autocomplete-results",
												source: function(request, response) {	
                                            
                                                    var category_filter = $('header select[name=\'category_id\']').val();
                                                    var category_filter_url = '';
                                                    if (category_filter) {
                                                        category_filter_url = '&filter_category_id=' + encodeURIComponent(category_filter);
                                                    }
													$.ajax({
														url: 'index.php?route=search/autocomplete&filter_name=' +  encodeURIComponent(request.term) + category_filter_url,
														dataType: 'json',
														success: function(json) {
															response($.map(json, function(item) {
																return {
																	label: item.name,
																	value: item.product_id,
																	href: item.href,
																	thumb: item.thumb,
																	desc: item.desc,
																	price: item.price
																}
															}));
														}
													});
												},
												select: function(event, ui) {
													document.location.href = ui.item.href;
													
													return false;
												},
												focus: function(event, ui) {
											      	return false;
											   	},
											   	minLength: 2
											})
											.data( "ui-autocomplete" )._renderItem = function( ul, item ) {
											  return $( "<li>" )
											    .append( "<a><img src='" + item.thumb + "' alt=''>" + item.label + "<br><span class='description'>" + item.desc + "</span><br><span class='price'>" + item.price + "</span></a>" )
											    .appendTo( ul );
											};
										});
										</script>
									<?php } ?>
                                         <div class="clearfix"></div>
								</div>
								
							</div>
							
							<!-- Header Right -->
							<div class="col-xs-12 col-md-3 no-margin" id="header-right">
                                <div class="top-cart-row-container">
                                    <div class="wishlist-compare-holder">
                                        <div class="wishlist ">
                                            <a href="<?php echo $wishlist; ?>"  id="wishlist-total"><i class="fa fa-heart"></i> <?php echo $text_wishlist; ?></a>
                                        </div>
                                        <div class="compare">
                                            <a href="<?php echo $theme_options->getModel('url')->link('product/compare'); ?>" id="compare-total"><i class="fa fa-exchange"></i> <?php echo $theme_options->get( 'compare_text', $config->get( 'config_language_id' ) ) != '' ? $theme_options->get( 'compare_text', $config->get( 'config_language_id' ) ) : 'Compare';  ?> <span class="value">(<?php echo $theme_options->getCompareCount() ?>)</span></a>
                                        </div>
                                    </div>
                                    <?php echo $cart; ?>
                                </div>
							</div>
						</div>
					</div>
					
					<?php 
					$menu = $modules->getModules('menu');
					if( count($menu) ) {
						foreach ($menu as $module) {
							echo $module;
						}
					} elseif($categories) {
					?>
					<div class="container-megamenu horizontal">
						<div class="megaMenuToggle">
							<div class="megamenuToogle-wrapper">
								<div class="megamenuToogle-pattern">
									<div class="container">
										<div><span></span><span></span><span></span></div>
										Navigation
									</div>
								</div>
							</div>
						</div>
						
						<div class="megamenu-wrapper">
							<div class="megamenu-pattern">
								<div class="container">
									<ul class="megamenu slide">
										<?php foreach ($categories as $category) { ?>
										<?php if ($category['children']) { ?>
										<li class="with-sub-menu hover"><p class="close-menu"></p><p class="open-menu"></p>
											<a href="<?php echo $category['href'];?>"><span><strong><?php echo $category['name']; ?></strong></span></a>
										<?php } else { ?>
										<li>
											<a href="<?php echo $category['href']; ?>"><span><strong><?php echo $category['name']; ?></strong></span></a>
										<?php } ?>
											<?php if ($category['children']) { ?>
											<?php 
												$width = '100%';
												$row_fluid = 3;
												if($category['column'] == 1) { $width = '220px'; $row_fluid = 12; }
												if($category['column'] == 2) { $width = '500px'; $row_fluid = 6; }
												if($category['column'] == 3) { $width = '700px'; $row_fluid = 4; }
											?>
											<div class="sub-menu" style="width: <?php echo $width; ?>">
												<div class="content">
													<p class="arrow"></p>
													<div class="row hover-menu">
														<?php for ($i = 0; $i < count($category['children']);) { ?>
														<div class="col-sm-<?php echo $row_fluid; ?> mobile-enabled">
															<div class="menu">
																<ul>
																  <?php $j = $i + ceil(count($category['children']) / $category['column']); ?>
																  <?php for (; $i < $j; $i++) { ?>
																  <?php if (isset($category['children'][$i])) { ?>
																  <li><a href="<?php echo $category['children'][$i]['href']; ?>" onclick="window.location = '<?php echo $category['children'][$i]['href']; ?>';"><?php echo $category['children'][$i]['name']; ?></a></li>
																  <?php } ?>
																  <?php } ?>
																</ul>
															</div>
														</div>
														<?php } ?>
													</div>
												</div>
											</div>
											<?php } ?>
										</li>
										<?php } ?>
									</ul>
								</div>
							</div>
						</div>
					</div>
					<?php
					}
					?>
				</div>
			</div>
		</div>
	</div>
	
	<?php $slideshow = $modules->getModules('slideshow'); ?>
	<?php  if(count($slideshow)) { ?>
	<!-- Slider -->
	<div id="slider" class="<?php if($theme_options->get( 'slideshow_layout' ) == 2) { echo 'fixed'; } else { echo 'full-width'; } ?>">
		<div class="background-slider"></div>
		<div class="background">
			<div class="shadow"></div>
			<div class="pattern">
				<?php foreach($slideshow as $module) { ?>
				<?php echo $module; ?>
				<?php } ?>
			</div>
		</div>
	</div>
	<?php } ?>
</header>
