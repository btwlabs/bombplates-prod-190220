<?php

/**
 * @file
 * Contains Drupal\pw_sync\Inc\ResyncServerFunc
 */

namespace Drupal\pw_sync\Inc;

use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Helper functions for performing resync from server-side
 */
class ResyncServerFunc {

  /**
   * Check if the request is valid
   *
   * @param string $name
   *    Candidate username
   * @param string $mail
   *    Candidate email address
   * @param string $site
   *    Candidate site
   * @return mixed
   *    FALSE on valid request. Error message if not
   */
  public static function invalidRequest($name, $mail, $site) {
    $result = FALSE;
    $config = \Drupal::config('pw_sync.settings');
    $auth_name = $_SERVER['PHP_AUTH_USER'];
    $auth_pass = $_SERVER['PHP_AUTH_PW'];
    $watchdog_arr = ['@name' => $name, '@mail' => $mail, '@site' => $site, '@server' => $auth_name];
    \Drupal::logger('pw_sync')->notice('Password resync requested for @name/@mail from @site on @server', $watchdog_arr);
    if ($auth_pass != $config->get('password')) {
      $result = t('Authentication failed');
      \Drupal::logger('pw_sync')->warning('Resync failed: Wrong password.', $watchdog_arr);
    } // if wrong password
    elseif (!$name || !$mail || !$site) {
      $result = t('Required field missing');
      \Drupal::logger('pw_sync')->warning('Resync failed: missing field (name=@name, mail=@mail, site=@site)', $watchdog_arr);
    } // if missing name mail or site
    return $result;
  } // invalidRequest

  /**
   * Load the user from the request data.
   *
   * @param string $name
   *    Candidate username
   * @param string $mail
   *    Candidate email address
   * @param string $site
   *    Candidate site
   * @return mixed
   *    User object or FALSE on failure.
   */
  public static function loadAccount($name, $mail, $site) {
    if ($name && $account = user_load_by_name($name)) { }
    elseif ($mail && $account = user_load_by_mail($mail)) { }
    elseif ($site && $account = MiscFunc::userBySite($site)) { }
    return $account;
  } // loadAccount

  /**
   * Pull the relevant data from an account and ready it for sending back
   *
   * @param object $account
   *    User object
   * @return string
   *    Encrypted text ready to display
   */
  public static function buildResponse($account) {
    $pw_sync_key_field = $account->get('field_pw_sync_key');
    if ($pw_sync_key_field->isEmpty()) {
      $nkey = MiscFunc::generateKey();
      $account->set('field_pw_sync_key', $nkey)->save();
    }
    $data = [
      'pass' => $account->pass->value,
      'name' => $account->getAccountName(),
      'mail' => $account->getEmail(),
      'key' => $account->field_pw_sync_key->value
    ];
    $host = \Drupal::request()->getClientIp();
    // If requesting from an internal IP, don't try to find the exact session. Just grab the most recent.
    $internal_ips = \Drupal::config('pw_sync.settings')->get('ip_whitelist');
    if (in_array($host, $internal_ips)) { $host = FALSE; }
    if ($sess_info = ResyncSharedFunc::fetchSession($account, $host)) {
      $data['sso'] = $sess_info['sso'];
      $data['sid'] = $sess_info['sid'];
      $data['hostname'] = $sess_info['hostname'];
    }
    return ResyncSharedFunc::encrypt(json_encode($data));
  } // buildResponse

  /**
   * Generate a Single Sign On url for the current user.
   *
   * @return string.
   *    A valid URL.
   */
  public static function ssoUrl() {
    $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $config = \Drupal::config('pw_sync.settings');
    $subdomain_field = $config->get('subdomain');
    $domain = $config->get('domain');
    $field = $account->get($subdomain_field);
    $session = \Drupal::request()->getSession();
    $sso_key = user_pass_rehash($account, \Drupal::time()->getRequestTime());
    $session->set('sso', $sso_key);
    $session->save();
    $subdomains = $field->get(0)->getValue();
    $subdomain = reset($subdomains);
    return "https://$subdomain.$domain/user/sso?key=$sso_key";
  } // ssoUrl

} // ResyncServerFunc
