<?php

namespace Drupal\event_registration;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service for managing event registration operations.
 */
class EventManager {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an EventManager object.
   *
   * @param \Drupal\Core\Database\Connection $database
   
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->configFactory = $config_factory;
  }

  /**
   * Gets all event categories.
   *
   * @return array
   *   Array of unique categories.
   */
  public function getCategories() {
    $query = $this->database->select('event_config', 'ec')
      ->fields('ec', ['category'])
      ->distinct();
    return $query->execute()->fetchCol();
  }

  /**
   * Gets event dates for a specific category.
   *
   * @param string $category
   *   The event category.
   *
   * @return array
   *   Array of event dates.
   */
  public function getEventDatesByCategory($category) {
    $query = $this->database->select('event_config', 'ec')
      ->fields('ec', ['event_date'])
      ->condition('category', $category)
      ->distinct();
    return $query->execute()->fetchCol();
  }

  /**
   * Gets events by category and date.
   *
   * @param string $category
   *   The event category.
   * @param string $event_date
   *   The event date.
   *
   * @return array
   *   Array of events with id and name.
   */
  public function getEventsByCategoryAndDate($category, $event_date) {
    $query = $this->database->select('event_config', 'ec')
      ->fields('ec', ['id', 'event_name'])
      ->condition('category', $category)
      ->condition('event_date', $event_date);
    return $query->execute()->fetchAllKeyed();
  }

  /**
   * Checks if registration is currently open for any event.
   *
   * @return bool
   *   TRUE if registration is open.
   */
  public function isRegistrationOpen() {
    $current_date = date('Y-m-d');
    $query = $this->database->select('event_config', 'ec')
      ->condition('registration_start', $current_date, '<=')
      ->condition('registration_end', $current_date, '>=')
      ->countQuery();
    return $query->execute()->fetchField() > 0;
  }

  /**
   * Checks if a duplicate registration exists.
   *
   * @param string $email
   *   The email address.
   * @param string $event_date
   *   The event date.
   *
   * @return bool
   *   TRUE if duplicate exists.
   */
  public function isDuplicateRegistration($email, $event_date) {
    $query = $this->database->select('event_registration', 'er')
      ->condition('email', $email)
      ->condition('event_date', $event_date)
      ->countQuery();
    return $query->execute()->fetchField() > 0;
  }

  /**
   * Saves a registration.
   *
   * @param array $data
   *   Registration data.
   *
   * @return int
   *   The registration ID.
   */
  public function saveRegistration(array $data) {
    return $this->database->insert('event_registration')
      ->fields($data)
      ->execute();
  }

  /**
   * Saves an event configuration.
   *
   * @param array $data
   *   Event configuration data.
   *
   * @return int
   *   The event ID.
   */
  public function saveEventConfig(array $data) {
    return $this->database->insert('event_config')
      ->fields($data)
      ->execute();
  }

  /**
   * Gets event details by ID.
   *
   * @param int $event_id
   *   The event ID.
   *
   * @return object|false
   *   Event object or FALSE.
   */
  public function getEventById($event_id) {
    return $this->database->select('event_config', 'ec')
      ->fields('ec')
      ->condition('id', $event_id)
      ->execute()
      ->fetchObject();
  }

  /**
   * Gets registrations filtered by event date and name.
   *
   * @param string|null $event_date
   *   The event date.
   * @param int|null $event_id
   *   The event ID.
   *
   * @return array
   *   Array of registration records.
   */
  public function getRegistrations($event_date = NULL, $event_id = NULL) {
    $query = $this->database->select('event_registration', 'er')
      ->fields('er');

    if ($event_date) {
      $query->condition('event_date', $event_date);
    }

    if ($event_id) {
      $query->condition('event_id', $event_id);
    }

    return $query->execute()->fetchAll();
  }

  /**
   * Gets count of registrations.
   *
   * @param string|null $event_date
   *   The event date.
   * @param int|null $event_id
   *   The event ID.
   *
   * @return int
   *   Count of registrations.
   */
  public function getRegistrationCount($event_date = NULL, $event_id = NULL) {
    $query = $this->database->select('event_registration', 'er');

    if ($event_date) {
      $query->condition('event_date', $event_date);
    }

    if ($event_id) {
      $query->condition('event_id', $event_id);
    }

    return $query->countQuery()->execute()->fetchField();
  }
/**
   * Gets all unique event dates from event_config.
   *
   * @return array
   *   Array of event dates.
   */
  public function getAllEventDates() {
    $query = $this->database->select('event_config', 'ec')
      ->fields('ec', ['event_date'])
      ->distinct()
      ->orderBy('event_date', 'ASC');
    return $query->execute()->fetchCol();
  }

  /**
   * Gets events by date.
   *
   * @param string $event_date
   *   The event date.
   *
   * @return array
   *   Array of events with id and name.
   */
  public function getEventsByDate($event_date) {
    $query = $this->database->select('event_config', 'ec')
      ->fields('ec', ['id', 'event_name'])
      ->condition('event_date', $event_date);
    return $query->execute()->fetchAllKeyed();
  } 
}