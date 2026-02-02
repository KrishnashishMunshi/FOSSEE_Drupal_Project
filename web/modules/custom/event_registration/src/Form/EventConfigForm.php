<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\event_registration\EventManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for configuring events.
 */
class EventConfigForm extends FormBase {

  /**
   * The event manager service.
   *
   * @var \Drupal\event_registration\EventManager
   */
  protected $eventManager;

  /**
   * Constructs an EventConfigForm object.
   *
   * @param \Drupal\event_registration\EventManager $event_manager
   *   The event manager service.
   */
  public function __construct(EventManager $event_manager) {
    $this->eventManager = $event_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_registration.event_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_registration_event_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['event_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event Name'),
      '#required' => TRUE,
    ];

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category of the Event'),
      '#options' => [
        'Online Workshop' => $this->t('Online Workshop'),
        'Hackathon' => $this->t('Hackathon'),
        'Conference' => $this->t('Conference'),
        'One-day Workshop' => $this->t('One-day Workshop'),
      ],
      '#required' => TRUE,
    ];

    $form['event_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Event Date'),
      '#required' => TRUE,
    ];

    $form['registration_start'] = [
      '#type' => 'date',
      '#title' => $this->t('Event Registration Start Date'),
      '#required' => TRUE,
    ];

    $form['registration_end'] = [
      '#type' => 'date',
      '#title' => $this->t('Event Registration End Date'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Event'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $event_name = $form_state->getValue('event_name');
    if (preg_match('/[^a-zA-Z0-9\s]/', $event_name)) {
      $form_state->setErrorByName('event_name', $this->t('Special characters are not allowed in Event Name.'));
    }

    $registration_start = $form_state->getValue('registration_start');
    $registration_end = $form_state->getValue('registration_end');
    $event_date = $form_state->getValue('event_date');

    if ($registration_start > $registration_end) {
      $form_state->setErrorByName('registration_end', $this->t('Registration end date must be after start date.'));
    }

    if ($event_date < $registration_start) {
      $form_state->setErrorByName('event_date', $this->t('Event date must be on or after registration start date.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->eventManager->saveEventConfig([
      'event_name' => $form_state->getValue('event_name'),
      'category' => $form_state->getValue('category'),
      'event_date' => $form_state->getValue('event_date'),
      'registration_start' => $form_state->getValue('registration_start'),
      'registration_end' => $form_state->getValue('registration_end'),
    ]);

    $this->messenger()->addMessage($this->t('Event has been saved successfully.'));
  }

}