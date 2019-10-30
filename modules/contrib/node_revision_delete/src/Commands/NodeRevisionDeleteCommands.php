<?php

namespace Drupal\node_revision_delete\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node_revision_delete\NodeRevisionDelete;
use Drupal\node_revision_delete\NodeRevisionDeleteCliService;
use Drush\Commands\DrushCommands;
use Consolidation\AnnotatedCommand\CommandData;

/**
 * Class NodeRevisionDeleteCommands.
 *
 * @package Drupal\node_revision_delete\Commands
 */
class NodeRevisionDeleteCommands extends DrushCommands {

  /**
   * The NodeRevisionCliService.
   *
   * @var \Drupal\node_revision_delete\NodeRevisionDeleteCliService
   */
  protected $cliService;

  /**
   * The ConfigManager service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The NodeRevisionDelete service.
   *
   * @var NodeRevisionDelete
   */
  protected $nodeRevisionDelete;

  /**
   * NodeRevisionDeleteCommands constructor.
   *
   * @param \Drupal\node_revision_delete\NodeRevisionDeleteCliService $cliService
   *   The NodeRevisionDeleteCliService.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The ConfigManager service.
   * @param NodeRevisionDelete $nodeRevisionDelete
   *   The NodeRevisionDelete service.
   */
  public function __construct(
    NodeRevisionDeleteCliService $cliService,
    ConfigFactoryInterface $configFactory,
    NodeRevisionDelete $nodeRevisionDelete
  ) {
    $this->cliService = $cliService;
    $this->configFactory = $configFactory;
    $this->nodeRevisionDelete = $nodeRevisionDelete;
  }

  /**
   * Configures how many revisions delete per cron run.
   *
   * @param int $quantity
   *   Revisions quantity to delete per cron run.
   *
   * @usage nrd-delete-cron-run
   *   Show how many revisions the module will delete per cron run.
   * @usage nrd-delete-cron-run 50
   *   Configure the module to delete 50 revisions per cron run.
   *
   * @command nrd:delete-cron-run
   * @aliases nrd-dcr, nrd-delete-cron-run
   */
  public function deleteCronRun($quantity = NULL) {
    // Getting an editable config because we will get and set a value.
    $config = $this->configFactory->getEditable('node_revision_delete.settings');
    // If no argument found?
    if (!is_null($quantity)) {
      // Saving the values in the config.
      $config->set('node_revision_delete_cron', $quantity);
      $config->save();

      $message = dt('<info>The module was configured to delete @revisions revisions per cron run.</info>', ['@revisions' => $quantity]);
      $this->io()->writeln($message);
    }
    else {
      // Getting the values from the config.
      $revisions = $config->get('node_revision_delete_cron');
      $message = dt('<info>The revisions quantity to delete per cron run is: @revisions.</info>', ['@revisions' => $revisions]);
      $this->io()->writeln($message);
    }
  }

  /**
   * Get the last time that the node revision delete was made.
   *
   * @usage nrd-last-execute
   *   Show the last time that the node revision delete was made.
   *
   * @command nrd:last-execute
   * @aliases nrd-le, nrd-last-execute
   */
  public function lastExecute() {
    $this->cliService->ioGetLastExecute($this->io());
  }

  /**
   * Configures the frequency with which to delete revisions while cron run.
   *
   * @param int $time
   *   The time value (never, every_hour, every_time, everyday, every_week,
   *   every_10_days, every_15_days, every_month, every_3_months,
   *   every_6_months, every_year or every_2_years)
   *
   * @usage nrd-set-time
   *   Show a list to select the frequency with which to delete revisions while
   *   cron is running.
   * @usage nrd-set-time every_time
   *   Configure the module to delete revisions every time the cron runs.
   *
   * @command nrd:set-time
   * @aliases nrd-st, nrd-set-time
   */
  public function setTime($time = '') {
    $this->cliService->ioSetTime($time, $this->io());
  }

  /**
   * Shows the frequency with which to delete revisions while cron is running.
   *
   * @usage nrd-get-time
   *   Shows the actual frequency with which to delete revisions while cron is
   *   running.
   *
   * @command nrd:get-time
   * @aliases nrd-gt, nrd-get-time
   */
  public function getTime() {
    $this->cliService->ioGetTime($this->io());
  }

