<?php

/**
 * @file
 * Contains Drupal\pw_sync\Inc\ResyncClientFunc
 */

namespace Drupal\pw_sync\Inc;

use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Helper functions for performing resync from client-side
 */
class ResyncClientFunc {

  /**
   * Fetch our own FQDN
   *
   * @return mixed
   *    A FQDN string or false on failure
   */
  public static function getSite() {
    $site = $_SERVER['SERVER_NAME'];
    if (class_exists('Drupal\bombplates\Inc\UrlFunc')) {
      $site = \Drupal\bombplates\Inc\UrlFunc::internalDomain();
    } // if function_exists(bombplates_base_url) (clients only)
    return $site;
  } // getSite

  /**
   * Build and send resync queries to the server
   *
   * @param string $site
   *    The the FQDN of this site
   * @return array
   *    decoded results from the server
   */
  public static function buildSend($site) {
    $config = \Drupal::config('pw_sync.settings');
    $url = $config->get('server') . '/pw/resync';
    $result = [];
    $accounts = \Drupal\bombplates\Inc\MiscFunc::userLoadByPermission('use pw_sync');
    foreach ($accounts AS $account) {
      $name = $account->getAccountName();
      $mail = $account->getEmail();
      $posts = [
        'name=' . urlencode($name),
        'mail=' . urlencode($mail),
        'site=' . urlencode($site),
      ];
      if ($sess_data = ResyncSharedFunc::fetchSession($account)) {
        $posts[] = 'sid=' . urlencode($sess_data['sid']);
        $posts[] = 'sso=' . urlencode($sess_data['sso']);
        $posts[] = 'hostname=' . urlencode($sess_data['hostname']);
      }
      $c = curl_init();
      curl_setopt($c, CURLOPT_URL, $url);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($c, CURLOPT_USERPWD, MiscFunc::httpAuth());
      curl_setopt($c, CURLOPT_POST, count($posts));
      curl_setopt($c, CURLOPT_POSTFIELDS, implode('&', $posts));
      $c_res = curl_exec($c);
      curl_close($c);
      $result[$account->id()] = json_decode($c_res, TRUE);
    }
    return $result;
  } // buildSend

  /**
   * Process the results returned from server queries.
   *
   * @param array $syncs
   *    per buildSend.
   * @return string
   *    Json-encoded string for display.
   */
  public static function processSync($syncs) {
    foreach ($syncs AS $uid => $sync_res) {
      $account = \Drupal\user\Entity\User::load($uid);
      $name = $account->getAccountName();
      if (isset($sync_res['error'])) {
        \Drupal::logger('pw_sync')->error(
          'Error fetching sync info for "@user" from server: "@err"',
          ['@user' => $name, '@err' => $sync_res['error']]
        );
        $result['errors'][] = t('@n: @e', ['@n' => $name, '@e' => $sync_res['error']]);
      } // if sync_res[error]
      elseif (!$sync_res['data']) {
        \Drupal::logger('pw_sync')->error(
          'Error fetching sync info for "@user" from server: No data returned',
          ['@user' => $name]
        );
        $result['errors'][] = t('@n: No data returned', ['@n' => $name]);
      } // if !sync_res[data]
      else {
        //decrypt the data
        $data = json_decode(ResyncSharedFunc::decrypt($sync_res['data']), TRUE);
        if (!$data) {
          \Drupal::logger('pw_sync')->error(
            'Error fetching sync info for "@user" from server: Data "@data" could not be decrypted',
            ['@user' => $account->getAccountName(), '@data' => $sync_res['data']]
          );
          $result['errors'][] = t('@n: Key was malformed or corrupt', ['@n' => $name]);
        } // if !data
        else {
          self::updateUser($account, $data);
          $result['status'] = 'Ok';
        } // if data
      } // if !sync_res[error] && sync_res[data]
    } // foreach sync_res in syncs
    return json_encode($result);
  } // processSync

  /**
   * Update a list of users with new data from the server
   *
   * @param array $syncs
   *    per buildSend
   */
  public static function updateUsers($syncs) {
    foreach ($syncs AS $uid => $sync_res) {
      if (isset($sync_res['data'])) {
        $data = json_decode(ResyncSharedFunc::decrypt($sync_res['data']), TRUE);
        if ($data) {
          $account = \Drupal\user\Entity\User::load($uid);
          updateUser($account, $sync_res['data']);
        } // if data decrypted
      } // if sync_res[data]
    } // foreach uid=>sync_res in syncs
  } // updateUsers

  /**
   * Update a user's data with what was returned from the server.
   *
   * @param object $account
   *    User object.
   * @param array $data
   *    Data to update on the user
   */
  public static function updateUser($account, $data) {
    $account->pass->pre_hashed = TRUE;
    $account->pass->value = $data['pass'];
    $account->set('field_pw_sync_key', $data['key'])
      ->set('name', $data['name'])
      ->set('mail', $data['mail'])
      ->save();
    if (isset($data['sid']) && isset($data['hostname']) && isset($data['sso'])) {
      ResyncSharedFunc::createSession($account, $data['sid'], $data['hostname'], $data['sso']);
    }
  } // updateUser

} // ResyncClientFunc
