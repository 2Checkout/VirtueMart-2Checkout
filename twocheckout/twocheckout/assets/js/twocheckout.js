jQuery(document).ajaxComplete(function (event, jqxhr, settings) {
    jQuery('#checkoutFormSubmit').bind();

    if (settings.url.indexOf('/cart?tmpl=component') >= 0) {
        let paymentId = jQuery('#tcoApiForm').data('pid'),
            selected = jQuery('input[name="virtuemart_paymentmethod_id"]:checked').val();

        if (parseInt(paymentId) === parseInt(selected)) {
            prepareTwoPayJs();
        }
    }
});

window.addEventListener('load', function () {

    let paymentId = jQuery('#tcoApiForm').data('pid'),
        selected = jQuery('input[name="virtuemart_paymentmethod_id"]:checked').val();

    if (parseInt(paymentId) === parseInt(selected)) {
        prepareTwoPayJs();
    }
    jQuery('body').on('change', 'input[name="virtuemart_paymentmethod_id"]', function (e) {
        e.preventDefault();
        let selected = jQuery(this).val(),
            paymentId = jQuery('#tcoApiForm').data('pid');

        jQuery('#checkoutFormSubmit').bind();
        if (parseInt(paymentId) === parseInt(selected)) {
            prepareTwoPayJs();
        }
    });
});

function prepareTwoPayJs() {
    if (jQuery('#checkoutFormSubmit').attr('name') === 'confirm') {
        jQuery('#checkoutFormSubmit').unbind();
    }
    jQuery('#card-element').html('');
    if (!jQuery('#two-co-iframe').length) {
        let sellerId = jQuery('#tcoApiForm').data('seller'),
            defaultStyle = jQuery('#tcoApiForm').data('default_style'),
            jsPaymentClient = new TwoPayClient(sellerId.toString()),
            style = jQuery('#tcoApiForm').data('style'),
            orderDoneUrl = jQuery('#tcoApiForm').data('order'),
            component = (parseInt(defaultStyle) === 1) ?
                jsPaymentClient.components.create('card') :
                jsPaymentClient.components.create('card', style);

        component.mount('#card-element');
        jQuery('body').on('click', '#checkoutFormSubmit', function (event) {

            event.preventDefault();
            if (jQuery(this).attr('name') === 'confirm') {
                jQuery('.tco-error').remove();
                startLoading();
                jQuery('#checkoutForm').append('<input name="confirm" value="1" type="hidden">');
                let customer = jQuery('span.values.vm2-name').html();
                if (!customer) {
                    customer = jQuery('span.values.vm2-first_name').html() + ' ' + jQuery('span.values.vm2-last_name').html();
                }
                jsPaymentClient.tokens.generate(component, {name: customer}).then(function (response) {

                    let form = jQuery('#checkoutForm');
                    jQuery('#ess_token').val(response.token);
                    jQuery.ajax({
                        type: 'POST',
                        url: orderDoneUrl,
                        data: form.serialize()
                    }).done(function (response) {
                        let result = JSON.parse(response);
                        if (result.status && result.redirect) {
                            window.location.href = result.redirect;
                        } else {
                            if(typeof Virtuemart.stopVmLoading === 'function') {
                                Virtuemart.stopVmLoading();
                            } else {
                                jQuery( ".vmLoadingDiv" ).hide();
                            }
                            jQuery('#tcoApiForm').prepend('<div class="tco-error">' + result.messages + '</div>');
                        }
                    });
                }).catch(function (error) {
                    if (error.toString() !== 'Error: Target window is closed') {
                        jQuery('#tcoApiForm').prepend('<div class="tco-error">' + error + '</div>');
                        console.error(error);
                        if(typeof Virtuemart.stopVmLoading === 'function') {
                            Virtuemart.stopVmLoading();
                        } else {
                            jQuery( ".vmLoadingDiv" ).hide();
                        }                    }
                });
            }
        });
    }
    return true;
}

function startLoading() {
    jQuery('body').addClass('vmLoading');
    if (!jQuery('div.vmLoadingDiv').length) {
        jQuery('body').append('<div class="vmLoadingDiv"><div class="vmLoadingDivMsg"></div></div>');
    }
}
