<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$method=$_SERVER['REQUEST_METHOD']??'GET'; $in=request_data(); $action=(string)($in['action']??$_GET['action']??'');
function mobile_user():?array{
 $h=(string)($_SERVER['HTTP_AUTHORIZATION']??''); if(!preg_match('/^Bearer\s+([a-f0-9]{64})$/i',trim($h),$m))return null;
 $t=one('SELECT user_type,user_id FROM mobile_tokens WHERE token_hash=? AND revoked_at IS NULL AND expires_at>NOW() LIMIT 1',[hash('sha256',$m[1])]); if(!$t)return null;
 if($t['user_type']==='employee'){$u=one('SELECT id,name,personnel_code,post,inactive,in_latest FROM employees WHERE id=?',[(int)$t['user_id']]);if(!$u||!(int)$u['in_latest']||(int)$u['inactive'])return null;return ['type'=>'employee','id'=>(int)$u['id'],'name'=>$u['name'],'personnel_code'=>$u['personnel_code'],'post'=>$u['post']];}
 $u=one('SELECT id,name,username,role,personnel_code,active FROM admins WHERE id=?',[(int)$t['user_id']]);if(!$u||!(int)$u['active'])return null;return ['type'=>'admin','id'=>(int)$u['id'],'name'=>$u['name'],'username'=>$u['username'],'role'=>$u['role'],'personnel_code'=>(string)$u['personnel_code']];
}
function mobile_token(string $type,int $id):string{$v=bin2hex(random_bytes(32));q("INSERT INTO mobile_tokens(user_type,user_id,token_hash,expires_at,created_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY),NOW())",[$type,$id,hash('sha256',$v)]);return $v;}
try{
 if($action==='login'&&$method==='POST'){
  if(($in['user_type']??'')==='employee'){$r=payroll_login((string)($in['national']??''),(string)($in['personnel']??''),(string)($in['phone']??''));if(empty($r['ok'])||empty($r['employee']['id']))json_out(['ok'=>false,'message'=>$r['message']??'ورود انجام نشد.'],401);$type='employee';$id=(int)$r['employee']['id'];$u=['type'=>$type,'id'=>$id,'name'=>$r['employee']['name']];}
  elseif(($in['user_type']??'')==='admin'){$r=one('SELECT id,name,role,password_hash,personnel_code FROM admins WHERE username=? AND active=1 LIMIT 1',[trim((string)($in['username']??''))]);if(!$r||!password_verify((string)($in['password']??''),(string)$r['password_hash'])){usleep(350000);json_out(['ok'=>false,'message'=>'نام کاربری یا رمز عبور صحیح نیست.'],401);}$type='admin';$id=(int)$r['id'];$u=['type'=>$type,'id'=>$id,'name'=>$r['name'],'role'=>$r['role']];if(in_array((string)$r['role'],['admin','senior'],true)){start_session_secure();session_regenerate_id(true);$_SESSION['admin']=['id'=>$id,'name'=>$r['name'],'role'=>$r['role'],'pc'=>(string)($r['personnel_code']??'')];}}
  else json_out(['ok'=>false,'message'=>'نوع حساب کاربری معتبر نیست.'],422);
  json_out(['ok'=>true,'token'=>mobile_token($type,$id),'token_type'=>'Bearer','expires_in'=>2592000,'user'=>$u]);
 }
 $u=mobile_user();if(!$u)json_out(['ok'=>false,'message'=>'نشست معتبر نیست؛ دوباره وارد شوید.','logout'=>true],401);

 // ثبت امن شناسه دستگاه برای ارسال اعلان فوری؛ توکن فقط به حساب احراز هویت‌شده متصل می‌شود.
 if($action==='push_token_register'&&$method==='POST'){
  $pushToken=trim((string)($in['token']??''));
  $platform=(string)($in['platform']??'');
  if($pushToken===''||strlen($pushToken)>512||preg_match('/\\s/',$pushToken))json_out(['ok'=>false,'message'=>'شناسه اعلان معتبر نیست.'],422);
  if(!in_array($platform,['android','ios'],true))json_out(['ok'=>false,'message'=>'نوع دستگاه معتبر نیست.'],422);
  $hash=hash('sha256',$pushToken);
  q("INSERT INTO mobile_push_tokens(user_type,user_id,token_value,token_hash,platform,created_at,updated_at,last_seen_at,revoked_at)
     VALUES(?,?,?,?,?,NOW(),NOW(),NOW(),NULL)
     ON DUPLICATE KEY UPDATE user_type=VALUES(user_type),user_id=VALUES(user_id),token_value=VALUES(token_value),
     platform=VALUES(platform),updated_at=NOW(),last_seen_at=NOW(),revoked_at=NULL",
     [$u['type'],$u['id'],$pushToken,$hash,$platform]);
  json_out(['ok'=>true,'registered'=>true]);
 }
 if($action==='push_token_unregister'&&$method==='POST'){
  $pushToken=trim((string)($in['token']??''));
  if($pushToken!==''){
   q('UPDATE mobile_push_tokens SET revoked_at=NOW(),updated_at=NOW() WHERE token_hash=? AND user_type=? AND user_id=?',
     [hash('sha256',$pushToken),$u['type'],$u['id']]);
  }
  json_out(['ok'=>true]);
 }
 if($action==='me'&&$method==='GET'){
  if($u['type']==='employee'){$e=one('SELECT * FROM employees WHERE id=?',[$u['id']]);$p=employee_periods((int)$e['id']);json_out(['ok'=>true,'user'=>$u,'periods'=>array_map(fn($x)=>['id'=>(int)$x['id'],'label'=>period_label($x),'has_work'=>(bool)$x['has_work']],$p),'advance'=>advance_overview($e),'loan'=>advance_overview($e,'loan'),'promotion'=>promo_overview($e),'leave'=>['types'=>array_map(fn($k,$t)=>['key'=>$k,'label'=>$t['label'],'unit'=>$t['unit']],array_keys(LEAVE_TYPES),LEAVE_TYPES),'requests'=>leave_list_for((int)$e['id']),'balance'=>leave_annual_balance($e),'max_hours'=>(int)cfg('leave_max_hours_per_day',8)],'payslip_window'=>payslip_window()]);}
  $a=['id'=>$u['id'],'name'=>$u['name'],'role'=>$u['role'],'pc'=>$u['personnel_code']];$scope=admin_employee_scope_ids($a);
  if($scope===null)$counts=one("SELECT COUNT(*) total,COALESCE(SUM(in_latest=1 AND inactive=0),0) active,COALESCE(SUM(inactive=1 OR in_latest=0),0) inactive FROM employees");
  elseif(!$scope)$counts=['total'=>0,'active'=>0,'inactive'=>0];
  else $counts=one('SELECT COUNT(*) total,COALESCE(SUM(in_latest=1 AND inactive=0),0) active,COALESCE(SUM(inactive=1 OR in_latest=0),0) inactive FROM employees WHERE id IN ('.implode(',',array_fill(0,count($scope),'?')).')',$scope);
  json_out(['ok'=>true,'user'=>$u,'counts'=>$counts,'pending'=>admin_pending_counts($a)]);
 }

 if($u['type']==='employee'){
  $e=one('SELECT * FROM employees WHERE id=? AND in_latest=1 AND inactive=0 LIMIT 1',[$u['id']]);
  if(!$e) json_out(['ok'=>false,'message'=>'حساب کاربری فعال نیست.','logout'=>true],401);
  switch($action){
   case 'profile':
    json_out(['ok'=>true,'employee'=>['name'=>$e['name'],'national'=>$e['national_code'],'personnel'=>$e['personnel_code'],'post'=>$e['post'],'hire_date'=>(($d=jalali_from_start_code((string)$e['start_code']))?jalali_str($d):'')],'profile'=>employee_profile_get((int)$e['id'])]);
   case 'payslip':
    // مشاهده‌ی فیش‌های ذخیره‌شده مستقل از باز یا بسته بودن پنجره‌ی ثبت/بارگذاری دوره است.
    // payslip_for فقط فیش متعلق به همین کارمند را برمی‌گرداند.
    $periodId=(int)($in['period_id']??$_GET['period_id']??0); $slip=$periodId?payslip_for((int)$e['id'],$periodId):null;
    if(!$slip) json_out(['ok'=>false,'message'=>'فیش این دوره برای شما ثبت نشده است.'],404);
    json_out(['ok'=>true,'slip'=>$slip]);
   case 'advance_submit':
    json_out(advance_submit($e,$in,'advance')+['advance'=>advance_overview($e,'advance')]);
   case 'loan_submit':
    json_out(advance_submit($e,$in,'loan')+['loan'=>advance_overview($e,'loan')]);
   case 'promo_submit':
    json_out(promo_submit($e,$in)+['promotion'=>promo_overview($e)]);
   case 'leave_submit':
    $lr=leave_submit($e,$in); json_out($lr+['requests'=>leave_list_for((int)$e['id']),'balance'=>leave_annual_balance($e)]);
   case 'notif_poll':
    json_out(['ok'=>true,'items'=>notify_poll('emp:'.$e['id'])]);
   case 'notif_ack':
    notify_ack('emp:'.$e['id'],(array)($in['ids']??[])); json_out(['ok'=>true]);
  }
 }

 if($u['type']==='admin'){
  $a=['id'=>$u['id'],'name'=>$u['name'],'role'=>$u['role'],'pc'=>$u['personnel_code']];
  switch($action){
   case 'manager_employees':
    // The scope is calculated on the server; a client cannot request employees outside it.
    $scope=admin_employee_scope_ids($a);
    $search=trim((string)($in['q']??$_GET['q']??''));
    $where=['in_latest=1'];
    $params=[];
    if($scope!==null){
     if(!$scope) json_out(['ok'=>true,'employees'=>[],'total'=>0]);
     $where[]='id IN ('.implode(',',array_fill(0,count($scope),'?')).')';
     $params=array_merge($params,$scope);
    }
    if($search!==''){
     $where[]='(name LIKE ? OR personnel_code LIKE ? OR post LIKE ?)';
     $like='%'.$search.'%';
     array_push($params,$like,$like,$like);
    }
    $rows=all('SELECT id,name,personnel_code,post,inactive FROM employees WHERE '.implode(' AND ',$where).' ORDER BY name LIMIT 1000',$params);
    json_out(['ok'=>true,'employees'=>$rows,'total'=>count($rows)]);
   case 'manager_leaves':
    json_out(['ok'=>true,'requests'=>leave_manager_list($a,null,(array)($in['filter']??[]))]);
   case 'manager_advances':
    json_out(['ok'=>true,'requests'=>advance_admin_list((array)($in['filter']??[]),$a)]);
   case 'manager_promotions':
    json_out(['ok'=>true,'requests'=>promo_admin_list($a,(array)($in['filter']??[]))]);
   case 'manager_leave_decide':
    if($method!=='POST') json_out(['ok'=>false,'message'=>'Method not allowed'],405);
    $result=leave_decide($a,(int)($in['id']??0),(string)($in['decision']??''),(string)($in['note']??''),$u['name']);
    json_out($result+['pending'=>admin_pending_counts($a)]);
   case 'manager_advance_decide':
    if($method!=='POST') json_out(['ok'=>false,'message'=>'Method not allowed'],405);
    $approved=isset($in['approved'])&&$in['approved']!==''?(int)digits((string)$in['approved']):null;
    $result=advance_decide($a,(int)($in['id']??0),(string)($in['decision']??''),$approved,(string)($in['note']??''),$u['name']);
    json_out($result+['pending'=>admin_pending_counts($a)]);
   case 'manager_promo_decide':
    if($method!=='POST') json_out(['ok'=>false,'message'=>'Method not allowed'],405);
    $result=promo_decide($a,(int)($in['id']??0),(string)($in['decision']??''),(string)($in['note']??''),$u['name'],
      isset($in['score'])?(string)$in['score']:null,isset($in['amount'])?(string)$in['amount']:null);
    json_out($result+['pending'=>admin_pending_counts($a)]);
   case 'manager_notif_poll':
    json_out(['ok'=>true,'items'=>notify_poll('admin:'.$a['id']),'pending'=>admin_pending_counts($a)]);
   case 'manager_notif_ack':
    if($method!=='POST') json_out(['ok'=>false,'message'=>'Method not allowed'],405);
    notify_ack('admin:'.$a['id'],(array)($in['ids']??[])); json_out(['ok'=>true]);
  }
 }

 if($u['type']==='admin' && in_array($action,['admin_list','admin_save','admin_toggle','admin_settings_get','admin_window_save','admin_rules_save','admin_promo_mode_save','admin_approval_flows_save','mobile_upload','mobile_employee_create','admin_announcement_send'],true)){
  $a=['id'=>$u['id'],'name'=>$u['name'],'role'=>$u['role'],'pc'=>$u['personnel_code']];
  $full=in_array((string)$a['role'],['admin','senior'],true);
  if(!$full) json_out(['ok'=>false,'message'=>'دسترسی کافی ندارید.'],403);
  if($action==='admin_list') json_out(['ok'=>true,'admins'=>all('SELECT id,username,name,role,personnel_code,active FROM admins ORDER BY id')]);
  if($action==='admin_announcement_send'){
   if($method!=='POST') json_out(['ok'=>false,'message'=>'Method not allowed'],405);
   $title=trim((string)($in['title']??'')); $body=trim((string)($in['body']??''));
   if($title===''||$body==='') json_out(['ok'=>false,'message'=>'عنوان و متن اطلاعیه الزامی است.'],422);
   $kind=in_array((string)($in['kind']??''),['announcement','urgent','reminder'],true)?(string)$in['kind']:'announcement';
   $tone=$kind==='urgent'?'warning':'info';
   $target=(string)($in['target']??'all');
   if($target==='all'){
    $rows=all('SELECT id FROM employees WHERE in_latest=1 AND inactive=0 ORDER BY id');
   }elseif($target==='selected'){
    $ids=array_values(array_unique(array_filter(array_map('intval',(array)($in['employee_ids']??[])),fn($v)=>$v>0)));
    if(!$ids) json_out(['ok'=>false,'message'=>'حداقل یک کارمند را انتخاب کنید.'],422);
    $rows=all('SELECT id FROM employees WHERE in_latest=1 AND inactive=0 AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids);
   }else{
    json_out(['ok'=>false,'message'=>'گروه گیرندگان معتبر نیست.'],422);
   }
   foreach($rows as $row) notify_emp((int)$row['id'],$kind,$title,$body,$tone);
   json_out(['ok'=>true,'message'=>'اطلاعیه برای '.count($rows).' کارمند ثبت شد.','sent'=>count($rows)]);
  }

  if($action==='mobile_employee_create'){ $created=employee_create_manual($in,(string)$a['name']); json_out(['ok'=>true]+$created); }
  if($action==='admin_save'){
   $id=(int)($in['id']??0);$username=trim((string)($in['username']??''));$name=trim((string)($in['name']??''));$role=in_array($in['role']??'', ['admin','senior'],true)?$in['role']:'manager';$pc=digits((string)($in['personnel']??''));$pass=(string)($in['password']??'');
   if($pc!==''&&!one('SELECT id FROM employees WHERE personnel_code=? LIMIT 1',[$pc]))json_out(['ok'=>false,'message'=>'کد پرسنلی در فهرست کارکنان پیدا نشد.'],422);
   if($username===''||$name==='')json_out(['ok'=>false,'message'=>'نام کاربری و نام نمایشی الزامی است.'],422);
   if($id){q('UPDATE admins SET username=?,name=?,role=?,personnel_code=? WHERE id=?',[$username,$name,$role,$pc,$id]);if($pass!==''){if(strlen($pass)<8)json_out(['ok'=>false,'message'=>'رمز عبور باید حداقل ۸ کاراکتر باشد.'],422);q('UPDATE admins SET password_hash=? WHERE id=?',[password_hash($pass,PASSWORD_DEFAULT),$id]);}}
   else{if(strlen($pass)<8)json_out(['ok'=>false,'message'=>'برای حساب جدید رمز حداقل ۸ کاراکتر باشد.'],422);q('INSERT INTO admins(username,password_hash,name,role,personnel_code,active,created_at) VALUES(?,?,?,?,?,1,NOW())',[$username,password_hash($pass,PASSWORD_DEFAULT),$name,$role,$pc]);}
   json_out(['ok'=>true,'message'=>'حساب مدیریتی ذخیره شد.']);
  }
  if($action==='admin_toggle'){ $id=(int)($in['id']??0);if($id===$a['id'])json_out(['ok'=>false,'message'=>'امکان غیرفعال‌کردن حساب خودتان وجود ندارد.'],422);q('UPDATE admins SET active=1-active WHERE id=?',[$id]);json_out(['ok'=>true,'message'=>'وضعیت حساب تغییر کرد.']);}
  if($action==='admin_settings_get')json_out(['ok'=>true,'windows'=>['advance'=>advance_window('advance'),'loan'=>advance_window('loan'),'payslip'=>payslip_window()],'rules'=>fin_rules(),'promo_mode'=>promo_apply_mode(),'approval_flows'=>approval_flows()]);
  if($action==='admin_window_save'){
   $which=(string)($in['which']??'');$prefix=$which==='payslip'?'pay':($which==='advance'?'adv':($which==='loan'?'loan':''));if($prefix==='')json_out(['ok'=>false,'message'=>'نوع بازه معتبر نیست.'],422);
   if(!empty($in['clear'])){set_setting($prefix.'_start',null);set_setting($prefix.'_end',null);json_out(['ok'=>true,'message'=>'بازه پاک شد.']);}
   $s=jalali_parse((string)($in['start']??''));$e=jalali_parse((string)($in['end']??''));if(!$s||!$e||jalali_num($s)>jalali_num($e))json_out(['ok'=>false,'message'=>'بازه تاریخ شمسی معتبر نیست.'],422);set_setting($prefix.'_start',jalali_str($s));set_setting($prefix.'_end',jalali_str($e));json_out(['ok'=>true,'message'=>'بازه ذخیره شد.']);
  }
  if($action==='admin_rules_save')json_out(fin_rules_save($in));
  if($action==='admin_promo_mode_save')json_out(promo_mode_save($in));
  if($action==='admin_approval_flows_save')json_out(approval_flows_save($in));
  if($action==='mobile_upload'){
   if($method!=='POST')json_out(['ok'=>false,'message'=>'Method not allowed'],405);
   $year=(int)($_POST['year']??0);$month=(int)($_POST['month']??0);
   if($year<1390||$year>1500||$month<1||$month>12)json_out(['ok'=>false,'message'=>'سال و ماه دوره را درست انتخاب کنید.'],422);
   if(empty($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK)json_out(['ok'=>false,'message'=>'فایل اکسل دریافت نشد.'],422);
   $file=$_FILES['file'];$name=(string)$file['name'];if(!preg_match('/\\.xlsx$/i',$name))json_out(['ok'=>false,'message'=>'فقط فایل xlsx پذیرفته می‌شود.'],422);
   if((int)$file['size']>cfg('upload_max_mb',40)*1024*1024)json_out(['ok'=>false,'message'=>'حجم فایل بیش از حد مجاز است.'],413);
   $report=payroll_import((string)$file['tmp_name'],$year,$month,$name,(string)$a['name']);
   json_out(['ok'=>true,'report'=>$report,'message'=>'فایل با موفقیت بارگذاری و اطلاعات به‌روز شد.']);
  }
 }

 if($action==='logout'&&$method==='POST'){$h=(string)($_SERVER['HTTP_AUTHORIZATION']??'');if(preg_match('/^Bearer\s+([a-f0-9]{64})$/i',trim($h),$m))q('UPDATE mobile_tokens SET revoked_at=NOW() WHERE token_hash=?',[hash('sha256',$m[1])]);if($u['type']==='admin'){start_session_secure();unset($_SESSION['admin']);session_regenerate_id(true);}json_out(['ok'=>true]);}
 json_out(['ok'=>false,'message'=>'این عملیات در نسخه بعدی فعال می‌شود.'],404);
}catch(Throwable $e){error_log('[mobile api] '.$e->getMessage());json_out(['ok'=>false,'message'=>'خطای داخلی سرور. دوباره تلاش کنید.'],500);}
