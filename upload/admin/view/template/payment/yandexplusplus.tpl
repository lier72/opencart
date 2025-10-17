<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-cod" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <?php if ($error_warning) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-pencil"></i></h3>
      </div>
      <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-cod" class="form-horizontal">
        	<div class="form-group required">
            <label class="col-sm-2 control-label"><?php echo $entry_license; ?></label>
            <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_license" value="<?php if (isset($yandexplusplus_license)){ echo $yandexplusplus_license; }?>" />
              <br />
              <?php if ($error_license) { ?>
              <div class="text-danger"><?php echo $error_license; ?></div>
              <?php } ?></div>
          </div>
        	<div class="form-group required">
            <label class="col-sm-2 control-label"><?php echo $entry_login; ?></label>
            <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_login" value="<?php if (isset($yandexplusplus_login)){ echo $yandexplusplus_login; }?>" />
              <br />
              <?php if ($error_login) { ?>
              <div class="text-danger"><?php echo $error_login; ?></div>
              <?php } ?></div>
        	</div>
      	<div class="form-group required">
        <label class="col-sm-2 control-label"><?php echo $entry_password; ?></label>
        <div class="col-sm-10"><input class="form-control" type="password" name="yandexplusplus_password" value="<?php if (isset($yandexplusplus_login)){ echo $yandexplusplus_password; }?>" />
          <br />
          <?php if ($error_password) { ?>
          <div class="text-danger"><?php echo $error_password; ?></div>
          <?php } ?></div>
      	</div>
        <div class="form-group">
          <label class="col-sm-2">Адрес HTTP-уведомления:</label>
          <div class="col-sm-10"><?php echo $copy_result_url; ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_name_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;" ><?php if ($yandexplusplus_name_attach) { ?>
            <input type="radio" name="yandexplusplus_name_attach" value="1" checked="checked" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_name_attach" value="0" />
            <?php echo $text_default; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_name_attach" value="1" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_name_attach" value="0" checked="checked" />
            <?php echo $text_default; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_name; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
            <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_name_<?php echo $language['language_id']; ?>" cols="50" rows="1"><?php echo isset(${'yandexplusplus_name_' . $language['language_id']}) ? ${'yandexplusplus_name_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_fixen ; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_fixen == 'proc') { ?>
            <input type="radio" name="yandexplusplus_fixen" value="fix" />
            <?php echo $entry_fixen_fix; ?>
            <input type="radio" name="yandexplusplus_fixen" value="proc" checked="checked" />
            <?php echo $entry_fixen_proc; ?>
            <input type="radio" name="yandexplusplus_fixen" value="0" />
            <?php echo $entry_fixen_order; ?>
            <?php } else if($yandexplusplus_fixen == 'fix'){ ?>
            <input type="radio" name="yandexplusplus_fixen" value="fix" checked="checked" />
            <?php echo $entry_fixen_fix; ?>
            <input type="radio" name="yandexplusplus_fixen" value="proc" />
            <?php echo $entry_fixen_proc; ?>
            <input type="radio" name="yandexplusplus_fixen" value="0" />
            <?php echo $entry_fixen_order; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_fixen" value="fix" />
            <?php echo $entry_fixen_fix; ?>
            <input type="radio" name="yandexplusplus_fixen" value="proc" />
            <?php echo $entry_fixen_proc; ?>
            <input type="radio" name="yandexplusplus_fixen" value="0" checked="checked" />
            <?php echo $entry_fixen_order; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_fixen_amount; ?></label>
          <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_fixen_amount" value="<?php echo isset($yandexplusplus_fixen_amount) ? $yandexplusplus_fixen_amount : ''; ?>" ><br />
          <?php if ($error_fixen) { ?>
          <div class="text-danger"><?php echo $error_fixen; ?></div>
          <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_komis; ?></label>
          <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_komis" value="<?php echo isset($yandexplusplus_komis) ? $yandexplusplus_komis : ''; ?>" >%</div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_maxpay; ?></label>
          <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_maxpay" value="<?php echo isset($yandexplusplus_maxpay) ? $yandexplusplus_maxpay : ''; ?>" >руб.</div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_style; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_style) { ?>
            <input type="radio" name="yandexplusplus_style" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_style" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_style" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_style" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_later; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_button_later) { ?>
            <input type="radio" name="yandexplusplus_button_later" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_button_later" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_button_later" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_button_later" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $text_createorder_or_notcreate; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_createorder_or_notcreate) { ?>
            <input type="radio" name="yandexplusplus_createorder_or_notcreate" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_createorder_or_notcreate" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_createorder_or_notcreate" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_createorder_or_notcreate" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_success_alert_admin_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_success_alert_admin) { ?>
            <input type="radio" name="yandexplusplus_success_alert_admin" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_success_alert_admin" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_success_alert_admin" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_success_alert_admin" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_success_alert_customer_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_success_alert_customer) { ?>
            <input type="radio" name="yandexplusplus_success_alert_customer" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_success_alert_customer" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_success_alert_customer" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_success_alert_customer" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_instruction_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_instruction_attach) { ?>
            <input type="radio" name="yandexplusplus_instruction_attach" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_instruction_attach" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_instruction_attach" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_instruction_attach" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_instruction; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_instruction_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_instruction_' . $language['language_id']}) ? ${'yandexplusplus_instruction_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_mail_instruction_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_mail_instruction_attach) { ?>
            <input type="radio" name="yandexplusplus_mail_instruction_attach" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_mail_instruction_attach" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_mail_instruction_attach" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_mail_instruction_attach" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_mail_instruction; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_mail_instruction_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_mail_instruction_' . $language['language_id']}) ? ${'yandexplusplus_mail_instruction_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_success_comment_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_success_comment_attach) { ?>
            <input type="radio" name="yandexplusplus_success_comment_attach" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_success_comment_attach" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_success_comment_attach" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_success_comment_attach" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_success_comment; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_success_comment_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_success_comment_' . $language['language_id']}) ? ${'yandexplusplus_success_comment_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_hrefpage_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_hrefpage_text_attach) { ?>
            <input type="radio" name="yandexplusplus_hrefpage_text_attach" value="1" checked="checked" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_hrefpage_text_attach" value="0" />
            <?php echo $text_default; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_hrefpage_text_attach" value="1" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_hrefpage_text_attach" value="0" checked="checked" />
            <?php echo $text_default; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_hrefpage; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_hrefpage_text_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_hrefpage_text_' . $language['language_id']}) ? ${'yandexplusplus_hrefpage_text_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_success_page_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_success_page_text_attach) { ?>
            <input type="radio" name="yandexplusplus_success_page_text_attach" value="1" checked="checked" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_success_page_text_attach" value="0" />
            <?php echo $text_default; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_success_page_text_attach" value="1" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_success_page_text_attach" value="0" checked="checked" />
            <?php echo $text_default; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_success_page_text; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_success_page_text_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_success_page_text_' . $language['language_id']}) ? ${'yandexplusplus_success_page_text_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
         <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_waiting_page_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_waiting_page_text_attach) { ?>
            <input type="radio" name="yandexplusplus_waiting_page_text_attach" value="1" checked="checked" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_waiting_page_text_attach" value="0" />
            <?php echo $text_default; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_waiting_page_text_attach" value="1" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_waiting_page_text_attach" value="0" checked="checked" />
            <?php echo $text_default; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_waiting_page_text; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_waiting_page_text_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_waiting_page_text_' . $language['language_id']}) ? ${'yandexplusplus_waiting_page_text_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
        <label class="col-sm-2 control-label"><?php echo $entry_on_status; ?></label>
        <div class="col-sm-10"><select class="form-control" name="yandexplusplus_on_status_id">
            <?php foreach ($order_statuses as $order_status) { ?>
            <?php if ($order_status['order_status_id'] == $yandexplusplus_on_status_id) { ?>
            <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
            <?php } else { ?>
            <option value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
            <?php } ?>
            <?php } ?>
          </select></div>
      	</div>
      	<div class="form-group">
        <label class="col-sm-2 control-label"><?php echo $entry_order_status; ?></label>
        <div class="col-sm-10"><select class="form-control" name="yandexplusplus_order_status_id">
            <?php foreach ($order_statuses as $order_status) { ?>
            <?php if ($order_status['order_status_id'] == $yandexplusplus_order_status_id) { ?>
            <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
            <?php } else { ?>
            <option value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
            <?php } ?>
            <?php } ?>
          </select></div>
      	</div>
      	<div class="form-group">
        <label class="col-sm-2 control-label"><?php echo $entry_geo_zone; ?></label>
        <div class="col-sm-10"><select class="form-control" name="yandexplusplus_geo_zone_id">
            <option value="0"><?php echo $text_all_zones; ?></option>
            <?php foreach ($geo_zones as $geo_zone) { ?>
            <?php if ($geo_zone['geo_zone_id'] == $yandexplusplus_geo_zone_id) { ?>
            <option value="<?php echo $geo_zone['geo_zone_id']; ?>" selected="selected"><?php echo $geo_zone['name']; ?></option>
            <?php } else { ?>
            <option value="<?php echo $geo_zone['geo_zone_id']; ?>"><?php echo $geo_zone['name']; ?></option>
            <?php } ?>
            <?php } ?>
          </select></div>
      	</div>
      	<div class="form-group">
        <label class="col-sm-2 control-label"><?php echo $entry_status; ?></label>
        <div class="col-sm-10"><select class="form-control" name="yandexplusplus_status">
            <?php if ($yandexplusplus_status) { ?>
            <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
            <option value="0"><?php echo $text_disabled; ?></option>
            <?php } else { ?>
            <option value="1"><?php echo $text_enabled; ?></option>
            <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
            <?php } ?>
          </select></div>
      	</div>
      	<div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_sort_order; ?></label>
          <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_sort_order" value="<?php echo $yandexplusplus_sort_order; ?>" size="1" /></div>
        </div>
      </form>
    </div>
      <p style="text-align:center;">YandexPlusPlus Версия <?php echo $version ?></p>
  </div>
</div>
</div>
<?php echo $footer; ?> 