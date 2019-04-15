<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Inc\CmdFunc
 */

namespace Drupal\bombplates_forms\Inc;

/**
 *  miscellaneous shared public static functions for bombplates_forms module
 */
class CmdFunc {

  /**
   * Queue up a standard command in the database: /usr/bandsXtools/ACTIONSite.pl -v -v -u SITE -s SUBDOMAIN
   *
   * @param string $action
   *  del|suspend|unsuspend
   * @param string $site
   *  Public-facing domain name, possibly with protocol (e.g. http://www.someband.com)
   * @param string $subdomain
   *  Official subdomain (e.g. someband)
   * @param string $server
   *  Hosting server to launch the command on
   * @return int
   *  insert ID on success. FALSE on failure
   */
  public static function standardCommand($action, $site, $subdomain, $server) {
    $action = strtolower($action);
    if (!in_array($action, ['del', 'suspend', 'unsuspend', 'new'])) {
      self::commandError('Launching command: Illegal action attempted.');
      return FALSE;
    } // if action is not del|suspend|unsuspend
    $domain = preg_replace('!^(https?://)?(www\.)?!', '', $site);
    if (!$domain) { $domain = $subdomain . '.bombplates.com'; }
    $cmd = "/usr/bandsXtools/$action"."Site.pl -v -v -u $domain -s $subdomain";
    return self::constructedCommand($cmd, $server, $subdomain, $domain);
  } // standardCommand

  /**
   * Build launch account commands
   *
   * @param object $account
   *    User object
   * @param int $theme
   *    ID of the theme to use
   * @param string $server
   *    Server to launch commands on
   */
  public static function doLaunchCommands($account, $theme, $server) {
    if (!$theme) { $theme = 1058; }
    // remove single quotes from our data
    $domain = str_replace("'", '\'"\'"\'', preg_replace('!^([a-z]*://)?(www\.)?!i', '', escapeshellcmd($account->field_websites->value)));
    $subdomain = str_replace("'", '\'"\'"\'', escapeshellcmd($account->field_subdomain->value));
    $band = str_replace("'", '\'"\'"\'', escapeshellcmd($account->field_band_name->value));
    $pw_key = str_replace("'", '\'"\'"\'', $account->field_pw_sync_key->value);
    $mail = str_replace("'", '\'"\'"\'', escapeshellcmd($account->mail->value));
    $name = str_replace("'", '\'"\'"\'', escapeshellcmd($account->name->value));
    $pass = str_replace("'", '\'"\'"\'', escapeshellcmd($account->pass->value));
    $ip = \Drupal::request()->getClientIp();
    $sid = \Drupal::request()->getSession()->getId();
    $cmds = [
      "/usr/bandsXtools/newSite.pl -v -v -u '$domain' -b ' $band ' -d '$subdomain' -e '$mail' ",
      "/usr/bandsXtools/newSite-theme.pl -v -v -url '$subdomain.bombplates.com' -t '$theme' -b ' $band ' -usr '$name' -e '$mail' -ph ' $pass ' -k ' $pw_key ' -ip '$ip' -sid ' $sid '",
      $finalizeTheme = "/usr/bandsXtools/finalizeTheme.pl -v -v -u '$subdomain.bombplates.com'",
    ];
    if (self::insertCommandsTransaction($cmds, $server, $subdomain, $domain)) {
      NetworkFunc::promptScripts($server);
    }
    else {
      $support_url = \Drupal\Core\Url::fromUri('https://bombplates.desk.com');
      $support_link = \Drupal\Core\Link::fromTextAndurl(t('contact support'), $support_url)->toString();
      drupal_set_message(t('There was a critical error launching your site. Please @m immediately.', ['@m' => $support_link]));
    }
  } // doLaunchCommands

  /**
   * Transactionally queue one or more commands, reverting on failure
   *
   * @param array $cmds
   *    Strings per constructedCommand
   * @param string $server
   *    Per constructedCommand
   * @param string $subdomain
   *    Per constructedCommand
   * @param string $domain
   *    Per constructedCommand
   * @return boolean
   *    Was the transaction completed without error?
   */
  protected static function insertCommandsTransaction($cmds, $server, $subdomain, $domain) {
    $cids = [];
    foreach ($cmds AS $cmd) {
      if ($cid = self::constructedCommand($cmd, $server, $subdomain, $domain)) {
        $cids[] = $cid;
      } // if insert success
      else { // Something failed. Revert our other changes
        foreach ($cids AS $cid) {
          self::cancelCommand($cid);
        }
        \Drupal::logger('bombplates_forms')->ERROR('Critical Error: Failed to insert command into db: "@cmd"', ['@cmd' => $cmd]);
        return FALSE;
      } // if insert failed
    } // foreach cmd in cmds
    return TRUE;
  } // doAbortableCommands

