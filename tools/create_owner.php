<?php
/**
 * Creates the first Owner account (CLI). The password must be changed and 2FA set up at first login.
 *   php tools/create_owner.php "Full Name" username email@example.com
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Services\Passwords;

[$_, $name, $username, $email] = array_pad($argv, 4, null);
if (!$name || !$username || !$email) {
    fwrite(STDERR, "Usage: php tools/create_owner.php \"Full Name\" username email\n");
    exit(1);
}
$roleId = DB::value("SELECT id FROM roles WHERE slug = 'owner'");
if (!$roleId) {
    fwrite(STDERR, "Run php tools/migrate.php first.\n");
    exit(1);
}
$password = Passwords::generate();
DB::insert('users', [
    'username' => $username, 'email' => $email, 'name' => $name,
    'password_hash' => Passwords::hash($password), 'role_id' => $roleId,
    'status' => 'active', 'must_change_password' => 1,
]);
echo "Owner account created.\nUsername: $username\nTemporary password: $password\n";
echo "You will be asked to change it and to set up 2-factor authentication at first login.\n";
