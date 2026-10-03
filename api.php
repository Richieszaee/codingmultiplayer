<?php
/**
 * CodeLive backend — PHP + SQLite replacement for the original Next.js + PostgreSQL API.
 * Needs PHP 8.0+ with the pdo_sqlite extension (enabled on practically every host).
 * The database is created automatically on the first request, in ./data/
 *
 * Open  api.php?r=/health  to check that your host is set up correctly.
 */
declare(strict_types=1);

const DATA_DIR              = __DIR__ . '/data';
const ONLINE_SECONDS        = 20;        // a participant counts as "online" if seen in the last N seconds
const MAX_FILE_BYTES        = 1048576;   // 1 MB per file
const MAX_FILES_PER_ROOM    = 300;
const MAX_WORKSPACES_PER_IP = 20;        // new workspaces per IP address per hour
const SLUG_CHARS            = 'abcdefghjkmnpqrstuvwxyz23456789';

const LANGUAGE_BY_EXT = [
    'js' => 'javascript', 'mjs' => 'javascript', 'cjs' => 'javascript', 'jsx' => 'javascript',
    'ts' => 'typescript', 'tsx' => 'typescript', 'html' => 'html', 'htm' => 'html', 'css' => 'css',
    'scss' => 'scss', 'json' => 'json', 'md' => 'markdown', 'markdown' => 'markdown', 'py' => 'python',
    'rb' => 'ruby', 'go' => 'go', 'rs' => 'rust', 'java' => 'java', 'kt' => 'kotlin', 'c' => 'c',
    'h' => 'c', 'cpp' => 'cpp', 'cc' => 'cpp', 'hpp' => 'cpp', 'cs' => 'csharp', 'php' => 'php',
    'sql' => 'sql', 'yml' => 'yaml', 'yaml' => 'yaml', 'xml' => 'xml', 'sh' => 'shell',
    'bash' => 'shell', 'zsh' => 'shell', 'txt' => 'plaintext', 'svg' => 'xml', 'env' => 'plaintext',
    'toml' => 'ini', 'ini' => 'ini', 'dockerfile' => 'dockerfile',
];

