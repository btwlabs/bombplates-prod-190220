<?php

/**
 * @file
 *  Contains Drupal\stripe\Inc\CronFunc
 */

namespace Drupal\stripe\Inc;

use Drupal\bombplates\Inc as Bombplates;

/**
 *  Cron helper public static functions for stripe payment module
 */
class CronFunc {

  /**
   * Find recent payments and log them to the database
   *
   * @param int $time
   *  timestamp
   */
  public static function logPaymentsSince($time) {
    $stripe = MiscFunc::loadApi();
    $charges = $stripe->getCharges($time);
    foreach ($charges AS $charge) {
      if (self::chargeIsReady($charge)) {
        $customer_id = $charge->customer;
        if ($account = self::userByCustomerId($customer_id)) {
          $customer = $stripe->getCustomer($customer_id);
          $invoice = $stripe->getInvoice($charge->invoice);
          $subscription_id = 'N/A';
          if (isset($invoice->subscription)) {
            $subscription_id = $invoice->subscription;
          } // if invoice->subscription
          elseif (!empty($customer->subscriptions->data)) {
            $subscription_id = reset($customer->subscriptions->data)->id;
          } // elsif customer->subscriptions
          $subscription = $stripe->getSubscription($subscription_id, $customer->id);
          $options = [
            'payment_type' => 'stripe',
            'payment_time' => $charge->created,
            'payment_title' => $charge->id,
            'stripe' => [
              'stripe_subscription' => $subscription->id,
              'stripe_customer' => $customer_id,
              'stripe_charge' => $charge->id,
            ],
            'count' => max(1, $subscription->plan->interval_count),
          ];
          \Drupal::moduleHandler()->invokeAll('bombplates_process_account', ['paid', $account, $options]);
        } // if account
      } // if charge is ready
    } // foreach charge in charges
  } // logPaymentsSince

  /**
   * Find outstanding invoices and email them to billing
   *
   * @param int $time
   *  timestamp to search from
   */
  public static function logRefundsSince($time) {
    $stripe = MiscFunc::loadApi();
    $invoices = $stripe->getInvoices($time);
    $refunds = [];
    $si_url = 'https://dashboard.stripe.com/'
      . (\Drupal::config('stripe.settings')->get('stripe_test_mode') ? 'live' : 'test')
      . '/invoices';
    foreach ($invoices AS $invoice) {
      if (self::invoiceIsRefund($invoice) ){
        //$refunds[$invoice->id] = -1 * $invoice->ending_balance;
        $id = $invoice->id;
        $balance = $invoice->ending_balance / 100;
        $refunds[] = [
          'l',
          ['l' => \Drupal\Core\Link::fromTextAndUrl("$id - $balance", \Drupal\Core\Url::fromUri("$si_url/$id", ['external' => TRUE]))->toString()],
        ];
      } // invoice is refund
    } // foreach invoice in invoices
    if (!empty($refunds)) {
      $body = [
        'Stripe appears to have processed refunds for one or more Bombplates accounts.',
        'Please review the following invoices and adjust your records as needed.',
      ];
      Bombplates\MiscFunc::queueMail([
        'to' => \Drupal::config('bombplates_payment.settings')->get('billing_mail'),
        'subject' => t('Stripe has processed Bombplates refunds'),
        'body' => Bombplates\MiscFunc::buildMailBody(array_merge($body, $refunds)),
      ]);
    } // if !empty(refunds)
  } // logRefundsSince

  /**
   * Determine if an invoice is probably a refund
   *
   * @param object $invoice
   *  a Stripe_Invoice
   * @return boolean
   *  is it a refund?
   */
  protected static function invoiceIsRefund($invoice) {
    return $invoice->ending_balance < 0;
  } // invoiceIsRefund

  /**
   * Verify that a charge is ready to log - i.e. it has not been logged yet, and it has been completed
   *
   * @param object $charge
   *  a Stripe_Charge object
   * @return boolean
   *  does the charge not exist in our database yet
   */
  protected static function chargeIsReady($charge) {
    $nids = \Drupal::entityQuery('node')
      ->condition('field_stripe_charge', $charge->id, '=')
      ->condition('type', 'bombplates_payment', '=')
      ->execute();
    return empty($nids) && $charge->paid;
  } // chargeIsReady

  /**
   * Load a user from a stripe customer id
   *
   * @param string $customer_id
   *  per stripe api
   * @return mixed
   *  user object or FALSE on failure
   */
  protected static function userByCustomerId($customer_id) {
    $uids = \Drupal::entityQuery('user')
      ->condition('field_stripe_customer', $customer_id, '=')
      ->execute();
    return empty($uids) ? FALSE : \Drupal\user\Entity\User::load(reset($uids));
  } // userByCustomerId
} // CronFunc
