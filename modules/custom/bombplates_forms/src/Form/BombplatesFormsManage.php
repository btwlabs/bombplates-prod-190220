<?php

/**
 * @file
 *  contains \Drupal\bombplates_forms\Form\BombplatesFormsManage
 */

namespace Drupal\bombplates_forms\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\bombplates_forms\Inc as BombplatesForms;

/**
 * Account management forms
 */
class BombplatesFormsManage implements FormInterface {
  use \Drupal\Core\StringTranslation\StringTranslationTrait;
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bombplates_forms_manage';
  } // getFormID

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    $form['task'] = [
      '#type' => 'actions',
      '#title' => $this->t('Select Task'),
      '#prefix' => '<div id="bombplates_forms-launch-wrapper">',
      '#suffix' => '</div>',
    ];
    $form['task']['load_launch'] = [
      '#type' => 'link',
      '#title' => $this->t('Launch'),
      '#url' => \Drupal\Core\Url::fromUri('internal:/manage/account_forms/ManageLaunchForm'),
      '#attributes' => [
        'class' => ['use-ajax', 'button'],
      ],
    ];
    $form['task']['load_claim'] = [
      '#type' => 'link',
      '#title' => $this->t('Claim'),
      '#url' => \Drupal\Core\Url::fromUri('internal:/manage/account_forms/ManageClaimForm'),
      '#attributes' => [
        'class' => ['use-ajax', 'button'],
      ],
    ];
    $form['task']['load_grant'] = [
      '#type' => 'link',
      '#title' => $this->t('Grant'),
      '#url' => \Drupal\Core\Url::fromUri('internal:/manage/account_forms/ManageGrantForm'),
      '#attributes' => [
        'class' => ['use-ajax', 'button'],
      ],
    ];
    $form['task']['load_cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromUri('internal:/manage/account_forms/ManageCancelForm'),
      '#attributes' => [
        'class' => ['use-ajax', 'button'],
      ],
    ];
    $form['task']['load_suspend'] = [
      '#type' => 'link',
      '#title' => $this->t('Suspend'),
      '#url' => \Drupal\Core\Url::fromUri('internal:/manage/account_forms/ManageSuspendForm'),
      '#attributes' => [
        'class' => ['use-ajax', 'button'],
      ],
    ];
    $form['task']['load_unsuspend'] = [
      '#type' => 'link',
      '#title' => $this->t('Unsuspend'),
      '#url' => \Drupal\Core\Url::fromUri('internal:/manage/account_forms/ManageUnsuspendForm'),
      '#attributes' => [
        'class' => ['use-ajax', 'button'],
      ],
    ];
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $form;
  } // buildForm

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Nothing to do
  } // validate

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Nothing to do
  } // submit

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['config.' . $self->getFormId()];
  }
} // BombplatesFormsManage
