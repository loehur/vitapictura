<?php
namespace App\Controllers\EventUser;
use App\Core\Controller;use App\Helpers\CustomerAuth;use App\Services\Events;
class Balance extends Controller {
 public function index():void{
  $this->handleCors();$u=$this->customer();
  $prof=$this->db()->query('SELECT balance FROM vp_event_profiles WHERE customer_id=? LIMIT 1',[(int)$u['id']])->row_array();
  $ledger=$this->db()->query('SELECT id,order_id,photo_id,amount,type,note,created_at FROM vp_event_user_ledger WHERE customer_id=? ORDER BY id DESC LIMIT 200',[(int)$u['id']])->result_array()?:[];
  foreach($ledger as &$l){$l['id']=(int)$l['id'];$l['amount']=(float)$l['amount'];}
  unset($l);
  $this->success(['balance'=>$prof?(float)$prof['balance']:0,'ledger'=>$ledger],'Balance loaded');
 }
 private function customer():array{$u=CustomerAuth::user();if(!$u)$this->error('Unauthorized',401);Events::ensureProfile($this->db(),(int)$u['id']);return $u;}
}
