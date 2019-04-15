<?php

/**
 * @file
 * Describes hooks and plugins provided by the Bombplates module
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Perform an action on a Bombplates user's account.
 *
 * @param $action
 *  The action being performed. By default one of suspend|delete|unsuspend|paid|cancel_subscription.
 * @param $account
 *  A standard drupal user object.
 * @param $options
 *  An array of other options.
 */
function hook_bombplates_process_account($action, $account, $options) {
  switch ($action) {
    case 'suspend' :
      /**
       * @options['missed_payments']
       *    An integer counting the number of new missed payments.
       * @options['increment_pay_date']
       *    A boolean specifying if the user's next payment due date should be incremented.
       * @options['warning_only']
       *    A boolean specifying if any real action should be taken or if the suspension should just be noted.
       */
      break;

    case 'unsuspend' :
      /**
       * @options['log_payments']
       *    A boolean specify if all of the user's existing missed payments should be logged as make up payments.
       * @options['forgive_payments']
       *    A boolean specifying if the user's exiting missed payments should be forgiven. (Mutually-exclusive with log_payments).
       * @options['values']
       *    Assorted data usually via FormState::getStorage and FormState::getValues
       */
      break;

    case 'delete' :
      // No particular data in $options
      break;

    case 'paid' :
      /**
       * @options['payment_time']
       *    An integer containing the Unix timestamp of when the payment was made.
       * @options['payment_title']
       *    Human-readable name
       * @options['count']
       *    Number of months being paid for (default 1)
       */
      break;

    case 'cancel_subscription' :
      // No particular data in $options
      break;

    default:
      // No behavior defined
  }
} // hook_bombplates_process_account

/**
 * List fields that are to be considered protected within the bombplates system. These fields will be blocked from editing by non-admin users.
 *
 * @return array
 *  Strings representing field names
 */
function hook_bombplates_protected_fields() {
  return ['field_bombplates_field1', 'field_bombplates_field2'];
} // hook_bombplates_protected_fields

/**
 * Generate a list of admin links for this module (viewable by a supplied user)
 *
 * @param $account
 *  A partially-loaded user object
 * @return array
 *  Links in a render array
 */
function hook_bombplates_admin_links($account) {
  if ($account && $account->hasPermission('do something')) {
    $result['bombplates'] = [
      '#type' => 'link',
      '#title' => t('Manage Bombplates'),
      '#url' => Url::fromRoute('bombplates'),
    ];
  }
  return $result;
} // hook_bombplates_admin_links

/**
 * @} End of "addtogroup hooks"
 */
