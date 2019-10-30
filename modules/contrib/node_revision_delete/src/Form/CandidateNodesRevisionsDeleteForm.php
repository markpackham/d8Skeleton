<?php

namespace Drupal\node_revision_delete\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node_revision_delete\NodeRevisionDeleteInterface;

/**
 * Provides a candidate node revision deletion confirmation form.
 */
class CandidateNodesRevisionsDeleteForm extends ConfirmFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The node object.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * The node revision delete interface.
   *
   * @var \Drupal\node_revision_delete\NodeRevisionDeleteInterface
   */
  protected $nodeRevisionDelete;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\node_revision_delete\NodeRevisionDeleteInterface $node_revision_delete
   *   The node revision delete.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, NodeRevisionDeleteInterface $node_revision_delete) {
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeRevisionDelete = $node_revision_delete;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('node_revision_delete')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_type_revisions_delete_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $content_type = NULL, $nid = NULL) {
    $this->node = $this->entityTypeManager->getStorage('node')->load($nid);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the candidates revisions for the node "%node_title" ?', ['%node_title' => $this->node->getTitle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $description = '<p>' . $this->t('This action will delete the candidate revisions for the "@node_title" content type.', ['@node_title' => $this->node->getTitle()]) . '</p>';
    $description .= '<p>' . parent::getDescription() . '</p>';
    return $description;
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('node_revision_delete.candidate_nodes', ['content_type' => $this->node->getType()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Getting the content type candidate revisions.
    $candidate_revisions = $this->nodeRevisionDelete->getCandidatesRevisionsByNids([$this->node->id()]);

    // Add the batch.
    batch_set($this->nodeRevisionDelete->getRevisionDeletionBatch($candidate_revisions, FALSE));

    // Redirecting.
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
