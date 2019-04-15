<?php

/**
 * @file
 * Contains \Drupal\timezones\Controller\TimezonesController
 */

namespace Drupal\timezones\Controller;

/**
 * Controller routines for timezones routes
 */
class TimezonesController {

  /**
   * Returns the timezone offset for a location
   *
   * @param string $city
   *    Name of a city
   * @param string $state
   *    Name of a state, province, or region
   * @param string $zip
   *    Zip or postal code
   * @return array
   *    A render array consisting of only a single integer between -12 and +12
   */
  public function get($city = '', $state = '', $zip = '') {
    $result = 'UNDEFINED';
    $city = $city ? $city : \Drupal::request()->query->get('city', '');
    $city = trim(preg_replace('/[^a-zA-Z0-9]/', ' ', $city));
    $state = $state ? $state : \Drupal::request()->query->get('state', '');
    $state = trim(preg_replace('/[^a-zA-Z0-9]/', ' ', $state));
    $zip = $zip ? $zip : \Drupal::request()->query->get('zip', '');
    $zip = trim(preg_replace('/[^a-zA-Z0-9]/', ' ', $zip));

    // Ask Google
    if ($result == 'UNDEFINED') {
      $result = $this->askGoogle($city, $state, $zip);
    } // if !tx
    $content = [
      'offset' => [
        '#markup' => $result,
      ],
      '#cache' => [
        'contexts' => ['url'],
      ],
    ];
    $bombplates_nohtml_renderer = \Drupal::service('bombplates.renderer.bombplates_nohtml_renderer');
    return $bombplates_nohtml_renderer->renderNohtml($content, TRUE, FALSE);
  } // get

  /**
   * Query google for the timezone of a city/state/postal code
   *
   * @param string $city
   *    Name of a city
   * @param string $state
   *    Name of a state, province, or region
   * @param string $zip
   *    Zip or postal code
   * @return string
   *    Time zone offset from GMT or "UNDEFINED"
   */
  protected function askGoogle($city, $state, $zip) {
    $result = 'UNDEFINED';
    //get the latitude/longitude from google
    $g_url = 'https://www.google.com/maps/search/';
    $key = \Drupal::config('Bombplates.settings')->get('google_api_key');
    $geo_url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . str_replace(' ', '+', "$city+$state+$zip") . "&key=$key";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $geo_url);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Bombplates/1.0');
    $header = [
      'Content-Type: application/json; charset=UTF-8',
      'Content-Encoding: gzip',
    ];
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $c_res = curl_exec($curl);
    curl_close($curl);
    $geo = json_decode($c_res, TRUE);
    if ($geo && isset($geo['results'][0]['geometry']['location'])) {
      $lat = $geo['results'][0]['geometry']['location']['lat'];
      $lng = $geo['results'][0]['geometry']['location']['lng'];
      $tz_url = "https://maps.googleapis.com/maps/api/timezone/json?location=$lat,$lng&timestamp=" . time() . "&key=$key";
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $tz_url);
      curl_setopt($curl, CURLOPT_USERAGENT, 'Bombplates/1.0');
      curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
      curl_setopt($curl, CURLOPT_TIMEOUT, 10);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      $c_res = curl_exec($curl);
      curl_close($curl);
      $tz = json_decode($c_res, TRUE);
      if (isset($tz['rawOffset'])) {
        $result = ($tz['rawOffset'] + (isset($tz['dstOffset']) ? $tz['dstOffset'] : 0)) / 3600;
      }
    } // if geo[results][0][geometry][location]
    return (string)$result;
  } // askGoogle
} // TimezonesController
