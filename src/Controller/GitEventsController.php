<?php
/**
 * @file
 * Contains \Drupal\git_events\Controller\GitEventsController.
 */

namespace Drupal\git_events\Controller;

use Drupal\Core\Controller\ControllerBase;

class GitEventsController extends ControllerBase {
  public function content() {
    return array(
        '#type' => 'markup',
        '#markup' => $this->t('GIT Events'),
    );
  }
}