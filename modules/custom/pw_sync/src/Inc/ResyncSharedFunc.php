<?php

/**
 * @file
 * Contains Drupal\pw_sync\Func\ResyncSharedFunc.
 */

namespace Drupal\pw_sync\Inc;

const PW_SYNC_DECRYPT = 0;
const PW_SYNC_ENCRYPT = 1;

/**
 * Helper functions used by both client and server-side resync.
 */
class ResyncSharedFunc {

  /**
   * Find a session (stub) for an unknown user.
   *
   * @param string $key
   *  per user_pass_rehash.
   * @return array
   *  Containing fields from sessions table. Specifically uid.
   */
  public static function findSession($key) {
    $sub_str = serialize('sso') . serialize($key);
    $query = \Drupal\Core\Database\Database::getConnection('default')->select('sessions')
      ->fields('sessions', ['uid', 'sid', 'hostname'])
      ->condition('session', '%' . db_like($sub_str) . '%', 'LIKE')
      ->condition('hostname', \Drupal::request()->getClientIp(), '=')
      ->orderBy('timestamp', 'DESC')
      ->range(0, 1)
      ->execute();
    $result = $query->fetchAssoc();
    return $result;
  } // findSession

  /**
   * Get extant session data for a known user.
   *
   * @param object $account
   *    User object
   * @param string $host
   *    Optional host name (IP) to filter by
   * @return mixed
   *    Array containing sid, hostname, and sso or FALSE on failure
   */
  public static function fetchSession($account, $host = FALSE) {
    $query = \Drupal\Core\Database\Database::getConnection('default')->select('sessions')
      ->fields('sessions', ['sid', 'hostname', 'session'])
      ->condition('uid', $account->id(), '=')
      ->orderBy('timestamp', 'DESC')
      ->range(0, 1);
    if ($host) {
      $query->condition('hostname', $host, '=');
    }
    $result = $query->execute()->fetchAssoc();
    if ($result) {
      $regex = '/s:3:"sso";s:[0-9]+:"([^"]+)"/';
      if (preg_match($regex, $result['session'], $matches)) {
        $result['sso'] = $matches[1];
      }
      else {
        $result['sso'] = user_pass_rehash($account, \Drupal::time()->getRequestTime());
      }
      unset ($result['session']);
    }
    return $result;
  } // fetchSession

  /**
   * Create a new session for a user
   *
   * @param object $account
   *  User object
   * @param string $sid
   *  Session ID
   * @param string $hostname
   *  IP address
   * @param string $sso
   *  One-time use SSO key
   */
  public static function createSession($account, $sid, $hostname, $sso) {
    //user_login_finalize($account);
    $time = \Drupal::time()->getRequestTime();
    $account->setLastLoginTime($time);
    \Drupal::entityManager()
      ->getStorage('user')
      ->updateLastLoginTimestamp($account);
    $fields = [
      'uid' => $account->id(),
      'hostname' => $hostname,
      'timestamp' => $time,
      'session' => serialize(['autologout_last' =>$time, 'sso' => $sso]),
    ];
    \Drupal\Core\Database\Database::getConnection('default')->merge('sessions')
      ->keys(['sid' =>  $sid])
      ->fields($fields)
      ->execute();
  } // createSession

  /**
   * Delete one of our session stubs
   *
   * @param string $sid
   *  Session ID or key
   * @param boolean $is_key
   *  Signifies that $sid is a key instead
   */
  public static function deleteSession($sid, $is_key=FALSE) {
    if ($is_key) {
      $session_info = self::findSession($key);
      $sid = $session_info['sid'];
    }
    if ($sid) {
      $query = \Drupal\Core\Database\Database::getConnection('default')->delete('sessions')
        ->condition('sid', $sid, '=')
        ->execute();
    }
  } // deleteSession

  /**
   * Get the path of the gnupg keys and set it as an environment variable.
   *
   * @param type int
   *    one of PW_SYNC_ENCRYPT|PW_SYNC_DECRYPT
   * @return string
   *    A file path
   */
  public static function set_gpg_path($type = PW_SYNC_DECRYPT) {
      $config = \Drupal::config('pw_sync.settings');
      switch ($type) {
        case PW_SYNC_ENCRYPT :
          $path = $config->get('gpg_encrypt_path');
          break;
        case PW_SYNC_DECRYPT :
        default :
          $path = $config->get('gpg_decrypt_path');
      } // switch type
      if (!$path) {
        $path = $_SERVER['DOCUMENT_ROOT'] . base_path() . drupal_get_path('module', 'pw_sync') . '/files/gnupg';
      } // if ! path
      if (substr($path, 0, 1) != '/') {
        $path = $_SERVER['DOCUMENT_ROOT'] . "/$path";
      }
      putenv("GNUPGHOME=$path/.gnupg");
      return $path;
  } // set_gpg_path

  /**
   * Encrypt pw_sync data with gnuPG.
   *
   * @param string $int_key
   *    data to encrypt
   * @return string
   *    the encrypted string or FALSE on failure
   */
  public static function encrypt($int_key) {
    $result = FALSE;
    if (extension_loaded("gnupg")) {
      $path = self::set_gpg_path(PW_SYNC_ENCRYPT);
      $gpg = new \gnupg();
      //check our key
      $recipient = 'support@bombplates.com';
      if (!$gpg->addencryptkey($recipient)) {
        $public = $gpg->import(file_get_contents("$path/bombplates.pubkey.txt"));
        if (!$gpg->addencryptkey($public['fingerprint'])) {
          $error = $gpg->geterror();
          \Drupal::logger('pw_sync')->warning('Could add encryption keys: "@e"', ['@e' => $error]);
        }
      } // if !gpg->addencryptkey
      $result = $gpg->encrypt($int_key);
    } // if extension_loaded(gnupg)
    return $result;
  } // encrypt

  /**
   * Decrypt pw_sync data with gnuPG.
   *
   * @param string $int_key
   *    data to decrypt
   * @return string
   *    the decrypted string or FALSE on failure
   */
  public static function decrypt($int_key) {
    $result = FALSE;
    if (extension_loaded("gnupg")) {
      $path = self::set_gpg_path(PW_SYNC_DECRYPT);
      $gpg = new \gnupg();
      //check our key
      $recipient = 'support@bombplates.com';
      if (!$gpg->adddecryptkey($recipient, '')) {
        $public = $gpg->import(file_get_contents("$path/bombplates.pubkey.txt"));
        if (!$gpg->adddecryptkey($public['fingerprint'], '')) {
          $error = $gpg->geterror();
          \Drupal::logger('pw_sync')->warning('Could add decryption keys: "@e"', ['@e' => $error]);
        }
      } // if !gpg->adddecryptkey
      $result = $gpg->decrypt($int_key);
    } // if extension_loaded(gnupg)
    return $result;
  } // decrypt

} // ResyncSharedFunc
