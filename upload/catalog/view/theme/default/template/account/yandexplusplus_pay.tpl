<form method="POST" name="form" action="https://money.yandex.ru/quickpay/confirm.xml"> 
 <input type="hidden" name="receiver" value="<?php echo $receiver; ?>"> 
 <input type="hidden" name="formcomment" value="<?php echo $confname; ?> : <?php echo $order_text; ?> <?php echo $order_id; ?>"> 
 <input type="hidden" name="short-dest" value="<?php echo $confname; ?> : <?php echo $order_text; ?> <?php echo $order_id; ?>"> 
 <input type="hidden" name="label" value="<?php echo $order_id; ?>"> 
 <input type="hidden" name="quickpay-form" value="small">
 <input type="hidden" name="targets" value="<?php echo $order_text_target; ?> <?php echo $order_id; ?>"> 
 <input type="hidden" name="sum" value="<?php echo $total; ?>" data-type="number" >
 <input type="hidden" name="comment" value="" >
 <input type="hidden" name="need-fio" value="false"> 
 <input type="hidden" name="need-email" value="false" > 
 <input type="hidden" name="need-phone" value="false"> 
 <input type="hidden" name="need-address" value="false"> 
 <input type="hidden" name="paymentType" value="<?php echo $paymentType; ?>">
</form> 
<script>
document.form.submit();
</script>