<?php
namespace App\Controllers\Webhook;
use App\Core\Controller;
use App\Services\WhatsApp;
class Midtrans extends Controller {
 public function notification():void{
  $this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);
  $b=$this->getBody();
  $order=$this->db()->query('SELECT * FROM vp_orders WHERE order_number=? LIMIT 1',[(string)($b['order_id']??'')])->row_array();
  if(!$order)$this->success(null,'Notification ignored');
  $key=(string)(\Env::MIDTRANS_SERVER_KEY??'');if($key==='')$this->error('Webhook is not configured',503);
  $signature=hash('sha512',(string)($b['order_id']??'').(string)($b['status_code']??'').(string)($b['gross_amount']??'').$key);
  if(empty($b['signature_key'])||!hash_equals($signature,(string)$b['signature_key']))$this->error('Invalid Midtrans signature',401);
  if(number_format((float)$order['total'],2,'.','')!==number_format((float)($b['gross_amount']??-1),2,'.',''))$this->error('Payment amount mismatch',422);
  $tx=(string)($b['transaction_status']??'');$fraud=(string)($b['fraud_status']??'accept');
  $paid=in_array($tx,['settlement','capture'],true)&&$fraud!=='deny';
  $paymentStatus=$paid?'paid':(in_array($tx,['expire'],true)?'expired':(in_array($tx,['refund','partial_refund'],true)?'cancelled':(in_array($tx,['cancel','deny','failure'],true)?'failed':'pending')));
  $orderStatus=$paymentStatus==='paid'?'paid':($paymentStatus==='expired'?'expired':(($paymentStatus==='failed'||$paymentStatus==='cancelled')?'cancelled':'pending_payment'));
  $now=$GLOBALS['now']??date('Y-m-d H:i:s');
  $expiry=!empty($b['expiry_time'])?(string)$b['expiry_time']:null;
  $this->db()->beginTransaction();
  try{$this->db()->update('vp_payments',['transaction_id'=>(string)($b['transaction_id']??''),'payment_type'=>(string)($b['payment_type']??''),'status'=>$paymentStatus,'raw_notification'=>json_encode($b),'paid_at'=>$paymentStatus==='paid'?$now:null,'expiry_time'=>$expiry,'updated_at'=>$now],['order_id'=>(int)$order['id'],'provider'=>'midtrans']);$this->db()->update('vp_orders',['status'=>$orderStatus,'updated_at'=>$now],['id'=>(int)$order['id']]);$this->db()->commit();}catch(\Throwable $e){$this->db()->rollback();try{WhatsApp::adminAlert('Webhook Midtrans gagal REF#'.$order['order_number'].': '.$e->getMessage());}catch(\Throwable $e2){error_log('WA webhook-alert failed: '.$e2->getMessage());}$this->error('Webhook processing failed',500);}
  if($paymentStatus==='paid'){try{WhatsApp::orderReceived($order);}catch(\Throwable $e){error_log('WA order-received failed: '.$e->getMessage());}}
  if(in_array($paymentStatus,['failed','expired','cancelled'],true)){try{WhatsApp::adminAlert('Pembayaran '.$paymentStatus.' REF#'.$order['order_number'].' ('.$tx.')');}catch(\Throwable $e){error_log('WA payment-alert failed: '.$e->getMessage());}}
  $this->success(null,'Notification processed');
 }
}
