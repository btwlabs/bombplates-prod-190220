<?php

/**
 * @file
 *  Contains \Drupal\pw_sync\Inc\NetworkFunc
 */

namespace Drupal\pw_sync\Inc;


/**
 * Miscellaneous public static functions for pw_sync module that use network functionality - i.e. they will query other servers
 */
class NetworkFunc {

  /**
   * Update a user's password|username|email throughout the family tree
   *
   * @param array $var
   * array of options that can include
   * @var $account object
   * A \Drupal\user\Entity\User - required
   * @var $uname string
   * Previous username
   * @var $nuname mixed
   * New username string or FALSE if not updated
   * @var $nemail mixed
   * New email address string or FALSE if not updated
   * @var $key string
   * Old syncing key
   * @var $nkey string
   * New syncing key
   * @var $pass mixed
   * New password string or FALSE if not updated
   * @var $except_children array
   * Strings of child nodes to exclude
   * @var $except_parent boolean
   * Exclude this node's parent?
   */
  public static function updateFamily($var) {
    //Only invoke this function once per page load
    $family_updated = &drupal_static(__FUNCTION__);
    if ($family_updated) { return; }
    $config = \Drupal::config('pw_sync.settings');
    self::updateChildren($var);
    // UPDATE PARENT
    $parent = $config->get('server');
    $except_parent = isset($var['except_parent']) ? $var['except_parent'] : FALSE;
    if (!$except_parent && $parent) {
      self::updateParent($var);
    } // if update parent
    $family_updated = TRUE;
  } // updateFamily

  public static function updateChildren($var) {
    // Configuration settings
    $config = \Drupal::config('pw_sync.settings');
    $account = $var['account'];
    $subdomain_field = $config->get('subdomain');
    $domain = $config->get('domain');
    // Values to update
    $uname = isset($var['uname']) ? $var['uname'] : '';
    $nuname = isset($var['nuname']) ? $var['nuname'] : '';
    $nemail = isset($var['nemail']) ? $var['nemail'] : '';
    $key = isset($var['key']) ? $var['key'] : '';
    $nkey = isset($var['nkey']) ? $var['nkey'] : '';
    $pass = isset($var['pass']) ? $var['pass'] : '';
    $except_children = isset($var['except_children']) ? $var['except_children'] : [];
    // UPDATE CHILDREN
    if ($subdomain_field && $domain) {
      $settings = [
        'uname' => $uname,
        'nuname' => $nuname,
        'nemail' => $nemail,
        'key' => $key,
        'nkey' => $nkey,
        'pass' => $pass,
      ];
      MiscFunc::setCacheData([$uname, $nuname], $settings);
      foreach ($except_children AS $key => $except_child) {
        $except_children[$key] = preg_replace("!^(https?:)?//|\.$domain$!", '', $except_child);
      }
      $field = $account->get($subdomain_field);
      for ($i = 0; $i < $field->count(); $i++) {
        $subdomains = $field->get($i)->getValue();
        $subdomain = reset($subdomains);
        if (!in_array($subdomain, $except_children)) {
          //$url = "http://$subdomain.$domain/pw/outdate?nothtml=1&notheme=1";
          $url = 'http://10.1.10.234/pw/outdate?nothtml=1&notheme=1';
          $post = "uname=$uname&auname=$nuname&nkey=$nkey";
          $post_num = 3;
          $c = curl_init();
          curl_setopt($c, CURLOPT_URL, $url);
          curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($c, CURLOPT_POST, $post_num);
          curl_setopt($c, CURLOPT_POSTFIELDS, $post);
          curl_setopt($c, CURLOPT_HTTPHEADER, ["Host: $subdomain.$domain"]);
          $curl_res = curl_exec($c);
        } // !in_array(subdomain, except_children)
      } // foreach subdomain in field
      MiscFunc::setCacheData([$uname, $nuname], NULL);
    } // if subdomain_field && domain
  } // updateChildren

