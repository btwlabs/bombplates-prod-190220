<?php

namespace Drupal\bombplates\Plugin\QueueWorker;

/**
 * A queue that sends out system mails on CRON runs
 *
 * @QueueWorker(
 *   id = "bombplates_mail",
 *   title = @Translation("Cron Bombplates Mail"),
 *   cron = {"time" = 10},
 * )
 */

class CronBombplatesMail extends BombplatesMailBase {}
