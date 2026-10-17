<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], string $layout = 'portal'): never
    {
        Response::html(View::render($template, $data, $layout));
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    protected function redirect(string $to): never
    {
        Response::redirect($to);
    }

    protected function back(string $fallback = '/portal'): never
    {
        Response::back($fallback);
    }
}