  /**
   * Send a pw update to our parent.
   *
   * @param array $var
   *  Containing data to sync. At minimum, uname and account.
   */
  public static function updateParent($var) {
    $server = \Drupal::config('pw_sync.settings')->get('server');
    $url = "$server/pw/update?nothtml=1&notheme=1";
    global $base_url;
    $this_server = $base_url;
    $account = $var['account'];
    $posts = [
      'uname=' . $var['uname'],
      'key=' . $account->field_pw_sync_key->value,
      "server=$this_server",
    ];
    if (isset($var['pass'])) {
      $posts[] = 'pass=' . $var['pass'];
    }
    if (isset($var['nuname'])) {
      $posts[] = 'nuname=' . $var['nuname'];
    }
    if (isset($var['nemail'])) {
      $posts[] = 'nemail=' . $var['nemail'];
    }
    if (isset($var['nkey'])) {
      $posts[] = 'nkey=' . $var['nkey'];
    }
    if (isset($var['sid']) && isset($var['sso']) && isset($var['hostname'])) {
      $posts[] = 'sid=' . urlencode($var['sid']);
      $posts[] = 'sso=' . urlencode($var['sso']);
      $posts[] = 'hostname=' . urlencode($var['hostname']);
    }
    elseif ($sess_data = ResyncSharedFunc::fetchSession($account)) {
      $posts[] = 'sid=' . urlencode($sess_data['sid']);
      $posts[] = 'sso=' . urlencode($sess_data['sso']);
      $posts[] = 'hostname=' . urlencode($sess_data['hostname']);
    }
    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_POST, count($posts));
    curl_setopt($c, CURLOPT_POSTFIELDS, implode('&', $posts));
    $c_res = curl_exec($c);
  } // updateParent

  /**
   * Check the availability of a username/email - only check locally and upwards. We no longer look down.
   *
   * @param string $name
   * Username.
   * @param string $mail
   * Email address.
   * @param string $source
   * Domain that invoked this - prevents infinite loops
   * @return array|boolean
   * Containing field_name => error_message on failure. FALSE on success
   */
  public static function checkAvailable($name = '', $mail = '', $source = '') {
    $result = FALSE;
    if (!$name && !$mail) {
      $result = [
        'name' => t('No username specified'),
        'mail' => t('No email specified. Username or email is required')
      ];
    }
    elseif ($name && \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => $name])) {
      $result = ['name' => t('That username is taken')];
    }
    elseif ($mail && \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $mail])) {
      $result = [
        'mail' => t(
          'That email address is already in use. Please email support@bombplates.com if you have lost your password or cannot access part of your account'
        ),
      ];
    }
    else {
      $server = \Drupal::config('pw_sync.settings')->get('server');
      if ($server && preg_replace('!^https?://!','',$server) != preg_replace('!^https?://!','',$source)) {
        $c = curl_init();
        $post = 'source=' . ResyncClientFunc::getSite();
        $post_num = 1;
        if($name) {
          $post .= "&nuname=$name";
          $post_num++;
        } // if name
        if($mail) {
          $post .= "&nemail=$mail";
          $post_num++;
        } // if mail
        $url = "$server/pw/available?nothtml=1&notheme=1";
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_POST, $post_num);
        curl_setopt($c, CURLOPT_POSTFIELDS, $post);
        $c_res = curl_exec($c);
        $c_err = curl_error($c);
        if (!$c_res && $c_err) {
          $result = ['name' => 'Network error: "' . $c_err . '" Please contact support.'];
        } // if curl error
        elseif (stripos($c_res, 'site offline') === FALSE || stripos($c_res, '200 ok') === FALSE) {
          $result = json_decode($c_res, TRUE);
          // Check for the old format
          if (isset($result['field'])) {
            $result = [$result['field'] => $result['msg']];
          }
        } // if result doesn't contain one of our whitelisted strings, return the json decode
      } // if server && server != source
    } // if name|numail was passed and does not exist locally
    return $result;
  } // checkAvailable
} // NetworkFunc
