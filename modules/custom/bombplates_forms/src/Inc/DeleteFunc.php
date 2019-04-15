<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Inc\DeleteFunc
 */

namespace Drupal\bombplates_forms\Inc;

/**
 * Static public static functions to aid in site deletion
 */
class DeleteFunc {

  /**
   * Delete an account
   *
   * @param object $account
   *  user object
   */
  public static function deleteAccount($account) {
    $subdomain = $account->field_subdomain->value;
    if ($subdomain) {
      self::deleteAccountSites($account);
      DnsFunc::delete($subdomain);
    }
    else {
      $msg = 'Could not delete user @u. No subdomain found. (@n/@s)';
      $args = ['@u' => $account->uid->value, '@s' => $account->field_websites->value, '@n' => $account->name->value];
      \Drupal::logger('bombplates_forms')->ERROR($msg, $args);
    }
    self::deleteAccountManagement($account->uid->value);
  } // deleteAccount

  /**
   * Flag all of a user's sites for deletion
   *
   * @param object $account
   *  user object
   */
  protected static function deleteAccountSites($account) {
    //delete site
    $sites = explode(',',$account->field_websites->value);
    $subdomain = $account->field_subdomain->value;
    $servers = \Drupal::config('bombplates_forms.settings')->get('hosting_servers');
    foreach ($sites AS $site) {
      foreach ($servers AS $server) {
        CmdFunc::standardCommand('del', $site, $subdomain, $server);
      } // foreach server in servers - NOTE: there is only one server
    } // foreach site in sites
  } // deleteAccountSites

  /**
   * Remove a user from AMs' lists of managed accounts
   *
   * @param int $uid
   *  user's uid
   */
  public static function deleteAccountManagement($uid) {
    foreach (MiscFunc::getAccountManagers($uid) AS $am) {
      for ($i = 0; $i < $am->field_accounts_managed->count(); $i++) {
        if ($uid == $am->field_accounts_managed->get($i)->getValue()['target_id']) {
          $am->field_accounts_managed->removeItem($i);
          $am->save();
          break;
        }
      } // for i = 0..am->field_accounts_managed->count
    } // foreach am in getAccountManagers
  } // deleteAccountManagement

  /**
   * Log a user cancellation in the database (create the appropriate content entry)
   *
   * @param object $account
   *    User Object
   * @param object $reason
   *    TranslatableInterface
   * @return object
   *    The new node
   */
  public static function logCancel($account, $reason) {
    $name = $account->name->value;
    $subdomain = $account->field_subdomain->value;
    $band_name = $account->field_band_name->value;
    $mail = $account->mail->value;
    $join_date = $account->created->value;
    $name = $account->name->value;
    $subdomain = $account->field_subdomain->value;
    $band_name = $account->field_band_name->value;
    $mail = $account->mail->value;
    $join_date = $account->created->value;
    $node = \Drupal\node\Entity\Node::create([
      'type' => 'user_cancellation',
      'title' => $name,
      'uid' => 1,
      'created' => \Drupal::time()->getRequestTime(),
      'changed' => \Drupal::time()->getRequestTime(),
      'status' => NODE_NOT_PUBLISHED,
      'promote' => NODE_NOT_PROMOTED,
      'sticky' => NODE_NOT_STICKY,
      'body' => $reason,
    ]);
    $node->field_user_cancel_subdomain->value = $subdomain;
    $node->field_user_cancel_artist_name->value = $band_name;
    $node->field_user_cancel_mail->value = $mail;
    $node->get('field_user_cancel_dates')->set(0, date('Y-m-d', $join_date))->set(1, date('Y-m-d', \Drupal::time()->getRequestTime()));
    $node->save();
    return $node;
  } // logCancel

} // DeleteFunc
