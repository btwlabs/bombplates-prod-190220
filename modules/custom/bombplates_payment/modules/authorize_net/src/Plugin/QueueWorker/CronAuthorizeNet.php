<?php

namespace Drupal\authorize_net\Plugin\QueueWorker;

/**
 * A queue that logs payments on CRON runs
 *
 * @QueueWorker(
 *   id = "authorize_net",
 *   title = @Translation("Cron Authorize.net Payment Processing"),
 *   cron = {"time" = 10},
 * )
 */

class CronAuthorizeNet extends AuthorizeNetBase {}
