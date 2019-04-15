<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Inc\CronFunc
 */

namespace Drupal\bombplates_forms\Inc;

use Drupal\bombplates\Inc as Bombplates;

/**
 * Static helper public static functions to run cron functionality
 */
class CronFunc {

  /**
   * Send out a reminder and delete users who haven't launched their site within a week
   *
   * @param int $time_range
   *  length of window to filter users' "created" date by (in seconds)
   */
  public static function processIncompleteAccounts($time_range) {
    self::processIncompleteAccountsReminder1($time_range);
    self::processIncompleteAccountsReminder2($time_range);
    self::processIncompleteAccountsDelete($time_range);
  } // processIncompleteAccounts

  /**
   * Inform users that their trial is about to end and suspend expired trials
   *
   * @param int $time_range
   *  length of window to filter users' "created" date by (in seconds)
   */
  public static function processTrials($time_range) {
    self::trialExpiring($time_range);
    self::endTrials($time_range);
  } // processTrials

  /**
   * Fetch users with incomplete registrations
   *
   * @param int $range_start
   *  start of account->created window
   * @param int $range_end
   *  end of account->created window
   * @return array
   *  Of user objects
   */
  protected static function fetchIncompleteAccounts($range_start, $range_end) {
    $result = [];
    $config = \Drupal::config('bombplates_forms.settings');
    $uids = \Drupal::entityQuery('user')
      ->condition('created', $range_start, '>')
      ->condition('created', $range_end, '<')
      ->condition('uid', 1, '>')
      ->condition('roles', $config->get('role_grants.on_launch.revoke'))
      ->execute();
    return \Drupal\user\Entity\User::loadMultiple($uids);
  } // fetchIncompleteAccounts

  /**
   * Contact all users who signed up a day ago but didn't launch a site yet.
   *
   * @param int $time_range
   *  length of window to filter users' "created" date by (in seconds)
   */
  protected static function processIncompleteAccountsReminder1($time_range) {
    $range_end = \Drupal::time()->getRequestTime() - 86400; // 24*60*60 = 24 hours ago
    $range_start = $range_end - $time_range;
    $accounts = self::fetchIncompleteAccounts($range_start, $range_end);
    $body = [
      "Here at Bombplates we want to help you grow your music career, but we can't start until your account is fully registered!",
      "We noticed you've started signing up for a Bombplates account, but haven't completed registration.",
      [
        'In order to get your site created, you will have to log in to @login and complete the @form for us.',
        [
          '@login' => new \Drupal\Component\Render\FormattableMarkup('<a style="color:#057be6; text-decoration:none;" href="http://www.bombplates.com">bombplates.com</a>', []),
          '@form' => new \Drupal\Component\Render\FormattableMarkup('<a style="color:#057be6; text-decoration:none;" href="http://www.bombplates.com/artist-info">signup form</a>', []),
        ],
      ],
      [
        'Please feel free to send your questions or concerns about using Bombplates to @support.',
        [
          '@support' => new \Drupal\Component\Render\FormattableMarkup('<a style="color:#057be6; text-decoration:none;" href="mailto:support@bombplates.com">support@bombplates.com.</a>', []),
        ],
      ],
      'A member of our support staff will get back to you promptly.',
      'Thank you for signing up!',
    ];
    $default_params = [
      'from' => 'support@bombplates.com',
      'subject' => t('Your Bombplates registration is not finished!'),
      'key' => 'reminder',
      'is_external' => TRUE,
    ];
    foreach ($accounts AS $account) {
      $params = $default_params;
      $params['to'] = $account->mail->value;
      $params['body'] = Bombplates\MiscFunc::buildMailBody($body, $account);
      Bombplates\MiscFunc::queueMail($params);
    } // foreach account in accounts
  } // processIncompleteAccountsReminder1

  /**
   * Six days after account creation, send users a reminder to complete their profile if they have not
   *
   * @param int $time_range
   *  length of window to filter users' "created" date by (in seconds)
   */
  protected static function processIncompleteAccountsReminder2($time_range) {
    $range_end = \Drupal::time()->getRequestTime() - 518400; // 6*24*60*60 = 6 Days ago
    $range_start = $range_end - $time_range;
    $accounts = self::fetchIncompleteAccounts($range_start, $range_end);
    $default_params = [
      'from' => 'support@bombplates.com',
      'subject' => t('Your Bombplates account is about to be deleted.'),
      'key' => 'deleting',
      'is_external' => TRUE,
    ];
    $body = [
      [
        "Last week you created an account on @bp. Please complete the artist info form for us (@form) within the next day, so your account won't be automatically deleted.",
        [
          '@bp' => new \Drupal\Component\Render\FormattableMarkup('<a style="color:#057be6; text-decoration:none;" href="http://www.bombplates.com">bombplates.com</a>', []),
          '@form' => new \Drupal\Component\Render\FormattableMarkup('<a style="color:#057be6; text-decoration:none;" href="http://www.bombplates.com/artist-info">bombplates.com/artist-info</a>', []),
        ],
      ],
      [
        "If you think you have incorrectly received this message, have had trouble with the sign-up process, or otherwise need help, please contact Bombplates support at @mail",
        [
          '@mail' => new \Drupal\Component\Render\FormattableMarkup('<a style="color:#057be6; text-decoration:none;" href="mailto:support@bombplates.com">support@bombplates.com.</a>', []),
        ],
      ],
      'A member of our support staff will get back to you promptly.',
    ];
    foreach ($accounts AS $account) {
      $params = $default_params;
      $params['to'] = $account->mail->value;
      $params['body'] = Bombplates\MiscFunc::buildMailBody($body, $account);
      Bombplates\MiscFunc::queueMail($params);
      // foreach account in accounts
    } // foreach account in accounts
  } // processIncompleteAccountsReminder2

