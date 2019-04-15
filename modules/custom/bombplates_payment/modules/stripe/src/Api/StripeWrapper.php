<?php

/**
 * @file
 *  Contains StripeWrapper class definition
 */

namespace Drupal\stripe\Api;

use Drupal\stripe\Inc as StripeFunc;

class StripeWrapper {
  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  /**
   * @var string
   *  The Stripe API key to be used for requests.
   */
  protected $apiKey;

  /**
   * @var string
   *  The base URL for the Stripe API.
   */
  protected $apiBase;

  /**
   * @var string|null
   *  The version of the Stripe API to use for requests.
   */
  protected $apiVersion;

  /**
   * @var array
   *  A cache of data retreived from the API already
   */
  protected $cache;

  ################### GENERAL-USE ###################

  /**
   * Constructor
   *
   * @param string $api_key
   *  As provided by Stripe
   */
  public function __construct($api_key) {
    $this->apiKey = $api_key;
    \Stripe\Stripe::setApiKey($api_key);
    $this->apiBase = 'https://api.stripe.com';
    $this->apiVersion = \Stripe\Stripe::VERSION;
    $this->verifySslCerts = true;
    $this->cache = [];
  } // _construct

  /**
   * Log an error with the Stripe API
   *
   * @param string $action
   *  what were we trying to do (gerunds recommended - e.g. "combatting the robot uprising")
   * @param object $error
   *  a \Stripe\Error object
   * @param string $severity
   *  per watchdog
   */
  protected function logError($action, $stripe_error, $severity = 'WARNING') {
    $body = $stripe_error->getJsonBody();
    if ($body) {
      $err = $body['error'];
      $msg = $this->t(
        'API Error: @stat (@type:@code) error @act: "@msg"',
        [
          '@stat' => $stripe_error->getHttpStatus(),
          '@type' => isset($err['type']) ? $err['type'] : '',
          '@code' => isset($err['code']) ? $err['code'] : '',
          '@act' => $action,
          '@msg' => isset($err['message']) ? $err['message'] : ''
        ]
      );
    } // if body
    else {
      $msg = [
        'Non-API Error: "@msg"',
        ['@msg' => $stripe_error->getMessage()],
      ];
    } // if !body
    \Drupal::logger('Stripe')->{$severity}($msg);
  } // logError

  /**
   * Verify that an object type is valid per the API
   *
   * @param string $type
   *  e.g. Plan, Customer, Card, etc.
   * @param boolean $display_errors
   *  display errors to user?
   * @return mixed
   *  correctly-capitalized string of the type or false on failure
   */
  protected function validateType($type, $display_errors = FALSE) {
    $result = ucwords($type);
    $valid = [
      'ApplicationFee', 'Balance', 'Card', 'Charge', 'Coupon', 'Customer',
      'Event', 'Invoice', 'InvoiceItem', 'List', 'Plan', 'Recipient',
      'Refund', 'Subscription', 'Token', 'Transfer'
    ];
    if (!in_array($result, $valid)) {
      $result = FALSE;
      if ($display_errors) {
        drupal_set_message('Invalid API object "@t" requested', ['@t' => $type], 'warning');
      } // if display_errors
    } // if ! valid
    return $result;
  } // validateType

  /**
   * Validate an array as containing specific data types
   *
   * @param array $in
   *  data input
   * @param array $args
   *  expected keys in the format ('key1', 'key2') or ('key1' => ['alt_name1', 'alt_name2'])
   * @param string $callback
   *  is_int, is_array, is_a, etc.
   * @param boolean $return_and_reset
   *  Instead of returning $this, return and reset $this->validatedData
   * @return array
   *  validated data
   */
  protected function validateByType($in, $args, $callback, $return_and_reset = FALSE) {
    $result = [];
    foreach ($args AS $key => $value) {
      if (is_array($value) && is_string($key)) {
        foreach ($value AS $alt_name) {
          if(isset($in[$alt_name]) && $callback($in[$alt_name])) { $result[$key] = $in[$alt_name]; }
        } // foreach alt_name in value
      } // is_array(value) and is_string(key)
      else {
        if (isset($in[$value]) && $callback($in[$value])) { $result[$value] = $in[$value]; }
      }
    } // foreach key=>value in args
    return $result;
  } // validateByType

  /**
   * Validate string data before passing to the API
   *
   * @param array $in
   *  per validateByType
   * @param array $args
   *  per validateByType
   * @return array
   *  per validateByType
   */
  protected function validateStrings($in, $args) {
    return $this->validateByType($in, $args, 'is_string');
  } // validateStrings

