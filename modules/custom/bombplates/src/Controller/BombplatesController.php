<?php

/**
 * @file
 *  Contains \Drupal\bombplates\Controller\BombplatesController
 */

namespace Drupal\bombplates\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for Bombplates main module
 */
class BombplatesController extends ControllerBase {
  /**
   * Returns the main management page
   *
   * @return array
   *    Render array
   */
  public function manage() {
    $account = \Drupal::currentUser();
    $result = \Drupal::moduleHandler()->invokeAll('bombplates_admin_links', [$account]);
    return $result;
  } // manage
} // \Drupal\bombplates\Controller\BombplatesController
