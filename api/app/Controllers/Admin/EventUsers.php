<?php
namespace App\Controllers\Admin;
use App\Core\Controller;use App\Helpers\AdminAuth;use App\Services\Events;
class EventUsers extends Controller {
 public function index():void{
  $this->handleCors();$this->admin();
  $rows=$this->db()->query("SELECT c.id,c.full_name,c.email,c.avatar_url,p.balance,p.status,p.approved,p.max_events,p.max_photos_per_event,(SELECT COUNT(*) FROM vp_events e WHERE e.customer_id=c.id) event_count,(SELECT COUNT(*) FROM vp_event_photos ph INNER JOIN vp_events e2 ON e2.id=ph.event_id WHERE e2.customer_id=c.id) photo_count FROM vp_event_profiles p INNER JOIN vp_customers c ON c.id=p.customer_id ORDER BY c.id DESC")->result_array()?:[];
  foreach($rows as &$r){$r['id']=(int)$r['id'];$r['balance']=(float)$r['balance'];$r['approved']=((int)$r['approved'])===1;$r['event_count']=(int)$r['event_count'];$r['photo_count']=(int)$r['photo_count'];$r['max_events']=$r['max_events']!==null?(int)$r['max_events']:null;$r['max_photos_per_event']=$r['max_photos_per_event']!==null?(int)$r['max_photos_per_event']:null;}
  unset($r);
  $this->success(['items'=>$rows,'defaults'=>['maxEvents'=>Events::DEFAULT_MAX_EVENTS,'maxPhotos'=>Events::DEFAULT_MAX_PHOTOS],'whitelistMode'=>Events::whitelistMode($this->db())],'Event users loaded');
 }
 public function show($id=null):void{
  $this->handleCors();$this->admin();$id=(int)$id;
  $u=$this->db()->query('SELECT c.id,c.full_name,c.email,c.avatar_url,p.balance,p.status,p.approved,p.max_events,p.max_photos_per_event FROM vp_event_profiles p INNER JOIN vp_customers c ON c.id=p.customer_id WHERE c.id=? LIMIT 1',[$id])->row_array();
  if(!$u)$this->error('User event tidak ditemukan',404);
  $u['id']=(int)$u['id'];$u['balance']=(float)$u['balance'];$u['approved']=((int)$u['approved'])===1;$u['max_events']=$u['max_events']!==null?(int)$u['max_events']:null;$u['max_photos_per_event']=$u['max_photos_per_event']!==null?(int)$u['max_photos_per_event']:null;
  $u['events']=$this->db()->query('SELECT id,name,slug,event_date,status,default_price_standard,default_price_original,(SELECT COUNT(*) FROM vp_event_photos p2 WHERE p2.event_id=vp_events.id) photo_count FROM vp_events WHERE customer_id=? ORDER BY event_date DESC',[$id])->result_array()?:[];
  $u['ledger']=$this->db()->query('SELECT id,order_id,photo_id,amount,type,note,created_at FROM vp_event_user_ledger WHERE customer_id=? ORDER BY id DESC LIMIT 100',[$id])->result_array()?:[];
  $this->success($u,'Event user loaded');
 }
 public function update_status($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$this->admin();$id=(int)$id;
  $status=(string)($this->getBody()['status']??'');
  if(!in_array($status,['active','blocked'],true))$this->error('Status tidak valid',422);
  $ok=$this->db()->update('vp_event_profiles',['status'=>$status,'updated_at'=>$GLOBALS['now']??date('Y-m-d H:i:s')],['customer_id'=>$id]);
  if(!$ok)$this->error('User event tidak ditemukan',404);
  $this->success(null,'Status diperbarui');
 }
 public function set_approval($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$this->admin();$id=(int)$id;
  $approved=((bool)($this->getBody()['approved']??false))?1:0;
  $ok=$this->db()->update('vp_event_profiles',['approved'=>$approved,'updated_at'=>$GLOBALS['now']??date('Y-m-d H:i:s')],['customer_id'=>$id]);
  if(!$ok)$this->error('User event tidak ditemukan',404);
  $this->success(['approved'=>(bool)$approved],'Persetujuan disimpan');
 }
 public function save_limits($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$this->admin();$id=(int)$id;
  $b=$this->getBody();
  $me=(!isset($b['max_events'])||$b['max_events']===''||$b['max_events']===null)?null:max(0,(int)$b['max_events']);
  $mp=(!isset($b['max_photos_per_event'])||$b['max_photos_per_event']===''||$b['max_photos_per_event']===null)?null:max(0,(int)$b['max_photos_per_event']);
  $ok=$this->db()->update('vp_event_profiles',['max_events'=>$me,'max_photos_per_event'=>$mp,'updated_at'=>$GLOBALS['now']??date('Y-m-d H:i:s')],['customer_id'=>$id]);
  if(!$ok)$this->error('User event tidak ditemukan',404);
  $this->success(null,'Limit disimpan');
 }
 private function admin():array{$a=AdminAuth::user();if(!$a)$this->error('Unauthorized',401);return $a;}
}
