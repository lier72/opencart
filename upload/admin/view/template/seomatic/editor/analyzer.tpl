<!-- Content Analyzer -->
<script type="text/javascript">
    

    //Preset the Variables
    var SEOAnalyzer = {

        //Use the same keyword as SEOLite
        keyword:        'select[name="seolite_keyword"]:visible',

        //Get the Page Title
        title:          'input[name$="[name]"]:visible',

        //Get the Meta Description
        description:    'textarea[name$="[meta_description]"]:visible',

        //Get the Page Content
        content:        'textarea[name$="[description]"]',

        //Get the URL
        url:            'input[name="keyword"]'

    };


    




    /**
    *
    *   #tab-general - append
    *       - Adds the Analyzer Button to the bottom of the Tab General Tab
    *
    *   Params:
    *       n/a
    *
    **/
    $('[id$="general"] .tab-pane').append(
        '<div class="form-group">'+
            '<label class="col-sm-2 control-label">'+
                '<span data-toggle="tooltip" data-original-title="Analyze Your Page for SEO Recommendations">Analyze Page:</span>'+
            '</label>'+
            '<div class="col-sm-10">'+
                '<div class="table-responsive">'+
                    '<table class="table table-bordered table-hover SEOanalyze">'+
                        '<thead>'+
                            '<tr>'+
                                '<td class="text-right" colspan="100%">'+
                                    '<a class="btn btn-primary SEOanalyze-report-button"><span>Build Report</span></a>'+
                                '</td>'+
                            '</tr>'+
                        '</thead>'+
                        '<tbody>'+
                            '<tr>'+
                                '<td class="text-center" colspan="100%">You haven\'t created a report yet!</td>'+
                            '</tr>'+
                        '</tbody>'+
                    '</table>'+
                '</div>'+
            '</div>'+
        '</div>'
    );











    /**
    *
    *   NOTE - Initialize
    *       - Changes the SEOAnalyzer Field to CKEDITOR instead
    *
    *   Params:
    *       n/a
    *
    **/
    var AnalyzerContent = window.setInterval(function(){
        if( $(SEOAnalyzer.content).nextAll('.note-editor').length > 0 ){

            //Set the Content
            SEOAnalyzer.content = 'div[id^="language"]:not([id*="s"]) .note-editable';

            //Save the Timer
            var timer = null;

            $('body').on('keyup', SEOAnalyzer.content, function(){

                //Clear the Timer
                if( timer !== null ) window.clearTimeout( timer );

                //Set the Timer
                timer = window.setTimeout(function(){

                    //Clear the Timer
                    timer = null;

                    if( $( SEOAnalyzer.keyword ).val() != '' ){

                        //Run the Analyzer
                        $('.SEOanalyze-report-button').click();

                    }

                },500);

            });

            //End the Interval
            window.clearInterval( AnalyzerContent );

        }
    },50);



















    /**
    *
    *   CLICK: div[id^="language"]:not([id*="s"]) .note-editor button
    *       - Update the Analyzer when a Button is Clicked
    *
    *   Params:
    *       n/a
    *
    **/
    $( 'body' ).on( 'click' , 'div[id^="language"]:not([id*="s"]) .note-editor button' , function(){
        if( $( SEOAnalyzer.content ).filter(':visible').length > 0 ){

            //Run Report
            $('.SEOanalyze-report-button').click();

        }
    });





















    /**
    *
    *   SEOAnalyze a[class="button"],- click
    *       - Run the SEO Analyzer
    *
    *   Params:
    *       n/a
    *
    **/
    $('.SEOanalyze-report-button').click(function(){

        //Prepare the Data
        var rows = '',
            data = {
                keyword:        $( SEOAnalyzer.keyword ).val(),
                title:          $( SEOAnalyzer.title ).eq(0).val(),
                description:    $( SEOAnalyzer.description ).eq(0).val(),
                content:        ( 
                                    typeof CKEDITOR !== 'undefined' ? 
                                        ( SEOAnalyzer.content.indexOf('iframe') > -1 ? $( SEOAnalyzer.content ).filter(':visible').contents().find('body').html() : $( SEOAnalyzer.content ).eq(0).val() ).replace(/&nbsp;/g,' ') 
                                    : 
                                        ( SEOAnalyzer.content.indexOf('note-editable') > -1 ? $( SEOAnalyzer.content ).filter(':visible').html() : $( SEOAnalyzer.content ).filter(':visible').val() ).replace(/&nbsp;/g,' ')
                                ),
                url:            $( SEOAnalyzer.url ).eq(0).val()
            };

        
        $('.SEOanalyze tbody').html(
            '<tr>' +
                '<td colspan="100%" style="height:300px;">' +
                    '<div id="seomatic-analyzer-loading" style="background:url(\'view/image/seomatic-loading-large.gif\') center center no-repeat; width:100%; height:100%;"></div>' +
                '</td>' +
            '</tr>'
        );


        //Send the Analysis request
        $.post('index.php?route=seomatic/functions/analyze&token=<?php echo $token; ?>&time='+new Date(), data, function(obj){
            if( obj.result ){

                //Build the Report
                for( var i=0; i<obj.report.length; i++){

                    rows += '<tr>'+
                                '<td class="text-center '+obj.report[i]['class']+'" style="background:'+obj.report[i].color+'; width:10px !important;" width="10px">'+
                                    '&nbsp;'+
                                '</td>'+
                                '<td class="text-left">'+
                                    obj.report[i].text+
                                '</td>'+
                                '<td class="text-left" style="color:gray;">'+
                                    '<strong>'+
                                        '<small>'+
                                            obj.report[i].type+
                                        '</small>'+
                                    '</strong>'+
                                '</td>'+
                            '</tr>';

                }


                //Create the Table
                $('.SEOanalyze tbody').html( rows );

            }else{

                //An Error Occured
                alert( obj.error );

                //Remove the Table
                $('.SEOanalyze tbody').html('<tr><td class="text-center" colspan="100%">You haven\'t created a report yet!</td></tr>');

            }
        },'json');

    });




























    /**
    *
    *   SEOAnalyzer.keyword - onchange
    *       - Run the SEO Analyzer when any of the SEOAnalyzer Fields Change
    *
    *   Params:
    *       n/a
    *
    **/


    //Save the Timer
    var timer = null;

        
    for( key in SEOAnalyzer ){

        //On Keyup, Run the Analyzer
        $( 'body' ).on( 'keyup' , SEOAnalyzer[key] , function(){

            //If a Keyword Exists
            if( $( SEOAnalyzer.keyword ).val() != '' ){

                //Clear the Timer
                if( timer !== null ) window.clearTimeout( timer );

                //Set the Timer
                timer = window.setTimeout(function(){

                    //Clear the Timer
                    timer = null;
             
                    //Run the Report
                    $('.SEOanalyze-report-button').click();
            
                },500);

            }

        });

    }




























    /**
    *
    *   [data-toggle="tab"]
    *       - Run the Visible Analzer when a Toggle is Clicked
    *
    *   Params:
    *       n/a
    *
    **/
    $('[data-toggle="tab"]').click(function(){
        window.setTimeout(function(){
            if( $( SEOAnalyzer.keyword ).is(':visible') && $( SEOAnalyzer.keyword ).val() != '' ){

                $('.SEOanalyze-report-button').click();
        
            }
        },50);
    });









    







    /**
    *
    *   seomatic:keyword:deleted
    *       - When a Keyword is Deleted & no Keywords remain, Clear the Analyzer
    *
    *   Params:
    *       n/a
    *
    **/
    $(document).bind('seomatic:keyword:deleted',function(ev,el,obj){

        //Delete the Keyword
        $('.SEOkeyword option[data-id="'+obj.keywordid+'"]').remove();

        //If no Keywords Remain
        if( $('.SEOkeyword').val() == '' ){

            //Remove the Table
            $('.SEOanalyze tbody').html('<tr><td class="center" colspan="100%">You haven\'t created a report yet!</td></tr>');

        }

    });






















</script>