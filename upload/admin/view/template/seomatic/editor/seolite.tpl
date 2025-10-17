



        <!-- SEOLite -->
        <script type="text/javascript">
            jQuery(document).ready(function($){
                var SEOLite = {



                    //Search Engine Friendly URLS
                    sefurl:     {

                        //Are the SEF URLS active?
                        active:     <?php echo $seolite_sefurls; ?>,


                        //The Preset Path based on the Version of Opencart
                        preset:     '<?php echo addslashes( $seolite_preset ); ?>',


                        //The Frontend Server Path
                        path:       '<?php echo addslashes( $seolite_path ); ?>'

                    },



                    //The Keyword Value
                    keyword:      $('.SEOkeyword').val(),



                    //The Opencart Title Tag
                    title:        'div[id^="language"]:not([id*="s"]) input[name$="[name]"]:not([type="hidden"],[name*="image"]), div[id^="language"]:not([id*="s"]) input[name$="[title]"]:not([type="hidden"],[name*="image"]), input[name="config_meta_title"]:not([type="hidden"])',



                    //Custom Title Field for Plugins
                    customtitle:  'div[id^="language"]:not([id*="s"]) input[name*="title"]:not([type="hidden"],[name*="image"],[name*="meta_title"])',
                    


                    //The Opencart Meta Description
                    description:  'div[id^="language"]:not([id*="s"]) textarea[name$="[meta_description]"]:not([type="hidden"]), textarea[name="config_meta_description"]:not([type="hidden"])',



                    //The Opencart Description
                    content:      'div[id^="language"]:not([id*="s"]) textarea[name$="[description]"]',



                    //The Opencart SEO URL Keyword
                    url:          'input[name^="keyword"]',




                    //Maximum Characters in the Title
                    maxtitle:     60,




                    //Maximum Characters in the Description
                    maxdesc:      156,



                    //Limit the Text in SEOLite to the Maximum Characters, long strings are cut off with " ..."
                    maxlength:    function(text,length){
                        if( text && this.striptags(text).length > length ){

                            var size  = 0,
                              text    = text.split(' ');
                          
                            for(var i=0; i<text.length; i++){
                                size += this.striptags(text[i]).length;
                                if(size > length){
                                    text = text.slice(0,i-1).join(' ')+' ...';
                                    break;
                                }
                            }
                          
                            if(typeof text == 'object') text = text.join(' ');
                        
                        }
                        return text;
                    },




                    //Prepare the URL text
                    getURL:       function(text){ return text.replace(/[!@#$%^&*()~,.|\/\\<>{}\]\[+=_]/ig, '').replace(/ /g,'-').replace(/-+/g,'-').toLowerCase(); },




                    //Strip all the HTML Tags from the passed Text
                    striptags:    function(text){ return text ? this.decode( text ).replace(/(<([^>]+)>)/ig,"") : text ; },




                    //Decode HTML Special Chars
                    decode:       function(text){ return text.replace(/&amp;/g, '&') .replace(/&lt;/g, '<') .replace(/&gt;/g, '>') .replace(/&quot;/g, '"') .replace(/&#039;/g, "'"); },




                    //Checks the Remaining Allowed Characters for a Field
                    remaining:    function(obj,length){ return length - $(obj).val().length; },





                    //Gets the Current Value of a Field
                    value:        function(field){ 
                        switch(field){
                            case 'description':
                                var content = $(SEOLite[field]).filter(':visible').eq(0).val();
                                break;
                            case 'content':
                                var content = this.striptags($(SEOLite.content).filter(':visible').eq(0).html());
                                break;
                            case 'url':
                                var value   = $(SEOLite[field]).eq(0).val();
                                var content = (SEOLite.sefurl.active && value != ''? SEOLite.sefurl.path + value : SEOLite.sefurl.preset);
                                break;
                            default:
                                var content = $(SEOLite[field]).filter(':visible').eq(0).val();
                        }

                        return this.striptags( content );
                    },






                    //Searches the Field for Keywords and the maximum allowed Characters
                    search:     function(field,max){
                        if($(SEOLite[field]).length == 0) return;
                        
                        var regex    = new RegExp((field=='url'?this.getURL(this.keyword):this.keyword).replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'),'ig'),
                            value    = this.value(field),
                            value    = (max !== undefined? SEOLite.maxlength( value , SEOLite[max]) : value);
                            contents = this.keyword != ''? value.replace(regex,function(match){ return '<strong style="color:#000;">'+match+'</strong>'}) : value,
                            count    = value ? value.match(regex) : 0 ,
                            total    = count==null || this.keyword == ''?0:count.length;

                        if(field == 'title' && ($(SEOLite.customtitle).length == 0 || $(SEOLite.customtitle).val() == '')){
                            $('.SEOcustomtitleresults').html( total );
                        }else
                        if(field == 'customtitle' && contents != ''){
                            $('.SEOtitle:visible').html( contents );
                        }else
                        if(field == 'customtitle' && contents == ''){
                            return;
                        }else
                        if(field == 'title' && $(SEOLite.customtitle).val() != ''){
                            $('.SEO'+field+'results').html( total );
                            return;
                        }else
                        if(field == 'description' && $(SEOLite.description).filter(':visible').val() != ''){
                            $('.SEOdescriptionresults').html( total );
                            $('.SEOdescription').html( this.maxlength(contents,SEOLite.maxdesc) );
                        }else
                        if(field == 'content' && max !== undefined && ($(SEOLite.description).filter(':visible').length == 0 || $(SEOLite.description).filter(':visible').val() == '')){
                            $('.SEOdescriptionresults').html( total );
                            $('.SEOdescription').html( this.maxlength(contents,SEOLite.maxdesc) );
                        }
                        $('.SEO'+field+'results').html( total );
                        $('.SEO'+field).html( contents );
                      }



                //End SEOLite = {
                };








              
                /**
                *
                *   Title
                *       - Prepares the Title
                *
                *
                *
                **/
                //$('.SEOtitle').html( SEOLite.maxlength($(SEOLite.title).val(),SEOLite.maxtitle) );
                //$('.SEOtitle').attr('name', $(SEOLite.title).attr('name').replace('title','seokeyword') );







                /**
                *
                *   html
                *       - Sets up the HTML into each of the Languages
                *
                *   Params
                *       n/a
                *
                **/
                $('.tab-pane[id^="language"], [id="tab-store"]').prepend(

                    '<div class="form-group required">'+
                        '<label class="col-sm-2 control-label" for="input-name">Keyword</label>'+
                        '<div class="col-sm-10">'+

                            <?php if( ($route = explode('/', $this->registry->get('request')->get['route'])) && $route[0] != 'catalog'){ ?>

                                //The Keyword Input Selector
                                '<input type="text" name="seolite_keyword[<?php echo $this->registry->get('request')->get['route']; ?>]" class="SEOkeyword" value="<?php echo addslashes( $seolite_keyword ); ?>" class="form-control" />'+

                            <?php }else{ ?>

                                //The Keyword Selector
                                '<select name="seolite_keyword" class="SEOkeyword" class="form-control">'+
                                    <?php if( count($keywords['keywords']) > 0 ){ ?>
                                        <?php foreach( $keywords['keywords'] as $k ){ ?>
                                            '<option value="<?php echo $k['keyword']; ?>" data-id="<?php echo $k['keywordid']; ?>"><?php echo $k['keyword']; ?></option>'+
                                        <?php } ?>
                                    <?php }else{ ?>
                                        '<option value="">None Available</option>'+
                                    <?php } ?>
                                '</select>'+

                            <?php } ?>

                        '</div>'+
                    '</div>'+

                    '<div class="form-group">'+
                        '<div class="col-sm-2"></div>'+
                        '<div class="col-lg-10" style="width:512px;">'+

                            //The SEO Title Visual Reference
                            '<a class="SEOtitle" href="#" style="font-size: 16px; color: #11c; line-height: 19px; text-decoration:underline; margin:0; padding:0;"></a>'+

                            '<br />'+

                            //The SEO URL Visual Reference
                            '<a class="SEOurl" href="#" style="font-size: 13px; color: #282; line-height: 15px; margin:0; padding:0;"><?php echo addslashes( $seolite_path ); ?></a>'+

                            //The SEO Meta Description Visual Reference
                            '<p style="font-size: 13px; color: #000; line-height: 15px; margin:0; padding:0; word-wrap:break-word;">'+
                                '<span class="SEOdescription" style="color:rgb(135,135,135);"></span>'+
                            '</p>'+


                            '<hr />'+
                            
                            //The Keywords Found
                            '<p>Keywords Found:</p>'+




                            //Numerical List Showing the Keywords Found
                            '<ul class="seolist">'+
                                '<li>Header: <strong class="SEOtitleresults">n/a</strong></li>'+
                                '<li>Title: <strong class="SEOcustomtitleresults">n/a</strong></li>'+
                                '<li>URL: <strong class="SEOurlresults">n/a</strong> <small style="color:#<?php echo $seolite_sefurls?'0F0':'F00'; ?>">(<?php echo $seolite_sefurls?'SEF Urls Active':'SEF Urls Inactive'; ?>)</small></li>'+
                                '<li>Content: <strong class="SEOcontentresults">n/a</strong></li>'+
                                '<li>Meta Description: <strong class="SEOdescriptionresults">n/a</strong></li>'+
                            '</ul>'+

                        //End .col-lg-12
                        '</div>'+

                    //End .form-group
                    '</div>'
                );






                /**
                *
                *   Keyword
                *       - Sets the initial Keyword
                *
                *   Params:
                *       n/a
                *
                **/
                SEOLite.keyword = $('.SEOkeyword').val();





                /**
                *
                *  Title
                *       - Adds the Remaining Text below the Title Tag
                *
                *   Params:
                *       n/a
                *
                **/
                var type = $(SEOLite.customtitle).length > 0 && $(SEOLite.customtitle).val() != '' ? SEOLite.customtitle : SEOLite.title;
                $(type).after(
                    '<p class="remaining">Title display in search engines is limited to '+SEOLite.maxtitle+' chars, <strong class="SEOtitleremaining">'+
                        SEOLite.remaining(SEOLite.title,SEOLite.maxtitle)+
                    '</strong> chars left.</p>'
                );












                /**
                *
                *  Description
                *       - Adds the Remaining Text below the Meta Description Tag
                *
                *   Params:
                *       n/a
                *
                **/
                var type = $(SEOLite.description).length > 0? SEOLite.description : SEOLite.content;
                $(type).after(
                    '<p class="remaining">'+
                        'The meta description will be limited to '+SEOLite.maxdesc+' chars, '+
                        '<strong class="SEOdescriptionremaining">'+
                            SEOLite.remaining(type,SEOLite.maxdesc)+
                        '</strong> '+
                        'chars left. <br />'+
                        '<small>**If This field is empty, it will be auto-generated from the description</small>'+
                    '</p>'
                );










                /**
                *
                *   Keyword - change
                *       - Updates all of the SEOLite Fields based on the new keyword
                *
                *   Params:
                *       n/a
                *
                **/
                $( 'body' ).on( 'change' , '.SEOkeyword' , function(){
                    if( $('.SEOkeyword:visible').length > 0 ){

                        SEOLite.keyword = $('.SEOkeyword:visible').val();
                        $('.SEOkeywordplaceholder:visible').html('('+SEOLite.keyword+')');
                        SEOLite.search('title','maxtitle');
                        SEOLite.search('customtitle','maxtitle');
                        SEOLite.search('description','maxdesc');
                        SEOLite.search('content','maxdesc');
                        SEOLite.search('content');
                        SEOLite.search('url');

                    }else{

                        SEOLite.keyword = $('.SEOkeyword:first').val();

                    }
                });











                /**
                *
                *   Title - Key Up
                *       - Checks the Custom Title for the maximum amount of characters
                *       - Checks the Custom Title for Keywords
                *       - Checks the Available Remaining Characters
                *
                *   Params:
                *       n/a
                *
                **/
                $( 'body' ).on( 'keyup' , SEOLite.title , function(){ 
                    SEOLite.search('title','maxtitle');
                    $('.SEOtitleremaining:visible').html( SEOLite.remaining(this,SEOLite.maxtitle) ); 
                });











                /**
                *
                *   Custom Title - Key Up
                *       - Checks the Custom Title for the maximum amount of characters
                *       - Checks the Custom Title for Keywords
                *       - Checks the Available Remaining Characters
                *
                *   Params:
                *       n/a
                *
                **/
                $( 'body' ).on( 'keyup' , SEOLite.customtitle , function(){ 
                    SEOLite.search('customtitle','maxtitle'); 
                    $( ($(this).val() == ''? this : SEOLite.title) ).parent().find('.remaining').appendTo( $( ($(this).val() == ''? SEOLite.title : this) ).parent() );
                    $('.SEOtitleremaining:visible').html( SEOLite.remaining(this,SEOLite.maxtitle) );
                });













                /**
                *
                *   SEO Keyword - Key Up
                *       - Forces the SEO URL structure
                *       - Checks the String for keywords
                *
                *   Params:
                *       n/a
                *
                **/
                $( 'body' ).on( 'keyup' , SEOLite.url , function(){
                    $(this).val( SEOLite.getURL( $(this).val() ) );
                    SEOLite.search('url');
                });




















                /**
                *
                *   SEO Meta Description - Key Up
                *       - Forces the SEO URL structure
                *       - Checks the String for keywords
                *
                *   Params:
                *       n/a
                *
                **/
                $( 'body' ).on( 'keyup' , SEOLite.description, function(){
                    if($(this).val() != ''){

                        SEOLite.search('description','maxdesc'); 
                        $('.SEOdescriptionremaining:visible').html( SEOLite.remaining(this,SEOLite.maxdesc) ); 
                    
                    }else{

                        SEOLite.search('content','maxdesc');
                    
                    }
                });








                /**
                *
                *   Summernote - Initialize
                *       - Changes the Tags to Lookup when Editing the Content
                *
                *   Params:
                *       n/a
                *
                **/
                var intval = window.setInterval(function(){
                    if( $(SEOLite.content).nextAll('.note-editor').length > 0 ){

                        SEOLite.content = 'div[id^="language"]:not([id*="s"]) .note-editable';
                        
                        $('body').on('keyup', SEOLite.content, function(){ 
                            if($(SEOLite.description).filter(':visible').length > 0 && $(SEOLite.description).filter(':visible').val() == ''){
                                SEOLite.search('content','maxdesc'); 
                                $('.SEOdescriptionremaining:visible').html( SEOLite.remaining(this,SEOLite.maxdesc) );
                            }
                            SEOLite.search('content'); 
                        });

                        window.clearInterval( intval );

                    }
                },50);








                /**
                *
                *   CKEDITOR - Initialize
                *       - Changes the Tags to Lookup when Editing the Content
                *
                *   Params:
                *       n/a
                *
                **/
                if( typeof CKEDITOR !== 'undefined' ){

                    CKEDITOR.on('instanceReady', function(){
                        SEOLite.content = '[id^="cke_"][id*="description"] iframe';
                        $(SEOLite.content).each(function(){
                            $(this).contents().find('body').keyup(function(){ 

                                if($(SEOLite.description).length > 0 && $(SEOLite.description).filter(':visible').val() == ''){

                                    SEOLite.search('content','maxdesc'); 
                                    $('.SEOdescriptionremaining:visible').html( SEOLite.remaining(this,SEOLite.maxdesc) );

                                }

                                SEOLite.search('content');                                 
                            });
                        });
                    });
                
                }






                /**
                *
                *   Initialize
                *       - Initialize all of the Fields
                *
                *
                **/
                $('body').on('click','[data-toggle="tab"]',function(){
                    var intval = window.setInterval(function(){
                        if( $('.SEOkeyword:visible').length > 0 ){

                            //Clear the Interval
                            window.clearInterval( intval );

                            //Run the Loader
                            SEOLite.search('title','maxtitle');
                            SEOLite.search('customtitle','maxtitle');
                            SEOLite.search('description','maxdesc');
                            SEOLite.search('content','maxdesc');
                            SEOLite.search('content');
                            SEOLite.search('url');

                        }
                    },50);
                });




            });
        </script>












        <!-- On Keyword Update -->
        <script type="text/javascript">



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
                $('.SEOkeyword').each(function(){

                    //Add the Keyword
                    $(this).append(
                        '<option value="'+obj.keyword+'" data-id="'+obj.keywordid+'">'+
                            obj.keyword+
                        '</option>'
                    );

                    //Remove the None Available Option if it exists
                    $(this).find('option[value=""]').remove();

                    //If the Keyword is the Same
                    if( obj.keyword == $(this).val() ){

                        //Update SEOLite
                        $(this).change();

                    }

                });
            });







            /**
            *
            *   seomatic:keyword:updated
            *       - When a Keyword is Updated, Update it on the List
            *
            *   Params:
            *       n/a
            *
            **/
            $(document).bind('seomatic:keyword:updated',function(ev,el,obj){
                $('.SEOkeyword').each(function(){

                    //Update the Keyword
                    $(this).find('option[data-id="'+obj.keywordid+'"]').val( obj.keyword ).html( obj.keyword );

                    //If the Keyword is the Same
                    if( obj.keyword == $(this).val() ){

                        //Update SEOLite
                        $(this).change();

                    }

                });
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
                $('.SEOkeyword').each(function(){

                    //Delete the Keyword
                    $(this).find('option[data-id="'+obj.keywordid+'"]').remove();

                    //If no Keywords Remain
                    if( $(this).find('option').length == 0 ){

                        //Show None Available
                        $(this).append('<option value="">None Available</option>');

                    }else{

                        //Update SEOLite
                        $(this).change();

                    }

                });

            });



        </script>
