<?php

namespace Drupal\node_revision_delete\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node_revision_delete\NodeRevisionDeleteInterface;
use Drupal\Core\Link;
use Drupal\Core\Datetime\DateFormatterInterface;

/**
 * Class CandidateNodesForm.
 *
 * @package Drupal\node_revision_delete\Form
 */
class CandidateNodesForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The node revision delete interface.
   *
   * @var \Drupal\node_revision_delete\NodeRevisionDeleteInterface
   */
  protected $nodeRevisionDelete;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\node_revision_delete\NodeRevisionDeleteInterface $node_revision_delete
   *   The node revision delete.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    NodeRevisionDeleteInterface $node_revision_delete,
    DateFormatterInterface $date_formatter
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeRevisionDelete = $node_revision_delete;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('node_revision_delete'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'candidates_nodes';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $content_type = NULL) {
    // Table header.
    $header = [
      $this->t('Nid'),
      $this->t('Title'),
      $this->t('Author'),
      $this->t('Status'),
      $this->t('Updated'),
      $this->t('Candidate revisions'),
      $this->t('Operations'),
    ];
    // Table rows.
    $rows = [];
    // Getting the cantidate nodes.
    $candidate_nodes = $this->nodeRevisionDelete->getCandidatesNodes($content_type);
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($candidate_nodes);

    /* @var $node \Drupal\node\Entity\Node */
    foreach ($nodes as $node) {
      $nid = $node->id();

      $route_parameters = [
        'content_type' => $content_type,
        'nid' => $nid,
      ];

      // Number of candidate revisions.
      $candidate_revisions = count($this->nodeRevisionDelete->getCandidatesRevisionsByNids([$nid]));
      // Creating a link to the candidate revisions page.
      $candidate_revisions = Link::createFromRoute($candidate_revisions, 'node_revision_delete.candidate_revisions', $route_parameters);

      $dropbutton = [
        '#type' => 'dropbutton',
        '#links' => [
          // Action to delete revisions.
          'delete_revisions' => [
            'title' => $this->t('Delete revisions'),
            'url' => Url::fromRoute('node_revision_delete.candidate_nodes_revisions_delete_confirm', $route_parameters),
          ],
        ],
      ];

      // Setting the row values.
      $rows[$nid] = [
        $nid,
        Link::fromTextAndUrl($node->getTitle(), $node->toUrl('canonical')),
        $node->getOwner()->getAccountName() ? Link::fromTextAndUrl($node->getOwner()->getAccountName(), $node->getOwner()->toUrl('canonical')) : $this->t('Anonymous (not verified)'),
        $node->isPublished() ? $this->t('Published') : $this->t('Not published'),
        $this->dateFormatter->format($node->getChangedTime(), 'short'),
        $candidate_revisions,
        [
          'data' => $dropbutton,
        ],
      ];
    }

    /* @var $content_type \Drupal\node\Entity\NodeType */
    $content_type = $this->entityTypeManager->getStorage('node_type')->load($content_type);
    $content_type_url = $content_type->toUrl()->toString();
    $caption = $this->t('Candidates nodes for content type <a href=":url">%title</a>', [':url' => $content_type_url, '%title' => $content_type->label()]);

    $form['candidate_nodes'] = [
      '#type' => 'tableselect',
      '#caption' => $caption,
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('There are not candidates nodes with revisions to be deleted.'),
      '#sticky' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete revisions'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get selected content types.
    $nids = array_filter($form_state->getValue('candidate_nodes'));

    if (count($nids)) {
      // Getting the candidate revisions to delete.
      $candidate_revisions = $this->nodeRevisionDelete->getCandidatesRevisionsByNids($nids);
      // Add the batch.
      batch_set($this->nodeRevisionDelete->getRevisionDeletionBatch($candidate_revisions, FALSE));
    }
  }

}
