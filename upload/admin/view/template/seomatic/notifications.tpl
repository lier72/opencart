        <script type="text/javascript">
            jQuery(document).ready(function(){

                <?php if( $this->registry->get('SEOMatic')->error ){ ?>
                    $('#content').prepend(
                        '<div class="alert alert-danger seomatic_warning">'+
                            '<i class="fa fa-excalmation-circle"></i>'+
                            '<?php echo addslashes($this->registry->get('SEOMatic')->error); ?>'+
                        '</div>'
                    );
                <?php } ?>


                <?php if( $this->registry->get('SEOMatic')->warning ){ ?>
                    $('#content').prepend(
                        '<div class="alert alert-warning seomatic_attention">'+
                            '<i class="fa fa-excalmation-circle"></i>'+
                            '<?php echo addslashes($this->registry->get('SEOMatic')->warning); ?>'+
                        '</div>'
                    );
                <?php } ?>


                <?php if( $this->registry->get('SEOMatic')->success ){ ?>
                    $('#content').prepend(
                        '<div class="alert alert-success seomatic_success">'+
                            '<i class="fa fa-check-circle"></i>'+
                            '<?php echo addslashes($this->registry->get('SEOMatic')->success); ?>'+
                        '</div>'
                    );
                <?php } ?>


            });
        </script>
