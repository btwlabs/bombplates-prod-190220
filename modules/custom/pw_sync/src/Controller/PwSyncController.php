<?php

/**
 * @file
 * Contains \Drupal\pw_sync\Controller\PwSyncController
 */

namespace Drupal\pw_sync\Controller;

use Drupal\bombplates\Inc as Bombplates;
use Drupal\pw_sync\Inc as PWSync;
use Symfony\Component\HttpFoundation\Session\Session;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for pw_sync routes
 */
class PwSyncController extends ControllerBase {

  /**
   * Authenticate a pw_sync request.
   *
   * @param string $server
   *   Domain name of the requesting server.
   * @param string $uname
   *   Username of the user.
   * @param string $key
   *   pw_sync key of the user.
   * @param string $auname
   *   Alternate username.
   * @param string $nkey
   *   Alternate key.
   * @return array
   *   Containing an error array or authenticated account.
   */
  protected function auth($server = '', $uname = '', $key = '', $auname = '', $nkey = '') {
    $result = [];
    if (!$uname) {
      $result['error'][] = $this->t('No username specified.');
    }
    if (!$key && !$nkey) {
      $result['error'][] = $this->t('No shared secret specified.');
    }
    if (!$server) {
      $result['error'][] = $this->t('No server specified.');
    }
    if (isset($result['error'])) { return $result; }
    $all_names = [$uname, $auname];
    $alts = PWSync\MiscFunc::getCacheData([$uname, $auname]);
    if ($alts && isset($alts['uname'])) {
      $all_names[] = $alts['uname'];
    }
    if ($alts && isset($alts['nuname'])) {
      $all_names[] = $alts['nuname'];
    }
    foreach (array_unique($all_names) AS $name) {
      if ($account = user_load_by_name($name)) {
        break;
      }
    } // foreach name in unique(all_names)
    if (!$account || !$account->id()) {
      $result['error'][] = $this->t('User not found.');
    } // if user not found
    else {
      $result['account'] = $account;
      $config = \Drupal::config('pw_sync.settings');
      $parent = $config->get('server');
      $subdomain_field = $config->get('subdomain');
      $domain = preg_replace('!^(https?:)?//!', '', $config->get('domain'));
      $server = preg_replace('!^(https?:)?//!', '', $server);
      if ($server == $domain) {
        $result['site'] = $domain;
      }
      elseif ($account->hasField($subdomain_field)) {
        $field = $account->get($subdomain_field);
        for ($i = 0; $i < $field->count(); $i++) {
          $subdomain = $field->get($i)->getValue()['value'];
          if ($server == "$subdomain.$domain") {
            $result['site'] = $server;
          }
        } // for i = 0..field->count
      } // if server != domain
      if (!isset($result['site'])) {
        $result['error'][] = $this->t('@s does not match any sites on file', ['@s' => $server]);
      }
      $keys = [$account->get('field_pw_sync_key')->first()->getValue()['value']];
      if ($alts && isset($alts['key'])) {
        $keys[] = $alts['nkey'];
      }
      if ($alts && isset($alts['key'])) {
        $keys[] = $alts['nkey'];
      }
      if (!in_array($key, $keys) && !in_array($nkey, $keys)) {
        $result['error'][] = $this->t('shared key mismatch.');
      }
      //authenticate referrer IP
      $ref = $_SERVER['REMOTE_ADDR'];
      if (!in_array($ref, $config->get('ip_whitelist'))) {
        $dns = dns_get_record($server, DNS_A);
        $ip = $dns[0]['ip'];
        if (substr($ip, 0, strrpos($ip, '.')) != substr($ref, 0, strrpos($ip, '.'))) {
          $result['error'][] = $this->t('DNS mismatch');
        } // IP does not public dns records
      } // IP not whitelisted
    } // if user found
    return $result;
  } // auth

