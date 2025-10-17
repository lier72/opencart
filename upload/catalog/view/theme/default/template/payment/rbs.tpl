<h2><?php echo $text_instruction; ?></h2>
<p><b><?php echo $text_description; ?></b></p>
<div class="well well-sm">
  <p><?php echo $bank; ?></p>
  <p><?php echo $text_payment; ?></p>
</div>
<form action="<?php echo $action; ?>" method="post">
    <div class="buttons">
        <div class="pull-right">
            <input type="submit" name="m_process" value="<?php echo $button_confirm; ?>" class="btn btn-primary" />
        </div>
    </div>
</form>