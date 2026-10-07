<?php
namespace App\Controllers\EventUser;
use App\Core\Controller;use App\Helpers\CustomerAuth;use App\Services\Events as EventService;
class Events extends Controller {
 public function index():void{
  $this->handleCors();$u=$this->customer();
  $rows=$this->db()->query("SELECT e.id,e.name,e.slug,e.description,e.cover_image_url,e.event_date,e.default_price_standard,e.default_price_original,e.status,e.created_at,(SELECT COUNT(*) FROM vp_event_photos p WHERE p.event_id=e.id) photo_count FROM vp_events e WHERE e.customer_id=? ORDER BY e.event_date DESC,e.id DESC",[(int)$u['id']])->result_array()?:[];
  foreach($rows as &$r){$r['id']=(int)$r['id'];$r['photo_count']=(int)$r['photo_count'];$r['default_price_standard']=$r['default_price_standard']!==null?(float)$r['default_price_standard']:null;$r['default_price_original']=$r['default_price_original']!==null?(float)$r['default_price_original']:null;}
  unset($r);
  $this->success(['items'=>$rows,'limits'=>EventService::limits($this->db(),(int)$u['id'])],'Events loaded');
 }
 public function save($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$id=(int)$id;$b=$this->getBody();
  $can=EventService::canCreate($this->db(),(int)$u['id']);if(!$can['ok'])$this->error($can['reason'],403);
  $name=trim((string)($b['name']??''));if($name==='')$this->error('Nama event wajib diisi',422);
  $date=trim((string)($b['event_date']??''));$ts=strtotime($date);if($ts===false)$this->error('Tanggal event tidak valid',422);$date=date('Y-m-d',$ts);
  $status=(string)($b['status']??'draft');if(!in_array($status,['draft','published','archived'],true))$status='draft';
  $std=(!isset($b['default_price_standard'])||$b['default_price_standard']===''||$b['default_price_standard']===null)?null:max(0,(float)$b['default_price_standard']);
  $orig=(!isset($b['default_price_original'])||$b['default_price_original']===''||$b['default_price_original']===null)?null:max(0,(float)$b['default_price_original']);
  $coverRaw=trim((string)($b['cover_image_url']??''));$cover=($coverRaw!==''&&strpos(EventService::coverKeyFromUrl($coverRaw),'events/')===0)?$coverRaw:null;$desc=trim((string)($b['description']??''))?:null;
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  if($id>0){
   $own=$this->db()->query('SELECT id FROM vp_events WHERE id=? AND customer_id=? LIMIT 1',[$id,(int)$u['id']])->row_array();
   if(!$own)$this->error('Event tidak ditemukan',404);
   $this->db()->update('vp_events',['name'=>$name,'event_date'=>$date,'description'=>$desc,'cover_image_url'=>$cover,'default_price_standard'=>$std,'default_price_original'=>$orig,'status'=>$status,'updated_at'=>$now],['id'=>$id]);
  } else {
   $limits=EventService::limits($this->db(),(int)$u['id']);
   $count=(int)($this->db()->query('SELECT COUNT(*) c FROM vp_events WHERE customer_id=?',[(int)$u['id']])->row_array()['c']??0);
   if($count>=$limits['maxEvents'])$this->error('Batas maksimal '.$limits['maxEvents'].' event tercapai',422);
   $slug=EventService::slugify($name);$base=$slug;$i=1;
   while($this->db()->query('SELECT id FROM vp_events WHERE slug=? LIMIT 1',[$slug])->row_array()){$slug=$base.'-'.(++$i);}
   $id=(int)$this->db()->insert('vp_events',['customer_id'=>(int)$u['id'],'name'=>$name,'slug'=>$slug,'description'=>$desc,'cover_image_url'=>$cover,'event_date'=>$date,'default_price_standard'=>$std,'default_price_original'=>$orig,'status'=>$status,'created_at'=>$now,'updated_at'=>$now]);
  }
  $this->success(['id'=>$id],'Event disimpan');
 }
 public function cover():void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();
  $can=EventService::canCreate($this->db(),(int)$u['id']);if(!$can['ok'])$this->error($can['reason'],403);
  if(empty($_FILES['file'])||!is_array($_FILES['file']))$this->error('File wajib diisi',422);
  $f=$_FILES['file'];if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)$this->error('Upload gagal',422);
  if((int)$f['size']<1||(int)$f['size']>5*1024*1024)$this->error('Ukuran file cover maksimal 5MB',422);
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
  if(!isset(EventService::ALLOWED[$mime]))$this->error('Format gambar tidak didukung (JPG/PNG/WEBP/GIF/BMP)',422);
  try{$res=EventService::storeCover($f['tmp_name'],$mime);}catch(\Throwable $e){$this->error($e->getMessage(),422);}
  $this->success(['url'=>$res['coverUrl']],'Cover diunggah');
 }
 public function remove($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$id=(int)$id;
  $own=$this->db()->query('SELECT id FROM vp_events WHERE id=? AND customer_id=? LIMIT 1',[$id,(int)$u['id']])->row_array();
  if(!$own)$this->error('Event tidak ditemukan',404);
  EventService::deleteEventFiles($this->db(),$id);
  $this->db()->delete('vp_events',['id'=>$id]);
  $this->success(null,'Event dihapus');
 }
 private function customer():array{$u=CustomerAuth::user();if(!$u)$this->error('Unauthorized',401);EventService::ensureProfile($this->db(),(int)$u['id']);return $u;}
}
