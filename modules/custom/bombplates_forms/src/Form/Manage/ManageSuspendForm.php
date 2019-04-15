<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Form\Manage\ManageSuspendForm
 */

namespace Drupal\bombplates_forms\Form\Manage;

use Drupal\bombplates_forms\Inc as BombplatesForms;
use Drupal\bombplates\Inc as Bombplates;

/**
 * modal "suspend account" form
 */
class ManageSuspendForm extends BombplatesFormsManageBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bombplates_forms_manage_suspend';
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
    $options = $this->loadManagedOptions(0);
    if (empty($options)) {
      $form['error'] = [
        '#type' => 'markup',
        '#markup' => $this->t('You are not currently managing any active users.'),
      ];
    } // empty(options)
    else {
      $form['band_uid'] = [
        '#title' => $this->t('Suspend Site'),
        '#type' => 'select',
        '#options' => $options,
      ];
      $form['missed_payments'] = [
        '#type' => 'select',
        '#title' => $this->t('Add missed payments'),
        '#options' => range(0,12),
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
    } // !empty(options)
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
    $account = \Drupal\user\Entity\User::load($values['band_uid']);
    $missed_payments = $values['missed_payments'];
    $options = [
      'missed_payments' => $missed_payments,
      'increment_pay_date' => FALSE,
    ];
    Bombplates\MiscFunc::queueProcess(
      ['action' => 'suspend', 'account' => $account, 'options' => $options],
      'bombplates_process',
      TRUE
    );
  } // submitForm
  /**
   * {@inheritdoc}
   */
  protected function successMessage() {
    return $this->t('Account Suspended');
  } // successMessage
} // ManageSuspendForm
