<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Form\Manage\ManageGrantForm
 */

namespace Drupal\bombplates_forms\Form\Manage;

use Drupal\bombplates_forms\Inc as BombplatesForms;

/**
 * Modal "grant management" form
 */
class ManageGrantForm extends BombplatesFormsManageBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bombplates_forms_manage_grant';
  } // getFormId

  /**
   * Build the form
   *
   * @return array
   *    Per drupal form api
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $form['band_uid'] = [
      '#type' => 'select',
      '#title' => $this->t('Band'),
      '#options' => $this->loadManageableOptions(),
    ];
    $form['am_uid'] = [
      '#type' => 'select',
      '#title' => $this->t('Account Manager'),
      '#options' => $this->loadAccountManagersOptions(),
    ];
    $form['exclusive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Grant exclusive ownership?'),
      '#description' => $this->t('Grant exclusive ownership? All other Account Managers will have their ownership revoked.'),
    ];
    $form['customer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mark as "customer"? (Out-of-band payment)'),
      '#description' => $this->t(
        'Toggle this on if this is a NEW customer who will be sending payments directly to us instead of using the payment form on bombplates.com'
      ),
      '#options' => [
        0 => 'no',
        1 => 'yes',
      ],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#attributes' => ['class' => ['use-ajax']],
        '#ajax' => [
          'callback' => [$this, 'submitModalFormAjax'],
          'event' => 'click',
        ],
      ],
    ];
    $form['#attached'] = [
      'library' => ['core/drupal.dialog.ajax'],
    ];
    return $form;
  } // buildForm

  /**
   * {@inheritdoc}
   */
 public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
   // Nothing to do
 } // validateForm

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $band = \Drupal\user\Entity\User::load($values['band_uid']);
    $exclusive = $values['exclusive'];
    $customer = $values['customer'];
    $account = \Drupal\user\Entity\User::load($values['am_uid']);
    if ($exclusive) {
      foreach (BombplatesForms\MiscFunc::getAccountManagers($band->uid->value) AS $am) {
        if ($am->uid->value != $account->uid->value) {
          BombplatesForms\MiscFunc::unmanageAccount($am, $band);
        }
      } // foreach am in getAccountManagers
    }
    if ($customer) {
      BombplatesForms\MiscFunc::grantRoles($band, 'on_payment');
    }
    BombplatesForms\MiscFunc::manageAccount($account, $band);
  } // Grant

  /**
   * {@inheritdoc}
   */
  protected function successMessage() {
    return $this->t('Account Granted!');
  } // successMessage
} // ManageGrantForm
