<?php

/**
 * @file
 * Contains \Drupal\bombplates_payment\Form\BombplatesPaymentForm
 */

namespace Drupal\bombplates_payment\Form;

use Drupal\bombplates\Inc as Bombplates;
use Drupal\Core\Form\FormInterface;

/**
 * Bombplates payment form
 */
class BombplatesPaymentForm implements FormInterface {
  use \Drupal\Core\StringTranslation\StringTranslationTrait;
  /**
   * {@inheritdoc}
   */
  public function getFormID(){
    return 'bombplates_payment_form';
  } // getFormId

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $config = \Drupal::config('bombplates_payment.settings');
    $form_state->loadInclude('bombplates_payment', 'inc', 'include/form');
    $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $form['#account'] = $account;
    $missed_payments = $account->field_missed_payments->value;
    if ($missed_payments) {
      $cost = $missed_payments * $config->get('base_fee');
      $form['missed_payments'] = [
        'message' => [
          '#prefix' => '<div class="messages status"><ul><li>',
          '#markup' => $this->t(
            'Please note: you currently have @count outstanding @PAY. You will automatically be billed @COST USD upon completing this form.',
            [
              '@COST' => new \Drupal\Component\Render\FormattableMarkup(
                "<span class='bpp-missed'>$cost</span>",
                []
              ),
              '@count' => $missed_payments,
              '@PAY' => ($missed_payments > 1 ? 'payments' : 'payment')
            ]
          ),
          '#suffix' => '</li></ul></div>',
        ],
        'missed_payment_count' => ['#type' => 'value', '#value' => $missed_payments],
        'missed_payment_cost' => ['#type' => 'value', '#value' => $cost],
      ];
    } // if missed_payments
    $payment_service = $config->get('bombplates_payment_service');
    $handler = \Drupal::moduleHandler();
    foreach ($handler->getImplementations('bombplates_payment_form') AS $module) {
      $form = $handler->invoke($module, 'bombplates_payment_form', [$form, $payment_service]);
    }
    $form['bombplates_payment_module'] = [
      '#type' => 'value',
      '#value' => $payment_service,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ],
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
    $account = $form['#account'];
    $message = FALSE;
    if (!$account->hasRole('customer')) {
      $message = $this->t('Thank you for entering your payment info! You will NOT be charged until your trial period has ended. If you cancel before your trial period ends, you will not be charged at all.');
      $account->addRole('customer');
      $account->save();
    }
    //Log payment and unsuspend the account (if applicable)
    $options = [
      'log_payments' => TRUE,
      'forgive_payments' => FALSE,
      'values' => array_merge_recursive($form_state->getValues(), $form_state->getStorage()),
    ];
    \Drupal::moduleHandler()->invokeAll('bombplates_process_account', ['unsuspend', $account, $options]);
    // If the payment type has CHANGED, cancel any old payment types
    $paying_users_groups = \Drupal::moduleHandler()->invokeAll('bombplates_payment_find_paying_users');
    foreach ($paying_users_groups AS $module => $uids) {
      if ($module != $form_state->getValue('bombplates_payment_module') && in_array($account->id(), $uids)) {
        Bombplates\MiscFunc::queueProcess([
          'action' => 'cancel_subscription', 'account' => $account, 'options' => $options,
        ]);
      } // if module mismatch, but uid is in array
    } // foreach module=>uids in paying_users_groups
    // Email billing to let them know it's updated
    $link = \Drupal\Core\Link::fromTextAndUrl($account->getEmail(), \Drupal\Core\Url::fromUri('mailto:support@bombplates.com'))->toString();
    $body = [[
      '@n (@lnk) / @d has updated their payment information',
      ['@n' => $account->getUsername(), '@lnk' => $link, '@d' => $account->field_subdomain->value]
    ]];
    Bombplates\MiscFunc::queueMail([
      'to' => \Drupal::config('bombplates_payment.settings')->get('billing_mail'),
      'subject' => $this->t('[BOMBPLATES INTERNAL] Payment Info Updated'),
      'body' => Bombplates\MiscFunc::buildMailBody($body),
    ]);
    if ($message) { drupal_set_message($message); }
    $form_state->setRedirectUrl(\Drupal\Core\Url::fromRoute('entity.user.edit_form', ['user' => $account->uid->value]));
    drupal_set_message($this->t('Your subscription is updated. Thank you!'));
  } // submitForm
} // BombplatesPaymentForm
