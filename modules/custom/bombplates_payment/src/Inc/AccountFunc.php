<?php

/**
 * @file
 *  Contains Drupal\bombplates_payment\Inc\AccountFunc
 */

namespace Drupal\bombplates_payment\Inc;

use Drupal\bombplates\Inc as Bombplates;
use Drupal\bombplates_payment\Inc as BombplatesPayment;

/**
 *  public static functions to do the processing for bombplates_payment_bombplates_process_account
 */
class AccountFunc {

  /**
   * Helper public static function for hook_bombplates_process_account Flag a user's status as suspended
   *
   * @param object - user object $account
   * @param int - number of payments the user has missed $missed_payments
   * @param date bool - should the date of the user's next scheduled payment be bumped up a month? $increment_pay
   */
  public static function suspendAccount($account, $missed_payments = 0, $increment_pay_date = FALSE) {
    $last_payment = $account->field_next_payment->value;
    if (!$last_payment) { $last_payment = date('c', \Drupal::time()->getRequestTime()); }
    $next_payment_date = $increment_pay_date ? new \DateTime("$last_payment + 1 month") : new \DateTime($last_payment);
    // If they're more than a month behind, give them an extra 10 days before deleting the site
    $next_payment = $next_payment_date->format('U') > \Drupal::time()->getRequestTime()
      ? $next_payment_date->format('Y-m-d\TH:i:s')
      : date('Y-m-d\TH:i:s', \Drupal::time()->getRequestTime() + 864000); // 60*60*24*10 = 10 days in seconds
    $next_payment = preg_replace('/[-+][0-9:]*$/', '', $next_payment);
    $missed_payments += (int)($account->field_missed_payments->value);
    $account->set('field_next_payment', $next_payment)
      ->set('field_missed_payments', $missed_payments)
      ->set('field_suspended', 1)
      ->save();
  } // suspendAccount

  /**
   * Helper public static function for hook_bombplates_process_account. Flag a user's status as unsuspended
   *
   * @param object $account
   *  a user object
   * @param int $log_payments
   *  Number of payments to log as if they were paid
   * @param bool $forgive_payments
   *  number of payments to be discarded as unrecoverable
   * @param array $values
   *  Usually per FormState::getValues and/or FormState::getStorage
   */
  public static function unsuspendAccount($account, $log_payments = 0, $forgive_payments = 0, $values = []) {
    $account->set('field_suspended', 0);
    if ($log_payments) {
      $missed_payments = $account->field_missed_payments->value;
      $account->set('field_missed_payments', 0);
      if (isset($values['bombplates_payment_module'])) {
        $title = t('Make-up @t payment', ['@t' => $values['bombplates_payment_module']]);
      }
      else {
        $title = t('Make-up payment');
      }
      for ($i = 0; $i < $missed_payments; $i++) {
        BombplatesPayment\MiscFunc::logPayment($title, $account, $values);
      }
    } // if log_payments
    elseif ($forgive_payments) {
      $account->set('field_missed_payments', 0);
    } // if forgive_payments
    $account->save();
  } // unsuspendAccount

  /**
   * Update a user's payment status to reflect that their payment has been received
   *
   * @param object $account
   *  user object
   * @param array $options
   *  Other options passed in by whatever module invoked this
   */
  public static function paidAccount($account, $options = []) {
    $payment_time = isset($options['payment_time']) ?  (int)$options['payment_time'] : \Drupal::time()->getRequestTime();
    $payment_title = isset($options['payment_title']) ? (string)$options['payment_title'] : t('Payment');
    $count = isset($options['count']) ? (int)$options['count'] : 1;

    $next_payment = new \DateTime(date('c', $payment_time) . " + $count month + 2 days");
    $account->set('field_last_payment', $payment_time)
      ->set('field_next_payment', $next_payment->format('Y-m-j\TH:i:s'))
      ->save();
    for ($i = 0; $i < $count; $i++) {
      Bombplates\MiscFunc::queueProcess(
        ['action' => 'paid', 'account' => $account, 'options' => ['title' => $payment_title, 'values' => $options]],
        'bombplates_payment'
      );
    } // for i = 0..count
  } // paidAccount
} // AccountFunc
