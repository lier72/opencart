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
            <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_card_license" value="<?php if (isset($yandexplusplus_card_license)){ echo $yandexplusplus_card_license; }?>" />
              <br />
              <?php if ($error_license) { ?>
              <div class="text-danger"><?php echo $error_license; ?></div>
              <?php } ?></div>
          </div>
          <div class="form-group required">
            <label class="col-sm-2 control-label"><?php echo $entry_login; ?></label>
            <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_card_login" value="<?php if (isset($yandexplusplus_card_login)){ echo $yandexplusplus_card_login; }?>" />
              <br />
              <?php if ($error_login) { ?>
              <div class="text-danger"><?php echo $error_login; ?></div>
              <?php } ?></div>
          </div>
        <div class="form-group required">
        <label class="col-sm-2 control-label"><?php echo $entry_password; ?></label>
        <div class="col-sm-10"><input class="form-control" type="password" name="yandexplusplus_card_password" value="<?php if (isset($yandexplusplus_card_login)){ echo $yandexplusplus_card_password; }?>" />
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
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_name_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;" ><?php if ($yandexplusplus_card_name_attach) { ?>
            <input type="radio" name="yandexplusplus_card_name_attach" value="1" checked="checked" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_card_name_attach" value="0" />
            <?php echo $text_default; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_name_attach" value="1" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_card_name_attach" value="0" checked="checked" />
            <?php echo $text_default; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_name; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
            <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_card_name_<?php echo $language['language_id']; ?>" cols="50" rows="1"><?php echo isset(${'yandexplusplus_card_name_' . $language['language_id']}) ? ${'yandexplusplus_card_name_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_fixen ; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_fixen == 'proc') { ?>
            <input type="radio" name="yandexplusplus_card_fixen" value="fix" />
            <?php echo $entry_fixen_fix; ?>
            <input type="radio" name="yandexplusplus_card_fixen" value="proc" checked="checked" />
            <?php echo $entry_fixen_proc; ?>
            <input type="radio" name="yandexplusplus_card_fixen" value="0" />
            <?php echo $entry_fixen_order; ?>
            <?php } else if($yandexplusplus_card_fixen == 'fix'){ ?>
            <input type="radio" name="yandexplusplus_card_fixen" value="fix" checked="checked" />
            <?php echo $entry_fixen_fix; ?>
            <input type="radio" name="yandexplusplus_card_fixen" value="proc" />
            <?php echo $entry_fixen_proc; ?>
            <input type="radio" name="yandexplusplus_card_fixen" value="0" />
            <?php echo $entry_fixen_order; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_fixen" value="fix" />
            <?php echo $entry_fixen_fix; ?>
            <input type="radio" name="yandexplusplus_card_fixen" value="proc" />
            <?php echo $entry_fixen_proc; ?>
            <input type="radio" name="yandexplusplus_card_fixen" value="0" checked="checked" />
            <?php echo $entry_fixen_order; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_fixen_amount; ?></label>
          <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_card_fixen_amount" value="<?php echo isset($yandexplusplus_card_fixen_amount) ? $yandexplusplus_card_fixen_amount : ''; ?>" ><br />
          <?php if ($error_fixen) { ?>
          <div class="text-danger"><?php echo $error_fixen; ?></div>
          <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_komis; ?></label>
          <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_card_komis" value="<?php echo isset($yandexplusplus_card_komis) ? $yandexplusplus_card_komis : ''; ?>" >%</div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_maxpay; ?></label>
          <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_card_maxpay" value="<?php echo isset($yandexplusplus_card_maxpay) ? $yandexplusplus_card_maxpay : ''; ?>" >руб.</div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_style; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_style) { ?>
            <input type="radio" name="yandexplusplus_card_style" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_style" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_style" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_style" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_later; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_button_later) { ?>
            <input type="radio" name="yandexplusplus_card_button_later" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_button_later" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_button_later" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_button_later" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $text_createorder_or_notcreate; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_createorder_or_notcreate) { ?>
            <input type="radio" name="yandexplusplus_card_createorder_or_notcreate" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_createorder_or_notcreate" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_createorder_or_notcreate" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_createorder_or_notcreate" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_success_alert_admin_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_success_alert_admin) { ?>
            <input type="radio" name="yandexplusplus_card_success_alert_admin" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_success_alert_admin" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_success_alert_admin" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_success_alert_admin" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_success_alert_customer_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_success_alert_customer) { ?>
            <input type="radio" name="yandexplusplus_card_success_alert_customer" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_success_alert_customer" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_success_alert_customer" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_success_alert_customer" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_instruction_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_instruction_attach) { ?>
            <input type="radio" name="yandexplusplus_card_instruction_attach" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_instruction_attach" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_instruction_attach" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_instruction_attach" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_instruction; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_card_instruction_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_card_instruction_' . $language['language_id']}) ? ${'yandexplusplus_card_instruction_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_mail_instruction_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_mail_instruction_attach) { ?>
            <input type="radio" name="yandexplusplus_card_mail_instruction_attach" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_mail_instruction_attach" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_mail_instruction_attach" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_mail_instruction_attach" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_mail_instruction; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_card_mail_instruction_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_card_mail_instruction_' . $language['language_id']}) ? ${'yandexplusplus_card_mail_instruction_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_success_comment_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_success_comment_attach) { ?>
            <input type="radio" name="yandexplusplus_card_success_comment_attach" value="1" checked="checked" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_success_comment_attach" value="0" />
            <?php echo $text_no; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_success_comment_attach" value="1" />
            <?php echo $text_yes; ?>
            <input type="radio" name="yandexplusplus_card_success_comment_attach" value="0" checked="checked" />
            <?php echo $text_no; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_success_comment; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_card_success_comment_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_card_success_comment_' . $language['language_id']}) ? ${'yandexplusplus_card_success_comment_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_hrefpage_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_hrefpage_text_attach) { ?>
            <input type="radio" name="yandexplusplus_card_hrefpage_text_attach" value="1" checked="checked" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_card_hrefpage_text_attach" value="0" />
            <?php echo $text_default; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_hrefpage_text_attach" value="1" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_card_hrefpage_text_attach" value="0" checked="checked" />
            <?php echo $text_default; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_hrefpage; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_card_hrefpage_text_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_card_hrefpage_text_' . $language['language_id']}) ? ${'yandexplusplus_card_hrefpage_text_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_success_page_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_success_page_text_attach) { ?>
            <input type="radio" name="yandexplusplus_card_success_page_text_attach" value="1" checked="checked" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_card_success_page_text_attach" value="0" />
            <?php echo $text_default; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_success_page_text_attach" value="1" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_card_success_page_text_attach" value="0" checked="checked" />
            <?php echo $text_default; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_success_page_text; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_card_success_page_text_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_card_success_page_text_' . $language['language_id']}) ? ${'yandexplusplus_card_success_page_text_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
         <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_waiting_page_tab; ?></label>
          <div class="col-sm-10 control-label" style="text-align:left;"><?php if ($yandexplusplus_card_waiting_page_text_attach) { ?>
            <input type="radio" name="yandexplusplus_card_waiting_page_text_attach" value="1" checked="checked" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_card_waiting_page_text_attach" value="0" />
            <?php echo $text_default; ?>
            <?php } else { ?>
            <input type="radio" name="yandexplusplus_card_waiting_page_text_attach" value="1" />
            <?php echo $text_my; ?>
            <input type="radio" name="yandexplusplus_card_waiting_page_text_attach" value="0" checked="checked" />
            <?php echo $text_default; ?>
            <?php } ?></div>
        </div>
        <div class="form-group">
          <label class="col-sm-2 control-label"><?php echo $entry_yandexplusplus_card_waiting_page_text; ?></label>
          <div class="col-sm-10"><?php foreach ($languages as $language) { ?>
          <img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" style="vertical-align:top;"/> <textarea class="form-control" name="yandexplusplus_card_waiting_page_text_<?php echo $language['language_id']; ?>" cols="50" rows="3"><?php echo isset(${'yandexplusplus_card_waiting_page_text_' . $language['language_id']}) ? ${'yandexplusplus_card_waiting_page_text_' . $language['language_id']} : ''; ?></textarea><br /><?php } ?></div>
        </div>
        <div class="form-group">
        <label class="col-sm-2 control-label"><?php echo $entry_on_status; ?></label>
        <div class="col-sm-10"><select class="form-control" name="yandexplusplus_card_on_status_id">
            <?php foreach ($order_statuses as $order_status) { ?>
            <?php if ($order_status['order_status_id'] == $yandexplusplus_card_on_status_id) { ?>
            <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
            <?php } else { ?>
            <option value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
            <?php } ?>
            <?php } ?>
          </select></div>
        </div>
        <div class="form-group">
        <label class="col-sm-2 control-label"><?php echo $entry_order_status; ?></label>
        <div class="col-sm-10"><select class="form-control" name="yandexplusplus_card_order_status_id">
            <?php foreach ($order_statuses as $order_status) { ?>
            <?php if ($order_status['order_status_id'] == $yandexplusplus_card_order_status_id) { ?>
            <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
            <?php } else { ?>
            <option value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
            <?php } ?>
            <?php } ?>
          </select></div>
        </div>
        <div class="form-group">
        <label class="col-sm-2 control-label"><?php echo $entry_geo_zone; ?></label>
        <div class="col-sm-10"><select class="form-control" name="yandexplusplus_card_geo_zone_id">
            <option value="0"><?php echo $text_all_zones; ?></option>
            <?php foreach ($geo_zones as $geo_zone) { ?>
            <?php if ($geo_zone['geo_zone_id'] == $yandexplusplus_card_geo_zone_id) { ?>
            <option value="<?php echo $geo_zone['geo_zone_id']; ?>" selected="selected"><?php echo $geo_zone['name']; ?></option>
            <?php } else { ?>
            <option value="<?php echo $geo_zone['geo_zone_id']; ?>"><?php echo $geo_zone['name']; ?></option>
            <?php } ?>
            <?php } ?>
          </select></div>
        </div>
        <div class="form-group">
        <label class="col-sm-2 control-label"><?php echo $entry_status; ?></label>
        <div class="col-sm-10"><select class="form-control" name="yandexplusplus_card_status">
            <?php if ($yandexplusplus_card_status) { ?>
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
          <div class="col-sm-10"><input class="form-control" type="text" name="yandexplusplus_card_sort_order" value="<?php echo $yandexplusplus_card_sort_order; ?>" size="1" /></div>
        </div>
      </form>
    </div>
      <p style="text-align:center;">YandexPlusPlus Версия <?php echo $version ?></p>
  </div>
</div>
</div>
<?php echo $footer; ?> 