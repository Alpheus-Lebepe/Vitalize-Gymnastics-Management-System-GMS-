<?php
// <-------------------------------------------Vitalize Gymnastics Management System (GMS) ------------------------------------------>

// This allows the database to be created automatically if it does not exist
// ---------- CONFIG ----------
const DB_PATH = __DIR__ . '/vitalize.db';
$dsn = 'sqlite:' . DB_PATH;

// DATABASE CONNECTION (Creates PDO connection)
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO($GLOBALS['dsn']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

 // Create tables if not exist
    $pdo->exec("PRAGMA foreign_keys = ON");

    // Programs table
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS programs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        coach_name TEXT NOT NULL,
        coach_contact TEXT NOT NULL,
        duration_weeks INTEGER NOT NULL CHECK(duration_weeks > 0),
        skill_level TEXT NOT NULL CHECK(skill_level IN ('Beginner','Intermediate','Advanced')),
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    // Gymnasts table
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS gymnasts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        age INTEGER NOT NULL CHECK(age >= 3 AND age <= 100),
        experience_level TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);

    // Enrollments table
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS enrollments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        gymnast_id INTEGER NOT NULL,
        program_id INTEGER NOT NULL,
        enrolled_at TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE(gymnast_id, program_id),
        FOREIGN KEY (gymnast_id) REFERENCES gymnasts(id) ON DELETE CASCADE,
        FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
    );
    SQL);

    // Attendance table
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        enrollment_id INTEGER NOT NULL,
        session_no INTEGER NOT NULL CHECK(session_no >= 1),
        attended INTEGER NOT NULL CHECK(attended IN (0,1)),
        session_date TEXT,
        recorded_at TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE(enrollment_id, session_no),
        FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
    );
    SQL);

    // Progress table
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS progress (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        enrollment_id INTEGER NOT NULL,
        session_no INTEGER NOT NULL CHECK(session_no >= 1),
        notes TEXT,
        score INTEGER, -- 0-100  
        recorded_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
    );
    SQL);

    // Notifications table (for coach notifications)
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        coach_name TEXT NOT NULL,
        coach_contact TEXT NOT NULL,
        message TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    SQL);
    return $pdo;
}

// ---------- HELPERS ----------
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } // h() to escape HTML and to prevent Cross Site Scripting (XSS)
function redirect($path) { header("Location: $path"); exit; }
function param($key, $default=null) { return $_POST[$key] ?? $_GET[$key] ?? $default; }



// ---------- DATA ACCESS ----------
function get_program($id) {
    $stmt = db()->prepare("SELECT * FROM programs WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function enrolled_count($program_id) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM enrollments WHERE program_id = ?");
    $stmt->execute([$program_id]);
    return (int)$stmt->fetchColumn();
}

function progress_percent($enrollment_id) {
    // completion = (# sessions with attendance OR any progress entry) / duration * 100 (capped 100)) 
    // this is calculated based on the number of unique sessions attended or with progress notes
    $stmt = db()->prepare("SELECT p.duration_weeks FROM enrollments e JOIN programs p ON e.program_id = p.id WHERE e.id = ?");
    $stmt->execute([$enrollment_id]);
    $duration = (int)$stmt->fetchColumn();
    if ($duration <= 0) return 0;

    $stmt = db()->prepare("SELECT COUNT(DISTINCT session_no) FROM (
        SELECT session_no FROM attendance WHERE enrollment_id = ?
        UNION
        SELECT session_no FROM progress WHERE enrollment_id = ?
    )");
    $stmt->execute([$enrollment_id, $enrollment_id]);
    $covered = (int)$stmt->fetchColumn();

    return min(100, (int)round(($covered / $duration) * 100));
}

function coach_notify($coach_name, $coach_contact, $message) {
    $stmt = db()->prepare("INSERT INTO notifications (coach_name, coach_contact, message) VALUES (?,?,?)");
    $stmt->execute([$coach_name, $coach_contact, $message]);
}

// ---------- ACTION DISPATCH ----------
$action = param('action','list_programs');

