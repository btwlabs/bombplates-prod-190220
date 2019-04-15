<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Inc\LaunchFunc
 */

namespace Drupal\bombplates_forms\Inc;

use Drupal\bombplates_payment\Inc as BombplatesPayment;

/**
 *  Static public static functions to launch a website following artist info submission
 */
class LaunchFunc {

  /**
   * Prepare an account for launch
   *
   * @param object $account
   *  A user object
   * @param array $artist_info
   *  From the artist-info form
   */
  public static function prepUser(\Drupal\user\UserInterface $account, $artist_info) {
    $account = self::launchUpdateUserIntegration($account, $artist_info);
    $account = self::launchUpdateUserBilling($account, $artist_info);
  } // prepUser

  /**
   * Launch a new site
   *
   * @param object $account
   *  A user object
   * @param array $artist_info
   *  From the artist-info form
   */
  public static function launch(\Drupal\user\UserInterface $account, $artist_info) {
    $server = NetworkFunc::findSpace();
    $artist_info['server'] = $server;

    $account = MiscFunc::grantRoles($account, 'on_launch');
    CmdFunc::doLaunchCommands($account, (int) $artist_info['template'], $server);
    DnsFunc::add($server, $artist_info['subdomain']);
    MailFunc::welcome($account, $artist_info);
  } // launch

  /**
   *
   * @param object $account
   *    A user object
   * @param array $artist_info
   *    As returned by ArtistInfoFunc::extractArtistInfo
   * @return object
   *    The modified account object
   */
  protected static function launchUpdateUserIntegration(\Drupal\user\UserInterface $account, $artist_info) {
    $account->field_websites->value = $artist_info['domain'];
    $account->field_subdomain->value = $artist_info['subdomain'];
    if (!$account->field_band_name->value) {
      $account->field_band_name->value = $artist_info['band'];
    }
    if (!$account->field_pw_sync_key->value) {
      $account->field_pw_sync_key->value = \Drupal\pw_sync\Inc\MiscFunc::generateKey();
    }
    $account->save();
    return $account;
  } // launchUpdateUserIntegration

  /**
   * Update a user's billing information following submission of the artist_info form
   *
   * @param object $account
   *    a user object
   * @param array $artist_info
   *    as returned by ArtistInfoFunc::extractArtistInfo
   * @return object
   *    Modified user object
   */
  protected static function launchUpdateUserBilling(\Drupal\user\UserInterface $account, $artist_info) {
    //coupon code and trial period
    $trial = 1; // 1x30 day interval
    $coupon = $artist_info['coupon'];
    if ($coupon) {
      if ($coup_val = BombplatesPayment\FormFunc::checkCoupon($coupon, TRUE)) {
        $trial += $coup_val;
        $account->field_referral_entered->value = $coupon;
      } // if coup_val
    } // if coupon
    // Trial ends $trial months from today
    $trial_ends = new \DateTime("+$trial months");
    $account->field_trial_ends->value = $trial_ends->format('Y-m-d\TH:i:s');;
    $account->field_next_payment->value = $trial_ends->add(new \DateInterval('P1D'))->format('Y-m-d\TH:i:s');
    $account->field_billing_status->value = 'Billed Account';
    $account->save();
    return $account;
  } // launchUpdateUserBilling

} // LaunchFunc
