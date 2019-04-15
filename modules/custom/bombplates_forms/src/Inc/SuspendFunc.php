<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Inc\SuspendFunc
 */

namespace Drupal\bombplates_forms\Inc;

/**
 * Static public static functions for suspending sites
 */
class SuspendFunc {

  /**
   * Flag all of a user's sites for suspension
   *
   * @param object $account
   *  user object
   * @return array
   *  names (strings) of the server hosting the suspended site
   */
  public static function suspendAccountSites($account) {
    $servers = \Drupal::config('bombplates_forms.settings')->get('hosting_servers');
    $server = reset($servers);
    $sites = explode(',',$account->field_websites->value);
    $subdomain = $account->field_subdomain->value;
    foreach ($sites AS $site) {
      CmdFunc::standardCommand('suspend', $site, $subdomain, $server);
    } // foreach sites in site
    return $servers;
  } // suspendAccountSites

  /**
   * Flag all of a user's sites for unsuspension
   *
   * @param object $account
   *  user object
   * @return string
   *  name of the server hosting the unsuspended site
   */
  public static function unsuspendAccountSites($account) {
    $servers = \Drupal::config('bombplates_forms.settings')->get('hosting_servers');
    $server = reset($servers);
    $sites = explode(',',$account->field_websites->value);
    $subdomain = $account->field_subdomain->value;
    foreach ($sites AS $site) {
      CmdFunc::standardCommand('unsuspend', $site, $subdomain, $server);
    } // foreach sites in site
    return $servers;
  } // unsuspendAccountSites
} // SuspendFunc
