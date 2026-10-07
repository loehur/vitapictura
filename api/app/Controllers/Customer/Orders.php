<?php
namespace App\Controllers\Customer;
use App\Core\Controller;use App\Helpers\CustomerAuth;use App\Services\Events;
class Orders extends Controller {
 public function index():void{$this->handleCors();if(!$this->isGet())$this->error('Method not allowed',405);$u=$this->customer();$status=trim((string)$this->query('status',''));$sql='SELECT o.id,o.order_number,o.status,o.total,o.created_at,p.status payment_status,d.tracking_number,d.status delivery_status FROM vp_orders o LEFT JOIN vp_payments p ON p.order_id=o.id LEFT JOIN vp_order_deliveries d ON d.order_id=o.id WHERE o.customer_id=?';$params=[(int)$u['id']];if($status!==''){$sql.=' AND o.status=?';$params[]=$status;}$sql.=' ORDER BY o.created_at DESC';$this->success(['items'=>$this->db()->query($sql,$params)->result_array()?:[]],'Orders loaded');}
 public function show($id=null):void{
  $this->handleCors();if(!$this->isGet())$this->error('Method not allowed',405);$u=$this->customer();
  $o=$this->db()->query('SELECT o.*,p.status payment_status,p.payment_type,p.paid_at,d.tracking_number,d.status delivery_status,d.tracking_payload,d.shipped_at,d.delivered_at FROM vp_orders o LEFT JOIN vp_payments p ON p.order_id=o.id LEFT JOIN vp_order_deliveries d ON d.order_id=o.id WHERE o.id=? AND o.customer_id=? LIMIT 1',[(int)$id,(int)$u['id']])->row_array();
  if(!$o)$this->error('Order not found',404);
  $paid=in_array($o['status'],['paid','processing','shipped','completed'],true)||($o['payment_status']??'')==='paid';
  $rows=$this->db()->query('SELECT id,item_type,ref_id,product_name,note,selections_snapshot,quantity,unit_price,total_price FROM vp_order_items WHERE order_id=? ORDER BY id',[(int)$o['id']])->result_array()?:[];
  $items=[];
  foreach($rows as $it){
   $it['id']=(int)$it['id'];$it['item_type']=$it['item_type']?:'product';$it['quantity']=(int)$it['quantity'];$it['unit_price']=(float)$it['unit_price'];$it['total_price']=(float)$it['total_price'];
   if($it['item_type']==='event_photo'){
    $it['ref_id']=$it['ref_id']!==null?(int)$it['ref_id']:null;$it['selections']=[];
    $ph=$this->db()->query('SELECT oip.id,oip.photo_id,oip.variant,oip.price,p.preview_key,p.original_uploaded FROM vp_order_item_photos oip INNER JOIN vp_event_photos p ON p.id=oip.photo_id WHERE oip.order_item_id=? ORDER BY oip.id',[(int)$it['id']])->result_array()?:[];
    $pl=[];
    foreach($ph as $p){$pl[]=['id'=>(int)$p['id'],'photoId'=>(int)$p['photo_id'],'variant'=>$p['variant'],'price'=>(float)$p['price'],'previewUrl'=>Events::previewUrl($p['preview_key']),'originalUploaded'=>((int)$p['original_uploaded'])===1,'canDownloadStandard'=>$paid,'canDownloadOriginal'=>$paid&&((int)$p['original_uploaded'])===1];}
    $it['photos']=$pl;
   } else {
    $it['selections']=json_decode((string)($it['selections_snapshot']??''),true)?:[];unset($it['selections_snapshot']);
   }
   $items[]=$it;
  }
  $o['items']=$items;
  $o['recipient']=json_decode((string)($o['recipient_snapshot']??''),true)?:[];$o['tracking']=json_decode((string)($o['tracking_payload']??''),true);unset($o['recipient_snapshot'],$o['tracking_payload']);
  $this->success($o,'Order loaded');
 }
 private function customer():array{$u=CustomerAuth::user();if(!$u)$this->error('Unauthorized',401);return $u;}
}