  /**
   * Validate int data before passing to the API
   *
   * @param array $in
   *  per validateByType
   * @param array $args
   *  per validateByType
   * @return array
   *  per validateByType
   */
  protected function validateInts($in, $args) {
    return $this->validateByType($in, $args, 'is_int');
  } // validateInts

  /**
   * Validate timestamp data before passing to the API
   *
   * @param array $in
   *  per validateByType
   * @param array $args
   *  per validateByType
   * @return array
   *  per validateByType
   */
  protected function validateTimestamps($in, $args) {
    return $this->validateByType($in, $args, StripeFunc\MiscFunc::class . '::isTimestamp');
  } // validateTimestamps

  /**
   * Retreive a specific object by type and ID
   *
   * @param string $id
   *  object ID
   * @param string $type
   *  ucword()ed type name
   * @param boolean $display_errors
   *  should errors be sent to drupal_set_message()
   * @param boolean $bypass_cache
   *  should the cache be ignored?
   * @return mixed
   *  object per stripe API or FALSE on failure
   */
  protected function getGeneric($id, $type, $display_errors = FALSE, $bypass_cache = FALSE) {
    if (isset($this->cache[$type][$id]) && !$bypass_cache) {
      $result = $this->cache[$type][$id];
    }
    else {
      $result = FALSE;
      $type = $this->validateType($type, $display_errors);
      if ($id && $type) {
        try {
          $result = call_user_func("\\Stripe\\$type::retrieve", $id);
        }
        catch (\Stripe\Error\Base $e) {
          $err_msg = $this->logError("loading single $type", $e, 'WARNING');
          if ($display_errors) {
            drupal_set_message($err_msg);
          } // if display_errors
        } // try/catch
      } // if id && type
      $this->cache[$type][$id] = $result;
    } // if !cached
    return $result;
  } // getGeneric

  /**
   * Retreive all objects of a specific type
   *
   * @param string $type
   *  type of object to retreive
   * @param array $args
   *  arguments to pass into the API
   * @param boolean $display_errors
   *  should errors be printed to the screen
   * @return array
   *  Objects per stripe API
   */
  protected function getAllGeneric($type, $args = [], $display_errors = FALSE) {
    $result = [];
    $type = $this->validateType($type, $display_errors);
    if ($type) {
      try {
        $api_res = call_user_func("\\Stripe\\$type::all", $args);
        $result = $api_res->data;
      }
      catch (\Stripe\Error\Base $e) {
        $err_msg = $this->logError("loading all $type", $e, 'WARNING');
        if ($display_errors) {
          drupal_set_message($err_msg);
        } // if display_errors
      } // try/catch
    } // if type
    return $result;
  } // getAllGeneric

  ################### PLANS ###################
  /**
   * Retreive all subscription plans
   *
   * @return array
   *  Per getAllGeneric
   */
  public function getPlans() {
    return $this->getAllGeneric('Plan');
  } // getPlans

  /**
   * Retreive a specific subscription plan
   *
   * @param string $id
   *  plan ID
   * @param boolean $display_errors
   *  should errors be sent to drupal_set_message()
   * @param boolean $bypass_cache
   *   should the cache be ignored?
   * @return mixed
   *  object per stripe API or FALSE on failure
   */
  public function getPlan($id, $display_errors = FALSE, $bypass_cache = FALSE) {
    return $this->getGeneric($id, 'Plan', $display_errors, $bypass_cache);
  } // getPlan

  /**
   ** Delete a specific subscription plan
   *
   * @param string $id
   *  plan ID
   * @return boolean
   *  was the deletion successful
   */
  public function deletePlan($id) {
    $result = FALSE;
    $plan = $this->getPlan($id);
    if ($plan) {
      try {
        $plan->delete();
        $result = TRUE;
      } // try
      catch (\Stripe\Error\Base $e) {
        $this->logError('deleting subscription plan', $e, 'WARNING');
      } // catch \Stripe\Error\Base
    } // if plan
    return $result;
  } // deletePlan

  /**
   * Update a specific subscription plan
   *
   * @param string $id
   *  plan ID
   * @param array $changes
   *  containing optionally name, metadata, and statement_descriptor
   * @return mixed
   *  the altered plan object OR boolean FALSE on failure
   */
  public function updatePlan($id, $changes) {
    $result = FALSE;
    $plan = $this->getPlan($id);
    if ($plan) {
      try {
        if (isset($changes['name'])) { $plan->name = $changes['name']; }
        if (isset($changes['statement_descriptor'])) { $plan->statement_descriptor = $changes['statement_descriptor']; }
        if (isset($changes['metadata'])) { $plan->metadata = $changes['metadata']; }
        $plan->save();
      } // try
      catch (StripeError $e) {
        $this->logError('updating subscription plan', $e, 'WARNING');
      } // catch
    } // if plan
    return $result;
  } // updatePlan

