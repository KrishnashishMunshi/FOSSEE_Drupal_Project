# Event Registration Module - Drupal 10

**FOSSEE Summer Internship Submission**

<img width="3127" height="1154" alt="Image" src="https://github.com/user-attachments/assets/ea9add66-0611-4659-ac57-433ea0e34509" />

---

##  Project Specifications

### Architecture
- **Service-Oriented Design**: Business logic isolated in `EventManager` service, injected via Dependency Injection (no `\Drupal::service()` in forms/controllers)
- **Framework**: Drupal 10.x with Form API, AJAX API, Config API, Mail API, and Database API
- **Database**: Two custom tables with relational integrity:
  - `event_config`: Stores events (PK: `id`)
  - `event_registration`: Stores registrations (FK: `event_id` → `event_config.id`)
  - Composite index on `(email, event_date)` for duplicate prevention
- **AJAX State Management**: Cascading dropdowns update dynamically without page reloads

### Technology Stack
- PSR-4 autoloading
- Symfony Dependency Injection
- Drupal coding standards compliant
- Zero contributed modules

---

##  Core Features

### Dynamic User Interface
- **AJAX Cascading Dropdowns**: Select category → event dates populate → select date → event names populate
- **Real-time Filtering**: Admin interface filters registrations by date/event with live participant counts
- **Registration Window Logic**: Form automatically shows/hides based on current date vs. admin-configured start/end dates

### Validation & Security
- **Duplicate Prevention**: Email + Event Date uniqueness enforced at form validation and database index levels
- **Input Sanitization**: Regex validation blocks special characters in text fields
- **Email Format Validation**: Built-in browser and server-side checks

### Administrative Tools
- **Event Configuration**: Create unlimited events with registration windows
- **Settings Dashboard**: Configure admin notification email with enable/disable toggle (Config API)
- **Registration Reports**: View all registrations filtered by event, export as CSV
- **Permission System**: Two custom permissions for granular access control

### Email Automation
- **User Confirmations**: Personalized emails sent upon successful registration
- **Admin Notifications**: Optional alerts with full registration details
- **Template System**: Separate email content for users vs. admins via `hook_mail()`

---

##  Database Schema

### Table: `event_config`
| Column | Type | Description |
|--------|------|-------------|
| id | SERIAL (PK) | Auto-increment primary key |
| event_name | VARCHAR(255) | Event title |
| category | VARCHAR(100) | Online Workshop, Hackathon, Conference, One-day Workshop |
| event_date | VARCHAR(20) | Event occurrence date |
| registration_start | VARCHAR(20) | Registration window start |
| registration_end | VARCHAR(20) | Registration window end |

### Table: `event_registration`
| Column | Type | Description |
|--------|------|-------------|
| id | SERIAL (PK) | Auto-increment primary key |
| full_name | VARCHAR(255) | Registrant name |
| email | VARCHAR(255) | Registrant email |
| college_name | VARCHAR(255) | College/institution |
| department | VARCHAR(255) | Department |
| category | VARCHAR(100) | Selected event category |
| event_date | VARCHAR(20) | Selected event date |
| event_id | INT (FK) | Foreign key to event_config.id |
| created | INT | Unix timestamp |

**Indexes:**
- Composite: `(email, event_date)` for duplicate detection
- Single: `event_id` for JOIN optimization

---

##  Key Design Decisions

### Challenge: Complex AJAX Dependency Chain
**Problem**: Registration form needs 3-level cascading dropdowns (category → date → event name)  
**Solution**: Implemented AJAX callbacks with wrapper containers:
```php
'#ajax' => [
  'callback' => '::updateEventDateCallback',
  'wrapper' => 'event-date-wrapper',
  'event' => 'change',
]
```
Each selection triggers a database query via `EventManager` service, returning only relevant options.

### Challenge: Registration Window Management
**Problem**: Forms must be available only during admin-defined time periods  
**Solution**: Centralized `isRegistrationOpen()` method in `EventManager`:
```php
public function isRegistrationOpen() {
  $current_date = date('Y-m-d');
  $query = $this->database->select('event_config', 'ec')
    ->condition('registration_start', $current_date, '<=')
    ->condition('registration_end', $current_date, '>=')
    ->countQuery();
  return $query->execute()->fetchField() > 0;
}
```

### Challenge: No Hardcoded Configuration
**Requirement**: "Configuration must use Config API, should not contain hard-coded values"  
**Solution**: Admin email stored in `event_registration.settings` config object:
```php
$config = $this->configFactory->get('event_registration.settings');
$admin_email = $config->get('admin_email');
```

### Challenge: CSV Export Without Contrib Modules
**Solution**: Native PHP streaming with `fputcsv()`:
- Memory-efficient (handles 1000+ records)
- Automatic RFC 4180 escaping
- Direct HTTP response without file system writes

---

##  Installation Instructions

### Prerequisites
- Drupal 10.x installed
- DDEV or local PHP/MySQL environment
- Drush CLI tool

### Steps

**1. Copy Module Files**
```bash
cp -r event_registration /path/to/drupal/web/modules/custom/
```

