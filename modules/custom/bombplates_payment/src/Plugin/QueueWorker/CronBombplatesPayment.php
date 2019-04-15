<?php

namespace Drupal\bombplates_payment\Plugin\QueueWorker;

/**
 * A queue that logs payments on CRON runs
 *
 * @QueueWorker(
 *   id = "bombplates_payment",
 *   title = @Translation("Cron Bombplates Payment Processing"),
 *   cron = {"time" = 10},
 * )
 */

class CronBombplatesPayment extends BombplatesPaymentBase {}
