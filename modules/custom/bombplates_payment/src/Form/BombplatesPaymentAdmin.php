<?php

/**
 * @file
 * Contains \Drupal\bombplates_payment\Form\BombplatesPaymentAdmin
 */

namespace Drupal\bombplates_payment\Form;

use Drupal\Core\Form\FormInterface;

/**
 * Bombplates payment admin form
 */
class BombplatesPaymentAdmin implements FormInterface {
  use \Drupal\Core\StringTranslation\StringTranslationTrait;
  /**
   * {@inheritdoc}
   */
  public function getFormID(){
    return 'bombplates_payment_admin';
  } // getFormId

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form = \Drupal::moduleHandler()->invokeAll('bombplates_payment_admin_form', []);
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
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
    $modules = system_rebuild_module_data();
    foreach (\Drupal\Core\Render\Element::children($form) AS $module) {
      if (isset($modules[$module]) && $modules[$module]->status) {
        $settings = \Drupal::configFactory()->getEditable("$module.settings");
        $this->recurseAndSubmit($form[$module], $form_state, $settings);
        $settings->save();
        if (isset($form[$module]['#invalidate_cache'])) {
          \Drupal::cache()->invalidate($form[$module]['#invalidate_cache']);
        }
      } // if module is installed
    } // foreach module in Element::children($form)
  } // submitForm

  /**
   * Recurse through a form element to find values and add them to a config object
   *
   * @param array - per drupal forms api $element
   * @param array - per drupal forms api $form_state
   * @param &$settings object - a \Drupal\Core\Config\Config configuration object
   */
  protected function recurseAndSubmit($element, \Drupal\Core\Form\FormStateInterface $form_state, &$settings) {
    foreach (\Drupal\Core\Render\Element::children($element) AS $key) {
      if ($form_state->hasValue($key)) {
        $settings->set($key, $form_state->getValue($key));
      } // if form_state->hasValue(key)
      elseif (isset($element[$key]) && is_array($element[$key])) {
        $this->recurseAndSubmit($element[$key], $form_state, $settings);
      } // if element[key] is array
    } // foreach key in Element::children(form[module])
  } // recurseAndSubmit
} // BombplatesPaymentAdmin
