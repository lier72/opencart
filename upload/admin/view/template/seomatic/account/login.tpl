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
                   
                    <!-- Tabs -->               
                    <ul class="nav nav-tabs">
                        
                        <li class="active"><a href="#tab-login" data-toggle="tab"><?php echo $tab_login; ?></a></li>
                        
                        <li><a href="#tab-password" data-toggle="tab"><?php echo $tab_forgot_password; ?></a></li>
                   
                    </ul>
                      
                    <div class="tab-content">

                        <!-- General Tab -->
                        <form action="<?php echo $action_login; ?>" method="post" enctype="multipart/form-data" class="tab-pane active form-horizontal" id="tab-login">
            
                            <!-- Email -->
                            <div class="form-group required">
                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_email; ?></label>
                                <div class="col-sm-10">
                                    <input type="email" name="seomatic_email" value="<?php echo $value_email; ?>" class="form-control" />
                                </div>
                            </div>



                            <!-- Password -->
                            <div class="form-group required">
                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_password; ?></label>
                                <div class="col-sm-10">
                                    <input type="password" name="seomatic_password" value="" class="form-control" />
                                </div>
                            </div>


                            <!-- Login -->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_login; ?></label>
                                <div class="col-sm-10">
                                    <a onclick="$('#tab-login').submit();" class="btn btn-primary"><strong><?php echo $button_login; ?></strong></a>
                                </div>
                            </div>

                        </form>


                        <!-- Forgot Password Tab -->
                        <form action="<?php echo $action_forgot_password; ?>" method="post" enctype="multipart/form-data" class="tab-pane form-horizontal" id="tab-password">

                            <!-- Email -->
                            <div class="form-group required">
                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_email; ?></label>
                                <div class="col-sm-10">
                                    <input type="email" name="seomatic_email" value="<?php echo $value_email; ?>" class="form-control" />
                                </div>
                            </div>


                            <!-- Login -->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_forgot_password; ?></label>
                                <div class="col-sm-10">
                                    <a onclick="$('#tab-password').submit();" class="btn btn-primary"><strong><?php echo $button_forgot_password; ?></strong></a>
                                </div>
                            </div>

                        </form>


                <!-- /End form -->
                </form>

            <!-- /End class="panel panel-default" -->
            </div>

        <!-- /End class="container-fluid" -->
        </div>

    <!-- /End id="content" -->
</div>


  <script type="text/javascript"><!--

      
    //Select Tab
    $('a[href="'+window.location.hash+'"]').click();

  //--></script> 



<?php echo $footer; ?>


