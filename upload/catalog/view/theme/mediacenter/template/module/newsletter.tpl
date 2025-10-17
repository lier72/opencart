<div class="box newsletter" id="newsletter<?php echo $module_id; ?>">
    
	<?php if($module_title != '') { ?>
	<div class="box-heading"><?php echo $module_title; ?></div>
	<div class="strip-line"></div>
	<?php } ?>
	<div class="box-content">
        
		<?php if($module_text != '') echo $module_text; ?>
        <div class="newsletter-input">
            <div class="line-through"></div>
            <input type="text" class="email" placeholder="<?php echo $input_placeholder; ?>"  />
            <a class="button subscribe"><?php echo $subscribe_text; ?></a>
            <?php if($button_unsubscribe == 1) { ?>
                <a class="button unsubscribe"><?php echo $unsubscribe_text; ?></a>
            <?php } ?>
        </div>
	</div>
</div>

<script type="text/javascript">
$(document).ready(function() {
	function Unsubscribe() {
		$.post('<?php echo $unsubscribe_url; ?>', 
			{ 
				email: $('#newsletter<?php echo $module_id; ?> .email').val() 
			}, function (e) {
				$('#newsletter<?php echo $module_id; ?> .email').val('');
				alert(e.message);
			}
		, 'json');
	}
	
	function Subscribe() {
		$.post('<?php echo $subscribe_url; ?>', 
			{ 
				email: $('#newsletter<?php echo $module_id; ?> .email').val() 
			}, function (e) {
				if(e.error === 1) {
					var r = confirm(e.message);
					if (r == true) {
					    $.post('<?php echo $unsubscribe_url; ?>', { 
					    	email: $('#newsletter<?php echo $module_id; ?> .email').val() 
					    }, function (e) {
					    	$('#newsletter<?php echo $module_id; ?> .email').val('');
					    	alert(e.message);
					    }, 'json');
					}
				} else {
					$('#newsletter<?php echo $module_id; ?> .email').val('');
					alert(e.message);
				}
			}
		, 'json');
	}
	
	$('#newsletter<?php echo $module_id; ?> .subscribe').click(Subscribe);
	$('#newsletter<?php echo $module_id; ?> .unsubscribe').click(Unsubscribe);
	$('#newsletter<?php echo $module_id; ?> .email').keypress(function (e) {
	    if (e.which == 13) {
	        Subscribe();
	    }
	});
});
</script>