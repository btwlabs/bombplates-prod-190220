<?php

/**
 * @file
 *  Contains \Drupal\pw_sync\Inc\FormsFunc
 */

namespace Drupal\pw_sync\Inc;


/**
 * Additional form validation/submit public static functions for pw_sync module
 */
class FormsFunc {

  /**
   * Additional validate handler for user_form form - check for uniqueness
   *
   * @param array $form
   *  Per drupal forms api
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  Per drupal forms api
   */
  public static function userFormAlterValidate($form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // If they didn't change the password, email, or username, do nothing.
    $account = $form_state->getFormObject()->getEntity();
    $mail = $account->getEmail();
    $uname = $account->getUsername();
    $nuname = $values['name'] == $uname ? FALSE : $values['name'];
    $nemail = $values['mail'] == $mail ? FALSE : $values['mail'];
    $collision = FALSE;
    if ($nuname && $nemail) {
      $collision = NetworkFunc::checkAvailable($nuname, $nemail);
    }
    elseif ($nuname) {
      $collision = NetworkFunc::checkAvailable($nuname);
    }
    elseif ($nemail) {
      $collision = NetworkFunc::checkAvailable('',$nemail);
    }
    if ($collision) {
      $warn_only = \Drupal::currentUser()->hasPermission('administer pw_sync');
      foreach ($collision AS $field => $message) {
        if ($warn_only) {
          drupal_set_message(
            t('Warning: possible @f collision "@m"', ['@m' => $message, '@f' => $field]),
            'error'
          );
        }
        else {
          $form_state->setErrorByName($field, $message);
        }
      } // foreach field=>msg in collision
    } // if collision
  } // userFormAlterValidate

  /**
   * Additional submit handler for user_form form - performs syncing
   *
   * @param array $form
   *  Per drupal forms api
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  Per drupal forms api
   */
  public static function userFormAlterSubmitPresave($form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // If they didn't change the password, email, or username, do nothing.
    $account = $form_state->getFormObject()->getEntity();
    $pass = $values['pass'] ? \Drupal::service('password')->hash($values['pass']) : $account->pass->value;
    $mail = $account->getEmail();
    $uname = $account->getUsername();
    $nuname = $values['name'] == $uname ? FALSE : $values['name'];
    $nemail = $values['mail'] == $mail ? FALSE : $values['mail'];
    if (!($pass || $nuname || $nemail)) { return 0; }
    $key = $account->field_pw_sync_key->value;
    $nkey = MiscFunc::generateKey();
    NetworkFunc::updateFamily([
      'account' => $account,
      'uname' => $uname,
      'nuname' => $nuname,
      'nemail' => $nemail,
      'key' => $key,
      'nkey' => $nkey,
      'pass' => $pass,
    ]);
    $account->set('field_pw_sync_key', $nkey)->save();
    $form_state->set('pw_sync_key', $nkey);
  } // userFormAlterSubmitPresave

  /**
   * Additional submit handler for user_form form - performs syncing
   *
   * @param array $form
   *  Per drupal forms api
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  Per drupal forms api
   */
  public static function userFormAlterSubmitPostsave($form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $nkey = $form_state->get('pw_sync_key');
    if ($nkey) {
      $account = $form_state->getFormObject()->getEntity();
      $account->set('field_pw_sync_key', $nkey)->save();
    }
  } // userFormAlterSubmitPostsave

  /**
   * Additional submit handler for user_pass form (client side)
   *
   * @param array $form
   *  Per drupal forms api
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  Per drupal forms api
   */
  public static function forgotPasswordSubmitClient($form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $account = $form_state->getValue('account');
    $config = \Drupal::config('pw_sync.settings');
    $subdomain_field = $config->get('subdomain');
    $domain = $config->get('domain');
    if ($account->hasPermission('Use Password Sync') && $subdomain_field && $domain) {
      \Drupal\pw_sync\Controller\PwSyncController::resync();
    } // if user_access(Use Password Sync)
  } //forgotPasswordSubmitClient

  /**
   * Additional submit handler for user_pass form (server side)
   *
   * @param array $form
   *  Per drupal forms api
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  Per drupal forms api
   */
  public static function forgotPasswordSubmitServer($form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $account = $form_state->getValue('account');
    $config = \Drupal::config('pw_sync.settings');
    $subdomain_field = $config->get('subdomain');
    $domain = $config->get('domain');
    if ($account->hasPermission('Use Password Sync')) {
      $field = $account->get($subdomain_field);
      for ($i = 0; $i < $field->count(); $i++) {
        $subdomain = $field->get($i)->getValue()['value'];
        $url = "http://$subdomain.$domain/pw/resync";
        // Note: this process could be sped up by being asyncronous, but we should only be doing one update at a time anyway
        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
        $c_res = curl_exec($c);
        curl_close($c);
      } // for i = 0..field->count
    } // if user_access(Use Password Sync)
  } //forgotPasswordSubmitServer
} // FormsFunc
