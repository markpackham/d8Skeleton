<?php

namespace Drupal\node_revision_delete\Utility;

use Drupal\node_revision_delete\NodeRevisionDeleteBatch;

/**
 * Provides module internal helper methods.
 *
 * @ingroup utility
 */
class Batch {

  /**
   * Return the revision deletion batch definition.
   *
   * @param array $revisions
   *   The revisions array.
   * @param bool $dry_run
   *   The dry run option.
   *
   * @return array
   *   The batch definition.
   */
  public static function getRevisionDeletionBatch(array $revisions, $dry_run) {
    $operations = [];
    // Loop through the revisions to delete, create batch operations array.
    foreach ($revisions as $revision) {
      $operations[] = [
        [NodeRevisionDeleteBatch::class, 'deleteRevision'],
        [$revision, $dry_run],
      ];
    }

    // Create batch to delete revisions.
    $batch = [
      'title' => t('Deleting revisions'),
      'init_message' => t('Starting to delete revisions.'),
      'progress_message' => t('Deleted @current out of @total (@percentage%). Estimated time: @estimate.'),
      'error_message' => t('Error deleting revisions.'),
      'operations' => $operations,
      'finished' => [NodeRevisionDeleteBatch::class, 'finish'],
    ];

    return $batch;
  }

}
