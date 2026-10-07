<?php
namespace App\Controllers\Store;
use App\Core\Controller;
class Events extends Controller {
 public function index():void{
  $this->handleCors();
  $from=trim((string)$this->query('from',''));$to=trim((string)$this->query('to',''));
  if($from===''&&$to===''){$to=date('Y-m-d');$from=date('Y-m-d',strtotime('-6 days'));}
  if($from==='')$from=$to; if($to==='')$to=$from;
  $f=strtotime($from);$t=strtotime($to);
  if($f===false||$t===false)$this->error('Rentang tanggal tidak valid',422);
  if($f>$t){$tmp=$f;$f=$t;$t=$tmp;}
  if(($t-$f)>6*86400){$f=$t-6*86400;} // maks 7 hari inklusif
  $fromDate=date('Y-m-d',$f);$toDate=date('Y-m-d',$t);
  $q=trim((string)$this->query('q',''));
  $sql="SELECT e.id,e.name,e.slug,e.description,e.cover_image_url,e.event_date,u.name owner_name,(SELECT COUNT(*) FROM vp_event_photos p WHERE p.event_id=e.id AND p.status='active') photo_count FROM vp_events e INNER JOIN vp_customers u ON u.id=e.customer_id WHERE e.status='published' AND e.event_date BETWEEN ? AND ?";
  $params=[$fromDate,$toDate];
  if($q!==''){$sql.=' AND e.name LIKE ?';$params[]='%'.$q.'%';}
  $sql.=' ORDER BY e.event_date DESC,e.id DESC LIMIT 200';
  $rows=$this->db()->query($sql,$params)->result_array()?:[];
  foreach($rows as &$r){$r['id']=(int)$r['id'];$r['photo_count']=(int)$r['photo_count'];}
  unset($r);
  $this->success(['items'=>$rows,'from'=>$fromDate,'to'=>$toDate],'Events loaded');
 }
 public function show($slug=null):void{
  $this->handleCors();
  $slug=trim((string)($slug!==null&&$slug!==''?$slug:$this->query('slug','')));
  if($slug==='')$this->error('Event tidak ditemukan',404);
  $e=$this->db()->query("SELECT e.id,e.name,e.slug,e.description,e.cover_image_url,e.event_date,e.default_price_standard,e.default_price_original,u.name owner_name FROM vp_events e INNER JOIN vp_customers u ON u.id=e.customer_id WHERE e.slug=? AND e.status='published' LIMIT 1",[$slug])->row_array();
  if(!$e)$this->error('Event tidak ditemukan',404);
  $e['id']=(int)$e['id'];
  $e['default_price_standard']=$e['default_price_standard']!==null?(float)$e['default_price_standard']:null;
  $e['default_price_original']=$e['default_price_original']!==null?(float)$e['default_price_original']:null;
  $photos=$this->db()->query("SELECT id,preview_key,price_standard,price_original,width,height,original_uploaded FROM vp_event_photos WHERE event_id=? AND status='active' ORDER BY sort_order,id",[(int)$e['id']])->result_array()?:[];
  $ps=[];
  foreach($photos as $p){
   $std=$p['price_standard']!==null?(float)$p['price_standard']:$e['default_price_standard'];
   $orig=$p['price_original']!==null?(float)$p['price_original']:$e['default_price_original'];
   $ps[]=['id'=>(int)$p['id'],'previewUrl'=>\App\Services\Events::previewUrl($p['preview_key']),'priceStandard'=>$std,'priceOriginal'=>$orig,'originalReady'=>((int)$p['original_uploaded'])===1,'width'=>$p['width']!==null?(int)$p['width']:null,'height'=>$p['height']!==null?(int)$p['height']:null];
  }
  $e['photos']=$ps;
  $this->success($e,'Event loaded');
 }
}
