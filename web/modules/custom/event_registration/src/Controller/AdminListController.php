<?php

namespace Drupal\event_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\event_registration\EventManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for admin listing page.
 */
class AdminListController extends ControllerBase {

  /**
   * The event manager service.
   *
   * @var \Drupal\event_registration\EventManager
   */
  protected $eventManager;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs an AdminListController object.
   *
   * @param \Drupal\event_registration\EventManager $event_manager
   *   The event manager service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(EventManager $event_manager, FormBuilderInterface $form_builder) {
    $this->eventManager = $event_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_registration.event_manager'),
      $container->get('form_builder')
    );
  }

  /**
   * Displays the registrations listing page.
   *
   * @return array
   *   Render array.
   */
  public function listRegistrations() {
    $build = [];

    // Build the filter form.
    $build['filter_form'] = $this->formBuilder->getForm('Drupal\event_registration\Form\AdminFilterForm');

    return $build;
  }

  /**
   * Exports registrations as CSV.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   CSV file response.
   */
  public function exportCsv(Request $request) {
    $event_date = $request->query->get('event_date');
    $event_id = $request->query->get('event_id');

    $registrations = $this->eventManager->getRegistrations($event_date, $event_id);

    // Create CSV content.
    $csv_data = [];
    $csv_data[] = [
      'ID',
      'Full Name',
      'Email',
      'College Name',
      'Department',
      'Category',
      'Event Date',
      'Event Name',
      'Submission Date',
    ];

    foreach ($registrations as $registration) {
      $event = $this->eventManager->getEventById($registration->event_id);
      $csv_data[] = [
        $registration->id,
        $registration->full_name,
        $registration->email,
        $registration->college_name,
        $registration->department,
        $registration->category,
        $registration->event_date,
        $event ? $event->event_name : '',
        date('Y-m-d H:i:s', $registration->created),
      ];
    }

    // Convert to CSV string.
    $output = fopen('php://temp', 'r+');
    foreach ($csv_data as $row) {
      fputcsv($output, $row);
    }
    rewind($output);
    $csv_string = stream_get_contents($output);
    fclose($output);

    // Create response.
    $response = new Response($csv_string);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="event_registrations.csv"');

    return $response;
  }

}