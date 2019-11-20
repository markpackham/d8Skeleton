<?php

namespace Drupal\node_revision_generate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\node_revision_generate\NodeRevisionGenerateBatch;

/**
 * Class NodeRevisionGenerate.
 */
class NodeRevisionGenerate extends FormBase {

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A date time instance.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new NodeRevisionGenerate object.
   */
  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time, Connection $database) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_revision_generate_generate_revisions';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get all Content types.
    $content_type_code = [];
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($node_types as $type) {
      $content_type_code[$type->id()] = $type->label();
    }

    // Sort the content types by content type name.
    asort($content_type_code);

    $form['bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types'),
      '#options' => $content_type_code,
      '#required' => TRUE,
    ];

    $form['revisions_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Revisions number'),
      '#min' => 1,
      '#default_value' => 1,
      '#description' => $this->t('The maximum number of revisions that will be created for each node of the selected content types.'),
      '#required' => TRUE,
    ];

    $form['age'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Revisions age'),
      '#description' => $this->t('The age between each generated revision.'),
      '#required' => TRUE,
    ];

    $form['age']['number'] = [
      '#type' => 'number',
      '#min' => 1,
      '#default_value' => 1,
      '#required' => TRUE,
    ];

    $time_options = [
      '86400' => $this->t('Day'),
      '604800' => $this->t('Week'),
      '2629743' => $this->t('Month'),
    ];

    $form['age']['time'] = [
      '#type' => 'select',
      '#options' => $time_options,
    ];

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('The first revision will be generated starting from the created date of the last node revision and the last one will not have a date in the future. So, depending on this maybe we will not generate the number of revisions you expect.'),
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate revisions'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get selected content types.
    $bundles = array_filter($form_state->getValue('bundles'));

    // Get form values.
    $revisions_number = $form_state->getValue('revisions_number');
    $interval_number  = $form_state->getValue('number');
    $interval_time    = $form_state->getValue('time');

    // Get interval to generate revisions.
    $revisions_age = $interval_number * $interval_time;

    // Set batch for generate revisions.
    $this->setBatchForRevisions($bundles, $revisions_number, $revisions_age);
  }

  /**
   * Generate the operations for selected nodes and set the batch.
   *
   * @param array $bundles
   *   An array with the selected content types to generate node revisions.
   * @param int $revisions_number
   *   Number of revisions to generate.
   * @param int $revisions_age
   *   Interval in Unix timestamp format to add to the last revision date of the
   *   node.
   */
  protected function setBatchForRevisions(array $bundles, $revisions_number, $revisions_age) {
    // Get the available nodes to generate revisions.
    $nodes_for_revisions = $this->getAvailableNodesForRevisions($bundles, $revisions_age);

    // Check if there is nodes to generate revisions.
    if ($nodes_for_revisions) {
      // Build batch operations, one per revision.
      $operations = [];
      foreach ($nodes_for_revisions as $node) {
        $revision_timestamp = $node->revision_timestamp;
        // Initializing variables.
        $i = 0;
        $revision_timestamp += $revisions_age;
        // Adding operations.
        while ($i < $revisions_number && $revision_timestamp <= $this->time->getRequestTime()) {
          $operations[] = [
            [NodeRevisionGenerateBatch::class, 'generateRevisionsBatchProcess'],
            [$node->nid, $revision_timestamp],
          ];
          $revision_timestamp += $revisions_age;
          $i++;
        }
      }
      $batch = [
        'title' => $this->t('Generate revisions'),
        'operations' => $operations,
        'init_message' => $this->t('Starting the creation of revisions.'),
        'progress_message' => $this->t('Processed @current out of @total (@percentage%). Estimated time: @estimate.'),
        'finished' => [NodeRevisionGenerateBatch::class, 'finish'],
        'error_message' => $this->t('The revision creation process has encountered an error.'),
      ];

      batch_set($batch);
    }
    else {
      $this->messenger()->addWarning($this->t('There are not more available nodes to generate revisions of the selected content types and specified options.'));
    }
  }

  /**
   * Get the available nodes to generate revisions.
   *
   * Returns the ids of the available nodes to generate the revisions and the
   * next date (Unix timestamp) of the revision to be generated for that node.
   *
   * @param array $bundles
   *   An array with the selected content types to generate node revisions.
   * @param int $revisions_age
   *   Interval in Unix timestamp format to add to the last revision date of the
   *   node.
   *
   * @return array
   *   Returns the available nodes ids to generate the revisions and its next
   *   revision date.
   */
  private function getAvailableNodesForRevisions(array $bundles, $revisions_age) {
    // Variable with the placeholders arguments needed for the expression.
    $interval_time = [
      ':interval' => $revisions_age,
      ':current_time' => $this->time->getRequestTime(),
    ];

    $query = $this->database->select('node_field_data', 'node');
    // Get/check the last revision (vid).
    $query->leftJoin('node_revision', 'revision', 'node.vid = revision.vid');
    // Get the node id to generate revisions.
    $query->addField('node', 'nid', 'nid');
    // Get the node id to generate revisions.
    $query->addField('revision', 'revision_timestamp', 'revision_timestamp');
    // Get nodes with title to avoid some error on save it.
    $query->isNotNull('node.title');
    // Get nodes of selected content types (bundles).
    $query->condition('node.type', $bundles, 'IN');
    // Get only the published nodes.
    $query->condition('node.status', 1);
    // Check the next date to generate the revision be <= current date.
    $query->where('revision.revision_timestamp + :interval <= :current_time', $interval_time);
    // Return the available nodes ids and its next revision date, as array.
    return $query->execute()->fetchAll();
  }

}
