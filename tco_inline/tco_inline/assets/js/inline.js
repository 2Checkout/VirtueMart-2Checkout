/**
 *
 * Verifone inline payment plugin
 *
 * @package VirtueMart
 * @subpackage payment
 * Copyright (C) 2004 - 2014 Virtuemart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */

var tcoCardElementLoaded = false;

jQuery(document).ajaxComplete(function (event, jqxhr, settings) {
    jQuery('#checkoutFormSubmit').bind();
    if (settings.url.indexOf('/cart?tmpl=component') >= 0) {
        var paymentId = jQuery('#tcoInlineForm').data('pid'),
            selected = jQuery('input[name="virtuemart_paymentmethod_id"]:checked').val();
        if (parseInt(paymentId) === parseInt(selected)) {
            prepareInline();
        }
    }
});

window.addEventListener('load', function () {
    var paymentId = jQuery('#tcoInlineForm').data('pid'),
        selected = jQuery('input[name="virtuemart_paymentmethod_id"]:checked').val();

    if (parseInt(paymentId) === parseInt(selected)) {
        jQuery('#checkoutFormSubmit').unbind();
        prepareInline();
    }

    jQuery('body').on('change', 'input[name="virtuemart_paymentmethod_id"]', function (e) {
        e.preventDefault();
        tcoCardElementLoaded = false;
        var selected = jQuery(this).val(),
            paymentId = jQuery('#tcoApiForm').data('pid');

        jQuery('#checkoutFormSubmit').bind();
        if (parseInt(paymentId) === parseInt(selected)) {
            jQuery('#checkoutFormSubmit').unbind();
            prepareInline();
        }

    });
});

function prepareInline() {
    if (jQuery('#checkoutFormSubmit').attr('name') === 'confirm') {
        jQuery('#checkoutFormSubmit').unbind();
    }
    var seller_id = jQuery('#tcoInlineForm').data('seller'),
        order_done_url = jQuery('#tcoApiForm').data('order');

    jQuery("#checkoutFormSubmit").one("click", function (event) {
        event.preventDefault();
        if (jQuery(this).attr('name') === 'confirm') {
            jQuery('#checkoutFormSubmit').vm2front('startVmLoading');
            jQuery('#checkoutForm').append('<input name="confirm" value="1" type="hidden">');

            var form = jQuery('#checkoutForm');
            jQuery.ajax({
                type: 'POST',
                url: order_done_url,
                data: form.serialize()
            }).done(function (response) {
                var payload = JSON.parse(response);
                (function (document, src, libName, config) {
                    var script = document.createElement('script');
                    script.src = src;
                    script.async = true;
                    var firstScriptElement = document.getElementsByTagName('script')[0];
                    script.onload = function () {
                        TwoCoInlineCart.setup.setMerchant(payload['merchant']);
                        TwoCoInlineCart.setup.setMode(payload.mode);
                        TwoCoInlineCart.register();

                        TwoCoInlineCart.cart.setCurrency(payload['currency']);
                        TwoCoInlineCart.cart.setLanguage(payload['language']);
                        TwoCoInlineCart.cart.setReturnMethod(payload['return-method']);
                        TwoCoInlineCart.cart.setTest(payload['test']);
                        TwoCoInlineCart.cart.setOrderExternalRef(payload['order-ext-ref']);
                        TwoCoInlineCart.cart.setExternalCustomerReference(payload['customer-ext-ref']);
                        TwoCoInlineCart.cart.setSource(payload['src']);

                        TwoCoInlineCart.products.removeAll();
                        TwoCoInlineCart.products.addMany(payload['products']);
                        TwoCoInlineCart.billing.setData(payload['billing_address']);
                        TwoCoInlineCart.billing.setCompanyName(payload['billing_address']['company-name']);
                        TwoCoInlineCart.shipping.setData(payload['shipping_address']);
                        TwoCoInlineCart.cart.setSignature(payload['signature']);
                        TwoCoInlineCart.cart.setAutoAdvance(true);

                        if(typeof Virtuemart.stopVmLoading === 'function') {
                            Virtuemart.stopVmLoading();
                        } else {
                            jQuery( ".vmLoadingDiv" ).hide();
                        }

                        TwoCoInlineCart.cart.checkout();

                    };
                    firstScriptElement.parentNode.insertBefore(script, firstScriptElement);
                })(document, 'https://secure.2checkout.com/checkout/client/twoCoInlineCart.js', 'TwoCoInlineCart',
                    {"app": {"merchant": payload.merchant}, "cart": {"host": "https:\/\/secure.2checkout.com"}}
                );

                jQuery("input[name='confirm']").remove();
                prepareInline();
            })
                .error(function (res) {
                    if(typeof Virtuemart.stopVmLoading === 'function') {
                        Virtuemart.stopVmLoading();
                    } else {
                        jQuery( ".vmLoadingDiv" ).hide();
                    }
                    console.log('Ajax error!');
                    return false;
                });
        }
        return true;
    });

}
