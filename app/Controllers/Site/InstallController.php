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
    public function index(): void
    {
        if ($this->ownerExists()) {
            Response::notFound();
        }
        $errors = [];
        $done = null;
        if (Request::isPost()) {
            $key = (string) Config::get('app.key', '');
            if ($key === '' || $key === 'CHANGE_ME') {
                $errors['key'] = 'Set app.key in config/config.php first (php tools/keygen.php prints one).';
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
        $key = (string) Config::get('app.key', '');
        $suggest = $key === '' || $key === 'CHANGE_ME' ? base64_encode(random_bytes(32)) : null;
        $this->view('site/install', ['title' => 'Install', 'errors' => $errors, 'done' => $done, 'suggest' => $suggest], 'auth');
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
