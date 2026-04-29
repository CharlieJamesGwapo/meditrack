<?php
/**
 * Setup / Diagnostic tool for Meditrack (Bislig District Hospital).
 *
 * SECURITY:
 *  - Gated by SETUP_TOKEN below. Change it before deploying.
 *  - DELETE THIS FILE from the server as soon as you are done.
 *
 * Access:  https://<your-host>/meditrack/setup-diagnostic.php?token=CHANGE-ME-NOW
 */

// ────────────────────────────────────────────────────────────────────────────
// CHANGE THIS TOKEN before uploading. Keep it secret. No one else should know.
// ────────────────────────────────────────────────────────────────────────────
const SETUP_TOKEN = 'bislig-2026-emergency-setup-9f2a7b';

// ────────────────────────────────────────────────────────────────────────────

// Token gate (URL param OR posted field)
$provided = $_GET['token'] ?? $_POST['token'] ?? '';
if (!hash_equals(SETUP_TOKEN, $provided)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden. Append ?token=YOUR-TOKEN to the URL.\n";
    exit;
}

// Bootstrap DB connection from env.php (same path the app uses)
$envPath = __DIR__ . '/env.php';
if (!file_exists($envPath)) {
    fail('env.php not found at ' . $envPath);
}
$env = require $envPath;
foreach (['DB_HOST', 'DB_NAME', 'DB_USERNAME', 'DB_PASSWORD'] as $k) {
    if (!array_key_exists($k, $env)) fail("Missing $k in env.php");
}

