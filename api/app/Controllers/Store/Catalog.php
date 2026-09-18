<?php
namespace App\Controllers\Store;
use App\Core\Controller;

class Catalog extends Controller
{
    public function home(): void { $this->handleCors(); $this->success(['categories'=>$this->categoryList(), 'featured'=>$this->productList(true, 8)], 'Catalog home loaded'); }
    public function categories(): void { $this->handleCors(); $this->success(['items'=>$this->categoryList()], 'Categories loaded'); }
    public function products(): void { $this->handleCors(); $this->success(['items'=>$this->productList(false, max(1,min(48,(int)$this->query('limit',24))))], 'Products loaded'); }
    public function show($slug = null): void {
        $this->handleCors(); $slug=trim((string)($slug ?: $this->query('slug',''))); if($slug==='') $this->error('Product slug is required',422);
        $raw=$this->db()->query("SELECT p.*,c.name category_name,c.slug category_slug FROM vp_products p LEFT JOIN vp_categories c ON c.id=p.category_id WHERE p.slug=? AND p.status='published' LIMIT 1",[$slug])->row_array();
        if(!$raw) $this->error('Product not found',404);
        $p=$this->product($raw); $pid=(int)$p['id'];
        $p['gallery']=array_map(function($m){return ['key'=>$m['image_key'],'url'=>$m['url'],'suffix'=>$m['image_suffix'],'alt'=>$m['alt_text'],'sortOrder'=>(int)$m['sort_order']];},$this->db()->query('SELECT image_key,image_suffix,url,alt_text,sort_order FROM vp_product_media WHERE product_id=? ORDER BY sort_order,id',[$pid])->result_array()?:[]);
        $groups=$this->db()->query('SELECT id,legacy_vg_id,name,group_level,parent_group_id,is_required,sort_order FROM vp_product_option_groups WHERE product_id=? ORDER BY group_level,sort_order,id',[$pid])->result_array();
        foreach($groups as &$g){$gid=(int)$g['id'];$g['id']=$gid;$g['level']=(int)$g['group_level'];$g['parentGroupId']=$g['parent_group_id']!==null?(int)$g['parent_group_id']:null;$g['isRequired']=(int)$g['is_required']===1;$g['values']=array_map(function($v){return ['id'=>(int)$v['id'],'name'=>$v['name'],'imageSuffix'=>$v['image_suffix'],'parentValueId'=>$v['parent_value_id']!==null?(int)$v['parent_value_id']:null,'priceDelta'=>(float)$v['price_delta'],'weightDelta'=>(int)$v['weight_delta_grams'],'lengthDelta'=>(int)$v['length_delta_mm'],'widthDelta'=>(int)$v['width_delta_mm'],'heightDelta'=>(int)$v['height_delta_mm']];},$this->db()->query('SELECT id,name,image_suffix,parent_value_id,price_delta,weight_delta_grams,length_delta_mm,width_delta_mm,height_delta_mm FROM vp_product_option_values WHERE option_group_id=? AND is_active=1 ORDER BY sort_order,id',[$gid])->result_array()?:[]);unset($g['parent_group_id'],$g['group_level'],$g['is_required'],$g['sort_order'],$g['legacy_vg_id']);} unset($g);
        $p['options']=$groups;
        $p['perluFile']=((int)($raw['perlu_file']??0))===1;
        $p['mal']=json_decode((string)($raw['mal_json']??''),true)?:[];
        $p['tabs']=array_map(function($t){return ['title'=>$t['title'],'contentKey'=>$t['content_key'],'html'=>$t['content_html'],'sortOrder'=>(int)$t['sort_order']];},$this->db()->query('SELECT title,content_key,content_html,sort_order FROM vp_product_detail_tabs WHERE product_id=? ORDER BY sort_order,id',[$pid])->result_array()?:[]);
        $this->success($p,'Product loaded');
    }
    private function categoryList(): array { return $this->db()->query("SELECT id,name,slug,description,image_url AS image,is_featured FROM vp_categories WHERE status='published' ORDER BY sort_order,name")->result_array() ?: []; }
    private function productList(bool $featured,int $limit): array { $sql="SELECT p.*,c.name category_name,c.slug category_slug FROM vp_products p LEFT JOIN vp_categories c ON c.id=p.category_id WHERE p.status='published'"; if($featured)$sql.=' AND p.is_featured=1'; $sql.=' ORDER BY p.is_featured DESC,p.popularity DESC,p.created_at DESC LIMIT '.(int)$limit; return array_map(fn($p)=>$this->product($p),$this->db()->query($sql)->result_array()?:[]); }
    private function product(array $p): array { $cover=$p['cover_image_url'] ?: $this->legacyCoverImage($p['legacy_product_id'] ?? null); return ['id'=>(int)$p['id'],'name'=>$p['name'],'slug'=>$p['slug'],'description'=>$p['description']??'','shortDescription'=>$p['short_description']??'','basePrice'=>(float)$p['base_price'],'price'=>$this->startingPrice($p),'coverImage'=>$cover,'category'=>['name'=>$p['category_name']??'','slug'=>$p['category_slug']??'']]; }
    private function startingPrice(array $p): float { $price=(float)$p['base_price']; $rows=$this->db()->query('SELECT MIN(v.price_delta) AS minimum_price FROM vp_product_option_groups g INNER JOIN vp_product_option_values v ON v.option_group_id=g.id AND v.is_active=1 WHERE g.product_id=? AND g.is_required=1 GROUP BY g.id',[(int)$p['id']])->result_array() ?: []; foreach($rows as $row)$price+=(float)$row['minimum_price']; return $price; }
    private function legacyCoverImage($legacyId): ?string { $id=(int)$legacyId; return in_array($id,[1,2,3,4,5,6,7,8,9,12,13,14,15,16,17,18,19,20,21,22,23,24],true) ? '/uploads/products/product-'.$id.'.webp' : null; }
}
