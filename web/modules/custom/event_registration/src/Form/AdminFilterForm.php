<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\event_registration\EventManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin filter form for event registrations.
 */
class AdminFilterForm extends FormBase {

  /**
   * The event manager service.
   *
   * @var \Drupal\event_registration\EventManager
   */
  protected $eventManager;

  /**
   * Constructs an AdminFilterForm object.
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
    return 'event_registration_admin_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="admin-filter-wrapper">';
    $form['#suffix'] = '</div>';

    // Get all event dates.
    $event_dates = $this->eventManager->getAllEventDates();
    $date_options = ['' => $this->t('- Select Event Date -')] + array_combine($event_dates, $event_dates);

    $form['event_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => $date_options,
      '#ajax' => [
        'callback' => '::updateEventNameCallback',
        'wrapper' => 'event-name-wrapper',
        'event' => 'change',
      ],
    ];

    $form['event_name_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-name-wrapper'],
    ];

    // Get event names for selected date.
    $selected_date = $form_state->getValue('event_date');
    $event_name_options = ['' => $this->t('- Select Event Name -')];

    if ($selected_date) {
      $events = $this->eventManager->getEventsByDate($selected_date);
      $event_name_options += $events;
    }

    $form['event_name_wrapper']['event_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Name'),
      '#options' => $event_name_options,
      '#ajax' => [
        'callback' => '::updateRegistrationsCallback',
        'wrapper' => 'registrations-wrapper',
        'event' => 'change',
      ],
    ];

    $form['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#ajax' => [
        'callback' => '::updateRegistrationsCallback',
        'wrapper' => 'registrations-wrapper',
      ],
    ];

    // Registrations display area.
    $form['registrations_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'registrations-wrapper'],
    ];

    // Get filtered registrations.
    $selected_event_id = $form_state->getValue('event_name');
    
    if ($selected_date && $selected_event_id) {
      $count = $this->eventManager->getRegistrationCount($selected_date, $selected_event_id);
      $registrations = $this->eventManager->getRegistrations($selected_date, $selected_event_id);

      $form['registrations_wrapper']['count'] = [
        '#markup' => '<h3>' . $this->t('Total Participants: @count', ['@count' => $count]) . '</h3>',
      ];

      // Export button.
      $form['registrations_wrapper']['export'] = [
        '#type' => 'link',
        '#title' => $this->t('Export as CSV'),
        '#url' => \Drupal\Core\Url::fromRoute('event_registration.export_csv', [], [
          'query' => [
            'event_date' => $selected_date,
            'event_id' => $selected_event_id,
          ],
        ]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];

      // Build table.
      $rows = [];
      foreach ($registrations as $registration) {
        $rows[] = [
          $registration->full_name,
          $registration->email,
          $registration->event_date,
          $registration->college_name,
          $registration->department,
          date('Y-m-d H:i:s', $registration->created),
        ];
      }

      $form['registrations_wrapper']['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Name'),
          $this->t('Email'),
          $this->t('Event Date'),
          $this->t('College Name'),
          $this->t('Department'),
          $this->t('Submission Date'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No registrations found.'),
      ];
    }
    elseif ($selected_date || $selected_event_id) {
      $form['registrations_wrapper']['message'] = [
        '#markup' => '<p>' . $this->t('Please select both event date and event name to view registrations.') . '</p>',
      ];
    }

    return $form;
  }

  /**
   * AJAX callback to update event name dropdown.
   */
  public function updateEventNameCallback(array &$form, FormStateInterface $form_state) {
    return $form['event_name_wrapper'];
  }

  /**
   * AJAX callback to update registrations display.
   */
  public function updateRegistrationsCallback(array &$form, FormStateInterface $form_state) {
    return $form['registrations_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Form submission is handled via AJAX.
    $form_state->setRebuild();
  }

}