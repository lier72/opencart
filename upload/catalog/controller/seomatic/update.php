<?php
class ControllerSeomaticUpdate extends Controller {







	/**
	*
	*	update
	* 		- Runs the SEOMatic Update
	*
	* 	Params:
	* 		n/a
	*
	**/
	public function index(){

		//Load the settings
		$this->load->model('setting/setting');

		//Get the Setting
		$install = $this->model_setting_setting->getSetting('seomatic_update');

		//If we don't have a Key setup
		if( !$install || !$this->model_setting_setting->getSetting('seomatic_autoupdate') ){

			//Output and Return
			$this->response->setOutput( 'Invalid Security Key' );
			return;

		}

		//Check the Keys
		if( isset( $this->request->get['key'] ) && $this->request->get['key'] == $install['seomatic_update'] ){

			//Fork the Process if Available
			if( function_exists('pcntl_fork') ){
			
				//Fork the File
				$pid = pcntl_fork();

				if( $pid ){

					//Run the Update on the Forked File
					$this->update(true);

				}

				//Return Output on Parent
				$this->response->setOutput( 'SEOMatic Update ' . ( $pid == -1 ? 'Failed' : 'Running ' ) );		
			
			}else{

				//Fork the Process to Exec if possible
				if( function_exists('exec') ){

					//Run the Exec Update
					exec( PHP_BINDIR . '/php -r \'chdir("' . str_replace( 'catalog/' , '' , DIR_APPLICATION ) . '"); $_GET["route"] = "seomatic/update/update"; include("index.php");\' > /dev/null &' , $a , $b );


					//Return Output on Parent
					$this->response->setOutput( 'SEOMatic Update Running' );		

				}else{

					//Just run the Update
					$this->update(true);

				}

			}

		}else{

			$this->response->setOutput( 'Invalid Security Key' );

		}
	}







