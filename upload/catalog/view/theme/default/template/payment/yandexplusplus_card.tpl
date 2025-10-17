<?php if ($instructionat){ ?>
<h2><?php echo $text_instruction; ?></h2>
<div class="content">
  <p><?php echo $yandexplusplus_cardi; ?></p>
</div>
<?php } ?>
<div class="buttons">
  <div class="pull-right">
    <input type="button" value="<?php echo $button_confirm; ?>" id="button-confirm" class="btn btn-primary" />
  </div>
</div>
<?php if ($btnlater){ ?><div class="pull-left"><input class="paylater btn btn-secondary" type="button" value="<?php echo $button_later; ?>" id="button-pay"  style="float:right;"/></div> <?php } ?>
<script type="text/javascript"><!--
$('#button-confirm').bind('click', function() {
	$.ajax({ 
		type: 'get',
		url: 'index.php?route=payment/yandexplusplus_card/confirm',
		success: function() {
			location = '<?php echo $pay_url; ?>';
		}		
	});
});
<?php if ($btnlater){ ?>
$('#button-pay').bind('click', function() {
	$.ajax({ 
		type: 'get',
		url: 'index.php?route=payment/yandexplusplus_card/confirm',
		cache: false,
		beforeSend: function() {
			$('#button-confirm').button('loading');
		},
		complete: function() {
			$('#button-confirm').button('reset');
		},		
		success: function() {
			location = '<?php echo $payment_url; ?>';
		}		
	});
});
<?php } ?>
//--></script>
