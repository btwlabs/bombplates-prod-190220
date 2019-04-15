<?php

/**
 * @file
 * Contains \Drupal\pw_sync\Form\PwSyncAdminForm.
 */

namespace Drupal\pw_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\pw_sync\Inc as PWSync;

/**
 * Password syncing admin settings form
 */
class PwSyncAdminForm extends ConfigFormBase {

  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormID(){
    return 'pw_sync_admin';
  } // getFormId

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'server', 'subdomain', 'password', 'domain',
    ];
  } // getEditableConfigNames

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $config = \Drupal::config('pw_sync.settings');
    $form['server'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server'),
      '#description' => $this->t('What is the URL of the next server up? (NOTE: this SHOULD be an SSL-enabled server.)'),
      '#default_value' => $config->get('server'),
    ];
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user');
    $keys = [0 => $this->t('None')] + array_keys($field_definitions);
    $form['subdomain'] = [
      '#type' => 'select',
      '#title' => $this->t('Subdomain Field'),
      '#description' => $this->t('What user field will contain subdomains of child servers?'),
      '#options' => array_combine($keys, $keys),
      '#default_value' => $config->get('subdomain'),
    ];
    $form['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain'),
      '#description' => $this->t('What domain will be used with subdomains to find child servers?'),
      '#default_value' => $config->get('domain'),
    ];
    $auth = PWSync\MiscFunc::httpAuth();
    $name = substr($auth, 0, strpos($auth, ':'));
    $form['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#description' => $this->t('HTTP auth password. Username is automatically generated as ":n"', [':n' => $name]),
      '#default_value' => $config->get('password'),
    ];
    $form['ip_whitelist'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IP Whitelist'),
      '#description' => $this->t('Comma-separated list of internal IP addresses that will always be considered "correct" for authenticating sync requests'),
      '#default_value' => implode(',', $config->get('ip_whitelist')),
    ];
    return parent::buildForm($form, $form_state);
  } // buildForm

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $ipv4 = '(([0-9]{1,3}\.){3}[0-9]{1,3})';
    $ipv6 = '(([0-9a-f]{4}:)+([0-9a-f]{4}|::))';
    $ip = "($ipv4|$ipv6)";
    $ip_reg = "/^$ip(,$ip)*/i";
    if (!preg_match($ip_reg, $form_state->getValue('ip_whitelist'))) {
      $form_state->setErrorByName('ip_whitelist', $this->t('Whitelist must be comma-separated IP addresses'));
    }
  } // validateForm

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    \Drupal::configFactory()->getEditable('pw_sync.settings')
      ->set('server', $form_state->getValue('server'))
      ->set('domain', $form_state->getValue('domain'))
      ->set('subdomain', $form_state->getValue('subdomain'))
      ->set('password', $form_state->getValue('password'))
      ->set('ip_whitelist', explode(',', $form_state->getValue('ip_whitelist')))
      ->save();
  } // submitForm
} // PwSyncAdminForm
