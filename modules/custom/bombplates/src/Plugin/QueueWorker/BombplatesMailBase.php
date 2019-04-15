<?php

/**
 * @file
 * Contains Drupal\bombplates\Plugin\QueueWorker\BombplatesMailBase.php
 */

namespace Drupal\bombplates\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides base functionality for BombplatesMail queue workers.
 */
abstract class BombplatesMailBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
    $to = $data->to;
    $from = $data->from;
    $key = $data->key;
    $langcode = $data->langcode;
    $params = [
      'body' => $data->body,
      'subject' => $data->subject,
      'is_external' => $data->is_external,
      'to' => $to,
      'from' => $from,
    ];
    \Drupal::service('plugin.manager.mail')->mail('bombplates', $key, $to, $langcode, $params, $from);
  }
} // BombplatesMailBase
