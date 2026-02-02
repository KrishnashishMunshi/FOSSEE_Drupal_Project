<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for event registration settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['event_registration.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_registration_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('event_registration.settings');

    $form['admin_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Admin Notification Email Address'),
      '#description' => $this->t('Email address to receive registration notifications.'),
      '#default_value' => $config->get('admin_email'),
    ];

    $form['admin_notifications_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Admin Notifications'),
      '#description' => $this->t('Check this box to send registration notifications to the admin email.'),
      '#default_value' => $config->get('admin_notifications_enabled'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $admin_notifications_enabled = $form_state->getValue('admin_notifications_enabled');
    $admin_email = $form_state->getValue('admin_email');

    if ($admin_notifications_enabled && empty($admin_email)) {
      $form_state->setErrorByName('admin_email', $this->t('Admin email is required when notifications are enabled.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('event_registration.settings')
      ->set('admin_email', $form_state->getValue('admin_email'))
      ->set('admin_notifications_enabled', $form_state->getValue('admin_notifications_enabled'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}