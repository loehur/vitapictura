<?php
namespace App\Controllers\Webhook;
use App\Core\Controller;
use App\Services\Biteship;
class Midtrans extends Controller {
 public function notification():void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);
  $b=$this->getBody();$key=(string)(\Env::MIDTRANS_SERVER_KEY??'');if($key==='')$this->error('Webhook is not configured',503);
  $signature=hash('sha512',(string)($b['order_id']??'').(string)($b['status_code']??'').(string)($b['gross_amount']??'').$key);
  if(empty($b['signature_key'])||!hash_equals($signature,(string)$b['signature_key']))$this->error('Invalid Midtrans signature',401);
  $order=$this->db()->query('SELECT * FROM vp_orders WHERE order_number=? LIMIT 1',[(string)$b['order_id']])->row_array();if(!$order)$this->error('Order not found',404);
  if(number_format((float)$order['total'],2,'.','')!==number_format((float)($b['gross_amount']??-1),2,'.',''))$this->error('Payment amount mismatch',422);
  $tx=(string)($b['transaction_status']??'');$fraud=(string)($b['fraud_status']??'accept');
  $paymentStatus=in_array($tx,['settlement','capture'],true)&&$fraud!=='deny'?'paid':(in_array($tx,['expire'],true)?'expired':(in_array($tx,['cancel','deny','failure'],true)?'failed':'pending'));
  $orderStatus=$paymentStatus==='paid'?'paid':($paymentStatus==='expired'?'expired':($paymentStatus==='failed'?'cancelled':'pending_payment'));
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  $this->db()->beginTransaction();
  try{$this->db()->update('vp_payments',['transaction_id'=>(string)($b['transaction_id']??''),'payment_type'=>(string)($b['payment_type']??''),'status'=>$paymentStatus,'raw_notification'=>json_encode($b),'paid_at'=>$paymentStatus==='paid'?$now:null,'updated_at'=>$now],['order_id'=>(int)$order['id'],'provider'=>'midtrans']);$this->db()->update('vp_orders',['status'=>$orderStatus,'updated_at'=>$now],['id'=>(int)$order['id']]);$this->db()->commit();}catch(\Throwable $e){$this->db()->rollback();$this->error('Webhook processing failed',500);}
  if($paymentStatus==='paid')$this->bookBiteship($order,$now);
  $this->success(null,'Notification processed');
 }
 private function bookBiteship(array $order,string $now):void{
  if(empty($order['courier_company'])||empty($order['courier_type']))return;
  if($this->db()->query('SELECT id FROM vp_order_deliveries WHERE order_id=? LIMIT 1',[(int)$order['id']])->row_array())return;
  try{
   $settings=[];$rows=$this->db()->query('SELECT name,value FROM vp_settings')->result_array()?:[];foreach($rows as $r)$settings[$r['name']]=$r['value'];
   $items=$this->db()->query('SELECT oi.quantity,oi.total_price,p.name,p.weight_grams,p.length_mm,p.width_mm,p.height_mm FROM vp_order_items oi INNER JOIN vp_product_configurations c ON c.id=oi.configuration_id INNER JOIN vp_products p ON p.id=c.product_id WHERE oi.order_id=?',[(int)$order['id']])->result_array()?:[];
   $bItems=array_map(fn($it)=>['name'=>(string)$it['name'],'description'=>'Vita Pictura','category'=>'others','value'=>(float)$it['total_price'],'quantity'=>(int)$it['quantity'],'weight'=>max(1,(int)$it['weight_grams']),'length'=>max(1,(int)$it['length_mm']),'width'=>max(1,(int)$it['width_mm']),'height'=>max(1,(int)$it['height_mm'])],$items);
   $recipient=json_decode((string)$order['recipient_snapshot'],true)?:[];
   $originName=(string)($settings['origin_name']??'Vita Pictura');$originPhone=(string)($settings['origin_contact_phone']??'');$originAddress=(string)($settings['origin_address']??'');$originPostal=(int)($settings['postal_code']??0);$organization=(string)($settings['store_name']??'Vita Pictura');
   $payload=['shipper_contact_name'=>$originName,'shipper_contact_phone'=>$originPhone,'shipper_contact_email'=>'','shipper_organization'=>$organization,'origin_contact_name'=>$originName,'origin_contact_phone'=>$originPhone,'origin_address'=>$originAddress,'origin_postal_code'=>$originPostal,'origin_coordinate'=>['latitude'=>(float)\Env::BITESHIP_ORIGIN_LATITUDE,'longitude'=>(float)\Env::BITESHIP_ORIGIN_LONGITUDE],'destination_contact_name'=>(string)($recipient['recipientName']??''),'destination_contact_phone'=>(string)($recipient['recipientPhone']??''),'destination_contact_email'=>'','destination_address'=>(string)($recipient['addressLine']??''),'destination_postal_code'=>(int)($recipient['postalCode']??0),'destination_coordinate'=>['latitude'=>(float)($recipient['latitude']??0),'longitude'=>(float)($recipient['longitude']??0)],'courier_company'=>(string)$order['courier_company'],'courier_type'=>(string)$order['courier_type'],'delivery_type'=>'now','order_note'=>'Vita Pictura '.$order['order_number'],'metadata'=>['order_number'=>$order['order_number']],'items'=>$bItems];
   $res=Biteship::createOrder($payload);
   $this->db()->insert('vp_order_deliveries',['order_id'=>(int)$order['id'],'biteship_order_id'=>(string)($res['id']??''),'tracking_number'=>(string)($res['courier']['waybill_id']??$res['courier_waybill_id']??'')?:null,'courier_company'=>(string)$order['courier_company'],'courier_service'=>(string)($order['courier_service']??'')?:null,'status'=>(string)($res['status']??'confirmed'),'tracking_payload'=>json_encode($res),'created_at'=>$now,'updated_at'=>$now]);
  }catch(\Throwable $e){error_log('Biteship booking failed: '.$e->getMessage());}
 }
}
