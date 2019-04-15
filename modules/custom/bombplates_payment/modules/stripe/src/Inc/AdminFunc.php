<?php

/**
 * @file
 *  Contains Drupal\stripe\Inc\AdminFunc
 */

namespace Drupal\stripe\Inc;


/**
 * admin form for stripe module
 */
class AdminFunc {

  /**
   * Generate the body of the admin form
   *
   * @return array - per drupal forms api
   */
  public static function Settings() {
    $form = [];
    $config = \Drupal::config('stripe.settings');
    $form['stripe_instructions'] = [
      '#type' => 'markup',
      '#value' => '<p>'
        . t('In order to get these values, log into your Stripe account and go to Account Settings -> API Keys. ')
        . '</p>',
    ];
    $form['stripe_publishable_key'] = [
      '#type' => 'textfield',
      '#title' => t('Publishable Key'),
      '#description' => t('This key will be publicly visible'),
      '#default_value' => $config->get('stripe_publishable_key'),
    ];
    $form['stripe_secret_key'] = [
      '#type' => 'textfield',
      '#title' => t('Secret Key'),
      '#description' => t('This key will not be publicly visible'),
      '#default_value' => $config->get('stripe_secret_key'),
    ];
    $form['stripe_test_publishable_key'] = [
      '#type' => 'textfield',
      '#title' => t('Test Publishable Key'),
      '#description' => t('This key will be publicly visible'),
      '#default_value' => $config->get('stripe_test_publishable_key'),
    ];
    $form['stripe_test_secret_key'] = [
      '#type' => 'textfield',
      '#title' => t('Test Secret Key'),
      '#description' => t('This key will not be publicly visible'),
      '#default_value' => $config->get('stripe_test_secret_key'),
    ];
    $form['stripe_test_mode'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable Test Mode?'),
      '#description' => t('If test mode is enabled, no money will exchange hands, and the test keys will be used.'),
      '#default_value' => $config->get('stripe_test_mode'),
    ];
    $form['stripe_default_descriptor'] = [
      '#type' => 'textfield',
      '#title' => t('Default Statement Descriptor'),
      '#description' => t('Default description to put on customer credit card statments'),
      '#maxlength' => 22,
      '#default_value' => $config->get('stripe_default_descriptor'),
    ];
    $options = MiscFunc::planOptions(TRUE, TRUE);
    $defaults = array_keys(MiscFunc::planOptions(FALSE, FALSE));
    $form['stripe_enabled_plans'] = [
      '#type' => 'checkboxes',
      '#title' => t('Enabled Plans'),
      '#options' => $options,
      '#default_value' => $defaults,
      '#suffix' => \Drupal\Core\Link::fromTextAndUrl(
        t('Add new plan'),
        \Drupal\Core\Url::fromUri('internal:/admin/config/payment/stripe')
      )->toString(),
    ];
    return $form;
  } // Settings
} // AdminFunc
