<?php

/**
 * @file
 *  Contains \Drupal\bombplates_migrate\Plugin\migrate\source\BpFunc
 */

namespace Drupal\bombplates_migrate\Plugin\migrate\misc;

/**
 * Miscellaneous helper functions for migration
 */
class BpFunc {

  /**
   * Try to convert data that may be serialized or json to an array
   *
   * @param string $input
   *    Data encoded in an arbitrary format
   * @return mixed
   *    The decoded data or FALSE on failure. Arrays are preferred
   */
  public static function decode($input) {
    $result = FALSE;
    if ($json = json_decode($input, TRUE)) {
      $result = $json;
    }
    elseif ($serial = @unserialize($input, ['allowed_classes' => FALSE])) {
      $result = $serial;
    }
    return $result;
  } // decode

} // BpFunc