  /**
   * Configures the time options for the inactivity time.
   *
   * Configures the time options for the inactivity time that the revision must
   * have to be deleted.
   *
   * @param int $max_number
   *   The maximum number for inactivity time configuration.
   * @param int $time
   *   The time value for inactivity time configuration (days, weeks or months).
   *
   * @usage nrd-when-to-delete-time
   *   Shows the time configuration for the inactivity time.
   * @usage nrd-when-to-delete-time 30 days
   *   Set the maximum inactivity time to 30 days.
   * @usage nrd-when-to-delete-time 6 weeks
   *   Set the maximum inactivity time to 6 weeks.
   *
   * @command nrd:when-to-delete-time
   * @aliases nrd-wtdt, nrd-when-to-delete-time
   */
  public function whenToDeleteTime($max_number = NULL, $time = NULL) {
    // Getting an editable config because we will get and set a value.
    $config = $this->configFactory->getEditable('node_revision_delete.settings');
    // Getting or setting values?
    if (isset($max_number)) {
      // Saving the values in the config.
      $node_revision_delete_when_to_delete_time['max_number'] = $max_number;
      $node_revision_delete_when_to_delete_time['time'] = $time;
      $config->set('node_revision_delete_when_to_delete_time', $node_revision_delete_when_to_delete_time);
      $config->save();

      // We need to update the max_number in the existing content type
      // configuration if the new value is lower than the actual.
      $this->nodeRevisionDelete->updateTimeMaxNumberConfig('when_to_delete', $max_number);

      $time = $this->nodeRevisionDelete->getTimeNumberString($max_number, $time);
      $message = dt('<info>The maximum inactivity time was set to @max_number @time.</info>', ['@max_number' => $max_number, '@time' => $time]);
      $this->io()->writeln($message);
    }
    else {
      // Getting the values from the config.
      $node_revision_delete_when_to_delete_time = $config->get('node_revision_delete_when_to_delete_time');
      $max_number = $node_revision_delete_when_to_delete_time['max_number'];
      $time = $node_revision_delete_when_to_delete_time['time'];

      $time = $this->nodeRevisionDelete->getTimeNumberString($max_number, $time);
      $message = dt('<info>The maximum inactivity time is: @max_number @time.</info>', ['@max_number' => $max_number, '@time' => $time]);
      $this->io()->writeln($message);
    }
  }

  /**
   * Configures time options to know the minimum age.
   *
   * Configures time options to know the minimum age. that the revision must
   * have to be delete.
   *
   * @param int $max_number
   *   The maximum number for minimum age configuration.
   * @param int $time
   *   The time value for minimum age configuration (days, weeks or months).
   *
   * @usage nrd-minimum-age-to-delete-time
   *   Shows the time configuration for the minimum age of revisions.
   * @usage nrd-minimum-age-to-delete-time 30 days
   *   Set the maximum time for the minimum age to 30 days.
   * @usage nrd-minimum-age-to-delete-time 6 weeks
   *   Set the maximum time for the minimum age to 6 weeks.
   *
   * @command nrd:minimum-age-to-delete-time
   * @aliases nrd-matdt, nrd-minimum-age-to-delete-time
   */
  public function minimumAgeToDeleteTime($max_number = NULL, $time = NULL) {
    // Getting an editable config because we will get and set a value.
    $config = $this->configFactory->getEditable('node_revision_delete.settings');
    // Getting or setting values?
    if (isset($max_number)) {
      // Saving the values in the config.
      $node_revision_delete_minimum_age_to_delete_time['max_number'] = $max_number;
      $node_revision_delete_minimum_age_to_delete_time['time'] = $time;
      $config->set('node_revision_delete_minimum_age_to_delete_time', $node_revision_delete_minimum_age_to_delete_time);
      $config->save();

      // We need to update the max_number in the existing content type
      // configuration if the new value is lower than the actual.
      $this->nodeRevisionDelete->updateTimeMaxNumberConfig('minimum_age_to_delete', $max_number);

      // Is singular or plural?
      $time = $this->nodeRevisionDelete->getTimeNumberString($max_number, $time);
      $message = dt('<info>The maximum time for the minimum age was set to @max_number @time.</info>', ['@max_number' => $max_number, '@time' => $time]);
      $this->io()->writeln($message);
    }
    else {
      // Getting the values from the config.
      $node_revision_delete_minimum_age_to_delete_time = $config->get('node_revision_delete_minimum_age_to_delete_time');
      $max_number = $node_revision_delete_minimum_age_to_delete_time['max_number'];
      $time = $node_revision_delete_minimum_age_to_delete_time['time'];

      // Is singular or plural?
      $time = \Drupal::service('node_revision_delete')->getTimeNumberString($max_number, $time);
      $message = dt('<info>The maximum time for the minimum age is: @max_number @time.</info>', ['@max_number' => $max_number, '@time' => $time]);
      $this->io()->writeln($message);
    }
  }

  /**
   * Delete all revisions prior to a revision.
   *
   * @param int $nid
   *   The id of the node which revisions will be deleted.
   * @param int $vid
   *   The revision id, all prior revisions to this revision will be deleted.
   *
   * @usage nrd-delete-prior-revisions 1 3
   *   Delete all revisions prior to revision id 3 of node id 1.
   * @command nrd:delete-prior-revisions
   * @aliases nrd-dpr,nrd-delete-prior-revisions
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function deletePriorRevisions($nid = 0, $vid = 0) {
    $this->cliService->ioDeletePriorRevisions($nid, $vid, $this->io());
  }

  /**
   * Validate inputs before executing the drush command.
   *
   * @param Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @return bool
   *   Returns TRUE if the validations has passed FALSE otherwise.
   *
   * @hook validate nrd-delete-prior-revisions
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function deletePriorRevisionsValidate(CommandData $commandData) {
    $input = $commandData->input();
    $nid = $input->getArgument('nid');
    $vid = $input->getArgument('vid');

    // Nid argument must be numeric.
    if (!is_numeric($nid)) {
      $this->io()->error(t('Argument nid must be numeric.'));
      return FALSE;
    }

    // Vid argument must be numeric.
    if (!is_numeric($vid)) {
      $this->io()->error(t('Argument vid must be numeric.'));
      return FALSE;
    }

    // Check if argument nid is a valid node id.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    if (is_null($node)) {
      $this->io()->error(t("@nid is not a valid node id.", ['@nid' => $nid]));
      return FALSE;
    }
  }

}
