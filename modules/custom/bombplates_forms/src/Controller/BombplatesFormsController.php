<?php

/**
 * @file
 * Contains \Drupal\bombplates_forms\Controller\BombplatesFormsController
 */

namespace Drupal\bombplates_forms\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\bombplates_forms\Inc as BombplatesForms;

/**
 * Controller routines for bombplates_forms routes
 */
class BombplatesFormsController extends ControllerBase {

  /**
   * Return the days remaining in a user's trial
   *
   * @return array
   *    A render array containing the remaining time
   */
  public function trial() {
    $result = 0;
    $uname = \Drupal::request()->get('uname');
    if (!$uname) {
      $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      $uname = $account->getAccountName();
    } // if !uname
    else {
      $account = user_load_by_name($uname);
    } // if uname
    if ($account && !$account->hasRole('customer')) {
      $trial_field = $account->get('field_trial_ends');
      $trial_ends = $trial_field->isEmpty() ? \Drupal::time()->getRequestTime() : $trial_field->first()->value;
      $sec_left = (is_int($trial_ends) ? $trial_ends : strtotime($trial_ends)) - \Drupal::time()->getRequestTime();
      $result = ceil($sec_left / 86400); // seconds in a day
    }
    $content = [
      'trial' => [
        '#type' => 'markup',
        '#markup' => $result,
      ],
    ];
    $bombplates_nohtml_renderer = \Drupal::service('bombplates.renderer.bombplates_nohtml_renderer');
    return $bombplates_nohtml_renderer->renderNohtml($content, TRUE, FALSE);
  } // trial

  /**
   * Spin your wheels for a second before redirecting a user to their new site
   */
  public function launchSite() {
    $subdomain = \Drupal::request()->query->get('subdomain');
    if (!$subdomain) {
      $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      $subdomain = $account->field_subdomain->value;
    } // if subdomain not specified in _GET
    $sso_url = \Drupal\pw_sync\Inc\ResyncServerFunc::ssoUrl();
    return [
      'content' => [
        '#theme' => 'bombplates_forms_launch_page',
        '#subdomain' => $subdomain,
        '#sso_url' => $sso_url,
      ],
      '#cache' => ['contexts' => ['user', 'url.query_args:subdomain']],
    ];
  } // launchSite

  /**
   * Retreive a list of pending account commands
   *
   * @return array
   *    Render array - commands to execute
   */
  public function getCommands() {
    $content = [];
    $host_name = $this->isHost('get_commands');
    if (!$host_name) {
      $content['error'] = [
        '#type' => 'markup',
        '#markup' => $this->t('Warning: You are not authorized to access this resource.'),
      ];
    } // if ! host_name
    else {
      try {
        $db = \Drupal::database();
        $select = $db->select('bombplates_account_commands', 'ac')
          ->fields('ac', ['cid', 'command'])
          ->condition('time_sent', 0, '=')
          ->condition('server', $host_name, '=')
          ->execute();
        $cids = [];
        while ($q_row = $select->fetchAssoc()) {
          $cmds[] = '/usr/bandsXtools/launch_command.pl ' . $q_row['command'];
          $cids[] = $q_row['cid'];
        } // while fetching db results
        if (count($cids) > 0) {
          $update = $db->update('bombplates_account_commands')
            ->fields(['time_sent' => \Drupal::time()->getRequestTime()])
            ->condition('cid', $cids, 'IN')
            ->execute();
        }
      } catch (\Exception $e) {
        \Drupal::logger('bombplates_forms')->ERROR('Database error retrieving password commands: @err', ['@err' => $e->getMessage()]);
        $content['error'] = [
          '#type' => 'markup',
          '#markup' => $this->t('Database error retrieving password commands'),
        ];
      } // try/catch
    } // if host_name
    if (!empty($cmds)) {
      $result = FALSE;
      if (extension_loaded('gnupg')) {
        $config = \Drupal::config('bombplates_forms.settings');
        $path = $config->get('gpg_encrypt_path');
        $recipient = $config->get('gpg_recipient');
        $realpath = substr($path, 0, 1) == "/" ? $path : getcwd() . "/$path";
        //check our key
        try {
          putenv("GNUPGHOME=$realpath/.gnupg");
          $gpg = new \gnupg();
          if (!$gpg->addencryptkey($recipient)) {
            $keyfile = "$path/" . $config->get('gpg_public_key');
            $public = $gpg->import(file_get_contents($keyfile));
            if (!$public) {
              throw new \Exception($this->t('Could not import key file (@p) because "@e"', ['@p' => $keyfile, '@e' => $gpg->geterror()]));
            }
            if ($gpg->addencryptkey($public['fingerprint'])) {
              $result = $gpg->encrypt(implode("\n", $cmds));
            }
            else {
              throw new \Exception($this->t('Could not add encryption key because "@e".', ['@e' => $gpg->geterror()]));
            }
          } // if addencryptkey(recipient)
          else {
            $result = $gpg->encrypt(implode("\n", $cmds));
          }
        } // try
        catch (\Exception $e) {
          \Drupal::logger('bombplates_forms')->WARNING('gpg failure: @e', ['@e' => $e->getMessage()]);
        } // catch
      }  // if gpg
      $content['result'] = [
        '#type' => 'markup',
        '#markup' => $result ? $result : implode("\n", $cmds),
      ];
    } // !empty(cmds)

    // Never cache
    $content['#cache'] = ['max-age' => 0];
    $bombplates_nohtml_renderer = \Drupal::service('bombplates.renderer.bombplates_nohtml_renderer');
    return $bombplates_nohtml_renderer->renderNohtml($content, TRUE, FALSE);
  } // getCommands

  /**
   * Verify that the server we're talking to right now is in our list of hosting servers
   *
   * @param string $function
   *    What action is the system attempting to complete?
   * @return boolean
   *    Is the server authorized?
   */
  protected function isHost($function = 'get_commands') {
    $result = FALSE;
    $config = \Drupal::config('bombplates_forms.settings');
    $servers = $config->get('hosting_servers');
    $known_ips = [];
    foreach ($servers AS $server) {
      $dns = gethostbynamel($server);
      $server_ip = $dns[0];
      $known_hosts[$server_ip] = $server;
    } // foreach server in servers
    // Note: getClientIps is discouraged, but we are comparing to a very limited whitelist.
    //  Clients have little ability to masquerade as a trusted server without compromising dns
    $received_ips = \Drupal::request()->getClientIps();
    foreach ($received_ips AS $received_ip) {
      if (isset($known_hosts[$received_ip])) {
        $result = $known_hosts[$received_ip];
        break;
      }
    } // foreach received_ip in received_ips
    if (!$result) {
      \Drupal::logger('bombplates_forms')->INFO(
        'Invalid visitor (@ips) attempted access to hosting function @func',
        ['@ips' => implode(',', $received_ips), '@func' => $function]
      );
    } // !result
    return $result;
  } // isHost

  /**
   * Cancellation is confirmed page
   *
   * @return array
   *    Render array
   */
  public function bye() {
    return [
      'message' => [
        '#type' => 'markup',
        '#markup' => $this->t('We are sorry to see you go! Your site has been deleted, and you will not be billed further.'),
      ],
    ];
  } // bye
} // BombplatesFormsController
