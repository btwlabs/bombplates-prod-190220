<?php

/**
 * @file
 *  contains \Drupal\bombplates_forms\Inc\DnsFunc
 */

namespace Drupal\bombplates_forms\Inc;

/**
 *  Functions for adding/removing DNS records
 */
class DnsFunc {

  /**
   * Create a dnssimple record - Add a new entry to our DNSimple account.
   *
   * @param string $server
   *    URL of the server that is hosting the site
   * @param string $subdomain
   *    Subdomain to add to .bombplates.com for the entry.
   * @return mixed
   *    FALSE on failure or a json string returned from DNSimple
   */
  public static function add($server, $subdomain) {
    $result = FALSE;
    $dns = dns_get_record($server, DNS_A);
    $ip = isset($dns[0]['ip']) ? $dns[0]['ip'] : FALSE;
    if ($ip) {
      //POST variables
      $post = [
        'record' => [
          'content' => $ip,
          'name' => $subdomain,
          'record_type' => 'A',
          'ttl' => 3600
        ],
      ];
      $result = self::query('records', 'POST', $post);
    } // if ip
    return $result;
  } // add

  /**
   * Delete a DNS entry - Find and delete the DNS entry for a band's subdomain
   *
   * @param string $subdomain
   *    Subdomain of the entry to delete
   */
  public static function delete($subdomain) {
    $record = self::find($subdomain);
    // if we can't find the record, there's nothing to do
    if ($record) {
      $rec_id = $record->record->id;
      self::query("records/$rec_id", 'DELETE');
    }
  } // delete

  /**
   * POST a request to the DNSimple API
   *
   * @param string $path
   *    the end of the path to query
   * @param string $method
   *    HTTP method to use (GET|POST|PUT|DELETE)
   * @param array $post
   *    POST variables
   * @return object
   *    json-decoded results from DNSimple API or FALSE on failure
   */
  protected static function query($path, $method = 'GET', $post = []) {
    $dnsimple = \Drupal::config('bombplates_forms.settings')->get('dnsimple');
    $header = [];
    $curl_url = "https://api.dnsimple.com/v1/domains/bombplates.com/$path";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $curl_url);
    if (isset($dnsimple['token']) ) {
      $header[] = 'X-DNSimple-Domain-Token: ' . $dnsimple['token'];
      curl_setopt($curl, CURLOPT_HEADER,0);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
      $header[] = 'accept: application/json';
      $header[] = 'Content-Type: application/json';
      curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
      if (strtoupper($method) == 'POST') {
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post));
      } // if POST
      elseif (strtoupper($method) != 'GET') {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($post)) {
          curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post));
        }
      } // if GET
      curl_setopt($curl, CURLOPT_URL, $curl_url);
      $c_res = curl_exec($curl);
      if ($c_res == false) {
        \Drupal::logger('bombplates_forms')->WARNING(
          'dnssimple: curl_exec threw error "!err" for !url',
          ['!err'=>curl_error($curl), '!url'=>$curl_url]
        );
        $result = FALSE;
      } // if curl failed
      else {
        $result = json_decode($c_res);
      }
      curl_close($curl);
    } // if dnsimple[token]
    else {
      \Drupal::logger('bombplates_forms')->WARNING('DNSimple token not found. Giving up.');
      $result = FALSE;
    } // no auth token provided
    return $result;
  } // query

  /**
   * Find a specific record by subdomain name
   *
   * @param string $subdomain
   *    the subdomain
   * @return mixed
   *    object from DNSimple API or FALSE on failure
   */
  protected static function find($subdomain) {
    $result = FALSE;
    $records = self::getRecords();
    foreach ($records as $record) {
      if ($record->record->name == $subdomain) {
        $result = $record;
        break;
      } // if record->record->name = subdomain
    } // foreach record in records
    return $result;
  } // find

  /**
   * Get a full list of our DNS records, check local memory first, then query DNSimple
   *
   * @param boolean $force_refresh
   *    Force the system to query DNSimple
   * @return array
   *    DNS records from DNSimple
   */
  protected static function getRecords($force_refresh = FALSE) {
    $dns_records = &drupal_static(__FUNCTION__);
    if ($force_refresh || !$dns_records) {
      $dns_records = self::query('records');
    }
    return $dns_records;
  } // getRecords
} // DnsFunc