  /**
   * Render data as flat text for final display
   *
   * @param array $content
   * A render array
   * @return \Drupal\Core\Render\AttachmentsInterface
   * processed response per HtmlResponseAttachmentsProcessor::processAttachments
   */
  protected function bpRender($content) {
    $bombplates_nohtml_renderer = \Drupal::service('bombplates.renderer.bombplates_nohtml_renderer');
    return $bombplates_nohtml_renderer->renderNohtml($content, TRUE, FALSE);
  } // bpRender

  /**
   * Pushes an updated password from downstream. Invoked client to server.
   *
   * @return array
   *    A render array containing a success or error code and message
   */
  public function update() {
    $content = [];
    $pass = \Drupal::request()->request->get('pass');
    $server = \Drupal::request()->request->get('server');
    $uname = \Drupal::request()->request->get('uname');
    $key = \Drupal::request()->request->get('key');
    $nkey = \Drupal::request()->request->get('nkey');
    $nuname = \Drupal::request()->request->get('nuname');
    $nemail = \Drupal::request()->request->get('nemail');
    $sid = \Drupal::request()->request->get('sid');
    $hostname = \Drupal::request()->request->get('hostname');
    $sso = \Drupal::request()->request->get('sso');
    $auth = $this->auth($server, $uname, $key);
    if (!empty($auth['error'])) {
      $content['status'] = ['#markup' => 'Error'];
      $content['errors'] = ['#markup' => implode("\n", $auth['error'])];
    } // if !empty(auth[error])
    else {
      if ($pass && !preg_match('!^[a-z$./0-9]+$!i', $pass)) {
        $content['status'] = ['#markup' => 'Error'];
        $content['errors'] = ['#markup' => 'Invalid password'];
      } // if pass has wrong format
      else {
        $account = $auth['account'];
        PWSync\NetworkFunc::updateFamily([
          'account' => $account,
          'uname' => $uname,
          'nuname' => $nuname,
          'nemail' => $nemail,
          'key' => $key,
          'nkey' => $nkey,
          'pass' => $pass,
          'except_children' => [$server],
          'except_parent' => FALSE,
        ]);
        $account->set('field_pw_sync_key', $nkey);
        if ($nuname) { $account->set('name', $nuname); }
        if ($nemail) { $account->set('mail', $nemail); }
        if ($pass) {
          $account->pass->pre_hashed = TRUE;
          $account->pass->value = $pass;
        }
        $account->save();
        $content['ok'] = ['#markup' => '200 OK'];
        // If SSO has been invoked and there isn't already a pending session
        if ($sid && $hostname && $sso && !PWSync\ResyncSharedFunc::fetchSession($account, $hostname)) {
          PWSync\ResyncSharedFunc::createSession($account, $sid, $hostname, $sso);
        }
      } // if is unset or correct format
    } // !empty auth[error]
    // This is effectively a RESTful POST. Never cache it.
    $content['#cache'] = ['max-age' => 0];
    return $this->bpRender($content);
  } // update

