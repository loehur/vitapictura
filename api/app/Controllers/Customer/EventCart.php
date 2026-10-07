<?php
namespace App\Controllers\Customer;
use App\Core\Controller;use App\Helpers\CustomerAuth;use App\Services\Events;
class EventCart extends Controller {
 public function index():void{
  $this->handleCors();$u=$this->customer();
  $rows=$this->db()->query('SELECT c.id,c.event_id,c.photo_id,c.variant,c.unit_price,e.name event_name,e.slug event_slug,e.event_date FROM vp_cart_event_photos c INNER JOIN vp_events e ON e.id=c.event_id WHERE c.customer_id=? ORDER BY e.event_date DESC,c.id',[(int)$u['id']])->result_array()?:[];
  $previews=[];
  if($rows){$ids=array_map(fn($r)=>(int)$r['photo_id'],$rows);$ph=$this->db()->query('SELECT id,preview_key FROM vp_event_photos WHERE id IN ('.implode(',',$ids).')')->result_array()?:[];foreach($ph as $p)$previews[(int)$p['id']]=Events::previewUrl($p['preview_key']);}
  $events=[];
  foreach($rows as $r){
   $eid=(int)$r['event_id'];
   if(!isset($events[$eid]))$events[$eid]=['eventId'=>$eid,'eventName'=>$r['event_name'],'eventSlug'=>$r['event_slug'],'qty'=>0,'subtotal'=>0.0,'photos'=>[]];
   $events[$eid]['qty']++;
   $events[$eid]['subtotal']+=(float)$r['unit_price'];
   $events[$eid]['photos'][]=['cartId'=>(int)$r['id'],'photoId'=>(int)$r['photo_id'],'variant'=>$r['variant'],'price'=>(float)$r['unit_price'],'previewUrl'=>$previews[(int)$r['photo_id']]??''];
  }
  $items=array_values($events);
  $total=0.0;foreach($items as $it)$total+=$it['subtotal'];
  $this->success(['items'=>$items,'total'=>$total,'count'=>count($rows)],'Event cart loaded');
 }
 public function add():void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$b=$this->getBody();
  $photoId=(int)($b['photo_id']??0);$variant=(string)($b['variant']??'standard');
  if(!in_array($variant,['standard','original'],true))$this->error('Varian tidak valid',422);
  $p=$this->db()->query("SELECT p.id,p.event_id,p.price_standard,p.price_original,e.status,e.default_price_standard,e.default_price_original FROM vp_event_photos p INNER JOIN vp_events e ON e.id=p.event_id WHERE p.id=? AND p.status='active' LIMIT 1",[$photoId])->row_array();
  if(!$p)$this->error('Foto tidak ditemukan',404);
  if($p['status']!=='published')$this->error('Event belum dipublikasikan',422);
  $price=$variant==='original'?($p['price_original']!==null?(float)$p['price_original']:($p['default_price_original']!==null?(float)$p['default_price_original']:null)):($p['price_standard']!==null?(float)$p['price_standard']:($p['default_price_standard']!==null?(float)$p['default_price_standard']:null));
  if($price===null||$price<=0)$this->error('Harga belum diatur untuk varian ini',422);
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  $existing=$this->db()->query('SELECT id FROM vp_cart_event_photos WHERE customer_id=? AND photo_id=? LIMIT 1',[(int)$u['id'],$photoId])->row_array();
  if($existing)$this->db()->update('vp_cart_event_photos',['variant'=>$variant,'unit_price'=>$price],['id'=>(int)$existing['id']]);
  else $this->db()->insert('vp_cart_event_photos',['customer_id'=>(int)$u['id'],'event_id'=>(int)$p['event_id'],'photo_id'=>$photoId,'variant'=>$variant,'unit_price'=>$price,'created_at'=>$now]);
  $this->success(null,'Foto ditambahkan ke keranjang');
 }
 public function remove($id=null):void{$this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$this->db()->delete('vp_cart_event_photos',['id'=>(int)$id,'customer_id'=>(int)$u['id']]);$this->success(null,'Foto dihapus dari keranjang');}
 private function customer():array{$u=CustomerAuth::user();if(!$u)$this->error('Unauthorized',401);return $u;}
}
