<?php

/**
 * @file
 *  Theme and preprocess functions for bombplates module
 */

/**
 * Template preprocess bombplates_mail_header
 *
 * @param &$variables array
 *  Per drupal theme API
 */
function template_preprocess_bombplates_mail_header(&$variables) {
  $variables['img_path'] = \Drupal\Core\Url::fromUri('internal:/' . drupal_get_path('module', 'bombplates') . '/files', ['absolute' => TRUE])
    ->toString();
} // template_preprocess_bombplates_mail_header

/**
 * Template preprocess bombplates_mail_footer
 *
 * @param &$variables array
 *  Per drupal theme API
 */
function template_preprocess_bombplates_mail_footer(&$variables) {
  $variables['img_path'] = \Drupal\Core\Url::fromUri('internal:/' . drupal_get_path('module', 'bombplates') . '/files', ['absolute' => TRUE])
    ->toString();
  $variables['year'] = date('Y');
} // template_preprocess_bombplates_mail_footer

/**
 * Template preprocess bombplates_mail_body
 *
 * @param &$variables array
 *  Per drupal theme API
 */
function template_preprocess_bombplates_mail_body(&$variables) {
  $paragraphs = [];
  foreach ($variables['body'] AS $paragraph) {
    if (empty($paragraph)) {
      $paragraphs[] = [
        '#markup' => '<br/>',
      ];
    }
    else {
      $paragraphs[] = [
        '#prefix' => '<p>',
        '#markup' => $paragraph,
        '#suffix' => '</p>',
      ];
    }
  } // foreach paragraph in variables[body]
  $variables['paragraphs'] = $paragraphs;
} // template_preprocess_bombplates_mail_body */
