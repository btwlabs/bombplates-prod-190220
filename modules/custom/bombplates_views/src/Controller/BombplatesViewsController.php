<?php

/**
 * @file
 *  Contains \Drupal\bombplates_views\Controller\BombplatesViewsController
 */

namespace Drupal\bombplates_views\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for bombplates_views routes
 */
class BombplatesViewsController extends ControllerBase {

  /**
   * Embed an iframe of the demo site
   *
   * @param int $theme
   *    A Theme ID
   * @return array
   *    Render array
   */
  public function preview($theme_id) {
    $result = [];
    $match = \Drupal::entityQuery('node')
      ->condition('type', 'bombplate', '=')
      ->condition('field_theme_id', $theme_id, '=')
      ->count()
      ->execute();
    if ($match) {
      $result = [
        '#theme' => 'bombplates_views_preview',
        '#template_id' => $theme_id,
      ];
    } // if match
    else {
      $result['error'] = [
        '#type' => 'markup',
        '#markup' => t('Invalid theme ID specified'),
      ];
    } // if !match
    return $result;
  } // preview

} // BombplatesViewsController