/** Starter files for every new workspace (copied from the original project). */
function seed_files(): array
{
    static $seeds = null;
    if ($seeds === null) {
        $seeds = json_decode(<<<'SEEDS_JSON_END'
[{"path":"README.md","content":"# __WORKSPACE_NAME__\n\nWelcome to **CodeLive** — a real VS Code-style editor that you can share.\n\n## Work together\n- Share this workspace link with anyone\n- Everyone types in the same files, live\n- Chat from the Live Share panel\n- Press **Run** or `Ctrl+Enter` to execute JavaScript\n- Open the **Preview** panel to render `index.html`\n\n## Try it\n1. Open `script.js` and change the heading\n2. Hit the green **Run** button\n3. Watch the live preview update\n\nHappy hacking.\n"},{"path":"index.html","content":"<!DOCTYPE html>\n<html lang=\"en\">\n  <head>\n    <meta charset=\"UTF-8\" />\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />\n    <title>__WORKSPACE_NAME__</title>\n    <link rel=\"stylesheet\" href=\"styles.css\" />\n  </head>\n  <body>\n    <main class=\"shell\">\n      <header>\n        <p class=\"eyebrow\">Built live in CodeLive</p>\n        <h1 id=\"title\">__WORKSPACE_NAME__</h1>\n        <p class=\"lede\">\n          A collaborative workspace in the browser. Edit this page with your team\n          and hit Run to see it instantly.\n        </p>\n      </header>\n\n      <section class=\"stats\">\n        <article>\n          <span id=\"count\">0</span>\n          <label>Team clicks</label>\n        </article>\n        <article>\n          <span id=\"clock\">--:--:--</span>\n          <label>Local time</label>\n        </article>\n        <article>\n          <span id=\"mood\">Ready</span>\n          <label>Status</label>\n        </article>\n      </section>\n\n      <div class=\"actions\">\n        <button id=\"bump\" type=\"button\">Add a click</button>\n        <button id=\"spark\" type=\"button\" class=\"ghost\">Spark idea</button>\n      </div>\n\n      <ul id=\"ideas\" class=\"ideas\"></ul>\n    </main>\n    <script src=\"script.js\"></script>\n  </body>\n</html>\n"},{"path":"styles.css","content":":root {\n  color-scheme: dark;\n  --bg: #07111f;\n  --card: rgba(15, 27, 46, 0.88);\n  --line: rgba(94, 160, 255, 0.18);\n  --text: #e8f1ff;\n  --muted: #93a4c0;\n  --accent: #4fc1ff;\n  --accent-2: #7c5cff;\n}\n\n* { box-sizing: border-box; }\n\nhtml, body {\n  margin: 0;\n  min-height: 100%;\n  font-family: \"Segoe UI\", system-ui, sans-serif;\n  background:\n    radial-gradient(1200px 500px at 10% -10%, rgba(80, 140, 255, 0.25), transparent),\n    radial-gradient(900px 400px at 100% 0%, rgba(124, 92, 255, 0.2), transparent),\n    var(--bg);\n  color: var(--text);\n}\n\n.shell {\n  max-width: 880px;\n  margin: 8vh auto;\n  padding: 40px;\n  background: var(--card);\n  border: 1px solid var(--line);\n  border-radius: 28px;\n  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.35);\n}\n\n.eyebrow {\n  text-transform: uppercase;\n  letter-spacing: 0.18em;\n  font-size: 12px;\n  color: var(--accent);\n  margin: 0 0 12px;\n}\n\nh1 {\n  font-size: clamp(2.2rem, 6vw, 4rem);\n  line-height: 1.05;\n  margin: 0 0 12px;\n}\n\n.lede {\n  color: var(--muted);\n  font-size: 18px;\n  max-width: 46ch;\n}\n\n.stats {\n  display: grid;\n  grid-template-columns: repeat(3, 1fr);\n  gap: 12px;\n  margin: 32px 0 20px;\n}\n\n.stats article {\n  padding: 16px;\n  border-radius: 16px;\n  border: 1px solid var(--line);\n  background: rgba(255, 255, 255, 0.02);\n}\n\n.stats span {\n  display: block;\n  font-size: 28px;\n  font-weight: 700;\n}\n\n.stats label {\n  color: var(--muted);\n  font-size: 12px;\n}\n\n.actions { display: flex; gap: 10px; flex-wrap: wrap; }\n\nbutton {\n  border: 0;\n  border-radius: 999px;\n  padding: 12px 18px;\n  background: linear-gradient(135deg, var(--accent), var(--accent-2));\n  color: #041018;\n  font-weight: 700;\n  cursor: pointer;\n}\n\nbutton.ghost {\n  background: transparent;\n  color: var(--text);\n  border: 1px solid var(--line);\n}\n\n.ideas {\n  list-style: none;\n  padding: 0;\n  margin: 24px 0 0;\n  display: grid;\n  gap: 8px;\n}\n\n.ideas li {\n  padding: 12px 14px;\n  border-radius: 12px;\n  background: rgba(79, 193, 255, 0.08);\n  border: 1px solid var(--line);\n}\n\n@media (max-width: 700px) {\n  .stats { grid-template-columns: 1fr; }\n  .shell { margin: 0; border-radius: 0; min-height: 100vh; }\n}\n"},{"path":"script.js","content":"const ideas = [\n  \"Add a dark/light theme toggle\",\n  \"Show who is currently editing\",\n  \"Render a live collaborative counter\",\n  \"Drop in a canvas doodle board\",\n  \"Build a tiny game in this preview\",\n];\n\nconst title = document.getElementById(\"title\");\nconst countEl = document.getElementById(\"count\");\nconst clockEl = document.getElementById(\"clock\");\nconst moodEl = document.getElementById(\"mood\");\nconst listEl = document.getElementById(\"ideas\");\n\nlet clicks = 0;\ntry {\n  clicks = Number(localStorage.getItem(\"codelive.clicks\") || 0);\n} catch {\n  clicks = 0;\n}\n\nfunction render() {\n  countEl.textContent = String(clicks);\n  moodEl.textContent = clicks === 0 ? \"Ready\" : clicks > 12 ? \"On fire\" : \"In flow\";\n}\n\ndocument.getElementById(\"bump\").addEventListener(\"click\", () => {\n  clicks += 1;\n  try {\n    localStorage.setItem(\"codelive.clicks\", String(clicks));\n  } catch {\n    /* sandboxed preview */\n  }\n  render();\n});\n\ndocument.getElementById(\"spark\").addEventListener(\"click\", () => {\n  const idea = ideas[Math.floor(Math.random() * ideas.length)];\n  const item = document.createElement(\"li\");\n  item.textContent = idea;\n  listEl.prepend(item);\n});\n\nsetInterval(() => {\n  clockEl.textContent = new Date().toLocaleTimeString();\n}, 1000);\n\nrender();\nconsole.log(\"Preview ready:\", title.textContent);\n"},{"path":"hello.js","content":"// Run this file in the terminal with the green play button\n// or type: node hello.js\n\nfunction greet(team) {\n  const hour = new Date().getHours();\n  const when = hour < 12 ? \"Good morning\" : hour < 18 ? \"Good afternoon\" : \"Good evening\";\n  return `${when}, ${team}. CodeLive is live.`;\n}\n\nconst numbers = [3, 6, 9, 12];\nconst total = numbers.reduce((sum, n) => sum + n, 0);\n\nconsole.log(greet(\"collaborators\"));\nconsole.log(\"Numbers:\", numbers.join(\", \"));\nconsole.log(\"Sum:\", total);\nconsole.log(\"2 ** 10 =\", 2 ** 10);\n"},{"path":"src/utils.js","content":"function slugify(value) {\n  return String(value)\n    .toLowerCase()\n    .trim()\n    .replace(/[^a-z0-9]+/g, \"-\")\n    .replace(/(^-|-$)/g, \"\");\n}\n\nfunction clamp(value, min, max) {\n  return Math.min(max, Math.max(min, value));\n}\n\nif (typeof module === \"undefined\") {\n  console.log(\"utils loaded\", slugify(\"Hello CodeLive\"), clamp(12, 0, 10));\n}\n"}]
SEEDS_JSON_END, true) ?: [];
    }
    return $seeds;
}

