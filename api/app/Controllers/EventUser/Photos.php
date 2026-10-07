<?php
namespace App\Controllers\EventUser;
use App\Core\Controller;use App\Helpers\CustomerAuth;use App\Services\Events;
class Photos extends Controller {
 public function list($eventId=null):void{
  $this->handleCors();$u=$this->customer();$eventId=(int)$eventId;
  $ev=$this->db()->query('SELECT id FROM vp_events WHERE id=? AND customer_id=? LIMIT 1',[$eventId,(int)$u['id']])->row_array();
  if(!$ev)$this->error('Event tidak ditemukan',404);
  $rows=$this->db()->query('SELECT id,preview_key,price_standard,price_original,width,height,original_uploaded FROM vp_event_photos WHERE event_id=? ORDER BY sort_order,id',[$eventId])->result_array()?:[];
  $out=[];
  foreach($rows as $r){$out[]=['id'=>(int)$r['id'],'previewUrl'=>Events::previewUrl($r['preview_key']),'priceStandard'=>$r['price_standard']!==null?(float)$r['price_standard']:null,'priceOriginal'=>$r['price_original']!==null?(float)$r['price_original']:null,'originalUploaded'=>((int)$r['original_uploaded'])===1,'width'=>$r['width']!==null?(int)$r['width']:null,'height'=>$r['height']!==null?(int)$r['height']:null];}
  $this->success(['items'=>$out,'limits'=>Events::limits($this->db(),(int)$u['id'])],'Photos loaded');
 }
 public function upload_preview($eventId=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$eventId=(int)$eventId;
  $ev=$this->db()->query('SELECT id FROM vp_events WHERE id=? AND customer_id=? LIMIT 1',[$eventId,(int)$u['id']])->row_array();
  if(!$ev)$this->error('Event tidak ditemukan',404);
  $limits=Events::limits($this->db(),(int)$u['id']);
  $count=(int)($this->db()->query('SELECT COUNT(*) c FROM vp_event_photos WHERE event_id=?',[$eventId])->row_array()['c']??0);
  if($count>=$limits['maxPhotos'])$this->error('Batas '.$limits['maxPhotos'].' foto per event tercapai',422);
  if(empty($_FILES['file'])||!is_array($_FILES['file']))$this->error('File wajib diisi',422);
  $f=$_FILES['file'];if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)$this->error('Upload gagal',422);
  if((int)$f['size']<1||(int)$f['size']>5*1024*1024)$this->error('Ukuran sumber maksimal 5MB',422);
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
  if(!isset(Events::ALLOWED[$mime]))$this->error('Format gambar tidak didukung (JPG/PNG/WEBP/GIF/BMP)',422);
  try{$res=Events::processPhoto($f['tmp_name'],$mime,$eventId);}catch(\Throwable $e){$this->error($e->getMessage(),422);}
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  $sort=(int)($this->db()->query('SELECT COALESCE(MAX(sort_order),0)+1 n FROM vp_event_photos WHERE event_id=?',[$eventId])->row_array()['n']??0);
  $id=(int)$this->db()->insert('vp_event_photos',['event_id'=>$eventId,'preview_key'=>$res['previewKey'],'standard_key'=>$res['standardKey'],'standard_mime'=>$res['mime'],'original_key'=>null,'original_mime'=>null,'original_uploaded'=>0,'price_standard'=>null,'price_original'=>null,'width'=>$res['width'],'height'=>$res['height'],'sort_order'=>$sort,'status'=>'active','created_at'=>$now,'updated_at'=>$now]);
  $this->success(['id'=>$id,'previewUrl'=>Events::previewUrl($res['previewKey']),'width'=>$res['width'],'height'=>$res['height']],'Foto diunggah');
 }
 public function update_price($photoId=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$photoId=(int)$photoId;
  $row=$this->db()->query('SELECT p.id FROM vp_event_photos p INNER JOIN vp_events e ON e.id=p.event_id WHERE p.id=? AND e.customer_id=? LIMIT 1',[$photoId,(int)$u['id']])->row_array();
  if(!$row)$this->error('Foto tidak ditemukan',404);
  $b=$this->getBody();
  $std=(!isset($b['price_standard'])||$b['price_standard']===''||$b['price_standard']===null)?null:max(0,(float)$b['price_standard']);
  $orig=(!isset($b['price_original'])||$b['price_original']===''||$b['price_original']===null)?null:max(0,(float)$b['price_original']);
  $this->db()->update('vp_event_photos',['price_standard'=>$std,'price_original'=>$orig,'updated_at'=>$GLOBALS['now']??date('Y-m-d H:i:s')],['id'=>$photoId]);
  $this->success(null,'Harga disimpan');
 }
 public function set_all_prices($eventId=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$eventId=(int)$eventId;
  $ev=$this->db()->query('SELECT id FROM vp_events WHERE id=? AND customer_id=? LIMIT 1',[$eventId,(int)$u['id']])->row_array();
  if(!$ev)$this->error('Event tidak ditemukan',404);
  $b=$this->getBody();
  $std=(!isset($b['price_standard'])||$b['price_standard']===''||$b['price_standard']===null)?null:max(0,(float)$b['price_standard']);
  $orig=(!isset($b['price_original'])||$b['price_original']===''||$b['price_original']===null)?null:max(0,(float)$b['price_original']);
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  $this->db()->update('vp_events',['default_price_standard'=>$std,'default_price_original'=>$orig,'updated_at'=>$now],['id'=>$eventId]);
  $this->db()->query('UPDATE vp_event_photos SET price_standard=NULL, price_original=NULL, updated_at=? WHERE event_id=?',[$now,$eventId]);
  $this->success(null,'Harga semua foto diset');
 }
 public function reorder($eventId=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$eventId=(int)$eventId;
  $ev=$this->db()->query('SELECT id FROM vp_events WHERE id=? AND customer_id=? LIMIT 1',[$eventId,(int)$u['id']])->row_array();
  if(!$ev)$this->error('Event tidak ditemukan',404);
  $order=$this->getBody()['order']??[];if(!is_array($order))$this->error('Urutan tidak valid',422);
  foreach(array_values($order) as $i=>$pid){$this->db()->update('vp_event_photos',['sort_order'=>(int)$i],['id'=>(int)$pid,'event_id'=>$eventId]);}
  $this->success(null,'Urutan disimpan');
 }
 public function remove($photoId=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$photoId=(int)$photoId;
  $row=$this->db()->query('SELECT p.id,p.preview_key,p.standard_key,p.original_key FROM vp_event_photos p INNER JOIN vp_events e ON e.id=p.event_id WHERE p.id=? AND e.customer_id=? LIMIT 1',[$photoId,(int)$u['id']])->row_array();
  if(!$row)$this->error('Foto tidak ditemukan',404);
  $this->db()->delete('vp_event_photos',['id'=>$photoId]);
  Events::deletePhotoFiles($row);
  $this->success(null,'Foto dihapus');
 }
 private function customer():array{$u=CustomerAuth::user();if(!$u)$this->error('Unauthorized',401);Events::ensureProfile($this->db(),(int)$u['id']);return $u;}
}
