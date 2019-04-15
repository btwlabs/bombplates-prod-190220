<?php

/**
 * @file
 *  Contains \Drupal\bombplates_forms\Controller\BombplatesFormsManageModal
 */

namespace Drupal\bombplates_forms\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\bombplates_forms\Inc as BombplatesForms;

/**
 * Launch a modal account management form
 */
class BombplatesFormsManageModal extends ControllerBase {
  /**
   * The form builder
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * Constructor
   *
   * @param FormBuilder $formBuilder
   *    The form builder
   */
  public function __construct(FormBuilder $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  } // create

  /**
   * Callback for opening the modal form.
   *
   * @param string $type
   *    Name of a class that impliments Drupal\bombplates_forms\Form\Manage\BombplatesFormsManageBase
   */
  public function openModalForm($type) {
    $response = new AjaxResponse();

    $class = '\\Drupal\\bombplates_forms\\Form\\Manage\\' . $type;
    if (class_exists($class)) {
      // Get the modal form using the form builder.
      $modal_contents = $this->formBuilder->getForm($class);
    }
    else {
      $modal_contents = $this->t('Error: invalid action specified');
    }

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand(
      (string)$this->t('@t Account', ['@t' => $type]),
      $modal_contents,
      ['minWidth' => 600]
    ));

    return $response;
  } // openModalForm
} // BombplatesFormsManageModal
