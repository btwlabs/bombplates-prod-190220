<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Inc\MailFunc
 */

namespace Drupal\bombplates_forms\Inc;

use Drupal\bombplates\Inc as Bombplates;

/**
 * Static public static functions for sending out various system mails
 */

class MailFunc {

  /**
   * Alert all of a user's Account Managers that a user has been suspended or deleted
   *
   * @param string $action
   *  suspended|deleted
   * @param object $account
   *  A user object
   */
  public static function alertAm($action, $account) {
    $uid = $account->uid->value;
    $body = Bombplates\MiscFunc::buildMailBody([
      ["@b's site (@d) has been @a", ['@b' => $account->field_band_name->value, '@d' => $account->field_subdomain->value, '@a' => $action]],
      'You are listed as an account manager for this site. Please verify that this is correct.',
    ]);
    $subject = t('[BOMBPLATES INTERNAL] Your Client Is @act', ['@act' => ucwords($action)]);
    foreach (MiscFunc::getAccountManagers($uid) AS $am) {
      $params = [
        'to' => $am->mail->value,
        'subject' => $subject,
        'body' => $body,
        'key' => 'am-alert',
        'is_external' => FALSE,
      ];
      Bombplates\MiscFunc::queueMail($params);
    } // foreach $am in getAccountManagers
  } // alertAm

  /**
   * Alert the sales team of a cancelled account (if needs be)
   *
   * @param $object $account
   *  User object
   */
  public static function alertSales($account) {
    if ($account->field_missed_payments->value > 0) {
      $params = [
        'to' => 'sales.bombplates.com',
        'subject' => t('[BOMBPLATES INTERNAL] Nonpaying Subscription Canceled'),
        'body' => Bombplates\MiscFunc::buildMailBody([
          [
            "@b's site has been canceled. It had @m outstanding payments.",
            ['@b' => $account->field_band_name->value, '@m' => $account->field_missed_payments->value],
          ],
          'This is a permanent deletion. The site cannot be recovered.',
        ]),
      ];
    } // if missed_payments > 0
  } // alertSales

  /**
   * Tell an artist their site has been taken down
   *
   * @param object $account
   *   User object.
   */
  public static function alertArtist($account) {
    $pay_l = \Drupal\Core\Link::fromTextAndUrl(
      'bombplates.com',
      \Drupal\Core\Url::fromUri('internal:/user/payment/update', ['absolute'=>TRUE])
    )->toString();
    $body = [
      [
        'There is an outstanding balance on your account, so unfortunately we had to take your Bombplate (@L) offline. The site will remain offline for up to 23 days before automatic deletion.',
        ['@L' => 'http://' . $account->field_subdomain->value . '.bombplates.com'],
      ],
      [
        'Not to fear, though! You can bring it right back online simply by logging into @L and paying up your balance.',
        ['@L' => $pay_l],
      ],
    ];
    Bombplates\MiscFunc::queueMail([
      'to' => $account->getEmail(),
      'from' => \Drupal::config('bombplates_payment.settings')->get('billing_mail'),
      'subject' => t(
        'Your Bombplates subscription has been suspended!', [], ['langcode' => $account->getPreferredLangcode()]
      ),
      'body' => Bombplates\MiscFunc::buildMailBody($body, $account),
    ]);
  } // alertArtist

  /**
   * Send a new user a "welcome" email
   *
   * @param object $account
   *  User object
   * @param array $artist_info
   *  data from the artist-info form
   */
  public static function welcome($account, $artist_info) {
    $subdomain = $artist_info['subdomain'];
    $manage_url = \Drupal\Core\Url::fromUri("https://$subdomain.bombplates.com/user", ['query' => ['destination' => 'manage']]);
    $manage_link = \Drupal\Core\Link::fromTextAndUrl($manage_url->toString(), $manage_url)->toString();
    $support_url = \Drupal\Core\Url::fromUri('https://bombplates.desk.com');
    $support_link = \Drupal\Core\Link::fromTextAndurl(t('Bombplates Support Center'), $support_url)->toString();
    $design_url = \Drupal\Core\Url::fromUri("https://$subdomain.bombplates.com/manage/preview_design");
    $design_link = \Drupal\Core\Link::fromTextAndUrl(t('Design > Choose Design'),  $design_url)->toString();
    $prefix = '<h3>';
    $h3_style = 'font-weight: 300; font-family:"montserrat"; font-size:16px;';
    $suffix = '</h3>';
    $list = [
      'list' => [
        '#theme' => 'item_list',
        '#list_type' => 'ol',
        '#attributes' => ['style' => 'padding: 0 7%;'],
        '#items' => [
          [ // <li>
            [
              '#prefix' => $prefix,
              '#type' => 'container',
              '#attributes' => ['style' => $h3_style],
              '#markup' => t('Log In & Add Content'),
              '#suffix' => $suffix,
            ],
            [
              '#markup' => t('You can log in to your site at @l using the username and password you entered when you registered. Once you are in, add and edit all your site content as often as you like.', ['@l' => $manage_link]),
            ],
            [
              '#markup' => t('If you need help/guidance check out the @l!', ['@l' => $support_link]),
            ],
          ], // </li>
          [ // <li>
            [
              '#prefix' => $prefix,
              '#type' => 'container',
              '#attributes' => ['style' => $h3_style],
              '#markup' => t('Try Out Some Designs!'),
              '#suffix' => $suffix,
            ],
            [
              '#markup' => t(
                "Once you've logged in you can try out any of our existing templates. Just navigate to @l  and find one that suites your style!",
                ['@l' => $design_link]
              ),
            ],
          ], // </li>
          [ // <li>
            [
              '#prefix' => $prefix,
              '#type' => 'container',
              '#attributes' => ['style' => $h3_style],
              '#markup' => t('Customize'),
              '#suffix' => $suffix,
            ],
            [
              '#markup' => t("Now you've picked out a design and loaded in your awesome content, try playing around with the custom design options. Upload your own logo, change the background, go nuts!"),
            ],
          ], // </li>
        ],
      ],
    ];
    $body = Bombplates\MiscFunc::buildMailBody(
      [['@list', ['@list' => drupal_render($list)]]],
      $account
    );
    $params = [
      'to' => $account->mail->value,
      'from' => 'support@bombplates.com',
      'subject' => t('Welcome to Bombplates!'),
      'body' => $body,
      'key' => 'artist-info-welcome',
      'is_external' => FALSE,
    ];
    Bombplates\MiscFunc::queueMail($params);
  } // welcome
} // MailFunc