  /**
   * Create a new subscription plan
   *
   * @param string $id
   *  plan ID
   * @param array $data
   *  including any of: amount, currency, interval, interval_count, name, trial_period_days, metadata, statement_descriptor
   * @return mixed
   *  plan object for new plan OR boolean FALSE on failure
   */
  public function createPlan($id, $data) {
    $result = FALSE;
    if ($plan = $this->getPlan($id)) {
      $result = $this->updatePlan($id, $data);
    } // if plan exists
    else {
      try {
        $create = $this->validateStrings($data, ['id' => ['id', 'plan_id'], 'name', 'interval', 'currency'])
          + $this->validateInts($data, ['amount', 'interval_count', 'trial_period_days' ]);
        $result = \Stripe\Plan::create($create);
      } // try
      catch (\Stripe\Error\Base $e) {
        $this->logError('creating subscription plan', $e, 'WARNING');
      } // catch \Stripe\Error\Base
    } // !plan exists
    return $result;
  } // createPlan

  ################### CUSTOMER ###################

  /**
   * Retreive a specific customer
   *
   * @param string $id
   *  plan ID
   * @param boolean $display_errors
   *  should errors be sent to drupal_set_message()
   * @param boolean $bypass_cache
   *   should the cache be ignored?
   * @return mixed
   *  object per stripe API or FALSE on failure
   */
  public function getCustomer($id, $display_errors = FALSE, $bypass_cache = FALSE) {
    return $this->getGeneric($id, 'Customer', $display_errors, $bypass_cache);
  } // getPlan

  /**
   * Create a new customer
   *
   * @param array $data
   *  including any of: amount, currency, interval, interval_count, name, trial_period_days, metadata, statement_descriptor
   * @param object $account
   *  User object
   * @return mixed
   *  plan object for new plan OR boolean FALSE on failure
   */
  public function createCustomer($data, $account) {
    $result = FALSE;
    try {
      $data['email'] = isset($data['email']) && \Drupal::service('email.validator')->isValid($data['email']) ? $data['email'] : $account->mail;
      $create = $this->validateTimestamps($data, ['account_balance', 'trial_end' => ['trial_end', 'next_payment']])
        + $this->validateStrings($data, ['plan', 'email', 'source' => ['card', 'source']]);
      $result = \Stripe\Customer::create($create);
    } // try
    catch (\Stripe\Error\Base $e) {
      $this->logError('creating customer', $e, 'WARNING');
    } // catch \Stripe\Error\Base
    return $result;
  } // createCustomer

  /**
   * Update a specific customer
   *
   * @param string $id
   *  customer ID
   * @param array $changes
   *  containing optionally name, metadata, and statement_descriptor
   * @param object $account
   *  user object
   * @return mixed
   *  the altered customer object OR boolean FALSE on failure
   */
  public function updateCustomer($id, $changes, $account) {
    $result = FALSE;
    if ($customer = $this->getCustomer($id)) {
      try {
        $data = $this->validateInts($changes, ['account_balance'])
          + $this->validateStrings($changes, ['email', 'source' => ['source', 'card']]);
        foreach ($data AS $key => $value) {
          $customer->$key = $value;
        } // foreach key=>value in data
        $customer->save();
        if (isset($changes['plan'])) {
          $data = [
            'plan' => is_a($changes['plan'], '\\Stripe\\Plan') ? $changes['plan']->id : (string)$changes['plan'],
          ];
          $data += $this->validateTimestamps($changes, ['trial_end' => ['next_payment', 'trial_end']]);
          if (isset($customer->default_source)) { $data['source'] = $customer->default_source; }
          if ($subscription = reset($customer->subscriptions->data)) {
            $this->updateSubscription($subscription->id, $customer, $data);
          } // if subscription
          else {
            $this->createSubscription($customer, $data);
          } // !subscription
        } // if changes[plan]
        $customer = $this->getCustomer($customer->id, FALSE, TRUE);
      } // try
      catch (\Stripe\Error\Base $e) {
        $this->logError('updating customer', $e, 'WARNING');
      }
      $result = $customer;
    } // if customer
    return $result;
  } // updateCustomer