try {
    db(); // ensure that the Database(db) exists/connected


    // Create program
    if ($action === 'create_program' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = db()->prepare("INSERT INTO programs (name, description, coach_name, coach_contact, duration_weeks, skill_level) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            trim(param('name')),
            trim(param('description')),
            trim(param('coach_name')),
            trim(param('coach_contact')),
            (int)param('duration_weeks'),
            param('skill_level')
        ]);
        redirect('?msg=Program%20created');
    }

    // Update program
    if ($action === 'update_program' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)param('id');
        $stmt = db()->prepare("UPDATE programs SET name=?, description=?, coach_name=?, coach_contact=?, duration_weeks=?, skill_level=? WHERE id=?");
        $stmt->execute([
            trim(param('name')),
            trim(param('description')),
            trim(param('coach_name')),
            trim(param('coach_contact')),
            (int)param('duration_weeks'),
            param('skill_level'),
            $id
        ]);
        redirect('?msg=Program%20updated');
    }

    // Delete program
    if ($action === 'delete_program') {
        $id = (int)param('id');
        $stmt = db()->prepare("DELETE FROM programs WHERE id = ?");
        $stmt->execute([$id]);
        redirect('?msg=Program%20deleted');
    }

    // Enroll gymnast
    if ($action === 'enroll' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $program_id = (int)param('program_id');
        // create gymnast (registration)
        $gstmt = db()->prepare("INSERT INTO gymnasts (name, age, experience_level) VALUES (?,?,?)");
        $gstmt->execute([
            trim(param('gymnast_name')),
            (int)param('age'),
            trim(param('experience_level'))
        ]);
        $gymnast_id = (int)db()->lastInsertId();
        // create enrollment
        $estmt = db()->prepare("INSERT OR IGNORE INTO enrollments (gymnast_id, program_id) VALUES (?,?)");
        $estmt->execute([$gymnast_id, $program_id]);

        // coach notification (alerts about new enrollment in a selected program)
        $p = get_program($program_id);
        if ($p) {
            $msg = sprintf(
                'New enrolment: %s (age %d, %s) enrolled in %s.',
                trim(param('gymnast_name')), (int)param('age'), trim(param('experience_level')), $p['name']
            );
            coach_notify($p['coach_name'], $p['coach_contact'], $msg);
        }
        redirect('?action=view_program&id='.$program_id.'&msg=Gymnast%20enrolled');
    }

    // Mark attendance
    if ($action === 'mark_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $enrollment_id = (int)param('enrollment_id');
        $session_no = max(1, (int)param('session_no'));
        $attended = (int)!!param('attended');
        $date = trim(param('session_date')) ?: null;
        $stmt = db()->prepare("INSERT INTO attendance (enrollment_id, session_no, attended, session_date) VALUES (?,?,?,?)
            ON CONFLICT(enrollment_id, session_no) DO UPDATE SET attended=excluded.attended, session_date=excluded.session_date");
        $stmt->execute([$enrollment_id, $session_no, $attended, $date]);
        redirect('?action=view_enrollment&id='.$enrollment_id.'&msg=Attendance%20saved');
    }

    // Add progress notes / session scores
    if ($action === 'add_progress' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $enrollment_id = (int)param('enrollment_id');
        $session_no = max(1, (int)param('session_no'));
        $notes = trim(param('notes'));
        $score = strlen((string)param('score')) ? (int)param('score') : null;
        $stmt = db()->prepare("INSERT INTO progress (enrollment_id, session_no, notes, score) VALUES (?,?,?,?)");
        $stmt->execute([$enrollment_id, $session_no, $notes, $score]);
        redirect('?action=view_enrollment&id='.$enrollment_id.'&msg=Progress%20added');
    }

} catch (Throwable $e) {
    http_response_code(500);
    ?>
    <pre style="white-space:pre-wrap"><?= h($e) ?></pre>
    <?php
    exit;
}


// ---------- VIEW HELPERS / UI ----------
$skill_options = ['Beginner','Intermediate','Advanced']; // Predefined list of skill levels for programs and gymnasts