$pdo = null;
$dbError = null;
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4",
        $env['DB_USERNAME'],
        $env['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

// ────────────────────────────────────────────────────────────────────────────
// Action dispatch (POST only for mutating actions; CSRF via token)
$action  = $_POST['action'] ?? '';
$flash   = null;
$flashOk = false;

if ($action && $pdo) {
    try {
        switch ($action) {
            case 'reset_password': {
                $uid = (int) ($_POST['user_id'] ?? 0);
                $newPw = $_POST['new_password'] ?? '';
                if (!$uid) throw new RuntimeException('Missing user_id.');
                if (strlen($newPw) < 6) throw new RuntimeException('Password must be at least 6 characters.');
                $hash = password_hash($newPw, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = :h, status = 'active', updated_at = NOW() WHERE id = :id");
                $stmt->execute([':h' => $hash, ':id' => $uid]);
                if (!$stmt->rowCount()) throw new RuntimeException("No user with id $uid.");
                $flash = "Password reset for user #$uid. They are now active. New password: $newPw";
                $flashOk = true;
                break;
            }
            case 'set_status': {
                $uid = (int) ($_POST['user_id'] ?? 0);
                $st  = $_POST['status'] ?? 'active';
                if (!in_array($st, ['active', 'inactive'], true)) throw new RuntimeException('Bad status.');
                $stmt = $pdo->prepare("UPDATE users SET status = :s, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':s' => $st, ':id' => $uid]);
                $flash = "User #$uid status set to $st.";
                $flashOk = true;
                break;
            }
            case 'create_user': {
                $email    = trim($_POST['email'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $role     = $_POST['role'] ?? 'patient';
                $newPw    = $_POST['new_password'] ?? '';
                if (!$email || !$username) throw new RuntimeException('Email and username are required.');
                if (!in_array($role, ['admin', 'doctor', 'patient'], true)) throw new RuntimeException('Bad role.');
                if (strlen($newPw) < 6) throw new RuntimeException('Password must be at least 6 characters.');
                $hash = password_hash($newPw, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (email, username, password_hash, role, status, created_at, updated_at)
                     VALUES (:e, :u, :p, :r, 'active', NOW(), NOW())"
                );
                $stmt->execute([':e' => $email, ':u' => $username, ':p' => $hash, ':r' => $role]);
                $newId = $pdo->lastInsertId();
                $flash = "Created $role user #$newId — email: $email, username: $username, password: $newPw";
                $flashOk = true;
                break;
            }
            case 'run_schema': {
                $schemaFile = __DIR__ . '/database/schema.sql';
                if (!file_exists($schemaFile)) throw new RuntimeException("schema.sql not found at $schemaFile");
                $sql = file_get_contents($schemaFile);
                // crude split: most statements are separated by semicolon at end-of-line
                $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
                $ok = 0; $fail = 0; $errors = [];
                foreach ($statements as $stmt) {
                    if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) continue;
                    try { $pdo->exec($stmt); $ok++; }
                    catch (Throwable $e) { $fail++; $errors[] = substr($stmt, 0, 60) . ' -> ' . $e->getMessage(); }
                }
                $flash = "Schema run: $ok ok, $fail failed." . ($errors ? "\n\n" . implode("\n", array_slice($errors, 0, 10)) : '');
                $flashOk = $fail === 0;
                break;
            }
            default:
                throw new RuntimeException("Unknown action: $action");
        }
    } catch (Throwable $e) {
        $flash = 'ERROR: ' . $e->getMessage();
        $flashOk = false;
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Read-only diagnostics (only if DB connected)
$tables = [];
$users  = [];
if ($pdo) {
    try {
        $rows = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN, 0);
        foreach ($rows as $t) {
            $count = $pdo->query("SELECT COUNT(*) AS c FROM `$t`")->fetch();
            $tables[$t] = (int) $count['c'];
        }
    } catch (Throwable $e) {
        $dbError = $dbError ?? $e->getMessage();
    }
    if (in_array('users', array_keys($tables), true)) {
        $users = $pdo->query("SELECT id, email, username, role, status, last_login, created_at FROM users ORDER BY id ASC")->fetchAll();
    }
}

function fail(string $msg): void {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Setup error: $msg\n";
    exit;
}
function h(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Meditrack Setup / Diagnostic</title>
<style>
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 24px; line-height: 1.5; }
  .wrap { max-width: 1080px; margin: 0 auto; }
  h1 { color: #67e8f9; margin: 0 0 4px; }
  .sub { color: #94a3b8; margin: 0 0 24px; }
  .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 20px 24px; margin: 0 0 20px; }
  .card h2 { margin: 0 0 12px; font-size: 16px; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.05em; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #334155; }
  th { background: #0f172a; color: #94a3b8; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
  tr:hover td { background: #0f172a; }
  .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
  .pill-active   { background: #064e3b; color: #6ee7b7; }
  .pill-inactive { background: #7f1d1d; color: #fca5a5; }
  .pill-admin    { background: #172554; color: #93c5fd; }
  .pill-doctor   { background: #4a1d96; color: #c4b5fd; }
  .pill-patient  { background: #134e4a; color: #5eead4; }
  form { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; margin-top: 12px; }
  label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: #94a3b8; }
  input, select { background: #0f172a; border: 1px solid #475569; color: #e2e8f0; padding: 8px 10px; border-radius: 6px; font: inherit; min-width: 140px; }
  button { background: #0891b2; border: none; color: white; padding: 9px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; }
  button:hover { background: #0e7490; }
  button.danger { background: #b91c1c; }
  button.danger:hover { background: #991b1b; }
  .flash { padding: 14px 18px; border-radius: 10px; margin: 0 0 20px; white-space: pre-wrap; font-family: ui-monospace, monospace; font-size: 13px; }
  .flash-ok  { background: #064e3b; color: #d1fae5; border: 1px solid #047857; }
  .flash-err { background: #7f1d1d; color: #fee2e2; border: 1px solid #b91c1c; }
  .warn { background: #78350f; color: #fef3c7; border: 1px solid #b45309; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
  code { background: #0f172a; padding: 1px 6px; border-radius: 4px; color: #fbbf24; font-size: 12px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="wrap">

  <h1>Meditrack — Setup / Diagnostic</h1>
  <p class="sub">Bislig District Hospital · Consultation OPD Management System</p>

  <div class="warn">
    ⚠ <strong>DELETE THIS FILE</strong> from the server as soon as you are done. It exposes your database.
    <br>Once you fix the login problem and confirm everything works, remove <code>setup-diagnostic.php</code> via the cPanel File Manager.
  </div>

<?php if ($flash): ?>
  <div class="flash <?= $flashOk ? 'flash-ok' : 'flash-err' ?>"><?= h($flash) ?></div>
<?php endif; ?>

  <div class="card">
    <h2>Database connection</h2>
<?php if ($dbError): ?>
    <p style="color:#fca5a5;">❌ Connection failed: <code><?= h($dbError) ?></code></p>
    <p>Check <code>env.php</code> on the server. The host must be reachable and the credentials correct.</p>
<?php else: ?>
    <p>✅ Connected to <code><?= h($env['DB_NAME']) ?></code> on <code><?= h($env['DB_HOST']) ?></code> as <code><?= h($env['DB_USERNAME']) ?></code></p>
<?php endif; ?>
  </div>

<?php if ($pdo): ?>
  <div class="card">
    <h2>Tables · row counts</h2>
<?php if ($tables): ?>
    <table>
      <thead><tr><th>Table</th><th style="text-align:right">Rows</th></tr></thead>
      <tbody>
<?php foreach ($tables as $t => $c): ?>
        <tr><td><code><?= h($t) ?></code></td><td style="text-align:right"><?= $c ?></td></tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php else: ?>
    <p style="color:#fca5a5;">⚠ No tables in this database. Run the schema below.</p>
    <form method="post">
      <input type="hidden" name="token" value="<?= h(SETUP_TOKEN) ?>">
      <input type="hidden" name="action" value="run_schema">
      <button type="submit">Run database/schema.sql now</button>
    </form>
<?php endif; ?>
  </div>

<?php if ($users): ?>
  <div class="card">
    <h2>Users (<?= count($users) ?>)</h2>
    <table>
      <thead><tr>
        <th>ID</th><th>Email</th><th>Username</th><th>Role</th><th>Status</th><th>Last login</th><th>Created</th>
      </tr></thead>
      <tbody>
<?php foreach ($users as $u): ?>
        <tr>
          <td><?= (int) $u['id'] ?></td>
          <td><?= h($u['email']) ?></td>
          <td><?= h($u['username']) ?></td>
          <td><span class="pill pill-<?= h($u['role']) ?>"><?= h($u['role']) ?></span></td>
          <td><span class="pill pill-<?= h($u['status']) ?>"><?= h($u['status']) ?></span></td>
          <td><?= h($u['last_login'] ?: '—') ?></td>
          <td><?= h($u['created_at']) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="grid">
    <div class="card">
      <h2>Reset a password</h2>
      <p style="font-size:13px;color:#94a3b8;">Pick any user and set a new password. Sets status = active.</p>
      <form method="post">
        <input type="hidden" name="token" value="<?= h(SETUP_TOKEN) ?>">
        <input type="hidden" name="action" value="reset_password">
        <label>User
          <select name="user_id" required>
<?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>">#<?= (int)$u['id'] ?> — <?= h($u['email']) ?> (<?= h($u['role']) ?>)</option>
<?php endforeach; ?>
          </select>
        </label>
        <label>New password
          <input type="text" name="new_password" minlength="6" required placeholder="min 6 chars">
        </label>
        <button type="submit">Reset</button>
      </form>
    </div>

    <div class="card">
      <h2>Activate / deactivate</h2>
      <form method="post">
        <input type="hidden" name="token" value="<?= h(SETUP_TOKEN) ?>">
        <input type="hidden" name="action" value="set_status">
        <label>User
          <select name="user_id" required>
<?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>">#<?= (int)$u['id'] ?> — <?= h($u['email']) ?> (<?= h($u['status']) ?>)</option>
<?php endforeach; ?>
          </select>
        </label>
        <label>Status
          <select name="status">
            <option value="active">active</option>
            <option value="inactive">inactive</option>
          </select>
        </label>
        <button type="submit">Apply</button>
      </form>
    </div>
  </div>
<?php endif; ?>

  <div class="card">
    <h2>Create a user</h2>
    <p style="font-size:13px;color:#94a3b8;">Useful when the database is freshly imported and you need an admin account fast.</p>
    <form method="post">
      <input type="hidden" name="token" value="<?= h(SETUP_TOKEN) ?>">
      <input type="hidden" name="action" value="create_user">
      <label>Email <input type="email" name="email" required placeholder="admin@bislig.gov.ph"></label>
      <label>Username <input type="text" name="username" required placeholder="admin"></label>
      <label>Role
        <select name="role">
          <option value="admin">admin</option>
          <option value="doctor">doctor</option>
          <option value="patient">patient</option>
        </select>
      </label>
      <label>Password <input type="text" name="new_password" minlength="6" required placeholder="min 6 chars"></label>
      <button type="submit" class="danger">Create</button>
    </form>
  </div>
<?php endif; ?>

  <p style="text-align:center;color:#475569;font-size:12px;margin-top:32px;">
    setup-diagnostic.php · DELETE after use.
  </p>
</div>
</body>
</html>
