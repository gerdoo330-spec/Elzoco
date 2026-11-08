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
  elseif(($in['user_type']??'')==='admin'){$r=one('SELECT id,name,role,password_hash FROM admins WHERE username=? AND active=1 LIMIT 1',[trim((string)($in['username']??''))]);if(!$r||!password_verify((string)($in['password']??''),(string)$r['password_hash'])){usleep(350000);json_out(['ok'=>false,'message'=>'نام کاربری یا رمز عبور صحیح نیست.'],401);}$type='admin';$id=(int)$r['id'];$u=['type'=>$type,'id'=>$id,'name'=>$r['name'],'role'=>$r['role']];}
  else json_out(['ok'=>false,'message'=>'نوع حساب کاربری معتبر نیست.'],422);
  json_out(['ok'=>true,'token'=>mobile_token($type,$id),'token_type'=>'Bearer','expires_in'=>2592000,'user'=>$u]);
 }
 $u=mobile_user();if(!$u)json_out(['ok'=>false,'message'=>'نشست معتبر نیست؛ دوباره وارد شوید.','logout'=>true],401);
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
 if($action==='logout'&&$method==='POST'){$h=(string)($_SERVER['HTTP_AUTHORIZATION']??'');if(preg_match('/^Bearer\s+([a-f0-9]{64})$/i',trim($h),$m))q('UPDATE mobile_tokens SET revoked_at=NOW() WHERE token_hash=?',[hash('sha256',$m[1])]);json_out(['ok'=>true]);}
 json_out(['ok'=>false,'message'=>'این عملیات در نسخه بعدی فعال می‌شود.'],404);
}catch(Throwable $e){error_log('[mobile api] '.$e->getMessage());json_out(['ok'=>false,'message'=>'خطای داخلی سرور. دوباره تلاش کنید.'],500);}
