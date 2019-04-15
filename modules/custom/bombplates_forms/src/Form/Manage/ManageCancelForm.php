<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Form\Manage\ManageCancelForm
 */

namespace Drupal\bombplates_forms\Form\Manage;

use Drupal\bombplates_forms\Inc as BombplatesForms;

/**
 * Modal "Cancel account" form
 */
class ManageCancelForm extends BombplatesFormsManageBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bombplates_forms_manage_cancel';
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
        '#markup' => $this->t('You must claim one or more accounts in order to manage them.'),
      ];
    } // empty(options)
    else {
      $form['band_uid'] = [
        '#type' => 'select',
        '#options' => $options,
        '#description' => t('Note: a site must be suspended before it can be cancelled'),
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
    $client = \Drupal\user\Entity\User::load($values['band_uid']);
    $am = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    BombplatesForms\DeleteFunc::logCancel($client, t('Cancelled by AM (@n)', ['@n' => $am->name->value]));
    \Drupal::moduleHandler()->invokeAll('bombplates_process_account', ['delete', $client, []]);
  } // submitForm

  /**
   * {@inheritdoc}
   */
  protected function successMessage() {
    return $this->t('Account Cancelled');
  } // successMessage

} // ManageCancelForm
