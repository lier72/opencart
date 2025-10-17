<?php
class ModelSeomaticUpgrade extends Model {

	//The Admin Model Setting Setting Class
	private $model_setting_setting = null;



	/**
	*
	*	_construct
	* 		- Load the Admin Side Model Settings so we can Edit the settings
	*
	* 	Params:
	* 		$registry: 		(Object) The Registry Object
	*
	**/
	public function __construct($registry){
		
		//Set the Registry
		$this->registry = $registry;

		//Get the Directory
		$directory 	= str_replace( array( 'catalog/' ) , '' , DIR_APPLICATION ) ;

		//Get the Admin Folder
		$folder 	= glob( $directory . '/*/config.php' );

		//Ensure the Config file is the correct admin subfolder
		if( count( $folder ) > 0 ) while( strpos( file_get_contents( str_replace( 'config.php' , 'index.php' , $folder[0] ) ) , 'Location: ../install/index.php' ) === false ) array_shift( $folder );

		//Get the Admin Folder
		$admin 		= basename( dirname( $folder[0] ) ) ;

		//Load the Admin Settings File ( $directory is set before front.php )
		include_once( $directory . $admin . '/model/setting/setting.php' );

		//Store it
		$this->model_setting_setting = new ModelSettingSetting( $registry );



	}



	/**
	*
	*	SEOMatic Upgrade Functions
	*
	**/
	public function _123(){

		//Remove Unused Files
		unlink( DIR_APPLICATION . 'controller/seomatic/referral.php' );
		unlink( DIR_APPLICATION . 'view/theme/default/template/seomatic/referral.tpl' );
		rmdir( DIR_APPLICATION . 'view/theme/default/template/seomatic' );

		//Get SEOMatic Settings
		$seomatic = $this->model_setting_setting->getSetting( 'seomatic' );

		//Remove the old Referral Option
		unset( $seomatic['seomatic_referral'] );

		//Add the Dashboard Option
		$seomatic['seomatic_dashboard'] = 1;

		//Edit the Settings
		$this->model_setting_setting->editSetting( 'seomatic' , $seomatic );

	}



}
?>