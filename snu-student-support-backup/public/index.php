<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/core.php';
$page=(string)($_GET['page']??'dashboard');
$public=['login','register'];
$hasAdmin=(int)q('SELECT COUNT(*) FROM admins')->fetchColumn()!==0;
if (!$hasAdmin && $page!=='register') redirect('register');
if ($hasAdmin && $page==='register') redirect(current_admin()?'dashboard':'login');
if (!in_array($page,$public,true)) require_admin();
$error='';
$types=['Hardware Only','Software Only','Both Hardware and Software'];
$statuses=['Pending','In Progress','Done'];
$backs=['Yes','No','Not Needed'];
$accessories=['Charger','Laptop Bag','Mouse','Keyboard','Other'];
$hardware=['Keyboard Problems','Screen Problems','Battery Problems','Charging Problems','RAM Problems','SSD / Hard Disk Problems'];
$software=['Windows Installation','Windows Errors','Driver Installation','Software Installation','Virus Problems','Data Backup'];
if (is_post()) {
    check_csrf();
    try {
        if ($page==='register') {
            if ($hasAdmin) throw new RuntimeException('Registration has been closed.');
            $u=val('username',60); $p=(string)($_POST['password']??'');
            if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/',$u)) throw new RuntimeException('Username must be 3–60 characters, using letters, digits, underscore, dot or hyphen.');
            if (strlen($p)<12) throw new RuntimeException('Password must have at least 12 characters.');
            if ($p!==($_POST['confirm_password']??null)) throw new RuntimeException('Passwords do not match.');
            q('INSERT INTO admins (id,username,password_hash) VALUES (1,?,?)',[$u,password_hash($p,PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['admin_id']=1; flash('success','Administrator account created.'); redirect('dashboard');
        }
        if ($page==='login') {
            $now=time(); $block=(int)($_SESSION['blocked_until']??0);
            if ($block>$now) throw new RuntimeException('Too many attempts. Try again in '.ceil(($block-$now)/60).' minute(s).');
            $admin=one('SELECT * FROM admins WHERE username=?',[trim((string)($_POST['username']??''))]);
            if (!$admin || !password_verify((string)($_POST['password']??''),$admin['password_hash'])) {
                $_SESSION['login_failures']=(int)($_SESSION['login_failures']??0)+1;
                if ($_SESSION['login_failures']>=5) { $_SESSION['blocked_until']=$now+900;$_SESSION['login_failures']=0; }
                throw new RuntimeException('Incorrect username or password.');
            }
            session_regenerate_id(true); $_SESSION['admin_id']=1; unset($_SESSION['login_failures'],$_SESSION['blocked_until']); redirect('dashboard');
        }
        if ($page==='logout') { $_SESSION=[];session_regenerate_id(true);flash('success','You have logged out.');redirect('login'); }
        if ($page==='student-form') {
            $id=isset($_GET['id'])?record_id():null;
            if($id && !student($id)) throw new RuntimeException('Student does not exist.');
            $number=val('student_number',40);$name=val('student_name');$phone=val('phone_number',35);$dept=val('department',100);$cls=val('class_semester',100);$brand=val('brand',80);$notes=val('accessories_notes',2000,false);
            $selected=(array)($_POST['accessories']??[]);
            foreach($selected as $a) if(!in_array($a,$accessories,true)) throw new RuntimeException('Invalid accessory.');
            db()->beginTransaction();
            if($id) {q('UPDATE students SET student_number=?,student_name=?,phone_number=?,department=?,class_semester=? WHERE id=?',[$number,$name,$phone,$dept,$cls,$id]); q('UPDATE laptops SET brand=?,accessories_notes=? WHERE student_id=?',[$brand,$notes,$id]);$laptop=one('SELECT id FROM laptops WHERE student_id=?',[$id]);$laptopId=(int)$laptop['id'];}
            else {q('INSERT INTO students (student_number,student_name,phone_number,department,class_semester) VALUES (?,?,?,?,?)',[$number,$name,$phone,$dept,$cls]);$id=(int)db()->lastInsertId();q('INSERT INTO laptops (student_id,brand,accessories_notes) VALUES (?,?,?)',[$id,$brand,$notes]);$laptopId=(int)db()->lastInsertId();}
            q('DELETE FROM laptop_accessories WHERE laptop_id=?',[$laptopId]);
            foreach(array_unique($selected) as $a) q('INSERT INTO laptop_accessories (laptop_id,accessory) VALUES (?,?)',[$laptopId,$a]);
            db()->commit();flash('success','Student and laptop saved.');redirect('student',['id'=>$id]);
        }
        if ($page==='student-delete') {
            $id=record_id();if(!student($id))throw new RuntimeException('Student does not exist.');
            q('UPDATE students SET deleted_at=NOW() WHERE id=?',[$id]);flash('success','Student archived. Historical support records are retained.');redirect('students');
        }
        if ($page==='support-form') {
            $id=isset($_GET['id'])?record_id():null;
            $old=$id?support($id):null;if($id && !$old)throw new RuntimeException('Support record not found.');
            $sid=filter_var($_POST['student_id']??null,FILTER_VALIDATE_INT);$s=$sid?student((int)$sid):null;
            if(!$s||!$s['laptop_id'])throw new RuntimeException('Select a registered student with a laptop.');
            if($old && (int)$old['student_id']!==(int)$sid)throw new RuntimeException('Student cannot be changed on an existing support record.');
            $date=date_value('support_date');$type=enum_value('problem_type',$types);$status=enum_value('work_status',$statuses);$desc=val('problem_description',10000);
            $cats=(array)($_POST['problem_categories']??[]);foreach($cats as $c)if(!in_array($c,array_merge($hardware,$software),true))throw new RuntimeException('Invalid problem category.');
            $cats=array_values(array_unique($cats));
            $completion=$status==='Done'?date_value('completion_date',true):null;
            if($status==='Done' && !$completion)$completion=date('Y-m-d');
            if($completion && $completion<$date)throw new RuntimeException('Completion date must not precede support date.');
            $b=[];foreach(['drive_c','drive_d','desktop','documents'] as $field)$b[$field]=enum_value($field,$backs);
            db()->beginTransaction();
            if($id)q('UPDATE support_records SET support_date=?,problem_type=?,problem_categories=?,problem_description=?,work_status=?,completion_date=? WHERE id=?',[$date,$type,json_encode($cats),$desc,$status,$completion,$id]);
            else {q('INSERT INTO support_records (student_id,laptop_id,support_date,problem_type,problem_categories,problem_description,work_status,completion_date) VALUES (?,?,?,?,?,?,?,?)',[$sid,$s['laptop_id'],$date,$type,json_encode($cats),$desc,$status,$completion]);$id=(int)db()->lastInsertId();}
            q('INSERT INTO backup_records (support_id,drive_c,drive_d,desktop,documents) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE drive_c=VALUES(drive_c),drive_d=VALUES(drive_d),desktop=VALUES(desktop),documents=VALUES(documents)',[$id,$b['drive_c'],$b['drive_d'],$b['desktop'],$b['documents']]);
            db()->commit();flash('success','Support record saved.');redirect('support',['id'=>$id]);
        }
        if ($page==='support-delete') {
            $id=record_id();if(!support($id))throw new RuntimeException('Support record does not exist.');
            q('UPDATE support_records SET deleted_at=NOW() WHERE id=?',[$id]);flash('success','Support record archived (not permanently erased).');redirect('supports');
        }
    } catch (PDOException $ex) {
        if(db()->inTransaction())db()->rollBack();
        $error=$ex->getCode()==='23000'?'This student number or username is already in use.':'The database could not save the record. Check your input.';
    } catch (Throwable $ex) {
        if(db()->inTransaction())db()->rollBack();$error=$ex->getMessage();
    }
}
if(in_array($page,['student-delete','support-delete','logout'],true) && !is_post()){http_response_code(405);exit('Method not allowed.');}
function select_field(string $name,array $options,string $selected=''): void {
    echo '<select class="input" id="'.e($name).'" name="'.e($name).'" required>';
    foreach($options as $o)echo '<option value="'.e($o).'" '.($o===$selected?'selected':'').'>'.e($o).'</option>';
    echo '</select>';
}
function form_value(string $key,?array $record=null,string $default=''): string {return e((string)($_POST[$key]??$record[$key]??$default));}
function status_pill(string $status): string {return '<span class="pill '.($status==='Done'?'done':($status==='In Progress'?'progress':'pending')).'">'.e($status).'</span>';}
function option_badge(string $v): string {return '<span class="pill neutral">'.e($v).'</span>';}
function page_title(string $title,string $subtitle=''): void { echo '<div class="heading"><div><h1>'.e($title).'</h1>'.($subtitle?'<p>'.e($subtitle).'</p>':'').'</div></div>'; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(ucwords(str_replace('-',' ',$page)))?> · SNU Support</title><link rel="stylesheet" href="assets/style.css"></head><body>
<?php if(!in_array($page,$public,true)): ?>
<div class="app"><aside class="sidebar"><a class="brand" href="<?=url('dashboard')?>"><span class="brand-icon">S</span><span>SNU <strong>Support</strong><small>Student Support Team</small></span></a>
<nav class="nav"><span class="nav-label">WORKSPACE</span>
<a class="<?=nav_active('dashboard',$page)?>" href="<?=url('dashboard')?>">▦ &nbsp; Dashboard</a>
<a class="<?=in_array($page,['students','student','student-form'],true)?'active':''?>" href="<?=url('students')?>">♙ &nbsp; Students</a>
<a class="<?=in_array($page,['supports','support','support-form'],true)?'active':''?>" href="<?=url('supports')?>">⚒ &nbsp; Support Records</a>
<a href="<?=url('student-form')?>">＋ &nbsp; Add Student</a>
</nav><div class="sidebar-foot"><div class="muted small">Signed in as</div><strong><?=e(current_admin()['username']??'Admin')?></strong><form method="post" action="<?=url('logout')?>"><?=token()?><button class="logout" type="submit">Log out →</button></form></div></aside>
<div class="mainwrap"><header class="topbar"><button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false">☰</button><span>SNU Student Support Team</span><span class="topbar-right">Administrator</span></header><main class="content">
<?php else: ?><div class="auth-shell"><div class="auth-container"><div class="auth-logo">S</div><h1>SNU Student Support Team</h1><p class="muted">Computer Support Management</p><?php endif; ?>
<?php if(isset($_SESSION['flash'])): $f=$_SESSION['flash'];unset($_SESSION['flash']);?><div class="notice <?=e($f['type'])?>"><?=e($f['message'])?></div><?php endif; ?>
<?php if($error): ?><div class="notice error"><?=e($error)?></div><?php endif; ?>
<?php
switch($page) {
case 'register': ?>
<section class="panel auth-card"><h2>Set up administrator</h2><p class="muted">Only one admin can be created. Registration closes automatically afterwards.</p><form method="post"><?=token()?><label>Username<input class="input" name="username" required minlength="3" maxlength="60" autocomplete="username" value="<?=form_value('username')?>"></label><label>Password (12+ characters)<input class="input" type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Confirm password<input class="input" type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></label><button class="btn primary full">Create admin account</button></form></section>
<?php break;
case 'login': ?>
<section class="panel auth-card"><h2>Administrator login</h2><p class="muted">Enter your credentials to access student records.</p><form method="post"><?=token()?><label>Username<input class="input" name="username" required autocomplete="username" value="<?=form_value('username')?>"></label><label>Password<input class="input" type="password" name="password" required autocomplete="current-password"></label><button class="btn primary full">Sign in</button></form></section>
<?php break;
case 'dashboard':
$stats=one("SELECT (SELECT COUNT(*) FROM students WHERE deleted_at IS NULL) students, (SELECT COUNT(*) FROM laptops l JOIN students s ON s.id=l.student_id WHERE s.deleted_at IS NULL) laptops, (SELECT COUNT(*) FROM support_records WHERE deleted_at IS NULL AND work_status='Pending') pending, (SELECT COUNT(*) FROM support_records WHERE deleted_at IS NULL AND work_status='In Progress') in_progress, (SELECT COUNT(*) FROM support_records WHERE deleted_at IS NULL AND work_status='Done') done, (SELECT COUNT(*) FROM support_records WHERE deleted_at IS NULL AND problem_type IN ('Hardware Only','Both Hardware and Software')) hardware, (SELECT COUNT(*) FROM support_records WHERE deleted_at IS NULL AND problem_type IN ('Software Only','Both Hardware and Software')) software");
page_title('Dashboard','Overview of your students, laptops and repair activities.'); ?>
<div class="actions"><a class="btn primary" href="<?=url('student-form')?>">＋ Add Student</a><a class="btn" href="<?=url('support-form')?>">＋ New Support Record</a></div>
<div class="stat-grid"><?php foreach([['Total Students','students','◉'],['Total Laptops','laptops','▣'],['Pending Repairs','pending','◷'],['In Progress','in_progress','↻'],['Completed (Done)','done','✓'],['Hardware Problems','hardware','⚒'],['Software Problems','software','⌘']] as [$label,$key,$symbol]): ?><div class="stat panel"><div class="stat-symbol"><?=$symbol?></div><span class="muted"><?=e($label)?></span><strong><?=number_format((int)$stats[$key])?></strong></div><?php endforeach; ?></div>
<section class="panel"><div class="section-head"><h2>Recent support activity</h2><a href="<?=url('supports')?>">View all →</a></div><?php $rows=all('SELECT r.*,s.student_number,s.student_name FROM support_records r JOIN students s ON s.id=r.student_id WHERE r.deleted_at IS NULL ORDER BY r.support_date DESC,r.id DESC LIMIT 8'); include __DIR__.'/../app/partials/support_table.php';?></section>
<?php break;
case 'students':
page_title('Students','Search student records and filter by laptop and latest support status.');
$search=trim((string)($_GET['search']??''));$dept=trim((string)($_GET['department']??''));$brand=trim((string)($_GET['brand']??''));$type=(string)($_GET['problem_type']??'');$status=(string)($_GET['work_status']??'');
$departments=all('SELECT DISTINCT department FROM students WHERE deleted_at IS NULL ORDER BY department');$brands=all('SELECT DISTINCT brand FROM laptops ORDER BY brand');
$where=['s.deleted_at IS NULL'];$params=[];
if($search!==''){$where[]='(s.student_name LIKE ? OR s.student_number LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%';}
if($dept!==''){$where[]='s.department=?';$params[]=$dept;}if($brand!==''){$where[]='l.brand=?';$params[]=$brand;}
if(in_array($type,$types,true)){$where[]='r.problem_type=?';$params[]=$type;}
if(in_array($status,$statuses,true)){$where[]='r.work_status=?';$params[]=$status;}
$rows=all('SELECT s.*,l.brand,r.problem_type,r.work_status,r.id support_id FROM students s LEFT JOIN laptops l ON l.student_id=s.id LEFT JOIN support_records r ON r.id=(SELECT r2.id FROM support_records r2 WHERE r2.student_id=s.id AND r2.deleted_at IS NULL ORDER BY r2.support_date DESC,r2.id DESC LIMIT 1) WHERE '.implode(' AND ',$where).' ORDER BY s.created_at DESC,s.id DESC LIMIT 500',$params);
?>
<div class="actions"><a class="btn primary" href="<?=url('student-form')?>">＋ Add Student</a></div><section class="panel"><form method="get" class="filters"><input type="hidden" name="page" value="students"><input class="input" name="search" placeholder="Name or student number" value="<?=e($search)?>"><select class="input" name="department"><option value="">All Departments</option><?php foreach($departments as $x):?><option <?=($dept===$x['department']?'selected':'')?> value="<?=e($x['department'])?>"><?=e($x['department'])?></option><?php endforeach;?></select><select class="input" name="brand"><option value="">All Brands</option><?php foreach($brands as $x):?><option <?=($brand===$x['brand']?'selected':'')?> value="<?=e($x['brand'])?>"><?=e($x['brand'])?></option><?php endforeach;?></select><select class="input" name="problem_type"><option value="">All Problem Types</option><?php foreach($types as $x):?><option <?=($type===$x?'selected':'')?>><?=e($x)?></option><?php endforeach;?></select><select class="input" name="work_status"><option value="">All Statuses</option><?php foreach($statuses as $x):?><option <?=($status===$x?'selected':'')?>><?=e($x)?></option><?php endforeach;?></select><button class="btn primary">Apply Filters</button><a class="btn" href="<?=url('students')?>">Reset</a></form><div class="section-head"><h2>Student directory</h2><span class="muted"><?=count($rows)?> results (max 500)</span></div><div class="table-scroll"><table><thead><tr><th>Student Number</th><th>Name</th><th>Phone</th><th>Department</th><th>Laptop Brand</th><th>Problem Type</th><th>Work Status</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['student_number'])?></td><td><strong><?=e($r['student_name'])?></strong></td><td><?=e($r['phone_number'])?></td><td><?=e($r['department'])?></td><td><?=e($r['brand']??'—')?></td><td><?=e($r['problem_type']??'—')?></td><td><?=isset($r['work_status'])?status_pill($r['work_status']):'—'?></td><td class="table-actions"><a href="<?=url('student',['id'=>$r['id']])?>">View</a><a href="<?=url('student-form',['id'=>$r['id']])?>">Edit</a><form method="post" action="<?=url('student-delete',['id'=>$r['id']])?>" class="confirm-delete" data-message="Archive this student? Their support history will remain in the database."><?=token()?><button class="text-danger">Delete</button></form></td></tr><?php endforeach;?></tbody></table><?php if(!$rows):?><div class="empty">No students match the selected filters.</div><?php endif;?></div></section>
<?php break;
case 'student-form':
$id=isset($_GET['id'])?record_id():null;$r=$id?student($id):null;if($id&&!$r){http_response_code(404);echo '<div class="empty">Student not found.</div>';break;}
$selected=$r?array_column(all('SELECT accessory FROM laptop_accessories WHERE laptop_id=?',[$r['laptop_id']]),'accessory'):[];
page_title($id?'Edit Student':'Register Student','Save student and laptop details together.'); ?>
<section class="panel form-panel"><form method="post"><?=token()?><h2>Student details</h2><div class="form-grid"><?php foreach([['student_number','Student Number'],['student_name','Student Name'],['phone_number','Phone Number'],['department','Department'],['class_semester','Class / Semester']] as [$key,$label]):?><label><?=e($label)?><input class="input" name="<?=$key?>" required maxlength="150" value="<?=form_value($key,$r)?>"></label><?php endforeach;?></div><h2>Laptop information</h2><div class="form-grid"><label>Laptop Brand<input class="input" name="brand" list="brands" required maxlength="80" value="<?=form_value('brand',$r)?>"><datalist id="brands"><option value="HP"><option value="Dell"><option value="Lenovo"><option value="Acer"><option value="Asus"><option value="Apple"><option value="Other"></datalist></label></div><label>Accessories</label><div class="check-grid"><?php foreach($accessories as $a):?><label class="check"><input type="checkbox" name="accessories[]" value="<?=e($a)?>" <?=in_array($a,$selected,true)?'checked':''?>> <?=e($a)?></label><?php endforeach;?></div><label>Additional Accessories Notes<textarea class="input" rows="3" maxlength="2000" name="accessories_notes" placeholder="Optional notes"><?=form_value('accessories_notes',$r)?></textarea></label><div class="form-footer"><button class="btn primary">Save Student</button><a class="btn" href="<?=url('students')?>">Cancel</a></div></form></section>
<?php break;
case 'student':
$id=record_id();$r=student($id);if(!$r){http_response_code(404);echo '<div class="empty">Student not found.</div>';break;}
$acc=array_column(all('SELECT accessory FROM laptop_accessories WHERE laptop_id=?',[$r['laptop_id']]),'accessory');
page_title($r['student_name'],$r['student_number']);?><div class="actions"><a class="btn primary" href="<?=url('support-form',['student_id'=>$id])?>">＋ New Support Record</a><a class="btn" href="<?=url('student-form',['id'=>$id])?>">Edit Student</a><a class="btn" href="<?=url('students')?>">← All Students</a></div>
<div class="detail-grid"><section class="panel"><h2>Student information</h2><dl><?php foreach(['Student Number'=>'student_number','Name'=>'student_name','Phone Number'=>'phone_number','Department'=>'department','Class / Semester'=>'class_semester'] as $label=>$key):?><dt><?=e($label)?></dt><dd><?=e($r[$key])?></dd><?php endforeach;?></dl></section><section class="panel"><h2>Laptop information</h2><dl><dt>Brand</dt><dd><?=e($r['brand']??'—')?></dd><dt>Accessories</dt><dd><?=e(implode(', ',$acc)?:'None')?></dd><dt>Additional Notes</dt><dd><?=nl2br(e($r['accessories_notes']??''))?></dd></dl></section></div><section class="panel"><div class="section-head"><h2>Student Support History</h2><span class="muted">Previous cases are never overwritten</span></div><?php $rows=all('SELECT r.*,s.student_number,s.student_name FROM support_records r JOIN students s ON s.id=r.student_id WHERE r.student_id=? AND r.deleted_at IS NULL ORDER BY r.support_date DESC,r.id DESC',[$id]);include __DIR__.'/../app/partials/support_table.php';?></section>
<?php break;
case 'supports':
page_title('Support Records','All support cases, with status and date tracking.');
$search=trim((string)($_GET['search']??''));$status=(string)($_GET['work_status']??'');$from=(string)($_GET['from']??'');$to=(string)($_GET['to']??'');$where=['r.deleted_at IS NULL'];$params=[];
if($search!==''){$where[]='(s.student_name LIKE ? OR s.student_number LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%';}
if(in_array($status,$statuses,true)){$where[]='r.work_status=?';$params[]=$status;}
foreach(['from'=>'>=','to'=>'<='] as $key=>$op){$date=$key==='from'?$from:$to;if($date && DateTimeImmutable::createFromFormat('!Y-m-d',$date)?->format('Y-m-d')===$date){$where[]='r.support_date '.$op.' ?';$params[]=$date;}}
$rows=all('SELECT r.*,s.student_number,s.student_name FROM support_records r JOIN students s ON s.id=r.student_id WHERE '.implode(' AND ',$where).' ORDER BY r.support_date DESC,r.id DESC LIMIT 500',$params); ?>
<div class="actions"><a class="btn primary" href="<?=url('support-form')?>">＋ New Support Record</a></div><section class="panel"><form method="get" class="filters"><input type="hidden" name="page" value="supports"><input class="input" name="search" placeholder="Name or student number" value="<?=e($search)?>"><select class="input" name="work_status"><option value="">All Statuses</option><?php foreach($statuses as $x):?><option <?=($status===$x?'selected':'')?>><?=e($x)?></option><?php endforeach;?></select><label>From<input class="input" type="date" name="from" value="<?=e($from)?>"></label><label>To<input class="input" type="date" name="to" value="<?=e($to)?>"></label><button class="btn primary">Filter</button><a class="btn" href="<?=url('supports')?>">Reset</a></form><?php include __DIR__.'/../app/partials/support_table.php';?></section>
<?php break;
case 'support-form':
$id=isset($_GET['id'])?record_id():null;$r=$id?support($id):null;if($id&&!$r){http_response_code(404);echo '<div class="empty">Support record not found.</div>';break;}
$sid=(int)($r['student_id']??$_GET['student_id']??0);$students=all('SELECT s.id,s.student_name,s.student_number FROM students s JOIN laptops l ON l.student_id=s.id WHERE s.deleted_at IS NULL ORDER BY s.student_name');
$cats=$r?json_decode((string)$r['problem_categories'],true):[];if(!is_array($cats))$cats=[];
page_title($id?'Edit Support Record':'New Support Record','Each visit creates its own support history entry.'); ?>
<section class="panel form-panel"><form method="post"><?=token()?><h2>Support information</h2><div class="form-grid"><label>Student<select class="input" required name="student_id" <?=$id?'disabled':''?>><option value="">Choose student</option><?php foreach($students as $s):?><option value="<?=$s['id']?>" <?=$sid===(int)$s['id']?'selected':''?>><?=e($s['student_name'].' — '.$s['student_number'])?></option><?php endforeach;?></select><?php if($id):?><input type="hidden" name="student_id" value="<?=$sid?>"><?php endif;?></label><label>Support Date<input class="input" type="date" required name="support_date" value="<?=form_value('support_date',$r,date('Y-m-d'))?>"></label><label>Problem Type<?php select_field('problem_type',$types,(string)($_POST['problem_type']??$r['problem_type']??'Software Only'));?></label><label>Work Status<?php select_field('work_status',$statuses,(string)($_POST['work_status']??$r['work_status']??'Pending'));?></label><label>Completion Date (optional if Done)<input class="input" type="date" name="completion_date" value="<?=form_value('completion_date',$r)?>"></label></div>
<h2>Problem categories</h2><p class="muted small">Optional quick tags. Describe any other problem in the free-text field.</p><h3>Hardware</h3><div class="check-grid"><?php foreach($hardware as $c):?><label class="check"><input type="checkbox" name="problem_categories[]" value="<?=e($c)?>" <?=in_array($c,$cats,true)?'checked':''?>> <?=e($c)?></label><?php endforeach;?></div><h3>Software</h3><div class="check-grid"><?php foreach($software as $c):?><label class="check"><input type="checkbox" name="problem_categories[]" value="<?=e($c)?>" <?=in_array($c,$cats,true)?'checked':''?>> <?=e($c)?></label><?php endforeach;?></div><label>Problem Description<textarea class="input" required name="problem_description" maxlength="10000" rows="5" placeholder="Describe the symptoms, requested work, and outcome..."> <?=trim(form_value('problem_description',$r))?></textarea></label>
<h2>Data Backup</h2><p class="muted small">Indicate whether data was backed up for this particular support visit.</p><div class="form-grid"><?php foreach(['drive_c'=>'Drive C','drive_d'=>'Drive D','desktop'=>'Desktop','documents'=>'Documents'] as $key=>$label):?><label><?=e($label)?><?php select_field($key,$backs,(string)($_POST[$key]??$r[$key]??'Not Needed'));?></label><?php endforeach;?></div><div class="form-footer"><button class="btn primary">Save Support Record</button><a class="btn" href="<?=url('supports')?>">Cancel</a></div></form></section>
<?php break;
case 'support':
$id=record_id();$r=support($id);if(!$r){http_response_code(404);echo '<div class="empty">Support record not found.</div>';break;}
page_title('Support Record #'.$id,$r['student_name'].' · '.$r['student_number']); ?>
<div class="actions"><a class="btn primary" href="<?=url('support-form',['id'=>$id])?>">Edit Support</a><a class="btn" href="<?=url('student',['id'=>$r['student_id']])?>">View Student History</a><a class="btn" href="<?=url('supports')?>">← All Records</a><form method="post" action="<?=url('support-delete',['id'=>$id])?>" class="confirm-delete" data-message="Archive this support record? It remains retained in the database."><?=token()?><button class="btn danger">Delete Record</button></form></div>
<div class="detail-grid"><section class="panel"><h2>Support details</h2><dl><dt>Student</dt><dd><?=e($r['student_name'])?></dd><dt>Laptop Brand</dt><dd><?=e($r['brand'])?></dd><dt>Support Date</dt><dd><?=e($r['support_date'])?></dd><dt>Problem Type</dt><dd><?=e($r['problem_type'])?></dd><dt>Work Status</dt><dd><?=status_pill($r['work_status'])?></dd><dt>Completion Date</dt><dd><?=e($r['completion_date']??'—')?></dd><dt>Problem Tags</dt><dd><?php $tags=json_decode((string)$r['problem_categories'],true)?:[];echo e(implode(', ',$tags)?:'None');?></dd><dt>Description</dt><dd><?=nl2br(e($r['problem_description']))?></dd></dl></section><section class="panel"><h2>Backup record</h2><dl><?php foreach(['Drive C'=>'drive_c','Drive D'=>'drive_d','Desktop'=>'desktop','Documents'=>'documents'] as $label=>$key):?><dt><?=e($label)?></dt><dd><?=option_badge($r[$key]??'Not Needed')?></dd><?php endforeach;?></dl></section></div>
<?php break;
default: http_response_code(404);echo '<section class="panel"><h2>Page not found</h2><a href="'.url('dashboard').'">Return to Dashboard</a></section>';
}
?>
<?php if(!in_array($page,$public,true)): ?></main><footer class="footer">SNU Student Support Team · Secure administrator workspace</footer></div></div><?php else: ?></div></div><?php endif; ?><script src="assets/app.js" defer></script></body></html>
