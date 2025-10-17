<?php echo $header; ?>
<?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <button type="submit" form="form-slideshow" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
                <h1><?php echo $heading_title; ?> <small>(Version: <?php echo $this->registry->get('SEOMatic')->version; ?>)</small></h1>
                <ul class="breadcrumb">
                    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                    <?php } ?>
                </ul>
            </div>
        </div>
        <div class="container-fluid">
            <?php if ($error_warning) { ?>
                <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php } ?>
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
                </div>
                <div class="panel-body">
                    <form action="" method="post" enctype="multipart/form-data" id="seomatic-settings" class="form-horizontal">
                        <div class="form-group">
                            <label class="col-sm-2 control-label" style="text-align:left;" name="autoupdate">Auto Update:</label>
                            <div class="col-sm-8 control-label" style="text-align:left;">
                                Allow SEOMatic to Update Automatically?
                            </div>
                            <div class="col-sm-2">
                                <button id="autoupdate" name="autoupdate" class="btn btn-primary pull-right" value="<?php echo $this->registry->get('config')->get('seomatic_autoupdate'); ?>"><?php echo $this->registry->get('config')->get('seomatic_autoupdate') ? 'Disable' : 'Enable' ; ?></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-2 control-label" style="text-align:left;" name="dashboard">Default to Dashboard:</label>
                            <div class="col-sm-8 control-label" style="text-align:left;">
                                Default to the SEOMatic Dashboard on Product, Category and Information Pages
                            </div>
                            <div class="col-sm-2">
                                <button id="dashboard" name="dashboard" class="btn btn-primary pull-right" value="<?php echo $this->registry->get('config')->get('seomatic_dashboard'); ?>"><?php echo $this->registry->get('config')->get('seomatic_dashboard') ? 'Disable' : 'Enable' ; ?></button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript"><!--

  $('#seomatic-settings button').click(function(e){

    e.preventDefault();

    <?php if( $can_validate ){ ?>

      //Save the Button
      var el = this;

      //Make the call
      $.post('index.php?route=module/seomatic/' + $(this).attr('name') + '&token=<?php echo $this->registry->get('session')->data['token']; ?>',{ data: +$(this).val() },function(value){

        //Toggle the Button
        $(el).html( ( +value ? 'Disable' : 'Enable' ) );

        //Set the Value
        $(el).val( value );

      });

    <?php }else{ ?>

      alert('You don\'t have Modify permissions for this Module');

    <?php } ?>

  });

//--></script> 
<?php echo $footer; ?>