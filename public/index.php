<?php
/**
 * Front controller. On cPanel, upload the contents of /public into public_html and the rest of the project
 * into a folder OUTSIDE public_html (e.g. /home/USER/skarabian). Then set APP_PATH below if needed.
 */
$appPath = is_file(__DIR__ . '/../app/bootstrap.php') ? dirname(__DIR__) : dirname(__DIR__) . '/skarabian';
// $appPath = '/home/CPANELUSER/skarabian';

require $appPath . '/app/bootstrap.php';

App\Core\Kernel::handle();