  /**
   * Seven days after account creation, delete incomplete accounts
   *
   * @param int $time_range
   *  length of window to filter users' "created" date by (in seconds)
   */
  protected static function processIncompleteAccountsDelete($time_range) {
    $range_start = 0;
    $range_end = time() - 24*7*60*60;     // 1 week ago
    $accounts = self::fetchIncompleteAccounts($range_start, $range_end);
    $uids = array_keys($accounts);
    user_delete_multiple($uids);
  } // processIncompleteAccountsDelete

  /**
   * Fetch users whose trial is about expire
   *
   * @param int $range_start
   *  start of account->created window
   * @param int $range_end
   *  end of account->created window
   * @return array
   *  Of user objects
   */
  protected static function fetchTrialingAccounts($range_start, $range_end) {
    $result = [];
    $config = \Drupal::config('bombplates_forms.settings');
    $uids = \Drupal::entityQuery('user')
      ->condition('field_trial_ends', $range_start, '>')
      ->condition('field_trial_ends', $range_end, '<')
      ->condition('uid', 1, '>')
      ->condition('roles', $config->get('role_grants.on_payment.revoke'))
      ->execute();
    return \Drupal\user\Entity\User::loadMultiple($uids);
  } // fetchTrialingAccounts

  /**
   * Alert users that their trial is about to end in 1 week
   *
   * @param int $time_range
   *  length of window to filter users' "created" date by (in seconds)
   */
  protected static function trialExpiring($range) {
    $start = new \DateTime("+7 days");
    $end = new \DateTime("+7 days +$range sec");
    $accounts = self::fetchTrialingAccounts($start->format('c'), $end->format('c'));
    $default_params = [
      'from' => 'billing@bombplates.com',
      'subject' => t('Your Bombplates trial is about to expire!'),
      'key' => 'trial_expiring',
      'is_external' => TRUE,
    ];
    $body = [
      [
        'Your Bombplates trial will end in 7 days. Your site will be taken offline at the end of the trial period. In order to continue with your Bombplates subscription, please enter your payment information into @form',
        [
          '@form' => new \Drupal\Component\Render\FormattableMarkup('<a style="color:#057be6; text-decoration:none;" href="http://www.bombplates.com/user/payment">bombplates.com</a>', [])
        ],
      ],
    ];
    foreach ($accounts AS $account) {
      $params = $default_params;
      $params['to'] = $account->mail->value;
      $params['body'] = Bombplates\MiscFunc::buildMailBody($body, $account);
      Bombplates\MiscFunc::queueMail($params);
    } // foreach account in accounts
  } // trialExpiring

  /**
   * Suspend expired trials
   *
   * @param int $time_range
   *  length of window to filter users' "created" date by (in seconds)
   */
  protected static function endTrials($range) {
    $start = new \DateTime("");
    $end = new \DateTime("-$range sec");
    $accounts = self::fetchTrialingAccounts($start->format('c'), $end->format('c'));
    $default_params = [
      'from' => 'billing@bombplates.com',
      'subject' => t('Your Bombplates trial has expired!'),
      'key' => 'trial_over',
      'is_external' => TRUE,
    ];
    $body = [
      [
        "Your Bombplates trial has ended and we still need to set up a payment schedule. In order to continue with your Bombplates subscription, please enter your payment information into @form Otherwise, your site will be deleted in 7 days.",
        [
          '@form' => new \Drupal\Component\Render\FormattableMarkup('<a style="color:#057be6; text-decoration:none;" href="http://www.bombplates.com">bombplates.com.</a>', []),
        ],
      ],
      'Your Bombplate will be back online once your billing info is updated.',
    ];
    $options = [
      'missed_payments' => 1,
      'increment_pay_date' => TRUE,
      'warning_only' => FALSE,
    ];
    foreach ($accounts AS $account) {
      $params = $default_params;
      $params['to'] = $account->mail->value;
      $params['body'] = Bombplates\MiscFunc::buildMailBody($body, $account);
      Bombplates\MiscFunc::queueMail($params);
      Bombplates\MiscFunc::queueProcess(['action' => 'suspend', 'account' => $account, 'options' => $options]);
    } // foreach account in accounts
  } // endTrials

  /**
   * Find any servers that have outstanding functions and prompt them
   */
  public static function promptOutstanding() {
    $db = \Drupal::database();
    $select = $db->select('bombplates_account_commands', 'ac')
      ->fields('ac', ['server'])
      ->condition('time_sent', 0, '=')
      ->distinct()
      ->execute();
    //while ($server = $select->fetchField()) {
    //  NetworkFunc::promptScripts($server);
    //} // while retreiving db values
    $servers = $select->fetchCol();
    if (!empty($servers)) {
      NetworkFunc::promptScripts($servers);
    }
  } // promptOutstanding

} // BombplatesCron
