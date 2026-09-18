<?php
namespace App\Controllers\Admin;use App\Core\Controller;use App\Helpers\AdminAuth;
class Settings extends Controller {
 private const KEYS=['store_name','wa_number','origin_name','origin_contact_phone','origin_address','postal_code','couriers'];
 public function index():void{$this->handleCors();$this->admin();$rows=$this->db()->query('SELECT name,value FROM vp_settings')->result_array()?:[];$out=[];foreach($rows as $r)$out[$r['name']]=$r['value'];$this->success($out,'Settings loaded');}
 public function save():void{$this->handleCors();if(!$this->isPost())$this->error('Method not allowed',405);$this->admin();$b=$this->getBody();$now=$GLOBALS['now']??date('Y-m-d H:i:s');foreach(self::KEYS as $k){if(!array_key_exists($k,$b))continue;$v=trim((string)$b[$k]);$st=$this->db()->query('SELECT name FROM vp_settings WHERE name=? LIMIT 1',[$k])->row_array();if($st)$this->db()->update('vp_settings',['value'=>$v,'updated_at'=>$now],['name'=>$k]);else $this->db()->insert('vp_settings',['name'=>$k,'value'=>$v,'updated_at'=>$now]);}$this->success(null,'Pengaturan disimpan');}
 private function admin():array{$a=AdminAuth::user();if(!$a)$this->error('Unauthorized',401);return $a;}
}
