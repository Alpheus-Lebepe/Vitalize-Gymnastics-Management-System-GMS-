# Vitalize Gymnastics Management System
## User Manual and Technical Action Reference

**Application file:** `vitalize_management_system.php`  
**Database:** SQLite (`vitalize.db`)  
**Document type:** End-user guide with technical route and form-action reference

---

## 1. Overview

Vitalize is a browser-based gymnastics management system for maintaining gymnastics programs and monitoring participating gymnasts.

The system supports:

- Program management
- Coach information management
- Gymnast enrollment
- Attendance tracking
- Progress notes and scoring
- Automatic coach notification logging
- Program search and filtering

The application is implemented as a single PHP file with an automatically created SQLite database.

## 2. Important Note About the API

This project does **not** currently expose a conventional REST or JSON API. It is a server-rendered PHP application that uses query-string actions and HTML forms.

For that reason, this document uses the term **action reference** rather than claiming that the application provides REST endpoints. Requests return HTML pages or redirect back to HTML pages, not JSON responses.

If a formal REST API is required in the future, a separate API layer should be added with JSON responses, authentication, request validation, and versioned routes.

---

## 3. Getting Started

### 3.1 Requirements

You need:

- PHP 7.1 or later; PHP 8.x is recommended.
- PHP PDO SQLite support enabled.
- A PHP-compatible web server.
- A writable directory for the SQLite database file.
- Internet access if the Bootstrap stylesheet is loaded from the jsDelivr CDN.

Check the SQLite extension with:

```bash
php -m | grep -i sqlite
```

### 3.2 Start the application locally

From the directory containing `vitalize_management_system.php`, run:

```bash
php -S localhost:8000
```

Open the following address in a browser:

```text
http://localhost:8000/vitalize_management_system.php
```

The first request creates `vitalize.db` automatically in the same directory as the PHP file.

### 3.3 First-time setup

No database import or manual table creation is required. The application creates these tables automatically:

- `programs`
- `gymnasts`
- `enrollments`
- `attendance`
- `progress`
- `notifications`

---

## 4. Navigation

The main navigation contains three primary options:

| Menu item | Purpose |
|---|---|
| **Available Gymnastics Programs** | View, search, filter, edit, or delete programs |
| **+ Add Program** | Create a new gymnastics program |
| **Coach Notifications** | View notification records created after enrollments |

---

## 5. Managing Programs

### 5.1 Create a program

1. Select **+ Add Program**.
2. Enter the program name.
3. Select a skill level:
   - Beginner
   - Intermediate
   - Advanced
4. Enter an optional description.
5. Enter the coach or instructor name.
6. Enter the coach's email address or phone number.
7. Enter the duration in weeks.
8. Select **Create program**.

The program appears on the available-programs page.

### 5.2 View a program

Select a program name from the list to view:

- Program name
- Skill level
- Duration
- Coach name
- Coach contact details
- Program description
- Enrolled gymnasts
- Enrollment progress percentages

### 5.3 Search and filter programs

On the programs page, use:

- **Search** to search program names and descriptions.
- **Skill level** to filter by Beginner, Intermediate, or Advanced.
- **Coach** to search by coach name.

Select **Filter** to apply the selected criteria.

### 5.4 Edit a program

1. Select **Edit Program** from the program list or program details page.
2. Update the desired fields.
3. Select **Save changes**.

### 5.5 Delete a program

1. Select **Delete** next to the program.
2. Confirm the browser prompt.

Deleting a program also deletes its related enrollment, attendance, and progress records because the database uses cascading deletes.

> Deletion is permanent. Back up `vitalize.db` before removing important records.

---

## 6. Enrolling a Gymnast

### 6.1 Add an enrollment

1. Open the desired program.
2. Find the **Enroll a gymnast** form.
3. Enter the gymnast's name.
4. Enter an age between 3 and 100.
5. Select the gymnast's experience level.
6. Select **Enroll**.

The system creates:

- A gymnast record in `gymnasts`.
- An enrollment record linking the gymnast to the selected program.
- A coach notification record in `notifications`.

The notification contains the gymnast's name, age, experience level, and program name.

### 6.2 View enrolled gymnasts

The program details page displays all gymnasts enrolled in that program, including:

- Name
- Age
- Experience level
- Progress percentage
- A link to attendance and progress details

---

## 7. Tracking Attendance

### 7.1 Record attendance

1. From the program page, select **Progress/Attendance** next to a gymnast.
2. In **Mark attendance**, enter the session number.
3. Select the session date.
4. Select **Yes** if the gymnast attended.
5. Select **Save attendance**.

