<?php

	//Get the Page Type	
	$route 		= explode('/',$this->request->get['route']);
	$type 		= $route[1];


	//Set the Type
	$data['type'] 	 = $type;

	//Save the Job Type
	$data['job'] 		= explode('/',$this->request->get['route']);
	$data['job'] 		= $data['job'][2];


	//Save the Link ID
	$data['linkid'] 	= isset($this->request->get[ $type . '_id' ]) ? $this->request->get[ $type . '_id' ] : null ;

	//Save the Link Type
	$data['linktype'] = $type.'id';


	//Get the Account ID
	$data['accountid'] = $accountid = $this->config->get('seomatic_account');



	$Batch = $this->SEOMatic->batch()->add(array(

		//Get the Countries
		$this->SEOMatic->countries->get => array()

	))->add(array(

		//Get the Keywords
		$this->SEOMatic->account( $accountid )->keywords->get => array(
			'link' 		=> array(
				'id' 		=> !empty( $data['linkid'] ) ? $data['linkid'] : 0,
				'type' 		=> $data['linktype']
			),
			'limit' 	=> 100
		)

	))->add(array(

		//Get the Competitors
		$this->SEOMatic->account( $accountid )->competitors->get => array()

	))->add(array(

		//Get the Domains
		$this->SEOMatic->account( $accountid )->domains->get => array()

	))->send();


	//Get the Countries
	$data['countries'] 		= $Batch[0]['countries'];
	$data['keywords']		= $Batch[1];
	$data['competitors'] 	= $competitors 	= $Batch[2]['competitors'];
	$data['domains'] 		= $domains 		= $Batch[3]['domains'];
	$data['competitorids'] 	= array();
	$data['domainids'] 		= array();


	//Add the Competitors
	foreach($competitors as $competitor){
		$data['competitorids'][] = $competitor['competitorid'];
	}


	//Add the Domains
	foreach($domains as $domain){
		$data['domainids'][] = $domain['domainid'];
	}


	//Submitted with Errors, Load the Keywords from POST
	if(!empty( $this->request->post['seomatic']['keyword'] )){

		//Get the Size of the Post
		$size = count( $this->request->post['seomatic']['keyword']);

		//Prepare the Keyword Array
		$keywords = array();

		//Loop through each Posted Keyword
		for( $i=0; $i<$size; $i++ ){

			$keywords[] = array(
				'keywordid' 	=> $this->request->post['seomatic']['keywordid'][ $i ],
				'countryid' 	=> $this->request->post['seomatic']['countryid'][ $i ],
				'keyword' 		=> $this->request->post['seomatic']['keyword'][ $i ],
				'status' 		=> $this->request->post['seomatic']['status'][ $i ],
				'updated' 		=> 'Never',
				'google' 		=> '-',
				'yahoo' 		=> '-',
				'bing' 			=> '-'
			);

		}

		//Set the Keywords
		$data['keywords'] = array(
			'keywords' 	=> $keywords,
			'total' 	=> count($keywords)
		);

	}

	//Preset Timestamps
	$data['startdate'] 	= strtotime('last month') ;
	$data['enddate'] 	= time() ;

	if( $data['keywords']['total'] > 0 ){

		//Get the Keywords
		$keyword = $data['keywords']['keywords'][0] ;

		//Set the Added Date
		$added 				= strtotime( $keyword['added'] );
		$data['startdate'] 	= $added >= $data['startdate'] ? $added : $data['startdate'] ;

		//Set the Updated Date
		if( $keyword['updated'] != '0000-00-00 00:00:00' ){

			//Set the End Date
			$data['enddate']	= strtotime( $keyword['updated'] );

		}

	}

?>