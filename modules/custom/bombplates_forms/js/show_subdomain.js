Drupal.behaviors.bombplatesFormsShowSubdomain = {
  attach: function (context, settings) {
    jQuery('#subdomain_field').hide();
    jQuery('#show_subdomain_desc').click( function() {
      jQuery('#subdomain_field').show();
      jQuery('#show_subdomain_desc').hide();
    });
    jQuery('#edit-domain').change( function() {
      var val = jQuery(this).val();
      var newVal = val.replace(/(https?:\/\/)?(www\.)?([^.]*)\..+/i, "$3");
      jQuery('#edit-subdomain').val(newVal);
      updateSubdomainDesc(newVal);
    });
    jQuery('#edit-subdomain').change( function() {
      var newVal = jQuery(this).val();
      updateSubdomainDesc(newVal);
    });
    function updateSubdomainDesc(val) {
      var desc = "In a few minutes, your site will be deployed at "
        + val
        + ".bombplates.com. You will be able to start customizing it there and uploading content while you get your domain configured";
      jQuery('#edit-subdomain--description').html(desc);
    }
  },
}
