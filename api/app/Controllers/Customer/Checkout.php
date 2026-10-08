<?php
namespace App\Controllers\Customer;
use App\Core\Controller;use App\Helpers\CustomerAuth;use App\Services\Biteship;use App\Services\EventQuota;
class Checkout extends Controller {
 public function quote():void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$b=$this->getBody();
  EventQuota::clamp($this->db(),(int)$u['id']);
  $cart=$this->cart((int)$u['id']);$photos=$this->eventPhotos((int)$u['id']);
  if(!$cart&&!$photos)$this->error('Keranjang kosong',422);
  $out=['address'=>null,'subtotal'=>$this->subtotal($cart),'eventSubtotal'=>$this->eventSubtotal($photos),'eventFreeCount'=>$this->eventFreeCount($photos),'eventFreeAmount'=>$this->eventFreeAmount($photos),'rates'=>[]];
  if($cart){
   $addressId=(int)($b['address_id']??0);if($addressId<=0)$this->error('Pilih alamat pengiriman',422);
   $address=$this->address($addressId,(int)$u['id']);$out['address']=$this->snapshot($address);
   try{$out['rates']=Biteship::quote($address,$this->items($cart));}catch(\Throwable $e){$this->error($e->getMessage(),422);}
  }
  $this->success($out,'Checkout quoted');
 }
 public function create():void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$u=$this->customer();$b=$this->getBody();
  EventQuota::clamp($this->db(),(int)$u['id']);
  $cart=$this->cart((int)$u['id']);$photos=$this->eventPhotos((int)$u['id']);
  if(!$cart&&!$photos)$this->error('Keranjang kosong',422);
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  $productSubtotal=$this->subtotal($cart);$eventSubtotal=$this->eventSubtotal($photos);
  $address=null;$shipping=0.0;$courierCompany=null;$courierType=null;$courierService=null;$collection=null;
  if($cart){
   $address=$this->address((int)($b['address_id']??0),(int)$u['id']);
   try{$rates=Biteship::quote($address,$this->items($cart));}catch(\Throwable $e){$this->error($e->getMessage(),422);}
   $match=null;foreach($rates as $r)if(($r['company']??'')===($b['courier_company']??'')&&($r['type']??'')===($b['courier_type']??'')){$match=$r;break;}
   if(!$match)$this->error('Pilihan ongkir tidak lagi tersedia',422);
   $shipping=(float)($match['price']??0);
   $recipient=$this->snapshot($address);
   $courierCompany=$match['company']??null;$courierType=$match['type']??null;$courierService=$match['courier_service_name']??null;
   $collection=(isset($match['available_collection_method'])&&is_array($match['available_collection_method']))?json_encode($match['available_collection_method']):null;
  } else {
   $recipient=['recipientName'=>(string)($u['name']??''),'recipientPhone'=>'','addressLine'=>'Pesanan digital (foto event)','notes'=>'','areaId'=>null,'areaName'=>null,'postalCode'=>null,'latitude'=>0,'longitude'=>0];
  }
  $subtotal=$productSubtotal+$eventSubtotal;$total=$subtotal+$shipping;
  $this->db()->beginTransaction();
  try{
   $id=(int)$this->db()->insert('vp_orders',['order_number'=>'VP'.date('Ymd').strtoupper(bin2hex(random_bytes(4))),'customer_id'=>(int)$u['id'],'address_id'=>$address?(int)$address['id']:null,'status'=>'pending_payment','subtotal'=>$subtotal,'shipping_cost'=>$shipping,'total'=>$total,'recipient_snapshot'=>json_encode($recipient),'courier_company'=>$courierCompany,'courier_type'=>$courierType,'courier_service'=>$courierService,'available_collection_method'=>$collection,'created_at'=>$now,'updated_at'=>$now]);
   foreach($cart as $row){
    $this->db()->insert('vp_order_items',['order_id'=>$id,'item_type'=>'product','configuration_id'=>(int)$row['configuration_id'],'product_name'=>$row['name'],'note'=>trim((string)($row['note']??''))?:null,'selections_snapshot'=>$row['selections_json'],'quantity'=>(int)$row['quantity'],'unit_price'=>$row['unit_price'],'total_price'=>(float)$row['unit_price']*(int)$row['quantity']]);
    $this->db()->update('vp_product_configurations',['status'=>'ordered','updated_at'=>$now],['id'=>(int)$row['configuration_id']]);
   }
   $byEvent=[];foreach($photos as $p)$byEvent[(int)$p['event_id']][]=$p;
   foreach($byEvent as $eventId=>$list){
    $sum=0.0;foreach($list as $p)$sum+=$this->eventPhotoPrice($p);
    $ev=$this->db()->query('SELECT name FROM vp_events WHERE id=? LIMIT 1',[(int)$eventId])->row_array();
    $itemId=(int)$this->db()->insert('vp_order_items',['order_id'=>$id,'item_type'=>'event_photo','configuration_id'=>null,'ref_id'=>(int)$eventId,'product_name'=>($ev['name']??('Event #'.$eventId)),'selections_snapshot'=>null,'quantity'=>count($list),'unit_price'=>$sum,'total_price'=>$sum]);
    foreach($list as $p)$this->db()->insert('vp_order_item_photos',['order_item_id'=>$itemId,'photo_id'=>(int)$p['photo_id'],'variant'=>$p['variant'],'price'=>$this->eventPhotoPrice($p),'is_free'=>((int)($p['free_claimed']??0))===1?1:0,'created_at'=>$now]);
   }
   $this->db()->query('DELETE FROM vp_cart_items WHERE customer_id=?',[(int)$u['id']]);
   $this->db()->query('DELETE FROM vp_cart_event_photos WHERE customer_id=?',[(int)$u['id']]);
   $this->db()->commit();
   $orderNumber=$this->db()->query('SELECT order_number FROM vp_orders WHERE id=?',[$id])->row_array()['order_number'];
   $this->success(['id'=>$id,'orderNumber'=>$orderNumber,'total'=>$total],'Order created');
  }catch(\Throwable $e){$this->db()->rollback();$this->error('Checkout gagal: '.$e->getMessage(),500);}
 }
 private function customer():array{$u=CustomerAuth::user();if(!$u)$this->error('Unauthorized',401);return $u;}
 private function address(int $id,int $c):array{$r=$this->db()->query('SELECT * FROM vp_customer_addresses WHERE id=? AND customer_id=? LIMIT 1',[$id,$c])->row_array();if(!$r)$this->error('Alamat tidak ditemukan',404);return $r;}
 private function cart(int $id):array{return $this->db()->query('SELECT ci.*,c.selections_json,c.note,p.name,p.weight_grams,p.length_mm,p.width_mm,p.height_mm FROM vp_cart_items ci INNER JOIN vp_product_configurations c ON c.id=ci.configuration_id INNER JOIN vp_products p ON p.id=c.product_id WHERE ci.customer_id=?',[$id])->result_array()?:[];}
 private function eventPhotos(int $id):array{return $this->db()->query('SELECT id,event_id,photo_id,variant,unit_price,free_claimed FROM vp_cart_event_photos WHERE customer_id=? ORDER BY event_id,id',[$id])->result_array()?:[];}
 private function subtotal(array $c):float{return array_sum(array_map(fn($r)=>(float)$r['unit_price']*(int)$r['quantity'],$c));}
 private function eventPhotoPrice(array $p):float{return ((int)($p['free_claimed']??0))===1?0.0:(float)$p['unit_price'];}
 private function eventSubtotal(array $p):float{return array_sum(array_map(fn($r)=>$this->eventPhotoPrice($r),$p));}
 private function eventFreeCount(array $p):int{return count(array_filter($p,fn($r)=>((int)($r['free_claimed']??0))===1));}
 private function eventFreeAmount(array $p):float{return array_sum(array_map(fn($r)=>((int)($r['free_claimed']??0))===1?(float)$r['unit_price']:0.0,$p));}
 private function items(array $c):array{return array_map(fn($r)=>['name'=>$r['name'],'description'=>'Vita Pictura order','value'=>(float)$r['unit_price']*(int)$r['quantity'],'length'=>(int)($r['length_mm']??0),'width'=>(int)($r['width_mm']??0),'height'=>(int)($r['height_mm']??0),'weight'=>(int)($r['weight_grams']??0),'quantity'=>(int)$r['quantity']],$c);}
 private function snapshot(array $a):array{return ['recipientName'=>$a['recipient_name'],'recipientPhone'=>$a['recipient_phone'],'addressLine'=>$a['address_line'],'notes'=>trim((string)($a['notes']??'')),'areaId'=>$a['area_id'],'areaName'=>$a['area_name'],'provinceName'=>$a['province_name']??null,'regencyName'=>$a['regency_name']??null,'districtName'=>$a['district_name']??null,'villageName'=>$a['village_name']??null,'postalCode'=>$a['postal_code'],'latitude'=>(float)$a['latitude'],'longitude'=>(float)$a['longitude']];}
}
