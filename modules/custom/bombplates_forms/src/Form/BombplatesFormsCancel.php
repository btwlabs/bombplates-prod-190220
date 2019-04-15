<?php

/**
 * @file
 *  \Drupal\bombplates_forms\Form\BombplatesFormsCancel
 */

namespace Drupal\bombplates_forms\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\bombplates_forms\Inc as BombplatesForms;
use Drupal\bombplates\Inc as Bombplates;

/**
 * Account cancellation page
 */
class BombplatesFormsCancel implements FormInterface {
  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'bombplates_forms_cancel';
  } // getFormID

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $form['#account'] = $account;
    $form['reason_for_canceling'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reason for Cancelling'),
      '#required' => TRUE,
    ];
    $form['confirm_cancellation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirm Cancellation'),
      '#required' => TRUE,
      '#description' => $this->t(
        'Yes, I understand that my site,  account, all content, and all uploaded files will be deleted from the Bombplates system; and my credit card will not be billed further.'
      ),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
         '#type' => 'submit',
         '#value' => $this->t('Cancel My Account'),
       ],
    ];
    return $form;
  } // buildForm

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (!$values['confirm_cancellation']) {
      $form_state->setErrorByName('confirm_cancellation', $this->t('Please confirm that you want to cancel'));
    } // if !confirm_cancellation
  } // validateForm

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form_state->setRedirect('user.cancel.bye');
    $values = $form_state->getValues();
    $reason = $values['reason_for_canceling'];
    $account = $form['#account'];
    $this->alertSupport($account->field_subdomain->value, $account->mail->value, $reason);
    BombplatesForms\MailFunc::alertAm('deleted', $account);
    $this->sayGoodbye($account);
    BombplatesForms\DeleteFunc::logCancel($account, $reason);
    \Drupal::moduleHandler()->invokeAll('bombplates_process_account', ['delete', $account, []]);
    drupal_set_message($this->t('We are sorry to see you go! Your site has been deleted, and you will not be billed further.'));
  } // submitForm

  /**
   * Queue an email to support telling them a user has canceled
   *
   * @param string $subdomain
   *    Subdomain of the canceled site
   * @param string $email
   *    User's email address
   * @param string $reason
   *    User's stated reason for leaving
   */
  protected function alertSupport($subdomain, $email, $reason) {
    $body = Bombplates\MiscFunc::buildMailBody([
      ['@n has cancelled for the following reason:', ['@n' => $subdomain]],
      ['@r', ['@r' => $reason]],
      ['email: @m', ['@m' => $email]],
    ]);
    $params = [
      'to' => 'support@bombplates.com',
      'from' => 'support@bombplates.com',
      'subject' => $this->t('[BOMBPLATES INTERNAL] Subscription Cancelation'),
      'body' => $body,
    ];
    Bombplates\MiscFunc::queueMail($params);
  } // alertSales

  /**
   * Queue an email to a user saying goodbye
   *
   * @param objec $account
   *    User object
   */
  protected function sayGoodbye($account) {
    $body = Bombplates\MiscFunc::buildMailBody([
      'Sorry to see you leave Bombplates. We are always here to assist if you need any of our services in the future.',
      'Thanks for using Bombplates!',
      'Your site, account, all content, and all uploaded files have been deleted from the Bombplates system; and your credit card will not be billed further.',
    ], $account);
    $params = [
      'to' => $account->mail->value,
      'from' => 'support@bombplates.com',
      'subject' => $this->t('Your Bombplates Subscription Has Been Cancelled.'),
      'body' => $body,
    ];
  } // sayGoodbye
} // BombplatesFormsCancel
