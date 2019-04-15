<?php

/**
 * @file
 * Contains Drupal\bombplates\Plugin\QueueWorker\BombplatesProcessBase
 */

namespace Drupal\bombplates\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides base functionality for BombplatesProcess queue workers.
 */
abstract class BombplatesProcessBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct() {
  } // __construct

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static();
  } // create

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $action = $data->action;
    $account = $data->account;
    $options = $data->options;
    \Drupal::moduleHandler()->invokeAll('bombplates_process_account', [$action, $account, $options]);
  } // processItem
} // BombplatesMailBase
