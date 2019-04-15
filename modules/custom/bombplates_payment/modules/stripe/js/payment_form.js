Drupal.behaviors.stripePaymentForm = {
  attach: function (context, settings) {
    var stripe = Stripe(drupalSettings.stripe.pub_key);
    var elements = stripe.elements();
    var card = elements.create('card');
    card.mount('#card-element');

    jQuery('.bombplates-payment-form').submit(function(event) {
      var form = jQuery(this);
      var result = true;
      var is_processed = form.attr('stripe-processed');
      if (is_processed != true && is_processed != 'true') {
        // Disable the submit button to prevent repeated clicks
        form.find('[data-drupal-selector="edit-submit"]').attr('disabled', true);
        //Stripe.card.createToken(form, stripeResponseHandler);
        stripe.createToken(card).then(stripeResponseHandler);
        // Prevent the form from submitting with the default action
        result = false;
      } // !is_processed
      return result;
    }); // .bombplates-payment-form.submit
    jQuery('[data-drupal-selector="edit-stripe-plan"]').change(function() {
      updateCostFields(drupalSettings.stripe.plans[jQuery(this).val()].cost, drupalSettings.stripe.plans[jQuery(this).val()].time);
    });

    updateCostFields(drupalSettings.stripe.default_cost, drupalSettings.stripe.default_time);
    function stripeResponseHandler(response) {
      var form = jQuery('.bombplates-payment-form').first();
      if (response.error) {
        var err_msg ='<div class="messages error"><ul><li>' + response.error.message + '</li></ul></div>';
        form.prepend(err_msg);
        form.find('[data-drupal-selector="edit-submit"]').attr('disabled', false);
      } else {
        var token = response.token.id;
        jQuery('[data-drupal-selector="edit-stripe-token"]').val(token);
        form.attr('stripe-processed', true);
        form.submit();
      }
    } // stripeResponseHandler
    function updateCostFields(new_cost, new_time) {
      jQuery('.bpp-price').each(function () { jQuery(this).html(new_cost); });
      jQuery('.bpp-time').each(function () { jQuery(this).html(new_time); });
      jQuery('.bpp-missed').each(function () {
        payment = Math.round(100*(new_cost / new_time * drupalSettings.stripe.missed_payments )) / 100;
        jQuery(this).html(payment);
      });
    } // updateCostFields
  }, // attach
} // Drupal.behaviors.stripePaymentForm