Session numbers must be at least 1 and cannot exceed the program duration in weeks.

### 7.2 Update attendance

To update a previously recorded session, submit the attendance form again using the same enrollment and session number. The existing attendance record is updated rather than duplicated.

### 7.3 Attendance status

Attendance is shown as:

- **Present** when the attended value is `1`.
- **Absent** when the attended value is `0`.

---

## 8. Recording Progress

### 8.1 Add progress information

1. Open the gymnast's **Progress/Attendance** page.
2. In **Progress notes / scorecard**, enter the session number.
3. Optionally enter a score from 0 to 100.
4. Enter notes, feedback, or improvement suggestions.
5. Select **Add note**.

Progress records include:

- Session number
- Score
- Notes
- Date and time recorded

Multiple progress records may be added for the same session.

### 8.2 Progress percentage

The displayed progress percentage is calculated as follows:

```text
unique sessions with an attendance record or progress record
---------------------------------------------------------------- × 100
program duration in weeks
```

The result is rounded to the nearest whole number and capped at 100%.

Because the calculation counts any attendance record, both present and absent records count as covered sessions in the current implementation.

---

## 9. Coach Notifications

When a gymnast is enrolled, the application automatically stores a notification record containing:

- Date and time
- Coach name
- Coach contact details
- Enrollment message

Open **Coach Notifications** to view the notification log.

The current version does not send email messages or SMS messages. Notifications are stored and displayed inside the application only.

---

## 10. Technical Action Reference

### 10.1 General request format

The application uses the `action` query parameter:

```text
vitalize_management_system.php?action=<action>
```

If no action is provided, the application defaults to the program list.

### 10.2 Browser actions

| Action | Method | Parameters | Result |
|---|---|---|---|
| `list_programs` | GET | `q`, `f_skill`, `f_coach` | Displays filtered program list |
| `create_program_form` | GET | None | Displays the create-program form |
| `edit_program_form` | GET | `id` | Displays the edit form for a program |
| `view_program` | GET | `id` | Displays program details, enrollment form, and enrolled gymnasts |
| `view_enrollment` | GET | `id` | Displays enrollment details, attendance, and progress |
| `notifications` | GET | None | Displays coach notification records |

Examples:

```text
?action=list_programs
?action=list_programs&q=Junior&f_skill=Beginner
?action=view_program&id=1
?action=view_enrollment&id=3
?action=notifications
```

### 10.3 Form actions

These actions are submitted by POST forms and redirect after completion.

| Action | Required method | Main parameters | Result |
|---|---|---|---|
| `create_program` | POST | `name`, `description`, `coach_name`, `coach_contact`, `duration_weeks`, `skill_level` | Creates a program |
| `update_program` | POST | `id`, plus program fields | Updates a program |
| `enroll` | POST | `program_id`, `gymnast_name`, `age`, `experience_level` | Creates a gymnast and enrollment, then logs a notification |
| `mark_attendance` | POST | `enrollment_id`, `session_no`, `session_date`, `attended` | Creates or updates attendance |
| `add_progress` | POST | `enrollment_id`, `session_no`, `score`, `notes` | Adds a progress record |
| `delete_program` | GET in current implementation | `id` | Deletes a program and related records |

### 10.4 Redirect messages

After successful operations, the application redirects with a `msg` query parameter. Examples include:

```text
?msg=Program%20created
?msg=Program%20updated
?msg=Program%20deleted
?msg=Gymnast%20enrolled
?msg=Attendance%20saved
?msg=Progress%20added
```

These messages are displayed as success alerts in the page layout.

---

## 11. Data Reference

### 11.1 Programs

| Field | Description | Rules |
|---|---|---|
| `id` | Unique program identifier | Auto-incrementing integer |
| `name` | Program name | Required |
| `description` | Program description | Optional |
| `coach_name` | Coach or instructor | Required |
| `coach_contact` | Email or phone | Required |
| `duration_weeks` | Program duration | Must be greater than 0 |
| `skill_level` | Program skill level | Beginner, Intermediate, or Advanced |
| `created_at` | Creation timestamp | Automatically generated |

### 11.2 Gymnasts

| Field | Description | Rules |
|---|---|---|
| `id` | Unique gymnast identifier | Auto-incrementing integer |
| `name` | Gymnast name | Required |
| `age` | Gymnast age | 3 to 100 |
| `experience_level` | Experience level | Entered through the enrollment form |
| `created_at` | Registration timestamp | Automatically generated |

