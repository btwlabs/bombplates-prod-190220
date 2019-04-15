<?php

/**
 * @file
 *   Contains \Drupal\bombplates\PathProcessor\SslPathProcessor
 */

namespace Drupal\bombplates\PathProcessor;

use \Drupal\Core\PathProcessor\OutboundPathProcessorInterface;

/**
 *
 */
class SslPathProcessor implements OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  function processOutbound ($path, &$options = [], \Symfony\Component\HttpFoundation\Request $request = NULL, \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata = NULL) {
    $options['https'] = TRUE;
    return $path;
  } // processOutbound

} // SslPathProcessor
