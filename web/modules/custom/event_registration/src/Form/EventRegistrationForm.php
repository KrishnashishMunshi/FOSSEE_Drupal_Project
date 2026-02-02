<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\event_registration\EventManager;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for event registration.
 */
class EventRegistrationForm extends FormBase {

  /**
   * The event manager service.
   *
   * @var \Drupal\event_registration\EventManager
   */
  protected $eventManager;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an EventRegistrationForm object.
   *
   * @param \Drupal\event_registration\EventManager $event_manager
   *   The event manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(EventManager $event_manager, MailManagerInterface $mail_manager, ConfigFactoryInterface $config_factory) {
    $this->eventManager = $event_manager;
    $this->mailManager = $mail_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_registration.event_manager'),
      $container->get('plugin.manager.mail'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Check if registration is open.
    if (!$this->eventManager->isRegistrationOpen()) {
      $form['message'] = [
        '#markup' => '<p>' . $this->t('Event registration is currently closed.') . '</p>',
      ];
      return $form;
    }

    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#required' => TRUE,
    ];

    $form['college_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('College Name'),
      '#required' => TRUE,
    ];

    $form['department'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Department'),
      '#required' => TRUE,
    ];

    // Get categories from database.
    $categories = $this->eventManager->getCategories();
    $category_options = ['' => $this->t('- Select -')] + array_combine($categories, $categories);

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category of the Event'),
      '#options' => $category_options,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateEventDateCallback',
        'wrapper' => 'event-date-wrapper',
        'event' => 'change',
      ],
    ];

    $form['event_date_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-date-wrapper'],
    ];

    // Get event dates based on selected category.
    $selected_category = $form_state->getValue('category');
    $event_date_options = ['' => $this->t('- Select -')];

    if ($selected_category) {
      $event_dates = $this->eventManager->getEventDatesByCategory($selected_category);
      $event_date_options += array_combine($event_dates, $event_dates);
    }

    $form['event_date_wrapper']['event_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => $event_date_options,
      '#required' => TRUE,
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

    // Get event names based on selected category and date.
    $selected_date = $form_state->getValue('event_date');
    $event_name_options = ['' => $this->t('- Select -')];

    if ($selected_category && $selected_date) {
      $events = $this->eventManager->getEventsByCategoryAndDate($selected_category, $selected_date);
      $event_name_options += $events;
    }

    $form['event_name_wrapper']['event_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Name'),
      '#options' => $event_name_options,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];

    return $form;
  }

  /**
   * AJAX callback to update event date dropdown.
   */
  public function updateEventDateCallback(array &$form, FormStateInterface $form_state) {
    return $form['event_date_wrapper'];
  }

  /**
   * AJAX callback to update event name dropdown.
   */
  public function updateEventNameCallback(array &$form, FormStateInterface $form_state) {
    return $form['event_name_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate full name.
    $full_name = $form_state->getValue('full_name');
    if (preg_match('/[^a-zA-Z\s]/', $full_name)) {
      $form_state->setErrorByName('full_name', $this->t('Special characters are not allowed in Full Name.'));
    }

    // Validate college name.
    $college_name = $form_state->getValue('college_name');
    if (preg_match('/[^a-zA-Z0-9\s]/', $college_name)) {
      $form_state->setErrorByName('college_name', $this->t('Special characters are not allowed in College Name.'));
    }

    // Validate department.
    $department = $form_state->getValue('department');
    if (preg_match('/[^a-zA-Z\s]/', $department)) {
      $form_state->setErrorByName('department', $this->t('Special characters are not allowed in Department.'));
    }

    // Check for duplicate registration.
    $email = $form_state->getValue('email');
    $event_date = $form_state->getValue('event_date');

    if ($this->eventManager->isDuplicateRegistration($email, $event_date)) {
      $form_state->setErrorByName('email', $this->t('You have already registered for an event on this date.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Save registration.
    $registration_id = $this->eventManager->saveRegistration([
      'full_name' => $values['full_name'],
      'email' => $values['email'],
      'college_name' => $values['college_name'],
      'department' => $values['department'],
      'category' => $values['category'],
      'event_date' => $values['event_date'],
      'event_id' => $values['event_name'],
      'created' => time(),
    ]);

    // Get event details.
    $event = $this->eventManager->getEventById($values['event_name']);

    // Send confirmation email to user.
    $this->sendConfirmationEmail($values['email'], $values['full_name'], $event);

    // Send notification to admin if enabled.
    $config = $this->configFactory->get('event_registration.settings');
    if ($config->get('admin_notifications_enabled')) {
      $admin_email = $config->get('admin_email');
      if ($admin_email) {
        $this->sendConfirmationEmail($admin_email, 'Admin', $event, $values);
      }
    }

    $this->messenger()->addMessage($this->t('Thank you for registering! A confirmation email has been sent to @email.', [
      '@email' => $values['email'],
    ]));

    $form_state->setRedirect('event_registration.register');
  }

  /**
   * Sends confirmation email.
   *
   * @param string $to
   *   Recipient email address.
   * @param string $name
   *   Recipient name.
   * @param object $event
   *   Event object.
   * @param array $registration_data
   *   Registration data (optional, for admin emails).
   */
  protected function sendConfirmationEmail($to, $name, $event, array $registration_data = NULL) {
    $params = [
      'name' => $name,
      'event_name' => $event->event_name,
      'event_date' => $event->event_date,
      'category' => $event->category,
    ];

    if ($registration_data) {
      $params['registration_data'] = $registration_data;
    }

    $this->mailManager->mail('event_registration', 'registration_confirmation', $to, 'en', $params);
  }

}