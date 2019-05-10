<?php

namespace Drupal\node_revision_delete\Utility;

/**
 * Provides module internal helper methods.
 *
 * @ingroup utility
 */
class InternalFunctions {

  /**
   * Return the available values for time frequency.
   *
   * @param string $index
   *   The index to retrieve.
   *
   * @return string
   *   The index value (human readable value).
   */
  public static function getTimeValues($index = NULL) {
    $options_node_revision_delete_time = [
      'never' => t('Never'),
      'every_time' => t('Every time cron runs'),
      'every_hour' => t('Every Hour'),
      'everyday' => t('Everyday'),
      'every_week' => t('Every Week'),
      'every_10_days' => t('Every 10 Days'),
      'every_15_days' => t('Every 15 Days'),
      'every_month' => t('Every Month'),
      'every_3_months' => t('Every 3 Months'),
      'every_6_months' => t('Every 6 Months'),
      'every_year' => t('Every Year'),
      'every_2_years' => t('Every 2 Years'),
    ];

    if (isset($index) && isset($options_node_revision_delete_time[$index])) {
      return $options_node_revision_delete_time[$index];
    }
    else {
      return $options_node_revision_delete_time;
    }
  }

  /**
   * Return the time option in singular or plural.
   *
   * @param string $number
   *   The number.
   * @param string $time
   *   The time option (days, weeks or months).
   *
   * @return string
   *   The singular or plural value for the time.
   */
  public static function getTimeNumberString($number, $time) {
    // Time options.
    $time_options = [
      'days' => [
        'singular' => t('day'),
        'plural' => t('days'),
      ],
      'weeks' => [
        'singular' => t('week'),
        'plural' => t('weeks'),
      ],
      'months' => [
        'singular' => t('month'),
        'plural' => t('months'),
      ],
    ];

    return $number == 1 ? $time_options[$time]['singular'] : $time_options[$time]['plural'];
  }

}
