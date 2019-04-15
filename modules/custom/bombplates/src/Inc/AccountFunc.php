<?php

/**
 * @file
 *  Contains \Drupal\bombplates\Inc\AccountFunc
 */

namespace Drupal\bombplates\Inc;

/**
 * Functions to perform actions on accounts/users
 */
class AccountFunc {
  /**
   * Perform actions on a user account as it is deleted - invoked by hook_bombplates_delete_account
   *
   * @param object $account
   *   A user object.
   */
  public static function deleteAccount($account) {
    // Don't delete user 0 (anonymous) or 1 (administrator)
    if ($account->uid->value > 1) {
      $account->delete();
    }
  } // deleteAccount

  /**
   * Make sure a subdomain is available
   *
   * @param string $subdomain
   *   the subdomain to check
   * @param int $ignore_uid
   *   ignore this user uid
   * @return bool
   *   Is this subdomain available?
   */
  public static function checkSubdomainAvailable($subdomain, $ignore_uid = FALSE) {
    $result = TRUE;
    $lowercase_sub = strtolower($subdomain);
    //check vs. static black list
    $blacklist = [
      'www', 'dev', 'support', 'blog', 'store',
      'demo', 'sjtest', 'baseline', 'bands', 'ns', 'mail',
      'email', 'webmail', 'cpanel', 'whm', 'admin', 'administrator',
      'bomb', 'bombplates', 'test',
    ];
    foreach ($blacklist AS $evil_sub) {
      if (preg_match("/^$evil_sub\d*$/", $lowercase_sub)) {
        $result = FALSE;
        break;
      } // if subdomain is just evil sub and numbers
    } // foreach evil_sub in blacklist
    if ($result) {
      $query = \Drupal::entityQuery('user')
        ->condition('field_subdomain', $subdomain, '=');
      if ($ignore_uid) { $query->condition('uid', $ignore_uid, '!='); }
      $uids = $query->execute();
      if (!empty($uids)) { $result = FALSE; }
    } // if result
    return $result;
  } // checkSubdomainAvailable
} // AccountFunc
