<?php

/**
 * @file
 *  Contains \Drupal\bombplates_payment\Inc\CronFunc
 */

namespace Drupal\bombplates_payment\Inc;

use Drupal\bombplates\Inc as Bombplates;

/**
 *  Cron public static functionality
 */
class CronFunc {

  /**
   * Find accounts with out-of-band payments due and mark them as paid in the database
   */
  public static function outOfBandPayments() {
    $accounts = self::findOutOfBand();
    if (count($accounts) > 0) {
      $body = ['Payments are being automatically logged for the following out-of-band accounts:'];
      foreach ($accounts AS $account) {
        $body[] = $account->getAccountName();
        $options = [
          'payment_time' => \Drupal::time()->getRequestTime(),
          'payment_title' => t('Out-of-band payment'),
          'count' => 1,
        ];
        Bombplates\MiscFunc::queueProcess(
          ['action' => 'paid', 'account' => $account, 'options' => $options],
          'bombplates_process',
          TRUE
        );
      } // foreach account in accounts
      $body[] = 'Please verify that these users have, in fact, paid their hosting this month.';
      Bombplates\MiscFunc::queueMail([
        'to' => \Drupal::config('bombplates_payment.settings')->get('billing_mail'),
        'subject' => t('Out-of-band payments for Bombplates hosting are being logged'),
        'body' => Bombplates\MiscFunc::buildMailBody($body),
      ]);
    } // count(accounts) > 0
  } // outOfBandPayments

  /**
   * Warn delinquent accounts of their impending deletion
   */
  public static function warnDelinquents() {
    $accounts = self::fetchCurrentDelinquents(7);
    $config = \Drupal::config('bombplates_payment.settings');
    $base_fee = $config->get('base_fee');
    $billing = $config->get('billing_mail');
    foreach ($accounts AS $account) {
      $missed = $account->field_missed_payments->value;
      $balance = $missed * $base_fee;
      $t_opt = ['langcode' => $account->getPreferredLangcode()];
      $body = [
        ['Our records show that your last @m payments for your Bombplates subscription have not gone through.', ['@m' => $missed]],
        ['To prevent your site from being taken down, please log in to bombplates.com to update your billing information and pay your outstanding balance of @b. This is your last warning before deletion.', ['@b' => $balance]],
      ];
      Bombplates\MiscFunc::queueMail([
        'to' => $account->getEmail(),
        'from' => $billing,
        'subject' => t('Warning! Your Bombplates site may be cancelled soon!', [], $t_opt),
        'body' => Bombplates\MiscFunc::buildMailBody($body, $account),
      ]);
    } // foreach account in accounts
  } // warnDelinquents

  /**
   * Flag accounts with missed payments as delinquent
   */
  public static function flagDelinquents() {
    $config = \Drupal::config('bombplates_payment.settings');
    $billing = $config->get('billing_mail');
    $accounts = self::fetchNewDelinquents();
    $support_l = \Drupal\Core\Link::fromTextAndUrl('support@bombplates.com', \Drupal\Core\Url::fromUri('mailto:support@bombplates.com'))->toString();
    $site_l = \Drupal\Core\Url::fromUri('internal:/', ['absolute'=>TRUE])->toString();
    foreach ($accounts AS $account) {
      $missed_payments = $account->field_missed_payments->value > 0 ? $account->field_missed_payments->value : 0;
      $missed_payments++;
      if ($account->hasRole('customer')) {
        $body = [
          [
            'We are missing a payment from you. Your site will be taken offline in 7 days. Please check the payment information you entered for us on @L',
            ['@L' => $site_l],
          ],
          [
            'If you think you have incorrectly received this message or otherwise need help, please contact Bombplates support at @L',
            ['@L' => $support_l],
          ],
          'A member of our support staff will get back to you promptly.',
        ];
        Bombplates\MiscFunc::queueMail([
          'to' => $account->getEmail(),
          'from' => $billing,
          'subject' => t('Payment error on your Bombplates account', [], ['langcode' => $account->getPreferredLangcode()]),
          'body' => Bombplates\MiscFunc::buildMailBody($body, $account),
        ]);
      } // if account is customer
      $options = [
        'missed_payments' => 1,
        'increment_pay_date' => TRUE,
        'warning_only' => $missed_payments <= 1,
      ];
      Bombplates\MiscFunc::queueProcess([
        'action' => 'suspend', 'account' => $account, 'options' => $options
      ]);
    } // foreach account in accounts
  } // flagDelinquents