final class ApiError extends RuntimeException
{
    public function __construct(string $message, public int $status = 400)
    {
        parent::__construct($message);
    }
}

/* ───────────────────────────── helpers ───────────────────────────── */

function respond(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function now_iso(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
}

function body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new ApiError('Invalid JSON body', 400);
    }
    return $data;
}

/** Truncate to N characters without needing the mbstring extension. */
function cut(string $s, int $max): string
{
    return preg_match('/^.{0,' . $max . '}/us', $s, $m) === 1 ? $m[0] : substr($s, 0, $max);
}

function str_field(array $b, string $key, int $max, string $default = ''): string
{
    $v = $b[$key] ?? $default;
    if (!is_string($v)) {
        return $default;
    }
    return cut($v, $max);
}

function language_from_path(string $path): string
{
    $name = basename($path);
    if (strtolower($name) === 'dockerfile') {
        return 'dockerfile';
    }
    if (strpos($name, '.') === false) {
        return 'plaintext';
    }
    $ext = strtolower(substr($name, (int)strrpos($name, '.') + 1));
    return LANGUAGE_BY_EXT[$ext] ?? 'plaintext';
}

function normalize_path(string $input): string
{
    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $input)) as $p) {
        $p = trim($p);
        if ($p !== '' && $p !== '.' && $p !== '..') {
            $parts[] = $p;
        }
    }
    $path = implode('/', $parts);
    if (strlen($path) > 300 || preg_match('/[\x00-\x1f]/', $path)) {
        return '';
    }
    return $path;
}