	/**
	*
	*	recursive
	* 		- Load all the Files Recursively
	*
	* 	Params:
	*		$run: 			(Bool) Can the Function Run?
	*
	**/
	public function update( $run = false ){
		if( $run || php_sapi_name() === 'cli' ){

			//Enable set_time_limit if possible
			if( !ini_get('safe_mode') ) set_time_limit(0);

			/**
			*
			*	We Validate from two domains on two separate servers to ensure the packages are correct and no unexpected hacks are pushed to the users
			* 		- Significant security measures have been implemented to prevent pushing unwanted data.
			*
			**/

			//Get the Package
			$package 		= json_decode( $this->SEOMatic->cURL->get( 'https://seomatic.co/downloads/opencart/package.json' ) ) ;

			//Validation
			$verification 	= json_decode( $this->SEOMatic->cURL->get( 'https://wishmedia.ca/seomatic/opencart/package.json' ) ) ;

			//Check if the Package Version is Newer
			if( $package->version > $this->SEOMatic->version && $package->version == $verification->version && $package->uuid == $verification->uuid && class_exists('ZipArchive') ){

				//Prepare the Installation Directory
				$directory 	= DIR_SYSTEM . 'seomatic/' ;

				//The Zip File
				$zipfile 	= $directory . '/seomatic.zip' ;

				//If the File exists, We're already installing - just exit
				if(file_exists( $zipfile )){

					//Output the Response
					$this->response->setOutput( 'SEOMatic Already Upgrading' );

					//End the Process
					return;

				}

				//Create the Update Directory
				if( !is_dir( $directory ) ) mkdir( $directory , 0755 , true );

				//Create the File
				$fh 	= fopen( $zipfile , 'w+' );

				//Setup cURL;
				$this->SEOMatic->cURL->file = $fh;

				//Don't Return any Headers
				$this->SEOMatic->cURL->header = false;

				//Set Timeout
				$this->SEOMatic->cURL->timeout = 50;

				//Get the File
				$this->SEOMatic->cURL->get( 'https://seomatic.co/downloads/opencart/seomatic.zip' );

				//Unzip the File
				$zip 	= new ZipArchive;

				if( $zip->open( $zipfile ) === true ){

					$zip->extractTo( $directory );
					$zip->close();

					//Get the Root Directory
					$root 	= $directory . 'seomatic/upload opencart ' . $this->SEOMatic->opencart['identifiers'][0] . '.x/';

					//Load the Files
					$recursive 	= $this->recursive( $root );

					//Get the Root Folder
					$folder = str_replace( 'catalog/' , '' , DIR_APPLICATION );

					//Get the Admin Folder
					$admin 	= glob( $folder . '/*/config.php' ) ;

					//Ensure the Config file is the correct admin subfolder
					if( count( $admin ) > 0 ) while( strpos( file_get_contents( str_replace( 'config.php' , 'index.php' , $admin[0] ) ) , 'Location: ../install/index.php' ) === false ) array_shift( $admin );

					//Get the Basename
					$admin 	= basename( dirname( $admin[0] ) ) ;

					//Remove the Directory
					foreach( $recursive['files'] as $key => $file ){ 

						//Get the Existing File
						$existing 	= $folder . str_replace( array( $root , 'admin' ), array( '' , $admin ) , $file );

						//Remove the Existing File
						if( file_exists( $existing ) ) unlink( $existing );
						
						//Copy the New File Over
						copy( $file , $existing );

					}

					//Get all the folders and files
					$recursive = $this->recursive( $directory . 'seomatic/' );

					//Remove the Update Files
					foreach( $recursive['files'] as $file ) if( file_exists( $file ) ) unlink( $file );
					foreach( $recursive['folders'] as $folder ) if( is_dir( $folder ) ) rmdir( $folder );

					//Remove the Zipfile
					unlink( $zipfile );

					//Remove the Directory
					rmdir( $directory );

					//Complete the Upgrade
					$this->load->model('seomatic/upgrade');

					//Get Older Methods
					$methods = get_class_methods( $this->model_seomatic_upgrade );

					//Get the Current Version
					$version = str_replace( '.' , '' , $this->SEOMatic->version );

					//Loop through each Upgrade Function
					foreach( $methods as $method ){

						//Check if the Function is newer than the current version
						if( (int)ltrim($method,'_') > $version ){

							//Run the Upgrade Function
							call_user_func( array( $this->model_seomatic_upgrade , $method ) , null );

						}

					}

				}

				$this->response->setOutput( 'SEOMatic Updated to Version: ' . $package->version );	

			}else{

				$this->response->setOutput( 'SEOMatic is already up to date!' );	

			}

		}else{

			$this->response->setOutput( 'Invalid Permissions' );

		}
	}








	/**
	*
	*	recursive
	* 		- Load all the Files Recursively
	*
	* 	Params:
	*		$folder: 	(String) The Folder to Search Within
	*
	**/
	private function recursive($folder){

		//Prepare the Arrays
		$files 		= array();
		$folders 	= array();

		//Get the Files in the Folder
		$list = glob( $folder . '*' );

		//Loop through each Folder
		foreach( $list as $file ){
			//If its a Directory, Find the Files
			if( $file[0] != '.' && is_dir( $file ) ){
			
				//Get the Files / Folders
				$recursive = $this->recursive( $file . '/' );

				//Merge the Files
				$files = array_merge( $files , $recursive['files'] );
			
				//Merge the Folders
				$folders = array_merge( $folders , $recursive['folders'] );

				//Add the Folder
				$folders[] = $file;

			}else{

				//Add the File
				$files[] = $file;

			}
		}

		//Add the Parent Folder
		$folders[] = $folder;

		return array( 'files' => $files , 'folders' => $folders );

	}














	/**
	*
	*	autoupdate
	* 		- Toggles the AutoUpdate Function
	*
	* 	Params:
	* 		$_POST['data']: 		(Bool) The AutoUpdate Toggled Status (1: Enabled, 0: Disabled)
	*
	**/
	public function autoupdate(){

		if( $this->validate() ){

			$this->load->model('setting/setting');

			$this->model_setting_setting->editSettingValue('seomatic','seomatic_autoupdate', !(int)$this->request->post['data'] );

			echo (int)!$this->request->post['data'];

		}

	}





}
?>