  /**
   * Informs a site that a password needs to be updated from upstream. Invoked server to client.
   *
   * @return array
   *    A render array containing a success or error code and message.
   */
  public function outdate() {
    $content = [];
    $uname = \Drupal::request()->request->get('uname');
    $nuname = \Drupal::request()->request->get('auname');
    if (!$uname) {
      $content['status'] = ['#markup' => 'Error'];
      $content['errors'] = ['#markup' => '500 No uname specified'];
    } // if !uname
    elseif ($account = user_load_by_name($uname)) {
      $config = \Drupal::config('pw_sync.settings');
      $url = $config->get('server');
      $key = $account->field_pw_sync_key->value;
      if ($url && $key) {
        global $base_url;
        $url .= '/pw/checkup?nothtml=1&notheme=1';
        $post = "server=$base_url&uname=" . $account->getAccountName() . "&auname=$nuname&key=$key";
        $post_num = 4;
        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_POST, $post_num);
        curl_setopt($c, CURLOPT_POSTFIELDS, $post);
        $c_res = curl_exec($c);
        $json = json_decode($c_res, TRUE);
        $pass = $json['pass'];
        $key = $json['key'];
        $nuname = $json['nuname'];
        $nemail = $json['nemail'];
        if (preg_match('!\$[SH]\$[a-zA-Z0-9.]+!', $pass) && preg_match('/^[0-9a-z]+$/', $key)) {
          PWSync\NetworkFunc::updateFamily([
            'account' => $account,
            'uname' => $uname,
            'nuname' => $nuname,
            'nemail' => $nemail,
            'key' => $key,
            'pass' => $pass,
            'except_children' => FALSE,
            'except_parent' => TRUE,
          ]);
          $account->pass->pre_hashed = TRUE;
          $account->pass->value = $pass;
          $account->set('field_pw_sync_key', $key)
            ->set('name', $nuname)
            ->set('mail', $nemail)
            ->save();
          $content['ok'] = ['#markup' => '200 OK'];
        } else {
          $content['status'] = ['#markup' => 'Error'];
          $content['errors'] = ['#markup' => '500 Client could not fetch new password.'];
        } // IF valid pw and key ELSE
      } // if url  && key
    } // if account loaded succesfully
    else {
      $content['status'] = ['#markup' => 'Error'];
      $content['errors'] = ['#markup' => '500 User does not exist'];
    } // if no account found
    // This is effectively a RESTful POST. Never cache it.
    $content['#cache'] = ['max-age' => 0];
    return $this->bpRender($content);
  } // outdate

  /**
   * Requests updates on a password from downstream. Invoked client to server.
   *
   * @return array
   *    A render array containing a json-encoded string of data.
   */
  public function checkup() {
    $server = \Drupal::request()->get('server');
    $uname = \Drupal::request()->get('uname');
    $key = \Drupal::request()->get('key');
    $nkey = \Drupal::request()->get('nkey');
    $auname = \Drupal::request()->get('auname');
    $auth = $this->auth($server, $uname, $key, $auname, $nkey);
    if (!empty($auth['error'])) {
      $content['status'] = ['#markup' => 'Error'];
      $content['errors'] = ['#markup' => implode("\n", $auth['error'])];
    } // if !empty(auth[error])
    else {
      $account = $auth['account'];
      $cached = PWSync\MiscFunc::getCacheData([$uname, $auname]);
      $result = [
        'pass' => ($cached && $cached['pass']) ? $cached['pass'] : $account->pass->value,
        'key' => ($cached && $cached['nkey']) ? $cached['nkey'] : $account->field_pw_sync_key->value,
        'nemail' => ($cached && $cached['nemail']) ? $cached['nemail'] : $account->getEmail(),
        'nuname' => ($cached && $cached ['nuname']) ? $cached['nuname'] : $account->getAccountName(),
      ];
      $content['json'] = ['#markup' => json_encode($result)];
    } // empty auth[error]
    $content['#cache'] = ['max-age' => 0];
    return $this->bpRender($content);
  } // checkup

  /**
   * Checks the availability of a username or email address.
   *
   * @return array
   *    A render array containing a success code and message or a json-encoded error message.
   */
  public function check_available() {
    $name = \Drupal::request()->get('nuname');
    $mail = \Drupal::request()->get('nemail');
    $source = \Drupal::request()->get('source');
    $collision = PWSync\NetworkFunc::checkAvailable($name, $mail, $source);
    if ($collision) {
      $content['json'] = ['#markup' => json_encode($collision)];
    } // if collision
    else {
      $content['ok'] = ['#markup' => '200 OK'];
    } // if !collision
    $content['#cache'] = ['max-age' => 0];
    return $this->bpRender($content);
  } // check_available

  /**
   * Begins the blind resync process
   *
   * @return array
   *    A render array containing a success or error code and messsage.
   */
  public function resync() {
    if (PWSync\MiscFunc::isClient()) {
      $result = $this->resync_client();
    } // PWSync\MiscFunc::isClient
    else {
      $result = $this->resync_server();
    } // isServer
    $content['result'] = ['#markup' => $result];
    // This is effectively a RESTful POST. Never cache it.
    $content['#cache'] = ['max-age' => 0];
    return $this->bpRender($content);
  } // resync

  /**
   * Single-sign on - Send our session up or down the chain and then redirect to the new location.
   *
   * @return mixed
   *    Redirect object.
   */
  public function sso() {
    if (\Drupal::currentUser()->hasPermission('use pw_sync')) {
      $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      $config = \Drupal::config('pw_sync.settings');
      if (PWSync\MiscFunc::isClient()) {
        // Send our session to the parent
        $sess_info = PWSync\ResyncSharedFunc::fetchSession(\Drupal\user\Entity\User::load(\Drupal::currentUser()->id()));
        $data = [
          'account' => $account,
          'uname' => $account->getAccountName(),
          'sid' => $sess_info['sid'],
          'sso' => $sess_info['sso'],
          'hostname' => $sess_info['hostname'],
        ];
        PWSync\NetworkFunc::updateParent($data);
        $redirect = $config->get('server') . '/user/sso?key=' . $sess_info['sso'];
      } // PWSync\MiscFunc::isClient
      else {
        // Send our session to the child site
        $redirect = PWSync\ResyncServerFunc::ssoUrl();
      } // isServer
      $result = new \Drupal\Core\Routing\TrustedRedirectResponse($redirect);
      $result->addCacheableDependency((new \Drupal\Core\Cache\CacheableMetadata())->setCacheMaxAge(0));
    } // user->hasPermission(use pw_sync)
    else {
      if ($key = \Drupal::request()->get('key')) {
        if ($session_info = PWSync\ResyncSharedFunc::findSession($key)) {
          PWSync\ResyncSharedFunc::deleteSession($session_info['sid']);
          $account = \Drupal\user\Entity\User::load($session_info['uid']);
          user_login_finalize($account);
        }
      } // _GET[key]
      $result = $this->redirect('user.page');
    } // !user->hasPermission
    //  See https://www.drupal.org/node/2023537 for how to use new goto
    return $result;
  } // sso

  /**
   * Client-side resync function
   *    When prompted, query the main server for the current pw_sync_key for the admin account
   *
   * @return string
   *    for display
   */
  protected function resync_client() {
    if ($site = PWSync\ResyncClientFunc::getSite()) {
      $syncs = PWSync\ResyncClientFunc::buildSend($site);
      $result = PWSync\ResyncClientFunc::processSync($syncs);
    }
    else {
      \Drupal::logger('pw_sync')->error('Password resync failed: Could not find base url');
      $result = json_encode(['error' => $this->t('Could not find base url')]);
    }
    return $result;
  } // resync_client

  /**
   * Server-side recsync function
   *    Verify the submitted HTTP authentication and username/email and send the current sync key
   *
   * @return string
   *    JSON for display
   */
  protected function resync_server() {
    $name = \Drupal::request()->get('name');
    $mail = \Drupal::request()->get('mail');
    $site = \Drupal::request()->get('site');
    if ($error = PWSync\ResyncServerFunc::invalidRequest($name, $mail, $site)) {
      $result = ['error' => $error];
    }
    else {
      if ($account = PWSync\ResyncServerFunc::loadAccount($name, $mail, $site)) {
        $result = ['data' => PWSync\ResyncServerFunc::buildResponse($account)];
      } // if account
      else {
        $result = ['error' => 'User not found'];
        \Drupal::logger('pw_sync')->warning('Resync failed: user @user/@mail not found', $watchdog_arr);
      }
    } // if all good
    return json_encode($result);
  } // resync_server

} // PwSyncController
