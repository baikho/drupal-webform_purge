<?php

namespace Drupal\webform_purge\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionStorageInterface;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

/**
 * Class WebformPurgeCommands.
 *
 * @package Drupal\webform_purge\Commands
 */
class WebformPurgeCommands extends DrushCommands {

  use DependencySerializationTrait, LoggerChannelTrait, MessengerTrait, StringTranslationTrait;

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
   * @option purge-type Type of submissions to automatically purge
   *   Can be either "draft", "completed" or "all".
   * @option purge-days Days to retain submissions
   * @command webform-purge:purge
   * @aliases webform-purge-purge,wfpp
   *
   * @throws \Exception
   *   When the Webform doesn't exist for given id or settings.
   */
  public function purge($webform_id, $options = [
    'purge-type' => self::OPT,
    'purge-days' => self::OPT,
  ]) {

    $purge_type = $options['purge-type'] ?: NULL;
    $purge_days = $options['purge-days'] ? (int) $options['purge-days'] : NULL;

    /** @var \Drupal\webform\WebformEntityStorageInterface $webform_storage */
    $webform_storage = $this->entityTypeManager->getStorage('webform');
    $webform_query = $webform_storage->getQuery();
    $webform_query->accessCheck(FALSE);
    $webform_query->condition('id', $webform_id);

    // Purge types is optional, so query all types except 'none' if not given.
    if (!$purge_type) {
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
    /** @var \Drupal\webform\WebformInterface $webform */
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

    $purge_type = $purge_type ?: $webform->getSetting('purge');
    if (in_array($purge_type, [
      WebformSubmissionStorageInterface::PURGE_DRAFT,
      WebformSubmissionStorageInterface::PURGE_COMPLETED,
    ], TRUE)) {
      $webform_submission_query->condition('in_draft', $purge_type === WebformSubmissionStorageInterface::PURGE_DRAFT ? 1 : 0);
    }

    $results = $webform_submission_query->execute();

    if (empty($results)) {
      throw new \Exception(dt('No submissions to purge for given query.'));
    }

    $webform_submission_total = $webform_submission_storage->getTotal($webform);

    if (!$this->io()->confirm($this->t("Are you sure you want to delete @count of @total from '@title' webform?", [
      '@count' => count($results),
      '@total' => $webform_submission_total,
      '@title' => $webform->label(),
    ]))) {
      throw new UserAbortException();
    }

    // Define a batch operation.
    $batch_definition = [
      'title' => $this->t('Clear submissions'),
      'init_message' => $this->t('Clearing submission data'),
      'error_message' => $this->t('The submissions could not be cleared because an error occurred.'),
      'operations' => [
        [
          [$this, 'batchProcess'],
          [$webform, $results],
        ],
      ],
      'finished' => [
        $this, 'batchFinish',
      ],
    ];

    batch_set($batch_definition);
    drush_backend_batch_process();
  }

  /**
   * Webform purge batch API process callback.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The Webform instance.
   * @param array $submission_ids
   *   An array of Webform Submission Ids.
   * @param mixed|array $context
   *   The batch current context.
   */
  public function batchProcess(WebformInterface $webform, array $submission_ids, &$context) {

    if (!isset($context['sandbox']['progress'])) {

      // Init $sandbox vars.
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['limit'] = 500;
      $context['sandbox']['submission_ids'] = $submission_ids;
      $context['sandbox']['total'] = count($submission_ids);
    }

    $batch_submission_ids = array_slice($context['sandbox']['submission_ids'], $context['sandbox']['progress'], $context['sandbox']['limit'], TRUE);
    /** @var \Drupal\webform\WebformSubmissionStorageInterface $webform_submission_storage */
    $webform_submission_storage = $this->entityTypeManager->getStorage('webform_submission');
    $webform_submissions = $webform_submission_storage->loadMultiple(array_keys($batch_submission_ids));

    foreach ($webform_submissions as $webform_submission) {
      $context['sandbox']['progress']++;
      try {
        $webform_submission->delete();
      }
      catch (EntityStorageException $e) {
        $this->getLogger('webform_purge')->error($this->t('Failed to delete "@webform_submission"', [
          '@webform_submission' => $webform_submission->id(),
        ]));
      }
    }

    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
  }

  /**
   * Webform purge batch API finish callback.
   *
   * @param bool $success
   *   TRUE if batch successfully completed.
   * @param array $results
   *   Batch results.
   */
  public function batchFinish($success, array $results) {
    if (!$success) {
      $this->messenger()->addStatus($this->t('Finished with an error.'));
    }
    else {
      $this->messenger()->addStatus('Process finished.');
    }
  }

}
