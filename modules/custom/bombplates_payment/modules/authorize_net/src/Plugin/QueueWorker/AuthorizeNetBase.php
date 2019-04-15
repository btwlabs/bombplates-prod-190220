<?php

/**
 * @file
 *  Contains Drupal\authorize_net\Plugin\QueueWorker\AuthorizeNetBase
 */

namespace Drupal\authorize_net\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides base functionality for AuthorizeNet queue workers.
 */
abstract class AuthorizeNetBase extends \Drupal\bombplates_payment\Plugin\QueueWorker\BombplatesPaymentBase implements ContainerFactoryPluginInterface {

  /**
   * Hold the Authorize.net config settings
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * POST data initially passed it
   *
   * @var array
   */
  protected $post_data;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->config = \Drupal::config('authorize_net.settings');
  } // __construct

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->post_data = json_decode($data->post_received, TRUE);
    $time_received = $data->time_received;
    if ($this->verify()) {
      $this->processPayment($time_received);
    } // if verify()
  } // processItem

  /**
   * Verify that POST data is correct and came from authorize.net
   *
   * @return boolean
   *    Was the data verifiable?
   */
  protected function verify() {
    $md5_hash_value = $this->config->get('authorize_net_md5_hash_value');
    $api_login = $this->config->get('authorize_net_api_login_id');
    $username = $this->config->get('authorize_net_account_name');
    $hash_received = strtolower($this->post_data['x_MD5_Hash']);
    $trans_id = $this->post_data['x_trans_id'];
    $amount = $this->post_data['x_amount'];
    $subscription_id = $this->post_data['x_subscription_id'];
    $hashes_expected = [
      strtolower(md5("$md5_hash_value$trans_id$amount")),
      strtolower(md5("$md5_hash_value$api_login$trans_id$amount")),
      strtolower(md5("$md5_hash_value$username$trans_id$amount")),
    ];
    $result = in_array($hash_received, $hashes_expected)    // ensure hash matches
      && $hash_received && $trans_id && $amount && $subscription_id; // ensure all data was sent
    return $result;
  } // verify

  /**
   * Process POST data for payment info and log the payment as appropriate
   *
   * @param int $time_received
   *    Timestamp
   */
  protected function processPayment($time_received) {
    $account = $this->findUser();
    if (!$account) {
      \Drupal::logger('authorize_net')->WARNING('Could not identify user from valid POST data! (@d)', ['@d' => print_r($this->post_data,TRUE)]);
    } // if ! account
    else {
      $title = $this->post_data['x_trans_id'];
      $options = [
        'payment_type' => 'authorize_net',
        'payment_time' => $time_received,
        'payment_title' => $title,
        'authorize_net' => ['subscription_id' => $this->post_data['x_subscription_id']],
      ];
      \Drupal::moduleHandler()->invokeAll('bombplates_process_account', ['paid', $account, $options]);
    } // if account
  } // processPayment

  /**
   * Find the user associated with a payment
   *
   * @return object
   *    A user object or FALSE on failure
   */
  protected function findUser() {
    // NOTE: priorities have reversed in order
    // We can't guarantee that migrate preserved UIDs so we trust whichever user has the subscription ID over the customer ID
    $uids = \Drupal::entityQuery('user')
      ->condition('field_arb_subscription_id', $this->post_data['x_subscription_id'], '=')
      ->execute();
    $uid = reset($uids);
    if (!$uid) {
      $uid = isset($this->post_data['x_cust_id']) ? $this->post_data['x_cust_id'] : FALSE;
    } // ! uid
    $result = $uid ? \Drupal\user\Entity\User::load($uid) : FALSE;
    return $result;
  } // findUser
} // AuthorizeNetBase
