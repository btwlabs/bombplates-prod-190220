<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Inc\ArtistInfoFunc
 */

namespace Drupal\bombplates_forms\Inc;

use Drupal\bombplates\Inc as Bombplates;
use Drupal\bombplates_payment\Inc as BombplatesPayment;

const BOMBPLATES_FORMS_DEFAULT_TEMPLATE = 1058; // Wedgewood nee Black Rock

/**
 *  Standalone public static functions to add "artist info" fields to forms and validate and submit them
 */
class ArtistInfoFunc {

  /**
   * Embedded the artist-info form within another form
   *
   * @param &$form array
   *  the form to add the artist_info form to
   * @param FormStateInterface $form_state
   *  per drupal forms api
   * @return array
   *  per drupal forms api
   */
  public static function artistInfoEmbed(&$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    //$form_state->loadInclude('bombplates_forms', 'inc', 'include/artist-info.inc');
    $form = self::artistInfo($form);
    if (isset($form['actions']['#validate'])) {
      $form['actions']['#validate'][] = [__CLASS__, 'artistInfoValidateMain'];
    }
    else {
      $form['#validate'][] = [__CLASS__, 'artistInfoValidateMain'];
    }
    //$submit_array = isset($form['actions']['submit']) ? &$form['actions']['submit']['#submit'] : &$form['#submit'][];
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#submit'][] = [__CLASS__, 'artistInfoSubmitMain'];
    }
    else {
      $form['#submit'][] = [__CLASS__, 'artistInfoSubmitMain'];
    }
    return $form;
  } // artistInfoEmbed

  /**
   * Create Artist info fields
   *
   * @param &$form array
   *  per drupal forms api
   * @return array
   *  per drupal forms api
   */
  public static function artistInfo(&$form) {
    $template = self::currentTemplate();
    if ($template) {
      $form['template'] = [
        '#type' => 'value',
        '#value' => $template,
      ];
    }
    $form['band_name'] = [
      '#type' => 'textfield',
      '#title' => t('Band/Artist Name'),
      '#description' => t('Name you would like on the title of your site. Please include any special capitalization you may use.'),
      '#required' => TRUE,
    ];
    $form['domain'] = [
      '#type' => 'textfield',
      '#title' => t('Your Website Domain'),
      '#description' => t("We set up your site to point to the domain of your choice, please enter it above. Don't worry this will not change your current site until you change your DNS settings. (e.g. yourbandname.com)"),
      '#required' => TRUE,
    ];
    $form['show_subdomain'] = [
      '#type' => 'markup',
      '#markup' => '<span id="show_subdomain_desc">' . t('Click here to choose your own subdomain.') . '</span>',
    ];
    $form['subdomain'] = [
      '#type' => 'textfield',
      '#title' => t('Subdomain'),
      '#description' => ('This can be used to access your site while you are still building it. DO NOT include any www or .com, this should be a single word. (e.g. yourbandname)'),
      '#required' => TRUE,
      '#field_suffix' => '.bombplates.com',
      '#prefix' => '<div id="subdomain_field">',
      '#suffix' => '</div>',
    ];
    if ($coupon_code = \Drupal::service('user.private_tempstore')->get('bombplates_payment')->get('coupon_code')) {
      $form['coupon'] = [
        '#type' => 'value',
        '#value' => $coupon_code,
      ];
    } // if coupon_code
    else {
      $form['coupon'] = [
        '#type' => 'textfield',
        '#title' => t('Coupon or Referral Code'),
      ];
    } // if !coupon_code
    $form['#attached']['library'][] = 'bombplates_forms/show_subdomain';
    return $form;
  } // artistInfo

  /**
   * Retreive the ID of the template the user has currently selected.
   *
   * @return int
   *  ID of the template the user has selected (or the default)
   */
  public static function currentTemplate() {
    $template = (int) \Drupal::request()->query->get('template');
    if (!$template) {
      $tempstore = \Drupal::service('user.private_tempstore')->get('bombplates_forms');
      $template = (int) $tempstore->get('template');
      if (!$template) {
        $template = $tempstore->get('artist_info')['template'];
        if (!$template) {
          $template = FALSE;
        }
      }
    }
    return $template;
  } // currentTemplate

  /**
   * Primary form validate public static function for bombplates_forms_artist_info forms
   *
   * @param array $form
   *  per drupal forms api
   * @param FormStateInterface $form_state
   *  per drupal forms api
   */
  public static function artistInfoValidateMain($form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $subdomain = $values['subdomain'];
    $domain = $values['domain'];
    if (preg_match('/[^a-zA-Z0-9-]/', $subdomain)) {
      $form_state->setErrorByName('subdomain', t('Subdomain can only contain letters, numbers, and dashes.'));
    } // illegal character found in subdomain
    if (!Bombplates\AccountFunc::checkSubdomainAvailable($subdomain)) {
      $form_state->setErrorByName('subdomain', t('That subdomain is already in use. Please select another.'));
    } // subdomain taken
    if (!MiscFunc::checkDomainAvailable([$domain])) {
      $support_link = \Drupal\Core\Link::fromTextAndUrl(
        t('contact support'),
        \Drupal\Core\Url::fromUri('https://bombplates.desk.com', ['attributes' => ['target' => '_blank']])
      );
      $form_state->setErrorByName(
        'domain',
        t(
          'That domain is already in use. Please select another. If you think you are receiving this message in error, please @m',
          ['@m' => $support_link->toString()]
        )
      );
    } // domain taken
    elseif (!MiscFunc::validateFqdn($domain)) {
      $form_state->setErrorByName(
        'domain',
        t(
          'Domain must be a fully-qualified-domain name (e.g. "@sample.com")',
          ['@sample' => trim(preg_replace('/[^a-z0-9.-]+/i', '-', $values['band_name']), '-')]
        )
      );
    } // if domain is not valid format
    BombplatesPayment\FormFunc::validateCoupon($form, $form_state);
  } // artistInfoValidateMain

  /**
   * Form validate public static function for bombplates_forms_artist_info forms - validate template selection
   *
   * @param array $form
   *  per drupal forms api
   * @param FormStateInterface $form_state
   *  per drupal forms api
   * /
  public static function artistInfoValidateTemplate($form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $template = $form_state->getValues()['template'];
    if (preg_match('/[^a-z0-9]/i', $template)) {
      $form_state->setErrorByName('template', t('Invalid template ID selected.'));
    } // if template contains non alphanumerics
  } // artistInfoValidateTemplate

  /**
   * Extract artist info from a form
   *
   * @param array $form
   *  per drupal forms api
   * @param FormStateInterface $form_state
   *  per drupal forms api
   * @return array
   *  Relevant info from the form_state->values
   */
  public static function extractArtistInfo($form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $artist_info = [];
    $values = $form_state->getValues();
    $domain = preg_replace('!^[a-z]*:?//!i', '', $values['domain']);
    $artist_info['domain'] = $domain;
    //SUBDOMAIN
    $artist_info['subdomain'] = strtolower($values['subdomain']);
    //BAND NAME
    $artist_info['band'] = $values['band_name'];
    if (isset($values['template'])) {
      $artist_info['template'] = $values['template'];
    }
    //COUPON
    $artist_info['coupon'] = isset($values['coupon']) ? $values['coupon'] : '';
    return $artist_info;
  } // extractArtistInfo

  /**
   * Form submit public static function for bombplates_forms_artist_info forms - store data in the session for later retrieval
   *
   * @param array $form
   *  per drupal forms api
   * @param FormStateInterface $form_state
   *  per drupal forms api
   */
  public static function artistInfoSubmitMain($form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $artist_info = self::extractArtistInfo($form, $form_state);
    LaunchFunc::prepUser($account, $artist_info);
    // if template was not submitted, do not actually launch. Redirect to artist-info
    if (isset($artist_info['template'])) {
      LaunchFunc::launch($account, $artist_info);
      $redirect = 'bombplates_forms.launch-site';
    }
    elseif (count(\Drupal::service('router.route_provider')->getRoutesByNames(['view.bombplates.page_1']))) {
      $redirect = 'view.bombplates.page_1';
    }
    else {
      $redirect = 'user.artist_info';
    }
    // redirect the user
    $form_state->setRedirect($redirect);
  } // artistInfoSubmitMain

  /**
   * Build a selector for all themes
   *
   * @return array
   *    Single select element per forms api
   */
  public static function templateSelector() {
    $designs = MiscFunc::loadDesigns();
    $design_opts = ['' => t('Choose...')];
    $design_imgs = [];
    foreach ($designs as $nid => $node) {
      $design_opts[$node->field_theme_id->value] = $node->title->value;
      $image =  $node->field_image->get(0);
      $render = $image->view();
      $design_imgs[$node->field_theme_id->value] = (string)\Drupal::service('renderer')->renderPlain($render);
    }
    return [
      '#title' => t('Choose a Starting Design'),
      '#type' => 'select',
      '#options' => $design_opts,
      '#required' => TRUE,
      '#suffix' => '<div id="bombplates_forms_template_preview"></div>',
      '#attributes' => ['id' => 'select_template'],
      '#attached' => [
        'library' => [
          'js' => 'bombplates_forms/preview_thumbs',
        ],
        'drupalSettings' => [
          'bombplates_forms' => ['designs' => $design_imgs],
        ],
      ],
    ];
  } // templateSelector

} // ArtistInfoFunc
