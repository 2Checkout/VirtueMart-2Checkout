<br/>
<span id="tcoApiForm"
      data-pid="<?php echo $virtuemart_paymentmethod_id; ?>"
      data-seller="<?php echo $seller_id; ?>"
      data-default_style="<?php echo $default_style; ?>"
      data-style='<?php echo $style; ?>'
      data-order='<?php echo JRoute::_('index.php?option=com_virtuemart&view=cart&task=orderdone'); ?>'
>
    <input type="hidden" id="ess_token" name="ess_token" value="">
    <div id="tco_error"></div>
    <div id="card-element">
        <!-- A TCO IFRAME will be inserted here. -->
    </div>

</span>
