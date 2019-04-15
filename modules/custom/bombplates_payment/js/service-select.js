Drupal.behaviors.bombplatesPaymentSelectService = {
  attach: function (context, settings) {
    jQuery('#bombplates_payment_service_select').click( function () {
      jQuery(".bombplates-payment-settings").hide(); jQuery("#" + jQuery(this).val() + "_bombplates_payment_settings").show();
    });
    jQuery('#bombplates_payment_service_select').click();
  },
}
