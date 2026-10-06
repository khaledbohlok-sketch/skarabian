<?php
/**
 * SK Arabians — main configuration file.
 * Copy to config/config.php and fill in the values. Never commit config.php.
 */
return [
    'app' => [
        'name'        => 'SK Arabians',
        'portal_name' => 'SK Arabians Management System',
        // Public base URL without trailing slash, e.g. https://skarabian.com
        'url'         => 'https://skarabian.com',
        'env'         => 'production',          // production | staging | local
        'debug'       => false,                 // never true in production
        'timezone'    => 'Asia/Qatar',
        'default_lang'=> 'en',
        // 32-byte key, base64. Generate with:  php tools/keygen.php
        // Encrypts QID/passport/bank fields and signs the activity log. Keep a safe copy: lost key = lost data.
        'key'         => 'CHANGE_ME',
        // Staging protection: set a password to put the whole site behind HTTP basic auth (e.g. new.skarabian.com)
        'staging_user'     => null,
        'staging_password' => null,
        // Proxies whose X-Forwarded-For header is trusted (e.g. Cloudflare). Empty = use REMOTE_ADDR only.
        'trusted_proxies'  => [],
    ],

    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'cpaneluser_ska',
        'user'    => 'cpaneluser_ska',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'mail' => [
        'driver'     => 'smtp',                 // smtp | mail | log
        'host'       => 'smtpout.secureserver.net',
        'port'       => 465,
        'encryption' => 'ssl',                  // ssl | tls | none
        'username'   => 'no-reply@skarabian.com',
        'password'   => '',
        'from'       => 'no-reply@skarabian.com',
        'from_name'  => 'SK Arabians',
    ],

    // Optional WhatsApp Cloud API (Meta). Leave token empty to disable; wa.me share links still work.
    'whatsapp' => [
        'token'           => '',
        'phone_number_id' => '',
        'api_version'     => 'v20.0',
    ],

    'security' => [
        'session_timeout'   => 1800,   // seconds of inactivity (30 min)
        'lockout_attempts'  => 5,
        'lockout_minutes'   => 15,
        'captcha_after'     => 3,
        // How to detect the login country: 'header' (CF-IPCountry from Cloudflare), 'ipapi' (ip-api.com lookup), 'none'
        'geo_lookup'        => 'header',
        'hsts'              => true,
        'upload_max_mb'     => 15,
    ],

    'backup' => [
        'keep_days'       => 30,
        // Google Drive off-server copy using a service account (share the folder with the service account email)
        'gdrive_service_account_json' => '',   // absolute path to the JSON key file (outside public_html)
        'gdrive_folder_id'            => '',
        // Optional FTP off-server copy
        'ftp' => ['host' => '', 'user' => '', 'pass' => '', 'dir' => '/', 'ssl' => true],
        // Optional empty database used by the automatic restore test
        'restore_test_db' => '',
    ],

    'rates' => [
        'auto_update'  => true,
        // Must return {"rates": {"USD": 0.2747, ...}} relative to QAR
        'provider_url' => 'https://open.er-api.com/v6/latest/QAR',
    ],

    // The OLD portal database, read only, for the one-time import (tools/import_old.php). Remove after go-live.
    'old_db' => ['host' => 'localhost', 'name' => '', 'user' => '', 'pass' => ''],

    // Absolute path to storage (outside public_html). Default: <app root>/storage
    'storage_path' => null,
];