function header_html($title='Vitalize') { ?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"> <!-- I got this from google, to help me with styling (Bootstrap 5) -->

    <style>
       /* Custom CSS for styling components */

.progress-small {
    height: 18px
}

.table thead th {
    white-space: nowrap
}

body {
    min-height: 100vh;
    margin: 0;
    background: url('../images/img1.jpg') no-repeat;
    background-color: darkblue;
    background-size: cover;
}

/* Navbar styles */
.nav-link {
    color: white;
    font-weight: 500;
    padding: 8px 18px;
    border-radius: 20px;
    text-decoration: none;
    margin-left: 20px;
    font-size: 17px;
    transition: background .5s ease;
}

.nav-link:hover {
    background: white;
    color: black;
}

.navbar-brand {
    font-size: 30px;
    color: white;
    text-decoration: none;
    font-weight: 700;
    margin-right: auto;
    cursor: default;
}

.navbar {
    padding: 20px 100px;
    display: flex;
    align-items: center;
    backdrop-filter: blur(40px);
    z-index: 99;
    background: transparent;
}

/* Container and form styling */
.container-narrow {
    border-radius: 20px;
    background: rgba(0, 0, 0, .2);
    backdrop-filter: blur(20px);
    box-shadow: 0 0 30px rgba(0, 0, 0, .3);
    border: 2px solid rgba(255, 255, 255, .2);
    color: white;
    max-width: 1100px;
    padding: 20px;
}

.form-label {
    color: black;
    font-weight: 500;
    font-size: 17px;
}

.main_page select,
.main_page input {
    background-color: transparent;
    outline: none;
    border: 2px solid rgba(255, 255, 255, .2);
    padding: 15px;
    width: 100%;
    height: 100%;
    border-radius: 40px;
    font-size: 16px;
    color: white;
}

.main_page input::placeholder {
    color: white;
}

.main_page select option {
    background-color: black;
    color: white;
}

/* --- Tables, labels, cards styling --- */
.custom-blue-table thead th {
    background-color: rgba(0, 110, 200, 1);
    color: white;
}

.text-muted_2,
.form-label_2,
.view_form {
    color: white;
}

.text_note {
    color: red;
}

.card-about {
    border-radius: 20px;
    backdrop-filter: blur(20px);
    box-shadow: 0 0 30px rgba(0, 0, 0, .3);
    border: 2px solid rgba(255, 255, 255, .2);
    color: white;
    max-width: 1100px;
    padding: 20px;
} 
    </style>
</head>

<body >
<nav class="navbar navbar-expand-lg navbar-dark">  <!-- Navbar with dark theme -->
    <div class="container-fluid" >   <!-- I set the container to use fluid layout -->
        <a class="navbar-brand" href="#">Vitalize</a>
        <div class="navbar-nav">
            <!-- navigation links -->
            <a class="nav-link" href="?">Available Gymnastics Programs</a>
            <a class="nav-link" href="?action=create_program_form">+ Add Program</a>
            <a class="nav-link" href="?action=notifications">Coach Notifications</a>
        </div>
    </div>
</nav>
<main class="container container-narrow my-5" >
    <?php if ($msg = param('msg')): ?>  <!-- Display success messages -->
        <div class="alert alert-success"><?= h($msg) ?></div>
    <?php endif; ?>
<?php }

/**
 * Returns the HTML footer and closes the page.
 */

// HTML Footer 
function footer_html(){ ?>
</main>
</body>
</html>
<?php }




