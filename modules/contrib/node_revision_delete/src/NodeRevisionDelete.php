<?php

namespace Drupal\node_revision_delete;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Class NodeRevisionDelete.
 *
 * @package Drupal\node_revision_delete
 */
class NodeRevisionDelete implements NodeRevisionDeleteInterface {

  use StringTranslationTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The configuration file name.
   *
   * @var string
   */
  protected $configurationFileName;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TranslationInterface $string_translation,
    Connection $connection,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager
  ) {
    $this->configurationFileName = 'node_revision_delete.settings';
    $this->configFactory = $config_factory;
    $this->stringTranslation = $string_translation;
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function updateTimeMaxNumberConfig($config_name, $max_number) {
    // Looking for all the content types.
    $content_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    // Checking the when_to_delete value for all the configured content types.
    foreach ($content_types as $content_type) {
      $changed = TRUE;
      // Getting the config variables.
      $config = $this->configFactory->getEditable('node.type.' . $content_type->id());
      $third_party_settings = $config->get('third_party_settings');
      // If the new defined max_number is smaller than the defined
      // when_to_delete value in the config, we need to change the stored config
      // value.
      if (isset($third_party_settings['node_revision_delete'][$config_name]) && $max_number < $third_party_settings['node_revision_delete'][$config_name]) {
        $third_party_settings['node_revision_delete'][$config_name] = $max_number;
        $changed = TRUE;
      }
      // Saving only if we have changes.
      if ($changed) {
        // Saving the values in the config.
        $config->set('third_party_settings', $third_party_settings)->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeString($config_name, $number) {
    // Geting the config.
    $config_name_time = $this->configFactory->get($this->configurationFileName)->get('node_revision_delete_' . $config_name . '_time');
    // Is singular or plural?
    $time = $this->getTimeNumberString($number, $config_name_time['time']);
    // Return the time string for the $config_name parameter.
    switch ($config_name) {
      case 'minimum_age_to_delete':
        return $number . ' ' . $time;

      case 'when_to_delete':
        return $this->t('After @number @time of inactivity', [
          '@number' => $number,
          '@time' => $time,
        ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveContentTypeConfig($content_type, $minimum_revisions_to_keep, $minimum_age_to_delete, $when_to_delete) {
    // Getting the config file.
    $config = $this->configFactory->getEditable('node.type.' . $content_type);
    // Getting the variables with the content types configuration.
    $third_party_settings = $config->get('third_party_settings');
    // Adding the info into the array.
    $third_party_settings['node_revision_delete'] = [
      'minimum_revisions_to_keep' => $minimum_revisions_to_keep,
      'minimum_age_to_delete' => $minimum_age_to_delete,
      'when_to_delete' => $when_to_delete,
    ];
    // Saving the values in the config.
    $config->set('third_party_settings', $third_party_settings)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteContentTypeConfig($content_type) {
    // Getting the config file.
    $config = $this->configFactory->getEditable('node.type.' . $content_type);
    // Getting the variables with the content types configuration.
    $third_party_settings = $config->get('third_party_settings');
    // Checking if the config exists.
    if (isset($third_party_settings['node_revision_delete'])) {
      // Deleting the value from the array.
      unset($third_party_settings['node_revision_delete']);
      // Saving the values in the config.
      $config->set('third_party_settings', $third_party_settings)->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeValues($index = NULL) {

    $options_node_revision_delete_time = [
      '-1'       => $this->t('Never'),
      '0'        => $this->t('Every time cron runs'),
      '3600'     => $this->t('Every hour'),
      '86400'    => $this->t('Everyday'),
      '604800'   => $this->t('Every week'),
      '864000'   => $this->t('Every 10 days'),
      '1296000'  => $this->t('Every 15 days'),
      '2592000'  => $this->t('Every month'),
      '7776000'  => $this->t('Every 3 months'),
      '15552000' => $this->t('Every 6 months'),
      '31536000' => $this->t('Every year'),
      '63072000' => $this->t('Every 2 years'),
    ];

    if (isset($index) && isset($options_node_revision_delete_time[$index])) {
      return $options_node_revision_delete_time[$index];
    }
    else {
      return $options_node_revision_delete_time;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeNumberString($number, $time) {
    // Time options.
    $time_options = [
      'days' => [
        'singular' => $this->t('day'),
        'plural' => $this->t('days'),
      ],
      'weeks' => [
        'singular' => $this->t('week'),
        'plural' => $this->t('weeks'),
      ],
      'months' => [
        'singular' => $this->t('month'),
        'plural' => $this->t('months'),
      ],
    ];

    return $number == 1 ? $time_options[$time]['singular'] : $time_options[$time]['plural'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCandidatesNodes($content_type, $minimum_revisions_to_keep, $minimum_age_to_delete, $when_to_delete) {
    $query = $this->connection->select('node', 'n');
    $query->join('node_revision', 'r', 'r.nid = n.nid');
    $query->fields('n', ['nid']);
    $query->addExpression('COUNT(*)', 'total');
    $query->condition('n.type', $content_type);
    $query->groupBy('n.nid');
    $query->having('COUNT(*) > ' . $minimum_revisions_to_keep);

    // Allow other modules to alter candidates query.
    $query->addTag('node_revision_delete_candidates');
    $query->addTag('node_revision_delete_candidates_' . $content_type);

    return $query->execute()->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousRevisions($nid, $currently_deleted_revision_id) {
    // Getting the node storage.
    $node_storage = $this->entityTypeManager->getStorage('node');
    // Getting the node.
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    // Get current language code from URL.
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Get all revisions of the current node, in all languages.
    $revision_ids = $node_storage->revisionIds($node);

    $revisions_before = [];
    if (count($revision_ids) > 0) {
      // Loop through the list of revision ids, select the ones that have.
      // Same language as the current language AND are older than the current
      // deleted revision.
      foreach ($revision_ids as $vid) {
        // Compare revision using vid, the newer revision has bigger vid.
        if ($currently_deleted_revision_id - $vid > 0) {
          $revision = $node_storage->loadRevision($vid);
          // Only show revisions that are affected by the language
          // that is being displayed.
          if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
            array_push($revisions_before, $revision);
          }
        }
      }

      // Sort revisions by comparing revisionId, newest revision first.
      usort($revisions_before, function ($rev1, $rev2) {
        return $rev1->getRevisionId() < $rev2->getRevisionId();
      });
    }

    return $revisions_before;
  }

  /**
   * {@inheritdoc}
   */
  public function getCandidatesRevisions($content_type, $minimum_revisions_to_keep, $minimum_age_to_delete, $when_to_delete) {
    // Getting the candidate nodes.
    $candidate_nodes = $this->getCandidatesNodes($content_type, $minimum_revisions_to_keep, $minimum_age_to_delete, $when_to_delete);

    $candidate_revisions = [];

    foreach ($candidate_nodes as $candidate_node) {
      $query = $this->connection->select('node', 'n');
      $query->join('node_revision', 'r', 'r.nid = n.nid');
      $query->fields('r', ['vid']);
      $query->fields('n', ['nid']);
      $query->condition('n.type', $content_type);
      $query->condition('n.nid', $candidate_node);
      $query->where('n.vid <> r.vid');
      $query->groupBy('n.nid');
      $query->groupBy('r.vid');
      $query->orderBy('vid', 'ASC');
      // We need to reduce in 1 because we don't want to count the default vid.
      // We excluded the default revision in the where call.
      $query->range($minimum_revisions_to_keep - 1, PHP_INT_MAX);

      $candidate_revisions = array_merge($candidate_revisions, $query->execute()->fetchCol());
    }

    return $candidate_revisions;
  }

}
