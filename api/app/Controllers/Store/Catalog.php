<?php
namespace App\Controllers\Store;
use App\Core\Controller;

class Catalog extends Controller
{
    public function home(): void { $this->handleCors(); $this->success(['categories'=>$this->categories(), 'featured'=>$this->products(true, 8)], 'Catalog home loaded'); }
    public function categories(): void { $this->handleCors(); $this->success(['items'=>$this->categories()], 'Categories loaded'); }
    public function products(): void { $this->handleCors(); $this->success(['items'=>$this->products(false, max(1,min(48,(int)$this->query('limit',24))))], 'Products loaded'); }
    public function show($slug = null): void {
        $this->handleCors(); $slug=trim((string)($slug ?: $this->query('slug',''))); if($slug==='') $this->error('Product slug is required',422);
        $p=$this->db()->query("SELECT p.*,c.name category_name,c.slug category_slug FROM vp_products p LEFT JOIN vp_categories c ON c.id=p.category_id WHERE p.slug=? AND p.status='published' LIMIT 1",[$slug])->row_array();
        if(!$p) $this->error('Product not found',404); $p=$this->product($p); $p['media']=$this->db()->query('SELECT url,alt_text,sort_order FROM vp_product_media WHERE product_id=? ORDER BY sort_order,id',[(int)$p['id']])->result_array();
        $groups=$this->db()->query('SELECT * FROM vp_product_option_groups WHERE product_id=? ORDER BY sort_order,id',[(int)$p['id']])->result_array(); foreach($groups as &$g){$g['values']=$this->db()->query('SELECT id,name,price_delta,weight_delta_grams FROM vp_product_option_values WHERE option_group_id=? AND is_active=1 ORDER BY sort_order,id',[(int)$g['id']])->result_array();} $p['options']=$groups; $this->success($p,'Product loaded');
    }
    private function categories(): array { return $this->db()->query("SELECT id,name,slug,description,image_url AS image,is_featured FROM vp_categories WHERE status='published' ORDER BY sort_order,name")->result_array() ?: []; }
    private function products(bool $featured,int $limit): array { $sql="SELECT p.*,c.name category_name,c.slug category_slug FROM vp_products p LEFT JOIN vp_categories c ON c.id=p.category_id WHERE p.status='published'"; if($featured)$sql.=' AND p.is_featured=1'; $sql.=' ORDER BY p.is_featured DESC,p.popularity DESC,p.created_at DESC LIMIT '.(int)$limit; return array_map(fn($p)=>$this->product($p),$this->db()->query($sql)->result_array()?:[]); }
    private function product(array $p): array { return ['id'=>(int)$p['id'],'name'=>$p['name'],'slug'=>$p['slug'],'description'=>$p['description']??'','shortDescription'=>$p['short_description']??'','price'=>(float)$p['base_price'],'coverImage'=>$p['cover_image_url']??null,'category'=>['name'=>$p['category_name']??'','slug'=>$p['category_slug']??'']]; }
}
