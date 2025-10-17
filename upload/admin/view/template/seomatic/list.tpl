
    <!-- Keyword List -->
    <script type="text/javascript">


        var Keywords = <?php echo json_encode($keywords); ?>;



        //Add Controls
        $('.page-header .pull-right').prepend(
            '<div style="display:inline;">'+
                '<strong>Showing Results For:</strong> '+
                '<select name="seomatic[domain]">'+
                    <?php if( count($domains) > 0 ){ ?>
                        <?php foreach( $domains as $domain ){ ?>
                            '<option value="<?php echo $domain['domainid']; ?>"><?php echo $domain['domain']; ?></option>'+
                        <?php } ?>
                    <?php }else{ ?>
                        '<option value="">No Domains Available</option>'+
                    <?php } ?>
                '</select>'+
                '<img src="view/image/loading.gif" alt="Loading" id="seomatic-loading" style="display:none;" />'+
            '</div>'+
            ' | '
        );




        /**
        *
        *   CHANGE:     [name="seomatic[domain]"]
        *       - Updates the SERP Listings on Change
        *
        *
        **/
        $('[name="seomatic[domain]"]').change(function(){

            //Update all the Domain ID's
            $('[data-serp]').attr('data-domainid', $(this).val() );

            //Re-run the SERP
            SEOMatic.SERP.load();

        });












        /**
        *
        *   CHANGE:     [name="seomatic[keyword][]"]
        *       - Update the Keyword SERP Listing
        *
        *
        **/
        $('[name="seomatic[keyword][]"]').change(function(){

            var parent  = $(this).parents('[data-serp]'),
                keyword = Keywords[ $(this).val() ];

            $( parent ).attr( 'data-keywordid' , $(this).val() ).data( 'keywordid' , $(this).val() );
            $( parent ).attr( 'data-countryid' , keyword.countryid ).data( 'countryid' , keyword.countryid );
            $( parent ).attr( 'data-updated' , keyword.updated ).data( 'updated' , keyword.updated );

            $( parent ).find( '.updated' ).html( keyword.date );
            $( parent ).find( '.flag img' ).attr('src' , keyword.country.image ).attr('alt' , keyword.country.name );

            //Rebuild the Parent
            SEOMatic.SERP.load( parent );
           

        });









    </script>
