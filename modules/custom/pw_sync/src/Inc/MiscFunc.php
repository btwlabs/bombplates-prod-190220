<?php

/**
 * @file
 *  Contains \Drupal\pw_sync\Inc\MiscFunc
 */

namespace Drupal\pw_sync\Inc;

/**
 * Miscellaneous helper public static functions for pw_sync module.
 */
class MiscFunc {
  /**
   * Generate a new sync key
   *
   * @return string
   * A random string of alphanumeric characters
   */
  public static function generateKey() {
    $alpha = [
      'q','w','e','r','t','y','u','i','o','p','a','s','d','f','g','h','j','k','l','z','x','c','v','b','n','m','1','2','3','4','5','6','7','8','9','0'
    ];
    $l = sizeof($alpha)-1;
    $result = '';
    for ($i = 0; $i < 32; $i++) {
      $result .= $alpha[rand(0,$l)];
    }
    return $result;
  } // generateKey

  /**
   * Determine if this server is a pw_sync client
   *
   * @return boolean
   * Is this server a pw_sync client?
   */
  public static function isClient() {
    return (boolean)(\Drupal::config('pw_sync.settings')->get('server'));
  } // isClient

  /**
   * Determine if this server is a pw_sync server
   *
   * @return boolean
   * Is this server a pw_sync server?
   */
  public static function isServer() {
    $config = \Drupal::config('pw_sync.settings');
    return (boolean)($config->get('subdomain')) && (bool)($config->get('domain'));
  } // isServer

  /**
   * Find a user by their subdomain
   *
   * @param string $site
   *    [protocol://]FQDN of the site we're syncing
   * @return object|FALSE
   *    a user object or FALSE on failure
   */
  public static function userBySite($site) {
    $result = FALSE;
    $config = \Drupal::config('pw_sync.settings');
    $field = $config->get('subdomain');
    $domain = $config->get('domain');
    $matches = [];
    if (preg_match("!(https?://)?([^/.]+).$domain!", $site, $matches)) {
      $uids = \Drupal::entityQuery('user')
        ->condition($field, $matches[2], '=')
        ->execute();
      if ($uid = reset($uids)) {
        $result = \Drupal\user\Entity\User::load($uid);
      }
    } // if preg_match
    return $result;
  } // userBySite

  /**
   * Cache a user's name, key, etc. for asynchronous access
   *
   * @param array $names
   *  list of alternate names the user may go by
   * @param mixed $settings
   *  array of data to store or NULL to remove values entirely
   */
  public static function setCacheData($names, $settings = NULL) {
    $cache = \Drupal::cache()->get('pw_sync_pending');
    $data = $cache ? $cache->data : [];
    foreach (array_unique($names) AS $name) {
      if ($name) {
        if ($settings) {
          $data[$name] = $settings;
        }
        else {
           unset($data[$name]);
        }
      } // if name
    } // foreach name in names
    \Drupal::cache()->set('pw_sync_pending', $data);
  } // setCacheData

  /**
   * Retreive a user's name, key, etc. from the cache for asynchronous access
   *
   * @param array $names
   *  list of alternate names the user may go by
   * @return mixed
   *  Array of cached data or FALSE if it doesn't exist
   */
  public static function getCacheData($names) {
    $result = FALSE;
    $cache = \Drupal::cache()->get('pw_sync_pending');
    if ($cache) {
      foreach ($names AS $name) {
        if ($name && isset($cache->data[$name])) {
          $result = $cache->data[$name];
          break;
        } // if name && cache[name]
      } // foreach name in names
    } // if cache
    return $result;
  } // getCacheData

  /**
   * Build our own HTTP Auth string.
   *
   * @return string
   *  Of the form name:password
   */
  public static function httpAuth() {
    $name = str_replace(':', '.', \Drupal::config('system.site')->get('name'));
    $pass = \Drupal::config('pw_sync.settings')->get('password');
    return "$name:$pass";
  } // httpAuth
} // MiscFunc
