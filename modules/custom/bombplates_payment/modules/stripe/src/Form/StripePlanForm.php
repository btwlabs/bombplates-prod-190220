<?php

/**
 * @file
 * contains \Drupal\stripe\Form\StripePlanForm
 */

namespace Drupal\stripe\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\stripe\Inc as StripeFunc;

class StripePlanForm implements FormInterface {
  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormID(){
    return 'stripe_plan_form';
  } // getFormId

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $plan_id = '') {
    $show_form = TRUE;
    $config = \Drupal::config('stripe.settings');
    $stripe = StripeFunc\MiscFunc::loadApi();
    if ($stripe) {
      if ($plan_id) {
        $plan = $stripe->getPlan($plan_id);
        if ($plan->error) {
          drupal_set_message($this->t('Error retreiving exiting plan: "@err"', ['@err' => $plan->error]), 'error');
          $show_form = FALSE;
        }
      } // if plan_id
      else {
        $form['new_plan'] = [
          '#type' => 'value',
          '#value' => TRUE,
        ];
      } // !plan_id
      if ($show_form) {
        if ($plan_id) {
          $form['plan_id'] = [
            '#type' => 'value',
            '#value' => $plan_id,
          ];
        } // if plan_id
        else {
          $form['plan_id'] = [
            '#type' => 'textfield',
            '#required' => TRUE,
            '#title' => $this->t('Plan ID'),
            '#description' => $this->t('Must be unique and consist of lowercase alphanumerics and underscores.'),
            '#default_value' => '',
          ];
        } // !plan_id
        $form['name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Plan Name'),
          '#default_value' => isset($plan->name) ? $plan->name : '',
        ];
        if (!$plan_id) {
          $form['amount'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Amount'),
            '#description' => $this->t('Plan price in cents (e.g. 2000 for $20)'),
            '#default_value' => isset($plan->amount) ? $plan->amount : '2000',
          ];
          $form['currency'] = [
            '#type' => 'value',
            '#value' => 'USD',
          ];
          $form['interval'] = [
            '#type' => 'value',
            '#value' => 'month',
          ];
          $form['interval_count'] = [
            '#type' => 'select',
            '#title' => $this->t('Months Per Payment'),
            '#description' => $this->t('Number of months per payment'),
            '#options' => array_combine(range(1,24), range(1,24)),
            '#default_value' => isset($plan->interval_count) ? $plan->interval_count : 1,
          ];
        } // !plan_id
        $form['statement_descriptor'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Statement Descriptor'),
          '#description' => $this->t("Description to attach to a customer's credit card statement"),
          '#default_value' => isset($plan->statement_descriptor) ? $plan->statement_descriptor : $config->get('stripe_default_descriptor'),
          '#maxlength' => 22,
        ];
        $plans_enabled = $config->get('stripe_enabled_plans');
        $form['enabled'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Plan Enabled?'),
          '#description' => $this->t('Should users be able to select this plan when updating their subscriptions?'),
          '#default_value' => isset($plans_enabled[$plan_id]) ? $plans_enabled[$plan_id] : TRUE,
        ];
        $form['buttons'] = [
          [
            'submit' => [
              '#type' => 'submit',
              '#value' => $this->t('Save'),
            ],
          ],
        ];
        if ($plan_id) {
          $form['buttons']['delete'] = [
            '#type' => 'submit',
            '#value' => $this->t('Delete')->render(),
            '#description' => $this->t('Note: Deletion is permanent; you will not be promped for confirmation.'),
          ];
        } // if plan_id
      } // if show_form
    } // if stripe
    return $form;
  } // buildForm

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = \Drupal::config('stripe.settings');
    $stripe = StripeFunc\MiscFunc::loadApi();
    if (!$stripe) {
      $url = \Drupal\Core\Url::fromUri("internal:/admin/config/payment/settings");
      $msg = $this->t(
        'Stripe is misconfigured. @lnk',
        ['@lnk' => \Drupal\Core\Link::fromTextAndUrl($this->t('You need to set your Stripe keys'), $url)->toString()]
      );
      $form_state->setErrorByName('', $msg);
    } // if !stripe
    if (!isset($form['buttons']['delete']['#value']) || $values['op'] != $form['buttons']['delete']['#value']) {
      if (!preg_match('/^[a-z0-9_]+$/', $values['plan_id'])) {
        $form_state->setErrorByName('plan_id', $this->t('Plan ID must consist only of lowercase letters, numbers, and underscores'));
      }
      if (isset($values['amount']) && !preg_match('/^[1-9][0-9]*$/', $values['amount'])) {
        $form_state->setErrorByName('amount', $this->t('Amount must be a positive integer'));
      }
    } // !delete button hit
  } // validateForm

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = \Drupal::config('stripe.settings');
    $stripe = StripeFunc\MiscFunc::loadApi();
    $plans_enabled = $config->get('stripe_enabled_plans');
    $plan_id = $values['plan_id'];
    if ($values['op'] == $form['buttons']['delete']['#value']) {
      unset($plans_enabled[$plan_id]);
      $stripe->deletePlan($plan_id);
      drupal_set_message($this->t('Stripe plan "@p" deleted', ['@p' => $plan_id]));
    } // if delete
    elseif ($values['new_plan']) {
      $data = $values;
      $data['amount'] = (int)$data['amount'];
      $data['interval_count'] = (int)$data['interval_count'];
      $plan = $stripe->createPlan($plan_id, $data);
      $plans_enabled[$plan_id] = $values['enabled'];
    } // if new_plan
    else {
      $plan = $stripe->updatePlan($plan_id, $values);
      $plans_enabled[$plan_id] = $values['enabled'];
    } // else
    \Drupal::configFactory()->getEditable('stripe.settings')->set('stripe_enabled_plans', $plans_enabled)->save();
    $form_state->setRedirect('payment_admin');
    //$form_state->setRedirect(\Drupal\Core\Url::fromUri('internal:/admin/config/payment/settings'));
  } // submitForm
} // StripePaymentPlanForm
