<?php

/**
 * @file
 *   theming and preprocess functions for stripe module
 */

use Drupal\stripe\Inc as StripeFunc;

/**
 * Preprocess function for theme(stripe_payment_form)
 *
 * @param &$variables array
 *  per drupal theme layer
 */
function template_preprocess_stripe_payment_form(&$variables) {
  $stripe = StripeFunc\MiscFunc::loadApi();
  $config = \Drupal::config('stripe.settings');
  $account = $variables['account'];
  $stripe_customer = $stripe->getCustomer($account->field_stripe_customer->value);
  $stripe_subscription = $stripe->getSubscription($account->field_stripe_subscription->value, $stripe_customer);
  $enabled_plans = (array)$config->get('stripe_enabled_plans');
  $plans = [];
  foreach ($stripe->getPlans() AS $plan) {
    if (isset($enabled_plans[$plan->id]) && $enabled_plans[$plan->id]) {
      $plans[$plan->id] = ['cost' => (float)($plan->amount/100), 'time' => $plan->interval_count];
    } // if enabled_plans[plan->id]
  } // foreach plan in $stripe->getPlans
  $variables['#attached']['library'][] = 'stripe/payment-form';
  $variables['#attached']['drupalSettings']['stripe']['plans'] = $plans;
  $default_plan = reset($plans);
  $variables['#attached']['drupalSettings']['stripe']['default_cost'] = $default_plan['cost'];
  $variables['#attached']['drupalSettings']['stripe']['default_time'] = $default_plan['time'];
  $variables['#attached']['drupalSettings']['stripe']['missed_payments'] = $account->field_missed_payments->value;
  $pub_key = $config->get('stripe_test_mode') ? $config->get('stripe_test_publishable_key') : $config->get('stripe_publishable_key');
  $variables['#attached']['drupalSettings']['stripe']['pub_key'] = $pub_key;
} // template_preprocess_stripe_payment_form
