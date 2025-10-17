<div class="tab-pane active" id="tab-dashboard">


    <!-- Add the Scripts -->
    <script src="view/javascript/seomatic/amcharts/amcharts.js" type="text/javascript"></script>
    <script src="view/javascript/seomatic/amcharts/funnel.js" type="text/javascript"></script>
    <script src="view/javascript/seomatic/amcharts/gauge.js" type="text/javascript"></script>
    <script src="view/javascript/seomatic/amcharts/pie.js" type="text/javascript"></script>
    <script src="view/javascript/seomatic/amcharts/radar.js" type="text/javascript"></script>
    <script src="view/javascript/seomatic/amcharts/serial.js" type="text/javascript"></script>
    <script src="view/javascript/seomatic/amcharts/xy.js" type="text/javascript"></script>
    <script src="view/javascript/seomatic/amcharts/themes/light.js" type="text/javascript"></script>
    <script src="view/javascript/seomatic/daterangepicker/moment.min.js" type="text/javascript"></script>
    <script src="view/javascript/seomatic/daterangepicker/jquery.daterangepicker.js" type="text/javascript"></script>

    <!-- Add the Styles -->
    <link href="view/stylesheet/seomatic/daterangepicker.css" type="text/css" media="all" rel="stylesheet" />



    <!-- Date Selector -->
    <div style="float:right; padding-bottom:10px;">
        <strong style="margin:0 10px;">Showing Results For Date Range:</strong>
        <span id="chart-range">
            <input type="text" id="chart-start" name="seomatic[chart][start]" value="<?php echo date( 'Y-m-d' , $startdate ); ?>" />
            <input type="text" id="chart-end" name="seomatic[chart][end]" value="<?php echo date( 'Y-m-d' , $enddate ); ?>" />
        </span>
        <strong style="margin:0 10px;">on Search Engine:</strong>
        <select name="seomatic[chart][engine]">
            <option value="google">Google</option>
            <option value="bing">Bing</option>
            <option value="yahoo">Yahoo</option>
        </select>
        <strong style="margin:0 10px;">using Keyword:</strong>
        <select name="seomatic[chart][keyword]" id="ChartKeyword">
            <?php if( count( $keywords['keywords'] ) > 0 ){ ?>
                <?php foreach( $keywords['keywords'] as $k ){ ?>
                    <option value="<?php echo $k['keywordid']; ?>" data-id="<?php echo $k['keywordid']; ?>"><?php echo $k['keyword']; ?></option>
                <?php } ?>
            <?php }else{ ?>
                <option value="">None Available</option>
            <?php } ?>
        </select>
    </div>

    <hr style="clear:both; height:1px; background:#EEE; border:none;" />


    <!-- Chart -->
    <div id="seomatic-chart" class="chart" style="height:400px; clear:both;">
        <div id="seomatic-chart-loading" style="background:url('view/image/seomatic-loading-large.gif') center center no-repeat; width:100%; height:100%; display:none;"></div>
        <div id="seomatic-chart-nodata" style="width:100%; height:100%; line-height:400px; text-align:center; color:#CCC; font-size:25px; display:block;"><?php 
            if( $this->registry->get('SEOMatic')->expired ){
                                
                //The Subscription Link
                $subscribe = $this->registry->get('url')->link('seomatic/account', 'token=' . $this->session->data['token'] . '#tab-plans', 'SSL');

                //Expired
                echo 'Your SEOMatic Subscription Has Expired: <a href="' . $subscribe . '" style="color:#CCC;">Subscribe Now</a>';

            }else{

                //Standard Message
                echo 'No Data Available';

            }
        ?></div>
        <div id="seomatic-chart-wrapper" style="width:100%; height:100%;">

        </div>
    </div>



    <!-- Keyword Domain Selector -->
    <div style="float:right; padding:20px 0 10px;">
        <div style="display:inline;">
            <strong>Showing Results For:</strong>
            <select name="seomatic[keywords][domain]">
                <?php if(count( $domains ) > 0){ ?>
                    <?php foreach( $domains as $domain ){ ?>
                        <option value="<?php echo $domain['domainid']; ?>"><?php echo $domain['domain']; ?></option>
                    <?php } ?>
                <?php } ?>
            </select>
        </div>
        |
        <a class="btn btn-primary addkeyword"><span>Add a Keyword</span></a>
    </div>

    <div class="clearfix"></div>


    <!-- Keyword List -->

    <div class="table-responsive">

        <table class="table table-bordered table-hover" id="keywords">
            <thead>
                <tr>
                    <?php if( !$this->registry->get('SEOMatic')->expired ){ ?>
                        <td class="text-left" width="150">Last Updated</td>
                    <?php } ?>
                    <td class="text-left">Country</td>
                    <td class="text-left">Keyword</td>
                    <td class="text-left" width="75">Google</td>
                    <td class="text-left" width="75">Bing</td>
                    <td class="text-left" width="75">Yahoo</td>
                    <td class="text-left" width="50">Status</td>
                    <td class="text-center" width="120">Action</td>
                </tr>
            </thead>
            <tbody>
                <?php if( $keywords['total'] > 0 ){ ?>

                    <?php foreach($keywords['keywords'] as $item){ ?>
                        <tr class="keyword" data-id="<?php echo $item['keywordid']; ?>" data-keywordid="<?php echo $item['keywordid']; ?>" data-domainid="<?php echo $domains[0]['domainid']; ?>" data-countryid="<?php echo $item['countryid']; ?>" data-updated="<?php echo $item['updated']; ?>" data-added="<?php echo $item['added']; ?>" data-serp>
                            
                            <?php if( !$this->registry->get('SEOMatic')->expired ){ ?>
        
                                <!-- Updated -->
                                <td class="text-left updated"><?php echo  ( $item['updated'] != '0000-00-00 00:00:00' ? date('M d, Y h:i A',strtotime($item['updated'])) : 'Never' ); ?></td>
    
                            <?php } ?>
    
                            <!-- Countries -->
                            <td class="text-left">
                                <?php foreach($countries as $country){ ?>
                                    <?php if( $country['countryid'] == $item['countryid'] ){ ?>

                                        <img src="<?php echo $country['image']; ?>" alt="<?php echo $country['name']; ?>" />

                                    <?php } ?>
                                <?php } ?>
                                <input type="hidden" name="seomatic[countryid][]" value="<?php echo $item['countryid']; ?>" />
                            </td>

                            <!-- Keyword -->
                            <td class="left">
                                <?php echo $item['keyword']; ?>
                                <input type="hidden" name="seomatic[keyword][]" value="<?php echo $item['keyword']; ?>" />
                            </td>

                            <?php if( !$this->registry->get('SEOMatic')->expired ){ ?>
                            
                                <!-- SERPs -->
                                <td class="text-center google" data-google>-</td>
                                <td class="text-center bing" data-bing>-</td>
                                <td class="text-center yahoo" data-yahoo>-</td>
                            
                            <?php }else{ ?>

                                <!-- Expired -->
                                <td colspan="3" class="text-center center no-keywords-text">
                                    <span style="white-space:nowrap;">
                                        Your SEOMatic Subscription Has Expired: 
                                        <a href="<?php echo $this->registry->get('url')->link('seomatic/account', 'token=' . $this->session->data['token'] . '#tab-plans', 'SSL'); ?>">Subscribe Now</a>
                                    </span>
                                </td>

                            <?php } ?>

                            <!-- Status -->
                            <td class="text-center">
                                <select name="seomatic[status][]">
                                    <option value="0"<?php if(!$item['status']) echo ' selected="selected"'; ?>>Inactive</option>
                                    <option value="1"<?php if($item['status']) echo ' selected="selected"'; ?>>Active</option>
                                </select>
                            </td>

                            <!-- Actions -->
                            <td class="text-center" style="white-space:nowrap;">
                                <button type="button" name="delete">Delete</button>
                                |
                                <button type="button" name="save">Save</button>
                                <input type="hidden" name="seomatic[keywordid][]" value="<?php echo $item['keywordid']; ?>" />
                            </td>
                        </tr>
                    <?php } ?>

                <?php } ?>


                <tr id="nokeywords"<?php if($keywords['total'] > 0){ ?> style="display:none;"<?php } ?>>
                    <td class="text-center" colspan="8">
                        No Keywords Available: 
                        <a class="btn btn-primary addkeyword"><span>Add a Keyword</span></a>
                    </td>
                </tr>

            </tbody>
        </table>

    </div>


      <!-- Chart JS -->
      <script type="text/javascript">
            <?php if( !$this->registry->get('SEOMatic')->expired ){ ?>


                //The Chart Object
                var Chart = {


                    //The Chart Object
                    obj: null,



                    //Short Textual Month Names
                    months:     ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],



                    //The Domains
                    domains:        <?php echo json_encode($domains); ?>,



                    //The Competitors
                    competitors:    <?php echo json_encode($competitors); ?>,




                    //Get the Date Object from a String
                    getDate:   function(str){

                        //Prepare the Date
                        str     = str.split('-');
                        date    = new Date( Date.UTC( str[0] , str[1] - 1 , str[2] , 0 , 0 , 0 ) );

                        //Return
                        return date;
                    },




                    //Get the Days Between the Range
                    getDays:  function(start,end){

                        //Return the Days Passed
                        return  Math.round( Math.abs( ( this.getDate(start).getTime() - this.getDate(end).getTime() ) / ( 24 * 60 * 60 * 1000) ) );

                    },


                    //Format Javascript Date object to YYYY-MM-DD
                    showDate:   function(date){

                        //Return the Formatted Date
                        return date.getUTCFullYear() + '-' + ('00' + (date.getUTCMonth()+1)).slice(-2) + '-' +  ('00' + date.getUTCDate()).slice(-2)

                    }



                //End var Chart = {
                };













                /**
                *
                *   Click: [name^="seomatic[chart]"]
                *       - When a Chart Element is Modified - Update and Run the Chart
                *
                *   Params:
                *       n/a
                *
                **/
                $('[name^="seomatic[chart]"]').change(function(){

                    //Only run if the keyword has a real ID (not temporary
                    if( !isNaN(parseFloat( $('#ChartKeyword').val() )) && isFinite( $('#ChartKeyword').val() ) ){

                        var data    = {

                            //The Class to Call
                            'class':        'keywords',

                            //The Function to Call
                            'function':     'get',

                            //The Object to Send
                            'object':       {

                                //Get the Keyword ID
                                keywordid: $('[name="seomatic[chart][keyword]"]').val()
                                
                            }

                        };

                        //Show Loading
                        $('#seomatic-chart-loading').css('display','block');

                        //Hide Chart
                        $('#seomatic-chart-wrapper').css('display','none');

                        //Hide No Data
                        $('#seomatic-chart-nodata').css('display','none');

                        //Get the Keyword Data and Ensure Data Exists (Prevent SERP Caching)
                        $.post('index.php?route=seomatic/functions/api&token=<?php echo $token; ?>', data, function(obj){
                            if( obj.result && obj.keyword.updated != '0000-00-00 00:00:00' ){

                                var data    = {

                                    //The Class to Call
                                    'class':        'serp',

                                    //The Function to Call
                                    'function':     'get',

                                    //The Object to Send
                                    'object':       [

                                        //Competitors Data
                                        {
                                            accountid:          <?php echo $accountid; ?>,
                                            keywordid:          $('[name="seomatic[chart][keyword]"]').val(),
                                            link:               {  
                                                id:                 [<?php echo implode(',',$competitorids); ?>],
                                                type:               'competitorid'
                                            },
                                            date:               {
                                                start:              $('[name="seomatic[chart][start]"]').val(),
                                                end:                $('[name="seomatic[chart][end]"]').val()
                                            },
                                            listing:            ['organic','map'],
                                            engine:             $('[name="seomatic[chart][engine]"]').val(),
                                            group:              'date',
                                            limit:              1000
                                        },

                                        //Domain Data
                                        {
                                            accountid:          <?php echo $accountid; ?>,
                                            keywordid:          $('[name="seomatic[chart][keyword]"]').val(),
                                            link:               {
                                                id:                 [<?php echo implode(',',$domainids); ?>],
                                                type:               'domainid'
                                            },
                                            date:               {
                                                start:              $('[name="seomatic[chart][start]"]').val(),
                                                end:                $('[name="seomatic[chart][end]"]').val()
                                            },
                                            listing:            ['organic','map'],
                                            engine:             $('[name="seomatic[chart][engine]"]').val(),
                                            group:              'date',
                                            limit:              1000
                                        }
                                        
                                    ]

                                };


                                //Load Data
                                $.post('index.php?route=seomatic/functions/api&token=<?php echo $token; ?>', data, function(obj){

                                    //Get the Starting Date
                                    var start = $('[name="seomatic[chart][start]"]').val(),

                                    //Get the Ending Date
                                    end = $('[name="seomatic[chart][end]"]').val(),

                                    //Get the Keyword Used
                                    keyword = $('option[value="' + $('[name="seomatic[chart][keyword]"]').val() + '"]').html(),

                                    //Get the Search Engine Name
                                    engine = $('option[value="' + $('[name="seomatic[chart][engine]"]').val() + '"]').html(),

                                    //The Types of Domains in the Chart
                                    types = ['domain','competitor'],

                                    //Total Days in Date Range
                                    days = Chart.getDays( start , end ),

                                    //The Starting Date
                                    start = Chart.getDate( start ),

                                    //The Chart Structure
                                    chart = [],
                                        
                                    //The Chart Data
                                    data = [],

                                    //Compiler Var to build the Chart Data
                                    compiler = { competitor:{} , domain:{} };


                                    //Loop through all the returned listings
                                    for( var i=0; i<obj.length; i++ ){
                                        for( var j=0; j<obj[i].serp.length; j++ ){

                                            //Set the Date
                                            var date = new Date( obj[i].serp[j].date ),

                                            //Prepare Data
                                            item = {
                                                linkid:     obj[i].serp[j].linkid,
                                                linktype:   obj[i].serp[j].linktype.replace('id',''),
                                                reference:  obj[i].serp[j].linkid + obj[i].serp[j].linktype,
                                                list:       Chart[ obj[i].serp[j].linktype.replace('id','') + 's' ],
                                                date:       ( date.getUTCMonth().toString() + date.getUTCDate().toString() + date.getUTCFullYear().toString() )
                                            };

                                            //If we don't have a reference yet
                                            if(typeof compiler[ item.linktype ][ item.reference ] === 'undefined'){

                                                //Loop through all the (Competitors or Domains)
                                                for( var k=0; k < item.list.length; k++ ){

                                                    //When we find the right item
                                                    if( item.list[k].linkid == item.linkid ){

                                                        //Save the Domain
                                                        obj[i][j].reference = item.list[k].domain;

                                                    }
                                                
                                                }


                                                //Create the Object
                                                compiler[ item.linktype ][ item.reference ] = {
                                                    dates:  {},
                                                    data:   obj[i].serp[j]
                                                };

                                            }

                                            //Only add if we don't have the current date (We only want the highest position)
                                            if( typeof compiler[ item.linktype ][ item.reference ].dates[ item.date ] === 'undefined' ){

                                                //Save the Data
                                                compiler[ item.linktype ][ item.reference ].dates[ item.date ] = obj[i].serp[j].serp ;

                                            }

                                        }
                                    }



                                    //Go through each day
                                    for( var i=0, date=start; i <= days; i++ ){

                                        //Set the Date
                                        date.setDate( date.getDate() + ( i > 0 ? 1 : 0 ) );

                                        //Save the Timestamp
                                        var timestamp = ( date.getUTCMonth().toString() + date.getUTCDate().toString() + date.getUTCFullYear().toString() );

                                        //Add it to the Date Object
                                        var obj = {
                                            date:       Chart.months[ date.getUTCMonth() ] + ' ' + date.getUTCDate(),
                                            year:       date.getUTCFullYear()
                                        };


                                        //Loop through the compiler types (Competitor / Domain )
                                        for( var j=0; j < types.length; j++ ){

                                            //Loop
                                            for( var k=0; k < Chart[ types[j] + 's' ].length; k++ ){

                                                //Get the item
                                                var item1 = Chart[ types[j] + 's' ][k];

                                                //Get the Date
                                                var item2 = compiler[ types[j] ][ item1[ types[j]+'id' ] + types[j]+'id' ];
                                                    item2 = ( typeof item2 !== 'undefined' ? item2.dates[ timestamp ] : null );

                                                //Save the Competitor
                                                obj[ item1.domain ] = ( typeof item2 === 'undefined' || item2 === null ? 51 : item2 );
                                            
                                            }

                                        }


                                        //Add the object to the Chart Data
                                        data.push( obj );

                                    }



                                    //Hide Loading
                                    $('#seomatic-chart-loading').css('display','none');

                                    //Show Chart
                                    $('#seomatic-chart-wrapper').css('display','block');

                                    

                                    //Build the Chart
                                    if( Chart.obj === null ){


                                        //Build the Domains
                                        for( var i=0; i < types.length; i++ ){
                                            for( var j=0; j < Chart[ types[i]+'s' ].length; j++ ){

                                                //Get the Chart Item
                                                var item = Chart[ types[i]+'s' ][j];

                                                chart.push({
                                                    'balloonText':          item.domain + ' ' + engine + ' SERP [[category]] [[year]]: [[value]]',
                                                    'bullet':               'round',
                                                    'title':                item.domain,
                                                    'valueField':           item.domain,
                                                    'fillAlphas':           0
                                                });

                                            }
                                        }


                                        //Build Chart
                                        Chart.obj = AmCharts.makeChart('seomatic-chart-wrapper', {
                                            type:             'serial',
                                            theme:            'light',
                                            legend:           { useGraphSettings: true },
                                            dataProvider:     data,
                                            valueAxes:        [{
                                                integersOnly:       true,
                                                maximum:            51,
                                                minimum:            1,
                                                reversed:           true,
                                                axisAlpha:          0,
                                                dashLength:         5,
                                                gridCount:          10,
                                                position:           'left',
                                                //title:              'SERP Position (1-50)'
                                            }],
                                            titles:           [{
                                                text:               ' SERP Rank for ' + keyword,
                                                size:               20,
                                                bold:               false
                                            }],
                                            startDuration:    0.5,
                                            graphs:           chart,
                                            chartCursor:      {
                                                cursorAlpha:        0,
                                                cursorPosition:     'mouse',
                                                zoomable:           false
                                            },
                                            categoryField:    'date',
                                            categoryAxis:     {
                                                gridPosition:       'start',
                                                axisAlpha:          0,
                                                fillAlpha:          0.05,
                                                fillColor:          '#000000',
                                                gridAlpha:          0,
                                                position:           'top'
                                            },
                                            exportConfig:     {
                                                menuBottom:         '15px',
                                                menuRight:          '15px',
                                                menuItems:          [{
                                                    icon:               'http:view/javascript/seomatic/amcharts/images/export.png',
                                                    format:             'png'
                                                }]
                                            }
                                        });



                                    //Update the Chart
                                    }else{

                                        //Set the Title
                                        Chart.obj.titles[0].text = engine + ' SERP Rank for ' + keyword;

                                        //Set the Chart Data
                                        Chart.obj.dataProvider = data;

                                        //Send Chart Update
                                        Chart.obj.validateNow();

                                        //Update the Data
                                        Chart.obj.validateData();

                                    }


                                },'json');

                            }else{

                                //Hide Wrapper
                                $('#seomatic-chart-wrapper').css('display','none');

                                //Hide Loading
                                $('#seomatic-chart-loading').css('display','none');

                                //Show No Data
                                $('#seomatic-chart-nodata').css('display','block');

                            }
                        },'json');
                    
                    }

                });














                /**
                *
                *   dateRangePicker
                *       - Create the Chart's Date Range Picker
                *
                *   Params:
                *       n/a
                *
                **/
                $('#chart-range').dateRangePicker({
                    separator : ' to ',            
                    time: {
                    
                      //Disable the Time
                      enabled: false
                    
                    },            
                    getValue: function(){

                        //Get the Value
                        return ( $('input[name="seomatic[chart][start]"]').val() && $('input[name="seomatic[chart][end]"]').val() ) ? $('input[name="seomatic[chart][start]"]').val() + ' to ' + $('input[name="seomatic[chart][end]"]').val() : '' ;

                    },
                    setValue: function(s,s1,s2){

                        //Set the Start Date
                        $('input[name="seomatic[chart][start]"]').val(s1);

                        //Set the End Date
                        $('input[name="seomatic[chart][end]"]').val(s2);
                    
                        //Only run if we have a keyword
                        if( $('#ChartKeyword').val() != '' ){

                            //Trigger Change
                            $('[name^="seomatic[chart]"]:eq(0)').change();
 
                        }

                    },
                    startDate: ( $('#ChartKeyword').val() != '' ? SEOMatic['Date'].parse( $('[data-keywordid="' + $('#ChartKeyword').val() + '"]').data('added') ) : null ),
                    endDate: ( $('#ChartKeyword').val() != '' ? SEOMatic['Date'].parse( $('[data-keywordid="' + $('#ChartKeyword').val() + '"]').data('updated') ) : null )
                });











                /**
                *
                *   Chart Keyword
                *       - On the Chart Keyword Update, Update the Minimum an Maximum Date Range
                *
                *   Params:
                *       n/a
                *
                **/
                $('[name="seomatic[chart][keyword]"]').change(function(e){

                    var el      = $('[data-keywordid="' + $(this).val() + '"]' ),
                        added   = $(el).data('added'),
                        updated = $(el).data('updated');

                    if( $(el).length > 0 && $(el).data('updated') != '0000-00-00 00:00:00' ){

                        var added       = SEOMatic['Date'].parse( added ),
                            updated     = SEOMatic['Date'].parse( updated ),
                            date        = new Date();

                        //Get Last Month
                        date.setMonth( date.getUTCMonth() - 1 );

                        //Set the Start Date
                        $('#chart-start').val( added < date ? Chart.showDate( date ) : Chart.showDate( added ) );                        

                        //Set the End Date
                        $('#chart-end').val(Chart.showDate( updated ));

                        //Only run if we have keywords
                        if( $('#ChartKeyword').val() != '' ){

                            //Update the Date Range Picker
                            $('#chart-range').dateRangePicker({
                                startDate:  SEOMatic['Date'].parse( $('[data-keywordid="' + $('#ChartKeyword').val() + '"]').data('added') ),
                                endDate:    SEOMatic['Date'].parse( $('[data-keywordid="' + $('#ChartKeyword').val() + '"]').data('updated') )
                            });

                        }

                    }


                });








                
                /**
                *
                *   Chart Setup
                *       - If we have keywords set up the chart event, otherwise show the chart as not available
                *
                *   Params:
                *       n/a
                *
                **/
                if( $('[name="seomatic[chart][keyword]"]').val() != '' ){

                    //Render the Chart
                    $('[name^="seomatic[chart]"]').eq(0).change();

                }else{

                    //Show No Data
                    $('#seomatic-chart-nodata').css('display','none');

                    //Hide Wrapper
                    $('#seomatic-chart-wrapper').css('display','block');                    

                    //Hide Loader
                    $('#seomatic-chart-loading').css('display','none');

                    //Show Chart not Avaialble
                    $('#seomatic-chart-wrapper').html(
                        '<div style="border:1px solid #EEE; line-height:400px; text-align:center; height:100%; width:100%; font-size:30px; color:#EEE; font-weight:bold;">'+
                            'Chart Not Available'+
                        '</div>'
                    );

                }












                /**
                *
                *   seomatic:keyword:added
                *       - When a Keyword is Added, Add it to the list
                *
                *   Params:
                *       n/a
                *
                **/
                $(document).bind('seomatic:keyword:added',function(ev,el,obj){

                    //Add the Keyword
                    $('#ChartKeyword').append(
                        '<option value="'+obj.keywordid+'" data-id="'+obj.keywordid+'">'+
                            obj.keyword+
                        '</option>'
                    );

                    //Remove the None Available Option if it exists
                    $('#ChartKeyword option[value=""]').remove();

                    //If the Keyword is the Same
                    if( obj.keywordid == $('#ChartKeyword').val() ){

                        //Update SEOLite
                        $('#ChartKeyword').change();

                    }

                });











                /**
                *
                *   seomatic:keyword:deleted
                *       - When a Keyword is Deleted, Remove it from the List
                *
                *   Params:
                *       n/a
                *
                **/
                $(document).bind('seomatic:keyword:deleted',function(ev,el,obj){

                    //Delete the Keyword
                    $('#ChartKeyword option[data-id="'+obj.keywordid+'"]').remove();


                    //If no Keywords Remain
                    if( $('#ChartKeyword option').length == 0 ){

                        //Show None Available
                        $('#ChartKeyword').append('<option value="">None Available</option>');

                    }else{

                        //Update SEOLite
                        $('#ChartKeyword').change();

                    }


                });


            

        <?php } ?>
    </script>






























      <!-- Keyword List -->
      <script type="text/javascript">







        //Keyword Object
        var Keywords = {

            //Newly Created Keyword ID Increment
            id:         1,

            //New Page
            insert:     <?php echo $job=='add' ? 'true' : 'false' ; ?>,

            //Link Type
            link:       {
                id:     '<?php echo $linkid; ?>',
                type:   '<?php echo $linktype; ?>',
            },

            //Countdown Timer to check if the Keyword Exists on Insert Pages
            timer:      null,

            //A list of the Countries
            countries:  <?php echo json_encode($countries); ?>,


            //Get a Keyword Data
            get:        function(scope){              

                //Get the Element
                var row  = $(scope).parents('tr'),

                //Prepare the Data
                data = {
                    keywordid:  $(row).find('[name="seomatic[keywordid][]"]').val(),
                    linkid:     Keywords.link.id,
                    linktype:   Keywords.link.type,
                    countryid:  $(row).find('[name="seomatic[countryid][]"]').val(),
                    keyword:    $(row).find('[name="seomatic[keyword][]"]').val(),
                    status:     $(row).find('[name="seomatic[status][]"]').val()
                };

                //Return the Row
                return data;
            }

        };













        /**
        *
        *   Click: a.addkeyword
        *       - Add the New Keyword HTML
        *
        *   Params:
        *       n/a
        *
        **/
        $('a.addkeyword').click(function(e){
            e.preventDefault();


            //Add the Row
            $('#keywords tbody').prepend(
                '<tr class="keyword" data-id="" data-keywordid="" data-domainid="" data-countryid="" data-updated="0000-00-00 00:00:00" data-added="0000-00-00 00:00:00" data-serp>' +

                    <?php if( !$this->registry->get('SEOMatic')->expired ){ ?>

                        //Updated
                        '<td class="text-left">Never</td>'+

                    <?php } ?>

                    //Countries
                    '<td class="text-left">'+
                        '<select name="seomatic[countryid][]" style="width:200px;">'+
                            <?php foreach($countries as $country){ ?>
                                '<option value="<?php echo $country['countryid']; ?>"<?php if( $this->registry->get('config')->get('seomatic_country') == $country['countryid'] ){ ?> selected="selected"<?php } ?>>'+
                                    '<?php echo $country['name']; ?>'+
                                '</option>'+
                            <?php } ?>
                        '</select>'+
                    '</td>'+

                    //Keyword
                    '<td class="text-left"><input type="text" name="seomatic[keyword][]" value="" style="width:100%;" /></td>'+


                    <?php if( !$this->registry->get('SEOMatic')->expired ){ ?>
                    
                        //SERPs
                        '<td class="text-center google" data-google>-</td>'+
                        '<td class="text-center bing" data-bing>-</td>'+
                        '<td class="text-center yahoo" data-yahoo>-</td>'+
                    
                    <?php }else{ ?>

                        //Expired
                        '<td colspan="3" class="text-center center no-keywords-text">'+
                            '<span style="white-space:nowrap;">'+
                                'Your SEOMatic Subscription Has Expired: '+
                                '<a href="<?php echo $this->registry->get('url')->link('seomatic/account', 'token=' . $this->session->data['token'] . '#tab-plans', 'SSL'); ?>">Subscribe Now</a>'+
                            '</span>'+
                        '</td>'+

                    <?php } ?>

                    //Status
                    '<td class="text-center">'+
                        '<select name="seomatic[status][]">'+
                            '<option value="0">Inactive</option>'+
                            '<option value="1">Active</option>'+
                        '</select>'+
                    '</td>'+

                    //Actions
                    '<td class="text-center" style="white-space:nowrap;">'+
                        '<button type="button" name="delete">Delete</button>'+
                        ' | '+
                        '<button type="button" name="create">Create</button>'+
                        '<input type="hidden" name="seomatic[keywordid][]" value="tmp'+( Keywords.id++ )+'" />'+
                    '</td>'+

                '</tr>'
            );

            //Hide the No Keywords
            $('#nokeywords').hide();

        });


























        /**
        *
        *   KEYUP: seomatic[keyword][]
        *       - Restrict the Characters on Keyup
        *
        *   Params:
        *       n/a
        *
        **/
        $('#keywords').on('keyup','input[name="seomatic[keyword][]"]',function(e){
        
            //Remove all Unwanted Characters
            $(this).val( $(this).val().replace(/[!@#$%^&*()~,.|\/\\<>{}\]\[+=_-]/ig,'') );

        });


















        /**
        *
        *   BLUR: seomatic[keyword][]
        *       - Check Keyword Length and give recommendations
        *
        *   Params:
        *       n/a
        *
        **/
        $('body').on('blur','input[name="seomatic[keyword][]"]',function(e){

            var validations = {

                //Too many Characters
                characters:     ( $(this).val().length > 45 ),

                //Too many words
                words:          ( $(this).val().split(/\s+/).length > 5 )

            };

            //Check the Total Characters
            if( $(this).data('notified') != $(this).val() && ( validations.characters || validations.words )  ){

                //Output an Alert
                alert(
                    (
                        validations.characters ?
                            'You have over 45 characters in your keyword.'
                        :
                            'You have more than 5 words in your key phrase.'
                    ) +
                    "\r" +
                    'Long keywords or key phrases are not recommended'
                );

                //Store the value so we don't notify twice
                $(this).data( 'notified' , $(this).val() );

            }

        });













        








        /**
        *
        *   Click: button[name="save"]
        *       - Update a Keyword
        *
        *   Params:
        *       n/a
        *
        **/
        $('#keywords').on('click','button[name="save"]',function(e){
          e.preventDefault();

            //Prepare Data
            var el        = $(this).parents('tr'),
                data      = Keywords.get(this);

            //Ensure the Keyword is not Empty
            if( data.keyword != '' ){

                //Check if its an Insert Page
                if( !Keywords.insert ){

                    //Post the Status
                    $.post('index.php?route=seomatic/keywords/update&token=<?php echo $token; ?>', data, function(obj){
                        if( obj.result ){

                            //Trigger Updated Event
                            $(document).trigger('seomatic:keyword:updated',[el,$.extend(data,obj)]);

                            //Output Success
                            alert('Keyword Saved');

                        }else{

                            //Output Error
                            alert( obj.error );

                            //Keyword Limit Reached
                            if( obj.code == 'keyword-limit-reached' ){

                                //Set the Status to Inactive
                                $( el ).find('select[name="seomatic[status][]"]').val(0);

                            }

                        }
                    },'json');

                }else{
                            
                    //Trigger Updated Event
                    $(document).trigger('seomatic:keyword:updated',[el,data]);

                }


            }else{

                //Keyword Required
                alert('A Keyword is Required');

            }

        });



















        /**
        *
        *   Click: button[name="create"]
        *       - Create a New Keyword (Temporarily if its an insert page, immediately on an existing page)
        *
        *   Params:
        *       n/a
        *
        **/
        $('#keywords').on('click','button[name="create"]',function(e){
            e.preventDefault();

            //Prepare Data
            var el              = this,
                parent          = $( this ).parents('tr'),
                datetime        = SEOMatic['Date'].datetime(),
                data            = Keywords.get(this);
                data.domainid   = $('[name="seomatic[keywords][domain]"]').val();

            //Check if its an insert page
            if( !Keywords.insert ){

                //Post the Status
                $.post('index.php?route=seomatic/keywords/create&token=<?php echo $token; ?>', data, function(obj){
                    if( obj.result ){

                        //Update the Keyword ID
                        $(el).next().val( obj.keywordid );
                        $(parent).attr( 'data-id' , obj.keywordid ).data( 'id' , obj.keywordid );
                        $(parent).attr( 'data-keywordid' , obj.keywordid ).data( 'keywordid' , obj.keywordid );
                        $(parent).attr( 'data-domainid' , data.domainid ).data( 'domainid' , data.domainid );
                        $(parent).attr( 'data-countryid' , data.countryid ).data( 'countryid' , data.countryid );
                        $(parent).attr( 'data-added' , datetime ).data( 'added' , datetime );

                        //Trigger Event
                        $(document).trigger('seomatic:keyword:added',[el,$.extend(data,obj)]);

                        //Change the Button
                        $(el).replaceWith('<button type="button" name="save">Save</button>');

                        //Hide the Keyword Field and Show just the Keyword
                        $(parent).find('[name="seomatic[keyword][]"]').replaceWith( 
                            '<input type="hidden" name="seomatic[keyword][]" value="' + data.keyword + '" />' + 
                            data.keyword 
                        );

                        for( var i=0; i<Keywords.countries.length; i++ ){
                            if( data.countryid == Keywords.countries[i].countryid ){

                                //Hide the Country Field and Show the Country with the Flag
                                $(parent).find('[name="seomatic[countryid][]"]').replaceWith(
                                    '<input type="hidden" name="seomatic[countryid][]" value="' + data.countryid + '" />' +
                                    '<img src="' + Keywords.countries[i].image + '" alt="' + Keywords.countries[i].name + '" />' 
                                );

                                //End the Loop
                                break;

                            }
                        }


                    }else{

                        //Output Error
                        alert( obj.error );
                    
                    }
                },'json');

            }else
            if( data.keyword != '' ){

                //Send an API commend to check for the keyword
                var request = {

                    //The Class to Call
                    'class':        'keywords',

                    //The Function to Call
                    'function':     'get',

                    //The Object to SEnd
                    'object':       {

                        //The Keyword to Lookup
                        keyword:    data.keyword

                    }

                };

                //Send the API Call
                $.post('index.php?route=seomatic/functions/api&token=<?php echo $token; ?>',request,function(obj){
                    if( obj.keywords.length == 0 ){

                        //Change the Button
                        $(el).replaceWith('<button type="button" name="save">Save</button>');

                        //Trigger Event
                        $(document).trigger('seomatic:keyword:added',[el,data]);

                    }else{

                        //Keyword Exists
                        alert('The Keyword Already Exists on this Account');

                    }
                },'json');


            }else{


                //Keyword Required
                alert('A Keyword is Required');

            }

        });














        /**
        *
        *   Click: button[name="delete"]
        *       - Removes a Single Keyword
        *
        *   Notes:
        *       - This will be turned into a batch function so you can delete multiple keywords at once.
        *
        *   Params:
        *       n/a
        *
        **/
        $('#keywords').on('click','button[name="delete"]',function(e){
            e.preventDefault();

            //Ensure we want to delete this keyword
            if( window.confirm('Are you sure you want to delete this keyword?') ){


                //Save the Element
                var el      = this,
                    data    = Keywords.get(this),
                    ids     = [ data.keywordid ];

                //Check if its an insert page
                if( !Keywords.insert && data.keywordid.indexOf('tmp') === -1 ){

                    //Send the Command
                    $.post('index.php?route=seomatic/keywords/remove&token=<?php echo $token; ?>', { keywordids:ids }, function(obj){
                        if( obj[0].result ){

                            //Remove the Field
                            $(el).parents('tr').remove();

                            //No Keywords Remain
                            if( $('#keywords tbody').children(':not(#nokeywords)').length == 0 ){

                                //Show the No Keywords
                                $('#nokeywords').show();

                            }

                            //Trigger Event
                            $(document).trigger('seomatic:keyword:deleted',[el,data]);

                        }else{
                            
                            //Output Error
                            alert( obj[0].error );
                          
                        }
                    },'json');

                }else{

                    //Remove the Field
                    $(el).parents('tr').remove();


                    //If no Keywords Remain
                    if( $('#keywords tbody').children(':not(#nokeywords)').length == 0 ){

                        //Show the No Keywords
                        $('#nokeywords').show();

                    }

                    //Trigger Event
                    $(document).trigger('seomatic:keyword:deleted',[el,data]);

                }

            }
        });




















        //Update the Keyword SERP Listing
        $('[name="seomatic[keywords][domain]"]').change(function(){

            //Prepare Data
            var ids = [];

            //Get the IDS
            $('[name="seomatic[keywordid][]"]').each(function(){
                
                //Get the Value
                var v = $(this).val();

                //If its a Number
                if( !isNaN(parseFloat(v)) && isFinite(v) ){

                    //Add it to the IDS
                    ids.push(v);

                }

            });


            //Set the Data
            var data = {

                //The Keyword IDs to check
                keywordids:     ids,

                //The Domain ID to check
                domainid:       $(this).val()

            };


            $.post('index.php?route=seomatic/functions/serp&token=<?php echo $token; ?>',data,function(obj){

                //Loop through each SERP result
                for( var i=0; i<obj.length; i++ ){

                    //Get the Element
                    var el = $('[name="seomatic[keywordid][]"][value="'+obj[i].keywordid+'"]').parents('tr');

                    //Set Bing
                    $(el).find('.bing').html( obj[i].bing );

                    //Set Yahoo
                    $(el).find('.yahoo').html( obj[i].yahoo );

                    //Set Google
                    $(el).find('.google').html( obj[i].google );

                    //Set Last Updated
                    $(el).find('.updated').html( obj[i].updated );

                }

            },'json');
           

        });








    </script>




    <!-- Default Dashboard Option -->
    <script type="text/javascript">
    <?php echo $this->registry->get('config')->get('seomatic_dashboard'); ?>
        <?php if( !$this->registry->get('config')->get('seomatic_dashboard') ){ ?>
            (function($){
                jQuery(window).load(function(){
                    window.setTimeout(function(){

                        $('[href="#tab-general"]').click();
                    
                    },500);            
                });
            })(jQuery);
        <?php } ?>
    </script>
    
</div>