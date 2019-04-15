<?php

/**
 * @file
 *  Contains \Drupal\bombplates\Inc\MiscFunc
 */

namespace Drupal\bombplates\Inc;

/**
 * Miscellaneous helper public static functions
 */
class MiscFunc {

  /**
   * Load users by permission
   *
   * @param string $permission
   *  Machine name of a permission to check
   */
  public static function userLoadByPermission($permission) {
    $roles = user_roles(FALSE, $permission);
    $uids = \Drupal::entityQuery('user')
      ->condition('roles', array_keys($roles), 'IN')
      ->condition('uid', [0,1], 'NOT IN')
      ->execute();
    return \Drupal\user\Entity\User::loadMultiple($uids);
  } // userLoadByPermission

  /**
   * Build a body array for an email to a customer
   *
   * @param array $body
   *  Of plain strings and/or strings and args per t() (e.g. [ [ 'text :r', [ ':r' => 'replacement' ] ] ]
   * @param object $account
   *  User object. If NULL, the email will be considered internal and use the default language
   * @return array
   *  per queueMail() body param
   */
  public static function buildMailBody($body, $account = NULL) {
    static $support_l = FALSE;
    if (!$support_l) {
      $support_l = \Drupal\Core\Link::fromTextAndUrl('support@bombplates.com', \Drupal\Core\Url::fromUri('mailto:support@bombplates.com'))->toString();
    }
    static $sign_offs = [];
    if ($account) {
      $langcode = $account->getPreferredLangcode();
      $t_opt = ['langcode' => $langcode];
      if (!isset($sign_offs[$langcode])) {
        $sign_offs[$langcode] = [
          t('Thank you for using Bombplates and feel free to email any questions or concerns to @lnk.', ['@lnk' => $support_l], $t_opt),
          '',
          t('Sincerely,', [], $t_opt),
          t('The Bombplates team', [], $t_opt),
        ];
      }
      $sign_off = $sign_offs[$langcode];
      $result = [
        t('Dear @n,', ['@n' => $account->getDisplayName()], $t_opt),
        '',
      ];
    } // if account
    else {
      $langcode = \Drupal::service('language.default')->get()->getId();
      $t_opt = ['langcode' => $langcode];
      $result = ['<p style="color:red; font-weight:bold;">[INTERNAL]</p>'];
      $sign_off = [];
    } // if !account
    foreach ($body AS $val) {
      $arr = is_array($val) ? $val : [$val];
      $string = $arr[0];
      $args = isset($arr[1]) ? $arr[1] : [];
      $result[] = t($string, $args, $t_opt);
    } // foreach val in body
    $result = array_merge($result, $sign_off);
    return $result;
  } // buildMailBody

  /**
   * Queue an email for delivery. Default Bombplates letterhead will be added
   *
   * @param array $params
   *  containing at minimum to (email address), subject (string) and body (array)
   * @return boolean
   *  Was the email queued successfully?
   */
  public static function queueMail($params) {
    $queue = \Drupal::service('queue')->get('bombplates_mail');
    $item = new \stdClass();
    $item->to = $params['to'];
    $item->from = isset($params['from']) ? $params['from'] : \Drupal::config('system.site')->get('mail');
    $item->subject = $params['subject'];
    $item->body = is_array($params['body']) ? $params['body'] : [$params['body']];
    $item->key = isset($params['key']) ? $params['key'] : 'client_alert';
    $item->is_external = isset($params['is_external']) ? $params['is_external'] : TRUE;
    $item->langcode = isset($params['langcode']) ? $params['langcode'] : \Drupal\Core\Language\LanguageInterface::LANGCODE_DEFAULT;
    return $queue->createItem($item);
  } // queueMail

  /**
   * Queue some account processing
   *
   * @param array $params
   *  containing action, account, and options per hook_bombplates_process_account
   * @param string $queue_name
   *  Name of the queue to put this in if
   * @param boolean $immediate
   *  Skip the queue and execute immediately
   * @return boolean
   *  Was the process queued or executed successfully?
   */
  public static function queueProcess($params, $queue_name = 'bombplates_process', $immediate = FALSE) {
    $result = TRUE;
    if (!isset($params['action'])) {
      \Drupal::logger('bombplates')->ERROR('queueProcess CAN NOT be invoked without action specified');
      return FALSE;
    }
    elseif (!isset($params['account'])) {
      \Drupal::logger('bombplates')->WARNING('queueProcess should not be invoked without account specified');
    }
    $action = $params['action'];
    $account = isset($params['account']) ? $params['account'] : \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $options = isset($params['options']) ? $params['options'] : [];
    if ($immediate) {
     \Drupal::moduleHandler()->invokeAll('bombplates_process_account', [$action, $account, $options]);
    } // immediate
    else {
      $queue = \Drupal::service('queue')->get($queue_name);
      $item = new \stdClass();
      $item->action = $action;
      $item->account = $account;
      $item->options = $options;
      $result = $queue->createItem($item);
    } // !immediate
    return TRUE;
  } // queueProcess
} // MiscFunc