  /**
   * Delete a Stripe customer and their subscriptions, etc.
   *
   * @param string $id
   *  Stripe ID
   * @return boolean
   *  was the deletion successful?
   */
  public function deleteCustomer($id) {
    $result = FALSE;
    if ($customer = $this->getCustomer($id)) {
      try {
        $customer->delete();
        $result = TRUE;
      } // try
      catch (\Stripe\Error\Base $e) {
        $this->logError('deleting customer', $e, 'WARNING');
      } // catch stripe_error
    } // if customer
    return $result;
  } // deleteCustomer

  ################### SUBSCRIPTION ###################

  /**
   * Retreive a specific subscription
   *
   * @param string $id
   *  subscription ID
   * @param mixed $customer
   *  a customer ID string or a customer object or FALSE (per stripe_api_get_customer)
   * @param boolean $display_errors
   *  should errors be sent to drupal_set_message()?
   * @param boolean $bypass_cache
   *  should the cache be ignored?
   * @return mixed
   *  object per stripe API or FALSE on failure
   */
  public function getSubscription($id, $customer, $display_errors = FALSE, $bypass_cache = FALSE) {
    if (isset($this->cache['subscription'][$id]) && !$bypass_cache) { return $this->cache['subscription'][$id]; }
    $result = FALSE;
    if (is_string($customer)) {
      $customer = $this->getCustomer($customer, $display_errors);
    }
    if (is_a($customer, '\\Stripe\\Customer')) {
      try {
        $result = $customer->subscriptions->retrieve($id);
      } // try
      catch (\Exception $e) {
        $err_msg = $this->logError('loading single subscription', $e, 'WARNING');
        $result = FALSE;
        if ($display_errors) {
          drupal_set_message($err_msg);
        } // if display_errors
      } // try catch
    } // if customer
    $this->cache['subscription'][$id] = $result;
    return $result;
  } // getSubscription

  /**
   * Create a new stripe subscription
   *
   * @param mixed $customer
   *  customer ID or object
   * @param array $changes
   *  subscription data
   * @return mixed
   *  subscription object or FALSE on failure
   */
  public function createSubscription($customer, $data) {
    $result = FALSE;
    $customer = is_a($customer, '\\Stripe\\Customer') ? $customer : $this->getCustomer($customer);
    if ($customer) {
      try {
        $params = $this->validateStrings($changes, ['plan', 'source']);
        $params += $this->validateTimestamps($changes, ['trial_end']);
        $result = $customer->subscriptions->create($params);
      } catch (\Stripe\Error\Base $e) {
        $this->logError('creating subscription', $e, 'WARNING');
        $result = FALSE;
      } // try catch stripe_error
    } // if customer
    return $result;
  } // createSubscription

  /**
   * Update an existing stripe subscription
   *
   * @param string $id
   *  subscription ID
   * @param mixed $customer
   *  customer ID or object
   * @param array $changes
   *  subscription data
   * @return mixed
   *  subscription object or FALSE on failure
   */
  public function updateSubscription($id, $customer, $changes) {
      $result = FALSE;
      $subscription = $this->getSubscription($id, $customer);
      if ($subscription) {
        try {
          $data = $this->validateStrings($changes, ['plan', 'source']);
          foreach ($data AS $key => $value) {
            $subscription->$key = $value;
          }
          $subscription->save();
          $result = $subscription;
        }
        catch (\Stripe\Error\Base $e) {
          $this->logError('updating subscription', $e, 'WARNING');
          $result = FALSE;
        }
      } // if customer
      return $result;
  } // updateSubscription

  /**
   * Cancel a subscription immediately
   *
   * @param mixed $subscription
   *  either subscription ID or \Stripe\Subscription object
   * @param array $data
   *  other parameters
   * @return object
   *  the cancelled subscription object
   */
  public function deleteSubscription($subscription, $customer = '', $data = []) {
    $subscription = is_a($subscription, '\\Stripe\\Subscription') ? $subscription : $this->getSubscription($subscription, $customer);
    if (is_a($subscription, '\\Stripe\\Subscription')) {
      $subscription->cancel($data);
    }
    return $subscription;
  } // stripe_api_delete_subscription

  ################### CARD ###################