**2. Enable Module**
```bash
ddev drush en event_registration -y
```
This automatically creates database tables via `hook_schema()`.

**3. Import Sample Data (Optional)**
```bash
ddev mysql < web/modules/custom/event_registration/event_registration.sql
```

**4. Clear Cache**
```bash
ddev drush cr
```

**5. Set Permissions**
Navigate to `/admin/people/permissions` and assign:
- **Administer Event Registration** → Administrator role
- **View Event Registrations** → Administrator role

Or via Drush:
```bash
ddev drush role:perm:add administrator 'administer event registration'
ddev drush role:perm:add administrator 'view event registrations'
```

**6. Access URLs**
- Event Configuration: `/admin/config/event-registration/event-config`
- Settings: `/admin/config/event-registration/settings`
- Public Registration: `/event/register`
- Admin Reports: `/admin/reports/event-registrations`

---

##  Module Structure

```
event_registration/
├── src/
│   ├── Controller/
│   │   └── AdminListController.php       # Admin listing + CSV export
│   ├── Form/
│   │   ├── AdminFilterForm.php           # AJAX admin filter
│   │   ├── EventConfigForm.php           # Event creation
│   │   ├── EventRegistrationForm.php     # Public registration
│   │   └── SettingsForm.php              # Admin email config
│   └── EventManager.php                  # Service layer (business logic)
├── event_registration.info.yml           # Module metadata
├── event_registration.install            # Database schema (hook_schema)
├── event_registration.module             # Hooks (hook_mail)
├── event_registration.permissions.yml    # Custom permissions
├── event_registration.routing.yml        # Route definitions
├── event_registration.services.yml       # DI container config
├── event_registration.sql                # Database dump
└── README.md
```

---

##  Quick Test Workflow

**1. Create Event (as admin)**
```
/admin/config/event-registration/event-config
- Event Name: "Drupal Workshop"
- Category: "Online Workshop"
- Event Date: 2026-03-15
- Registration Start: 2026-02-01
- Registration End: 2026-03-10
```
<img width="1857" height="761" alt="Image" src="https://github.com/user-attachments/assets/c65e20d3-1f6c-4f5c-ad65-5d809eb1c131" />

**2. Configure Settings**
```
/admin/config/event-registration/settings
- Admin Email: your@email.com
- Enable Notifications: ✓
```

<img width="1862" height="440" alt="Image" src="https://github.com/user-attachments/assets/dc8cdf5b-7b3b-4abd-91d1-5ae782e8fa6b" />



**3. Register (as user)**
```
/event/register
- Select category → dates populate
- Select date → event names populate
- Submit form
```
<img width="1857" height="964" alt="Image" src="https://github.com/user-attachments/assets/825740db-4081-4d80-bdbe-aa5b9d491dbf" />

<img width="1857" height="964" alt="Image" src="https://github.com/user-attachments/assets/19c6d450-de93-4bb9-acca-6085564bcc3d" />

**4. Verify**
- Check email inbox (user + admin notifications)
- Visit `/admin/reports/event-registrations`
- Filter by event, export CSV

<img width="1862" height="606" alt="Image" src="https://github.com/user-attachments/assets/3e87ab5c-5b1b-4854-ac5c-2a0b6949d9e2" />

<img width="1862" height="606" alt="Image" src="https://github.com/user-attachments/assets/fa996f60-0989-47b4-b33e-365849cff949" />

<img width="1857" height="964" alt="Image" src="https://github.com/user-attachments/assets/364e2b55-a6e6-454e-afef-b42d605d111a" />

<img width="1915" height="1042" alt="Image" src="https://github.com/user-attachments/assets/05457f28-959d-4267-86cb-fc8675309a4d" />

**5. Database Verification**
```bash
ddev mysql
SELECT * FROM event_config;
SELECT * FROM event_registration;
```

---

##  Technical Highlights

### Dependency Injection Pattern
```php
// Form constructor
public function __construct(EventManager $event_manager) {
  $this->eventManager = $event_manager;
}

// Service container integration
public static function create(ContainerInterface $container) {
  return new static(
    $container->get('event_registration.event_manager')
  );
}
```

### AJAX Callback Implementation
```php
$form['category'] = [
  '#ajax' => [
    'callback' => '::updateEventDateCallback',
    'wrapper' => 'event-date-wrapper',
  ],
];

public function updateEventDateCallback(array &$form, FormStateInterface $form_state) {
  return $form['event_date_wrapper'];
}
```

### Validation Layers
1. **Form-level**: Regex pattern matching, email format
2. **Service-level**: Duplicate registration check
3. **Database-level**: Composite unique index


---

## Submission Checklist

- [x] SQL dump includes sample events
- [x] No debug statements (`kint`, `dpm`, `var_dump`)
- [x] Module folder named `event_registration`
- [x] All routes accessible without errors
- [x] Drupal coding standards compliant
- [x] README.md complete
- [x] Composer.json and composer.lock included
- [x] Consistent Git commit history

---

##  Contact

**Developer**: Krishnashish Munshi  
**Email**: ksmunshi06@gmail.com

---

**Thank you for reviewing this submission!**