function make_slug(int $length = 7): string
{
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= SLUG_CHARS[random_int(0, strlen(SLUG_CHARS) - 1)];
    }
    return $out;
}

const COLORS = ['#007acc', '#4ec9b0', '#c586c0', '#dcdcaa', '#ce9178', '#569cd6',
                '#9cdcfe', '#d7ba7d', '#b5cea8', '#f14c4c', '#c586c0', '#4fc1ff'];

/* ───────────────────────────── database ───────────────────────────── */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!extension_loaded('pdo_sqlite')) {
        throw new ApiError('This PHP install has no pdo_sqlite extension. Ask your host to enable it.', 500);
    }
    if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0755, true) && !is_dir(DATA_DIR)) {
        throw new ApiError('Cannot create the data/ folder. Make the app folder writable (chmod 755 or 775).', 500);
    }
    if (!is_writable(DATA_DIR)) {
        throw new ApiError('The data/ folder is not writable. Set its permissions to 755 or 775.', 500);
    }

    // Keep the database out of reach from the web: deny rules + an unguessable filename.
    $ht = DATA_DIR . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht,
            "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
    }
    if (!file_exists(DATA_DIR . '/index.html')) {
        @file_put_contents(DATA_DIR . '/index.html', '');
    }
    $cfg = DATA_DIR . '/config.php';
    if (!file_exists($cfg)) {
        @file_put_contents($cfg, "<?php return ['db' => 'codelive-" . bin2hex(random_bytes(12)) . ".sqlite'];\n");
    }
    $conf = @include $cfg;
    $file = is_array($conf) && isset($conf['db']) ? (string)$conf['db'] : 'codelive.sqlite';

    $pdo = new PDO('sqlite:' . DATA_DIR . '/' . $file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 8000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS workspaces (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL,
  creator TEXT
);
CREATE TABLE IF NOT EXISTS files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
  path TEXT NOT NULL,
  content TEXT NOT NULL DEFAULT '',
  language TEXT NOT NULL DEFAULT 'plaintext',
  version INTEGER NOT NULL DEFAULT 1,
  updated_by TEXT,
  updated_at TEXT NOT NULL,
  UNIQUE (workspace_id, path)
);
CREATE INDEX IF NOT EXISTS files_workspace_idx ON files(workspace_id);
CREATE TABLE IF NOT EXISTS participants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
  session_id TEXT NOT NULL,
  name TEXT NOT NULL,
  color TEXT NOT NULL,
  active_file_id INTEGER,
  cursor_line INTEGER NOT NULL DEFAULT 1,
  cursor_column INTEGER NOT NULL DEFAULT 1,
  last_seen TEXT NOT NULL,
  UNIQUE (workspace_id, session_id)
);
CREATE INDEX IF NOT EXISTS participants_workspace_idx ON participants(workspace_id);
CREATE TABLE IF NOT EXISTS chat_messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
  participant_name TEXT NOT NULL,
  participant_color TEXT NOT NULL,
  content TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS chat_workspace_idx ON chat_messages(workspace_id);
SQL);
    return $pdo;
}

function q(string $sql, array $args = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
}

/* ───────────────────────────── serializers ───────────────────────────── */

function file_out(array $r): array
{
    return [
        'id'        => (int)$r['id'],
        'path'      => $r['path'],
        'content'   => $r['content'],
        'language'  => $r['language'],
        'version'   => (int)$r['version'],
        'updatedBy' => $r['updated_by'],
        'updatedAt' => $r['updated_at'],
    ];
}

function participant_out(array $r, int $now): array
{
    $seen = (new DateTimeImmutable($r['last_seen']))->getTimestamp();
    return [
        'id'           => (int)$r['id'],
        'sessionId'    => $r['session_id'],
        'name'         => $r['name'],
        'color'        => $r['color'],
        'activeFileId' => $r['active_file_id'] === null ? null : (int)$r['active_file_id'],
        'cursorLine'   => (int)$r['cursor_line'],
        'cursorColumn' => (int)$r['cursor_column'],
        'lastSeen'     => $r['last_seen'],
        'online'       => ($now - $seen) < ONLINE_SECONDS,
    ];
}

