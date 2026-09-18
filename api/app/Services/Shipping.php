<?php
namespace App\Services;
use App\Core\DB;
class Shipping {
 public static function bookBiteship(DB $db,array $order):array{
  if(empty($order['courier_company'])||empty($order['courier_type']))throw new \RuntimeException('Pesanan belum punya kurir');
  $existing=$db->query('SELECT id,biteship_order_id,tracking_number FROM vp_order_deliveries WHERE order_id=? LIMIT 1',[(int)$order['id']])->row_array();
  if($existing&&!empty($existing['biteship_order_id']))return ['id'=>(int)$existing['id'],'biteship_order_id'=>$existing['biteship_order_id'],'tracking_number'=>$existing['tracking_number'],'status'=>'existing'];
  $settings=[];$rows=$db->query('SELECT name,value FROM vp_settings')->result_array()?:[];foreach($rows as $r)$settings[$r['name']]=$r['value'];
  $items=$db->query('SELECT oi.quantity,oi.total_price,p.name,p.weight_grams,p.length_mm,p.width_mm,p.height_mm FROM vp_order_items oi INNER JOIN vp_product_configurations c ON c.id=oi.configuration_id INNER JOIN vp_products p ON p.id=c.product_id WHERE oi.order_id=?',[(int)$order['id']])->result_array()?:[];
  $bItems=array_map(fn($it)=>['name'=>(string)$it['name'],'description'=>'Vita Pictura','category'=>'others','value'=>(float)$it['total_price'],'quantity'=>(int)$it['quantity'],'weight'=>max(1,(int)$it['weight_grams']),'length'=>max(1,(int)$it['length_mm']),'width'=>max(1,(int)$it['width_mm']),'height'=>max(1,(int)$it['height_mm'])],$items);
  $recipient=json_decode((string)$order['recipient_snapshot'],true)?:[];
  $originName=(string)($settings['origin_name']??'Vita Pictura');$originPhone=(string)($settings['origin_contact_phone']??'');$originAddress=(string)($settings['origin_address']??'');$originPostal=(int)($settings['postal_code']??0);$organization=(string)($settings['store_name']??'Vita Pictura');
  $payload=['shipper_contact_name'=>$originName,'shipper_contact_phone'=>$originPhone,'shipper_contact_email'=>'','shipper_organization'=>$organization,'origin_contact_name'=>$originName,'origin_contact_phone'=>$originPhone,'origin_address'=>$originAddress,'origin_postal_code'=>$originPostal,'origin_coordinate'=>['latitude'=>(float)\Env::BITESHIP_ORIGIN_LATITUDE,'longitude'=>(float)\Env::BITESHIP_ORIGIN_LONGITUDE],'destination_contact_name'=>(string)($recipient['recipientName']??''),'destination_contact_phone'=>(string)($recipient['recipientPhone']??''),'destination_contact_email'=>'','destination_address'=>(string)($recipient['addressLine']??''),'destination_postal_code'=>(int)($recipient['postalCode']??0),'destination_coordinate'=>['latitude'=>(float)($recipient['latitude']??0),'longitude'=>(float)($recipient['longitude']??0)],'courier_company'=>(string)$order['courier_company'],'courier_type'=>(string)$order['courier_type'],'delivery_type'=>'now','order_note'=>'Vita Pictura '.$order['order_number'],'metadata'=>['order_number'=>$order['order_number']],'items'=>$bItems];
  $res=Biteship::createOrder($payload);
  $now=date('Y-m-d H:i:s');
  $data=['biteship_order_id'=>(string)($res['id']??''),'tracking_number'=>(string)($res['courier']['waybill_id']??$res['courier_waybill_id']??'')?:null,'courier_company'=>(string)$order['courier_company'],'courier_service'=>(string)($order['courier_service']??'')?:null,'status'=>(string)($res['status']??'confirmed'),'tracking_payload'=>json_encode($res),'updated_at'=>$now];
  if($existing){$db->update('vp_order_deliveries',$data,['id'=>(int)$existing['id']]);$deliveryId=(int)$existing['id'];}
  else{$data['order_id']=(int)$order['id'];$data['created_at']=$now;$deliveryId=(int)$db->insert('vp_order_deliveries',$data);}
  return ['id'=>$deliveryId,'biteship_order_id'=>$data['biteship_order_id'],'tracking_number'=>$data['tracking_number'],'status'=>$data['status'],'response'=>$res];
 }
}
