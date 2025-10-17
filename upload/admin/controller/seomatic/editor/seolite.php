<?php

  	//Get the Page Type
  	$route 		= explode('/',$this->request->get['route']);
  	$type 		= $route[1];


  	//Get the Link
    $link =  HTTP_SERVER;
    if(isset($this->request->get[$type.'_id'])){

  
        if(isset($this->url)){

            //1.5.x
            $link = $this->url->link('product/'.$type, '&' . ($type=='category'?'path=':'product_id=') . $this->request->get[$type.'_id'] );

        }else{

            //1.4.x
            $link = HTTP_SERVER . 'index.php?route=product/'.$type . '&' . ($type=='category'?'path=':'product_id=') . $this->request->get[$type.'_id'] ;

        }

      
    }


    //Get the Keyword if one exists
    $keyword = !empty( $this->request->post ) ? $this->request->post[ 'seolite_keyword' ] : $this->config->get('seolite_keyword') ;

    //Set the Keyword
    $data['seolite_keyword'] = !empty($keyword) && !empty($keyword[ $this->request->get['route'] ]) ? $keyword[ $this->request->get['route'] ] : '' ;


    //Add the Extra Fields
  	$data['seolite_sefurls'] 	      = $this->config->get('config_seo_url');
    $data['seolite_preset']         = str_replace(dirname($_SERVER['PHP_SELF']),'',$link);
    $data['seolite_path']           = str_replace(dirname($_SERVER['PHP_SELF']),'',HTTP_SERVER);


?>