  /**
   * Suspend delinquent accounts
   */
  public static function suspendDelinquents() {
    $accounts = self::fetchCurrentDelinquents(23);
    $delinquents = [];
    $config = \Drupal::config('bombplates_payment.settings');
    $base_fee = $config->get('base_fee');
    $billing = $config->get('billing_mail');
    foreach ($accounts AS $account) {
      $options = [
        'missed_payments' => 0,
        'increment_pay_date' => FALSE,
        'warning_only' => FALSE,
      ];
      Bombplates\MiscFunc::queueProcess([
        'action' => 'suspend', 'account' => $account, 'options' => $options
      ]);
      $delinquents[$account->id()] = [
        'uid' => ['#markup' => $account->id()],
        'name' => ['#markup' => $account->getUsername()],
        'subdomain' => ['#markup' => $account->field_subdomain->value],
        'balance' => ['#markup' => $account->field_missed_payments->value * $base_fee],
      ];
    } // foreach account in accounts
    if (!empty($delinquents)) {
      $delinquents['#type'] = 'table';
      $delinquents['#header'] = ['uid', t('user name'), t('subdomain'), t('outstanding balance')];
      $body = [
        'The following accounts are now suspended for missed payments.',
        [
          '@T',
          ['@T' => \Drupal::service('renderer')->renderPlain($delinquents)],
        ],
      ];
      Bombplates\MiscFunc::queueMail([
        'to' => $billing,
        'subject' => t('Sites suspended for non-payment'),
        'body' => Bombplates\MiscFunc::buildMailBody($body),
      ]);
    } // !empty(delinquents)
  } // suspendDelinquents

  /////HELPER FUNCTIONS/////

  /**
   * Find all out-of-band customers who are due to log another payment
   *
   * @return array
   *  Of user objects
   */
  protected static function findOutOfBand() {
    $exclude = array_merge([0,1], self::findInBand());
    $uids = \Drupal::entityQuery('user')
      ->condition('roles.target_id', 'customer', '=')
      ->condition('field_next_payment', date('c', \Drupal::time()->getRequestTime()), '<')
      ->condition('field_billing_status', 'Billed Account', '=')
      ->condition('field_suspended', 0, '=')
      ->condition('field_missed_payments', 0, '=')
      ->condition('uid', $exclude, 'NOT IN')
      ->execute();
    return \Drupal\user\Entity\User::loadMultiple($uids);
  } // findOutOfBand

  /**
   * Find all users who are paying in-band
   *
   * @return array - of int user ids
   */
  protected static function findInBand() {
    $result = [];
    foreach (\Drupal::moduleHandler()->invokeAll('bombplates_payment_find_paying_users') AS $module => $uids) {
      $result += (array)$uids;
    } // foreach module=>uids in hook_bombplates_find_paying_users
    return $result;
  } // findInBand

  /**
   * Find accounts that are about to be deleted
   *  This includes accounts with 1+ missing payments or the suspended flag whose next payment is due in the next few days (to our cron interval)
   *
   * @param int $days
   *   Number of days users must be out from next payment interval.
   * @return array
   *   Of user objects.
   */
  protected static function fetchCurrentDelinquents($days) {
    $cron_time = \Drupal::time()->getRequestTime() - \Drupal::state()->get('system.cron_last');
    $suspended = \Drupal::entityQuery('user')
      ->condition('field_suspended', 0, '>')
      ->condition('uid', 1, '>')
      ->execute();
    $missed = \Drupal::entityQuery('user')
      ->condition('field_missed_payments', 0, '>')
      ->condition('uid', 1, '>')
      ->execute();
    $window_start = \Drupal::time()->getRequestTime() + $days*86400;
    $window_end = $window_start + $cron_time;
    $due_query = \Drupal::entityQuery('user')
      ->condition('uid', 1, '>')
      ->condition('field_billing_status', 'Billed Account', '=')
      ->condition('field_next_payment', date('c', $window_start), '>')
      ->condition('field_next_payment', date('c', $window_end), '<');
    if (!empty($suspended) && !empty($missed)) {
      $or = $due_query->orConditionGroup()
        ->condition('uid', $suspended, 'IN')
        ->condition('uid', $missed, 'IN');
      $due_query->condition($or);
    } // both suspended and missed found
    elseif (!empty($suspended)) {
      $due_query->condition('uid', $suspended, 'IN');
    } // just suspended found
    elseif (!empty($missed)) {
      $due_query->condition('uid', $missed, 'IN');
    } // just missed found
    $due = $due_query->execute();
    return \Drupal\user\Entity\User::loadMultiple($due);
  } // fetchCurrentDelinquents

  /**
   * Find accounts with new missed payments
   *
   * @return array - of user objects
   */
  protected static function fetchNewDelinquents() {
    $missed = \Drupal::entityQuery('user')
      ->condition('uid', 1, '>')
      ->condition('field_next_payment', date('c', \Drupal::time()->getRequestTime()), '<')
      ->condition('field_billing_status', 'Billed Account', '=')
      ->execute();
    return \Drupal\user\Entity\User::loadMultiple($missed);
  } // fetchNewDelinquents
} // CronFunc
