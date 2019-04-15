Drupal.behaviors.bombplatesFormsLaunchPage = {
  attach: function (context, settings) {
    //var base_url = 'https://' + drupalSettings.bombplates_forms.subdomain + '.bombplates.com';
    jQuery('#progress_top').addClass('active');
    //setTimeout("location.href = '" + base_url + "/user'", 30000);
    setTimeout("location.href = '" + drupalSettings.bombplates_forms.sso_url + "'", 30000);
  } // attach
} // Drupal.behaviors.bombplatesFormsLaunchPage
