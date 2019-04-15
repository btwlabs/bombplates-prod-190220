<?php

/**
 * @file
 *  Contains Drupal\authorize_net\Inc\AdminFunc
 */

namespace Drupal\authorize_net\Inc;


/**
 *  admin settings form(s) for authorize_net module
 */
class AdminFunc {

  /**
   * Authorize.net settings form
   *
   * @return array per the drupal forms API
   */
  public static function settings() {
    $form = [];
    $config = \Drupal::config('authorize_net.settings');
    $form['authorize_net_api_login_id'] = [
      '#title' => t('API Login ID'),
      '#default_value' => $config->get('authorize_net_api_login_id'),
      '#type' => 'textfield',
    ];
    $form['authorize_net_transaction_key'] = [
      '#title' => t('API Transaction Key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('authorize_net_transaction_key'),
    ];
    $form['authorize_net_md5_hash_value'] = [
      '#title' => t('MD5 Hash Value (shared secret)'),
      '#type' => 'textfield',
      '#default_value' => $config->get('authorize_net_md5_hash_value'),
    ];
    $form['authorize_net_account_name'] = [
      '#title' => t('Account Login Name'),
      '#type' => 'textfield',
      '#default_value' => $config->get('authorize_net_account_name'),
    ];
    $form['authorize_net_test_mode'] = [
      '#title' => t('Enable test mode'),
      '#type' => 'checkbox',
      '#options' => [
        0 => 'disabled',
        1 => 'enabled',
      ],
      '#default_value' => $config->get('authorize_net_test_mode'),
    ];
    return $form;
  } // settings
} // AdminFunc
