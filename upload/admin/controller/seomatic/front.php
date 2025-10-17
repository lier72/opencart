<?php

	/**
	*
	*	SEOMatic Included into system/engine/front.php
	* 		- Only run in ADMIN
	*		- Load SEOMatic
	* 		- Check over the Current Installation
	* 		- Attempt to Log in to SEOMatic
	* 		- Create an Account Automagically if none exist
	*
	**/



	/**
	*
	*	Load the SEOMatic Version from VQMod
	*
	**/
	$vqmod = file_get_contents( $directory . 'vqmod/xml/seomatic.xml' ) ;

	//Find the Version
	preg_match( '/<version>(.+?)<\/version>/', $vqmod, $matches );

	//Get the Version
	$version = count( $matches ) > 0 ? $matches[1] : null ;




	/**
	*
	*	SEOMatic Initialization
	*
	**/

	
	// Load Required
	$registry->get('load')->helper('seomatic/index');

	// Setup SEOMatic
	$registry->set('SEOMatic', new SEOMatic);

	// Preset Data
	$url 						= $registry->get('url');
	$language 					= $registry->get('language');
	$SEOMatic 					= $registry->get('SEOMatic');
	$config 					= $registry->get('config');
	$load 						= $registry->get('load');
	$session 					= $registry->get('session');
	$request 					= $registry->get('request');
	$document 					= $registry->get('document');
	$redirect 					= $registry->get('redirect');
	$user 						= array(
		'email'		=> $config->get('seomatic_email'),
		'password' 	=> $config->get('seomatic_password'),
		'account' 	=> $config->get('seomatic_account')
	);

	
	//Preset SEOMatic Settings
	$SEOMatic->installed 	= $config->get('seomatic_installed');
	$SEOMatic->connected 	= false;
	$SEOMatic->accountid 	= false;
	$SEOMatic->expired 		= false;
	$SEOMatic->opencart 	= array( 'version' => VERSION , 'identifiers' => explode('.',VERSION) );
	$SEOMatic->email 		= $user['email'];
	$SEOMatic->password 	= $user['password'];
	$SEOMatic->platform 	= 'opencart';
	$SEOMatic->version 		= $version;

	$SEOMatic->debug(array(
		'benchmark' 	=> false,
		'output' 		=> false
	));	






	/**
	*
	*	SEOMatic Update Request
	*
	**/

	//Get the Last time it was Updated
	$updated = $SEOMatic->cache->get('update');	

	//Check the Cache and run the Update once per hour - If Caching is unwriteable we cache the update check to the admin and ONLY run if it is the admin user logged in to the administration
	if( $version && ( ( ( $SEOMatic->cache->writable && !$updated ) || ( !$SEOMatic->cache->writable && ( defined('HTTP_CATALOG') && !empty($session->data['token']) && ( !isset($SEOMatic->session->updated) || time() > $SEOMatic->session->updated ) ) ) && ( !isset($request->get['route']) || $request->get['route'] !== 'seomatic/update' ) ) ) ){ 

		//Load the Admin Settings File ( $directory is set before front.php )
		include_once( $directory . $admin . '/model/setting/setting.php' );

		//Store it
		$admin_setting_setting = new ModelSettingSetting( $registry );

		//Create the Security Installation Key
		$key = substr(str_shuffle(MD5(microtime())), 0, 10);

		//Save it
		$admin_setting_setting->editSetting( 'seomatic_update' , array( 'seomatic_update' => $key ) );

		//Save the Request
		$SEOMatic->cache->store( 'update' , null , true , '1 hour' );

		//Session is a backup incase the cache 
		$SEOMatic->session->updated = strtotime('1 hour');

		//Ping for Updates
		$SEOMatic->cURL->get( ( !defined('HTTP_CATALOG') ? HTTP_SERVER : HTTP_CATALOG ) . '?route=seomatic/update&key=' . $key );

	}








	/**
	*
	*	Only run below if Admin
	*
	**/


	//Return if not admin ($folder defined in seomatic.xml)
	if( $folder && count( $folder ) > 0  || !$SEOMatic->installed ) return;











	/**
	*
	*	SEOMatic Admin Initialization
	*
	**/




	//Load Admin Requirements
	$registry->get('load')->language('seomatic/header');
	$registry->get('load')->model('setting/store');


	// Load SEOMatic Models
	$registry->get('load')->model('seomatic/account');
	$registry->get('load')->model('seomatic/pagination');









	/**
	*
	*	Validate User
	*
	**/

	//Fix 1.4.x Token
	if( $SEOMatic->opencart['identifiers'][0] == 1 && $SEOMatic->opencart['identifiers'][1] == 4 ){
		$session->data['token'] = empty( $session->data['token'] ) ? '' : $session->data['token'] ;
	}


	//If not logged in, just return
	if( empty($session->data['user_id']) || !isset($_GET['route']) ){
		return true;
	}








	/**
	*
	*	Check SEOMatic Account
	*
	**/




	//If no Account Exists, Show Warning
	if( !$user['email'] ){

		//Account Doesnt Exist, Show Error
		$SEOMatic->error = vsprintf($language->get('account_warning'),array(
			$url->link('seomatic/register', 'token=' . $session->data['token'], 'SSL'),
			$url->link('seomatic/account', 'token=' . $session->data['token'], 'SSL')
		));
	
		//Set Connceted to False
		$SEOMatic->connected = false;

		//Exit / Return
		return;

	}










	/**
	*
	*	SEOMatic Login
	*
	**/

	//Batch all the Queries at Once
	$Batch = $SEOMatic->batch()->add(array(

		//Login
		$SEOMatic->authentication->login => $user

	))->wait()->add(array(

		//Get all the User Accounts
		$SEOMatic->accounts->all => array('limit' => 1)

	))->add(array(

		//Get the User Info
		$SEOMatic->user->info => array()

	));

	if( $user['account'] ){

		$Batch->add(array(

			//Get the Account
			$SEOMatic->accounts->get => array('accountid' => $user['account'])

		));

	}

	//Send the Batch
	$Batch = $Batch->send();











	/**
	*
	*	SEOMatic Login Validations & Connection
	*
	**/




	//Server Down?
	if( isset( $Batch['result'] ) ){

		//Authentication Failed
		$SEOMatic->error = vsprintf($language->get('authentication_failed'),array(
			$Batch['error']
		));

		//Exit / Return
		return;

	}




	//Try logging on
	if( ( $authentication = $Batch[0] ) && !$authentication['result'] ){

		//Authentication Failed
		$SEOMatic->error = vsprintf($language->get('authentication_failed'),array(
			$authentication['error']
		));

		//Exit / Return
		return;

	}

	//Set SEOMatic Connected
	$SEOMatic->connected = true;









	/**
	*
	*	Get the User
	*
	**/
	$user = array_merge( $user , $Batch[2]['user'] );








	/**
	*
	*	Ensure we're running the correct platform verison
	*
	**/

	//The Metadata we should be saving
	$metadata = array(
		'platform' 	=> $SEOMatic->platform,
		'version' 	=> VERSION,
		'seomatic' 	=> $SEOMatic->version
	);

	//Check if the Metadata is the same
	if( $user['meta'] != $metadata ){

		//Update the Metadta
		$SEOMatic->user->update(array(
			'meta' 	=> $metadata
		));

	}





















	/**
	*
	*	SEOMatic Account Validation
	*
	**/


	//Ensure user has an account
	if( !$user['account'] ){

		//Has Accounts
		if( ( $accounts = $Batch[1] ) && $Batch[1]['result'] && $accounts['total'] > 0 ){

			//Show Has Accounts
			$SEOMatic->warning = vsprintf($language->get('account_unselected'),array(
				$url->link('seomatic/account', 'token=' . $session->data['token'] . '#tab-account', 'SSL'),
				$url->link('seomatic/account', 'token=' . $session->data['token'] . '#tab-account', 'SSL')
			));
	
		}else{

			//Show No Accounts
			$SEOMatic->warning = vsprintf($language->get('no_accounts'),array(
				$url->link('seomatic/account', 'token=' . $session->data['token'] . '#tab-account', 'SSL'),
				$url->link('seomatic/account/create', 'token=' . $session->data['token'], 'SSL')
			));

		}

		//Exit / Return
		return;

	}

	//Set the SEOMatic Account
	$SEOMatic->accountid = $user['account'] ;
	$SEOMatic->expired 	 = empty( $Batch[3]['account']['subscription'] ) || ( $Batch[3]['account']['subscription'] == 'trial' && time() > strtotime($Batch[3]['account']['trial']) ) ;














	/**
	*
	*	SEOMatic User Verification Check
	*
	**/


	//Ensure user email has been verified
	if( !$user['verified'] ){

		//Resend the Verification if Passed
		if( isset($request->get['verification_resend']) && $SEOMatic->user->resendVerification() && $SEOMatic->getResult() ){

			//Resent the Verification
			$SEOMatic->success = vsprintf($language->get('email_verification_sent'),array(
				$user['email']
			));

		}else{

			//Preset Data
			$data = array();

			//Get the _GET data
			foreach( $request->get as $key=>$val ){
				
				//Don't add Route or Email_Verification_Sent
				if( !in_array($key,array('route','verification_resend')) ){

					//Add the Get Var
					$data[] = urlencode($key).'='.urlencode($val);

				}

			}

			//Show the Warning
			$SEOMatic->warning = vsprintf($language->get('email_unverified'),array(
				$url->link( $request->get['route'] , implode('&',$data) . '&verification_resend=true', 'SSL'),
			));

		}

	}






?>