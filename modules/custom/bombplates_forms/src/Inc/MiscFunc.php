<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Inc\MiscFunc
 */

namespace Drupal\bombplates_forms\Inc;

/*
 * miscellaneous static shared public static functions for bombplates_forms module
 */
class MiscFunc {
  /**
   * Validate a fully-qualified domain name
   *
   * @param string $dn
   *  Domain name name to validate
   * @return string
   *  $dn converted to a FQDN (ending with .bombplates.com by default). FALSE on failure
   */
  public static function validateFqdn($dn) {
    $result = FALSE;
    if (preg_match('/^[a-z0-9-]*(\.[a-z0-9_-]+)+$/', $dn)) {
      $result = $dn;
    } // if server is valid FQDN
    return $result;
  } // validateFqdn

  /**
   * Fetch all of a user's account managers
   *
   * @param int $uid
   *  a user's uid
   * @return array
   *  of user objects
   */
  public static function getAccountManagers($uid) {
    $query = \Drupal::entityQuery('user');
    $or = $query->orConditionGroup()
      ->condition('field_accounts_managed', "$uid", '=');
    $uids = $query
      ->condition('roles', ['account_manager'], 'IN')
      ->condition($or)
      ->execute();
    return \Drupal\user\Entity\User::loadMultiple($uids);
  } // getAccountManagers

  /**
   * Remove an account from a manager's list
   *
   * @param object $manager
   *    User object of AM
   * @param object $band
   *    User object of band
   */
  public static function unmanageAccount(\Drupal\Core\Entity\EntityInterface $manager, \Drupal\Core\Entity\EntityInterface $band) {
    $changed = FALSE;
    $bands = [];
    foreach ($manager->field_accounts_managed AS $delta => $field) {
      if ($field->target_id == $band->uid->value) {
        $changed = TRUE;
      }
      else {
        $bands[] = $field->target_id;
      }
    } // foreach delta=>field in field_accounts_managed
    if ($changed) {
      $manager->field_accounts_managed->setValue($bands);
      $manager->save();
    }
  } // unmanageAccount

  /**
   * Add an account to a manager's list
   *
   * @param object $manager
   *    User object of AM
   * @param object $band
   *    User object of band
   */
  public static function manageAccount(\Drupal\Core\Entity\EntityInterface $manager, \Drupal\Core\Entity\EntityInterface $band) {
    $already_manages = FALSE;
    foreach ($manager->field_accounts_managed AS $delta => $field) {
      if ($field->target_id == $band->uid->value) {
        $already_manages = TRUE;
        break;
      }
    } // foreach delta=>field in field_accounts_managed
    if (!$already_manages) {
      $manager->field_accounts_managed->appendItem($band->uid->value);
      $manager->save();
    }
  } // unmanageAccount

  /**
   * make sure a (secondary) domain is available
   *
   * @param array $domains
   *   list of domain name strings
   * @param int $ignore_uid
   *   uid to ignore (current user)
   * @return boolean
   *   is the url available on this server?
   */
  public static function checkDomainAvailable($domains, $ignore_uid = FALSE) {
    $query = \Drupal::entityQuery('user')
      ->condition('field_websites', $domains, 'IN');
    if ($ignore_uid) { $query->condition('uid', $ignore_uid, '!='); }
    $uids = $query->execute();
    return empty($uids);
  } // checkDomainAvailable

  /**
   * Assign/revoke user role(s) following site action
   *
   * @param object $account
   *  user object
   * @param string $action
   *  Corresponding to a sub-array from bombplates_forms.settings.role_grants - by default on_launch and on_payment are available
   * @return object
   *  User object passed in
   */
  public static function grantRoles(\Drupal\Core\Entity\EntityInterface $account, $action = 'on_launch') {
    $config = \Drupal::config('bombplates_forms.settings');
    $role_grants = $config->get('role_grants');
    $grant = $role_grants[$action]['grant'];
    $revoke = $role_grants[$action]['revoke'];
    $roles = $account->getRoles();
    $changed = FALSE;
    if ($grant && !in_array($grant, $roles)) {
      $account->addRole($grant);
      $changed = TRUE;
    } // if grant
    if ($revoke && in_array($revoke, $roles)) {
      $account->removeRole($revoke);
      $changed = TRUE;
    } // if grant
    if ($changed) {
      $account->save();
    }
    return $account;
  } // grantRoles

  /**
   * Find whatever server has the most space available to host a site
   *
   * @return string
   *    Server name
   */
  public static function findSpace() {
    $servers = \Drupal::config('bombplates_forms.settings')->get('hosting_servers');
    // There is only one hosting server, and we don't intend to add more
    return reset($servers);
  } // findSpace

  /**
   * Load all designs
   *
   * @return array
   *    Keys are node nids values are nodes
   */
  public static function loadDesigns() {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'bombplate', '=')
      ->execute();
    return(\Drupal\node\Entity\Node::loadMultiple($nids));
  } // loadDesigns

} // MiscFunc
