<?php

  class ModelSeomaticAnalysis extends Model {



      //Root Folder
      private $root       = '';









      //Title
      private $title        = array(
        'size'  => array(
            'min'   => 40, 
            'max'   => 70, 
            'limit' => 0
        ),
        'text'  => array(
            'missing'   => 'You\'re missing a page title! This is an important ranking factor!',
            'correct'   => 'Your page title is greater than 40 characters and less than 60.',
            'short'     => 'The page title contains less than 40 characters. We recommend increasing the keywords on the page or adding compelling call-to-action copy.',
            'long'      => 'The page title contains more than the recommended 60 characters, some of your words will not be visible in your listing.',
            'missing'   => 'The keyword does not appear in the page title',
            'beginning' => 'The keyword is at the beginning of the phrase which is thought to improve rankings!',
            'end'       => 'The keyword does not appear at the beginning, try moving it to the beginning'
        )
      );











      //Meta Description
      private $description  = array(
        'size'  => array(
            'min' => 120, 
            'max' => 155
        ),
        'text'  => array(
            'correct'   => 'The meta description is the correct length! But is the content more compeling than your competitions?',
            'short'     => 'The meta description is under 120 characters, you may want to add some more.',
            'long'      => 'The meta description has more than the recommended 155 characters, try reducing the characters so it will be visible in all of your listings',
            'none'      => 'The meta description was not specified and will be displayed from your body content instead',
            'exists'    => 'The meta description contains the keyword / phrase',
            'missing'   => 'The meta description has been specified, but does not contain the keyword / phrase'
        )
      );













      //Body Contnet
      private $content      = array(
        'size'   => array(
            'good'  => 300, 
            'ok'    => 250, 
            'poor'  => 200, 
            'limit' => 100
        ),
        'text'   => array(
            'good'    => 'The body contains %d words, more than the 300 word recommendation. Perfect!',
            'ok'      => 'The body contains %d words, a bit less than the 300 word recommendation.',
            'poor'    => 'The body contains %d words, this is below the 300 word recommendation.',
            'bad'     => 'The body contains %d words. We recommend you have a minimum of 300 words.',
            'none'    => 'The first paragraph <small>( %s )</small> doesn\'t contain the keyword. We recommend adding it in.',
            'exists'  => 'The keyword appears in the first paragraph of your content.',
            'flesch'  => 'The content scores %s in the <a href="http://en.wikipedia.org/wiki/Flesch-Kincaid_readability_test#Flesch_Reading_Ease" target="_blank">Flesch Reading Ease</a> test, this means that it is %s to read. %s'
        ),
        'headings' => array(
            'none'    => 'No subheading tags appear in the copy.',
            'keyword' => 'The keyword / phrase appears in %s (out of %s) subheadings.',
            'missing' => 'Your keyword does not exist in any subheadings.'
        ),
        'images'  => array(
            'none'    => 'There are no images that appear on this page.',
            'alt'     => 'The images on this page are missing alt tags.',
            'exists'  => 'The images on the page contain alt tags with the target keywords / phrase.',
            'missing' => 'The images do not contain alt tags with the keyword / phase.'
        ),
        'anchor'  => array(
            'links'     => 'There are no outbound links on this page, you might want to consider adding some.',
            'keyword'   => 'You\'re linking to another page that uses the keyword you\'re trying to rank for on this page. This page will not rank because of this.',
            'dofollow'  => 'This page has %s outbound link(s).',
            'nofollow'  => 'This page has %s nofollow outbound link(s).',
            'links'     => 'This page has %s nofollow link(s) and %s outbound link(s).'
        )
      );












      //Keyword
      private $keyword      = array(
        'text'    => array(
            'never'   => 'You\'ve never used this keyword before.',
            'once'    => 'You\'ve used this keyword before.',
            'many'    => 'You\'re using this keyword a lot, why not try some new keywords to rank for?',
            'stop'    => 'This keyword contains one or more stop words, you might want to remove these.'
        ),
        'level'   => array(
            'low'     => 'You have a total of %s keywords. Your keyword density is %s%%. We recommend increasing the amount of keywords you have.',
            'high'    => 'You have a total of %s keywords. Your keyword density is %s%%. This is over the 4.5%% recommendation.',
            'good'    => 'You have a total of %s keywords. Your keyword density is %s%%. Perfect!'
        )
      );













      //URL
      private $url          = array(
        'text'  => array(
            'exists'  => 'The URL contains the keyword / phrase.',
            'none'    => 'The keyword does not appear in the URL. If you modify the URL remember to use URL 301 redirects when necessary.',
            'stop'    => 'The URL contains one or more <a href="http://en.wikipedia.org/wiki/Stop_words">stop words</a>.',
            'alias'   => 'The URL is a bit long, consider shortening it.'
        )  
      );












      private $language     = array();
      private $special      = array( ",", "'", "\"", "?", "’", "“", "”", "|", "/" );
      private $alias        = array( "_", "-" );
      private $words        = array(" a ", " in ", " on ", " for ", " the ", " and " );
      private $stopwords    = array('a','about','above','after','again','against','all','am','an','and','any','are','aren\'t','as','at','be','because','been','before','being','below','between','both','but','by','can\'t','cannot','could','couldn\'t','did','didn\'t','do','does','doesn\'t','doing','don\'t','down','during','each','few','for','from','further','had','hadn\'t','has','hasn\'t','have','haven\'t','having','he','he\'d','he\'ll','he\'s','her','here','here\'s','hers','herself','him','himself','his','how','how\'s','i','i\'d','i\'ll','i\'m','i\'ve','if','in','into','is','isn\'t','it','it\'s','its','itself','let\'s','me','more','most','mustn\'t','my','myself','no','nor','not','of','off','on','once','only','or','other','ought','our','ours ',' ourselves','out','over','own','same','shan\'t','she','she\'d','she\'ll','she\'s','should','shouldn\'t','so','some','such','than','that','that\'s','the','their','theirs','them','themselves','then','there','there\'s','these','they','they\'d','they\'ll','they\'re','they\'ve','this','those','through','to','too','under','until','up','very','was','wasn\'t','we','we\'d','we\'ll','we\'re','we\'ve','were','weren\'t','what','what\'s','when','when\'s','where','where\'s','which','while','who','who\'s','whom','why','why\'s','with','won\'t','would','wouldn\'t','you','you\'d','you\'ll','you\'re','you\'ve','your','yours','yourself','yourselves');
      private $report       = array();
      private $problemWords = array(
        'simile'     => 3,
        'forever'    => 3,
        'shoreline'  => 2
      );










      // These syllables would be counted as two but should be one
      private $subSyllables = array(
        'cial',
        'tia',
        'cius',
        'cious',
        'giu',
        'ion',
        'iou',
        'sia$',
        '[^aeiuoyt]{2,}ed$',
        '.ely$',
        '[cg]h?e[rsd]?$',
        'rved?$',
        '[aeiouy][dt]es?$',
        '[aeiouy][^aeiouydt]e[rsd]?$',
        '^[dr]e[aeiou][^aeiou]+$', // Sorts out deal, deign etc
        '[aeiouy]rse$' // Purse, hearse
      );










      // These syllables would be counted as one but should be two
      private $addSyllables  = array(
        'ia',
        'riet',
        'dien',
        'iu',
        'io',
        'ii',
        '[aeiouym]bl$',
        '[aeiou]{3}',
        '^mc',
        'ism$',
        '([^aeiouy])\1l$',
        '[^l]lien',
        '^coa[dglx].',
        '[^gq]ua[^auieo]',
        'dnt$',
        'uity$',
        'ie(r|st)$'
      );









      // Single syllable prefixes and suffixes
      private $prefixSuffix   = array(
        '/^un/',
        '/^fore/',
        '/ly$/',
        '/less$/',
        '/ful$/',
        '/ers?$/',
        '/ings?$/'
      );
















      /**
      *
      *     __set
      *         - Set the Value of a Field
      *
      *         Params:
      *             - language:         (String) The Page Language
      *             - title:            (String) The Page Title
      *             - content:          (String) The Page Content
      *             - description:      (String) The Meta Description
      *             - url:              (String) The Page URL
      *             - root:             (String) The Page Root
      *
      *
      **/
      public function __set($key,$val){
        if(isset($this->{$key})) $this->{$key}['value'] = $val;
      }


























    /**
    *
    *     analyze
    *         - Start the Page Analyzer
    *
    *     Params:
    *         - task              (Object) The Current Task Object
    *             - language:         (String) The Page Language
    *             - title:            (String) The Page Title
    *             - content:          (String) The Page Content
    *             - description:      (String) The Meta Description
    *             - url:              (String) The Page URL
    *             - root:             (String) The Page Root
    *             - language:         (String) The Language to use
    *
    **/
    public function analyze($task=array()){


        //Prepare Data
        $task = array_merge(array(
            'url'           => '',
            'title'         => '',
            'description'   => '',
            'keyword'       => '',
            'content'       => '',
            'paragraph'     => '',
            'headings'      => '',
            'images'        => '',
            'anchors'       => '',
            'anchors_stats' => '',
            'language'      => 'en'
        ),$task);

    

        //Ensure Content is not Empty
        if(empty( $task['content'] )){
            $task['content'] = '<html><body></body></html>';
        }



        //Keyword Required
        if(empty( $task['keyword'] )){
            return array(
                'result'    => 0,
                'error'     => 'No keyword was set for the page. If you do not set a keyword, no report can be generated',
                'code'      => 'keyword-required'
            );
        }



        //DOMDocument Required
        if(!class_exists('DOMDocument')){
            return array(
                'result'    => 0,
                'error'     => 'DOMDocument was not found, ensure your version of PHP has DOMDocument.',
                'code'      => 'domdocument-required'
            );
        }


        //Use Internal Errors

        //Setup DOMDocument
        $DOM                        = new DOMDocument;
        $DOM->strictErrorChecking   = false;
        $DOM->preserveWhiteSpace    = false;
        @$DOM->loadHTML( $task['content'] );
        $xpath = new DOMXPath( $DOM );
      


        //Prepare the Data
        $task['url']            = trim( $task['url'] );
        $task['title']          = trim( $task['title'] );
        $task['description']    = trim( $task['description'] );
        $task['keyword']        = $this->cleanUpString( trim( $task['keyword'] ) );
        $task['paragraph']      = $this->getFirstParagraph( $task['content'] );
        $task['headings']       = $this->getHeading( $task['content'] );
        $task['images']         = $this->getImagesData( $task['content'] );
        $task['anchors']        = $this->getAnchorData( $xpath );
        $task['anchors_stats']  = $this->getTotalAnchors( $xpath );
      

        //Check if the keyword has been used already in previous pages/posts.
        $this->checkKeywordUsed( $task );
      
        //Keyword:
        $this->levelKeyword( $task );
      
        //Title: 
        $this->levelTitle( $task );
      
        //Description:
        $this->levelDescription( $task );
       
        //Body
        $this->levelBody( $task );
      
        //URL
        $this->levelURL( $task );
      
        //Headings
        $this->levelHeading( $task );
      
        //Images
        //$this->levelImages( $task );
      
        //Anchors
        $this->levelAnchor( $task );
      
        //Return the Report
        return array(
            'result'    => 1,
            'report'    => $this->build()
        );
        
    }




























    /**
    *
    *   cleanUpString
    *       - Clean up the str with special char replace by space
    *       - Second arg for replace optional chars and words replace by space
    *
    *   Params:
    *       - str       (String) The String to Clean Up
    *       - replace   (Bool) Replace Optional Characters / Words by Spaces
    *
    **/
    private function cleanUpString($str,$replace=false){      

        $str = strtolower($str);
        $str = str_replace($this->special,' ',$str);
        $str = preg_replace('/\s+/',' ', $str );
          

        if($replace){

            $str = str_replace($this->words, ' ', $str );
            $str = str_replace($this->alias, '', $str );

        }else{

            $str = str_replace($this->alias, ' ', $str );

        }

        return trim( preg_replace('/\s+/',' ', $str ) ); //Remove Multiple space with single space
        
        return $str;
    }




























    /**
    *
    *   checkKeywordUsed
    *       - Check the keyword already used in page/post conents
    *
    *     Params:
    *         n/a
    *
    **/
    private function checkKeywordUsed($keyword){
      /*

        $user     = 0; //if keyword not found into post/page contents 
        $userOnce = 1; //if keyword found only once into post/page contents
        $userOnce = 2; //if keyword found morethen once into post/page contents
          
        $rows = $this->db->select('
            SELECT
                COUNT(keyword) AS total
            FROM seomatic_keyword
            WHERE
                customer_id="'.(int)$this->request->get['customer_id'].'"
                AND
                keyword="'.$keyword.'"
          ');

        if(count($rows) == 0){

            $this->addLevel( 'pages' , $this->keyword['text']['never'] , 9 );
        
        }elseif($rows[0]['total'] == 1){
        
            $this->addLevel( 'pages' , $this->keyword['text']['used'] , 1 );
        
        }else{
        
            $this->addLevel( 'pages' , $this->keyword['text']['many'] , 1 );
        
        }

        */
    }




























    /**
    *
    *   levelKeyword
    *       - Check the Keyword for Stopwords
    *
    *   Params:
    *       - task:       (Object) The Current Task Object
    *             - language:         (String) The Page Language
    *             - title:            (String) The Page Title
    *             - content:          (String) The Page Content
    *             - description:      (String) The Meta Description
    *             - url:              (String) The Page URL
    *             - root:             (String) The Page Root
    *
    *
    **/
    private function levelKeyword($task){

        //Ensure the Keyword doesn't contain Stopwords
        if($this->checkStopWords( $task['keyword'] )){
            $this->addLevel( 'keyword' , $this->keyword['text']['stop'] , 5 );
        }

    }



























    /**
    *
    *   checkStopWords
    *       - Find any stop words existing in the term
    *
    *   Params:
    *       - string:       (String) The String to Check
    *
    *
    *
    **/
    private function checkStopWords($string){
        $words = explode(' ',strtolower($string) );
        foreach($words as $word){

            //If the Word is a Stop Word, Return the Word
            if(in_array(trim($word),$this->stopwords)) return $word;

        }

        //No Stopwords Found
        return false;

    }


























    /**
    *
    *   levelTitle
    *       - Find any stop words existing in the term
    *
    *   Params:
    *       - task:       (Object) The Current Task Object
    *             - language:         (String) The Page Language
    *             - title:            (String) The Page Title
    *             - content:          (String) The Page Content
    *             - description:      (String) The Meta Description
    *             - url:              (String) The Page URL
    *             - root:             (String) The Page Root
    *
    *
    **/
    private function levelTitle($task){      
        if(empty($task)){
      
            //The Title was Empty
            $this->addLevel(  'title' , $this->title['text']['missing'] , 1 );
      
        }else{
        

            //Get the Length of the String
            $length = strlen($task['title']);
        

            //The title is too Short
            if($length < $this->title['size']['min']){
            
                $this->addLevel(  'title' , $this->title['text']['short'] , 6 );
            

            //The Title is too long
            }else
            if($length > $this->title['size']['max']){
        
                $this->addLevel(  'title' , $this->title['text']['long'] , 6 );
            

            //The Title is a good length
            }else{

                $this->addLevel(  'title' , $this->title['text']['correct'] , 9 );
            
            }


            //Find the First Position of a Keyword
            $pos = stripos( $this->cleanUpString( $task['title'] ) , $task['keyword'] );         

            //Keyword is Missing
            if($pos === false){
            
                $this->addLevel(  'title' , $this->title['text']['missing'] , 2 );
            


            //The Keyword is in a Good Position
            }else
            if($pos <= $this->title['size']['limit']){
            
                $this->addLevel(  'title' , $this->title['text']['beginning'] , 9 );



            //The Keyword is too far from the Beginning            
            }else{
            
                $this->addLevel( 'title' , $this->title['text']['end'] , 6 );
            
            }

        }
    }






















    /**
    *
    *   levelDescription
    *       - Validate the Meta Description
    *
    *   Params:
    *       - task:       (Object) The Current Task Object
    *             - language:         (String) The Page Language
    *             - title:            (String) The Page Title
    *             - content:          (String) The Page Content
    *             - description:      (String) The Meta Description
    *             - url:              (String) The Page URL
    *             - root:             (String) The Page Root
    *
    *
    **/
    private function levelDescription($task){
        if(empty($task['description'])){

            //The Meta Description was Empty
            $this->addLevel( 'description' , $this->description['text']['none'] , 9 ); 

        }else{


            //Get the Length of the Meta Description        
            $length = strlen($task['description']);
        

            // The Meta Description is too short
            if($length < $this->description['size']['min']){

                $this->addLevel( 'description' , $this->description['text']['short'] , 6 );


            //The Meta Description is too long
            }else
            if($length > $this->description['size']['max']){

                $this->addLevel( 'description' , $this->description['text']['long'] , 6 );


            //The Meta Description is the perfect length
            }else{

                $this->addLevel( 'description' , $this->description['text']['correct'] , 9 );

            }


            //Clean up the Meta Description
            $term1 = $this->cleanUpString( $task['description'] , true );
            $term2 = $this->cleanUpString( $task['description'] , false );
        

            //The Keyword wasn't found in the Meta Description
            if( strpos($term1, $task['keyword']) === false && strpos($term2, $task['keyword']) === false ){
                
                $this->addLevel( 'description' , $this->description['text']['missing'] , 3 );
            


            //All Good!
            }else{
            
                $this->addLevel( 'description' , $this->description['text']['exists'] , 9 );
            
            }
        
        }
    }































    /**
    *
    *   getFirstParagraph
    *       - Get the First Paragraph of Content
    *
    *
    *   Params:
    *       - content:      (String) The Content String to Get the Paragraph From
    **/
    private function getFirstParagraph($content){
        $res = preg_match( '/<p>(.*?)<\/p>/s', $content, $matches );

        if($res){

            //Return the First Match
            return $matches[1];
        
        }else
        if(($pos = strpos($content,'<br')) !== false){

            //Return Content before the First Break Tag
            return substr($content,0,$pos);
        
        }


        //Nothing Found
        return false;
    }





















    /**
    *
    *   countWords
    *       - Count the Words in the content
    *
    *   Params:
    *       - content:      (String) The Content String to Count the Words From
    *
    *
    **/
    private function countWords($content){
        $text = $this->getNonHtmlText( $content );
        return $text == '.' ? 0 : (1 + strlen( utf8_decode( preg_replace( '/[^ ]/', '', $text ) ) ) );
    }






















    /**
    *
    *   getNonHtmlText
    *       - Removed Unwanted Strings from HTML
    *
    *   Params:
    *       - text:         (String) The HTML String
    *
    *
    **/
    private function getNonHtmlText($text){
        $tags = array( 'li', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'dd' );
        foreach($tags as $tag) $text = str_ireplace( '</' . $tag . '>', '.', $text );
      
        //Strip all Unwanted Tags
        $text = strip_tags($text);
        $text = preg_replace( '/[,:;()-]/', ' ', $text );
        $text = preg_replace( '/[\.!?]/', '.', $text );
        $text = trim( $text ) . '.';
        $text = preg_replace( '/[ ]*(\n|\r\n|\r)[ ]*/', ' ', $text );
        $text = preg_replace( '/([\.])[\. ]+/', '$1', $text );
        $text = trim( preg_replace( '/[ ]*([\.])/', '$1 ', $text ) );
        $text = preg_replace( '/[ ]+/', ' ', $text );
        $text = preg_replace_callback( '/\. [^ ]+/', create_function( '$matches', 'return strtolower($matches[0]);' ), $text );

        //Return the Text
        return $text;     
    }




















    /**
    *
    *   levelBody
    *       - Validate the Body 
    *
    *   Params:
    *       - task:       (Object) The Current Task Object
    *             - language:         (String) The Page Language
    *             - title:            (String) The Page Title
    *             - content:          (String) The Page Content
    *             - description:      (String) The Meta Description
    *             - url:              (String) The Page URL
    *             - root:             (String) The Page Root
    *
    **/
    private function levelBody($task){
        $contents = strip_tags(preg_replace( '/(<img([^>]+)?alt="([^"]+)"([^>]+)>)/','$3',$task['content']));
        $total    = $this->countWords($contents);
      

        //Not Enough Words in Body Content
        if($total < $this->content['size']['limit']){
            
            $this->addLevel( 'content' , sprintf($this->content['text']['bad'],$total) , -20 );
        

        //Not Enough Words in Body Content
        }else
        if($total < $this->content['size']['poor']){
            
            $this->addLevel( 'content' , sprintf($this->content['text']['bad'],$total) , -10 );
      

        //Enough Words in Body Content
        }else 
        if($total < $this->content['size']['ok']){
        
            $this->addLevel( 'content' , sprintf($this->content['text']['poor'], $total) , 5 );
        

        //Good Amount of Words in Body Content
        }else 
        if($total < $this->content['size']['good']){
        
            $this->addLevel( 'content' , sprintf($this->content['text']['ok'], $total) , 7 );
      

        //Perfect Amount of Words in Body Content
        }else{
        
            $this->addLevel( 'content' , sprintf($this->content['text']['good'], $total) , 9 );
      
        }


        //Prepare Data
        $body       = $this->cleanUpString( strtolower( $task['content'] ) );
        $keywords   = str_word_count( $task['keyword'] );

      

        //Over 10 Keywords Found
        if( $keywords > 10 ){
        
            $this->addLevel( 'content' , $this->keyword['text']['many'] , 9 );
      
        }else{
            
            // Check Keyword Level
            $level = 0;
        

            //If the Total Words is greater than 100
            if($total > 100){
        
                //Find all the Keywords 
                $keywords = preg_match_all('/'.preg_quote($task['keyword'],'/').'/msiU', $body, $res);
                $words    = $this->countWords($task['keyword']);


                //If there is more than one word in the Keywords
                if($keywords > 0 && $words > 0){
                    
                    $level = number_format((($keywords / ( $total - (($words - 1) * $words))) * 100), 2);
                
                }


                //Less than 1 Keyword
                if($level < 1){
                    
                    $this->addLevel( 'content' , sprintf($this->keyword['level']['low'],$keywords,$level) , 4 );
              

                //Perfect! Over 4.5 Keywords Found
                }else
                if($level > 4.5){
                
                    $this->addLevel( 'content' , sprintf($this->keyword['level']['high'],$keywords,$level) , -50 );
              

                //A Good Amount of Keywords
                }else{
                
                    $this->addLevel( 'content' , sprintf($this->keyword['level']['good'],$keywords,$level) , 9 );
              
                }
            }
        }

      


        //Prepare the Paragraph
        $paragraph = $this->cleanUpString( strtolower(strip_tags( $task['paragraph'] )) );

        //Keyword Doesn't Exist in Paragraph
        if(stripos($paragraph,$task['keyword']) === false){
            
            $shortened = substr( strip_tags( $paragraph ) , 0 , 30 ) . ( strlen( $paragraph ) > 30 ? '...' : '' ) ;

            $this->addLevel( 'content' , sprintf( $this->content['text']['none'] , $shortened ) , 3 );
      

        //Keyword Found
        }else{
            
            $this->addLevel( 'content' , $this->content['text']['exists'] , 9 );
        
        }




        //If the Language is English and has over 100 Words
        if($task['language'] == 'en' && $total > 100 ){
        
            // Flesch Reading Ease check
            $flesch = $this->flesch( $task['content'] );
        

            //Very Easy to Read Rating
            if($flesch >= 90){
                
                $this->addLevel( 'content' , sprintf($this->content['text']['flesch'],$flesch,'very easy','') , 9 );
            

            //Easy to Read Rating
            }else
            if($flesch >= 80){
            
                $this->addLevel( 'content' , sprintf($this->content['text']['flesch'],$flesch,'easy','') , 9 );
            


            //Fairly Easy to Read Rating
            }else 
            if($flesch >= 70){
            
                $this->addLevel( 'content' , sprintf($this->content['text']['flesch'],$flesch,'fairly easy',''), 8 );
            

            //OK to Read Rating
            }else 
            if($flesch >= 60){
            
                $this->addLevel( 'content' , sprintf($this->content['text']['flesch'],$flesch,'OK',''), 7 );
            

            //Fairly Difficult to Read Rating
            }else 
            if($flesch >= 50){
                
                $this->addLevel( 'content' , sprintf($this->content['text']['flesch'],$flesch,'fairly difficult','Shorter sentences may improve readability'), 6 );
            

            //Difficult to Read Rating
            }else 
            if($flesch >= 30){
            
                $this->addLevel( 'content' , sprintf($this->content['text']['flesch'],$flesch,'difficult','Consider making shorter sentences with less difficult words to improve readability'), 5 );
            

            //Very Difficult to Read Rating
            }else 
            if($flesch >= 0){
            
                $this->addLevel( 'content' , sprintf($this->content['text']['flesch'],$flesch,'very difficult','Consider making shorter sentences with less difficult words to improve readability'), 4 );
            
            }        
        }
    }























    /**
    *
    *   flesch
    *       - Get the Flesch Rating of the Content
    *
    *   Params:
    *       - contents:         (String) The Content String
    *
    **/
    function flesch($contents){
      $contents = $this->getNonHtmlText( $contents ); //Clean text
      return round( ( 206.835 - ( 1.015 * $this->avgWordsPerSentence( $contents ) ) - ( 84.6 * $this->avgSyllablesPerWord( $contents ) ) ), 1 );
    }
    


























    /**
    *
    *   avgWordsPerSentence
    *       - Get the Average Words Per Sentence in Content
    *
    *   Params:
    *       - contents:         (String) The Content String
    *
    **/
    function avgWordsPerSentence($contents){
        $contents         = $this->getNonHtmlText( $contents );  //Clean text
        $intSentenceCount = $this->getTotalSentence( $contents );
        $intWordCount     = $this->countWords( $contents );
        return ( $intWordCount / $intSentenceCount );
    }
    





























    /**
    *
    *   avgWordsPerSentence
    *       - Get Average Syllables per word
    *
    *   Params:
    *       - contents:         (String) The Content String
    *
    **/
    function avgSyllablesPerWord($contents){      
        $contents          = $this->getNonHtmlText( $contents ); //Clean text
        $intSyllableCount = 0;
        $intWordCount     = $this->countWords( $contents );
        $arrWords         = explode( ' ', $contents );
        
        //Loop through the Total Words
        for($i = 0; $i < $intWordCount; $i++){
            $intSyllableCount += $this->getTotalSyllables( $arrWords[$i] );
        }

        //Return the Average
        return ( $intSyllableCount / $intWordCount );
    }
    































    /**
    *
    *   getTotalSyllables
    *       - Get Total Syllables
    *
    *   Params:
    *       - contents:         (String) The Content String
    *
    **/
   private function getTotalSyllables($strWord){

        //Prepare Data
        $intSyllableCount = 0;
        $strWord          = strtolower( $strWord );

        //Check if the word exists in the problem words
        if(isset( $this->problemWords[$strWord] )){
            return $this->problemWords[$strWord];
        }


        //Prepare the String
        $strWord          = preg_replace( $this->prefixSuffix, '', $strWord, -1, $intPrefixSuffixCount );
        $strWord          = preg_replace( '/[^a-z]/is', '', $strWord );
        $arrWordParts     = preg_split( '/[^aeiouy]+/', $strWord );
        $intWordPartCount = 0;

        foreach( $arrWordParts as $strWordPart ){
            if( $strWordPart <> '' ){
                $intWordPartCount++;
            }
        }

        //Get the Total Syllables
        $intSyllableCount = $intWordPartCount + $intPrefixSuffixCount;

        //Subtract the Syllables
        foreach( $this->subSyllables as $strSyllable ){
            $intSyllableCount -= preg_match( '~' . $strSyllable . '~', $strWord );
        }

        //Add the Syllables
        foreach( $this->addSyllables as $strSyllable ){
            $intSyllableCount += preg_match( '~' . $strSyllable . '~', $strWord );
        }

        //Get the Total Syllables
        $intSyllableCount = ($intSyllableCount == 0) ? 1 : $intSyllableCount;
        

        //Return the Count
        return $intSyllableCount;     
    }








    




























    /**
    *
    *   getTotalSentence
    *       - Get the Total String
    *
    *   Params:
    *       - contents:         (String) The Content String
    *
    **/
    private function getTotalSentence($contents){    

        $contents = $this->getNonHtmlText( $contents );
        
        // Will be tripped up by "Mr." or "U.K.". Not a major concern at this point.
        $intSentences = max( 1, strlen( utf8_decode( preg_replace( '/[^\.!?]/', '', $contents ) ) ) );
        
        return $intSentences;

    }














































    /**
    *
    *   levelURL
    *       - Validate the URL
    *
    *   Params:
    *       - task:       (Object) The Current Task Object
    *             - language:         (String) The Page Language
    *             - title:            (String) The Page Title
    *             - content:          (String) The Page Content
    *             - description:      (String) The Meta Description
    *             - url:              (String) The Page URL
    *             - root:             (String) The Page Root
    *
    **/
    private function levelURL($task){
      
        $arrow  = $this->cleanUpString( $task['keyword'] , true );
        $term1  = $this->cleanUpString( $task['url'], true );
        $term2  = $this->cleanUpString( $task['url'], false );
      
        //Find the Keyword in the URL
        if(stripos($term1, $arrow) !== false || stripos($term2, $arrow) !== false){
            
            $this->addLevel( 'url' , $this->url['text']['exists'] , 9 );
      

        //Keyword Not Found
        }else{
            
            $this->addLevel( 'url' , $this->url['text']['none'] , 6 );
      
        }

        // Check for Stop Words in the alias
        if($this->checkStopWords( $task['url'], true ) !== false){
        
            $this->addLevel( 'url' , $this->url['text']['stop'] , 5 );
      
        }
        
        // Check if the alias isn't too long relative to the length of the keyword
        if((strlen( $task['keyword'] ) + 20 ) < strlen( $task['url'] ) && 40 < strlen( $task['url'] ) ){
        
            $this->addLevel( 'url' , $this->url['text']['alias'] , 5 );
      
        }
    }














































    /**
    *
    *   getHeading
    *       - Fetch all heading of the contents
    *
    *   Params:
    *       - xpath:         (String) The XPath Document
    *
    **/
    private function getHeading($contents){
       
        preg_match_all('/<h([1-6])([^>]+)?>(.*?)<\/h\\1>/is', $contents,$matches);
        
        $headings = array();
        
        foreach($matches[3] as $heading){
        
            $headings[] = strtolower($heading);
        
        }
        
        return $headings;
    }

















































    /**
    *
    *   levelHeading
    *       - Validate the Page Heading
    *
    *   Params:
    *       - task:       (Object) The Current Task Object
    *             - language:         (String) The Page Language
    *             - title:            (String) The Page Title
    *             - content:          (String) The Page Content
    *             - description:      (String) The Meta Description
    *             - url:              (String) The Page URL
    *             - root:             (String) The Page Root
    *
    **/
    private function levelHeading($task){
      

        //Get the Total Headings
        $total = count($task['headings']);
      

        //Subheading Not Found
        if($total == 0){
        
            $this->addLevel( 'headings' , $this->content['headings']['none'] , 7 );
      
        }else{
        
            //Preset the Flag
            $flag = 0;
        

            //Loop through the Headings
            foreach($task['headings'] as $heading){

                if(stripos( $this->cleanUpString( $heading ), $task['keyword'] ) !== false){

                    $flag++;

                }

            }


            //The Keyword Exists in the Subheading
            if($flag){
             
                $this->addLevel( 'headings' , sprintf($this->content['headings']['keyword'],$flag,$total) , 9 );
            

            //The Keyword Doesn't Exist in the Subheading
            }else{
            
                $this->addLevel( 'headings' , $this->content['headings']['missing'] , 5 );
            
            }

        } 
    }











































    /**
    *
    *   getImagesData
    *       - Get Images Alt Text
    *
    *   Params:
    *       - contents:         (String) The Content String
    *
    **/
    private function getImagesData($contents){
      
        preg_match_all('/<img[^>]+>/im', $contents, $matches);
      
        $images = array();    
      
        //Loop through the Images
        foreach($matches[0] as $image ){
            if(preg_match('/alt=("|\')(.*?)\1/s',$image,$alt)){
                $images[] = strtolower($alt[2]);
            }
        }
      
        //Return the Image
        return $images;
      
    }















































    /**
    *
    *   levelImages
    *       - Check Images
    *
    *   Params:
    *       - task:       (Object) The Current Task Object
    *             - language:         (String) The Page Language
    *             - title:            (String) The Page Title
    *             - content:          (String) The Page Content
    *             - description:      (String) The Meta Description
    *             - url:              (String) The Page URL
    *             - root:             (String) The Page Root
    *
    **/
    private function levelImages($task){ 
        
        $images = $task['images'];
        $total  = substr_count($task['content'],'<img');
      

        //No Iamges Found
        if($total == 0){
        
            $this->addLevel( 'images' , $this->content['images']['none'] , 3 );
      


        //No Alt Tags Found
        }else
        if(count($images) == 0 && $total != 0){
        
            $this->addLevel( 'images' , $this->content['images']['alt'] , 5 );
      


        //images Found
        }else{
            
            $flag = false;
      
            //Loop through Each Image
            foreach($images as $alt){          
                
                $term1 = $this->cleanUpString( $alt, true );
                $term2 = $this->cleanUpString( $alt, false );
              

                //Found a Keyword in Term 1
                if(strpos($term1,$task['keyword']) !== false){
               
                    $flag = true;
              

                //Found a Keyword in Term 2
                }else 
                if(strpos($term2,$task['keyword']) !== false){
               
                    $flag = true;      
              
                }    
            }
        

            //We found Keywords in the Images
            if($flag){
              
                $this->addLevel( 'images' , $this->content['images']['exists'] , 9 );
            

            //No Keywords Found
            }else{
              
                $this->addLevel( 'images' , $this->content['images']['missing'] , 5 );
            
            }

        }

    }
















































   /**
    *
    *   getAnchorData
    *       - Get the Anchor's Text
    *
    *   Params:
    *       - xpath:         (String) The DOMDocument XPath
    *
    **/
    //Get the Anchor Text Data
    private function getAnchorData($xpath){

        //Get the Anchors
        $dom_objects  = $xpath->query("//a|//A");
        $anchor_texts = array();
      
        //Loop through the found Anchor Tags
        foreach($dom_objects as $dom_object){

            //Check the Link for the Anchor 
            if($dom_object->attributes->getNamedItem('href')){
            
                $href = $dom_object->attributes->getNamedItem( 'href' )->textContent;          
              
                //Check if its an External Link
                if(substr($href,0,4) == 'http'){            
                    
                    $anchor_texts['external'] = $dom_object->textContent;
              
                }
        
            }
        }
      
        //Clear the Dom Objects
        unset($dom_objects);
    
        //Return the Anchor Text
        return $anchor_texts;
    
    }
















































    /**
    *
    *   getTotalAnchors
    *       - Get the Total Amount of Anchors on the Page
    *
    *   Params:
    *       - xpath:         (String) The DOMDocument XPath
    *
    **/
    private function getTotalAnchors(&$xpath){

        //Get the Anchor Tags in the Dom
        $objects        = $xpath->query("//a|//A");
        $count          = array(
            'total'         => 0,
            'internal'      => array( 'nofollow' => 0, 'dofollow' => 0 ),
            'external'      => array( 'nofollow' => 0, 'dofollow' => 0 ),
            'other'         => array( 'nofollow' => 0, 'dofollow' => 0 )
        );

        //Loop through the Found Anchor Tags
        foreach($objects as $object){
            
            //Increment the Total
            $count['total']++;
            

            //Check if it has an HREF
            if( $object->attributes->getNamedItem('href') ){
          
                $href  = $object->attributes->getNamedItem('href')->textContent;
          
                //Check the HREF for an external link
                if(substr($href,0,4) == 'http' && !preg_match('/https?:\/\/(www\.)?'.strtolower($_SERVER['HTTP_HOST']).'/i',$href) ){

                    $type = "external";

                }else
                //Check the HREF for an internal link
                if( strpos($href,':') === false ){
                
                    $type = "internal";


                //Different kind of HREF
                }else{
                    
                    $type = "other";

                }

                //Check the Link for Follow / No Follow
                if( $object->attributes->getNamedItem('rel') ){
            
                    $link_rel = $object->attributes->getNamedItem('rel')->textContent;
            
                    //REL is NoFollow
                    if(stripos($link_rel,'nofollow') !== false){
              
                        $count[$type]['nofollow']++;
            

                    //Rel is Follow
                    }else{
              
                        $count[$type]['dofollow']++;
                    
                    }
          
                //Rel is Follow
                }else{
            
                    $count[$type]['dofollow']++;
          
                }

            }
      
        }
      
        return $count;
    
    }



























































    /**
    *
    *   levelAnchor
    *       - Check the Page's Anchors
    *
    *   Params:
    *       - xpath:         (String) The DOMDocument XPath
    *
    **/
    private function levelAnchor( $task ){
      
        //Get Anchor Text
        $anchor_texts = $task['anchors'];
        $stats        = $task['anchors_stats'];

        //No Anchors Exist
        if($stats['external']['nofollow'] == 0 && $stats['external']['dofollow'] == 0){
        
            $this->addLevel( 'anchor' , sprintf($this->content['anchor']['links'],0,0) , 5 );
      
        }else{
        
            $flag = false;
        
            //Loop through Anchor Text and Check for Keyword
            foreach($anchor_texts as $anchor_text){
                if(strtolower($anchor_text) == $task['keyword']){
                    $flag = true;
                }
            }

            //Anchor Tags have the Keyword
            if($flag){
                $this->addLevel( $this->content['anchor']['keyword'] , 2 );
            }
          
            
            //Anchor Tags have Do Follow Links and 0 No Follow Links
            if($stats['external']['nofollow'] == 0 && $stats['external']['dofollow'] > 0){
                
                $this->addLevel( 'anchor' , sprintf($this->content['anchor']['dofollow'], $stats['external']['dofollow']) , 9 );
          


            //Anchor Tags have No Follow Links and 0 Do Follow Links
            }else
            if($stats['external']['nofollow'] > 0 && $stats['external']['dofollow'] == 0){
          
                $this->addLevel( 'anchor' , sprintf($this->content['anchor']['nofollow'], $stats['external']['nofollow']) , 7 );
        


            //Anchor Tags have Both No Follow and Do Follow Links
            }else{
          
                $this->addLevel( 'anchor' , sprintf($this->content['anchor']['links'], $stats['external']['nofollow'], $stats['external']['dofollow']) , 8 );
            
            }
        
        }
    }















































    /**
    *
    *   addLevel
    *       - Get the Level Class
    *
    *   Params:
    *       - type:    (String) The Type of Analysis Completed
    *       - text:    (String) The Textual String
    *       - level:   (INT) The Numeric Level Representation
    *
    **/
    //Get the Level Class
    private function addLevel($type,$text,$level){

        if($level < 1){
        
            $class = 'bad';
            $color = 'red';
        
        }else
        if($level < 3){
        
            $class = 'bad';
            $color = 'red';

        }else
        if($level < 4){
        
            $class = 'bad';
            $color = 'red';

        }else
        if($level < 6){
        
            $class = 'poor';
            $color = 'orange';

        }else
        if($level < 8){
        
            $class = 'okay';
            $color = 'yellow';

        }else{
        
            $class = 'good';
            $color = 'green';

        }

        $this->report[$level]     = !isset($this->report[$level])? array() : $this->report[$level] ;
        $this->report[$level][]   = array(
            'type'      => $type,
            'severity'  => $level,
            'class'     => $class, 
            'text'      => $text,
            'color'     => $color
        );

    }



















































    /**
    *
    *   build
    *       - Build the Report
    *
    *   Params:
    *       n/a
    *
    **/
    private function build(){

        //Sort the Report
        ksort( $this->report );

        //Prepare the Return Data
        $return = array();

        //Loop through the Report
        foreach( $this->report as $report ){
            foreach( $report as $item ){
                $return[] = $item;
            }
        }

        //Return the Report
        return $return;

    }












}

?>