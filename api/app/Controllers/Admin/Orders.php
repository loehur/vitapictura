<?php
namespace App\Controllers\Admin;
use App\Core\Controller;use App\Helpers\AdminAuth;use App\Services\Shipping;use App\Services\WhatsApp;use App\Services\Biteship;

class Orders extends Controller {
 public function index():void{
  $this->handleCors();$this->admin();
  $rows=$this->db()->query('SELECT o.id,o.order_number,o.status,o.subtotal,o.shipping_cost,o.total,o.created_at,o.admin_note,o.courier_company,o.courier_type,o.courier_service,o.available_collection_method,o.recipient_snapshot,o.discount_shipping,o.discount_items,o.discount_promo,c.full_name customer_name,c.phone customer_phone,p.status payment_status,p.payment_type,p.paid_at,d.tracking_number,d.status delivery_status,d.biteship_order_id,d.collection_method,d.price_paid FROM vp_orders o INNER JOIN vp_customers c ON c.id=o.customer_id LEFT JOIN vp_payments p ON p.order_id=o.id LEFT JOIN vp_order_deliveries d ON d.order_id=o.id ORDER BY o.created_at DESC LIMIT 100')->result_array()?:[];
  $ids=array_map(fn($r)=>(int)$r['id'],$rows);
  $itemsByOrder=[];
  if(!empty($ids)){
   $its=$this->db()->query('SELECT order_id,product_name,quantity,unit_price,total_price FROM vp_order_items WHERE order_id IN ('.implode(',',$ids).') ORDER BY id')->result_array()?:[];
   foreach($its as $it){$itemsByOrder[(int)$it['order_id']][]=['product_name'=>$it['product_name'],'quantity'=>(int)$it['quantity'],'unit_price'=>(float)$it['unit_price'],'total_price'=>(float)$it['total_price']];}
  }
  foreach($rows as &$r){
   $r['id']=(int)$r['id'];
   $r['subtotal']=(float)$r['subtotal'];$r['shipping_cost']=(float)$r['shipping_cost'];$r['total']=(float)$r['total'];
   $r['discount_shipping']=(float)($r['discount_shipping']??0);$r['discount_items']=(float)($r['discount_items']??0);$r['discount_promo']=(float)($r['discount_promo']??0);
   $r['recipient']=json_decode((string)($r['recipient_snapshot']??''),true)?:[];unset($r['recipient_snapshot']);
   $r['available_collection_method']=json_decode((string)($r['available_collection_method']??''),true)?:[];
   $r['items']=$itemsByOrder[$r['id']]??[];
  }
  unset($r);
  $expiry=defined('Env::ORDER_EXPIRY_HOURS')?(int)\Env::ORDER_EXPIRY_HOURS:25;
  $this->success(['items'=>$rows,'expiryHours'=>$expiry],'Orders loaded');
 }

 public function update_status($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$this->admin();
  $status=(string)($this->getBody()['status']??'');
  if(!in_array($status,['pending_payment','paid','processing','shipped','completed','cancelled','expired'],true))$this->error('Invalid status',422);
  $ok=$this->db()->update('vp_orders',['status'=>$status,'updated_at'=>$GLOBALS['now']??date('Y-m-d H:i:s')],['id'=>(int)$id]);
  if(!$ok)$this->error('Order not found',404);
  $this->success(null,'Order status updated');
 }

 public function save_delivery($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$this->admin();
  $b=$this->getBody();
  $order=$this->db()->query('SELECT id FROM vp_orders WHERE id=? LIMIT 1',[(int)$id])->row_array();
  if(!$order)$this->error('Order not found',404);
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  $data=['tracking_number'=>trim((string)($b['tracking_number']??''))?:null,'courier_company'=>trim((string)($b['courier_company']??''))?:null,'courier_service'=>trim((string)($b['courier_service']??''))?:null,'status'=>trim((string)($b['status']??'shipped')),'shipped_at'=>$now,'updated_at'=>$now];
  $existing=$this->db()->query('SELECT id FROM vp_order_deliveries WHERE order_id=? LIMIT 1',[(int)$id])->row_array();
  if($existing)$this->db()->update('vp_order_deliveries',$data,['id'=>(int)$existing['id']]);
  else $this->db()->insert('vp_order_deliveries',$data+['order_id'=>(int)$id,'created_at'=>$now]);
  $this->db()->update('vp_orders',['status'=>'shipped','updated_at'=>$now],['id'=>(int)$id]);
  $this->success(null,'Delivery saved');
 }

