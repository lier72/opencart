<?php $grid_center = 12; 
if($column_left != '') $grid_center = $grid_center-3; 
if($column_right != '') $grid_center = $grid_center-3; 
$modules = new Modules($this->registry);       
?>

<!-- BREADCRUMB
	================================================== -->
<div class="breadcrumb <?php if($theme_options->get( 'breadcrumb_layout' ) == 2) { echo 'fixed'; } else { echo 'full-width'; } ?>">
	<div class="background-breadcrumb"></div>
	<div class="background">
		<div class="shadow"></div>
		<div class="pattern">
			<div class="container">
				<div class="clearfix">
					<ul>
						<?php
                        $i = 0;
                        $next_cat_id = 0;
                        foreach ($breadcrumbs as $breadcrumb) {
                            $cats = array();
                            if($this->registry->get('request')->get['route'] == 'product/product' || $this->registry->get('request')->get['route'] == 'product/category'){
                                if($i == 1 ){
                                    $cats = $theme_options->getCategories(0);
                                }else if($next_cat_id > 0 && count($breadcrumbs)-1 > $i){
                                    $cats = $theme_options->getCategories($next_cat_id);
                                }
                            }
                            
                            
                        ?>
						<li class="item <?php echo !empty($cats) ? 'dropdown' : '';?>">
                            <a <?php echo !empty($cats) ? 'class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"' : '';?>
                                href="<?php echo $breadcrumb['href']; ?>"><?php if($breadcrumb['text'] != '<i class="fa fa-home"></i>') { echo $breadcrumb['text']; } else { if($theme_options->get( 'home_text', $config->get( 'config_language_id' ) ) != '') { echo $theme_options->get( 'home_text', $config->get( 'config_language_id' ) ); } else { echo 'Home'; } } ?></a>
                        
                            <?php if(!empty($cats)):?>
                            <ul class="dropdown-menu">
                                <li>
                                    <?php foreach($cats as $cat):?>
                                    <?php if($cat['label'] == $breadcrumb['text']):?>
                                        <?php $next_cat_id = $cat['category_id']; continue; ?>
                                    <?php endif; ?>
                                    <a href="<?php echo $cat['href'] ?>"><?php echo $cat['label'] ?></a>
                                    <?php endforeach; ?>
                                </li>
                            </ul>
                            <?php endif; ?>
                        
                        </li>
						<?php $i++; } ?>
					</ul>
<!--					<h1 id="title-page"><?php echo $heading_title; ?>
						<?php if(isset($weight)) { if ($weight) { ?>
						&nbsp;(<?php echo $weight; ?>)
						<?php } } ?>
					</h1>
					<div class="strip-line"></div>-->
				</div>
			</div>
		</div>
	</div>
</div>

<!-- MAIN CONTENT
	================================================== -->

<?php if($theme_options->get('contact_map') == 1 && $this->registry->get('request')->get['route'] == 'information/contact'):?>
<script src="//maps.google.com/maps/api/js?sensor=true"></script>

<div class="contact-map">
    <div id="map"></div>
    <div class="get-direction">
        <div class="container">
            <div class="row">
                <div class="center-block col-lg-10">
                    <div class="input-group">
                        <input type="text" id="starting-point" class="le-input input-lg form-control" placeholder="<?php echo $theme_options->get( 'enter_your_starting_point_text', $config->get( 'config_language_id' ) ) != '' ? $theme_options->get( 'enter_your_starting_point_text', $config->get( 'config_language_id' ) ) : 'Enter Your Starting Point';  ?>">
                        <span class="input-group-btn">
                            <button id="get-direction" class="btn btn-lg le-button" type="button"><?php echo $theme_options->get( 'get_directions_text', $config->get( 'config_language_id' ) ) != '' ? $theme_options->get( 'get_directions_text', $config->get( 'config_language_id' ) ) : 'Get Directions';  ?></button>
                        </span>
                    </div>
                    <!-- /input-group -->
                </div>
                <!-- /.col-lg-6 -->
            </div>
            <!-- /.row -->
        </div>
    </div>
</div>

