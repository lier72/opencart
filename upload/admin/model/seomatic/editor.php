<?php

  class ModelSeomaticEditor extends Model {


    /**
    *
    *   manage
    *       - Insert or Update the Keywords when a Product is Created or Updated
    *       
    *       Params:
    *           $route:         (String) The Page Type (product, information, category)
    *           $id:            (INT) The Page Type ID ( *product_id , *information_id , *category_id )
    *
    **/
    public function manage($route , $id){
        if( !empty($this->request->post['seomatic']['keyword']) ){

            //Get the Account ID
            $accountid  = $this->config->get('seomatic_account');

            //Prepare the Insert Array
            $insert     = array();

            //Prepare the Update Array
            $update     = array();


            //Go through each Keyword
            for( $i=0; $i < count($this->request->post['seomatic']['keyword']); $i++ ){

                //Check if the Keyword has been created
                if(!is_numeric( $this->request->post['seomatic']['keywordid'][ $i ] )){

                    //Send the Query
                    $insert[] = array(
                        'link'      => array(
                            'id'        => $id,
                            'type'      => $route.'id'
                        ),
                        'countryid' => $this->request->post['seomatic']['countryid'][ $i ],
                        'keyword'   => $this->request->post['seomatic']['keyword'][ $i ],
                        'status'    => $this->request->post['seomatic']['status'][ $i ]
                    );

                }else{


                    //Send the Query
                    $update[] = array(
                        'link'      => array(
                            'id'        => $id,
                            'type'      => $route.'id'
                        ),
                        'keywordid' => $this->request->post['seomatic']['keywordid'][ $i ],
                        'countryid' => $this->request->post['seomatic']['countryid'][ $i ],
                        'keyword'   => $this->request->post['seomatic']['keyword'][ $i ],
                        'status'    => $this->request->post['seomatic']['status'][ $i ]
                    );


                }

            }


            //If we have Keywords to Create
            if(!empty( $insert )){

                //Send the Insert
                $this->SEOMatic->account( $accountid )->keywords->add( $insert );

            }


            //If we have Keywords to Update
            if(!empty( $update )){

                //Send the Update
                $this->SEOMatic->account( $accountid )->keywords->update( $update );

            }

        }

    }











    /**
    *
    *   delete
    *       - Delete the Keywords when a Product is Deleted
    *       
    *       Params:
    *           $route:         (String) The Page Type (product, information, category)
    *           $id:            (INT) The Page Type ID ( *product_id , *information_id , *category_id )
    *
    **/
    public function delete($route){


        //Get the Account ID
        $accountid  = $this->config->get('seomatic_account');


        //Prepare the Delete Array
        $delete = array();


        //Prepare the List Array
        $list = array();


        //Get all the Products
        foreach( $this->request->post['selected'] as $id ){

            //Add the Query to the List
            $list[] = array(
                'link'  => array(
                    'id'    => $id,
                    'type'  => $route.'id'
                )
            );

        }   

        //Get the Keywords
        $query = $this->SEOMatic->account( $accountid )->keywords( $list );

        //Loop through Each Query
        foreach( $query as $keywords ){

            //If we have Keywords
            if( $keywords['result'] && count($keywords['keywords']) > 0 ){

                //Get the Keywordds
                foreach($keywords['keywords'] as $keyword){

                    //Add the Keyword ID
                    $delete[] = array(
                        'keywordid' => $keyword['keywordid']
                    );
                }

            }

        }
    

        //If we have Delete
        if( count($delete) > 0 ){

            //Send the Delete Command
            $this->SEOMatic->account( $accountid )->keywords->delete( $delete );

        }

    }









}

?>