  /**
   * Retreive a specific card
   *
   * @param string $id
   *  card ID
   * @param mixed $customer
   *  a customer ID string or a customer object or FALSE (per stripe_api_get_customer)
   * @param boolean $display_errors
   *  should errors be sent to drupal_set_message()
   * @param boolean $bypass_cache
   *  should the cache be ignored?
   * @return mixed
   *  object per stripe API or FALSE on failure
   */
  public function getCard($id, $customer, $display_errors = FALSE, $bypass_cache = FALSE) {
    if (isset($this->cache['card'][$id]) && !$bypass_cache) { return $this->cache['card'][$id]; }
    $result = FALSE;
    if (is_string($customer)) {
      $customer = $this->getCustomer($customer, $display_errors);
    }
    if (is_a($customer, '\\Stripe\\Customer')) {
      try {
        $result = $customer->sources->retrieve($id);
      }
      catch (\Exception $e) {
        $err_msg = $this->logError('loading single card', $e, 'WARNING');
        $result = FALSE;
        if ($display_errors) {
          drupal_set_message($err_msg, 'error');
        } // if display_errors
      } // try catch
    } // if customer
    $this->cache['card'][$id] = $result;
    return $result;
  } // getCard

  ############# TOKEN #############

  /**
   * Retreive a specific token by ID
   *
   * @param string $id
   *  token ID
   * @param boolean $display_errors
   *  should errors be sent to drupal_set_message()
   * @param boolean $bypass_cache
   *  should the cache be ignored?
   * @return mixed
   *  object per stripe API or FALSE on failure
   */
  public function getToken($id, $display_errors = FALSE, $bypass_cache = FALSE) {
    return $this->getGeneric($id, 'Token', $display_errors, $bypass_cache);
  } // getToken

  ############# CHARGE #############

  /**
   * Retreive a specific charge by ID
   *
   * @param string $id
   *  charge ID
   * @param boolean $display_errors
   *  should errors be sent to drupal_set_message()
   * @param boolean $bypass_cache
   *  should the cache be ignored?
   * @return mixed
   *  object per stripe API or FALSE on failure
   */
  public function getCharge($id, $display_errors = FALSE, $bypass_cache = FALSE) {
    return $this->getGeneric($id, 'Charge', $display_errors, $bypass_cache);
  } // getCharge

  /**
   * Load charges by time
   *
   * @param int $gte
   *  timestamp min of selection range
   * @param int $lte
   *  timestamp max of selection range
   * @return array
   *  of \Stripe\Charge objects
   */
  public function getCharges($gte = 0, $lte = 0) {
    $args = ['limit' => 100];
    if ($gte) {
      $args['created']['gte'] = (int)$gte;
    }
    if ($lte) {
      $args['created']['lte'] = (int)$lte;
    }
    return $this->getAllGeneric('Charge', $args);
  } // getCharges

  /**
   * Immediately create a charge against a user
   *
   * @param array $data
   *  identify the user/card and charge amount
   * @param boolean $display_errors
   *  display errors to user?
   * @return mixed
   *  \Stripe\Charge object or FALSE on failure
   */
  public function createCharge($data, $display_errors = FALSE) {
    $result = FALSE;
    try {
      $params = $this->validateStrings($data, ['currency', 'description', 'customer', 'source' => ['source', 'card']])
        + $this->validateInts($data, ['amount']);
      $result = \Stripe\Charge::create($params);
    } catch (\Stripe\Error\Base $e) {
      $err_msg = $this->logError('creating charge', $e, 'WARNING');
      $result = FALSE;
      if ($display_errors) {
        drupal_set_message($err_msg, 'error');
      } // if display_errors
    } // try catch stripe_error
    return $result;
  } // createCharge

  ############# INVOICE #############

  /**
   * Retreive a specific invoice by ID
   *
   * @param string $id
   *  invoice ID
   * @param boolean $display_errors
   *  should errors be sent to drupal_set_message()
   * @param boolean $bypass_cache
   *  should the cache be ignored?
   * @return mixed
   *  object per stripe API or FALSE on failure
   */
  public function getInvoice($id, $display_errors = FALSE, $bypass_cache = FALSE) {
    return $this->getGeneric($id, 'Invoice', $display_errors, $bypass_cache);
  } // getInvoice

  /**
   * Load invoices by time
   *
   * @param int $gte
   *  timestamp min of selection range
   * @param int $lte
   *  timestamp max of selection range
   * @return array
   *  of \Stripe\Invoice objects
   */
  public function getInvoices($gte = 0, $lte = 0) {
    $args = ['limit' => 100];
    if ($gte) {
      $args['date']['gte'] = (int)$gte;
    }
    if ($lte) {
      $args['date']['lte'] = (int)$lte;
    }
    return $this->getAllGeneric('Invoice', $args);
  } // getInvoices
} // StripeWrapper