function chat_out(array $r): array
{
    return [
        'id'               => (int)$r['id'],
        'participantName'  => $r['participant_name'],
        'participantColor' => $r['participant_color'],
        'content'          => $r['content'],
        'createdAt'        => $r['created_at'],
    ];
}

function workspace_by_slug(string $slug): ?array
{
    if (!preg_match('/^[a-z0-9-]{1,40}$/', $slug)) {
        return null;
    }
    $r = q('SELECT * FROM workspaces WHERE slug = ?', [$slug])->fetch();
    return $r ?: null;
}

function payload(array $ws): array
{
    $id = (int)$ws['id'];
    $files = array_map('file_out', q('SELECT * FROM files WHERE workspace_id = ?', [$id])->fetchAll());
    usort($files, fn($a, $b) => strcasecmp($a['path'], $b['path']));
    $now = time();
    $people = array_map(fn($r) => participant_out($r, $now),
        q('SELECT * FROM participants WHERE workspace_id = ? ORDER BY id', [$id])->fetchAll());
    $chat = array_map('chat_out', q(
        'SELECT * FROM (SELECT * FROM chat_messages WHERE workspace_id = ? ORDER BY id DESC LIMIT 80) ORDER BY id ASC',
        [$id])->fetchAll());
    return [
        'workspace' => [
            'id' => $id, 'name' => $ws['name'], 'slug' => $ws['slug'], 'createdAt' => $ws['created_at'],
        ],
        'files'        => $files,
        'participants' => $people,
        'chat'         => $chat,
    ];
}

function require_workspace(string $slug): array
{
    $ws = workspace_by_slug($slug);
    if (!$ws) {
        throw new ApiError('Workspace not found', 404);
    }
    return $ws;
}

/** Load a file by id, but only if it belongs to the workspace named in ?w= (the invite code acts as the key). */
function require_file(int $id): array
{
    $ws = workspace_by_slug(strtolower((string)($_GET['w'] ?? '')));
    if (!$ws) {
        throw new ApiError('Not found', 404);
    }
    $f = q('SELECT * FROM files WHERE id = ? AND workspace_id = ?', [$id, $ws['id']])->fetch();
    if (!$f) {
        throw new ApiError('Not found', 404);
    }
    return $f;
}

function is_unique_violation(PDOException $e): bool
{
    return str_contains($e->getMessage(), 'UNIQUE') || $e->getCode() === '23000';
}

/* ───────────────────────────── handlers ───────────────────────────── */

