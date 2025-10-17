<?php echo $header; ?>

<?php echo $column_left; ?>

<div id="content">
    <div class="page-header">
        <div class="container-fluid">
                
            <h1><?php echo $heading_title; ?></h1>


            <!-- Breadcrumbs -->
            <ul class="breadcrumb">
                <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                  <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                <?php } ?>
            </ul>

        </div>
    </div>
    <div class="container-fluid">

        <!-- Error Warning -->
        <?php if ($error_warning) { ?>
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-circle"></i> 
                <?php echo $error_warning; ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php } ?>
      

        <!-- Success -->
        <?php if ($success) { ?>
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> 
                <?php echo $success; ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <div class="success"><?php echo $success; ?></div>
        <?php } ?>

        
        <div class="panel panel-default">
            
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $heading_title; ?></h3>
            </div>
            
            <div class="panel-body">
            
                <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form" class="form-horizontal">

                  <div class="tab-content">
                
                    <div class="tab-pane active" id="tab-general">
                
                        <!-- Email -->
                        <div class="form-group required">
                            <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_email; ?></label>
                            <div class="col-sm-10">
                                <input type="email" name="seomatic_email" value="<?php echo $value_email; ?>" class="form-control" />
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="form-group required">
                            <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_password[0]; ?></label>
                            <div class="col-sm-10">
                                <input type="password" name="seomatic_password[]" value="" class="form-control" />
                            </div>
                        </div>

                        <!-- Password 1 -->
                        <div class="form-group required">
                            <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_password[1]; ?></label>
                            <div class="col-sm-10">
                                <input type="password" name="seomatic_password[]" value="" class="form-control" />
                            </div>
                        </div>


                        <!-- Username -->
                        <div class="form-group required">
                            <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_username; ?></label>
                            <div class="col-sm-10">
                                <input type="text" name="seomatic_username" value="" maxlength="200" class="form-control" />
                            </div>
                        </div>


                        <!-- Login -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="input-name">Submit</label>
                            <div class="col-sm-10">
                                <a onclick="$('#form').submit();" class="btn btn-primary"><strong><?php echo $button_register; ?></strong></a>
                            </div>
                        </div>


                    </div>

                <!-- /End id="tab-general -->
                </div>

            <!-- /End form -->
            </form>

        <!-- /End class="panel panel-default" -->
        </div>

    <!-- /End class="container-fluid" -->
    </div>

<!-- /End id="content" -->
</div>

<?php echo $footer; ?>