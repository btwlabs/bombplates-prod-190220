<?php

/**
 * @file
 *  Contains Drupal\authorize_net\Inc\CancelFunc
 */

namespace Drupal\authorize_net\Inc;

/*
 *  Helper public static functions to cancel accounts
 */
class CancelFunc {
  /**
   * Delete a user's Authorize.net ARB subscription
   *
   * @param object $account
   *  A user account
   * @return boolean
   *  Was the process successful?
   */
  public static function subscription($account) {
    $result = FALSE;
    $subscription_id = $account->field_arb_subscription_id->value;
    if ($subscription_id) {
      $details = ['subscriptionId' => $subscription_id];
      $xml_req = self::build($details);
      $tree = ConnectFunc::sendRequest($xml_req, 'cancel');
      $error_msg = self::process($tree);
      $result = !$error_msg;
      if ($error_msg) { \Drupal::logger('authorize_net')->ERROR($error_msg); }
      $account->set('field_arb_subscription_id', '')->save();
    } // if subscription_id
    return $result;
  } // subscription

  /**
   * Build an authorize.net subscription cancellation request
   *
   * @param array $details
   *  per authorize_net_payment_build_data
   * @return string
   *  xml similar to below
   *  <?xml version="1.0" encoding="UTF-8"?>
   *  <ARBCancelSubscriptionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
   *    <merchantAuthentication>
   *      <name>acctName</name>
   *      <transactionKey>112233445</transactionKey>
   *    </merchantAuthentication>
   *    <refId>.{1,20}</refId> //optional. Our subscription id
   *    <subscriptionId>\d{1,13}</subscription> //auth.net's id
   */
  protected static function build($details) {
    $config = \Drupal::config('authorize_net.settings');
    //build request
    //app authorization
    $full_details = [
      'ARBCancelSubscriptionRequest' => [
        'merchantAuthentication' => [
          'name' => $config->get('authorize_net_api_login_id'),
          'transactionKey' => $config->get('authorize_net_transaction_key'),
        ],
        'subscriptionId' => $details['subscriptionId'],
      ],
    ];
    $root_attributes = 'xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd"';
    $xml_req = ConnectFunc::buildXml($full_details, $root_attributes);
    return $xml_req;
  } // build

  /**
   * Process the results of an authorize.net arb subscription cancellation request
   *
   * @param array $tree
   *  per authorize_net_arb_send_arb_request
   * @return string
   *  optional error message
   */
  protected static function process($tree) {
    //process results
    $error_msg = '';
    $result_code = $tree['ARBCancelSubscriptionResponse']['messages']['resultCode'];
    if ($result_code != 'Ok') {
      $error_msg = "Cancellation failed ($result_code):<br/>\n";
      $err_tree = isset($tree['ARBCancelSubscriptionResponse']) ? $tree['ARBCancelSubscriptionResponse'] : $tree['ErrorResponse'];
      //message can either be an array of [code, text] combinations...
      if (!$err_tree['messages']['message']['code']) {
        foreach ($err_tree['messages']['message'] as $msg) {
          $error_msg .= $msg['code'].' : '.$msg['text']."<br/>\n";
        }
      } // if !err_tree[messages][message][code]
      else {
        //... or a single one
        $error_msg .= $err_tree['messages']['message']['code'] . ' - ' . $err_tree['messages']['message']['text'] . "<br/>\n";
      } // if err_tree[messages][message][code]
    } // result_code != ok
    return $error_msg;
  } // process
} // CancelFunc
