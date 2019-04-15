Drupal.behaviors.bombplatesFormsPreviewThumbs = {
  attach: function (context, settings) {
    jQuery('#select_template').change( function() {
      var theme_id = jQuery(this).val();
      jQuery('#bombplates_forms_template_preview').html(drupalSettings.bombplates_forms.designs[theme_id]);
    });
  } // attach
} // Drupal.behaviors.bombplatesFormsPreviewThumbs
