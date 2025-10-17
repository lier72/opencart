<?php echo $header; ?>
<?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <div class="panel panel-default"> 
      <div class="panel-body">
      <table class="table table-striped">
      <thead>
        <tr>
            <th>ID</th>
            <th>Номер заказа</th>
            <th>Сумма</th>
            <th>Пользователь</th>
            <th>email</th>
            <th>Дата создания</th>
            <th>Дата оплаты</th>
            <th>Идентификатор операции</th>
        </tr>
        </thead>
        <tbody>
        <?php
        foreach ($viewstatuses as $viewstatus){   ?>
          <tr>
          <td><?php echo $viewstatus['yandex_id']?></td>
          <td><?php echo $viewstatus['num_order']?></td>
          <td><?php echo $viewstatus['sum']?></td>
          <td><?php echo $viewstatus['user']?></td>
          <td><?php echo $viewstatus['email']?></td>
          <td><?php echo $viewstatus['date_created']?></td>
          <td><?php echo $viewstatus['date_enroled']?></td>
          <td><?php echo $viewstatus['sender']?></td>
          </tr>
        <?php }
        ?>
        </tbody>
        </table>

  </div>
</div>
</div>
</div>
<?php echo $footer; ?> 

