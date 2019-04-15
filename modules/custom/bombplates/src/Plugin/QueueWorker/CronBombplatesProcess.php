<?php

namespace Drupal\bombplates\Plugin\QueueWorker;

/**
 * A queue that sends out system mails on CRON runs
 *
 * @QueueWorker(
 *   id = "bombplates_process",
 *   title = @Translation("Cron Bombplates Processing"),
 *   cron = {"time" = 10},
 * )
 */

class CronBombplatesProcess extends BombplatesProcessBase {}