// (Program form)  to create  and edit gyminastic programs
function program_form($p=null){
    global $skill_options;
    $is_edit = $p !== null;  //check if the user is editing an existing program
    $action = $is_edit ? 'update_program' : 'create_program';
?>
<div class="card shadow-sm" >
    <div class="card-body" >
        <h5 class="card-title"><?= h($is_edit ? 'Edit Gymnastics Program' : 'Add Gymnastics Program') ?></h5> 
        <form method="post" action="?action=<?= h($action) ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="id" value="<?= h($p['id']) ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Program name</label>
                    <input required name="name" class="form-control" value="<?= h($p['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Skill level</label>
                    <select required name="skill_level" class="form-select">
                        <?php foreach($skill_options as $opt): ?>
                            <option value="<?= h($opt) ?>" <?= (($p['skill_level'] ?? '') === $opt) ? 'selected' : '' ?>>
                                <?= h($opt) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= h($p['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Coach/instructor name</label>
                    <input required name="coach_name" class="form-control" value="<?= h($p['coach_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Coach contact (email/phone)</label>
                    <input required name="coach_contact" class="form-control" value="<?= h($p['coach_contact'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Duration (weeks)</label>
                    <input required type="number" min="1" name="duration_weeks" class="form-control" value="<?= h($p['duration_weeks'] ?? 8) ?>">
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary"><?= h($is_edit ? 'Save changes' : 'Create program') ?></button>
                <a class="btn btn-outline-secondary" href="?">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php }



 // (View form / main page) display all available gymnastic programs
function list_programs(){            
    header_html('Programs — Vitalize');
    $q = trim((string)param('q',''));
    $skill = (string)param('f_skill','');
    $coach = trim((string)param('f_coach',''));
    ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">Available Gymnastics Programs</h3>
        <a style="border: 2px solid white; border-radius: 40px;" 
           class="btn btn-success" href="?action=create_program_form">+ Add Program</a>
    </div>

    <form class="card card-body shadow-sm mb-3" style="background: transparent;" method="get">
        <input type="hidden" name="action" value="list_programs">
        <div class="row g-2 align-items-end">
            <div class="main_page col-md-3 ">
                <label class="view_form">Search</label>
                <input name="q" value="<?= h($q) ?>" class="form-control" placeholder="Name/description">
            </div>
            <div class="main_page col-md-4 ">
                <label class="view_form">Skill level</label>
                <select name="f_skill" class="form-select">
                    <option value="">Any Skill Level</option>
                    <?php foreach(['Beginner','Intermediate','Advanced'] as $s): ?>
                        <option value="<?= h($s) ?>" <?= $skill===$s?'selected':'' ?>><?= h($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="main_page col-md-3 ">
                <label class="view_form">Coach</label>
                <input name="f_coach" value="<?= h($coach) ?>" class="form-control" placeholder="Coach name">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </div>
    </form>


    <?php
    // (Build query) used to filter programs based on search criteria / user preferences
    $where = []; $params = [];
    if ($q !== '') { $where[] = '(name LIKE ? OR description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($skill !== '') { $where[] = 'skill_level = ?'; $params[] = $skill; }
    if ($coach !== '') { $where[] = 'coach_name LIKE ?'; $params[] = "%$coach%"; }
    $sql = 'SELECT * FROM programs';
    if ($where) { $sql .= ' WHERE '.implode(' AND ',$where); }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="table-responsive">  <!-- Table for displaying programs (on main page) -->
        <table class="table table-striped align-middle custom-blue-table" >
            <thead>
                <tr>
                    <th>Program Name</th>
                    <th>Coach</th>
                    <th>Duration</th>
                    <th>Skill Level</th>
                    <th>Enrolled Gymnasts</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>

            <?php if ($rows): ?>
                <?php foreach($rows as $r): ?>
                    <tr>
                        <td><a style="font-size: 20px;" href="?action=view_program&id=<?= h($r['id']) ?>"><em><?= h($r['name']) ?></em></a></td>
                        <td><?= h($r['coach_name']) ?></td>
                        <td><?= h($r['duration_weeks']) ?> weeks</td>
                        <td><span class="badge text-bg-secondary"><?= h($r['skill_level']) ?></span></td>
                        <td><?= h(enrolled_count($r['id'])) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="?action=edit_program_form&id=<?= h($r['id']) ?>">Edit Program</a>
                            <a class="btn btn-sm btn-outline-danger" href="?action=delete_program&id=<?= h($r['id']) ?>" onclick="return confirm('Delete this program?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted">No Programs Available</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    footer_html();
}

function create_program_form(){ header_html('Add Program — Vitalize'); program_form(null); footer_html(); }

function edit_program_form(){  // in case the user wants to edit a program (deleted program) that does not exist (Edit Program Form)
    $p = get_program((int)param('id'));
    header_html('Edit Program — Vitalize');
    if ($p): ?>
        <?php program_form($p); ?>
    <?php else: ?>
        <div class="alert alert-danger">Program not found.</div>
    <?php endif;
    footer_html();
}

function view_program(){ 
    $p = get_program((int)param('id'));
    header_html('Program — Vitalize');
     $skill = (string)param('f_skill','');

    if (!$p): ?> 
        <div class="alert alert-danger">Program not found.</div>
        <?php footer_html(); return;
    endif; ?>

<!-- display program details in the Enrollment form -->
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h3 class="mb-1"><?= h($p['name']) ?></h3>
            <div class="text-muted_2"> • Skill level: <?= h($p['skill_level']) ?> <br> • Duration: <?= h($p['duration_weeks']) ?> weeks</div>
            <div>Coach: <strong><?= h($p['coach_name']) ?></strong> <span class="text-muted_2">(<?= h($p['coach_contact']) ?>)</span></div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="?action=edit_program_form&id=<?= h($p['id']) ?>">Edit Program</a> 
            <a class="btn btn-outline-secondary" href="?">Go-Back</a> <!-- Go back to the program list (main page) -->
        </div>
    </div>

    <div class="card-about mb-3 shadow-sm"> 
        <div class="card-body">
            <h5>About this program</h5>
            <p><?= nl2br(h($p['description'])) ?></p>
        </div>
    </div>

    <!-- (Enrollment form) used to register gymnasts in the selected program -->
    <div class="card-about mb-4 shadow-sm"> 
        <div class="card-body">
            <h5 class="card-title">Enroll a gymnast</h5>
            <form method="post" action="?action=enroll">
                <input type="hidden" name="program_id" value="<?= h($p['id']) ?>">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label_2">Name</label>
                        <input name="gymnast_name" required class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label_2">Age</label>
                        <input type="number" min="3" max="100" name="age" required class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label_2">Skill level</label>
                <select name="experience_level" class="form-select">
                    <?php foreach(['Beginner','Intermediate','Advanced'] as $s): ?>
                        <option value="<?= h($s) ?>" <?= $skill===$s?'selected':'' ?>><?= h($s) ?></option>
                    <?php endforeach; ?>
                </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-success">Enroll</button>
                    </div>
                </div>
            </form>
            <div class="form-label_2 small mt-2">The coach will be notified automatically.</div>
        </div>
    </div>
    <?php
    // Fetch enrolled gymnasts details for the selected program
    $stmt = db()->prepare("SELECT e.id AS enrollment_id, g.* 
                           FROM enrollments e 
                           JOIN gymnasts g ON e.gymnast_id=g.id 
                           WHERE e.program_id = ? 
                           ORDER BY e.enrolled_at DESC");
    $stmt->execute([$p['id']]);
    $enr = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

<!-- display the enrolled gymnasts in a table form-->
    <div class="card-about shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Enrolled gymnasts</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle custom-blue-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Experience</th>
                            <th>Progress</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($enr): ?>
                        <?php foreach($enr as $row): ?>
                            <?php $pct = progress_percent($row['enrollment_id']); ?>
                            <tr>
                                <td><?= h($row['name']) ?></td>
                                <td><?= h($row['age']) ?></td>
                                <td><?= h($row['experience_level']) ?></td>
                                <td style="min-width:200px"> <!-- for progress bar -->
                                    <div class="progress progress-small">
                                        <div class="progress-bar" role="progressbar" style="width: <?= h($pct) ?>%"><?= h($pct) ?>%</div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-primary" href="?action=view_enrollment&id=<?= h($row['enrollment_id']) ?>">Progress/Attendance</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-muted text-center">No gymnasts enrolled yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    footer_html();
}

// Display enrollment details in the Enrollment form
function view_enrollment(){
    $id = (int)param('id');
    $stmt = db()->prepare("SELECT e.*, 
                                  g.name AS gname, g.age, g.experience_level, 
                                  p.name AS pname, p.duration_weeks, p.id AS pid 
                           FROM enrollments e 
                           JOIN gymnasts g ON e.gymnast_id=g.id 
                           JOIN programs p ON e.program_id=p.id 
                           WHERE e.id=?");
    $stmt->execute([$id]);
    $E = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$E) {
        header_html('Enrollment — Vitalize'); ?>
        <div class="alert alert-danger">Enrollment not found.</div>
        <?php footer_html(); return;
    }
    header_html('Enrollment — Vitalize'); ?> 

    <div class="mb-3" >
        <a href="?action=view_program&id=<?= h($E['pid']) ?>" class="btn btn-sm btn-outline-secondary" style="border: 2px solid red; color: white; background: red">← Back to program</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><?= h($E['gname']) ?> — <span class="text-muted"><?= h($E['pname']) ?></span></h5>
            <?php $pct = progress_percent($id); ?>
            <div class="mb-2" style="max-width:400px">
                <div class="progress">
                    <div class="progress-bar" style="width: <?= h($pct) ?>%"><?= h($pct) ?>%</div>
                </div>
            </div>
            <div class="text-muted"> • Age: <?= h($E['age']) ?> <br>• Skill level: <?= h($E['experience_level']) ?> <br>• Duration: <?= h($E['duration_weeks']) ?> weeks</div>
        </div>
    </div>

    <!-- (Attendance and Progress forms) used to track gymnast attendance and progress in the selected program -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h6>Mark attendance</h6>
                    <form class="row g-2" method="post" action="?action=mark_attendance">
                        <input type="hidden" name="enrollment_id" value="<?= h($id) ?>"><!-- am here -->
                        <div class="col-4">
                            <label class="form-label">Session #</label>
                            <input type="number" min="1" max="<?= h($E['duration_weeks']) ?>" name="session_no" class="form-control" required>
                        </div>
                        <div class="col-5">
                            <label class="form-label">Session date</label>
                            <input type="date" name="session_date" class="form-control" required>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Attended</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="attended" value="1" id="att">
                                <label class="form-check-label" for="att">Yes</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary">Save attendance</button>
                        </div>
                    </form>

    <!-- Fetch and display attendance records for the selected enrollment in a table format -->
                    <?php
                    $stmt = db()->prepare("SELECT * FROM attendance WHERE enrollment_id=? ORDER BY session_no");
                    $stmt->execute([$id]);
                    $att = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <hr>
                    <div class="table-responsive">
                        <table class="table table-sm custom-blue-table">
                            <thead>
                                <tr>
                                    <th>Session</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($att): ?>
                                <?php foreach($att as $a): ?>
                                    <tr>
                                        <td><?= h($a['session_no']) ?></td>
                                        <td><?= h($a['session_date']) ?></td>
                                        <td>
                                            <?php if ($a['attended']): ?>
                                                <span class="badge text-bg-success">Present</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary">Absent</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-muted">No attendance yet.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <!-- Progress notes / scorecard section -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h6>Progress notes / scorecard</h6>
                    <form class="row g-2" method="post" action="?action=add_progress">
                        <input type="hidden" name="enrollment_id" value="<?= h($id) ?>">
                        <div class="col-4">
                            <label class="form-label">Session #</label>
                            <input type="number" min="1" max="<?= h($E['duration_weeks']) ?>" name="session_no" class="form-control" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Score (0-100)</label>
                            <input type="number" min="0" max="100" name="score" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="e.g., Feedback / Improvement suggestions"></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary">Add note</button>
                        </div>
                    </form>

    <!-- Fetch and display progress records for the selected enrollment in a table format -->
                    <?php
                    $stmt = db()->prepare("SELECT * FROM progress WHERE enrollment_id=? ORDER BY session_no, recorded_at");
                    $stmt->execute([$id]);
                    $pr = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <hr>
                    <div class="table-responsive">
                        <table class="table table-sm custom-blue-table">
                            <thead>
                            <tr>
                                <th>Session</th>
                                <th>Score</th>
                                <th>Notes</th>
                                <th>Recorded</th>
                            </tr>
                        </thead>
                            <tbody>
                            <?php if ($pr): ?>
                                <?php foreach($pr as $p): ?>
                                    <tr>
                                        <td><?= h($p['session_no']) ?></td>
                                        <td><?= h($p['score']) ?></td>
                                        <td><?= nl2br(h($p['notes'])) ?></td>
                                        <td><?= h($p['recorded_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-muted">No progress notes yet.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php
    footer_html();
}

// Fetch and display coach notifications
function notifications(){
    header_html('Coach Notifications — Vitalize');

    $stmt = db()->query("SELECT * FROM notifications ORDER BY created_at DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

<!-- Coach Notifications (log) messages to alert coaches about new enrollments -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Coach Notifications </h5>

            <div class="table-responsive">
                <table class="table table-sm custom-blue-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Coach</th>
                            <th>Contact</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows): ?>
                        <?php foreach($rows as $n): ?>
                            <tr>
                                <td><?= h($n['created_at']) ?></td>
                                <td><?= h($n['coach_name']) ?></td>
                                <td><?= h($n['coach_contact']) ?></td>
                                <td><?= h($n['message']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-muted">No notifications yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="text_note small">Note by Masilo: Email/SMS system is in develoment stage.</div>
        </div>
    </div>

    <?php
    footer_html();
}

// help to navigate the system 
// ---------- ROUTER ----------
switch($action){ // use the 'action' parameter to determine which function to call
    case 'list_programs': list_programs(); break;
    case 'create_program_form': create_program_form(); break;
    case 'edit_program_form': edit_program_form(); break;
    case 'view_program': view_program(); break;
    case 'view_enrollment': view_enrollment(); break;
    case 'notifications': notifications(); break;
    default: list_programs(); break;
// ---------- END ROUTER ----------
}


//--------------The END ----------------

// <----------- By Lebepe M.A --------------->