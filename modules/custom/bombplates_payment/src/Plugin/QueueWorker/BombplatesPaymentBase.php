<?php

/**
 * @file
 * Contains Drupal\bombplates_payment\Plugin\QueueWorker\BombplatesPaymentBase
 */

namespace Drupal\bombplates_payment\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\bombplates_payment\Inc as BombplatesPayment;

/**
 * Provides base functionality for BombplatesPayment queue workers.
 */
abstract class BombplatesPaymentBase extends \Drupal\bombplates\Plugin\QueueWorker\BombplatesProcessBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $account = $data->account;
    $options = $data->options;
    $title = $options['title'];
    $values = $options['values'];
    BombplatesPayment\MiscFunc::logPayment($title, $account, $values);
  } // processItem
} // BombplatesPaymentBase