 public function show($id=null):void{
  $this->handleCors();$this->admin();$id=(int)$id;
  $o=$this->db()->query('SELECT o.*,c.full_name customer_name,c.email customer_email,c.phone customer_phone FROM vp_orders o INNER JOIN vp_customers c ON c.id=o.customer_id WHERE o.id=? LIMIT 1',[$id])->row_array();
  if(!$o)$this->error('Order not found',404);
  $o['id']=(int)$o['id'];$o['subtotal']=(float)$o['subtotal'];$o['shipping_cost']=(float)$o['shipping_cost'];$o['total']=(float)$o['total'];
  $o['discount_shipping']=(float)($o['discount_shipping']??0);$o['discount_items']=(float)($o['discount_items']??0);$o['discount_promo']=(float)($o['discount_promo']??0);
  $o['available_collection_method']=json_decode((string)($o['available_collection_method']??''),true)?:[];
  $o['recipient']=json_decode((string)($o['recipient_snapshot']??''),true)?:[];unset($o['recipient_snapshot']);
  $items=$this->db()->query('SELECT id,product_name,selections_snapshot,quantity,unit_price,total_price FROM vp_order_items WHERE order_id=? ORDER BY id',[$id])->result_array()?:[];
  foreach($items as &$it){$it['id']=(int)$it['id'];$it['quantity']=(int)$it['quantity'];$it['unit_price']=(float)$it['unit_price'];$it['total_price']=(float)$it['total_price'];$it['selections']=json_decode((string)($it['selections_snapshot']??''),true)?:[];unset($it['selections_snapshot']);}
  unset($it);$o['items']=$items;
  $p=$this->db()->query('SELECT status,transaction_id,payment_type,gross_amount,paid_at FROM vp_payments WHERE order_id=? LIMIT 1',[$id])->row_array();
  if($p)$p['gross_amount']=(float)$p['gross_amount'];$o['payment']=$p;
  $d=$this->db()->query('SELECT tracking_number,courier_company,courier_service,status,shipped_at,delivered_at,biteship_order_id,collection_method,price_paid,tracking_history FROM vp_order_deliveries WHERE order_id=? LIMIT 1',[$id])->row_array();
  if($d){$d['price_paid']=isset($d['price_paid'])?(float)$d['price_paid']:null;$d['tracking_history']=json_decode((string)($d['tracking_history']??''),true)?:[];}
  $o['delivery']=$d;
  $this->success($o,'Order loaded');
 }

 public function book($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$this->admin();$id=(int)$id;
  $method=trim((string)($this->getBody()['collection_method']??''));
  $order=$this->db()->query('SELECT * FROM vp_orders WHERE id=? LIMIT 1',[$id])->row_array();
  if(!$order)$this->error('Order not found',404);
  try{
   $r=Shipping::bookBiteship($this->db(),$order,$method);
   if(in_array($order['status'],['paid','processing','pending_payment'],true))$this->db()->update('vp_orders',['status'=>'shipped','updated_at'=>$GLOBALS['now']??date('Y-m-d H:i:s')],['id'=>$id]);
   $this->success($r,'Pesanan dikirim');
  }catch(\Throwable $e){$this->error($e->getMessage(),422);}
 }