<script type="text/javascript">   
    
    var GMAP = {
        zoom : 16,
        latitude : <?php echo $theme_options->get('contact_map_lat') ?>,
        longitude : <?php echo $theme_options->get('contact_map_lng') ?>,
        mapIsNotActive : true,
        map: null,
        geocoder: null,
        directionsService : null,
        directionsDisplay : null,
        marker: null,
        
        setCustomMap: function(){
            if ($('.contact-map').length > 0 && GMAP.mapIsNotActive) {

                var styles = [
                    {
                        "featureType": "landscape",
                        "elementType": "geometry",
                        "stylers": [
                            {
                                "visibility": "simplified"
                            },
                            {
                                "color": "#E6E6E6"
                            }
                        ]
                    }, {
                        "featureType": "administrative",
                        "stylers": [
                            {
                                "visibility": "simplified"
                            }
                        ]
                    }, {
                        "featureType": "road",
                        "elementType": "geometry",
                        "stylers": [
                            {
                                "visibility": "on"
                            },
                            {
                                "saturation": -100
                            }
                        ]
                    }, {
                        "featureType": "road.highway",
                        "elementType": "geometry.fill",
                        "stylers": [
                            {
                                "color": "#808080"
                            },
                            {
                                "visibility": "on"
                            }
                        ]
                    }, {
                        "featureType": "water",
                        "stylers": [
                            {
                                "color": "#CECECE"
                            },
                            {
                                "visibility": "on"
                            }
                        ]
                    }, {
                        "featureType": "poi",
                        "stylers": [
                            {
                                "visibility": "on"
                            }
                        ]
                    }, {
                        "featureType": "poi",
                        "elementType": "geometry",
                        "stylers": [
                            {
                                "color": "#E5E5E5"
                            },
                            {
                                "visibility": "on"
                            }
                        ]
                    }, {
                        "featureType": "road.local",
                        "elementType": "geometry",
                        "stylers": [
                            {
                                "color": "#ffffff"
                            },
                            {
                                "visibility": "on"
                            }
                        ]
                    }, {}
                ];


                var options = {
                    mapTypeControlOptions: {
                        mapTypeIds: ['Styled']
                    },
                    center: new google.maps.LatLng(GMAP.latitude, GMAP.longitude),
                    zoom: GMAP.zoom,
                    disableDefaultUI: true,
                    scrollwheel: false,
                    mapTypeId: 'Styled'
                };
                var div = document.getElementById('map');

                GMAP.map = new google.maps.Map(div, options);

                var styledMapType = new google.maps.StyledMapType(styles, {
                    name: 'Styled'
                });

                GMAP.marker = new google.maps.Marker({
                    position: new google.maps.LatLng(GMAP.latitude, GMAP.longitude),
                    map: GMAP.map
                });

                GMAP.map.mapTypes.set('Styled', styledMapType);

                GMAP.mapIsNotActive = false;
                GMAP.geocoder = new google.maps.Geocoder();
                GMAP.directionsService = new google.maps.DirectionsService();
                GMAP.directionsDisplay = new google.maps.DirectionsRenderer();
                GMAP.directionsDisplay.setMap(GMAP.map);
            }
        },
                
        showRoute: function(address){

            var start = address;
            var end = GMAP.marker.getPosition();
            var request = {
                origin:start,
                destination:end,
                travelMode: google.maps.TravelMode.DRIVING
            };
            GMAP.directionsService.route(request, function(response, status) {
              if (status == google.maps.DirectionsStatus.OK) {
                GMAP.directionsDisplay.setDirections(response);
              }
            });
        }
        
    };
    
$(function() {  

    GMAP.setCustomMap();
    
    $('.contact-map #get-direction').click(function(){
        GMAP.showRoute($('#starting-point').val());
    })


});  
</script>

<?php endif; ?>


<div class="main-content <?php if($theme_options->get( 'content_layout' ) == 2) { echo 'fixed'; } else { echo 'full-width'; } ?> inner-page">
	<div class="background-content"></div>
	<div class="background">
		<div class="shadow"></div>
		<div class="pattern">
			<div class="container">
				<?php 
				$preface_left = $modules->getModules('preface_left');
				$preface_right = $modules->getModules('preface_right');
				?>
				<?php if( count($preface_left) || count($preface_right) ) { ?>
				<div class="row">
					<div class="col-sm-9">
						<?php
						if( count($preface_left) ) {
							foreach ($preface_left as $module) {
								echo $module;
							}
						} ?>
					</div>
					
					<div class="col-sm-3">
						<?php
						if( count($preface_right) ) {
							foreach ($preface_right as $module) {
								echo $module;
							}
						} ?>
					</div>
				</div>
				<?php } ?>
				
				<?php 
				$preface_fullwidth = $modules->getModules('preface_fullwidth');
				if( count($preface_fullwidth) ) {
					echo '<div class="row"><div class="col-sm-12">';
					foreach ($preface_fullwidth as $module) {
						echo $module;
					}
					echo '</div></div>';
				} ?>
				
				<div class="row">
					<?php 
					$columnleft = $modules->getModules('column_left');
					if( count($columnleft) ) { ?>
					<div class="col-md-3" id="column-left">
						<?php
						foreach ($columnleft as $module) {
							echo $module;
						}
						?>
					</div>
					<?php } ?>
					
					<?php $grid_center = 12; if( count($columnleft) ) { $grid_center = 9; } ?>
					<div class="col-md-<?php echo $grid_center; ?>">
						<?php 
						$content_big_column = $modules->getModules('content_big_column');
						if( count($content_big_column) ) { 
							foreach ($content_big_column as $module) {
								echo $module;
							}
						} ?>
						
						<?php 
						$content_top = $modules->getModules('content_top');
						if( count($content_top) ) { 
							foreach ($content_top as $module) {
								echo $module;
							}
						} ?>
						
						<div class="row">
							<?php 
							$grid_content_top = 12; 
							$grid_content_right = 3;
							$column_right = $modules->getModules('column_right'); 
							if( count($column_right) ) {
								if($grid_center == 9) {
									$grid_content_top = 8;
									$grid_content_right = 4;
								} else {
									$grid_content_top = 9;
									$grid_content_right = 3;
								}
							}
							?>
							<div class="col-md-<?php echo $grid_content_top; ?> center-column" id="content">

								<?php if (isset($error_warning)) { ?>
									<?php if ($error_warning) { ?>
									<div class="warning">
										<button type="button" class="close" data-dismiss="alert">&times;</button>
										<?php echo $error_warning; ?>
									</div>
									<?php } ?>
								<?php } ?>
								
								<?php if (isset($success)) { ?>
									<?php if ($success) { ?>
									<div class="success">
										<button type="button" class="close" data-dismiss="alert">&times;</button>
										<?php echo $success; ?>
									</div>
									<?php } ?>
								<?php } ?>