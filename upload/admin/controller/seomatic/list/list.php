<?php


	//Preset Data
    $list       = array();

    //Preset the Keyword IDs
    $keyworids = array();



    //Get the Account ID
    $accountid = $this->SEOMatic->accountid;


    //Get the Route
    $route = explode('/',$this->request->get['route']);
    $route = $route[1];

    //The Name of the Array (Categories, Products, Informations
    $name  = ( $route[ strlen($route) - 1 ] == 'y' ? substr($route,0,-1) . 'ies' : $route . 's' );

    //The Name of the ID
    $id     = $route . '_id' ;

    //Keywords Data
    $keywords = array();


    //Loop through Each Product
    foreach( $data[ $name ] as $item ){

    	//Add the List Item
        $list[] = $item[ $route.'_id' ];

    }


    //Batch the Commands
    $Batch = $this->SEOMatic->batch()->add(array(

        //Add the Keywords
        $this->SEOMatic->account( $accountid )->keywords->get => array(
            'link'      => array(
                'id'        => $list,
               'type'       => $route.'id',
            ),
            'limit'     => $this->config->get('config_admin_limit')
        )

    ))->add(array(

        //Get the Countries
        $this->SEOMatic->countries->get => array()

    ))->add(array(

        //Get the Domains
        $this->SEOMatic->account( $accountid )->domains->get => array()

    ))->send();


    //Save the Variables
    $searches   = $Batch[0];
    $countries  = $Batch[1];
    $domains    = $Batch[2];


    //Loop through the Items
    foreach( $data[ $name ] as $key => $item ){

    	//Ensure Keywords were Returned
    	if( $searches['result'] > 0 && count( $searches['keywords'] ) > 0 ){

			//Loop through the Results
	    	foreach( $searches['keywords'] as $i=>$search ){

	    		//if its the Same ID as the Page Item ID
	    		if( $search['linkid'] == $item[ $id ] ){

                    //If the Array Doesn't Exist
                    if(empty( $data[ $name ][ $key ]['keywords'] )){

                        //Create the Keyword Array
                        $data[ $name ][ $key ]['keywords'] = array();
                    
                    }

                    //Loop through the Countries
                    foreach( $countries['countries'] as $country ){
                        if( $country['countryid'] == $search['countryid'] ){

                            //Append the Country to the Keyword
                            $search['country'] = $country ;

                        }
                    }

                    $search['date']     = date('M d, Y h:i A',strtotime( $search['updated'] ));

                    //Add the Domain
                    $search['domain']   = $domains['domains'][0];

	    			//Save the Keyword
	    			$keywords[ $search['keywordid'] ] = $search;

	    			//Add the Keyword
	    			$data[ $name ][ $key ]['keywords'][] = $search;

	    		}

    		}

    	}

    }

    //Save the Route
    $data['route'] = $route;

    //Save the Domains
    $data['domains']  = $domains['domains'];

    //Save the Keyword
    $data['keywords'] = $keywords;


?>