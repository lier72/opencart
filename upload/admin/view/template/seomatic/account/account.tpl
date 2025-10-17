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
          
         				<li class="active"><a href="#tab-account" data-toggle="tab"><?php echo $tab_create_account; ?></a></li>

                    </ul>
                      
                    <div class="tab-content">

                    	<div class="tab-pane active form-horizontal" id="tab-account">

	                        <!-- General Tab -->
	                    	<form action="<?php echo $action_account_select; ?>" method="post" enctype="multipart/form-data" id="select_account">
	            
	                            <!-- Email -->
	                            <div class="form-group required">

	                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_account; ?></label>
	                            
	                                <?php if( count($accounts) > 0 ){ ?>

					                    <!-- Select an Account -->
					                    <form action="<?php echo $action_account_select; ?>" method="post" enctype="multipart/form-data" id="select_account" class="col-sm-3">
					                      	<select name="seomatic_account" class="form-control"><?php
						                        foreach($accounts as $account){
						                        	echo '<option value="'.$account['accountid'].'">'.$account['name'].'</option>';
						                        }
					                      	?></select>
					                      	<a onclick="$('#select_account').submit();" class="btn btn-primary"><span><?php echo $button_account_select; ?></span></a>
					                    </form>

						                </div>

					                    <!-- Or -->
					                    <div class="col-sm-1"></div>

                  
					                  	<!-- Create an Account -->    
					                  	<form action="<?php echo $action_account_create; ?>" method="post" enctype="multipart/form-data" id="create_account" class="col-sm-3">
					                    	<input type="text" name="name" placeholder="<?php echo $placeholder_account; ?>" class="form-control" />
					                    	<a onclick="$('#create_account').submit();" class="btn btn-primary"><span><?php echo $button_account_create; ?></span></a>
					                  	</form>

		                               <?php } ?>
	                            </div>

	                        <!-- /End #select_account -->
	                		</form>

	                	<!-- /End #tab-account -->
	                	</div>

	                <!-- /End .tab-content -->
	                </div>

                <!-- /End .pane-body -->
                </div>

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


