# Vitalize Gymnastics Management System

A lightweight, single-file PHP application for managing gymnastics programs, gymnast enrollments, attendance, progress notes, and coach notification records.

The application uses SQLite and creates its database schema automatically the first time it runs.

## Features

- Create, view, edit, and delete gymnastics programs.
- Store program descriptions, coaches, coach contact details, duration, and skill level.
- Search and filter programs by:
  - Program name or description
  - Skill level
  - Coach name
- Enroll gymnasts in a selected program.
- Track each gymnast's age, experience level, and enrollment date.
- Record attendance by session number and date.
- Add progress notes and optional scores from 0 to 100.
- Display an enrollment progress percentage based on sessions with attendance or progress records.
- Log a coach notification automatically when a gymnast is enrolled.
- Use Bootstrap 5 for the user interface.

## Requirements

- PHP 7.1 or later. PHP 8.x is recommended.
- PDO SQLite extension enabled.
- A web server capable of executing PHP, such as:
  - PHP's built-in development server
  - Apache with PHP
  - Nginx with PHP-FPM
- Internet access for the Bootstrap stylesheet loaded from jsDelivr, unless the stylesheet is replaced with a local copy.

To verify that the required SQLite extension is enabled, run:

```bash
php -m | grep -i sqlite
```

The output should include `PDO_SQLITE` and normally `sqlite3`.

## Installation

1. Place `vitalize_management_system.php` in a PHP-accessible web directory.
2. Ensure the web server process has permission to create and write files in that directory.
3. Optionally provide the background image expected by the stylesheet at:

   ```text
   ../images/img1.jpg
   ```

   The image is optional; the application falls back to a dark-blue background if it is unavailable.
4. Open the PHP file through a web server. The SQLite database is created automatically on first access.

### Run with PHP's built-in server

From the directory containing `vitalize_management_system.php`, run:

```bash
php -S localhost:8000
```

Then open:

```text
http://localhost:8000/vitalize_management_system.php
```

Do not open the file directly using a `file://` URL; PHP must process it through a web server.

## Database

The application stores its SQLite database at:

```text
vitalize.db
```

This file is created in the same directory as the PHP script. The schema is initialized automatically by the `db()` function.

The database contains the following tables:

| Table | Purpose |
|---|---|
| `programs` | Gymnastics program details and coach information |
| `gymnasts` | Registered gymnast details |
| `enrollments` | Links gymnasts to programs |
| `attendance` | Attendance status for each enrollment and session |
| `progress` | Session notes and optional scores |
| `notifications` | Coach notification log created after enrollment |

To reset the application during development, stop the server and delete `vitalize.db`. The database and all stored records will be recreated when the application is opened again.

> Deleting `vitalize.db` permanently removes all programs, gymnasts, enrollments, attendance records, progress notes, and notifications. Back it up before deleting it.

## Basic Usage

### 1. Create a program

Select **+ Add Program** and enter:

- Program name
- Skill level: `Beginner`, `Intermediate`, or `Advanced`
- Description
- Coach or instructor name
- Coach contact information
- Duration in weeks

Select **Create program** to save it.

### 2. Find a program

The home page lists available programs. Use the search and filter controls to search by name or description, skill level, or coach.

Select a program name to open its details.

### 3. Enroll a gymnast

From a program details page, complete the **Enroll a gymnast** form with:

- Gymnast name
- Age between 3 and 100
- Experience level

Select **Enroll**. The enrollment is saved and a notification record is created for the program's coach.

### 4. Record attendance

From the enrolled gymnasts list, select **Progress/Attendance** for a gymnast. In the **Mark attendance** form:

1. Enter a session number between 1 and the program duration.
2. Select the session date.
3. Select **Yes** when the gymnast attended.
4. Select **Save attendance**.

Attendance for the same enrollment and session can be updated later.

### 5. Record progress

On the same enrollment page, use **Progress notes / scorecard** to add:

- Session number
- Optional score from 0 to 100
- Notes or feedback

Select **Add note** to save the entry.

### 6. View coach notifications

Select **Coach Notifications** in the navigation bar to view notification records generated when gymnasts are enrolled. The current implementation stores notifications in the database only; it does not send email or SMS messages.

## URL Actions

The application uses a simple query-string router. Common actions include:

| URL/action | Purpose |
|---|---|
| `?` or `?action=list_programs` | List and filter programs |
| `?action=create_program_form` | Show the new-program form |
| `?action=edit_program_form&id=<id>` | Edit a program |
| `?action=view_program&id=<id>` | View program details and enroll gymnasts |
| `?action=view_enrollment&id=<id>` | View attendance and progress for an enrollment |
| `?action=notifications` | View coach notification records |

Create, update, enrollment, attendance, and progress operations are submitted through POST requests.

## Project Structure

The project currently consists of one main PHP file:

```text
vitalize_management_system.php   Application, UI, routing, and database initialization
vitalize.db                      SQLite database created at runtime
README.md                        Project documentation
images/img1.jpg                  Optional background image expected by the CSS
```

## Data Validation and Relationships

The database enforces several constraints:

- Program duration must be greater than zero.
- Program skill level must be `Beginner`, `Intermediate`, or `Advanced`.
- Gymnast age must be between 3 and 100.
- Session numbers must be at least 1.
- Attendance is stored as either present (`1`) or absent (`0`).
- A gymnast cannot have duplicate enrollment records for the same program.
- Attendance is unique per enrollment and session number.
- Deleting a gymnast, program, or enrollment cascades to related records where configured.

The application uses prepared SQL statements for user-supplied database values and escapes rendered HTML with `htmlspecialchars()`.

## Production Considerations

This project is suitable as a small local or educational management application. Before deploying it publicly, consider adding:

- User authentication and role-based access control.
- CSRF protection for all state-changing forms.
- Server-side validation with user-friendly validation messages.
- Authorization checks for edit and delete actions.
- POST-only deletion instead of the current GET-based delete link.
- A database backup and restore process.
- Secure production error handling that does not display exception details.
- HTTPS and secure server configuration.
- Pagination for large program, enrollment, and notification lists.
- Email or SMS delivery for coach notifications.
- A local Bootstrap copy or an asset build process if external CDN access is not desired.
- Improved progress calculations if attendance should count only attended sessions rather than any attendance record.

## Troubleshooting

### `could not find driver`

Enable the PDO SQLite extension in PHP. On Debian or Ubuntu, this is commonly installed with:

```bash
sudo apt install php-sqlite3
```

Restart the relevant web server after enabling the extension.

### The database cannot be created

Check that the directory containing `vitalize_management_system.php` is writable by the PHP process. The application must be able to create and update `vitalize.db` in that directory.

### The background image is missing

The stylesheet references `../images/img1.jpg` relative to the PHP page URL. Create the expected `images` directory and place `img1.jpg` there, or update the CSS background URL in the PHP file.

### Changes are not visible

Confirm that the page is being served by PHP rather than opened as a local file, and check the PHP/web-server error log for database or permission errors.

## License

No license is currently specified. Add a `LICENSE` file before distributing or deploying the project if a particular open-source or proprietary license is intended.
