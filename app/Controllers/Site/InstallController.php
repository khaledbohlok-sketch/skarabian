<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Migrator;
use App\Services\Passwords;
use App\Services\Seeder;

/**
 * One-time web installer for hosting without a command line: creates the database tables and the first Owner.
 * Only works while there is no Owner account, and only for someone who can read config.php (they must paste
 * the app key). After the Owner exists this page answers 404.
 */
class InstallController extends Controller
{
    /** PHP extensions the system cannot run without (without sodium nothing can be encrypted), and those that
     * single features need (docs/INSTALL.md, step 1): a missing one of these is only a warning. */
    private const EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'sodium', 'iconv'];
    private const FEATURE_EXTENSIONS = ['gd' => 'photos', 'fileinfo' => 'uploads', 'zip' => 'backups, Excel', 'curl' => 'exchange rates, WhatsApp, Google Drive'];

    public function index(): void
    {
        if (!$this->freshDatabase() || $this->ownerExists()) {
            Response::notFound();
        }
        $errors = [];
        $done = null;
        $missing = array_values(array_filter(self::EXTENSIONS, fn ($x) => !extension_loaded($x)));
        $key = (string) Config::get('app.key', '');
        $raw = base64_decode($key, true);
        $keyOk = $raw !== false && strlen($raw) === 32;
        if (Request::isPost()) {
            if ($missing) {
                $errors['php'] = 'Turn on these PHP extensions first: ' . implode(', ', $missing) . '.';
            }
            if ($key === '' || $key === 'CHANGE_ME') {
                $errors['key'] = 'Set app.key in config/config.php first (php tools/keygen.php prints one).';
            } elseif (!$keyOk) {
                // Checked here: a damaged key only fails later, when the first value is encrypted
                $errors['key'] = 'The app key in config/config.php is not a valid key. Replace its whole line with the one shown above.';
            } elseif (!hash_equals($key, trim((string) ($_POST['app_key'] ?? '')))) {
                $errors['key'] = 'This is not the key in config/config.php.';
                usleep(800000);
            }
            $name = trim((string) Request::post('name'));
            $username = strtolower(trim((string) Request::post('username')));
            $email = trim((string) Request::post('email'));
            $password = (string) ($_POST['password'] ?? '');
            if ($name === '') {
                $errors['name'] = 'Required.';
            }
            if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) {
                $errors['username'] = '3–60 characters: lowercase letters, numbers, dot, dash or underscore.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Enter a valid email.';
            }
            if ($err = Passwords::validate($password, [$username, $name, explode('@', $email)[0]])) {
                $errors['password'] = __($err);
            }
            if (!$errors) {
                @set_time_limit(0);
                try {
                    $log = array_merge(Migrator::run(), array_map(fn ($l) => 'seeded ' . $l, Seeder::run()));
                    if (!$this->ownerExists()) {
                        DB::insert('users', [
                            'username' => $username, 'email' => $email, 'name' => $name, 'password_hash' => Passwords::hash($password),
                            'role_id' => DB::value("SELECT id FROM roles WHERE slug = 'owner'"), 'status' => 'active', 'must_change_password' => 0,
                        ]);
                    }
                    $done = $log;
                } catch (\Throwable $e) {
                    \App\Core\ErrorHandler::log($e);
                    $errors['db'] = 'The database could not be set up: ' . $e->getMessage() . '. Check the db settings in config/config.php.';
                }
            }
        }
        $suggest = $keyOk ? null : base64_encode(random_bytes(32));
        $features = array_filter(self::FEATURE_EXTENSIONS, fn ($x) => !extension_loaded($x), ARRAY_FILTER_USE_KEY);
        $this->view('site/install', ['title' => 'Install', 'errors' => $errors, 'done' => $done, 'suggest' => $suggest, 'missing' => $missing, 'features' => $features], 'auth');
    }

    /**
     * Only a reachable, empty database may be installed: once any migration was recorded, or when the database
     * cannot be reached / answers with any other error, the page does not exist (it never renders because the DB is down).
     */
    private function freshDatabase(): bool
    {
        try {
            return (int) DB::value('SELECT COUNT(*) FROM schema_migrations') === 0;
        } catch (\PDOException $e) {
            // 42S02 = table does not exist: tables not created yet
            return $e->getCode() === '42S02';
        } catch (\Throwable) {
            return false;
        }
    }

    private function ownerExists(): bool
    {
        try {
            return (bool) DB::value("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' LIMIT 1");
        } catch (\Throwable) {
            return false; // tables not created yet
        }
    }
}
