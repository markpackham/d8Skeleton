<?php

namespace Drupal\node_revision_delete;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Drupal\node_revision_delete\Utility\Time;

/**
 * Class NodeRevisionDeleteCliService.
 */
class NodeRevisionDeleteCliService {

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The NodeRevisionDelete service.
   *
   * @var \Drupal\node_revision_delete\NodeRevisionDeleteInterface
   */
  protected $nodeRevisionDelete;

  /**
   * The DateFormatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The ConfigFactory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * NodeRevisionDeleteCliService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity_type_manager.
   * @param NodeRevisionDeleteInterface $node_revision_delete
   *   The node_revision_delete.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date_formatter.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config_factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    NodeRevisionDeleteInterface $node_revision_delete,
    DateFormatterInterface $date_formatter,
    ConfigFactoryInterface $config_factory,
    StateInterface $state) {
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeRevisionDelete = $node_revision_delete;
    $this->dateFormatter = $date_formatter;
    $this->configFactory = $config_factory;
    $this->state = $state;
  }

  /**
   * Method to delete revisions prior to a revision via Drush CLI.
   *
   * @param int $nid
   *   The node id.
   * @param int $vid
   *   The revision id.
   * @param Symfony\Component\Console\Style\StyleInterface $io
   *   The output style helper.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function ioDeletePriorRevisions($nid, $vid, StyleInterface $io) {
    // Get list of prior revisions.
    $previousRevisions = $this->nodeRevisionDelete->getPreviousRevisions($nid, $vid);

    if (count($previousRevisions) === 0) {
      $io->error(t("No prior revision(s) found to delete."));
      return;
    }

    if ($io->confirm(t("Confirm deleting @count revision(s)?", ['@count' => count($previousRevisions)]))) {
      // Check if current revision should be deleted, too.
      if ($io->confirm(t("Additionally, do you want to delete the revision @vid? @count revision(s) will be deleted.", ['@vid' => $vid, '@count' => count($previousRevisions) + 1]))) {
        $this->entityTypeManager->getStorage('node')->deleteRevision($vid);
      }

      foreach ($previousRevisions as $revision) {
        $this->entityTypeManager->getStorage('node')->deleteRevision($revision->getRevisionId());
      }
    }
  }

  /**
   * Get config 'node_revision_delete_time'.
   *
   * @param Symfony\Component\Console\Style\StyleInterface $io
   *   The style.
   */
  public function ioGetTime(StyleInterface $io) {
    // Getting the config.
    $config = $this->configFactory->get('node_revision_delete.settings');
    // Getting the values from the config.
    $time = $config->get('node_revision_delete_time');
    $time = $this->nodeRevisionDelete->getTimeValues($time);

    $message = dt('The frequency with which to delete revisions while cron is running is: @time.', ['@time' => $time]);
    $io->text($message);
  }

  /**
   * Get last time of node_revision_delete execution.
   *
   * @param Symfony\Component\Console\Style\StyleInterface $io
   *   The style.
   */
  public function ioGetLastExecute(StyleInterface $io) {
    // Getting the value.
    $last_execute = $this->state->get('node_revision_delete.last_execute', 0);
    if (!empty($last_execute)) {
      $last_execute = $this->dateFormatter->format($last_execute);
      $message = t('The last time when node revision delete was made was: @last_execute.', ['@last_execute' => $last_execute]);
    }
    else {
      $message = t('The removal of revisions through the module node revision delete has never been executed on this site.');
    }
    $io->text($message);
  }

  /**
   * Set config 'node_revision_delete_time' to the value from input.
   *
   * @param string $time
   *   The time option.
   * @param Symfony\Component\Console\Style\StyleInterface $io
   *   The style.
   */
  public function ioSetTime($time, StyleInterface $io) {
    // Getting an editable config because we will get and set a value.
    $config = $this->configFactory->getEditable('node_revision_delete.settings');

    // Check for correct argument.
    $options = Time::convertWordToTime();
    $options_keys = array_keys($options);

    if (!in_array($time, $options_keys)) {
      if (!empty($time)) {
        $io->warning(t('"@time_value" is not a valid time argument.', ['@time_value' => $time]));
      }
      $choice = $io->choice(t('Choose the frequency with which to delete revisions while cron is running:'), $this->nodeRevisionDelete->getTimeValues());
      $time = $options[$options_keys[$choice]];
    }
    else {
      $time = $options[$time];
    }
    // Saving the values in the config.
    $config->set('node_revision_delete_time', $time);
    $config->save();
    // Getting the values from the config.
    $time_value = $this->nodeRevisionDelete->getTimeValues($time);
    $message = dt('The frequency with which to delete revisions while cron is running was set to: @time.', ['@time' => $time_value]);
    $io->success($message);
  }

}
