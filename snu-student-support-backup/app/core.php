<?php
declare(strict_types=1);
$config = require __DIR__ . '/config.php';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
if ($config['force_https'] && !$isHttps) {
    http_response_code(403); exit('HTTPS is required for this deployment.');
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
session_name('SNU_SUPPORT_SESSION');
session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>$isHttps, 'httponly'=>true, 'samesite'=>'Lax']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
try {
    $db = new PDO('mysql:host='.$config['db_host'].';port='.$config['db_port'].';dbname='.$config['db_name'].';charset=utf8mb4', $config['db_user'], $config['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
} catch (PDOException $e) {
    http_response_code(503); exit('Database unavailable. Import sql/schema.sql and configure app/config.php (or environment variables).');
}
function db(): PDO { global $db; return $db; }
function q(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function one(string $sql, array $params=[]): ?array { $r=q($sql,$params)->fetch(); return $r?:null; }
function all(string $sql, array $params=[]): array { return q($sql,$params)->fetchAll(); }
function e(?string $s): string { return htmlspecialchars($s??'', ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function url(string $page, array $params=[]): string { return 'index.php?'.http_build_query(['page'=>$page]+$params); }
function redirect(string $page, array $params=[]): never { header('Location: '.url($page,$params),true,303); exit; }
function csrf(): string { return $_SESSION['csrf']??=bin2hex(random_bytes(32)); }
function token(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function check_csrf(): void { if (!hash_equals(csrf(),(string)($_POST['csrf']??''))) { http_response_code(419); exit('Invalid or expired request token. Please reload the page.'); } }
function flash(string $type, string $message): void { $_SESSION['flash']=['type'=>$type,'message'=>$message]; }
function current_admin(): ?array { return isset($_SESSION['admin_id']) ? one('SELECT id, username FROM admins WHERE id=?',[(int)$_SESSION['admin_id']]):null; }
function require_admin(): void { if (!current_admin()) redirect('login'); }
function is_post(): bool { return $_SERVER['REQUEST_METHOD']==='POST'; }
function val(string $key, int $max=150, bool $required=true): string { $v=trim((string)($_POST[$key]??'')); if (($required && $v==='') || mb_strlen($v)>$max) throw new RuntimeException(ucwords(str_replace('_',' ',$key)).' is required and must be at most '.$max.' characters.'); return $v; }
function enum_value(string $key, array $allowed): string { $v=(string)($_POST[$key]??''); if(!in_array($v,$allowed,true)) throw new RuntimeException('Invalid value for '.str_replace('_',' ',$key).'.'); return $v; }
function record_id(): int { $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT); if(!$id || $id<1) {http_response_code(404);exit('Record not found.');} return $id; }
function date_value(string $key, bool $optional=false): ?string { $v=trim((string)($_POST[$key]??'')); if($optional && $v==='') return null; $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v); if(!$d||$d->format('Y-m-d')!==$v) throw new RuntimeException('Invalid date for '.$key); return $v; }
function student(int $id): ?array { return one('SELECT s.*,l.id laptop_id,l.brand,l.accessories_notes FROM students s LEFT JOIN laptops l ON l.student_id=s.id WHERE s.id=? AND s.deleted_at IS NULL',[$id]); }
function support(int $id): ?array { return one('SELECT r.*,s.student_number,s.student_name,l.brand,b.drive_c,b.drive_d,b.desktop,b.documents FROM support_records r JOIN students s ON s.id=r.student_id JOIN laptops l ON l.id=r.laptop_id LEFT JOIN backup_records b ON b.support_id=r.id WHERE r.id=? AND r.deleted_at IS NULL',[$id]); }
function nav_active(string $name, string $page): string { return $name===$page?'active':''; }
