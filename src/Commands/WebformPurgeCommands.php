<?php

namespace Drupal\webform_purge\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\webform\WebformSubmissionStorageInterface;
use Drush\Commands\DrushCommands;

/**
 * Class WebformPurgeCommands.
 *
 * @package Drupal\webform_purge\Commands
 */
class WebformPurgeCommands extends DrushCommands {

  /**
   * Conversion of 1 day in seconds (60 * 60 * 24)
   *
   * @var int
   */
  public const DAY_IN_SECONDS = 86400;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a WebformPurgeCommands object.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(TimeInterface $time, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct();
    $this->time = $time;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Runs Webform Purge command.
   *
   * @option purge-types Types of submissions to automatically purge
   * @option purge-days Days to retain submissions
   * @command webform-purge:purge
   * @aliases webform-purge-purge,wfpp
   *
   * @throws \Exception
   *   When the Webform doesn't exist for given id.
   */
  public function purge($webform_id, $options = [
    'purge-types' => self::OPT,
    'purge-days' => self::OPT,
  ]) {
    $purge_types = $options['purge-types'] ?: NULL;
    $purge_days = $options['purge-days'] ? (int) $options['purge-days'] : NULL;

    /** @var \Drupal\webform\WebformEntityStorageInterface $webform_storage */
    $webform_storage = $this->entityTypeManager->getStorage('webform');
    $webform_query = $webform_storage->getQuery();
    $webform_query->accessCheck(FALSE);
    $webform_query->condition('id', $webform_id);

    // Purge types is optional, so query all types except 'none' if not given.
    if (!$purge_types) {
      $webform_query->condition('settings.purge', [
        WebformSubmissionStorageInterface::PURGE_DRAFT,
        WebformSubmissionStorageInterface::PURGE_COMPLETED,
        WebformSubmissionStorageInterface::PURGE_ALL,
      ], 'IN');
    }

    // Purge argument is optional, so check for Webform setting if not given.
    if (!$purge_days) {
      $webform_query->condition('settings.purge_days', 0, '>');
    }

    $results = $webform_query->execute();
    $webform = !empty($results) ? $webform_storage->load(reset($results)) : NULL;

    if (!$webform) {
      throw new \Exception(dt('Webform not found or not eligible for purge settings.'));
    }

    /** @var \Drupal\webform\WebformSubmissionStorageInterface $webform_submission_storage */
    $webform_submission_storage = $this->entityTypeManager->getStorage('webform_submission');
    $webform_submission_query = $webform_submission_storage->getQuery();
    $webform_submission_query->accessCheck(FALSE);
    $webform_submission_query->condition('webform_id', $webform->id());

    $purge_days = $purge_days ?: $webform->getSetting('purge_days');
    $webform_submission_query->condition('created', $this->time->getRequestTime() - ($purge_days * self::DAY_IN_SECONDS), '<');

  }

}
