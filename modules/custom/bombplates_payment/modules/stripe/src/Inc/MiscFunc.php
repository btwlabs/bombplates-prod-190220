<?php

/**
 * @file
 *  Contains Drupal\stripe\Inc\MiscFunc
 */

namespace Drupal\stripe\Inc;


/*
 *  Misecllaneous helper public static functions for Stripe module
 */
class MiscFunc {

  /**
   * Load our StripeApi object
   *
   * @return object
   *  A StripeWrapper object
   */
  public static function loadApi() {
    $stripe = &drupal_static(__FUNCTION__);
    if (!$stripe) {
      $config = \Drupal::config('stripe.settings');
      $key = $config->get('stripe_test_mode') ? $config->get('stripe_test_secret_key') : $config->get('stripe_secret_key');
      if ($key) {
        $stripe = new \Drupal\stripe\Api\StripeWrapper($key);
      } else {
        \Drupal::logger('Stripe')->WARNING('Stripe API key(s) not set correctly all Stripe calls will fail.');
        $stripe = NULL;
      }
    } // if !stripe
    return $stripe;
  } // loadApi

  /**
   * Validate a value as a valid stripe timestamp
   *
   * @param mixed $arg
   *  int timestamp or "now"
   * @return boolean
   *  is $arg a valid timestamp > time or "now"
   */
  public static function isTimestamp($arg) {
    return (is_int($arg) && $arg >= time()) || ($arg == 'now');
  } // isTimestamp

  /**
   * Retreive Stripe plans as an options array
   *
   * @param boolean $include_disabled
   *  should both disabled and enabled plans be returned?
   * @param boolean $link_to_edit
   *  should the title of each field be a link to edit the plan? (admins only)
   * @return array
   *  per #options parameter of drupal forms api
   */
  public static function planOptions($include_disabled = FALSE, $link_to_edit = FALSE) {
    $options = [];
    $stripe = self::loadApi();
    if ($stripe) {
      $config = \Drupal::config('stripe.settings');
      $enabled_plans = $config->get('stripe_enabled_plans');
      $plans = $stripe->getPlans();
      foreach ($plans AS $plan) {
        if ($include_disabled || $enabled_plans[$plan->id]) {
          if ($link_to_edit && \Drupal::currentUser()->hasPermission('admin bombplates_payment')) {
            $name = \Drupal\Core\Link::fromTextAndUrl(
              $plan->name,
              \Drupal\Core\Url::fromUri('internal:/admin/config/payment/stripe/' . $plan->id)
            )->toString();
          } else {
            $name = $plan->name;
          }
          $options[$plan->id] = t(
            '@name ($@price / @count @time(s))',
            ['@name' => $name, '@price' => (float)($plan->amount/100), '@count' => $plan->interval_count, '@time' => $plan->interval]
          );
        } // if include_disabled || enabled
      } // foreach plan in plans
    } // if stripe
    return $options;
  } // planOptions
} // MiscFunc
