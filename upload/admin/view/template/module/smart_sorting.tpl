<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  
  <div id="SmartSorting">
  
    <div class="page-header">
      <div class="container-fluid">
        <div class="pull-right">
          <button type="button" data-submission_type="save" data-code="<?php echo $code; ?>" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary">
            <i class="fa fa-save"></i>
          </button>        
          <button  type="button" data-submission_type="save-and-close" data-code="<?php echo $code; ?>" data-toggle="tooltip" title="<?php echo $button_save_and_close; ?>" class="btn btn-info">
            <i class="fa fa-save"></i>
          </button>        
          <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
        </div>
        <h1><?php echo $heading_title; ?> <span class="version badge"><?php echo $version; ?></span></h1>
        <ul class="breadcrumb">
          <?php foreach ($breadcrumbs as $breadcrumb) { ?>
          <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
          <?php } ?>
        </ul>
      </div>
    </div>
    <div class="container-fluid">    
      
      <?php if ($error_warning) { ?>
        <div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      <?php } ?>

      <?php if ($success) { ?>
        <div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle"></i> <?php echo $success; ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      <?php } ?>    

      <div id="fountainG" class="ajax-loader">
        <div id="fountainG_1" class="fountainG"></div>
        <div id="fountainG_2" class="fountainG"></div>
        <div id="fountainG_3" class="fountainG"></div>
        <div id="fountainG_4" class="fountainG"></div>
        <div id="fountainG_5" class="fountainG"></div>
        <div id="fountainG_6" class="fountainG"></div>
        <div id="fountainG_7" class="fountainG"></div>
        <div id="fountainG_8" class="fountainG"></div>
      </div>
      
      <div class="panel panel-default">

        <div class="panel-heading">
          <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
        </div>

        <div class="panel-body">

          <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-<?php echo $code; ?>" class="form-horizontal" autocomplete="off">

            <ul id="global-tabs" class="nav nav-tabs">
              <li class="active"><a href="#tab-smart-sorting" data-toggle="tab"><i class="fa fa-sort green" aria-hidden="true"></i><?php echo $tab_smart_sorting; ?></a></li>
              <li><a href="#tab-config" data-toggle="tab"><i class="fa fa-cog violet"></i><?php echo $tab_config; ?></a></li>
              <li><a href="#tab-help" data-toggle="tab"><i class="fa fa-life-ring marine-blue" aria-hidden="true"></i><?php echo $tab_help; ?> <span class="badge badge-tab-help"></span></a></li>
            </ul>

            <div class="tab-content">

              <div id="tab-smart-sorting" class="tab-pane active">

                <ul id="smart-sorting-tabs" class="nav nav-tabs">
                  <li class="active"><a href="#tab-smart-sorting-category" data-toggle="tab"><i class="fa fa-tags green" aria-hidden="true"></i><?php echo $tab_smart_sorting_category; ?></a></li>
                  <li><a href="#tab-smart-sorting-manufacturer" data-toggle="tab"><i class="fa fa-wrench violet" aria-hidden="true"></i><?php echo $tab_smart_sorting_manufacturer; ?></a></li>
                  <li><a href="#tab-smart-sorting-search" data-toggle="tab"><i class="fa fa-search marine-blue" aria-hidden="true"></i><?php echo $tab_smart_sorting_search; ?></a></li>
                  <li><a href="#tab-smart-sorting-special" data-toggle="tab"><i class="fa fa-cube orange" aria-hidden="true"></i><?php echo $tab_smart_sorting_special; ?></a></li>
                </ul>
                
                <div class="tab-content">
                  
                  <div id="tab-smart-sorting-category" class="tab-pane active">
                    
                    <div class="vtabs">
                      <div class="col-sm-3">
                        <ul id="smart-sorting-category-store-tabs" class="vtabs-tab-container nav nav-tabs tabs-left"><!--<ul class="vtabs-tab-container nav nav-pills nav-stacked">-->
                          <?php foreach($stores as $store) { ?>
                            <li class="<?php echo $store['store_id'] == 0 ? "active" : ""; ?>">
                              <a href="#tab-smart-sorting-category-store-<?php echo $store['store_id']; ?>" data-toggle="tab">
                                <span class="<?php echo ((bool)${$code}[$store['store_id']]['category_status'] === false) ? 'red' : '';  ?>"><?php echo $store['name']; ?></span>
                              </a>
                            </li>
                          <?php } ?>
                        </ul>
                      </div>
                      <!-- .col -->

                      <hr class="hidden-lg hidden-md hidden-sm" />

                      <div class="vtabs-content-container tab-content col-sm-9">
                        
                        <?php foreach($stores as $store) { ?>
                        
                          <fieldset id="tab-smart-sorting-category-store-<?php echo $store['store_id']; ?>" class="tab-pane <?php echo $store['store_id'] == 0 ? "active" : ""; ?>">

                            <legend><?php echo $store['name']; ?> - <?php echo $text_heading_category; ?></legend>

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-category-status">
                                <span data-toggle="tooltip" title="<?php echo $help_category_status; ?>"><?php echo $entry_category_status; ?></span>
                              </label>
                              <div class="col-sm-10">
                                <div class="row">
                                  <div class="col-sm-6">
                                    <select name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][category_status]" id="input-store-<?php echo $store['store_id']; ?>-category-status" class="form-control">
                                      <?php if (${$code}[$store['store_id']]['category_status']) { ?>
                                      <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                                      <option value="0"><?php echo $text_disabled; ?></option>
                                      <?php } else { ?>
                                      <option value="1"><?php echo $text_enabled; ?></option>
                                      <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                                      <?php } ?>
                                    </select>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <hr />

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-category-disable-sorting-methods">
                                <?php echo $entry_category_disable_sorting_methods; ?></span>
                              </label>
                              <div class="col-sm-10">
                                <div class="well well-sm well-scrollable mbm">
                                  <input type="hidden" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][category_disable_sorting_methods][]" value="" />
                                  <?php foreach ($category_sorting_methods as $category_sorting_method) { ?>
                                  <div class="checkbox">
                                    <label>
                                      <input type="checkbox" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][category_disable_sorting_methods][]" value="<?php echo $category_sorting_method['value']; ?>" <?php echo in_array($category_sorting_method['value'], ${$code}[$store['store_id']]['category_disable_sorting_methods']) ? 'checked' : ''; ?> />
                                      <?php echo $category_sorting_method['text']; ?>
                                    </label>
                                  </div>
                                  <?php } ?>
                                </div>
                                <div class="check-uncheck-all-container">
                                  <a href="#" class="well-check-all btn btn-info btn-sm"><?php echo $text_check_all; ?></a>
                                  <a href="#" class="well-uncheck-all btn btn-warning btn-sm"><?php echo $text_uncheck_all; ?></a>
                                </div>                      
                              </div>
                            </div>

                            <div class="space-10"></div>

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-category-default-sort-order">
                                <?php echo $entry_category_default_sort_order; ?>
                              </label>
                              <div class="col-sm-10">
                                <div class="row">
                                  <div class="col-sm-6">
                                    <select name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][category_default_sort_order]" id="input-store-<?php echo $store['store_id']; ?>-category-default-sort-order" class="form-control">
                                      <?php foreach ($category_sorting_methods as $category_sorting_method) { ?>
                                        <option value="<?php echo $category_sorting_method['value']; ?>" <?php echo $category_sorting_method['value'] == ${$code}[$store['store_id']]['category_default_sort_order'] ? 'selected="selected"' : ''; ?>><?php echo $category_sorting_method['text']; ?></option>
                                      <?php } ?>
                                    </select>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <hr />
                            
                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-category-product-limit-status"></label>
                              <div class="col-sm-10">
                                <div class="bs-switch bs-switch-common" data-bs-switch-toggle-target="toggle-store-<?php echo $store['store_id']; ?>-category-product-limit-status">
                                  <input type="hidden" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][category_product_limit_status]" value="0" />
                                  <div class="checkbox">
                                    <label>
                                      <input type="checkbox" id="input-store-<?php echo $store['store_id']; ?>-category-product-limit-status" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][category_product_limit_status]" value="1" <?php echo (bool)${$code}[$store['store_id']]['category_product_limit_status'] === true ? 'checked' : ''; ?> />
                                      <?php echo $entry_category_product_limit_status; ?>
                                    </label>
                                  </div>
                                </div>
                              </div>
                            </div>                            
                            
                            <div class="<?php echo ((bool)${$code}[$store['store_id']]['category_product_limit_status'] === true ? 'open' : 'closed'); ?>" data-bs-switch-toggle-id="toggle-store-<?php echo $store['store_id']; ?>-category-product-limit-status">
                            
                              <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-category-product-limits">
                                  <span data-toggle="tooltip" title="<?php echo $help_category_product_limits; ?>"><?php echo $entry_category_product_limits; ?></span>
                                </label>
                                <div class="col-sm-10">
                                  <div class="row">
                                    <div class="col-sm-6">
                                      <input type="text" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][category_product_limits]" value="<?php echo ${$code}[$store['store_id']]['category_product_limits']; ?>" id="input-store-<?php echo $store['store_id']; ?>-category-product-limits" class="form-control" />
                                    </div>
                                  </div>
                                </div>
                              </div>
                              <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-category-default-product-limit">
                                  <span data-toggle="tooltip" title="<?php echo $help_category_default_product_limit; ?>"><?php echo $entry_category_default_product_limit; ?></span>
                                </label>
                                <div class="col-sm-10">
                                  <div class="row">
                                    <div class="col-sm-3 col-md-2">
                                      <input type="number" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][category_default_product_limit]" value="<?php echo ${$code}[$store['store_id']]['category_default_product_limit']; ?>" id="input-store-<?php echo $store['store_id']; ?>-category-default-product-limit" class="form-control" min="1" />
                                    </div>
                                  </div>
                                </div>
                              </div>                   
                            </div>
 
                          </fieldset>
                        
                        <?php } ?>

                      </div>
                      <!-- /.vtabs-content-container -->
                    </div>
                    <!-- /.vtabs -->

                  </div>
                  <!-- /.tab-pane -->
                  
                  <div id="tab-smart-sorting-manufacturer" class="tab-pane">

                    <div class="vtabs">
                      <div class="col-sm-3">
                        <ul id="smart-sorting-manufacturer-store-tabs" class="vtabs-tab-container nav nav-tabs tabs-left"><!--<ul class="vtabs-tab-container nav nav-pills nav-stacked">-->
                          <?php foreach($stores as $store) { ?>
                            <li class="<?php echo $store['store_id'] == 0 ? "active" : ""; ?>">
                              <a href="#tab-smart-sorting-manufacturer-store-<?php echo $store['store_id']; ?>" data-toggle="tab">
                                <span class="<?php echo ((bool)${$code}[$store['store_id']]['manufacturer_status'] === false) ? 'red' : '';  ?>"><?php echo $store['name']; ?></span>
                              </a>
                            </li>
                          <?php } ?>
                        </ul>
                      </div>
                      <!-- .col -->

                      <hr class="hidden-lg hidden-md hidden-sm" />

                      <div class="vtabs-content-container tab-content col-sm-9">
                        
                        <?php foreach($stores as $store) { ?>
                        
                          <fieldset id="tab-smart-sorting-manufacturer-store-<?php echo $store['store_id']; ?>" class="tab-pane <?php echo $store['store_id'] == 0 ? "active" : ""; ?>">

                            <legend><?php echo $store['name']; ?> - <?php echo $text_heading_manufacturer; ?></legend>

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-manufacturer-status">
                                <span data-toggle="tooltip" title="<?php echo $help_manufacturer_status; ?>"><?php echo $entry_manufacturer_status; ?></span>
                              </label>
                              <div class="col-sm-10">
                                <div class="row">
                                  <div class="col-sm-6">
                                    <select name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][manufacturer_status]" id="input-store-<?php echo $store['store_id']; ?>-manufacturer-status" class="form-control">
                                      <?php if (${$code}[$store['store_id']]['manufacturer_status']) { ?>
                                      <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                                      <option value="0"><?php echo $text_disabled; ?></option>
                                      <?php } else { ?>
                                      <option value="1"><?php echo $text_enabled; ?></option>
                                      <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                                      <?php } ?>
                                    </select>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <hr />

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-manufacturer-disable-sorting-methods">
                                <?php echo $entry_manufacturer_disable_sorting_methods; ?></span>
                              </label>
                              <div class="col-sm-10">
                                <div class="well well-sm well-scrollable mbm">
                                  <input type="hidden" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][manufacturer_disable_sorting_methods][]" value="" />
                                  <?php foreach ($manufacturer_sorting_methods as $manufacturer_sorting_method) { ?>
                                  <div class="checkbox">
                                    <label>
                                      <input type="checkbox" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][manufacturer_disable_sorting_methods][]" value="<?php echo $manufacturer_sorting_method['value']; ?>" <?php echo in_array($manufacturer_sorting_method['value'], ${$code}[$store['store_id']]['manufacturer_disable_sorting_methods']) ? 'checked' : ''; ?> />
                                      <?php echo $manufacturer_sorting_method['text']; ?>
                                    </label>
                                  </div>
                                  <?php } ?>
                                </div>
                                <div class="check-uncheck-all-container">
                                  <a href="#" class="well-check-all btn btn-info btn-sm"><?php echo $text_check_all; ?></a>
                                  <a href="#" class="well-uncheck-all btn btn-warning btn-sm"><?php echo $text_uncheck_all; ?></a>
                                </div>                      
                              </div>
                            </div>

                            <div class="space-10"></div>

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-manufacturer-default-sort-order">
                                <?php echo $entry_manufacturer_default_sort_order; ?>
                              </label>
                              <div class="col-sm-10">
                                <div class="row">
                                  <div class="col-sm-6">
                                    <select name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][manufacturer_default_sort_order]" id="input-store-<?php echo $store['store_id']; ?>-manufacturer-default-sort-order" class="form-control">
                                      <?php foreach ($manufacturer_sorting_methods as $manufacturer_sorting_method) { ?>
                                        <option value="<?php echo $manufacturer_sorting_method['value']; ?>" <?php echo $manufacturer_sorting_method['value'] == ${$code}[$store['store_id']]['manufacturer_default_sort_order'] ? 'selected="selected"' : ''; ?>><?php echo $manufacturer_sorting_method['text']; ?></option>
                                      <?php } ?>
                                    </select>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <hr />
                            
                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-manufacturer-product-limit-status"></label>
                              <div class="col-sm-10">
                                <div class="bs-switch bs-switch-common" data-bs-switch-toggle-target="toggle-store-<?php echo $store['store_id']; ?>-manufacturer-product-limit-status">
                                  <input type="hidden" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][manufacturer_product_limit_status]" value="0" />
                                  <div class="checkbox">
                                    <label>
                                      <input type="checkbox" id="input-store-<?php echo $store['store_id']; ?>-manufacturer-product-limit-status" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][manufacturer_product_limit_status]" value="1" <?php echo (bool)${$code}[$store['store_id']]['manufacturer_product_limit_status'] === true ? 'checked' : ''; ?> />
                                      <?php echo $entry_manufacturer_product_limit_status; ?>
                                    </label>
                                  </div>
                                </div>
                              </div>
                            </div>                            
                            
                            <div class="<?php echo ((bool)${$code}[$store['store_id']]['manufacturer_product_limit_status'] === true ? 'open' : 'closed'); ?>" data-bs-switch-toggle-id="toggle-store-<?php echo $store['store_id']; ?>-manufacturer-product-limit-status">
                            
                              <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-manufacturer-product-limits">
                                  <span data-toggle="tooltip" title="<?php echo $help_manufacturer_product_limits; ?>"><?php echo $entry_manufacturer_product_limits; ?></span>
                                </label>
                                <div class="col-sm-10">
                                  <div class="row">
                                    <div class="col-sm-6">
                                      <input type="text" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][manufacturer_product_limits]" value="<?php echo ${$code}[$store['store_id']]['manufacturer_product_limits']; ?>" id="input-store-<?php echo $store['store_id']; ?>-manufacturer-product-limits" class="form-control" />
                                    </div>
                                  </div>
                                </div>
                              </div>
                              <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-manufacturer-default-product-limit">
                                  <span data-toggle="tooltip" title="<?php echo $help_manufacturer_default_product_limit; ?>"><?php echo $entry_manufacturer_default_product_limit; ?></span>
                                </label>
                                <div class="col-sm-10">
                                  <div class="row">
                                    <div class="col-sm-3 col-md-2">
                                      <input type="number" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][manufacturer_default_product_limit]" value="<?php echo ${$code}[$store['store_id']]['manufacturer_default_product_limit']; ?>" id="input-store-<?php echo $store['store_id']; ?>-manufacturer-default-product-limit" class="form-control" min="1" />
                                    </div>
                                  </div>
                                </div>
                              </div>                   
                            </div>
 
                          </fieldset>
                        
                        <?php } ?>

                      </div>
                      <!-- /.vtabs-content-container -->
                    </div>
                    <!-- /.vtabs -->
                    
                  </div>
                  <!-- /.tab-pane -->
                  
                  <div id="tab-smart-sorting-search" class="tab-pane">

                    <div class="vtabs">
                      <div class="col-sm-3">
                        <ul id="smart-sorting-search-store-tabs" class="vtabs-tab-container nav nav-tabs tabs-left"><!--<ul class="vtabs-tab-container nav nav-pills nav-stacked">-->
                          <?php foreach($stores as $store) { ?>
                            <li class="<?php echo $store['store_id'] == 0 ? "active" : ""; ?>">
                              <a href="#tab-smart-sorting-search-store-<?php echo $store['store_id']; ?>" data-toggle="tab">
                                <span class="<?php echo ((bool)${$code}[$store['store_id']]['search_status'] === false) ? 'red' : '';  ?>"><?php echo $store['name']; ?></span>
                              </a>
                            </li>
                          <?php } ?>
                        </ul>
                      </div>
                      <!-- .col -->

                      <hr class="hidden-lg hidden-md hidden-sm" />

                      <div class="vtabs-content-container tab-content col-sm-9">
                        
                        <?php foreach($stores as $store) { ?>
                        
                          <fieldset id="tab-smart-sorting-search-store-<?php echo $store['store_id']; ?>" class="tab-pane <?php echo $store['store_id'] == 0 ? "active" : ""; ?>">

                            <legend><?php echo $store['name']; ?> - <?php echo $text_heading_search; ?></legend>

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-search-status">
                                <span data-toggle="tooltip" title="<?php echo $help_search_status; ?>"><?php echo $entry_search_status; ?></span>
                              </label>
                              <div class="col-sm-10">
                                <div class="row">
                                  <div class="col-sm-6">
                                    <select name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][search_status]" id="input-store-<?php echo $store['store_id']; ?>-search-status" class="form-control">
                                      <?php if (${$code}[$store['store_id']]['search_status']) { ?>
                                      <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                                      <option value="0"><?php echo $text_disabled; ?></option>
                                      <?php } else { ?>
                                      <option value="1"><?php echo $text_enabled; ?></option>
                                      <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                                      <?php } ?>
                                    </select>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <hr />

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-search-disable-sorting-methods">
                                <?php echo $entry_search_disable_sorting_methods; ?></span>
                              </label>
                              <div class="col-sm-10">
                                <div class="well well-sm well-scrollable mbm">
                                  <input type="hidden" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][search_disable_sorting_methods][]" value="" />
                                  <?php foreach ($search_sorting_methods as $search_sorting_method) { ?>
                                  <div class="checkbox">
                                    <label>
                                      <input type="checkbox" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][search_disable_sorting_methods][]" value="<?php echo $search_sorting_method['value']; ?>" <?php echo in_array($search_sorting_method['value'], ${$code}[$store['store_id']]['search_disable_sorting_methods']) ? 'checked' : ''; ?> />
                                      <?php echo $search_sorting_method['text']; ?>
                                    </label>
                                  </div>
                                  <?php } ?>
                                </div>
                                <div class="check-uncheck-all-container">
                                  <a href="#" class="well-check-all btn btn-info btn-sm"><?php echo $text_check_all; ?></a>
                                  <a href="#" class="well-uncheck-all btn btn-warning btn-sm"><?php echo $text_uncheck_all; ?></a>
                                </div>                      
                              </div>
                            </div>

                            <div class="space-10"></div>

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-search-default-sort-order">
                                <?php echo $entry_search_default_sort_order; ?>
                              </label>
                              <div class="col-sm-10">
                                <div class="row">
                                  <div class="col-sm-6">
                                    <select name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][search_default_sort_order]" id="input-store-<?php echo $store['store_id']; ?>-search-default-sort-order" class="form-control">
                                      <?php foreach ($search_sorting_methods as $search_sorting_method) { ?>
                                        <option value="<?php echo $search_sorting_method['value']; ?>" <?php echo $search_sorting_method['value'] == ${$code}[$store['store_id']]['search_default_sort_order'] ? 'selected="selected"' : ''; ?>><?php echo $search_sorting_method['text']; ?></option>
                                      <?php } ?>
                                    </select>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <hr />
                            
                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-search-product-limit-status"></label>
                              <div class="col-sm-10">
                                <div class="bs-switch bs-switch-common" data-bs-switch-toggle-target="toggle-store-<?php echo $store['store_id']; ?>-search-product-limit-status">
                                  <input type="hidden" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][search_product_limit_status]" value="0" />
                                  <div class="checkbox">
                                    <label>
                                      <input type="checkbox" id="input-store-<?php echo $store['store_id']; ?>-search-product-limit-status" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][search_product_limit_status]" value="1" <?php echo (bool)${$code}[$store['store_id']]['search_product_limit_status'] === true ? 'checked' : ''; ?> />
                                      <?php echo $entry_search_product_limit_status; ?>
                                    </label>
                                  </div>
                                </div>
                              </div>
                            </div>                            
                            
                            <div class="<?php echo ((bool)${$code}[$store['store_id']]['search_product_limit_status'] === true ? 'open' : 'closed'); ?>" data-bs-switch-toggle-id="toggle-store-<?php echo $store['store_id']; ?>-search-product-limit-status">
                            
                              <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-search-product-limits">
                                  <span data-toggle="tooltip" title="<?php echo $help_search_product_limits; ?>"><?php echo $entry_search_product_limits; ?></span>
                                </label>
                                <div class="col-sm-10">
                                  <div class="row">
                                    <div class="col-sm-6">
                                      <input type="text" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][search_product_limits]" value="<?php echo ${$code}[$store['store_id']]['search_product_limits']; ?>" id="input-store-<?php echo $store['store_id']; ?>-search-product-limits" class="form-control" />
                                    </div>
                                  </div>
                                </div>
                              </div>
                              <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-search-default-product-limit">
                                  <span data-toggle="tooltip" title="<?php echo $help_search_default_product_limit; ?>"><?php echo $entry_search_default_product_limit; ?></span>
                                </label>
                                <div class="col-sm-10">
                                  <div class="row">
                                    <div class="col-sm-3 col-md-2">
                                      <input type="number" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][search_default_product_limit]" value="<?php echo ${$code}[$store['store_id']]['search_default_product_limit']; ?>" id="input-store-<?php echo $store['store_id']; ?>-search-default-product-limit" class="form-control" min="1" />
                                    </div>
                                  </div>
                                </div>
                              </div>                   
                            </div>
 
                          </fieldset>
                        
                        <?php } ?>

                      </div>
                      <!-- /.vtabs-content-container -->
                    </div>
                    <!-- /.vtabs -->
                    
                  </div>
                  <!-- /.tab-pane -->
                  
                  <div id="tab-smart-sorting-special" class="tab-pane">

                    <div class="vtabs">
                      <div class="col-sm-3">
                        <ul id="smart-sorting-special-store-tabs" class="vtabs-tab-container nav nav-tabs tabs-left"><!--<ul class="vtabs-tab-container nav nav-pills nav-stacked">-->
                          <?php foreach($stores as $store) { ?>
                            <li class="<?php echo $store['store_id'] == 0 ? "active" : ""; ?>">
                              <a href="#tab-smart-sorting-special-store-<?php echo $store['store_id']; ?>" data-toggle="tab">
                                <span class="<?php echo ((bool)${$code}[$store['store_id']]['special_status'] === false) ? 'red' : '';  ?>"><?php echo $store['name']; ?></span>
                              </a>
                            </li>
                          <?php } ?>
                        </ul>
                      </div>
                      <!-- .col -->

                      <hr class="hidden-lg hidden-md hidden-sm" />

                      <div class="vtabs-content-container tab-content col-sm-9">
                        
                        <?php foreach($stores as $store) { ?>
                        
                          <fieldset id="tab-smart-sorting-special-store-<?php echo $store['store_id']; ?>" class="tab-pane <?php echo $store['store_id'] == 0 ? "active" : ""; ?>">

                            <legend><?php echo $store['name']; ?> - <?php echo $text_heading_special; ?></legend>

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-special-status">
                                <span data-toggle="tooltip" title="<?php echo $help_special_status; ?>"><?php echo $entry_special_status; ?></span>
                              </label>
                              <div class="col-sm-10">
                                <div class="row">
                                  <div class="col-sm-6">
                                    <select name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][special_status]" id="input-store-<?php echo $store['store_id']; ?>-special-status" class="form-control">
                                      <?php if (${$code}[$store['store_id']]['special_status']) { ?>
                                      <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                                      <option value="0"><?php echo $text_disabled; ?></option>
                                      <?php } else { ?>
                                      <option value="1"><?php echo $text_enabled; ?></option>
                                      <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                                      <?php } ?>
                                    </select>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <hr />

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-special-disable-sorting-methods">
                                <?php echo $entry_special_disable_sorting_methods; ?></span>
                              </label>
                              <div class="col-sm-10">
                                <div class="well well-sm well-scrollable mbm">
                                  <input type="hidden" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][special_disable_sorting_methods][]" value="" />
                                  <?php foreach ($special_sorting_methods as $special_sorting_method) { ?>
                                  <div class="checkbox">
                                    <label>
                                      <input type="checkbox" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][special_disable_sorting_methods][]" value="<?php echo $special_sorting_method['value']; ?>" <?php echo in_array($special_sorting_method['value'], ${$code}[$store['store_id']]['special_disable_sorting_methods']) ? 'checked' : ''; ?> />
                                      <?php echo $special_sorting_method['text']; ?>
                                    </label>
                                  </div>
                                  <?php } ?>
                                </div>
                                <div class="check-uncheck-all-container">
                                  <a href="#" class="well-check-all btn btn-info btn-sm"><?php echo $text_check_all; ?></a>
                                  <a href="#" class="well-uncheck-all btn btn-warning btn-sm"><?php echo $text_uncheck_all; ?></a>
                                </div>                      
                              </div>
                            </div>

                            <div class="space-10"></div>

                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-special-default-sort-order">
                                <?php echo $entry_special_default_sort_order; ?>
                              </label>
                              <div class="col-sm-10">
                                <div class="row">
                                  <div class="col-sm-6">
                                    <select name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][special_default_sort_order]" id="input-store-<?php echo $store['store_id']; ?>-special-default-sort-order" class="form-control">
                                      <?php foreach ($special_sorting_methods as $special_sorting_method) { ?>
                                        <option value="<?php echo $special_sorting_method['value']; ?>" <?php echo $special_sorting_method['value'] == ${$code}[$store['store_id']]['special_default_sort_order'] ? 'selected="selected"' : ''; ?>><?php echo $special_sorting_method['text']; ?></option>
                                      <?php } ?>
                                    </select>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <hr />
                            
                            <div class="form-group">
                              <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-special-product-limit-status"></label>
                              <div class="col-sm-10">
                                <div class="bs-switch bs-switch-common" data-bs-switch-toggle-target="toggle-store-<?php echo $store['store_id']; ?>-special-product-limit-status">
                                  <input type="hidden" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][special_product_limit_status]" value="0" />
                                  <div class="checkbox">
                                    <label>
                                      <input type="checkbox" id="input-store-<?php echo $store['store_id']; ?>-special-product-limit-status" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][special_product_limit_status]" value="1" <?php echo (bool)${$code}[$store['store_id']]['special_product_limit_status'] === true ? 'checked' : ''; ?> />
                                      <?php echo $entry_special_product_limit_status; ?>
                                    </label>
                                  </div>
                                </div>
                              </div>
                            </div>                            
                            
                            <div class="<?php echo ((bool)${$code}[$store['store_id']]['special_product_limit_status'] === true ? 'open' : 'closed'); ?>" data-bs-switch-toggle-id="toggle-store-<?php echo $store['store_id']; ?>-special-product-limit-status">
                            
                              <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-special-product-limits">
                                  <span data-toggle="tooltip" title="<?php echo $help_special_product_limits; ?>"><?php echo $entry_special_product_limits; ?></span>
                                </label>
                                <div class="col-sm-10">
                                  <div class="row">
                                    <div class="col-sm-6">
                                      <input type="text" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][special_product_limits]" value="<?php echo ${$code}[$store['store_id']]['special_product_limits']; ?>" id="input-store-<?php echo $store['store_id']; ?>-special-product-limits" class="form-control" />
                                    </div>
                                  </div>
                                </div>
                              </div>
                              <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-store-<?php echo $store['store_id']; ?>-special-default-product-limit">
                                  <span data-toggle="tooltip" title="<?php echo $help_special_default_product_limit; ?>"><?php echo $entry_special_default_product_limit; ?></span>
                                </label>
                                <div class="col-sm-10">
                                  <div class="row">
                                    <div class="col-sm-3 col-md-2">
                                      <input type="number" name="<?php echo $code; ?>[<?php echo $store['store_id']; ?>][special_default_product_limit]" value="<?php echo ${$code}[$store['store_id']]['special_default_product_limit']; ?>" id="input-store-<?php echo $store['store_id']; ?>-special-default-product-limit" class="form-control" min="1" />
                                    </div>
                                  </div>
                                </div>
                              </div>                   
                            </div>
 
                          </fieldset>
                        
                        <?php } ?>

                      </div>
                      <!-- /.vtabs-content-container -->
                    </div>
                    <!-- /.vtabs --> 
                    
                  </div>
                  <!-- /.tab-pane -->
                  
                  
                </div>
                <!-- /#tab-smart-sorting .tab-content -->
                
              </div>
              <!-- /#tab-smart-sorting .tab-pane -->

              <div id="tab-config" class="tab-pane">

                <fieldset>
                  <legend><?php echo $text_general; ?></legend>

                  <div class="form-group">
                    <label class="col-sm-2 control-label" for="input-config-status">
                      <span data-toggle="tooltip" title="<?php echo $help_config_status; ?>"><?php echo $entry_config_status; ?></span>
                    </label>
                    <div class="col-sm-4">
                      <select name="config[<?php echo $code; ?>_status]" id="input-config-status" class="form-control">
                        <?php if ($config[$code . '_status']) { ?>
                        <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                        <option value="0"><?php echo $text_disabled; ?></option>
                        <?php } else { ?>
                        <option value="1"><?php echo $text_enabled; ?></option>
                        <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                        <?php } ?>
                      </select>
                    </div>
                  </div>

                  <hr />

                  <div class="form-group">
                    <label class="col-sm-2 control-label" for="input-config-js-debug">
                      <span data-toggle="tooltip" title="<?php echo $help_config_js_debug; ?>"><?php echo $entry_config_js_debug; ?></span>
                    </label>
                    <div class="col-sm-10">
                      <div class="bs-switch bs-switch-common" data-bs-switch-toggle-target="">
                        <input type="hidden" name="config[<?php echo $code; ?>_config][js_debug]" value="0" />
                        <div class="checkbox">
                          <label>
                            <input type="checkbox" id="input-config-js-debug" class="input-config-js-debug" name="config[<?php echo $code; ?>_config][js_debug]" value="1" <?php echo $config[$code . '_config']['js_debug'] == 1 ? 'checked' : ''; ?> />
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>

                  <hr />

                  <div class="form-group">
                    <label class="col-sm-2 control-label" for="input-config-sold-time-range">
                      <span data-toggle="tooltip" title="<?php echo $help_config_sold_time_range; ?>"><?php echo $entry_config_sold_time_range; ?></span>
                    </label>
                    <div class="col-sm-10">
                      <div class="row">
                        <div class="col-sm-3 col-md-2">
                          <input type="number" id="input-config-sold-time-range" class="input-config-sold-time-range form-control" name="config[<?php echo $code; ?>_config][sold_time_range]" value="<?php echo $config[$code . '_config']['sold_time_range']; ?>" min="0" /> 
                        </div>
                      </div>
                    </div>
                  </div>
                          
                  <hr />

                  <div class="form-group">
                    <label class="col-sm-2 control-label" for="input-config-reviewed-time-range">
                      <span data-toggle="tooltip" title="<?php echo $help_config_reviewed_time_range; ?>"><?php echo $entry_config_reviewed_time_range; ?></span>
                    </label>
                    <div class="col-sm-10">
                      <div class="row">
                        <div class="col-sm-3 col-md-2">
                          <input type="number" id="input-config-reviewed-time-range" class="input-config-reviewed-time-range form-control" name="config[<?php echo $code; ?>_config][reviewed_time_range]" value="<?php echo $config[$code . '_config']['reviewed_time_range']; ?>" min="0" /> 
                        </div>
                      </div>
                    </div>
                  </div>
                  
                </fieldset>

              </div>
              <!-- /.tab-pane -->              
              
              <div id="tab-help" class="tab-pane">               
                <div class="row">
                  <div class="col-sm-6">
                    <h3><i class="fa fa-key green"></i> License information</h3>
                    <div class="alert-lic-notice-container"></div>
                    <p>The following table shows your current license information:</p>
                    <table class="table table-striped table-lic-info">
                      <tbody>
                        <tr>
                          <td class="text-right">Licensee:</td>
                          <td class="lic-licensee-name"><?php echo $config[$code . '_lic']['licensee']['name']; ?></td>
                        </tr>
                        <tr>
                          <td class="text-right">Domain:</td>
                          <td class="lic-server"><?php echo $config[$code . '_lic']['server']; ?></td>
                        </tr>
                        <tr>
                          <td class="text-right">Purchase Date:</td>
                          <td class="lic-purchased_at-formatted"><?php echo $config[$code . '_lic']['purchased_at']['formatted']; ?></td>
                        </tr>
                        <tr>
                          <td class="text-right">Expiration Date:</td>
                          <td class="lic-expires_at-formatted"><?php echo $config[$code . '_lic']['expires_at']['formatted']; ?></td>
                        </tr>
                        <tr>
                          <td class="text-right">Status:</td>
                          <td class="lic-status-name"><i class="fa <?php echo $config[$code . '_lic']['status']['icon']['name']; ?> fa-fw" style="color: <?php echo $config[$code . '_lic']['status']['icon']['color']; ?>"></i> <?php echo $config[$code . '_lic']['status']['name'] ; ?></td>
                        </tr>
                      </tbody>
                      <tfoot>
                        <tr>
                          <td class="text-right" colspan="2">
                            <a class="btn btn-primary btn-manage-license-keys" target="_blank" href="<?php echo $config[$code . '_lic']['urls']['list']; ?>" role="button">Manage License Keys</a>
                          </td>
                        </tr>
                      </tfoot>
                    </table>
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][key]" value="<?php echo $config[$code . '_lic']['key']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][licensee][name]" value="<?php echo $config[$code . '_lic']['licensee']['name']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][server]" value="<?php echo $config[$code . '_lic']['server']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][purchased_at][raw]" value="<?php echo $config[$code . '_lic']['purchased_at']['raw']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][purchased_at][formatted]" value="<?php echo $config[$code . '_lic']['purchased_at']['formatted']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][expires_at][raw]" value="<?php echo $config[$code . '_lic']['expires_at']['raw']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][expires_at][formatted]" value="<?php echo $config[$code . '_lic']['expires_at']['formatted']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][status][id]" value="<?php echo $config[$code . '_lic']['status']['id']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][status][name]" value="<?php echo $config[$code . '_lic']['status']['name']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][status][icon][name]" value="<?php echo $config[$code . '_lic']['status']['icon']['name']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][status][icon][color]" value="<?php echo $config[$code . '_lic']['status']['icon']['color']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][checked_at][raw]" value="<?php echo $config[$code . '_lic']['checked_at']['raw']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][checked_at][formatted]" value="<?php echo $config[$code . '_lic']['checked_at']['formatted']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][urls][list]" value="<?php echo $config[$code . '_lic']['urls']['list']; ?>" readonly="readonly" />
                    <input type="hidden" name="config[<?php echo $code; ?>_lic][urls][detail]" value="<?php echo $config[$code . '_lic']['urls']['detail']; ?>" readonly="readonly" />
                    <hr>
                    <h3><i class="fa fa-info-circle marine-blue"></i> About application</h3>
                    <p><?php echo $heading_title; ?>, version <?php echo $version; ?></p>
                    <p>Copyright &copy; <?php call_user_func( function($y) { $c = date('Y'); echo $y . (($y != $c) ? '-' . $c : ''); }, 2013); ?> Cuispi. All rights reserved.</p>
                  </div>
                  <div class="col-sm-6">
                    <div class="well">
                      <h3><i class="fa fa-life-ring orange"></i> Priority support</h3>
                      <p>
                        Cuispi Priority Support is a priority response support channel that is staffed with our friendly and experienced Technical Support team. 
                        Priority Support is automatically included in your initial purchase price for a period of one year.
                      </p>
                      <p class="text-right">
                        <a class="btn btn-primary" target="_blank" href="http://support.cuispi.com/" role="button">Get Support</a>
                      </p>
                    </div>
                    <div class="well">
                      <h3><i class="fa fa-tags aqua-green"></i> Other products you may like</h3>
                      <p>
                        Cuispi products, systems and services offer innovative solutions with outstanding added value to customers. 
                        Check out all our top quality products now!
                      </p>
                      <p class="text-right">
                        <a class="btn btn-primary" target="_blank" href="http://productcentral.cuispi.com/" role="button">Find Out More</a>
                      </p>                      
                    </div>                   
                  </div>
                </div>
                <!-- /.row -->
              </div>
              <!-- /.tab-pane -->                
              
            </div>

          </form>
          
          <input type="hidden" id="lic-key" name="lic_key" value="<?php echo $lic_key; ?>" readonly="readonly" />          
          
        </div>
      </div>
    </div>
    <!-- /.container-fluid -->
  
  </div>
  <!-- /#SmartSorting -->  
  
  <div id="Activation" style="display: none;">
    <div class="page-header">
      <div class="container-fluid">
        <div class="pull-right">
          <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
        </div>
        <h1><?php echo $heading_title; ?> <span class="version badge"><?php echo $version; ?></span></h1>
        <ul class="breadcrumb">
          <?php foreach ($breadcrumbs as $breadcrumb) { ?>
            <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
          <?php } ?>
        </ul>
      </div>
    </div>
    <div class="container-fluid">
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><i class="fa fa-key"></i> Activate <?php echo $heading_title; ?></h3>
        </div>
        <div class="panel-body">
          <form>
            <p class="help-block">Please enter your license key to activate the extension.</p>
            <p class="help-block">
              Don&rsquo;t have a license key? <a href="http://getkey.cuispi.com/opencart" target="_blank">Get one now! <i class="fa fa-external-link"></i></a>
            </p>
            <div class="form-group">
              <label for="input-lic-key">License Key</label>
              <div class="row">
                <div class="col-sm-6">
                  <input type="text" id="input-lic-key" class="form-control" name="config[<?php echo $code; ?>_config][lic][key]" value="" placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX"  maxlength="29">
                </div>
              </div>
              <p class="help-block">
                Before you enter your license key, please make sure that you are connected to the Internet and press &ldquo;Activate&rdquo; button to initiate the license verification process.
              </p>
            </div>
            <button type="button" id="btn-activate" class="btn btn-lg btn-success">Activate</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <!-- /#Activation -->  
  
  <script type="text/javascript">
  $(function() {

    <?php if (isset($initialization_errors['error_admin_js_not_loaded'])) { ?> 
        
      $('#SmartSorting').hide();
      alert('<?php echo $initialization_errors['error_admin_js_not_loaded']; ?>');
	  
    <?php } else { ?> 

      var options = {};

      options['js_debug'] = Boolean(<?php echo $config[$code . '_config']['js_debug']; ?>);
      options['oc_version'] = '<?php echo VERSION; ?>';
      options['code'] = '<?php echo $code; ?>';
      options['_code'] = '<?php echo $_code; ?>';
      options['extension_path'] = '<?php echo $extension_path; ?>';
      options['initialization_errors'] = <?php echo json_encode($initialization_errors) ?>;
      options['user_token_key'] = '<?php echo $user_token_key; ?>';
      options['user_token_value'] = '<?php echo $user_token_value; ?>';

      options['stores'] = <?php echo json_encode($stores); ?>;

      options['lic'] = {
        svr: '<?php echo $server_name; ?>',
        lang: '<?php echo $admin_language ?>',
        tz: '<?php echo $date_default_timezone ?>',
        ip: '<?php echo $server_addr; ?>'
      };

      options['translations'] = {
        text_on: '<?php echo $text_on; ?>',
        text_off: '<?php echo $text_off; ?>'
      };

      $('#SmartSorting').smartSorting(options);

    <?php } ?> 	
    
  });
  </script> 
  
</div>
<!-- /#content -->

<?php echo $footer; ?>