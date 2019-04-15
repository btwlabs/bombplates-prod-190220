<?php

/**
 * @file
 *  Contains \Drupal\authorize_net\Controller\AuthorizeNetController
 */

namespace Drupal\authorize_net\Controller;

use Drupal\Core\Controller\ControllerBase;

class AuthorizeNetController extends ControllerBase {

  /**
   * Accept an authorize.net callback and queue it up for processing
   */
  public function paymentCallback() {
    $record = [
      'post_received' => json_encode($_POST),
      'time_received' => \Drupal::time()->getRequestTime(),
    ];
    $queue = \Drupal::service('queue')->get('authorize_net');
    $item = (object)$record;
    $queue->createItem($item);

    $content = [
      'ok' => ['#markup' => '200 OK'], // Always just return 200 Ok
      '#cache' => ['max-age' => 0],    // Never cache
    ];
    $bombplates_nohtml_renderer = \Drupal::service('bombplates.renderer.bombplates_nohtml_renderer');
    return $bombplates_nohtml_renderer->renderNohtml($content, TRUE, FALSE);
  } // paymentCallback
} // AuthorizeNetController
