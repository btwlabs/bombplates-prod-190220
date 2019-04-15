<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Form\Manage\ManageUnsuspendForm
 */

namespace Drupal\bombplates_forms\Form\Manage;

use Drupal\bombplates_forms\Inc as BombplatesForms;
use Drupal\bombplates\Inc as Bombplates;

/**
 * modal "unsuspend account" form
 */
class ManageUnsuspendForm extends BombplatesFormsManageBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bombplates_forms_manage_unsuspend';
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
    $options = $this->loadManagedOptions(1);
    if (empty($options)) {
      $form['error'] = [
        '#type' => 'markup',
        '#markup' => $this->t('You are not currently managing any suspended users.'),
      ];
    } // empty(options)
    else {
      $form['band_uid'] = [
        '#title' => $this->t('Unsuspend Site'),
        '#type' => 'select',
        '#options' => $options,
      ];
      $form['missed_payments_status'] = [
        '#type' => 'select',
        '#title' => t('What should be done with missed payments?'),
        '#options' => [
          'paid' => t('They have been paid'),
          'forgiven' => t('Delete them and consider them lost'),
        ],
        '#default_value' => 'paid',
      ];
      if (\Drupal::currentUser()->hasPermission('bombplates_forms administrator')) {
        $form['missed_payments_status']['#options']['retained'] = t('Retain them for future payment - You should probably never do this');
      }
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
    $am = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $account = \Drupal\user\Entity\User::load($values['band_uid']);
    $payment_status = $values['missed_payments_status'];
    $options = [
      'log_payments' => $payment_status == 'paid',
      'forgive_payments' => $payment_status == 'forgiven',
      'values' => [
        'bombplates_payment_module' => 'bombplates_forms',
        'payment_type' => 'bombplates_forms',
        'bombplates_forms' => [
          'am' => $am,
        ],
      ],
    ];
    Bombplates\MiscFunc::queueProcess(
      ['action' => 'unsuspend', 'account' => $account, 'options' => $options],
      'bombplates_process',
      TRUE
    );
  } // submitForm
  /**
   * {@inheritdoc}
   */
  protected function successMessage() {
    return $this->t('Account Unsuspended');
  } // successMessage
} // ManageUnsuspendForm