  /**
   * Insert a fully-constructed command into the database
   *
   * @param string $cmd
   *  Full invocation of a recognized perl script
   * @param string $server
   *  Name of one of our hosting servers
   * @param string $subdomain
   *  Subdomain of the site being affected
   * @param string $domain
   *  FQDN of the site being affected (optional)
   * @return int
   *  Insert ID on success. FALSE on failure.
   */
  public static function constructedCommand($cmd, $server, $subdomain, $domain = FALSE) {
    $result = FALSE;
    if (self::validateCommand($cmd, $server, $subdomain, $domain)) {
      $result = self::insertCommand($cmd, $server);
    }
    return $result;
  } // constructedCommand

  /**
   * Perform some basic validation that all commands are subject to
   *
   * @param string $cmd
   *  Full invocation of a recognized perl script
   * @param string $server
   *  Name of one of our hosting servers
   * @param string $subdomain
   *  Subdomain of the site being affected
   * @param string $domain
   *  FQDN of the site being affected (optional)
   * @return boolean
   *  Does everything look legit?
   */
  protected static function validateCommand($cmd, $server, $subdomain, $domain = FALSE) {
    $preg_bad_domain = '/(^[-])|([^a-z0-9._-])|(^$)/i';
    if ($domain && preg_match($preg_bad_domain, $domain)) {
      self::commandError('Launching command: Invalid domain (@d) supplied.', ['@d' => $domain]);
      return FALSE;
    }
    if (preg_match($preg_bad_domain, $subdomain)) {
      self::commandError('Launching command: Invalid subdomain (@s) supplied.', ['@s' => $subdomain]);
      return FALSE;
    }
    $servers = \Drupal::config('bombplates_forms.settings')->get('hosting_servers');
    if (!in_array($server, $servers)) {
      self::commandError('Launching command: Server (@s) not found in @l.', ['@s' => $server, '@l' => print_r($servers,TRUE)]);
      return FALSE;
    }
    $good_cmds = ['delSite', 'suspendSite', 'finalizeTheme', 'newSite', 'unsuspendSite', 'newSite-theme'];
    $preg_good_cmd = '!/usr/bandsXtools/(' . implode('|', $good_cmds) .').pl!';
    if (!preg_match($preg_good_cmd, $cmd)) {
      self::commandError('Launching command: Command not found in @w', ['@w' => print_r($good_cmds, TRUE)]);
      return FALSE;
    }
    return TRUE;
  } // validateCommand

  /**
   * Cancel an existing command before it runs
   *
   * @param int $cid
   *  cid field from bombplates_account_commands table
   * @return int
   *  Rows deleted
   */
  public static function cancelCommand($cid) {
    return \Drupal\Core\Database\Database::getConnection('default')->update('bombplates_account_commands')
      ->fields(['time_sent' => -1])
      ->condition('cid', $cid, '=')
      ->execute();
  } // cancelCommand

  /**
   * Insert a command into the database
   *
   * @param string $cmd
   *  Full command to insert
   * @param string $server
   *  Hosting server to launch the command on
   * @return int
   *  Insert ID
   */
  protected static function insertCommand($cmd, $server) {
    $fields = [
      'server' => $server,
      'command' => $cmd,
      'time_sent' => 0,
    ];
    return \Drupal\Core\Database\Database::getConnection('default')->insert('bombplates_account_commands')
      ->fields($fields)
      ->execute();
  } // insertCommand

  /**
   * Log an error from executing a standard command
   *
   * @msg string
   *  per watchdog system
   * @msg array
   *  per watchdog system
   */
  protected static function commandError($msg, $arr = []) {
    $msg .= ' @t';
    $arr['@t'] = print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), TRUE);
    \Drupal::logger('bombplates_forms')->ERROR($msg, $arr);
  } // commandError

} // CmdFunc
