<?php

/**
 * @file
 *  Contains Drupal\bombplates_migrate\Plugin\migrate\process\BpStripeCustomer
 */

namespace Drupal\bombplates_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Extract the subscription ID from the customer id field of a stripe_subscription node
 *
 * Possible input values can be either a flat string or a nested array
 * containing either the flat value or serialized data returned from the API
 *
 * @MigrateProcessPlugin(
 *   id = "bombplates_stripe_customer_process",
 * )
 */
class BpStripeCustomer extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_array($value)) {
      if (isset($value[0]['value'])) {
        $value = $value[0]['value'];
      }
      elseif (isset($value['value'])) {
        $value = $value['value'];
      }
      else {
        throw new MigrateException('Input must be either a flat value or an array with the key "value"' . var_export($value,TRUE));
      } // if missing value
    } // if array
    if (substr($value, 0, 26) == 'Stripe_Subscription JSON: ') {
      $value = substr($value, 26);
    }
    if ($decoded = \Drupal\bombplates_migrate\Plugin\migrate\misc\BpFunc::decode($value)) {
      $result = $decoded['id'];
    }
    else {
      $result = $value;
    }
    return $result;
  } // transform

} // BpStripeCustomer
