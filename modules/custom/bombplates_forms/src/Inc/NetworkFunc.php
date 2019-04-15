<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Inc\NetworkFunc
 */

namespace Drupal\bombplates_forms\Inc;

/*
 *  Static public static functions that interact with downstream servers
 */
class NetworkFunc {

  /**
   * Find which of our hosting servers has the most space available
   *
   * @return string
   *  domain name of a server (e.g. bandsX.bombplates.com)
   */
  public static function findSpace() {
    $servers = \Drupal::config('bombplates_forms.settings')->get('hosting_servers');
    $curr_best = 1000; // If all our servers are at 10,000% capacity, something is wrong!
    $result = '';
    foreach ($servers AS $server) {
      $space = self::checkSpace($server);
      if ($space < $curr_best) {
        $curr_best = $space;
        $result = $server;
      } // space < curr_best
    } // foreach server in servers
    return $result;
  } // findSpace

  /**
   * Find out how full a server is
   *
   * @param string $server
   *  domain name of a server to check
   * @return float
   *  how full is this server (1 being estimated max capacity)
   */
  protected static function checkSpace($server) {
    $url = "http://$server/afcs.php";
    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    $c_res = curl_exec($c);
    $result = (float)$c_res;
    if ($result == 0 && $c_res !== '0') {
      $result = 1000;
      \Drupal::logger('bombplates_forms')->ERROR('Invalid results returned by afcs on @server: @res', ['@server' => $server, '@res' => $c_res]);
    }
    return $result;
  } // checkSpace

  /**
   * Tell a band site server to run it's bandsXtools scripts. Call prompt.php on a band site server.
   *
   * @param array $servers
   *  String FQDNs of the servers to prompt, also accepts a single string
   */
  public static function promptScripts($servers) {
    if (is_string($servers)) {
      $servers = [$servers];
    }
    elseif (empty($servers)) {
      $servers = \Drupal::config('bombplates_forms.settings')->get('hosting_servers');
    }
    foreach ($servers AS $server) {
      if ($fqdn = MiscFunc::validateFqdn($server)) {
        exec("curl https://$fqdn/prompt.php > /dev/null 2>&1 &");
      }
    } // foreach server in servers
  } // promptScripts
} // NetworkFunc