### 11.3 Enrollments

| Field | Description |
|---|---|
| `id` | Unique enrollment identifier |
| `gymnast_id` | Linked gymnast |
| `program_id` | Linked program |
| `enrolled_at` | Enrollment timestamp |

A gymnast-program pair is unique in the database.

### 11.4 Attendance

| Field | Description | Rules |
|---|---|---|
| `id` | Unique attendance identifier | Auto-incrementing integer |
| `enrollment_id` | Linked enrollment | Required |
| `session_no` | Session number | At least 1 |
| `attended` | Attendance flag | `0` or `1` |
| `session_date` | Date of session | Optional in database; required by the form |
| `recorded_at` | Record timestamp | Automatically generated |

An enrollment can have only one attendance record per session number.

### 11.5 Progress

| Field | Description | Rules |
|---|---|---|
| `id` | Unique progress identifier | Auto-incrementing integer |
| `enrollment_id` | Linked enrollment | Required |
| `session_no` | Session number | At least 1 |
| `notes` | Coach feedback or notes | Optional |
| `score` | Session score | Intended range 0 to 100 |
| `recorded_at` | Record timestamp | Automatically generated |

### 11.6 Notifications

| Field | Description |
|---|---|
| `id` | Unique notification identifier |
| `coach_name` | Coach receiving the notification |
| `coach_contact` | Stored coach contact |
| `message` | Enrollment notification text |
| `created_at` | Notification timestamp |

---

## 12. Error Handling

Database and runtime exceptions are caught by the application and displayed as HTTP 500 responses. During development, this helps identify configuration problems.

For production deployment, exception details should be logged securely instead of displayed directly to users.

Common problems include:

### SQLite driver error

If PHP reports `could not find driver`, enable the PDO SQLite extension.

### Database permission error

Ensure the PHP process can create and write `vitalize.db` in the application directory.

### Program or enrollment not found

The requested record may have been deleted or the supplied ID may be invalid. Return to the program list and select the record again.

### Missing background image

The UI references `../images/img1.jpg`. If that file is missing, the page still displays a dark-blue background.

---

## 13. Backup and Restore

### 13.1 Back up the database

Stop active writes to the application and copy the database file:

```bash
cp vitalize.db vitalize-backup-$(date +%Y%m%d-%H%M%S).db
```

### 13.2 Restore the database

Stop the PHP server, preserve the existing database, and replace it with a backup:

```bash
mv vitalize.db vitalize-before-restore.db
cp vitalize-backup-YYYYMMDD-HHMMSS.db vitalize.db
```

### 13.3 Reset development data

To start over in a development environment:

```bash
rm vitalize.db
```

The schema is recreated automatically on the next request. Do not run this command in production unless all data has been backed up and deletion is intentional.

---

## 14. Security and Production Recommendations

Before exposing the application to the public internet, add:

1. Authentication and role-based authorization.
2. CSRF tokens for all POST requests.
3. POST-only deletion and server-side authorization for destructive operations.
4. Stronger server-side validation and friendly validation errors.
5. HTTPS.
6. Secure exception logging instead of rendering exception details.
7. Rate limiting and audit logging.
8. Database backup automation.
9. A formal API layer if external applications need access.
10. API authentication, such as session authentication, API keys, or OAuth, for any future API.

The current application escapes output with `htmlspecialchars()` and uses prepared statements for database values, but these protections do not replace authentication, CSRF protection, or authorization controls.

---

## 15. Suggested Future REST API Design

If the system is later converted into a JSON API, a possible versioned structure would be:

```text
GET    /api/v1/programs
POST   /api/v1/programs
GET    /api/v1/programs/{id}
PUT    /api/v1/programs/{id}
DELETE /api/v1/programs/{id}

GET    /api/v1/programs/{id}/enrollments
POST   /api/v1/programs/{id}/enrollments

GET    /api/v1/enrollments/{id}
GET    /api/v1/enrollments/{id}/attendance
POST   /api/v1/enrollments/{id}/attendance
GET    /api/v1/enrollments/{id}/progress
POST   /api/v1/enrollments/{id}/progress

GET    /api/v1/notifications
```

A future API should define JSON request and response schemas, HTTP status codes, authentication, pagination, filtering, validation errors, and rate limits before implementation.

---

## 16. Document Status

This manual reflects the behavior of the current single-file implementation. It should be updated when the application adds authentication, external notifications, a JSON API, new database tables, or changes to the progress calculation.
