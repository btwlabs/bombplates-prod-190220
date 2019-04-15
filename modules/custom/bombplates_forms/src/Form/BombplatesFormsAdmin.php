<?php

/**
 * @file
 *  contains \Drupal\bombplates_forms\Form\BombplatesFormsAdmin
 */

namespace Drupal\bombplates_forms\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\bombplates_forms\Inc as BombplatesForms;

/**
 * Bombplates forms admin form
 */
class BombplatesFormsAdmin implements FormInterface {
  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'bombplates_forms_admin';
  } // getFormID

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $config = \Drupal::config('bombplates_forms.settings');
    $form['hosting_servers'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hosting Server'),
      '#description' => $this->t('Server that hosts the band sites (FQDN)'),
      '#default_value' => implode("\n", $config->get('hosting_servers')),
      '#required' => TRUE,
    ];
    $form['gpg'] = [
      '#type' => 'details',
      '#title' => $this->t('GPG settings'),
      'gpg_recipient' => [
        '#type' => 'textfield',
        '#title' => $this->t('Recipient'),
        '#description' => $this->t('What email address should be used for encrypting account commands?'),
        '#default_value' => $config->get('gpg_recipient'),
      ],
      'gpg_encrypt_path' => [
        '#type' => 'textfield',
        '#title' => $this->t('Path'),
        '#description' => $this->t('Absolute path to the folder containing our GnuPG key folder - should contain a keyfile and the .gnupg folder'),
        '#default_value' => $config->get('gpg_encrypt_path'),
      ],
      'gpg_public_key' => [
        '#type' => 'textfield',
        '#title' => $this->t('Keyfile name'),
        '#description' => $this->t('Name of the keyfile found in Path above.'),
        '#default_value' => $config->get('gpg_public_key'),
      ],
    ];
    $login_redirect = $config->get('login_redirect');
    $form['login_redirect'] = [
      '#type' => 'details',
      '#title' => $this->t('Redirection based on role'),
      '#description' => $this->t('Enter a system path to send users to based on role after login'),
      '#tree' => TRUE,
    ];
    foreach ($login_redirect AS $role => $path) {
      if ($role_obj = \Drupal\user\Entity\Role::load($role)) {
        $form['login_redirect'][$role] = [
          '#type' => 'textfield',
          '#title' => $role_obj->label(),
          '#default_value' => $path,
        ];
      } // if role exists
    } // foreach role=>path in login_redirect
    $role_names = ['' => t('-None-')];
    foreach (user_roles(TRUE) AS $rid => $role) {
      $role_names[$rid] = $role->get('label');
    }
    $role_grants = $config->get('role_grants');
    $form['role_grants'] = ['#type' => 'details', '#title' => t('Role grants/revocations'), '#tree' => TRUE];
    $form['role_grants']['on_join'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Grant/Revoke Roles After Creating account'),
      'grant' => [
        '#type' => 'select',
        '#options' => $role_names,
        '#title' => $this->t('Grant'),
        '#default_value' => $role_grants['on_join']['grant'],
      ],
      'revoke' => [
        '#type' => 'select',
        '#options' => $role_names,
        '#title' => $this->t('Revoke'),
        '#default_value' => $role_grants['on_join']['revoke'],
      ],
    ];
    $form['role_grants']['on_launch'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Grant/Revoke Roles After Launching Site'),
      'grant' => [
        '#type' => 'select',
        '#options' => $role_names,
        '#title' => $this->t('Grant'),
        '#default_value' => $role_grants['on_launch']['grant'],
      ],
      'revoke' => [
        '#type' => 'select',
        '#options' => $role_names,
        '#title' => $this->t('Revoke'),
        '#default_value' => $role_grants['on_launch']['revoke'],
      ],
    ];
    $form['role_grants']['on_payment'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Grant/Revoke Roles After Submitting Payment Information'),
      'grant' => [
        '#type' => 'select',
        '#options' => $role_names,
        '#title' => $this->t('Grant'),
        '#default_value' => $role_grants['on_payment']['grant'],
      ],
      'revoke' => [
        '#type' => 'select',
        '#options' => $role_names,
        '#title' => $this->t('Revoke'),
        '#default_value' => $role_grants['on_payment']['revoke'],
      ],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ],
    ];
    return $form;
  } // buildForm

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $servers = explode("\n", $values['hosting_servers']);
    $servers_changed = FALSE;
    foreach ($servers AS $i => $server) {
      $fqdn = BombplatesForms\MiscFunc::validateFqdn($server);
      if (!$fqdn) {
        $form_state->setErrorByName('hosting_servers', $this->t('Hosting Server must be a fully-qualified domain name (e.g. "host.bombplates.com")'));
        break;
      } // if server is incorrectly formatted
      elseif ($fqdn != $server) {
        $servers[$i] = $fqdn;
        $servers_changed = TRUE;
      }
    } // foreach server in servers
    if ($servers_changed) { $form_state->setValue('hosting_servers', implode("\n", $servers)); }
    foreach ($values['login_redirect'] AS $rid => $path) {
      if (!\Drupal::pathValidator()->isValid($path)) {
        $form_state->setErrorByName("login_redirect][$rid", $this->t('Redirect paths must be valid internal paths (e.g. "admin" or "/"'));
      }
    } // foreach rid=>path in values[login_redirect]
  } // validateForm

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $servers = explode("\n", $values['hosting_servers']);
    $values['hosting_servers'] = $servers;
    $config = \Drupal::configFactory()->getEditable('bombplates_forms.settings')->setData($values)->save();
  } // submitForm

} // BombplatesFormsAdmin
