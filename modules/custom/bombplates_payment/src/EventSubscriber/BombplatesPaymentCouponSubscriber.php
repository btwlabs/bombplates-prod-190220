<?php

/**
 * @file
 *  BombplatesPaymentSubscriber class
 */


namespace Drupal\bombplates_payment\EventSubscriber;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BombplatesPaymentCouponSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    $events[KernelEvents::TERMINATE][] = ['checkForCoupon'];
    return $events;
  } // getSubscribedEvents

  public function checkForCoupon() {
    if ($coupon_code = \Drupal::request()->query->get('coupon_code')) {
      if ($this->checkCoupon($coupon_code, FALSE)) {
        $tempstore = \Drupal::service('user.private_tempstore')->get('bombplates_payment');
        $tempstore->set('coupon_code', $coupon_code);
      } // if checkCoupon
    } // if coupon_code
  } // checkForCoupon

  /**
   * Check the validity of a coupon code
   *
   * @param string - coupon as submitted by user $coupon
   * @param boolean - should the coupon be counted as "used" if successful $decrement_coupon
   * @return int - number of months of trial granted by this coupon
   */
  public static function checkCoupon($coupon, $decrement_coupon=FALSE) {
    $result = 0;
    if (!$coupon) { return $result; }
    //check it against our known coupon codes
    $coupon_is_valid = 0;
    //check against coupon codes
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'coupon_code', '=')
      ->condition('title', $coupon, '=')
      ->execute();
    if (!empty($nids)) {
      $node = node_load(reset($nids));
      $expiration = strtotime($node->field_expiration->value);
      $uses_left = $node->field_uses_left->value;
      if ($expiration > \Drupal::time()->getRequestTime() && $uses_left > 0) {
        $result = $node->field_free_months->value;
        $coupon_is_valid = 1;
        if ($decrement_coupon) {
          $node->set('field_uses_left', $uses_left - 1)->save();
        } // if decrement_coupon
      } // if coupon is valid
    } // if coupon matched a node
    if (!$coupon_is_valid) {
      $uids = \Drupal::entityQuery('user')
        ->condition('field_subdomain', $coupon, '=')
        ->execute();
      $uid = reset($uids);
      if ($uid && $uid != \Drupal::currentUser()->id()) {
        $result = 1;
        if ($decrement_coupon) {
          $account = \Drupal\user\Entity\User::load($uid);
          $field = $account->get('field_artists_referred');
          $referred = ($field->count() > 0) ? unserialize($field->first()->value) : [];
          $referred[\Drupal::currentUser()->id()] = 0;
          $account->set('field_artists_referred', serialize($referred))->save();
        } // if decrement_coupon
      } // if uid
    } // if !coupon_is_valid
    return $result;
  } // checkCoupon
} // BombplatesPaymentSubscriber
