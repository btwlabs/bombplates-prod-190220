<?php

/**
 * @file
 *  Theme and preprocess functions for bombplates_forms module
 */

use Drupal\bombplates_forms\Inc as BombplatesForms;

/**
 * Template preprocess function for bombplates_forms_launch_page
 *
 * @param &$variables array
 *  Per drupal theme api
 */
function template_preprocess_bombplates_forms_launch_page(&$variables) {
  $variables['#attached']['library'][] = 'bombplates_forms/launch-page';
  $variables['#attached']['drupalSettings']['bombplates_forms']['subdomain'] = $variables['subdomain'];
  $variables['#attached']['drupalSettings']['bombplates_forms']['sso_url'] = $variables['sso_url'];
  $variables['svg'] = file_url_transform_relative(file_create_url(drupal_get_path('module',  'bombplates_forms') . '/files/onecolor.svg'));
} // template_preprocess_bombplates_forms_launch_page
