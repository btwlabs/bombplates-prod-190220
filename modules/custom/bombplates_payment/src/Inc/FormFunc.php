<?php

/**
 * @file
 *  Contains Drupal\bombplates_payment\Inc\FormFunc
 */

namespace Drupal\bombplates_payment\Inc;


/**
 *  Functions to generate common form elements for payment forms
 */
class FormFunc {

  /**
   * Add an authorization checkbox field to a payment form
   *
   * @param object $account
   *  user object
   * @param int $trial_ends
   *  timestamp the user's trial ends
   * @return array
   *  per drupal forms api
   */
  public static function authorizationField($account, $trial_ends) {
    $field = [];
    $config = \Drupal::config('bombplates_payment.settings');
    if ($trial_ends < \Drupal::time()->getRequestTime()) {
      $auth_date = date('F j, Y', mktime(0,0,0,date('m')+1,1,date('Y')));
    } else {
      $auth_date = date('F j, Y', $trial_ends);
    }
    $auth_text = t('I certify that included billing information is my own and is accurate. ') . '<br/>'
      . t(
        'Further, I authorize Bombplates to charge my bank account or credit card a recurring amount of @PRICE USD per @MONTH month(s) beginning @DATE.',
        [
          '@PRICE' => new \Drupal\Component\Render\FormattableMarkup('<span class="bpp-price">' . $config->get('base_fee') . '</span>', []),
          '@MONTH' => new \Drupal\Component\Render\FormattableMarkup('<span class="bpp-time">1</span>', []),
          '@DATE' => $auth_date,
        ]
      ) . '<br/>';
    if ($account->field_missed_payments->value) {
      $cost = $account->field_missed_payments->value * $config->get('base_fee');
      $auth_text .= t(
        'I also agree to be charged <span class="bpp-missed">@MISSED</span> USD immediately for previous missed payments. ',
        ['@MISSED' => $cost]
      ) . '<br/>';
    } // if missed_payments
    $field['authorization'] = [
      '#type' => 'checkbox',
      '#title' => t('Authorization'),
      '#description' => $auth_text,
      '#options' => [
        0 => 0,
        1 => 1
      ],
      '#default_value' => 1,
      '#required' => TRUE,
      '#weight' => 50,
    ];
    return $field;
  } // authorizationField

  /**
   * Form validation public static function for authorizationField
   *
   * @param array $form
   *  Per Drupal forms api
   * @param &$form_state object
   *  Per Drupal forms api
   */
  public static function validateAuthorization($form, &$form_state) {
    if (!$form_state->getValue('authorization')) {
      $form_state->setErrorByName('authorization', t('You must click the checkbox to authorize us to charge your account.'));
    }
  } // validateAuthorization

  /**
   * Create a coupon code field for payment forms
   *
   * @param object $account
   *  User object
   * @return array
   *  Per Drupal forms api
   */
  public static function couponField($account) {
    $field = [];
    if (!MiscFunc::isCustomer($account) && !$account->field_referral_entered->value) {
      $field['coupon'] = [
        '#type' => 'textfield',
        '#title' => t('Coupon or Referral Code'),
      ];
    } // if !account is customer
    return $field;
  } // couponField

  /**
   * Validate a coupon code field
   *
   * @param array $form
   *  per drupal forms api
   * @param &$form_state object
   *  per drupal forms api
   */
  public static function validateCoupon($form, &$form_state) {
    $coupon = $form_state->getValue('coupon');
    if ($coupon) {
      // TODO: self::checCoupon is apparently deprecated
      if ($coup_val = self::checkCoupon($coupon, FALSE)) {
        $trial_ends = $form_state->getValue('trial_ends');
        $trial_ends += $coup_val * 2592000;
        $form_state->setValue('trial_ends', $trial_ends);
      } // if coup_val
      else {
        $form_state->setErrorByName('coupon', t('Invalid coupon or referral code.'));
      } // !coup_val
    } // if coupon
  } // validateCoupon

  /**
   * Check the validity of a coupon code
   *
   * @param string $coupon
   *   Coupon as submitted by user.
   * @param boolean $decrement_coupon
   *   Should the coupon be counted as "used" if successful.
   * @return int
   *   Number of months of trial granted by this coupon.
   */
  function checkCoupon($coupon, $decrement_coupon=FALSE) {
    return \Drupal\bombplates_payment\EventSubscriber\BombplatesPaymentCouponSubscriber::checkCoupon($coupon, $decrement_coupon);
  } // checkCoupon

  /**
   * Submit a coupon code field
   *
   * @param array $form
   *  per drupal forms api
   * @param &$form_state object
   *  per drupal forms api
   */
  public static function submitCoupon($form, &$form_state) {
    $account = $form['#account'];
    if ($coupon = $form_state->getValue('coupon')) {
      $trial_ends = $form_state->getValue('trial_ends');
      if ($coup_val = self::checkCoupon($coupon, TRUE)) {
        $account->field_trial_ends->value = $trial_ends;
        $account->field_referral_entered->value = $coupon;
      } // if coup_val
      if ($trial_ends > \Drupal::time()->getRequestTime()) {
        $account->field_next_payment->value = $trial_ends + 86400;
      }
      $account->save();
    } // if coupon
  } // submitCoupon
} // FormFunc
