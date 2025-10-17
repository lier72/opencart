<?php echo $header; ?>

<?php echo $column_left; ?>

    <div id="content">

        <div class="page-header">
            <div class="container-fluid">

                <div class="pull-right">
              
                    <button type="submit" form="form-setting" data-toggle="tooltip" title="<?php echo $button_account_create; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
                    
                    <a href="<?php echo $action_cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>

                </div>
                    
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
          
                        <li class="active"><a href="#tab-account" data-toggle="tab"><?php echo $tab_create_campaign; ?></a></li>

                    </ul>
                      
                    <div class="tab-content">

                      <div class="tab-pane active form-horizontal" id="tab-account">

                            <form action="<?php echo $action_create; ?>" method="post" id="form-campaign" enctype="multipart/form-data">


                                <!-- Account Name -->
                                <div class="form-group required">
                                    <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_account_name; ?></label>
                                    <div class="col-sm-10">
                                        <input type="text" name="name" maxlength="255" value="<?php echo $name; ?>" class="form-control" />
                                    </div>
                                </div>


                                <!-- Account Default Country -->
                                <div class="form-group required">
                                    <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_account_default_country; ?></label>
                                    <div class="col-sm-10">
                                        <select name="country">
                                            <?php foreach( $countries as $country ){ ?>
                                                <option value="<?php echo $country['countryid']; ?>"<?php if( preg_replace('/[^a-z0-9]/i','',$country['name']) == preg_replace('/[^a-z0-9]/i','',$store_country['name']) ){ ?> selected="selected"<?php } ?>><?php echo $country['name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>


                                <!-- Account Domains -->
                                <div class="form-group required">
                                    <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_account_domain; ?></label>
                                    <div class="col-sm-10">

                                        <div id="domains" class="well well-sm scrollbox" style="height:150px; overflow:auto;">
                                            <?php foreach ($domains as $i=>$domain) { ?>                                              
                                                <div class="checkbox">
                                                    <label style="display:block;">
                                                        <?php echo preg_replace('/https?:\/\//','',$domain); ?>
                                                        <img src="view/image/delete.png" alt="" class="pull-right" />
                                                        <input type="hidden" name="domains[]" value="<?php echo $domain; ?>" />
                                                    </label>
                                                    <hr style="margin:10px 0 0px;" />
                                                </div>                                            
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <br />

                                    <div class="col-sm-2"></div>

                                    <div class="col-sm-5">
                                        <input type="text" id="domain" name="domain" maxlength="255" value="http://" class="form-control" />
                                    </div>

                                    <div class="col-sm-3">
                                        <a id="add-domain" class="btn btn-primary"><span><?php echo $button_domain; ?></span></a>
                                    </div>

                                </div>




                                <!-- Account Competitors -->
                                <div class="form-group required">
                                    <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_account_competitor; ?></label>
                                    <div class="col-sm-10">

                                        <div id="competitors" class="well well-sm scrollbox" style="height:150px; overflow:auto;">
                                            <?php foreach ($competitors as $competitor) { ?>
                                                <div class="checkbox">
                                                    <label style="display:block;">
                                                        <?php echo $competitor['name']; ?> <small>( <?php echo $competitor['domain']; ?> )</small>
                                                        <img src="view/image/delete.png" alt="" class="pull-right" />
                                                        <input type="hidden" name="competitors[]" value="<?php echo $competitor['domain']; ?>" />
                                                    </label>
                                                    <hr style="margin:10px 0 0px;" />
                                                </div>                                            
                                            <?php } ?>
                                        </div>

                                    </div>

                                    <br />

                                    <div class="col-sm-2"></div>

                                    <div class="col-sm-5">
                                        <input type="text" id="competitor" name="competitor" maxlength="255" value="http://" class="form-control" />
                                    </div>

                                    <div class="col-sm-3">
                                        <a id="add-competitor" class="btn btn-primary"><span><?php echo $button_competitor; ?></span></a>
                                    </div>

                                </div>



                            <!-- /End #form-campaign -->
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





    //The Prefix
    var prefix  = 'http://',
        running = {domain:false, competitor:false};





    //On Keydown, ensure http will not be edited
    $('#domain,#competitor').keydown(function(e){
      if( (e.keyCode == 8 && $(this).val().length == 7) || $(this).val().substr(0,7) != prefix  ){

        e.preventDefault();
        e.stopPropagation();
        return false;

      }
    });






    //If Prefix was deleted, Re-add it.
    $('#domain,#competitor').keyup(function(e){
      if( $(this).val().substr(0,7) != prefix ){
        $(this).val( prefix );
      }
    });






    //Add Domain
    $('#add-domain,#add-competitor').click(function(){

      //Get the Type
      var btn       = this,
          type      = $(btn).attr('id').replace('add-',''),
          existing  = [];


      //Load the Domains / Competitors
      $('input[name="'+type+'s[]"]').each(function(){
        existing.push( $(this).val() );
      });

      //Clear any Errors
      $(btn).next('small').remove();

      //Check if only the Prefix is set
      if( $('#'+type).val() == prefix ){

        //Invalid Domain
        $(btn).after('<small style="margin-left:5px; color:#F00;"><?php echo $validate_invalid_domain; ?></small>');

      //Check if the Domain Exists already
      }else
      if( $.inArray( domain , existing ) !== -1 ){

        //Domain Exists
        $(btn).after('<small style="margin-left:5px; color:#F00;"><?php echo $validate_domain_exists; ?></small>');

        //Reset the Input
        $('#'+type).val( prefix );

      //Continue, if not already running
      }else
      if( !running[type] ){

        //Show the Loading
        $(btn).after('<small style="margin-left:5px;"> <img src="view/image/loading.gif"/> <?php echo $validate_validating_domain; ?> </small>');

        //Modify the Style to show Disabled
        $(btn).css('background','#999');

        //Set it to Running
        running[type] = true;

        //Validate the Domain
        $.post('index.php?route=seomatic/account/domain&token=<?php echo $token; ?>',{ 'domain' : $('#'+type).val() },function(data){


          //Prepare Result
          var data = $.parseJSON(data);

          //Remove the Loading
          $(btn).next('small').remove();

          //Remove the Style
          $(btn).removeAttr('style');

          //Set it to Not Running
          running[type] = false;

          if( data.result ){

            //Prepare the Name
            if( type == 'competitor' ){
              var name = data.name + ' <small>( '+ $('#'+type).val() + ' )</small>';
            }else{
              var name = $('#'+type).val().replace(/http(?:s)?:\/\/(?:www\.)?/g,'');
            }

            //Add the Domain
            $('#'+type+'s').append(
                '<div class="checkbox">'+
                    '<label style="display:block;">'+
                        name +
                        '<img src="view/image/delete.png" alt="" class="pull-right" />'+
                        '<input type="hidden" name="'+type+'s[]" value="'+ $('#'+type).val() +'" />'+
                    '</label>'+
                    '<hr style="margin:10px 0 0px;" />'+
                '</div>'
            );

          }else{

            //Show the Error
            $(btn).after('<small style="margin-left:5px; color:#F00;">'+data.error+'</small>');

          }


          //Reset the Input
          $('#'+type).val( prefix );

        });
      
      }

    });











    //Remove a Scrollbox Item
    $('.scrollbox').on('click','img[src*="delete"]',function(){
      $(this).parents('.checkbox').remove();
    });





  //--></script>

<?php echo $footer; ?>


