<?php

/**
 * @file
 *  Contains Drupal\stripe\Inc\PaymentForm
 */

namespace Drupal\stripe\Inc;

use Drupal\bombplates_payment\Inc as BombplatesPayment;

/**
 *  helper public static functions to build and process Stripe's portion of the user payment form
 */
class PaymentForm {

  /**
   * generate the payment form
   *
   * @param &$form array
   *  per drupal forms api
   * @return array
   *  per drupal forms api
   */
  public static function build(&$form) {
    $account = $form['#account'];
    $stripe = MiscFunc::loadApi();
    if ($stripe) {
      $customer = $account->field_stripe_customer->value ? $stripe->getCustomer($account->field_stripe_customer->value) : NULL;
      $subscription = $customer && $account->field_stripe_subscription->value
        ? $stripe->getSubscription($account->field_stripe_subscription->value, $customer)
        : NULL;
      $trial_ends = isset($form['#trial_ends']) && $form['#trial_ends'] > \Drupal::time()->getRequestTime() ? $form['#trial_ends'] : strtotime($account->field_next_payment->value);
      $plan_options = MiscFunc::planOptions(FALSE, FALSE);
      if (count($plan_options) > 1) {
        $default_plan = is_a($subscription, 'Stripe_Subscription') ? $subscription->plan->id : key($plan_options);
        $form['stripe_plan'] = [
          '#type' => 'select',
          '#title' => t('Select your subscription plan'),
          '#options' => $plan_options,
          '#default_value' => $default_plan,
        ];
      } // if multiple plan_options
      else {
        $default_plan = key($plan_options);
        $form['stripe_plan'] = [
          '#type' => 'value',
          '#value' => $default_plan,
        ];
      } // if single plan_options
      $form['stripe_form'] = [
        '#theme' => 'stripe_payment_form',
        '#account' => $account,
      ];
      $form['stripe_token'] = [
        '#type' => 'hidden',
        '#default_value' => '',
      ];
      $form['stripe_customer'] = [
        '#type' => 'value',
        '#value' => $customer ? $customer->id : '',
      ];
      $form['stripe_subscription'] = [
        '#type' => 'value',
        '#value' => $subscription ? $subscription->id : '',
      ];
      $form['trial_ends'] = [
        '#type' => 'value',
        '#value' => $trial_ends,
      ];
      $form = array_merge(
        $form,
        BombplatesPayment\FormFunc::couponField($account),
        BombplatesPayment\FormFunc::authorizationField($account, $trial_ends)
      );
      $form['#validate'][] = self::class . '::validate';
      $form['#submit'][] = self::class . '::submit';
    } // if stripe
    return $form;
  } // build

  /**
   * validate public static function for stripe paymentForm
   *
   * @param array $form
   *   Per drupal forms api $form.
   * @param FormStateInterface &$form_state
   *   Per drupal forms api.
   */
  public static function validate($form, &$form_state) {
    BombplatesPayment\FormFunc::validateCoupon($form, $form_state);
    BombplatesPayment\FormFunc::validateAuthorization($form, $form_state);
    $stripe = MiscFunc::loadApi();
    if ($stripe) {
      $values = $form_state->getValues();
      $storage = $form_state->getStorage();
      $token_id = $values['stripe_token'];
      $token = $stripe->getToken($token_id, TRUE);
      if (!$token) {
        $form_state->setErrorByName('stripe_token', t('Unrecognized payment token. Please contact Bombplates support.'));
      } // if !token
      else {
        $storage['stripe']['stripe_token'] = $token;
      } // if token
      if (!$form_state->getErrors()) {
        $account = $form['#account'];
        $customer_id = $values['stripe_customer'];
        $subscription_id = $values['stripe_subscription'];
        $token_id = $storage['stripe']['stripe_token'];
        $plan = $stripe->getPlan($values['stripe_plan']);
        $balance = isset($plan->interval_count) && isset($values['missed_payment_count'])
          ? ($plan->amount/$plan->interval_count) * $values['missed_payment_count']
          : 0;
        $trial_end = max($values['trial_ends'], 'now', strtotime($account->field_next_payment->value)-(3600*24));
        $data = [
          'email' => $account->mail->value,
          'plan' => $plan->id,
          'source' => $token->id,
          'trial_end' => $trial_end,
        ];
        $customer = $customer_id ? $stripe->updateCustomer($customer_id, $data, $account) : $stripe->createCustomer($data, $account);
        if (!$customer) {
          $form_state->setErrorByName('stripe_token', t('Error registering payment information'));
        } // if !customer
        elseif ($balance) {
          $charge_data = [
            'currency' => 'usd',
            'amount' => (int)$balance,
            'description' => t('Makeup payment'),
            'customer' => $customer->id
          ];
          $charge = $stripe->createCharge($charge_data, TRUE);
          if (!$charge) {
            $form_state->setErrorByName('stripe_token', t('Error charging missed payment(s)'));
            // Cancel the subscription we just created.
            $subscription = reset($customer->subscriptions->data);
            $subscription = stripe_api_delete_subscription($subscription, $customer->id);
          } // !charge
          else {
            $storage['stripe']['stripe_charge'] = $charge->id;
          } // if charge
        } // if balance
        $storage['stripe']['stripe_customer'] = $customer->id;
        $storage['stripe']['stripe_subscription'] = reset($customer->subscriptions->data)->id;
      } // !form_state->getErrors()
      $form_state->setStorage($storage);
    } // if stripe
    else {
      $form_state->setErrorByName('stripe_token', t('Payment gateway misconfigured. Please contact support.'));
    } // !stripe
  } // validate

  /**
   * submit public static function for stripe paymentForm
   *
   * @param array $form
   *   Per drupal forms api.
   * @param FormStateInterface &$form_state
   *   Per drupal forms api.
   */
  public static function submit($form, &$form_state) {
    $account = $form['#account'];
    //deal with their coupon code
    BombplatesPayment\FormFunc::submitCoupon($form, $form_state);
    $stripe = MiscFunc::loadApi();
    if ($stripe) {
      $values = $form_state->getValues();
      $storage = $form_state->getStorage();
      $customer = $stripe->getCustomer($storage['stripe']['stripe_customer']);
      $subscription = $stripe->getSubscription($storage['stripe']['stripe_subscription'], $customer);
      if ($subscription->plan->id != $values['stripe_plan']) {
        $data = [
          'plan' => $values['stripe_plan'],
          'source' => $storage['stripe']['stripe_token'],
        ];
        $subscription = $stripe->updateSubscription($subscription->id, $customer, $data);
      } // if plan changed
      $account->set('field_stripe_customer', $customer->id)
        ->set('field_stripe_subscription', $subscription->id)
        ->save();
    } // if stripe
  } // submit
} // PaymentForm
