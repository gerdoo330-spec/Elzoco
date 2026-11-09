<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
start_session_secure();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok'=>false,'message'=>'Method not allowed'],405);
require_csrf();
$in=request_data();
try {
    $a=require_admin_json();
    if (!in_array($a['role'] ?? '', ['admin','senior'], true)) {
        json_out(['ok'=>false,'message'=>'فقط ادمین و مدیر ارشد مجاز به ارسال اطلاعیه هستند.'],403);
    }
    $action=(string)($in['action'] ?? 'list');
    if ($action==='list') {
        $rows=all('SELECT id,title,body,kind,audience,audience_value,created_at,active,recipient_count FROM announcements ORDER BY id DESC LIMIT 100');
        json_out(['ok'=>true,'announcements'=>$rows]);
    }
    if ($action==='deactivate') {
        $id=(int)($in['id'] ?? 0);
        if ($id<1) json_out(['ok'=>false,'message'=>'شناسه معتبر نیست.'],422);
        q('UPDATE announcements SET active=0 WHERE id=?',[$id]);
        json_out(['ok'=>true,'message'=>'اطلاعیه غیرفعال شد.']);
    }
    if ($action!=='create') json_out(['ok'=>false,'message'=>'درخواست نامعتبر است.'],400);
    $title=trim((string)($in['title'] ?? ''));
    $body=trim((string)($in['body'] ?? ''));
    $kind=(string)($in['kind'] ?? 'general');
    $audience=(string)($in['audience'] ?? 'all');
    $value=trim((string)($in['audience_value'] ?? ''));
    if ($title==='' || $body==='') json_out(['ok'=>false,'message'=>'عنوان و متن اطلاعیه الزامی است.'],422);
    if (mb_strlen($title)>190 || mb_strlen($body)>5000) json_out(['ok'=>false,'message'=>'طول عنوان یا متن بیش از حد مجاز است.'],422);
    if (!in_array($kind,['general','urgent','reminder','news'],true)) $kind='general';
    if (!in_array($audience,['all','section','branch','selected'],true)) json_out(['ok'=>false,'message'=>'مخاطب نامعتبر است.'],422);

    $where="e.in_latest=1 AND e.inactive=0";
    $params=[];
    if ($audience==='section' || $audience==='branch') {
        if ($value==='') json_out(['ok'=>false,'message'=>'مقدار سکشن یا شعبه را انتخاب کنید.'],422);
        $jsonExpr="CASE WHEN JSON_VALID(ep.extra_json) THEN ep.extra_json ELSE '{}' END";
        $field=$audience==='section' ? 'section' : 'branch';
        $expr="COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT($jsonExpr, '$.$field')), ''), '')";
        $where.=" AND $expr=?";
        $params[]=$value;
        $sql="SELECT e.id FROM employees e LEFT JOIN employee_profiles ep ON ep.employee_id=e.id WHERE $where";
    } elseif ($audience==='selected') {
        $ids=array_values(array_unique(array_filter(array_map('intval',(array)($in['employee_ids'] ?? [])),fn($v)=>$v>0)));
        if (!$ids) json_out(['ok'=>false,'message'=>'حداقل یک کارمند را انتخاب کنید.'],422);
        $where.=" AND e.id IN (".implode(',',array_fill(0,count($ids),'?')).")";
        $params=$ids;
        $sql="SELECT e.id FROM employees e WHERE $where";
    } else {
        $sql="SELECT e.id FROM employees e WHERE $where";
    }
    $employees=all($sql,$params);
    if (!$employees) json_out(['ok'=>false,'message'=>'برای این مخاطب، کارمند فعالی پیدا نشد.'],422);
    $pdo=db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO announcements (title,body,kind,audience,audience_value,created_by,created_at,active,recipient_count) VALUES (?,?,?,?,?,?,NOW(),1,?)',
          [mb_substr($title,0,190),$body,$kind,$audience,$value,(int)$a['id'],count($employees)]);
        $announcementId=(int)$pdo->lastInsertId();
        foreach($employees as $emp) {
            notify_emp((int)$emp['id'],'announcement',$title,$body,$kind==='urgent'?'danger':'info');
        }
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    json_out(['ok'=>true,'id'=>$announcementId,'recipient_count'=>count($employees),'message'=>'اطلاعیه در صندوق اعلان کارکنان ثبت شد.']);
} catch(Throwable $e) {
    error_log('[announcements] '.$e->getMessage());
    json_out(['ok'=>false,'message'=>'خطا در پردازش اطلاعیه. تنظیمات دیتابیس و به‌روزرسانی را بررسی کنید.'],500);
}
