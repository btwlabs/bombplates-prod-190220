<?php

/**
 * @file
 *  \Drupal\bombplates_forms\Form\BombplatesFormsArtistInfo form
 */

namespace Drupal\bombplates_forms\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\bombplates_forms\Inc as BombplatesForms;

/**
 * Artist info form page
 */
class BombplatesFormsArtistInfo implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'bombplates_forms_artist_info';
  } // getFormID

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    //$form = BombplatesForms\ArtistInfoFunc::artistInfo($form);
    if ($template = BombplatesForms\ArtistInfoFunc::currentTemplate()) {
      $form['template'] = [
        '#type' => 'value',
        '#value' => $template,
      ];
    } // template
    else {
      $form['template'] = BombplatesForms\ArtistInfoFunc::templateSelector();
    } // !template
    $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $key_values = array(
      'domain' => $account->field_websites->value,
      'subdomain' => $account->field_subdomain->value,
      'band_name' => $account->field_band_name->value,
    );
    foreach ($key_values AS $key => $value) {
      $form[$key] = [
        '#type' => 'value',
        '#value' => $value,
      ];
    } // foreach key=>value in key_values
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => t('Launch @d!', ['@d' => $account->field_subdomain->value . '.bombplates.com']),
      ],
    ];
    return $form;
  } // buildForm

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $template = $form_state->getValues()['template'];
    if (preg_match('/[^a-z0-9]/i', $template)) {
      $form_state->setErrorByName('template', t('Invalid template ID selected.'));
    } // if template contains non alphanumerics
  } // validateForm

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $artist_info = BombplatesForms\ArtistInfoFunc::extractArtistInfo($form, $form_state);
    $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    BombplatesForms\LaunchFunc::launch($account, $artist_info);
    $destination = \Drupal\Core\Url::fromRoute(
      'bombplates_forms.launch-site',
      ['subdomain' => $artist_info['subdomain']]
    );
    $form_state->setRedirectUrl($destination);
  } // submitForm

  /**
   * Redirect unauthenticated users to register. Redirect launched users to their own site
   */
  public function access(AccountInterface $user) {
    // $accounts is a Drupal\Core\Session\AccountProxy
    $config = \Drupal::config('bombplates_forms.settings');
    if (!$user->isAuthenticated()) {
      $query = \Drupal::request()->query->all();
      $register = \Drupal\Core\Url::fromRoute('user.register', $query)->toString();
      $response = new \Symfony\Component\HttpFoundation\RedirectResponse($register);
      $response->send();
    }
    elseif (in_array($config->get('role_grants.on_launch.grant'), $user->getRoles())) {
      $account = \Drupal\user\Entity\User::load($user->id());
      $subdomain = $account->field_subdomain->value;
      $template = BombplatesForms\ArtistInfoFunc::currentTemplate();
      $url = \Drupal\Core\Url::fromUri("https://$subdomain.bombplates.com", ['query' => ['preview_design' => $template]])->toString();
      $response = new \Symfony\Component\HttpFoundation\RedirectResponse($url);
      $response->send();
    }
    return \Drupal\Core\Access\AccessResult::allowedIf($user->hasPermission('artist info bombplates_form'));
  } // checkRedirect

} // BombplatesFormsArtistInfo
