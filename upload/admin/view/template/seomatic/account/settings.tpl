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
            <?php } ?>


            <div class="panel panel-default">
                
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $heading_title; ?></h3>
                    <a href="<?php echo $action_logout; ?>" class="btn btn-primary" style="float:right; margin-top:-8px;">
                        <span>
                            <i class="fa fa-sign-out fa-lg"></i>
                            <?php echo $button_logout; ?>
                        </span>
                    </a>
                </div>
                
                <div class="panel-body">
                   
                    <!-- Tabs -->               
                    <ul class="nav nav-tabs">        
            
                        <li class="active"><a href="#tab-profile" data-toggle="tab"><?php echo $tab_profile; ?></a></li>
                        <li><a href="#tab-account" data-toggle="tab"><?php echo $tab_account; ?></a></li>
                        <li><a href="#tab-plans" data-toggle="tab"><?php echo $tab_plan; ?></a></li>
                        <li><a href="#tab-cards" data-toggle="tab"><?php echo $tab_cards; ?></a></li>
                        <li><a href="#tab-invoices" data-toggle="tab"><?php echo $tab_invoices; ?></a></li>

                    </ul>


                    <div class="tab-content">


                        <!-- Profile Tab -->
                        <div class="tab-pane active form-horizontal" id="tab-profile">    

                            <!-- Email -->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_email; ?></label>
                                <div class="col-sm-8">
                                    <input type="text" name="email" value="<?php echo $value_email; ?>" class="form-control" />
                                </div>
                                <div class="col-sm-2">
                                    <a id="update-email" data-function="email" data-name="Email" class="btn btn-primary"><span><?php echo $button_email; ?></span></a>
                                </div>
                            </div>


                            <!-- Password -->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_password; ?></label>
                                <div class="col-sm-4">
                                    <input type="password" name="password[]" value="" placeholder="<?php echo $placeholder_password[0]; ?>" class="form-control" />
                                </div>
                                <div class="col-sm-4">
                                    <input type="password" name="password[]" value="" placeholder="<?php echo $placeholder_password[1]; ?>" class="form-control" />
                                </div>
                                <div class="col-sm-2">
                                    <a id="update-password" data-function="password" data-name="Password" class="btn btn-primary"><span><?php echo $button_password; ?></span></a>
                                </div>
                            </div>


                            <!-- Username -->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_username; ?></label>
                                <div class="col-sm-8">
                                    <input type="text" name="username" value="<?php echo $user['username']; ?>" class="form-control" />
                                </div>
                                <div class="col-sm-2">
                                    <a id="update-username" data-function="username" data-name="Username" class="btn btn-primary"><span><?php echo $button_username; ?></span></a>
                                </div>
                            </div>


                        <!-- /End #tab-profile -->
                        </div>



                        <!-- Account Tab -->
                        <div class="tab-pane form-horizontal" id="tab-account">


                            <!-- Account Select -->
                            <div class="form-group">

                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_account_select; ?></label>
                            
                                <?php if( count($accounts) > 0 ){ ?>

                                    <!-- Select an Account -->
                                    <form action="<?php echo $action_account_select; ?>" method="post" enctype="multipart/form-data" id="select-account" class="col-sm-3">
                                        <select name="seomatic_account" class="form-control">
                                            <option value="">Select Campaign</option>
                                            <?php
                                                foreach($accounts as $acct){
                                                    echo '<option value="'.$acct['accountid'].'"'.($this->registry->get('config')->get('seomatic_account') == $acct['accountid']?' selected="selected"':'').'>'.$acct['name'].'</option>';
                                                }
                                            ?>
                                        </select>
                                    </form>

                                    <div class="col-sm-1">
                                        <a onclick="$('#select-account').submit();" class="btn btn-primary"><span><?php echo $button_account_select; ?></span></a>
                                    </div>

                                <?php } ?>
              
                                <!-- Create an Account -->    
                                <form action="<?php echo $action_account_create; ?>" method="post" enctype="multipart/form-data" id="create-account" class="col-sm-1">
                                    <a onclick="$('#create-account').submit();" class="btn btn-primary"><span><?php echo $button_account_create; ?></span></a>
                                </form>
                            </div>


                            <?php if($this->registry->get('config')->get('seomatic_account')){ ?>

                                <hr />


                                <!-- Account Name -->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_account_name; ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="name" maxlength="255" value="<?php echo $account['name']; ?>" class="form-control" />
                                    </div>
                                    <div class="col-sm-2">
                                        <a id="update-account-name" data-function="name" data-name="Account Name" class="btn btn-primary"><span><?php echo $button_account_name; ?></span></a>
                                    </div>
                                </div>


                                <!-- Account Country -->
                                <div class="form-group required">
                                    <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_account_default_country; ?></label>
                                    <div class="col-sm-8">
                                        <select name="countryid">
                                            <?php foreach( $countries as $country ){ ?>
                                                <option value="<?php echo $country['countryid']; ?>"<?php if( $this->registry->get('config')->get('seomatic_country') == $country['countryid'] ){ ?> selected="selected"<?php } ?>><?php echo $country['name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-2">
                                        <a id="update-default-country" data-function="country" data-name="Default Country" class="btn btn-primary"><span><?php echo $button_account_name; ?></span></a>
                                    </div>
                                </div>


                                <!-- Account Domains -->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="input-name">
                                        <?php echo $entry_account_domain; ?> <br />
                                        <small>( <?php echo $entry_automatic_update; ?> )</small>
                                    </label>
                                    <div class="col-sm-10">

                                        <div id="domains" class="well well-sm scrollbox" style="height:150px; overflow:auto;">
                                            <?php foreach ($domains as $i=>$domain) { ?>                                              
                                                <div class="checkbox">
                                                    <label style="display:block;">
                                                        <?php echo $domain['domain']; ?>
                                                        <img src="view/image/delete.png" alt="" data-type="domain" data-function="Domain" class="pull-right" />
                                                        <input type="hidden" name="domains[]" value="<?php echo $domain['domainid']; ?>" />
                                                    </label>
                                                    <hr style="margin:10px 0 0px;" />
                                                </div>                                            
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <br />

                                    <div class="col-sm-2"></div>

                                    <div class="col-sm-8">
                                        <input type="text" id="domain" name="domain" maxlength="255" value="http://" class="form-control" />
                                    </div>

                                    <div class="col-sm-2">
                                        <a id="add-domain" class="btn btn-primary" data-type="domain" data-function="Domain" data-add-domain><span><?php echo $button_domain; ?></span></a>
                                    </div>

                                </div>




                                <!-- Account Competitors -->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="input-name">
                                        <?php echo $entry_account_competitor; ?> <br />
                                        <small>( <?php echo $entry_automatic_update; ?> )</small>
                                    </label>
                                    <div class="col-sm-10">

                                        <div id="competitors" class="well well-sm scrollbox" style="height:150px; overflow:auto;">
                                            <?php foreach ($competitors as $competitor) { ?>
                                                <div class="checkbox">
                                                    <label style="display:block;">
                                                        <?php echo $competitor['name']; ?> <small>( <?php echo $competitor['domain']; ?> )</small>
                                                        <img src="view/image/delete.png" alt="" data-type="competitor" data-function="Competitor" class="pull-right" />
                                                        <input type="hidden" name="competitors[]" value="<?php echo $competitor['competitorid']; ?>" />
                                                    </label>
                                                    <hr style="margin:10px 0 0px;" />
                                                </div>                                            
                                            <?php } ?>
                                        </div>

                                    </div>

                                    <br />

                                    <div class="col-sm-2"></div>

                                    <div class="col-sm-8">
                                        <input type="text" id="competitor" name="competitor" maxlength="255" value="http://" class="form-control" />
                                    </div>

                                    <div class="col-sm-2">
                                        <a id="add-competitor" class="btn btn-primary" data-type="competitor" data-function="Competitor" data-add-competitor><span><?php echo $button_competitor; ?></span></a>
                                    </div>

                                </div>


                                <!-- Report -->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="input-name">
                                        <span data-toggle="tooltip" data-original-title="<?php echo $notifications[0]['description']; ?>"><?php echo $notifications[0]['name']; ?></span> <br />
                                        <small>( <?php echo $entry_automatic_update; ?> )</small>
                                    </label>     
                                    <div class="col-sm-10">
                                        <input type="hidden" name="report[id]" value="<?php echo $notifications[0]['notificationid']; ?>" />
                                        <select name="report[frequency]">
                                          <option value="0"<?php if( $notifications[0]['frequency'] == 0 ){ ?> selected="selected"<?php } ?>>Never email this report</option>
                                          <option value="1"<?php if( $notifications[0]['frequency'] == 1 ){ ?> selected="selected"<?php } ?>>Email me updates every day</option>
                                          <option value="2"<?php if( $notifications[0]['frequency'] == 2 ){ ?> selected="selected"<?php } ?>>Email me updates every week</option>
                                        </select>
                                    </div>
                                </div>



                            <?php } ?>

                        <!-- /End #tab-account -->
                        </div>



                        <!-- Tab Plan -->
                        <div class="tab-pane form-horizontal" id="tab-plans">

                            <?php if( $this->registry->get('SEOMatic')->accountid ){ ?>

                                <div class="table-responsive">

                                    <h2>Manage Your Plan</h2>

                                    <table class="table tab-bordered table-hover">

                                        <thead>
                                            <tr>
                                                <td class="text-left"><?php echo 'Plan Name'; ?></td>
                                                <td class="text-left"><?php echo 'Cost'; ?></td>
                                                <td class="text-left"><?php echo 'Coupon'; ?></td>
                                                <td class="text-left"><?php echo 'Keywords Included'; ?></td>
                                                <td class="text-left"><?php echo 'Keywords Remaining'; ?></td>
                                                <td class="text-left"><?php echo 'Expiry / Renewal Date'; ?></td>
                                                <td class="text-center" width="50"><?php echo 'Status'; ?></td>
                                                <td class="text-center" width="90"><?php echo 'Subscribe'; ?></td>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($plans as $plan){ ?>
                                                <tr class="<?php echo $plan['id']; if($plan['id'] == $account['subscription']){ echo ' active'; }?>">
                                                




                                                    <!-- Plan Name -->
                                                    <td class="text-left plan"><?php 

                                                        echo $plan['name']. ' ';

                                                        if( $plan['trial_period_days'] ){
                                                            echo '<small>('.$plan['trial_period_days'].' '.ucwords($plan['interval']).')</small>';
                                                        } 

                                                    ?></td>
                                                




                                                    <!-- Cost -->
                                                    <td class="text-left cost" data-amount="<?php echo $plan['amount']; ?>" data-currency="<?php echo strtoupper($plan['currency']); ?>" data-interval="<?php echo ucwords($plan['interval']); ?>"><?php 

                                                        if( $plan['id'] != 'trial' && $plan['id'] == $account['subscription'] && !empty($subscription['discount']) ){

                                                            //Get the Coupon
                                                            $coupon   = $subscription['discount']['coupon'];

                                                            //Get the Coupon Duration
                                                            switch( $coupon['duration'] ){
                                                                case 'once':
                                                                    $duration = ' for 1 Month';
                                                                    break;

                                                                case 'repeating':
                                                                    $duration = ' for '.$coupon['duration_in_months'].' Months';
                                                                    break;

                                                                case 'forever':
                                                                    $duration = ' Forever';
                                                                    break;
                                                            } 


                                                            //Get the Amount Off
                                                            if( $coupon['amount_off'] ){

                                                                $amount = ( $plan['amount'] - $coupon['amount_off'] ) / 100;
                                                                $amount = '$' . number_format( ( $amount > 0 ? $amount : 0 ) , 2 );

                                                            }else{

                                                                $amount = $plan['amount'] * ( $coupon['percent_off'] / 100 );
                                                                $amount = ( $plan['amount'] - $amount ) / 100;
                                                                $amount = '$' . number_format( ( $amount > 0 ? $amount : 0 ) , 2 );

                                                            }


                                                            //Show the Discount
                                                            echo $amount . ' ' . strtoupper($plan['currency']) . $duration ;

                                                        }else{
                              
                                                            //Show the Full Price
                                                            echo '$' . number_format( ( $plan['amount'] / 100 ) , 2 ) . ' ' . strtoupper($plan['currency']) . ' / ' . ucwords($plan['interval']); 
                              
                                                        }

                                                    ?></td>
                                                




                                                    <!-- Coupon -->
                                                    <td class="text-left coupon"><?php

                                                        if( $plan['id'] != 'trial' && $plan['id'] == $account['subscription'] && !empty($subscription['discount']) ){

                                                            //Get the Coupon
                                                            $coupon   = $subscription['discount']['coupon'];


                                                            //Get the Coupon Duration
                                                            switch( $coupon['duration'] ){
                                                                case 'once':
                                                                    $duration = ' for 1 Month';
                                                                    break;

                                                                case 'repeating':
                                                                    $duration = ' for '.$coupon['duration_in_months'].' Months';
                                                                    break;

                                                                case 'forever':
                                                                    $duration = ' Forever';
                                                                    break;
                                                            } 


                                                            //Get the Amount Off
                                                            if( $coupon['amount_off'] ){

                                                                $amount = '$' . number_format( ( $coupon['amount_off'] / 100 ) , 2 );

                                                            }else{

                                                                $amount = $coupon['percent_off'] . '%';

                                                            }


                                                            //Show the Coupon
                                                            echo $amount . ' Off ' . $duration ;


                                                        }

                                                    ?></td>






                                                    <!-- Keywords Included -->
                                                    <td class="text-left keywords"><?php 

                                                        echo $plan['keywords']; 

                                                    ?></td>
                                                

                                                    <!-- Keywords Remaining -->
                                                    <td class="text-left remaining"><?php 

                                                        if($plan['id'] == $account['subscription']){ 

                                                            echo $available; 

                                                        } 

                                                    ?></td>
                                                







                                                    <!-- Expires / Renews -->
                                                    <td class="text-left renewal"><?php

                                                        if( $plan['id'] == 'trial' && time() <= strtotime($account['trial']) ){
                                                        
                                                            //Show Trial Expiry Date
                                                            echo date('M d, Y',strtotime($account['trial']));

                                                        }else
                                                        if( !empty($subscription['plan']) && $plan['id'] == $subscription['plan']['id'] ){
                                                        
                                                            //Show Plan Expiry Date
                                                            echo date('M d, Y',$subscription['current_period_end']);

                                                        }

                                                    ?></td>






                                                    <!-- Status -->
                                                    <td class="text-center status"><?php

                                                        if( $account['subscription'] == $plan['id'] ){


                                                            //Validate Trial
                                                            if( $plan['id'] == 'trial' ){

                                                                if( time() <= strtotime($account['trial']) ){

                                                                    echo 'Active';

                                                                }else{

                                                                    echo 'Expired';

                                                                }

                                                            }else

                                                            //Validate Everything Else
                                                            if( empty($subscription['cancel_at_period_end']) ){

                                                                echo 'Active';

                                                            }else{

                                                                echo 'Cancelled';

                                                            }

                                                        }

                                                    ?></td>
                                                





                                                    <!-- Subscribe / Unsubscribe -->
                                                    <td class="text-center subscription"><?php 
                                                        if( $plan['id'] != 'trial' ){
                                                            if( $plan['id'] == $account['subscription'] && empty($subscription['cancel_at_period_end']) ){

                                                                echo '<button type="button" name="unsubscribe" class="btn btn-primary" value="'.$plan['id'].'">Unsubscribe</button>';

                                                            }else{
                                                          
                                                                echo '<button type="button" name="subscribe" class="btn btn-primary" value="'.$plan['id'].'">Subscribe</button>';

                                                            }
                                                        } 
                                                    ?></td>





                                                </tr>

                                            <?php } ?>
                                                
                                                <tr>

                                                    <td class="text-right" colspan="5">Add Coupon:</td>

                                                    <td class="text-left" colspan="2">
                                                        <input type="text" name="coupon" placeholder="Coupon" class="form-control" />
                                                    </td>

                                                    <td class="text-center">
                                                        <button type="button" id="add-coupon" class="btn btn-primary">Add Coupon</button>
                                                    </td>

                                                </tr>

                                            </tbody>
                                        </table>
                                    </div>


                                    <div class="table-responsive">

                                        <h2>How it works</h2>

                                        <table class="table tab-bordered table-hover">
                                            
                                            <thead>
                                                <tr>
                                                    <td class="left"><?php echo 'Topic'; ?></td>
                                                    <td class="left"><?php echo 'Information'; ?></td>
                                                </tr>
                                            </thead>
                                            
                                            <tbody>

                                                <tr>
                                                    <td class="left">
                                                        How often are the Keywords Scraped?
                                                    </td>
                                                    <td class="left">
                                                        Unlike your typical SEO program, SEOMatic scrapes your keyword once every single day. 
                                                        <br /> 
                                                        This means up-to-date information exactly when you need it!
                                                    </td>
                                                </tr>
                                              
                                                <tr>
                                                    <td class="left">
                                                        What does 1 Keyword get me?
                                                    </td>
                                                    <td class="left">
                                                        Each Keyword works out to a Search Engine Query (Bing, Yahoo, Google) in search of your Domain SERP ranking. 
                                                    </td>
                                                </tr>
                                              
                                                <tr>
                                                    <td class="left">
                                                        How many Keywords do I have avaialble with my plan?
                                                    </td>
                                                    <td class="left">
                                                        Currently you have: <span id="remaining"><?php echo $account['keywords']; ?></span> Keywords
                                                    </td>
                                                </tr>
                                              
                                                <tr>
                                                    <td class="left">
                                                        Do I Need an SEOMatic Plan?
                                                    </td>
                                                    <td class="left">
                                                        To utilize SEOMatic's Paid features you will need a Subscription, but there are plenty of features that are free to use!
                                                    </td>
                                                </tr>

                                                <tr>
                                                    <td class="left">
                                                        If I want to change my plan in the middle of a billing period?
                                                    </td>
                                                    <td>
                                                        SEOMatic's plans are prorated meaning we will only charge you the difference for the remaining month. 
                                                        <br />
                                                        For example, if it is the middle of the month and you change from a 50$ plan to a 100$ plan, you will be charged an additional 25$ for the remaining half month.
                                                    </td>
                                                </tr>

                                                <tr>
                                                    <td class="left">
                                                        What if I don't like SEOMatic?
                                                    </td>
                                                    <td>
                                                        SEOMatic is comes with no obligations and no risk! If you aren't fully satisfied with your account within 30 days we'll refund your money!
                                                    </td>
                                                </tr>

                                            </tbody>

                                        </table>

                                    </div>



                                    <style type="text/css">
                                        #tab-plans .active td {
                                            background-color:#FFFFCB !important;
                                        }
                                        #tab-plans tr:hover td {
                                            background-color:transparent;
                                        }
                                  </style>
                    
                            <?php }else{ ?>

                                <p style="display:block; width:100%; text-align:center; font-size:20px; padding:60px 0; color:#CCC;">
                                    You must create a Campaign First
                                </p>

                            <?php } ?>

                        </div>



                        <div class="tab-pane form-horizontal" id="tab-cards">

                            <!-- Credit Card List -->
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-name">
                                    <?php echo $entry_creditcard_list; ?> <br />
                                    <small>( <?php echo $entry_automatic_update; ?> )</small>
                                </label>
                                <div class="col-sm-10">

                                    <div id="creditcards" class="well well-sm scrollbox" style="height:150px; overflow:auto;">
                                        <?php foreach ($creditcards as $i=>$creditcard) { ?>                              
                                            <div class="checkbox">
                                                <div style="display:block;">
                                                    <?php

                                                        //Find the Brand
                                                        foreach( $cards as $card ){

                                                            if( $card['brand'] == $creditcard['brand'] ){

                                                                //Is it the Default Card?
                                                                $default = $user['default_card'] == $creditcard['id'];

                                                                //Show the Default Selector
                                                                echo '<input type="radio" name="defaultcard" value="'.$creditcard['id'].'"'. ( $default ? ' checked="checked"' : '' ) .' />';

                                                                //Add the Brand Image
                                                                echo '<img src="'.$card['image'].'" alt="'.$card['brand'].'" style="display:inline; float:none; height:15px; margin-right:2px;" />';

                                                                //Set the Count
                                                                $count = 0;


                                                                /** Editted for PHP 5.2+ **/
                                                                if( !function_exists('credit_card_replace') ){
                                                                    function credit_card_replace( $match ){

                                                                        global $creditcard;
                                                                        global $count;

                                                                        return $creditcard['last4'][ $count++ ];

                                                                    }
                                                                }

                                                                //Output Asterisks
                                                                echo preg_replace_callback( '/\[0-9\]/' , 'credit_card_replace' , $card['hidden'] );

                                                                //Output Last 4 Digits
                                                                echo $creditcard['last4'];

                                                                //Get the Formatted Hidden Text
                                                                /* echo preg_replace_callback( '/\[0-9\]/', function( $match ) use($creditcard, &$count){ 
                                                                    return $creditcard['last4'][ $count++ ];
                                                                }, $card['hidden'] ); */

                                                                //Show the Credit Card Expiry & Default Card Text
                                                                echo ' <small>( '.$creditcard['exp_month'].' / '.$creditcard['exp_year'].' )'.( $default ? '<strong> - Default Card</strong>' : '' ).'</small>';

                                                            }

                                                        }
                                                    ?>
                                                    <img src="view/image/delete.png" alt="" data-type="card" data-function="Card" class="pull-right" />
                                                    <input type="hidden" name="creditcards[]" value="<?php echo $creditcard['id']; ?>" />
                                                </div>
                                                <hr style="margin:10px 0 0px;" />
                                            </div>                                
                                        <?php } ?>
                                    </div>
                                </div>

                            </div>




                            <!-- Add a Credit Card -->
                            <div class="form-group" id="add-creditcard">

                                <div class="clearfix">

                                    <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_creditcard_add; ?></label>
                                    <div class="col-sm-4">
                                        <input type="text" size="20" name="name" autocomplete="off" placeholder="Name on Card" class="form-control" />
                                    </div>

                                </div>

                                <br />

                                <div class="clearfix">

                                    <div class="col-sm-2"></div>
                                    <div class="col-sm-4">
                                        <input type="text" size="20" name="creditcard" autocomplete="off" placeholder="Card Number" class="form-control" style="margin-right:3px;" />
                                    </div>

                                </div>

                                <br />

                                <div class="clearfix">

                                    <div class="col-sm-2"></div>
                                    <div class="col-sm-2">
                                        <input type="text" size="8" name="exp" autocomplete="off" placeholder="MM / YY" class="form-control" />
                                    </div>
                                    <div class="col-sm-2">
                                        <input type="text" size="4" name="cvc" autocomplete="off" placeholder="CVC" class="form-control" />
                                    </div>

                                </div>

                                <br />

                                <div class="clearfix">


                                    <div class="col-sm-2"></div>

                                    <div class="col-sm-4">
                                        <a class="btn btn-primary" ><span><?php echo $button_creditcard; ?></span></a>
                                    </div>

                                </div>

                            </div>


                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_creditcard_accepted; ?></label>

                                <div class="col-sm-8">
                                    <?php foreach($cards as $card){ ?>
                                        <?php if($card['accepted']){ ?>
                                            <img src="<?php echo $card['image']; ?>" alt="<?php echo $card['brand']; ?>" />
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-name"><?php echo $entry_creditcard_secured; ?></label>
                                <div class="col-sm-8">
                                    <img src="<?php echo $security['image']; ?>" alt="<?php echo $security['name']; ?>" />
                                    <img src="//seomatic.co/api/images/icons/money-back-guarantee.png" alt="Moneyback Guarantee" width="80" />
                                </div>
                            </div>

                        <!-- /End #tab-cards -->
                        </div>




                        <!-- Invoices Tab -->
                        <div class="tab-pane form-horizontal" id="tab-invoices">

                            <div class="table-responsive">

                                <table class="table tab-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <td class="text-center" width="100"><?php echo 'Date'; ?></td>
                                            <td class="text-left"><?php echo 'Plan'; ?></td>
                                            <td class="text-center" width="50"><?php echo 'Subtotal'; ?></td>
                                            <td class="text-center" width="50"><?php echo 'Discount'; ?></td>
                                            <td class="text-center" width="50"><?php echo 'Total'; ?></td>  
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if( count($invoices) > 0 ){ ?>

                                            <?php foreach($invoices as $invoice){ ?>
                                                <tr>
                                                    <td class="text-center"><?php echo date('M d, Y',$invoice['date']); ?></td>
                                                    <td class="text-left"><?php echo $invoice['lines']['data'][0]['plan']['statement_description']; ?></td>
                                                    <td class="text-center"><?php echo '$' . number_format( ( $invoice['subtotal'] / 100 ) , 2 ); ?></td>
                                                    <td class="text-center"><?php
                                                        if( !empty($invoice['discount']) && !empty($invoice['discount']['coupon']) ){

                                                            //Get the Coupon
                                                            $coupon = $invoice['discount']['coupon'];


                                                            if( $coupon['amount_off'] ){

                                                                //Show the Discount
                                                                echo '$' . number_format( ( $coupon['amount_off'] / 100 ) , 2 );
                                                        
                                                            }else

                                                            if( $coupon['percent_off'] ){

                                                                //Get the Plan
                                                                $plan     = $invoice['lines']['data'][0]['plan'];

                                                                //Get the Discount
                                                                $discount = ( $plan['amount'] / 100 ) * ( $coupon['percent_off'] / 100 );

                                                                //Show the Percentage Off
                                                                echo '$' . number_format( ( $discount > 0 ? $discount : 0 ) , 2 );

                                                            }


                                                        }
                                                    ?></td>
                                                    <td class="center"><?php echo '$' . number_format( ( $invoice['total'] / 100 ) , 2 ); ?></td>
                                                </tr>
                                            <?php } ?>

                                        <?php }else{ ?>

                                            <tr>
                                                <td class="text-center" colspan="100%">No Invoices Yet</td>
                                            </tr>

                                        <?php } ?>
                                    </tbody>
                                
                                </table>

                            </div>

                        <!-- /End #tab-invoices -->
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



    //Preset Data
    var prefix      = 'http://',
        running     = {domain:false, competitor:false, cards:false},
        creditcards = { list:<?php echo json_encode($cards); ?>, active:null },
        safeKey     = false,
        Keywords    = {
            used:  <?php echo isset($used)? count( $used ) : 0 ; ?>
        };




























    /**
    *
    * Update Single Input Fields:
    *    Fields:
    *       - Account Name
    *       - Email
    *       - Password
    *       - Username
    *
    *
    **/
    $('#update-account-name,#update-default-country,#update-email,#update-password,#update-username').click(function(e){

      //Save the Button
      var btn         = this,
          id          = $(this).attr('id'),
          validation  = true;

      //Remove any Errors
      $(btn).next('small').remove();


      //Validate the Password
      if( id == 'update-password' ){

        //Ensure the Passwords are Equal
        if( $('input[name="password[]"]:eq(0)').val() != $('input[name="password[]"]:eq(1)').val() ){

          //Set Validation to Failed
          validation = false;

          //Show the Error
          $(btn).after('<small style="margin-left:5px; color:#F00;">Passwords do not match</small>');

        }

      }else
      if( $(this).val() != '' ){

        //Set the Validation to Failed
        validation = false;

        //Show the Error
          $(btn).after('<small style="margin-left:5px; color:#F00;">Value cannot be empty</small>');

      }


      //Only run if the Validation was Successful
      if( validation ){

        //Prepare the Data
        var data    = {},
            input   = $(btn).parents('.form-group').find('input:first,select:first'); 

        //Set the Data
        data[ $(input).attr('name') ] = $(input).val();

        //Post the Request
        $.post('index.php?route=seomatic/account/'+$(btn).data('function')+'&token=<?php echo $token; ?>',data,function(obj){

          //Ensure the Update Worked
          if( obj.result ){

            //Show Success
            $(btn).after('<small style="margin-left:5px; color:#090;">'+$(btn).data('name')+' Updated!</small>');
            $(btn).next('small').fadeOut(3000,function(){
              $(this).remove();
            });

          }else{

            //Show the Error
            $(btn).after('<small style="margin-left:5px; color:#F00;">'+obj.error+'</small>');

          }

        },'json');

      }

    });




























 
























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
          type      = $(btn).data('type'),
          func      = $(btn).data('function'),
          existing  = [],
          domain    = $('#'+type).val().replace(/http(?:s)?:\/\/(?:www\.)?/g,'');


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
        $.post('index.php?route=seomatic/account/domain&token=<?php echo $token; ?>',{ 'domain': $('#'+type).val() },function(data){


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

            //Prepare Data
            var data        = {};
                data[type]  = $('#'+type).val(); 




            //Send Update to SEOMatic
            $.post('index.php?route=seomatic/account/add'+func+'&token=<?php echo $token; ?>', data, function(obj){

              //Prepare Result
              var obj = $.parseJSON(obj);

              //Check the Result
              if( obj.result ){

                //Add the Domain
                $('#'+type+'s').append(
                  '<div class="checkbox">'+
                        '<label style="display:block;">'+
                            name +
                            '<img src="view/image/delete.png" alt="" data-type="'+type+'" data-function="'+func+'" class="pull-right" />'+
                            '<input type="hidden" name="'+type+'s[]" value="'+obj[ type + 'id' ]+'" />'+
                        '</label>'+
                        '<hr style="margin:10px 0 0px;" />'+
                  '</div>'
                );

              }else{

                //An Error Occured
                $(btn).after('<small style="margin-left:5px; color:#F00;">'+obj.error+'</small>');

              }

            });





          }else{

            //Show the Error
            $(btn).after('<small style="margin-left:5px; color:#F00;">'+data.error+'</small>');

          }


          //Reset the Input
          $('#'+type).val( prefix );

        });
      
      }

    });



























   
































    //Warn people before deleting Domains if none remain
    $('.scrollbox').on('click','img[data-type="competitor"][src*="delete"], img[data-type="domain"][src*="delete"]',function(e){

        //If no Domains or Competitors Remain
       if( $('input[name="domains[]"],input[name="competitors[]"]').length == 1 ){

            //Send Confirmation
            if( !confirm( "Deleting all of your Domains & Competitors will Deactivate any Active Keywords. \n\n Are you sure you want to continue?" ) ){

                //Prevent the Delete from Running
                e.stopImmediatePropagation();

            }

        }

        //Continue Propagation

    });
























    //Remove a Scrollbox Item
    $('.scrollbox').on('click','img[src*="delete"]',function(){
      
      //Prepare Data
      var btn     = this,
          input   = $(this).next('input[type="hidden"]'),
          type    = $(this).data('type'),
          func    = $(this).data('function'),
          message = $('[data-add-'+type+']'),
          data    = {};

      //Add the ID
      data[type+'id'] = $(input).val();

      //Remove any Error
      $(parent).children('small').remove();

      //Post the Request
      $.post('index.php?route=seomatic/account/delete'+func+'&token=<?php echo $token; ?>', data, function(obj){

        //Load the Object
        var obj = $.parseJSON(obj);

        //Check the Result
        if( obj.result ){

            //Remove the Field
            $(btn).parents('.checkbox').remove();

        }else{

            //Show the Error
            $(message).after('<small style="margin-left:5px; color:#F00;">'+obj.error+'</small>');

        }

      });

    });














    //Update the Notification Report Frequency
    $('[name="report[frequency]"]').change(function(){

      //Prepare the Data
      var data = {
          notificationid:   $(this).prevAll('input').val(),
          frequency:        $(this).val()
      };

      $.post('index.php?route=seomatic/account/report&token=<?php echo $token; ?>', data, function(obj){

            //Load the Object
            var obj = $.parseJSON(obj);

            //Check the Result
            if( !obj.result ){

                //Output the Error
                alert( obj.error );

            }

      });

    });
































    //Only Allow Numbers
    $('#add-creditcard input[name!="name"]').keydown(function(e){

      //Allow Keys
      if($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 || ( (e.keyCode == 86 || e.keyCode == 82 || e.keyCode == 65) && (e.metaKey || e.ctrlKey === true)) || (e.keyCode >= 35 && e.keyCode <= 39)) {
        
        //Set it as a Safe Key
        safeKey = true;

        //Return
        return;

      }

      //Only Numbers
      if((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {

          //Set it as an Unsafe Key
          safeKey = false;
          
          //Prevent the Default
          e.preventDefault();

      }

    });




























      //Only allow integers
      $('input[name="creditcard"]').keydown(function(e){

        
        //Preset Data
        var card = $(this).val().replace(/[^0-9]/g,''),
            trim = $.trim( $(this).val().slice(0,-1) );


        //Find the Credit Card
        for( var i=0; i<creditcards.list.length; i++ ){

          //Check the Type
          if(card.match( new RegExp(creditcards.list[i].verification) )){

            //Set the Active Card
            creditcards.active = i;


            //Add Credit Card Icon
            if( $(this).next('img').length == 0 ){

              //Remove any possible Error
              $(this).next('small').remove();

              //Add the Image
              $(this).after('<img src="'+creditcards.list[i].image+'" alt="'+creditcards.list[i].brand+'" style="height:20px; position:relative; top:5px;" />');
            
            }


            //If the Credit Card is NOT accepted, Show the Error
            if( !creditcards.list[i].accepted && $(this).nextAll('small').length == 0 ){

              //Show Error
              $(this).next('img').after('<small style="margin-left:5px; color:#F00;">'+'Creditcard Not Accepted'+'</small>');
            
            }

            //End the Loop
            break;

          }

        }



        //Show Invalid Card
        if( creditcards.active == null && card.length > 4 && $(this).nextAll('small').length == 0 ){

          //Show Error
          $(this).after('<small style="margin-left:5px; color:#F00;">'+'Invalid Credit Card'+'</small>');

        }



        //Preset they Key
        key = creditcards.active !== null? creditcards.active : 1 ;


        //If the Last Character is a String, Remove it
        if( e.keyCode == 8 && trim != $(this).val().slice(0,-1) ){
          
          //Set the New Value
          $(this).val( trim );

          //Stop from Completing
          e.preventDefault();

          //Return
          return;

        }


        //Limit the Length of the Card, Allow Keys
        if( card.length >= creditcards.list[ key ].length && $.inArray(e.keyCode, [37, 38, 39, 40, 46, 8, 9, 27, 13, 110, 190]) === -1 && !e.metaKey && !e.ctrlKey ){
          e.preventDefault();
          return;
        }

        //Add a Space if the Regex Passes
        if( new RegExp(creditcards.list[ key ].separation).exec( card ) && ( ( e.keyCode >= 48 && e.keyCode <= 57 ) || ( e.keyCode >= 95 && e.keyCode <= 105 ) ) ){
          $(this).val( $(this).val() + ' ' );
        }

        //Return
        return;

      });



























      //On Key up Ensure Card is Validated
      $('input[name="creditcard"]').keyup(function(e){

        //Get the Card
        var card = $(this).val().replace(/[^0-9]/,'');

        //Check if the Card is Active
        if( creditcards.active !== null && !card.match( new RegExp(creditcards.list[ creditcards.active ].verification) ) ){

            //Remove any Existing Error
            $(this).nextAll('small').remove();

            //If Not, Remove the Icon
            $(this).next('img').remove();

            //Set Active to NULL
            creditcards.active = null;

        }else
        if( card.length < 4 ){

          //Remove Invalid Card Error
          $(this).next('small').remove();

        }

      });


































      $('input[name="creditcard"]').on('paste',function(e){
        
        //Save the Element
        var el    = this;

        //Set Timeout to Run Function
        setTimeout(function(){

          //Save the Card
          var card = $(el).val().replace(/[^0-9]/g,'');

          //Remove all but numbers
          $(el).val( card );

          //Prepare the Keydown Event
          var e = jQuery.Event('keydown',{
            which:    37,
            keyCode:  37
          });


          //Trigger Keydown
          $(el).trigger(e).promise().done(function(e){


            //Preset they Key
            key = creditcards.active !== null? creditcards.active : 1 ;

            //Force the Card Length
            card.substr( 0 , creditcards.list[ key ].length );
            
            //Separate the Card
            var separation  = new RegExp(creditcards.list[ key ].separation).exec( card ),
                storage     = '';

            //Find the Closest Separation Point
            while( !separation && card.length > 1 ){
              storage     = card.charAt( card.length - 1 );
              card        = card.slice(0,-1);
              separation  = new RegExp(creditcards.list[ key ].separation).exec( card );
            }

            //If there was a Separation
            if( separation ){

              //A Holder for all of the Separation that is defined
              var separated = [];

              //Remove all Undefined Separation Fields
              for( var i=0; i<separation.length; i++){
                if( typeof separation[i] != 'undefined' ) separated.push( separation[i] );
              }

              //Build the String
              var string = separated.slice(1).join(' ') + (storage!=''? ' '+storage : '' )

              //Add the Separated Value
              $(el).val( string )

            }        

          //End $(el).trigger(e).promise().dome(function(e){
          });

        //End setTimeout(function(){
        },0);

      //End $(input[name="creditcard"]
      });



































      //Add the Separator to the Expiry Date
      $('input[name="exp"]').keydown(function(e){

        //Split the String
        var value = $(this).val().replace(/[^0-9]/g,''),
            exp   = value.match(/^([0-9]{2})([0-9]{1,2})?$/),
            trim  = $(this).val().slice(0,-1).replace(/ \/ $/,'');

        //If the Last Character is a String, Remove it
        if( e.keyCode == 8 && trim != $(this).val().slice(0,-1) ){
          
          //Set the New Value
          $(this).val( trim );

          //Stop from Completing
          e.preventDefault();

          //Return
          return;

        }

        //Only allow 4 Characters
        if( value.length >= 4  && $.inArray(e.keyCode, [37, 38, 39, 40, 46, 8, 9, 27, 13, 110, 190]) === -1 && !e.metaKey && !e.ctrlKey ){
          e.preventDefault();
          return
        }

        //Add the Separator if the Expression matches
        if( exp && ( ( e.keyCode >= 48 && e.keyCode <= 57 ) || ( e.keyCode >= 95 && e.keyCode <= 105 ) ) ){

          //Add the Slash in between the Month
          $(this).val( exp.slice(1).join(' / ') );

        }

        //Return
        return;

      });



































      //Add the Separator to the Expiry Date on Paste
      $('input[name="exp"]').on('paste',function(e){
        
        //Save the Element
        var el    = this;

        //Set Timeout to Run Function
        setTimeout(function(){

          //Save the Card
          var value = $(el).val().replace(/[^0-9]/g,''),
              exp   = value.match(/^([0-9]{2})([0-9]{1,2})?$/);

          //Clear all Non Numbers
          $(el).val( value );

          //if the Expression was Passed
          if( exp ){
          
            $(el).val( exp.slice(1).join(' / ') ); 

          }

        //End setTimeout(function(){
        },0);

      //End $(input[name="creditcard"]
      });


































      $('#add-creditcard .btn').click(function(e){
        if( !running.cards ){

          //Save the Button
          var btn = this;

          //Remove Any Errors
          $(btn).next('small').remove();

          //Show the Loading
          $(btn).after('<small style="margin-left:5px;"> <img src="view/image/loading.gif"/> Validating Card </small>');

          //Modify the Style to show Disabled
          $(btn).css('background','#999');

          //Set Running State to Active
          running.cards = true;

          //Get the Form
          var form      = $('#add-creditcard :input').serializeArray(),
              data      = {};

          //Build the Data
          for( var i=0; i < form.length; i++ ){
            data[ form[i].name ] = form[i].value;
          }


          //Add the Credit Card
          $.post('index.php?route=seomatic/account/addCard&token=<?php echo $token; ?>', data, function(obj){

            //Get the Object
            var obj = $.parseJSON(obj);

            //Remove The Loading
            $(btn).next('small').remove();

            //Remove the Style
            $(btn).removeAttr('style');

            //End the Running State
            running.cards = false;

            //Check the Success of the Post
            if( obj.result ){

              //Get the Card
              var card  = creditcards.list[ creditcards.active ],
                  last4 = data.creditcard.replace(/[^0-9]{4}$/g,''),
                  exp   = data.exp.replace(/[^0-9]/g,''),
                  index = 0;

              //Remove the Default Card Settings
              $('#creditcards :checked').prop('checked',false);
              $('#creditcards small strong').remove();

              //Show the New Card
              $('#creditcards').prepend(
                '<div class="checkbox">'+
                    '<div style="display:block;">'+
                        '<input type="radio" name="defaultcard"  value="'+obj.cardid+'" checked="checked" />'+
                        '<img src="'+card.image+'" alt="'+card.brand+'" style="display:inline; float:none; height:15px; margin-right:2px;" />'+
                        card.hidden.replace(/\[0-9\]/g,function(str){
                            return last4.charAt( index++ );
                        })+
                        ' '+
                        '<small>( '+exp.substr(0,2)+' / 20'+exp.slice(-2)+' ) <strong> - Default Card</strong></small>'+
                        '<img src="view/image/delete.png" alt="" data-type="card" data-function="Card" class="pull-right" />'+
                        '<input type="hidden" name="creditcards[]" value="'+obj.cardid+'" />'+
                    '</div>'+
                '</div>'
              );

              //Empty the Form
              $('#add-creditcard :input').val('');

              //Remove the Credit Card Icon
              $('input[name="creditcard"] + img').remove();

            }else{

              //Show the Error
              $(btn).after('<small style="margin-left:5px; color:#F00;"> '+obj.error+' </small>');

            }


          });

        }
      });































      //Set Default Card
      $('#creditcards').on('click','input[type="radio"]:enabled',function(){

          //Save the Elements
          var el        = this,
              current   = $('#creditcards small:has(strong)').prevAll('input[type="radio"]');

          //Disable ALL Radio Fields
          $('#creditcards input[type="radio"]').prop('disabled',true);

          //Remove the New Selection until Validation
          $(this).prop('checked',false);

          //Reset the Previous Selection
          $(current).prop('checked',true);

          //Remove any Errors
          $('#creditcards').next('small').remove();
          
          //Update the Credit Card
          $.post('index.php?route=seomatic/account/defaultCard&token=<?php echo $token; ?>', { cardid: $(this).val() }, function(obj){

            //Parse the JSON
            var obj = $.parseJSON( obj );

            //Remove all Disabling
            $('#creditcards input[type="radio"]').prop('disabled',false);

            //if the Result is Successful
            if( obj.result ){

              //Remove the Default Card Settings
              $('#creditcards :checked').prop('checked',false);
              $('#creditcards small strong').remove();

              //Set the New Card Settings
              $( el ).prop('checked',true);
              $( el ).nextAll('small').append('<strong> - Default Card</strong>');

            }else{

              //Show the Error
              $('#creditcards').after('<small style="margin-left:5px; color:#F00;"> '+obj.error+' </small>');

            }

          });

      });































      //Subscription Button
      $('#tab-plans').on('click','button[name="subscribe"]',function(){

        //Save the Button
        var btn       = this,
            current   = $('#tab-plans .active'),
            curbtn    = $( current ).find('[name="unsubscribe"]'),
            parent    = $(this).closest('tr'),
            message   = 'You are about to purchase the ' + $.trim( $(parent).find('.plan').text() ) + ' Plan, Are you sure you want to continue?',
            remaining = parseInt($(parent).find('.keywords').html()) - Keywords.used ;

        //Ensure they meant to make a purchase
        if( confirm(message) ){

          //Disable all the Button
          $('#tab-plans button').prop('disabled',true);

          //Post the 
          $.post('index.php?route=seomatic/account/subscribe&token=<?php echo $token; ?>', { 'planid':$(btn).val(), coupon:$('#tab-plans input[name="coupon"]').val() }, function(obj){

            //Parse the JSON
            obj = $.parseJSON(obj);

            //Remove all Disables
            $('#tab-plans button').prop('disabled',false);

            //Check the result
            if( obj.result ){

              //Remove and Replace the Current Subscription
              $( curbtn ).replaceWith('<button type="button" name="subscribe" class="btn btn-primary" value="'+$(curbtn).val()+'">Subscribe</button>');
              $( current ).removeClass('active');

              //Setup the New Subscription
              $(parent).addClass('active');
              $(btn).replaceWith('<button type="button" name="unsubscribe" class="btn btn-primary" value="'+$(btn).val()+'">Unsubscribe</button>');

              //Null all Active Columns
              $( current ).find('.status').html('');

              //Null the Expiry
              $( current ).find('.expiry').html('');

              //Null the Renewal
              $( current ).find('.renewal').html('');

              //Null all Non Subscribed Coupons
              $('#tab-plans tr:not(.active) .coupon').html('');

              //Null the Current Keywords Remaining
              $( current ).find('.remaining').html('');

              //Set Remaining for Subscription
              $( parent ).find('.remaining').html( remaining );

              //Set Remaining in Text
              $('#remaining').html( remaining );

              //Reset all Prices on Non Subscriptions
              $('#tab-plans tr:not(.active) .cost').each(function(e){

                //Reset the Price
                $(this).html( '$' + ( parseInt($(this).data('amount')) / 100 ).toFixed(2) + ' ' + $(this).data('currency') + ' / ' + $(this).data('interval') );

              });

              //Add the Expiry
              $(parent).find('.renewal').html( obj.subscription.current_period_end );

              //Add the Renewal
              $(parent).find('.status').html( 'Active' );

            }else{

              //An Error Occured
              alert( obj.error );

            }

          });


        }

      });





























      //Remove Subscription
      $('#tab-plans').on('click','button[name="unsubscribe"]',function(){

        //Save the Button
        var btn     = this,
            current = $('#tab-plans .active'),
            parent  = $(btn).parents('tr'),
            message = 'You are about to unsubscribe from the ' + $.trim( $(this).parents('tr').find('.plan').text() ) + ' Plan, Are you sure you want to continue?';

        //Ensure they meant to make a purchase
        if( confirm(message) ){

          //Disable all the Button
          $('#tab-plans button').prop('disabled',true);

          //Post the 
          $.post('index.php?route=seomatic/account/unsubscribe&token=<?php echo $token; ?>', {}, function(obj){

            //Parse the JSON
            obj = $.parseJSON(obj);

            //Remove all Disables
            $('#tab-plans button').prop('disabled',false);

            //Check the result
            if( obj.result ){

              //Remove and Replace the Current Subscription
              $( btn ).replaceWith('<button type="button" name="subscribe" class="btn btn-primary" value="'+$(btn).val()+'">Subscribe</button>');

              //Show Cancelled
              $(parent).find('.status').html( 'Cancelled' );

              //Add Renewal Date
              $(parent).find('.renewal').html( obj.subscription.current_period_end );
              

            }else{

              //An Error Occured
              alert( obj.error );

            }

          });


        }

      });



      


























      //Check Coupon
      $('#tab-plans #add-coupon').click(function(){

        //Save the Button
        var btn   = this,
            input = $('#tab-plans input[name="coupon"]');

        //Disable the Button
        $(this).prop('disabled',true);

        //Remove any Error or Message
        $(input).next('small').remove();

        //Post the Coupon
        $.post('index.php?route=seomatic/account/coupon&token=<?php echo $token; ?>', { coupon: $(input).val() }, function(obj){

          //Parse the JSON
          obj = $.parseJSON(obj);

          //Remove the Disable
          $(btn).prop('disabled',false);

          if( obj.result ){




            //Get the Duration
            if( obj.coupon.duration == 'once' ){
             
              //One Month Coupon
              var duration = ' for 1 Month' 
            
            }else
            if( obj.coupon.duration == 'repeating' ){

              //Coupon Repeating for x Months
              var duration = ' for ' + obj.coupon.duration_in_months + ' Months';

            }else
            if( obj.coupon.duration == 'forever' ){

              //Coupon lasts forever
              var duration = ' Forever';

            }







            //Loop through Each Coupon and Set Reduced Amount
            $('#tab-plans [data-amount]:not(:first)').each(function(){




              //Preset Data
              var amount    = '',
                  currency  = '';





              //Get the Discount
              if( obj.coupon.amount_off ){
              
                //Dollar Value Discount
                amount = ( parseInt($(this).data('amount')) - parseInt(obj.coupon.amount_off) ) / 100;
                amount = ( amount < 0 ? '0.00' : amount.toFixed(2) );
              
              }else{

                //Percentage Discount
                amount = parseInt($(this).data('amount')) * ( obj.coupon.percent_off / 100 );
                amount = ( parseInt($(this).data('amount')) - amount ) / 100;
                amount = ( amount < 0 ? '0.00' : amount.toFixed(2) );
              
              }





              //Get the Currency
              if( obj.coupon.currency ){
                
                //Use the Passed Currency
                currency = obj.coupon.currency.toUpperCase();

              }else{

                //Use the Current Currency
                currency = $(this).data('currency');

              }




              //Show the New Amout
              $(this).html('$' + amount + ' ' + currency + duration ); 



            });







            //Preset Data
            var discount = '';


            //Get the Discount
            if( obj.coupon.amount_off ){

              //Dollar Value Discount
              discount = '$' + ( parseInt(obj.coupon.amount_off) / 100 ).toFixed(2);
            
            }else{

              //Percentage Discount
              discount = obj.coupon.percent_off + '%';

            }


            //Show the Discount Below the Input
            $('#tab-plans .coupon:not(:first)').html( discount + ' Off '+duration );





          }else{

            //Show the Error
            $(input).after('<small style="margin-left:5px; color:#F00; display:block;"> '+obj.error+' </small>');

          }

        });


      });






  //--></script> 



<?php echo $footer; ?>