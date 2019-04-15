<?php

/**
 * @file
 *  Contains Drupal\bombplates_payment\Inc\MiscFunc
 */

namespace Drupal\bombplates_payment\Inc;

/**
 *  Miscellaneous helper public static functions
 */
class MiscFunc {
  /**
   * Log a payment
   *
   * @param string $title
   *  Description of the payment
   * @param object $account
   *  user object
   * @param array $values
   *  Other data passed in - may be derived from FormState::getValues()
   * @return object
   *  a bombplates_payment node
   */
  public static function logPayment($title, $account, $values = []) {
    $node = \Drupal\node\Entity\Node::create([
      'type' => 'bombplates_payment',
      'title' => $title,
      'uid' => $account->id(),
      'created' => isset($values['payment_time']) ? $values['payment_time'] : \Drupal::time()->getRequestTime(),
      'changed' => \Drupal::time()->getRequestTime(),
      'status' => NODE_NOT_PUBLISHED,
      'promote' => NODE_NOT_PROMOTED,
      'sticky' => NODE_NOT_STICKY,
    ]);
    $node->field_payment_site->value = $account->field_subdomain->value . '.bombplates.com';
    $node->field_payment_name->value = $account->getAccountName();
    if (!isset($values['payment_type'])) {
      $values['payment_type'] = 'unspecified';
    }
    $args = ['account' => $account, 'values' => $values];
    \Drupal::moduleHandler()->alter('bombplates_payment', $node, $args);
    $node->save();
    return $node;
  } // logPayment

  /**
   * Check if a user is a customer yet
   *
   * @param object - user object $account
   * @return bool - is the user flagged as a customer?
   */
  public static function isCustomer($account) {
    return $account->hasRole('customer');
  } // isCustomer
} // MiscFunc
