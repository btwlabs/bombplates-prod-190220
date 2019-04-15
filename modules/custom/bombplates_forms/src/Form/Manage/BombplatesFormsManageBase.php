<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Form\Manage\BombplatesFormsManageBase
 */

namespace Drupal\bombplates_forms\Form\Manage;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\bombplates_forms\Inc as BombplatesForms;

/**
 * Modal "claim account" form
 */
abstract class BombplatesFormsManageBase extends FormBase {
  /**
   * Gets the configuration names that will be editable
   *
   * @return array
   *    Configuration object names
   */
  protected function getEditableConfigNames() {
    return ['config.' . $self->getFormId()];
  } // getEditableConfigNames

  /**
   * Build the form - base form functionality that all children will likely use
   *
   * @return array
   *    Per drupal form api
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="bp_manage_form_modal">';
    $form['#suffix'] = '</div>';
    return $form;
  } // buildForm

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $response = new \Drupal\Core\Ajax\AjaxResponse();

    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new \Drupal\Core\Ajax\ReplaceCommand('#bp_manage_form_modal', $form));
    }
    else {
      $response->addCommand(new \Drupal\Core\Ajax\OpenModalDialogCommand("Success!", $this->successMessage()));
    }
    return $response;
  } // submitModalFormAjax

  /**
   * Generate a success message after submitting the form
   */
  protected function successMessage() {
    return $this->t('Success!');
  } // successMessage

  /**
   * List all managable accounts for select forms
   *
   * @return array
   *    Per #options in form API - uids and strings
   */
  protected function loadManageableOptions() {
    $result = [];
    $config = \Drupal::config('bombplates_forms.settings');
    $client_rids = [$config->get('role_grants.on_launch.grant'), $config->get('role_grants.on_payment.grant')];
    $client_uids = \Drupal::entityQuery('user')
      ->condition('roles', $client_rids, 'IN')
      ->execute();
    $clients = \Drupal\user\Entity\User::loadMultiple($client_uids);
    foreach ($clients as $uid => $client) {
      if ($client->field_subdomain->value) {
        $result[$uid] = $this->t('@s.bombplates.com (@n)', ['@s' => $client->field_subdomain->value, '@n' => $client->name->value]);
      }
      else {
        $result[$uid] = $this->t('@n (site incomplete)', ['@n' => $client->name->value]);
      }
    } // foreach uid=>client in clients
    asort($result);
    return $result;
  } // loadManageableOptions

  /**
   * List all accounts managed by a specific user
   *
   * @param mixed $suspended
   *    0|1 for [un]suspended or NULL for both
   * @param object $account
   *    A user object. Defaults to current
   * @return array
   *    Per #options in form API - uids and strings
   */
  protected function loadManagedOptions($suspended = NULL, $account = NULL) {
    if (!$account) { $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id()); }
    $result = [];
    foreach ($account->field_accounts_managed AS $fam) {
      $client = \Drupal\user\Entity\User::load($fam->target_id);
      if (isset($client->uid) && $client->uid) {
        if (is_null($suspended) || $client->field_suspended->value == $suspended) {
          $str = '@n (site incomplete)';
          $vars = ['@n' => $client->name->value];
          if ($client->field_subdomain->value) {
            $str = '@s.bombplates.com (@n)';
            $vars ['@s'] = $client->field_subdomain->value;
          }
          if ($client->field_missed_payments->value) {
            $str .= ' (@p missing payments)';
            $vars['@p'] = $client->field_missed_payments->value;
          }
          $result[$client->uid->value] = $this->t($str, $vars);
        } // if user is appropriately suspended or not
      } // if fam->target_id
    } // foreach $fam in account->field_accounts_managed
    asort($result);
    return $result;
  } // loadManagedOptions

  /**
   * List all account managers for select forms
   *
   * @return array
   *    Per #options in form API - uids and strings
   */
  protected function loadAccountManagersOptions() {
    $result = [];
    $am_uids = \Drupal::entityQuery('user')
      ->condition('roles', 'account_manager', '=')
      ->execute();
    $ams = \Drupal\user\Entity\User::loadMultiple($am_uids);
    foreach ($ams AS $uid => $am) {
      $result[$uid] = $am->name->value;
    } // foreach uid=>am in ams
    asort($result);
    return $result;
  } // loadAccountManagersOptions

} // BombplatesFormsManageBase
