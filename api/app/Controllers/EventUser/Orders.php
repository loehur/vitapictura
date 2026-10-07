<?php
namespace App\Controllers\EventUser;
use App\Core\Controller;use App\Helpers\CustomerAuth;use App\Services\Events;
class Orders extends Controller {
 public function index():void{
  $this->handleCors();$u=$this->customer();
  $rows=$this->db()->query("SELECT oip.id,oip.variant,oip.price,oip.photo_id,p.preview_key,p.original_uploaded,o.id order_id,o.order_number,o.status order_status,o.created_at FROM vp_order_item_photos oip INNER JOIN vp_event_photos p ON p.id=oip.photo_id INNER JOIN vp_events e ON e.id=p.event_id INNER JOIN vp_order_items oi ON oi.id=oip.order_item_id INNER JOIN vp_orders o ON o.id=oi.order_id WHERE e.customer_id=? ORDER BY o.created_at DESC,oip.id DESC",[(int)$u['id']])->result_array()?:[];
  $orders=[];
  foreach($rows as $r){
   $oid=(int)$r['order_id'];
   if(!isset($orders[$oid]))$orders[$oid]=['orderId'=>$oid,'orderNumber'=>$r['order_number'],'status'=>$r['order_status'],'createdAt'=>$r['created_at'],'photos'=>[]];
   $orders[$oid]['photos'][]=['id'=>(int)$r['id'],'photoId'=>(int)$r['photo_id'],'variant'=>$r['variant'],'price'=>(float)$r['price'],'previewUrl'=>Events::previewUrl($r['preview_key']),'originalUploaded'=>((int)$r['original_uploaded'])===1];
  }
  $this->success(['items'=>array_values($orders)],'Orders loaded');
 }
 public function upload_original($photoId=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$photoId=(int)$photoId;
  $row=$this->db()->query('SELECT p.id,p.event_id,p.original_uploaded FROM vp_event_photos p INNER JOIN vp_events e ON e.id=p.event_id WHERE p.id=? AND e.customer_id=? LIMIT 1',[$photoId,(int)$u['id']])->row_array();
  if(!$row)$this->error('Foto tidak ditemukan',404);
  if(empty($_FILES['file'])||!is_array($_FILES['file']))$this->error('File wajib diisi',422);
  $f=$_FILES['file'];if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)$this->error('Upload gagal',422);
  if((int)$f['size']<1||(int)$f['size']>52428800)$this->error('Ukuran file maksimal 50MB',422);
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
  if(!isset(Events::ALLOWED[$mime])&&!in_array($mime,['image/tiff','image/heic','image/heif'],true))$this->error('Format gambar tidak didukung',422);
  try{$res=Events::storeOriginal($f['tmp_name'],$mime,(int)$row['event_id']);}catch(\Throwable $e){$this->error($e->getMessage(),422);}
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  $this->db()->update('vp_event_photos',['original_key'=>$res['originalKey'],'original_mime'=>$res['mime'],'original_uploaded'=>1,'updated_at'=>$now],['id'=>$photoId]);
  $this->success(null,'File original tersimpan');
 }
 private function customer():array{$u=CustomerAuth::user();if(!$u)$this->error('Unauthorized',401);Events::ensureProfile($this->db(),(int)$u['id']);return $u;}
}
