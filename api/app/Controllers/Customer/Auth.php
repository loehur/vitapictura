<?php
namespace App\Controllers\Customer;
use App\Core\Controller;
use App\Helpers\CustomerAuth;

class Auth extends Controller
{
    public function config(): void { $this->handleCors(); if(!$this->isGet())$this->error('Method not allowed',405); $this->success(['googleClientId'=>(string)(\Env::GOOGLE_OAUTH_CLIENT_ID??'')], 'Authentication configuration loaded'); }
    public function google(): void {
        $this->handleCors(); if(!$this->isPost())$this->error('Method not allowed',405); CustomerAuth::ensureSchema($this->db());
        $credential=trim((string)($this->getBody()['credential']??'')); if($credential==='')$this->error('Google credential is required',422);
        $claims=$this->verifyGoogleCredential($credential); $subject=(string)($claims['sub']??''); $email=strtolower(trim((string)($claims['email']??'')));
        if($subject===''||$email===''||empty($claims['email_verified']))$this->error('Google account email is not verified',401);
        $now=$GLOBALS['now']??date('Y-m-d H:i:s'); $identity=$this->db()->query('SELECT c.* FROM vp_customer_identities i INNER JOIN vp_customers c ON c.id=i.customer_id WHERE i.provider=? AND i.provider_subject=? LIMIT 1',['google',$subject])->row_array();
        if(!$identity){ $customer=$this->db()->query('SELECT * FROM vp_customers WHERE email=? LIMIT 1',[$email])->row_array(); if(!$customer){$id=$this->db()->insert('vp_customers',['email'=>$email,'full_name'=>trim((string)($claims['name']??$email)),'avatar_url'=>(string)($claims['picture']??''),'status'=>'active','created_at'=>$now,'updated_at'=>$now]);$customer=$this->db()->query('SELECT * FROM vp_customers WHERE id=? LIMIT 1',[(int)$id])->row_array();} $this->db()->insert('vp_customer_identities',['customer_id'=>(int)$customer['id'],'provider'=>'google','provider_subject'=>$subject,'provider_email'=>$email,'email_verified_at'=>$now,'created_at'=>$now,'updated_at'=>$now]); $identity=$customer; }
        if(($identity['status']??'active')!=='active')$this->error('Customer account is unavailable',403); $this->db()->update('vp_customers',['full_name'=>trim((string)($claims['name']??$identity['full_name'])),'avatar_url'=>(string)($claims['picture']??($identity['avatar_url']??'')),'last_login_at'=>$now,'updated_at'=>$now],['id'=>(int)$identity['id']]); $fresh=$this->db()->query('SELECT * FROM vp_customers WHERE id=? LIMIT 1',[(int)$identity['id']])->row_array(); CustomerAuth::login($fresh); $this->success(CustomerAuth::public($fresh),'Login berhasil');
    }
    public function me(): void { $this->handleCors(); if(!$this->isGet())$this->error('Method not allowed',405); $u=CustomerAuth::user(); if(!$u)$this->error('Unauthorized',401); $this->success($u,'Authenticated'); }
    public function logout(): void { $this->handleCors(); if(!$this->isPost())$this->error('Method not allowed',405); CustomerAuth::logout(); $this->success(null,'Logout berhasil'); }
    private function verifyGoogleCredential(string $credential): array {
        $clientId=(string)(\Env::GOOGLE_OAUTH_CLIENT_ID??''); if($clientId==='')$this->error('Google OAuth is not configured',503);
        if(!function_exists('curl_init'))$this->error('Server does not support Google login',500); $url='https://oauth2.googleapis.com/tokeninfo?id_token='.rawurlencode($credential); $curl=curl_init($url); curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]); $body=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl); $claims=json_decode((string)$body,true); if($status!==200||!is_array($claims))$this->error('Google credential is invalid',401); if(($claims['aud']??'')!==$clientId||!in_array($claims['iss']??'',['accounts.google.com','https://accounts.google.com'],true)||(int)($claims['exp']??0)<time())$this->error('Google credential validation failed',401); return $claims;
    }
}
