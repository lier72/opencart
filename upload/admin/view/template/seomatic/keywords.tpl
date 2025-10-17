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



                    <?php if( $this->registry->get('SEOMatic')->connected ){ ?>

                        <!-- Keyword Domain Selector -->
                        <div class="clearfix" style="float:right; padding:20px 0 10px;">
                            <div style="display:inline;">
                                <strong>Showing Results For:</strong>
                                <select name="seomatic[keywords][domain]">
                                    <?php if( count($domains) > 0 ){ ?>
                                        <?php foreach( $domains as $domain ){ ?>
                                            <option value="<?php echo $domain['domainid']; ?>"><?php echo $domain['domain']; ?></option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>
                            |
                            <a class="btn btn-primary addkeyword"><span>Add a Keyword</span></a>
                        </div>

                        <div class="clearfix"></div>
                    <?php } ?>

                    <div class="tab-pane active form-horizontal" id="tab-keywords">
            
                        <?php if($this->registry->get('config')->get('seomatic_account')){ ?>

                            <div class="table-responsive">

                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <td class="left" width="1"><input type="checkbox" value="" name="keywords[all]" /></td>
                                            <?php if( !$this->registry->get('SEOMatic')->expired ){ ?>
                                                <td class="left" width="150"><?php echo 'Last Updated'; ?></td>
                                            <?php } ?>
                                            <td class="left"><?php echo 'Country'; ?></td>
                                            <td class="left"><?php echo 'Keyword'; ?></td>
                                            <td class="left" width="100"><?php echo 'Category'; ?></td>
                                            <td class="left" width="100"><?php echo 'Page <small>(Autocomplete)</small>'; ?></td>
                                            <td class="left"><?php echo 'Google'; ?></td>
                                            <td class="left"><?php echo 'Bing'; ?></td>
                                            <td class="left"><?php echo 'Yahoo'; ?></td>
                                            <td class="center" width="50"><?php echo 'Status'; ?></td>
                                            <td class="center" width="150"><?php echo 'Actions'; ?></td>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <?php if(count($keywords) > 0){ ?>

                                            <?php foreach($keywords as $keyword){ ?>
                                                <tr data-serp data-keywordid="<?php echo $keyword['keywordid']; ?>" data-domainid="<?php echo $domains[0]['domainid']; ?>" data-countryid="<?php echo $keyword['countryid']; ?>" data-updated="<?php echo $keyword['updated']; ?>" data-added="<?php echo $keyword['added']; ?>">
                                        
                                                    <!-- Keyword ID -->
                                                    <td class="text-left">
                                                        <input type="checkbox" value="<?php echo $keyword['keywordid']; ?>" name="keywords[]" />
                                                    </td>

                                                    <!-- Last Updated -->
                                                    <td class="text-center updated"><?php echo ( $keyword['updated'] != '0000-00-00 00:00:00' ? date('M d, Y h:i A',strtotime($keyword['updated'])) : 'Never' ); ?></td>


                                                    <!-- Country ID -->
                                                    <td class="text-center">
                                                        <?php foreach($countries as $country){ ?>
                                                            <?php if( $country['countryid'] == $keyword['countryid'] ){ ?>

                                                                <img src="<?php echo $country['image']; ?>" alt="<?php echo $country['name']; ?>" />

                                                            <?php } ?>
                                                        <?php } ?>
                                                        <input type="hidden" name="country" value="<?php echo $keyword['countryid']; ?>" />
                                                    </td>

                                                    <!-- Keyword -->
                                                    <td class="left">
                                                        <?php echo $keyword['keyword']; ?>
                                                        <input type="hidden" name="keyword" value="<?php echo $keyword['keyword']; ?>" />
                                                    </td>



                                                    <!-- Link Association Textual Reference -->
                                                    <td class="text-center">
                                                        <select name="linktype">
                                                            <option value="">Select a Type</option>
                                                            <option value="productid"<?php if($keyword['linktype'] == 'productid') echo ' selected="selected";'; ?>>Product</option>
                                                            <option value="categoryid"<?php if($keyword['linktype'] == 'categoryid') echo ' selected="selected";'; ?>>Category</option>
                                                            <option value="informationid"<?php if($keyword['linktype'] == 'informationid') echo ' selected="selected";'; ?>>Information</option>
                                                        </select>
                                                    </td>


                                                    <!-- Link Association ID -->
                                                    <td class="text-center linkid">
                                                        <input type="text" name="productid" placeholder="Product Name" value="<?php if($keyword['linktype'] === 'productid') echo $keyword['page']; ?>" <?php if($keyword['linktype'] !== 'productid') echo ' style="display:none";'; ?> />
                                                        <input type="text" name="categoryid" placeholder="Category Name" value="<?php if($keyword['linktype'] === 'categoryid') echo $keyword['page']; ?>" <?php if($keyword['linktype'] !== 'categoryid') echo ' style="display:none";'; ?> />
                                                        <input type="text" name="informationid" placeholder="Information Name" value="<?php if($keyword['linktype'] === 'informationid') echo $keyword['page']; ?>" <?php if($keyword['linktype'] !== 'informationid') echo ' style="display:none;"'; ?> />
                                                        <input type="text" name="none" placeholder="None" <?php if( $keyword['linktype'] ) echo 'style="display:none;"'; ?> disabled="disabled" />
                                                        <input type="hidden" name="linkid" value="<?php echo $keyword['linkid']; ?>" />
                                                    </td>

                                                    <?php if( !$this->registry->get('SEOMatic')->expired ){ ?>

                                                        <!-- Google SERP Rank -->
                                                        <td class="text-center google" data-google>-</td>


                                                        <!-- Bing SERP Rank -->
                                                        <td class="text-center bing" data-bing>-</td>


                                                        <!-- Yahoo SERP Rank -->
                                                        <td class="text-center yahoo" data-yahoo>-</td>

                                                    <?php }else{ ?>

                                                        <!-- Subscription Expired -->
                                                        <td colspan="3" class="text-center center no-keywords-text">
                                                            
                                                            <span style="white-space:nowrap;">
                                                            
                                                                Your SEOMatic Subscription Has Expired: 
                                                            
                                                                <a href="<?php echo $this->registry->get('url')->link('seomatic/account', 'token=' . $this->session->data['token'] . '#tab-plans', 'SSL'); ?>">Subscribe Now</a>
                                                            
                                                            </span>
                                                        
                                                        </td>


                                                    <?php } ?>

                                                    <!-- Keyword Status  -->
                                                    <td class="text-center">
                                                        <select name="status">
                                                            <option value="0"<?php if(!$keyword['status']) echo ' selected="selected"'; ?>><?php echo 'Inactive'; ?></option>
                                                            <option value="1"<?php if($keyword['status']) echo ' selected="selected"'; ?>><?php echo 'Active'; ?></option>
                                                        </select>
                                                        <input type="hidden" name="keywordid" value="<?php echo $keyword['keywordid']; ?>" />
                                                    </td>


                                                    <!-- Actions -->
                                                    <td class="text-center" style="white-space:nowrap;">
                                                        <button type="button" name="delete" value="<?php echo $keyword['keywordid']; ?>"><?php echo 'Delete'; ?></button>
                                                        |
                                                        <button type="button" name="save"><?php echo 'Save'; ?></button>
                                                    </td>


                                                </tr>
                                            <?php } ?>

                                        <?php }else{ ?>

                                            <!-- No Keywords Available -->
                                            <tr id="nokeywords">
                                                <td colspan="100%" class="center">
                                                    You haven't added any keywords! 
                                                </td>
                                            </tr>

                                        <?php } ?>

                                    </tbody>
                                </table>

                                <?php echo $pagination; ?>

                            <!-- /End .table-responsive -->
                            </div>

                        <?php } ?>

                    </div>



                <!-- /End .pane-body -->
                </div>

            <!-- /End class="panel panel-default" -->
            </div>

        <!-- /End class="container-fluid" -->
        </div>

    <!-- /End id="content" -->
    </div>




    <!-- Page Scripts -->
    <script type="text/javascript">//<![CDATA[





        //Keywords Object
        var Keywords = {

            //A list of the Countries
            countries:  <?php echo json_encode($countries); ?>,

            //Get the Keyword Data
            get:    function(scope){

                //Get the Parent Row
                var row  = $(scope).parents('tr');
                
                //Return the Object
                return {
                    keywordid:  $(row).find('[name="keywords[]"]').val(),
                    linkid:     $(row).find('[name="linkid"]').val(),
                    linktype:   $(row).find('[name="linktype"]').val(),
                    countryid:  $(row).find('[name="country"]').val(),
                    keyword:    $(row).find('[name="keyword"]').val(),
                    status:     $(row).find('[name="status"]').val()
                };

            }


        };












        /**
        *
        *   CLICK: button[name="create"]
        *       - Create a Keyword
        *
        *   Params:
        *       n/a
        *
        **/
        $('body').on('click','button[name="create"]',function(e){
            e.preventDefault();

            //Prepare Data
            var el              = this,
                row             = $(this).parents('tr'),
                datetime        = SEOMatic['Date'].datetime(),
                data            = Keywords.get(this);
                data.domainid   = $('select[name="domains"]').val();

            //Post the Status
            $.post('index.php?route=seomatic/keywords/create&token=<?php echo $token; ?>', data, function(obj){
                if( obj.result ){

                    //Update the Keyword ID
                    $(el).next().val( obj.keywordid );

                    //Change the Button
                    $(el).replaceWith('<button type="button" name="save">Save</button>');

                    //Update the SERP settings
                    $(row).attr('data-id', obj.keywordid ).data('id', obj.keywordid );
                    $(row).attr('data-keywordid', obj.keywordid ).data('keywordid', obj.keywordid );
                    $(row).attr('data-countryid', data.countryid ).data('countryid', data.countryid );
                    $(row).attr('data-domainid', data.domainid ).data('domainid', data.domainid );
                    $(row).attr('data-added', datetime ).data('added', datetime );


                    //Add to Results Listings
                    $(row).find('[name="keywords[]"]').val( obj.keywordid );

                    //Update the Country
                    $(row).find('[name="keyword"]').replaceWith(
                        '<input type="hidden" name="keyword" value="' + data.keyword + '" />' +
                        data.keyword
                    );


                    for( var i=0; i<Keywords.countries.length; i++ ){
                        if( data.countryid == Keywords.countries[i].countryid ){

                            //Hide the Country Field and Show the Country with the Flag
                            $(row).find('[name="country"]').replaceWith(
                                '<input type="hidden" name="country" value="' + data.countryid + '" />' +
                                '<img src="' + Keywords.countries[i].image + '" alt="' + Keywords.countries[i].name + '" />' 
                            );

                            //End the Loop
                            break;

                        }
                    }
                
                }else{
                
                    //Output Error
                    alert( obj.error );
                
                }
            },'json');

        });












        /**
        *
        *   CLICK: button[name="save"]
        *       - Update a Keyword
        *
        *   Params:
        *       n/a
        *
        **/
        $('body').on('click','button[name="save"]',function(){

            //Post the Status
            $.post('index.php?route=seomatic/keywords/update&token=<?php echo $token; ?>', Keywords.get(this), function(obj){
              alert( ( obj.result ? 'Keyword Saved' : obj.error ) );
            },'json');

        });


























        /**
        *
        *   KEYUP: seomatic[keyword][]
        *       - Restrict the Characters on Keyup
        *
        *   Params:
        *       n/a
        *
        **/
        $('body').on('keyup','input[name="keyword"]',function(e){
        
            //Remove all Unwanted Characters
            $(this).val( $(this).val().replace(/[!@#$%^&*()~,.|\/\\<>{}\]\[+=_-]/ig,'') );

        });


















        /**
        *
        *   BLUR: seomatic[keyword][]
        *       - Check Keyword Length and give recommendations
        *
        *   Params:
        *       n/a
        *
        **/
        $('body').on('blur','input[name="keyword"]',function(e){

            var validations = {

                //Too many Characters
                characters:     ( $(this).val().length > 45 ),

                //Too many words
                words:          ( $(this).val().split(/\s+/).length > 5 )

            };

            //Check the Total Characters
            if( $(this).data('notified') != $(this).val() && ( validations.characters || validations.words )  ){

                //Output an Alert
                alert(
                    (
                        validations.characters ?
                            'You have over 45 characters in your keyword.'
                        :
                            'You have more than 5 words in your key phrase.'
                    ) +
                    "\r" +
                    'Long keywords or key phrases are not recommended'
                );

                //Store the value so we don't notify twice
                $(this).data( 'notified' , $(this).val() );

            }

        });












        /**
        *
        *   CHANGE: button[name="delete"]
        *       - Remove a Keyword
        *
        *   Params:
        *       n/a
        *
        **/
        $('body').on('click','button[name="delete"]',function(){
          if( window.confirm('Are you sure you want to delete this keyword?') ){

            //Save the Element
            var el = this,

            //Get the Keyword Ids
            ids = Keywords.get(this).keywordid;


            //Ensure the Keyword was Created
            if( ids == '' ){
                        
                //Remove the Field
                $(el).parents('tr').remove();

                //Find Total keyworsd
                if( $('.list tbody tr').length == 0 ){

                    //Add the No Keywords Message
                    $('.list tbody tr').append(
                        '<tr id="nokeywords">'+
                            '<td colspan="100%" class="center">'+
                                'You haven\'t added any keywords!'+
                            '</td>'+
                        '</tr>'
                    );

                }

            }else{

                //Send the Command
                $.post('index.php?route=seomatic/keywords/remove&token=<?php echo $token; ?>', { keywordids:[ ids ] }, function(obj){
                    if( obj[0].result ){

                        //Remove the Field
                        $(el).parents('tr').remove();

                        //Find Total keyworsd
                        if( $('.list tbody tr').length == 0 ){

                            //Add the No Keywords Message
                            $('.list tbody tr').append(
                                '<tr id="nokeywords">'+
                                    '<td colspan="100%" class="center">'+
                                        'You haven\'t added any keywords!'+
                                    '</td>'+
                                '</tr>'
                            );

                        }

                    }else{
                    
                        alert( obj[0].error );
                  
                    }
                },'json');

            }

          }
        });












        /**
        *
        *   CHANGE: select[name="linktype"]
        *       - On Linktype Change, Show the ID
        *
        *   Params:
        *       n/a
        *
        **/
        $('body').on('change','select[name="linktype"]',function(){

            //Get the Row
            var row = $(this).parents('tr');

            //Hide the Input Fields
            $(row).find('.linkid input').val('').css('display','none');

            //Show the Selected Input
            $(row).find( ( $(this).val() == '' ? '.linkid input[name="none"]' : '.linkid input[name="'+$(this).val()+'"]' ) ).css('display','inline');

            //Clear the Existing Linkid
            $(row).find('.linkid input[name="linkid"]').val('');

        });

































        /**
        *
        *   CLICK a.addkeyword
        *       - Add a New Keyword
        *
        *   Params:
        *       n/a
        *
        **/
        $('a.addkeyword').click(function(e){
            e.preventDefault();


            //Add the Row
            $('table tbody').prepend(
                '<tr class="keyword" data-id="" data-keywordid="" data-domainid="" data-countryid="" data-updated="" data-added="" data-serp>'+

                    //Keyword ID           
                    '<td class="text-left">'+
                        '<input type="checkbox" value="" name="keywords[]" />'+
                    '</td>'+

                    <?php if( !$this->registry->get('SEOMatic')->expired ){ ?>

                        //Updated
                        '<td class="text-center">Never</td>'+

                    <?php } ?>

                    //Country
                    '<td class="text-center">'+
                        '<select name="country" style="width:200px;">'+
                            '<?php
                                foreach($countries as $country){ 

                                    $code = '';
                                    foreach($country['codes'] as $code) if( $code != '' ) break;
                          
                                    echo '<option value="'.$country['countryid'].'"' . ( $this->registry->get('config')->get('seomatic_country') == $country['countryid'] ? ' selected="selected"' : '' ) . '>'.$country['name'].' ('.$code.')</option>';

                                }
                            ?>'+                            
                      '</select>'+
                    '</td>'+

                    //Keyword
                    '<td class="text-left">'+
                        '<input type="text" value="" name="keyword" style="width:98%;" />'+
                    '</td>'+

                    //Link Association Textual Reference
                    '<td class="text-center">'+
                        '<select name="linktype">'+
                            '<option value="">Select a Type</option>'+
                            '<option value="productid">Product</option>'+
                            '<option value="categoryid">Category</option>'+
                            '<option value="informationid">Information</option>'+
                        '</select>'+
                    '</td>'+

                    //Link Association ID
                    '<td class="text-center linkid">'+
                        '<input type="text" name="productid" placeholder="Product Name" value="" style="display:none;" />'+
                        '<input type="text" name="categoryid" placeholder="Category Name" value="" style="display:none;" />'+
                        '<input type="text" name="informationid" placeholder="Information Name" value="" style="display:none;" />'+
                        '<input type="text" name="none" placeholder="None" disabled="disabled" />'+
                        '<input type="hidden" name="linkid" value="" />'+
                    '</td>'+

                    <?php if( !$this->registry->get('SEOMatic')->expired ){ ?>
                    
                        //SERPs
                        '<td class="text-center">-</td>'+
                        '<td class="text-center">-</td>'+
                        '<td class="text-center">-</td>'+
                    
                    <?php }else{ ?>


                        //Account Expired
                        '<td colspan="3" class="text-center center no-keywords-text">'+
                            '<span style="white-space:nowrap;">'+
                                'Your SEOMatic Subscription Has Expired: '+
                                '<a href="<?php echo $this->registry->get('url')->link('seomatic/account', 'token=' . $this->session->data['token'] . '#tab-plans', 'SSL'); ?>">Subscribe Now</a>'+
                            '</span>'+
                        '</td>'+

                    <?php } ?>

                    //Status
                    '<td class="text-center">'+
                        '<select name="status">'+
                            '<option value="0">Inactive</option>'+
                            '<option value="1">Active</option>'+
                        '</select>'+
                        '<input type="hidden" name="keywordid" value="" />'+
                    '</td>'+

                    //Actions
                    '<td class="text-center" style="white-space:nowrap;">'+
                        '<button type="button" name="delete" value="">Delete</button>'+
                        ' | '+
                        '<button type="button" name="create">Create</button>'+
                    '</td>'+

                '</tr>'
            );

            //Hide the No Keywords
            $('#nokeywords').hide();

        });




















        /**
        *
        *   CHANGE: select[name="domains"]
        *       - Update the Keyword SERP Listing
        *
        *   Params:
        *       n/a
        *
        **/
        $('select[name="domains"]').change(function(){

           $('[data-serp]').attr('data-domainid', $(this).val() );

            //Get the SERP Data
            SEOMatic.SERP.load();            

        });























        /**
        *
        *   LOOP:   [ 'product' , 'category' , 'information' ]
        *       - Loop through each type and set up an autocomplete
        *
        *   Params:
        *       n/a
        *
        **/
        for( var i=0, types=['product','category','information']; i<types.length; i++){
          (function(i){

            //When we focus on the element
            $('body').on('focus','input[name="'+types[i]+'id"]',function(){

                //If Autocomplete hasn't been set up
                if( !$(this).attr('autocomplete') ){

                    //Save the Elements
                    var el = this,
                        id = $(this).parents('td').find('input[name="linkid"]');

                    //Create Autocomplete
                    $(el).autocomplete({
                        delay:  500,
                        source: function(request, response) {
                            $.ajax({
                                url: 'index.php?route=seomatic/keywords/autocomplete&token=<?php echo $token; ?>&type='+types[i]+'&filter_name=' +  encodeURIComponent(request),
                                dataType: 'json',
                                success: function(json) {   
                                    response($.map(json, function(item) {
                                        return {
                                            label: item.name,
                                            value: item.value
                                        }
                                    }));
                                },
                                error: function(a){ console.log(a.responseText); }
                            });

                        }, 
                        select: function(sel) {
                            $(el).val(sel.label);
                            $(id).val(sel.value);
                            return false;
                        },
                        focus: function(event, ui) {
                            return false;
                        }
                    });

                }

            //End $('input[name="'+types[i]+'id"]').each(function(){
            });


          //End (function(i){
          })(i);
        }













    //]]></script> 



<?php echo $footer; ?>