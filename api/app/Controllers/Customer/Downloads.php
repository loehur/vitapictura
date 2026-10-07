<?php
namespace App\Controllers\Customer;
use App\Core\Controller;use App\Helpers\CustomerAuth;use App\Services\Events;
class Downloads extends Controller {
 public function photo($id=null):void{
  $this->handleCors();$u=$this->customer();$id=(int)$id;
  $row=$this->db()->query('SELECT oip.id,oip.variant,oip.downloaded_count,p.standard_key,p.standard_mime,p.original_key,p.original_mime,p.original_uploaded,o.customer_id,o.status FROM vp_order_item_photos oip INNER JOIN vp_order_items oi ON oi.id=oip.order_item_id INNER JOIN vp_orders o ON o.id=oi.order_id INNER JOIN vp_event_photos p ON p.id=oip.photo_id WHERE oip.id=? LIMIT 1',[$id])->row_array();
  if(!$row)$this->error('File tidak ditemukan',404);
  if((int)$row['customer_id']!==(int)$u['id'])$this->error('Tidak diizinkan',403);
  if(!in_array($row['status'],['paid','processing','shipped','completed'],true))$this->error('Pesanan belum dibayar',403);
  if($row['variant']==='original'){
   $req=(string)$this->query('variant','original');
   if($req==='standard'){
    $key=$row['standard_key'];$mime=$row['standard_mime']?:'application/octet-stream';
   } else {
    if((int)$row['original_uploaded']!==1||empty($row['original_key']))$this->error('File original belum tersedia',409);
    $key=$row['original_key'];$mime=$row['original_mime']?:'application/octet-stream';
   }
  } else {
   $key=$row['standard_key'];$mime=$row['standard_mime']?:'application/octet-stream';
  }
  $path=Events::privateRoot().'/'.ltrim($key,'/');
  if(!is_file($path))$this->error('File tidak ditemukan di server',404);
  $this->db()->update('vp_order_item_photos',['downloaded_count'=>((int)$row['downloaded_count'])+1],['id'=>$id]);
  $name=basename($key);
  if(ob_get_level())ob_end_clean();
  header('Content-Type: '.$mime);
  header('Content-Disposition: attachment; filename="'.$name.'"');
  header('Content-Length: '.(string)filesize($path));
  header('Cache-Control: private, no-store');
  readfile($path);
  exit;
 }
 private function customer():array{$u=CustomerAuth::user();if(!$u)$this->error('Unauthorized',401);return $u;}
}
