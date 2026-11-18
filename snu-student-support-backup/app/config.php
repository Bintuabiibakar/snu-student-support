<?php
declare(strict_types=1);
// Set these environment variables in production. Local XAMPP defaults are for development only.
return [
    'db_host' => getenv('SNU_DB_HOST') ?: '127.0.0.1',
    'db_port' => getenv('SNU_DB_PORT') ?: '3306',
    'db_name' => getenv('SNU_DB_NAME') ?: 'snu_student_support',
    'db_user' => getenv('SNU_DB_USER') ?: 'root',
    'db_pass' => getenv('SNU_DB_PASS') ?: '',
    'force_https' => filter_var(getenv('SNU_FORCE_HTTPS') ?: 'false', FILTER_VALIDATE_BOOLEAN),
];
