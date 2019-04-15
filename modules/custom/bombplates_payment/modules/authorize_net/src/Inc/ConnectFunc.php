<?php

/**
 * @file
 *  Contains Drupal\authorize_net\Inc\ConnectFunc
 */

namespace Drupal\authorize_net\Inc;

/**
 *  Helper public static functions for processing XML and sending requests to Authorize.net
 */
class ConnectFunc {
  /**
   * Send an authorize.net ARB request
   *
   * @param string $xml_req
   *  xml to pass into the API
   * @param string $req_type
   *  name of the request type (optional, for loggin)
   * @return mixed
   *  array of results from auth.net or FALSE on error
   */
  public static function sendRequest($xml_req, $req_type) {
    $curl_url = \Drupal::config('authorize_net.settings')->get('authorize_net_test_mode')
      ? 'https://apitest.authorize.net/xml/v1/request.api'    // test
      : 'https://api.authorize.net/xml/v1/request.api';       // live
    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $curl_url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
    curl_setopt($c, CURLOPT_HEADER, 1);
    curl_setopt($c, CURLOPT_POSTFIELDS, $xml_req);
    curl_setopt($c, CURLOPT_POST, 1);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
    $http_res = curl_exec($c);

    $output = explode("\r\n", $http_res);
    //ensure our web request succeeded
    if (!preg_match('/HTTP.[0-9.]+ 200 OK/', $http_res)) {
      \Drupal::logger('authorize_net')->ERROR(
        'Authorize.net query failed @OUT',
        ['@OUT' => json_encode($output)]
      );
      drupal_set_message('There was a network error. Please contact support.', 'error');
      return FALSE;
    } // !200 ok
    $xml_res = '';
    foreach ($output as $line) {
      if (preg_match('/<\?xml/', $line)) {
        $xml_res = $line;
      }
    } // foreach line in output
    $tree = self::xmlToArray($xml_res);
    return $tree;
  } // sendRequest

  /**
   * Convert our xml response into an array
   *
   * @param string $data
   *  xml returned from authorize.net
   * @return array
   *  data pulled from $data
   */
  protected static function xmlToArray($data) {
    $result = [];
    // Match  <TAG[ATTRIBUTES]>DATA</TAG
    //    TAG is greedy
    //    ATTRIBUTES is greedy
    //    DATA is lazy
    $reg_single = '!<([^<> ]+)[^>]*>(.*?)</\1!';
    if (preg_match_all($reg_single, $data, $matches, PREG_SET_ORDER)) {
      //recurse
      foreach ($matches as $match) {
        // Match <TAG[ATTRIBUTES]>DATA</TAG[GARBAGE]<TAG
        //    TAG is from previous match
        //    ATTRIBUTES is greedy
        //    DATA is lazy
        //    GARBAGE is greedy
        $reg_check_multi = '!<'.$match[1].'[^>]*>(.*?)</'.$match[1].'.*<'.$match[1].'!'; //multiples of the same tag
        if (preg_match($reg_check_multi, $data)) {
          if (!$result[$match[1]]) { //if we haven't already done this one
            // Match <TAG[ATTRIBUTES]>DATA</TAG
            //    TAG is from previous match
            //    ATTRIBUTES is greedy
            //    DATA is lazy
            $reg_multi = '!<'.$match[1].'[^>]*>(.*?)</'.$match[1].'!'; //multiples of the same tag
            preg_match_all($reg_multi, $data, $multi_matches, PREG_SET_ORDER);

            foreach ($multi_matches as $multi_match) {
              // Recurse
              $result[$match[1]][] = self::xmlToArray($multi_match[1]);
            } // foreach multi_match in multi_matches
          } // if result does not contain tag yet
        } // if data matches
        else { // !preg_match($reg_check_multi, $data)
          // Recurse
          $result[$match[1]] = self::xmlToArray($match[2]);
        }
      } // foreach match in matches
    } // if $data matches XML regex
    else {
      // trivial case: raw string w/ no xml
      $result = $data;
    } // if $data doe not match XML regex
    return $result;
  } // xmlToArray

  /**
   * Convert an array into XML
   *
   * @param array $arr
   *  of data to convert to xml
   * @param string $space
   *  spacing character. Not currently used
   * @param string $root_attr
   *  default attribute to give to top xml elements
   * @return string
   *  xml
   */
  protected static function arrayToXml($arr, $space='', $root_attr='') {
    $result = '';
    foreach ($arr as $key => $val) {
      $result .= "<$key";
      if ($root_attr) {
        $result .= " $root_attr";
        $root_attr = '';
      } // if root_attr
      $result .= '>';
      if (is_array($val)) {
        // Recurse
        $result .= self::arrayToXml($val);
      } // if val is array
      else {
        $result .= $val;
      } // if val is not array
      $result .= "</$key>";
    } // foreach key=>val in arr
    return $result;
  } // arrayToXml

  /**
   * Format XML for authorize.net from an array of data
   *
   * @param array $data
   *  of data to convert to xml
   * @param string $root_attr
   *  attribute to give top-level XML elements
   * @return string
   *  xml code
   */
  public static function buildXml($data, $root_attr) {
    $result = '<?xml version="1.0" encoding="UTF-8"?>';
    $result .= self::arrayToXml($data, '', $root_attr);
    return $result;
  } // buildXml
} // ConnectFunc