 /** Tarik riwayat tracking Biteship; auto-selesai bila statusnya delivered. */
 public function tracking($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$this->admin();$id=(int)$id;
  $d=$this->db()->query('SELECT id,order_id,biteship_order_id FROM vp_order_deliveries WHERE order_id=? LIMIT 1',[$id])->row_array();
  if(!$d)$this->error('Data pengiriman belum ada',404);
  if(empty($d['biteship_order_id']))$this->error('Pesanan belum punya ID Biteship',422);
  try{$res=Biteship::tracking((string)$d['biteship_order_id']);}catch(\Throwable $e){$this->error($e->getMessage(),422);}
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  $status=strtolower((string)($res['status']??''));
  $history=(isset($res['history'])&&is_array($res['history']))?$res['history']:[];
  $this->db()->update('vp_order_deliveries',['tracking_history'=>json_encode($history),'status'=>$status!==''?$status:'shipped','delivered_at'=>$status==='delivered'?$now:null,'updated_at'=>$now],['id'=>(int)$d['id']]);
  if($status==='delivered')$this->db()->update('vp_orders',['status'=>'completed','updated_at'=>$now],['id'=>(int)$d['order_id']]);
  $this->success(['status'=>$status,'history'=>$history],'Tracking loaded');
 }

 public function mark_paid($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$this->admin();$id=(int)$id;
  $order=$this->db()->query('SELECT id,order_number FROM vp_orders WHERE id=? LIMIT 1',[$id])->row_array();
  if(!$order)$this->error('Order not found',404);
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  $p=$this->db()->query('SELECT id FROM vp_payments WHERE order_id=? LIMIT 1',[$id])->row_array();
  if($p)$this->db()->update('vp_payments',['status'=>'paid','paid_at'=>$now,'updated_at'=>$now],['id'=>(int)$p['id']]);
  else $this->db()->insert('vp_payments',['order_id'=>$id,'provider'=>'manual','gross_amount'=>0,'status'=>'paid','paid_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
  $this->db()->update('vp_orders',['status'=>'processing','updated_at'=>$now],['id'=>$id]);
  try{WhatsApp::orderReceived($order);}catch(\Throwable $e){error_log('WA order-received failed: '.$e->getMessage());}
  $this->success(null,'Pesanan ditandai lunas');
 }

 public function mark_completed($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$this->admin();$id=(int)$id;
  $order=$this->db()->query('SELECT o.order_number,o.recipient_snapshot,c.phone FROM vp_orders o INNER JOIN vp_customers c ON c.id=o.customer_id WHERE o.id=? LIMIT 1',[$id])->row_array();
  $ok=$this->db()->update('vp_orders',['status'=>'completed','updated_at'=>$GLOBALS['now']??date('Y-m-d H:i:s')],['id'=>$id]);
  if(!$ok)$this->error('Order not found',404);
  if($order){try{WhatsApp::orderCompleted($order);}catch(\Throwable $e){error_log('WA order-completed failed: '.$e->getMessage());}}
  $this->success(null,'Pesanan selesai');
 }

 public function cancel($id=null):void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$this->admin();$id=(int)$id;
  $note=trim((string)($this->getBody()['note']??''))?:null;
  $row=$this->db()->query('SELECT o.id,o.order_number,o.recipient_snapshot,c.phone FROM vp_orders o INNER JOIN vp_customers c ON c.id=o.customer_id WHERE o.id=? LIMIT 1',[$id])->row_array();
  if(!$row)$this->error('Order not found',404);
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  $this->db()->update('vp_orders',['status'=>'cancelled','admin_note'=>$note,'updated_at'=>$now],['id'=>$id]);
  $this->db()->update('vp_payments',['status'=>'cancelled','updated_at'=>$now],['order_id'=>$id]);
  try{WhatsApp::orderCancelled($row,(string)$note);}catch(\Throwable $e){error_log('WA order-cancelled failed: '.$e->getMessage());}
  $this->success(null,'Pesanan dibatalkan');
 }

 private function admin():array{$a=AdminAuth::user();if(!$a)$this->error('Unauthorized',401);return $a;}
}
