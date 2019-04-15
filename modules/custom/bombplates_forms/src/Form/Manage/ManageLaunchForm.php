<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Form\Manage\ManageLaunchForm
 */

namespace Drupal\bombplates_forms\Form\Manage;

use Drupal\bombplates\Inc as Bombplates;
use Drupal\bombplates_forms\Inc as BombplatesForms;
use Drupal\pw_sync\Inc as PwSync;

/**
 * Modal "launch account" form
 */
class ManageLaunchForm extends BombplatesFormsManageBase {

  /**
   * Subdomain of the site being launched
   */
  protected $subdomain;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bombplates_forms_manage_launch';
  } // getFormId

  /**
   * Build the form
   *
   * @return array
   *    per drupal forms api
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $form['template'] = BombplatesForms\ArtistInfoFunc::templateSelector();
    $form['user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Band Member Username'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the username of a band member to create their account'),
    ];
    $form['user_mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Band Member Email'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the email address of a band member to create their account'),
    ];
    $form['band_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Band/Artist Name'),
      '#required' => TRUE,
    ];
    $form['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Website URL'),
      '#required' => TRUE,
      '#attributes' => ['id' => 'edit-domain'],
    ];
    $form['subdomain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subdomain'),
      '#required' => TRUE,
      '#field_suffix' => '.bombplates.com',
      '#attributes' => ['id' => 'edit-subdomain'],
    ];
    $form['billing_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Billing status'),
      '#options' => [
        'Billed Account' => $this->t('Billed Account'),
        'Comped Account' => $this->t('Comped Account'),
        'Test Account' => $this->t('Test Account'),
      ],
    ];
    $form['trial'] = [
      '#type' => 'select',
      '#title' => $this->t('Length of trial (in months)'),
      '#options' => [
        0 => $this->t('0 - Note: account will be deleted in 1 week if payment is not entered!'),
        1 => 1,
        2 => 2,
        3 => 3,
        6 => 6,
        12 => 12,
      ],
      '#default_value' => 1,
    ];
    $form['customer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Out-of Band Payments?'),
      '#default_value' => TRUE,
      '#options' => [
        0 => $this->t('no'),
        1 => $this->t('yes'),
      ],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit_launch' => [
        '#type' => 'submit',
        '#value' => $this->t('Launch Site'),
        '#attributes' => ['class' => ['use-ajax']],
        '#ajax' => [
          'callback' => [$this, 'submitModalFormAjax'],
          'event' => 'click',
        ],
      ],
    ];
    $form['#attached'] = [
      'library' => ['core/drupal.dialog.ajax', 'bombplates_forms/show_subdomain'],
    ];
    return $form;
  } // buildForm

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => $values['user_name']])) {
      $form_state->setErrorByName('user_name', t('That user name is already in use'));
    } // if name already in use
    if (\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $values['user_mail']])) {
      $form_state->setErrorByName('user_mail', t('That email address is already in use'));
    } // if mail already in user
    BombplatesForms\ArtistInfoFunc::artistInfoValidateMain($form, $form_state);
  } // validateForm

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $artist_info = BombplatesForms\ArtistInfoFunc::extractArtistInfo($form, $form_state);
    // 60 * 60 * 24 * 30 = 1 month in seconds
    $trial = \Drupal::time()->getRequestTime() + 2592000 * (int) $values['trial'];
    $account = \Drupal\user\Entity\User::create([
      'name' => $values['user_name'],
      'mail' => $values['user_mail'],
      'status' => 1,
      'created' => \Drupal::time()->getRequestTime(),
      'changed' => \Drupal::time()->getRequestTime(),
      'field_websites' => [$artist_info['domain']],
      'field_subdomain' => $artist_info['subdomain'],
      'field_band_name' => $artist_info['band'],
      'field_pw_sync_key' => PwSync\MiscFunc::generateKey(),
      'field_trial_ends' => date('Y-m-d\TH:i:s', $trial),
      'field_next_payment' => date('Y-m-d\TH:i:s', $trial + 86400), // extra day for safety
      'field_billing_status' => $values['billing_status'],
    ]);
    $account->save();
    BombplatesForms\MiscFunc::grantRoles($account, 'on_launch');
    if ($values['customer']) {
      BombplatesForms\MiscFunc::grantRoles($account, 'on_payment');
    }

    $am = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    BombplatesForms\MiscFunc::manageAccount($am, $account);
    $server = BombplatesForms\NetworkFunc::findSpace();
    BombplatesForms\CmdFunc::doLaunchCommands($account, (int) $artist_info['template'], $server);
    BombplatesForms\DnsFunc::add($server, $artist_info['subdomain']);
    drupal_set_message($this->t('New site created at @sub.bombplates.com.', ['@sub' => $artist_info['subdomain']]));
  } // submitForm

  /**
   * {@inheritdoc}
   */
  protected function successMessage() {
    return $this->subdomain ? $this->t('@s Launched!', ['@s' => $this->subdomain]) : $this->t('Site Launched!');
  } // successMessage

} // Create
