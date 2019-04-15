<?php

/**
 * @file
 *   Describes hooks and functions for bombplates_payment modules.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * hook_bombplates_payment_list - list this module as a payment processer. Should only be implemented if hook_bombplates_payment_form is also implemented
 *
 * @return array
 *  keys are string machine-readable names. Values are string human-readable names
 */
function hook_bombplates_payment_list() {
  return ['bombplates_payment' => t('Bombplates Payment')];
} // hook_bombplates_payment_list

/**
 * hook_bombplates_payment_admin_form - Add custom fields to the main payment settings form
 *
 * @return array
 *  per drupal forms api.
 *  It's recommended to wrap all results in a div with the ID MODULE_bombplates_payment_settings
 *  and class "bombplates-payment-settings" to allow showing/hiding.
 *  The option #invalidate_cache allows each module to designate a cache to clear on form submission
 */
function hook_bombplates_payment_admin_form() {
  $form = [];
  $form['bombplates_payment'] = [
    '#type' => 'details',
    '#prefix' => '<div id="stripe_bombplates_payment_settings" class="bombplates-payment-settings">',
    '#suffix' => '</div>',
    'some text' => [
      '#type' => 'markup',
      '#markup' => t('More form fields would go here'),
    ],
    '#invalidate_cache' => 'bombplates:hook_bombplates:cache',
  ];
  return $form;
} // hook_bombplates_payment_admin_form

/**
 * Generate/extend the payment form
 *
 * @param array $form
 *  default payment form may contain the following elements
 *  $form['#account'] - user object
 *  $form['missed_payments'] - Message alerting user to missing payments. Contains count and cost fields
 *  $form['submit'] - submit button (appended after invoking this hook)
 *
 * @param string $service
 *  Name of the currently-enabled primary payment service
 *
 * @return array
 *  the same form extended.
 *  The default submit function invokes invokeAll('bombplates_process_account','unsuspend') as appropriate
 *  Data added to FormState->storage and FormState->values during validation will be passed through automatically
 *    Submit functions that wish to apply additional data will need to execute before the default handler
 */
function hook_bombplates_payment_form($form) {
  $form['my_module'] = [
    'my_field' => [
      '#type' => 'textfield',
      '#title' => t('Enter a value'),
      '#default_value' => variable_get('my_module_myvar', 'Value!'),
    ],
  ];
  return $form;
} // hook_bombplates_payment_form

/**
 * hook_bombplates_payment_find_paying_users - List all users that this module handles payment for
 *  i.e. this module is responsible for invoking bombplates_bombplates_process_account('paid') - or the queue equivalent
 *  Current payment status is irrelevant. (i.e. do not exclude delinquent accounts)
 *
 * @return array
 *  key(s) is module name, values is an array of user uids
 */
function hook_bombplates_payment_find_paying_users() {
  return ['bombplates_payment' => [0,1]];
} // hook_bombplates_payment_find_paying_users

/**
 * hook_bombplates_payment_alter - alter a payment node before it is fully-submitted
 *
 * @param &$data object
 *  The new node object being created
 * @param &$context array
 *  Containing 'account' object (per user) and 'values' array (arbitrary per module or per FormState::getValues())
 */
function hook_bombplates_payment_alter(&$data, &$context) {
  $data->my_custom_field = "Some Value";
} // hook_bombplates_payment_alter

/**
 *
 * @} end of "addtogroup hooks"
 */
