	$.extend(SEOMatic,{




		/**
		*
		*	DATE
		* 		- Date Parsing Function(s)
		*
		**/
		'Date': {


			/**
			*
			* 	Parse
			*		- Parses a returned server date
			*
			*	Params:
			* 		date: 		(String) The Date to Parse
			*
			**/
			parse: 		function( date ){

				var date = date.substring( 0 , date.indexOf('.') );
					date = date.split(/[-T:]/);

				return new Date( date[0], date[1]-1, date[2], date[3], date[4], date[5] );

			},


			/**
			*
			* 	Build
			*		- Builds a Readable Y-m-d H:i:s DateTo,e
			*
			*	Params:
			* 		date: 		(Object) The Date Object to Parse
			*
			**/
			datetime: 		function( date ){

				//Create the Date if Undefined
				if( typeof date === 'undefined' ) date = new Date();
				
				var datetime = {
					year: 		date.getUTCFullYear(),
					month: 		( date.getUTCMonth() + 1 ),
					day: 		date.getUTCDate(),
					hour: 		date.getUTCHours(),
					minutes: 	date.getUTCMinutes(),
					seconds: 	date.getUTCSeconds()
				};

				//Return the Formatted Date
				return  datetime.year + '-' + ( datetime.month < 10 ? '0' + datetime.month : datetime.month )  + '-' + ( datetime.day < 10 ? '0' + datetime.day : datetime.day ) + ' ' + datetime.hour  + ':' + datetime.minutes + ':' + datetime.seconds ;

			}


		},





		/**
		*
		*	SERP
		* 		- Gather Keyword SERP Data
		*
		**/
		SERP: {



	            /**
	            *
	            *   build
	            *       - Builds a Single SERP Request
	            *
	            *   Params:
	            *       data:        (Object) The SERP Data
	            *
	            *	Scope:
	            * 		this: 		(Object) The [serp-data] wrapper element
	            *
	            **/
	            build:      function( data ){
	                for( var i=0, el=this, engines=['google','bing','yahoo']; i < engines.length; i++ ){

	                	//Store the Engine
	                	var engine = engines[i];


	                    if( ( data[engine][0] == null || data[engine][0].serp == null ) && ( data[engine][1] == null || data[engine][1].serp == null ) ){

	                        //If No Results were Returned, Show NULL
	                        $(this).find('[data-'+engine+']').html( '50+' );

	                    }else{


	                    	//Ensure Both fields have 50 instead of null
	                    	for( var j=0; j < 2; j++ ) if( data[engine][j] !== null && data[engine][j].serp === null ) data[engine][j].serp = 50 ;

	                        //Get the Difference
	                        var difference = ( data[engine][1] === null || data[engine][1].serp === null ? 50 - data[engine][0].serp : data[engine][1].serp - data[engine][0].serp );



	                        //Check the Difference
	                        if( difference > 0 ){

	                            //Improved
	                            var settings = {
	                                'chevron':      'fa fa-chevron-up',
	                                'class':        'seomatic-serp-up'
	                            };

	                        }else
	                        if( difference < 0 ){

	                            //Decreased
	                            var settings = {
	                                'chevron':      'fa fa-chevron-down',
	                                'class':        'seomatic-serp-down'
	                            }

	                        }else{

	                            //No Change
	                            var settings = {
	                                'chevron':      '',
	                                'class':        'seomatic-serp-static'
	                            }

	                        }

	                        //Set the rank
	                        $(this).find('[data-'+engine+']').html(
	                            '<div class="seomatic-serp">'+
	                                '<strong>' + ( data[engine][0].serp == 50 ? '50+' : data[engine][0].serp ) + '</strong> ' +
	                                '<span class="' + settings['class'] + '">' +
	                                	'<i class="' + settings['chevron'] + '"></i>' + 
	                                	( difference != 0 ? Math.abs(difference) : '-' ) + 
	                                '</span>' +
	                            '</div>'
	                        );

	                    }

	                }
	            },








	            /**
	            *
	            *   load
	            *       - Sends the SERP Request
	            *
	            *   Params:
	            *       el:     (Element) The SERP Element to Use
	            *
	            **/
	            load:  function( el ){

	                //Get the Element
	                var el 		= ( typeof el !== 'undefined' ? el : '[data-serp]:not([data-updated="0000-00-00 00:00:00"])' ),
	                	self 	= this,
						data    = {

	                        //The Class to Call
	                        'class':        'serp',

	                        //The Function to Call
	                        'function':     'status',

	                        //The Object to Send
	                        'object':       {
	                        	keywordid: 		[],
	                        	'link': 	{
	                        		id: 		[],
	                        		type: 		'domainid'
	                        	},
	                        	limit: 			100
	                        }

	                    };

	                //Loop through the Fields
	                $(el).each(function(){

	                	var keywordids 	= data['object'].keywordid,
	                		linkids 	= data['object']['link']['id'];

	                	//Check for Duplicates
	                	if( $.inArray( $(this).data('keywordid') , keywordids ) === -1 ){

		                	//Add the Keyword ID
	                		keywordids.push( $(this).data('keywordid') );

	                	}

	                	//Check for Duplicates
	                	if( $.inArray( $(this).data('domainid') , linkids ) === -1 ){

	                		//Add the Link ID
	                		linkids.push( $(this).data('domainid') );
						
	                	}

	                });

	                //Show Loading
	                $( el ).find('[data-google],[data-bing],[data-yahoo]').html('<img src="view/image/loading.gif" alt="Loading" />');



	                //Load Data
	                $.post( 'index.php?route=seomatic/functions/api&token=' + SEOMatic.token , data, function(obj){
	                	for( var i=0; i<obj.serp.length; i++){

	                        //Build all SERP fields
                            self.build.call( $('[data-serp][data-domainid="' + obj.serp[i].linkid + '"][data-keywordid="' + obj.serp[i].keywordid + '"]') , obj.serp[i] );

	                    }
	                },'json');


	            },














	            /**
	            *
	            *   format
	            *       - Prepares the Number format
	            *
	            *   Params:
	            *       num:     (INT) The Number to Format
	            *
	            **/
	            format: 	function( num ){
	            	if( typeof num !== 'undefined' ){
		            	if( num < 100 ){

		            		return num;

		            	}else
		            	if( num < 1000 ){

		            		return num[0] + 'k' ;

		            	}else
		            	if( num < 10000 ){

		            		return num.substr(0,2) + 'k' ;

		            	}else
		            	if( num < 100000 ){

		            		return num.substr(0,3) + 'k' ;

		            	}else
		            	if( num < 1000000 ){

		            		return num[0] + ( num[1] > 0 ? '.' + num[1] : '' ) + 'm' ;

		            	}else
		            	if( num < 10000000 ){

		            		return num.substr(0,2) + (num[2] > 0 ? '.' + num[2] : '' ) + 'm' ;

		            	}else
		            	if( num < 100000000 ){

		            		return num.substr(0,3) + 'm' ;

		            	}else
		            	if( num > 100000000 ){

		            		return ( num - 100000000 ) + 'b' ;

		            	}
		            }

		            return null;
	            }



	        }






	});









	/**
	*
	*	READY: document
	* 		- Initailize any SEOMatic Data on Document Load
	*
	*
	**/
	jQuery(document).ready(function($){




		//If we have SERP Data on the Page
		if( $('[data-serp]').length > 0 ){

			//Build all of them
			SEOMatic.SERP.load();

		}


		/**
		*
		*	HOVER: 	.seomatic-serp
		*		- Align the Serp Hover field vertically and horizontally
		*
		* 	Params:
		* 		n/a
		*
		**/
		$('body').on('hover','.seomatic-serp',function(){

			var serphover = $(this).find('.seomatic-serphover') ;

			$( serphover ).css({ 
				visibility: 	'visible',
				top: 			( $(this).outerHeight(true) / 2 ) - ( $( serphover ).outerHeight(true) / 2 ),
				left: 			( $(this).outerWidth(true) / 2 ) - ( $( serphover ).outerWidth(true) / 2 ) 
			});

		})


	});