function create_workspace(): never
{
    $b = body();
    $name = trim(str_field($b, 'name', 60, 'Untitled Workspace')) ?: 'Untitled Workspace';
    $ipHash = sha1((string)($_SERVER['REMOTE_ADDR'] ?? 'x'));

    $since = (new DateTimeImmutable('-1 hour', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
    $recent = (int)q('SELECT COUNT(*) FROM workspaces WHERE creator = ? AND created_at > ?', [$ipHash, $since])->fetchColumn();
    if ($recent >= MAX_WORKSPACES_PER_IP) {
        throw new ApiError('Too many workspaces created from your address. Try again later.', 429);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $slug = make_slug();
        for ($i = 0; $i < 6 && workspace_by_slug($slug); $i++) {
            $slug = make_slug();
        }
        q('INSERT INTO workspaces (name, slug, created_at, creator) VALUES (?, ?, ?, ?)', [$name, $slug, now_iso(), $ipHash]);
        $wsId = (int)$pdo->lastInsertId();
        foreach (seed_files() as $seed) {
            q('INSERT INTO files (workspace_id, path, content, language, version, updated_at) VALUES (?, ?, ?, ?, 1, ?)', [
                $wsId, $seed['path'], str_replace('__WORKSPACE_NAME__', $name, $seed['content']),
                language_from_path($seed['path']), now_iso(),
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    respond(payload(require_workspace($slug)), 201);
}

function create_file(array $ws): never
{
    $b = body();
    $path = normalize_path((string)($b['path'] ?? ''));
    if ($path === '') {
        throw new ApiError('Path is required', 400);
    }
    $content = (string)($b['content'] ?? '');
    if (strlen($content) > MAX_FILE_BYTES) {
        throw new ApiError('File is too large (max 1 MB)', 413);
    }
    $count = (int)q('SELECT COUNT(*) FROM files WHERE workspace_id = ?', [$ws['id']])->fetchColumn();
    if ($count >= MAX_FILES_PER_ROOM) {
        throw new ApiError('This workspace has reached its file limit', 409);
    }
    try {
        q('INSERT INTO files (workspace_id, path, content, language, version, updated_at) VALUES (?, ?, ?, ?, 1, ?)',
            [$ws['id'], $path, $content, language_from_path($path), now_iso()]);
    } catch (PDOException $e) {
        if (is_unique_violation($e)) {
            throw new ApiError('A file with that path already exists', 409);
        }
        throw $e;
    }
    $row = q('SELECT * FROM files WHERE id = ?', [db()->lastInsertId()])->fetch();
    respond(file_out($row));
}

function sync(array $ws): never
{
    $b = body();
    $session = str_field($b, 'sessionId', 80);
    $name = trim(str_field($b, 'name', 40));
    if ($session === '' || $name === '') {
        throw new ApiError('sessionId and name are required', 400);
    }
    $activeFile = isset($b['activeFileId']) && is_numeric($b['activeFileId']) ? (int)$b['activeFileId'] : null;
    $line = isset($b['cursorLine']) && is_numeric($b['cursorLine']) ? max(1, (int)$b['cursorLine']) : null;
    $col  = isset($b['cursorColumn']) && is_numeric($b['cursorColumn']) ? max(1, (int)$b['cursorColumn']) : null;
    $color = str_field($b, 'color', 9);
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
        $color = '';
    }
    $wsId = (int)$ws['id'];

    $existing = q('SELECT * FROM participants WHERE workspace_id = ? AND session_id = ?', [$wsId, $session])->fetch();
    if ($existing) {
        q('UPDATE participants SET name = ?, active_file_id = ?, cursor_line = ?, cursor_column = ?, last_seen = ? WHERE id = ?', [
            $name,
            $activeFile ?? $existing['active_file_id'],
            $line ?? $existing['cursor_line'],
            $col ?? $existing['cursor_column'],
            now_iso(),
            $existing['id'],
        ]);
        $meId = (int)$existing['id'];
    } else {
        $n = (int)q('SELECT COUNT(*) FROM participants WHERE workspace_id = ?', [$wsId])->fetchColumn();
        q('INSERT INTO participants (workspace_id, session_id, name, color, active_file_id, cursor_line, cursor_column, last_seen) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            $wsId, $session, $name, $color ?: COLORS[$n % count(COLORS)], $activeFile, $line ?? 1, $col ?? 1, now_iso(),
        ]);
        $meId = (int)db()->lastInsertId();
    }
    $me = q('SELECT id, color, name FROM participants WHERE id = ?', [$meId])->fetch();
    respond(payload($ws) + ['me' => ['id' => (int)$me['id'], 'color' => $me['color'], 'name' => $me['name']]]);
}

function add_chat(array $ws): never
{
    $b = body();
    $content = trim(str_field($b, 'content', 2000));
    if ($content === '') {
        throw new ApiError('Message is required', 400);
    }
    $name = str_field($b, 'name', 40, 'Guest') ?: 'Guest';
    $color = str_field($b, 'color', 9, '#007acc');
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
        $color = '#007acc';
    }
    q('INSERT INTO chat_messages (workspace_id, participant_name, participant_color, content, created_at) VALUES (?, ?, ?, ?, ?)',
        [$ws['id'], $name, $color, $content, now_iso()]);
    $row = q('SELECT * FROM chat_messages WHERE id = ?', [db()->lastInsertId()])->fetch();
    respond(chat_out($row));
}

function update_file(int $id): never
{
    $file = require_file($id);
    $b = body();

    if (isset($b['path']) && is_string($b['path'])) {
        $path = normalize_path($b['path']);
        if ($path === '') {
            throw new ApiError('Invalid path', 400);
        }
        try {
            q('UPDATE files SET path = ?, language = ?, updated_at = ? WHERE id = ?', [$path, language_from_path($path), now_iso(), $id]);
        } catch (PDOException $e) {
            if (is_unique_violation($e)) {
                throw new ApiError('A file with that path already exists', 409);
            }
            throw $e;
        }
    } elseif (isset($b['content']) && is_string($b['content'])) {
        if (strlen($b['content']) > MAX_FILE_BYTES) {
            throw new ApiError('File is too large (max 1 MB)', 413);
        }
        $by = isset($b['updatedBy']) && is_string($b['updatedBy']) ? cut($b['updatedBy'], 40) : null;
        q('UPDATE files SET content = ?, version = version + 1, updated_by = ?, updated_at = ? WHERE id = ?',
            [$b['content'], $by, now_iso(), $id]);
    } else {
        throw new ApiError('content is required', 400);
    }
    respond(file_out(q('SELECT * FROM files WHERE id = ?', [$file['id']])->fetch()));
}

function delete_file(int $id): never
{
    $file = require_file($id);
    q('DELETE FROM files WHERE id = ?', [$file['id']]);
    respond(['ok' => true]);
}

function health(): never
{
    $info = [
        'ok'         => false,
        'php'        => PHP_VERSION,
        'pdo_sqlite' => extension_loaded('pdo_sqlite'),
        'data_dir'   => is_dir(DATA_DIR) ? (is_writable(DATA_DIR) ? 'writable' : 'NOT writable') : 'will be created on first use',
    ];
    try {
        db()->query('SELECT 1');
        $info['ok'] = true;
    } catch (Throwable $e) {
        $info['error'] = $e instanceof ApiError ? $e->getMessage() : 'Database error';
    }
    respond($info, $info['ok'] ? 200 : 500);
}

/* ───────────────────────────── router ───────────────────────────── */

try {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    // Some hosts block PUT/DELETE, so the frontend sends POST + ?m=PUT / ?m=DELETE.
    if ($method === 'POST' && in_array($_GET['m'] ?? '', ['PUT', 'DELETE'], true)) {
        $method = $_GET['m'];
    }
    $seg = array_values(array_filter(explode('/', (string)($_GET['r'] ?? '')), fn($s) => $s !== ''));
    $a = $seg[0] ?? '';
    $n = count($seg);

    if ($a === 'health' && $n === 1 && $method === 'GET') {
        health();
    }
    if ($a === 'workspaces') {
        if ($n === 1 && $method === 'GET') {
            respond(['count' => (int)q('SELECT COUNT(*) FROM workspaces')->fetchColumn()]);
        }
        if ($n === 1 && $method === 'POST') {
            create_workspace();
        }
        if ($n === 2 && $method === 'GET') {
            respond(payload(require_workspace(strtolower($seg[1]))));
        }
        if ($n === 3 && $method === 'POST') {
            $ws = require_workspace(strtolower($seg[1]));
            match ($seg[2]) {
                'files' => create_file($ws),
                'sync'  => sync($ws),
                'chat'  => add_chat($ws),
                default => throw new ApiError('Not found', 404),
            };
        }
    }
    if ($a === 'files' && $n === 2 && ctype_digit($seg[1])) {
        if ($method === 'PUT') {
            update_file((int)$seg[1]);
        }
        if ($method === 'DELETE') {
            delete_file((int)$seg[1]);
        }
    }
    throw new ApiError('Not found', 404);
} catch (ApiError $e) {
    respond(['error' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    error_log('CodeLive api.php: ' . $e);
    respond(['error' => 'Server error'], 500);
}
