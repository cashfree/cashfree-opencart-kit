<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a onclick="location = '<?php echo $cancel; ?>';" class="btn btn-default" data-toggle="tooltip"><?php echo $button_cancel; ?></a> 
      </div>
      <h1><?php echo $heading_title; ?></h1>

    <ul class="breadcrumb">
      <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
      <?php } ?>
     </ul>
  </div>
 </div>
 <?php if ($error_warning) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
 <?php } ?>
 
 <div class="container-fluid">
   <div class="panel panel-default">
     <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-pencil"></i><?php echo $text_edit;?></h3>
     </div>

     <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-cashfree" class="form-horizontal">    
        <div class="form-group required">
        <label class="col-sm-2 control-label" for="cashfree_api_url"><?php echo $entry_api_url; ?></label>
        <div class="col-sm-10">
          <input type="text" name="cashfree_api_url" value="<?php echo $cashfree_api_url; ?>" placeholder="Enter cashfree api url" id="cashfree_api_url" class="form-control" />            
          <?php if ($error_api_url) { ?>
            <span class="text-danger"><?php echo $error_api_url; ?></span>
          <?php } ?>
        </div>
        </div>

        <div class="form-group required">
        <label class="col-sm-2 control-label" for="cashfree_api_url"><?php echo $entry_app_id; ?></label>
        <div class="col-sm-10">
          <input type="text" name="cashfree_app_id" value="<?php echo $cashfree_app_id; ?>" placeholder="Enter cashfree app id" id="cashfree_app_id" class="form-control" />            
          <?php if ($error_app_id) { ?>
            <span class="text-danger"><?php echo $error_app_id; ?></span>
          <?php } ?>
        </div>
        </div>

        <div class="form-group required">
        <label class="col-sm-2 control-label" for="cashfree_secret_key"><?php echo $entry_secret_key; ?></label>
        <div class="col-sm-10">
          <input type="text" name="cashfree_secret_key" value="<?php echo $cashfree_secret_key; ?>" placeholder="Enter cashfree secret key" id="cashfree_secret_key" class="form-control" />            
          <?php if ($error_secret_key) { ?>
            <span class="text-danger"><?php echo $error_secret_key; ?></span>
          <?php } ?>
        </div>
        </div>

        <div class="form-group">
           <label class="col-sm-2 control-label" for="cashfree_order_status_id"><?php echo $entry_order_status; ?></label>
           <div class="col-sm-10">
             <select name="cashfree_order_status_id" id="cashfree_order_status_id" class="form-control">
             <?php foreach ($order_statuses as $order_status) { ?>
             <?php if ($order_status['order_status_id'] == $cashfree_order_status_id) { ?>
               <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
             <?php } else { ?>
               <option value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
             <?php } ?>
             <?php } ?>
             </select>
        </div>
      </div>
      
       <div class="form-group">
        <label class="col-sm-2 control-label" for="cashfree_status"><?php echo $entry_status; ?></label>
        <div class="col-sm-10">
          <select name="cashfree_status" id="cashfree_status" class="form-control">
          <?php if ($cashfree_status) { ?>
          <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
          <option value="0"><?php echo $text_disabled; ?></option>
          <?php } else { ?>
           <option value="1"><?php echo $text_enabled; ?></option>
           <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
          <?php } ?>
          </select>
        </div>
       </div>    
        
       <div class="form-group">
       <label class="col-sm-2 control-label" for="cashfree_secret_key"><?php echo $entry_sort_order; ?></label>
         <div class="col-sm-10">
           <input type="text" name="cashfree_sort_order" value="<?php echo $cashfree_sort_order; ?>" placeholder="Enter sort order key" id="cashfree_sort_order" class="form-control" />            
         </div>
      </div>        
      </form>
    </div>
  </div>
</div>
</div>
<?php echo $footer; ?>