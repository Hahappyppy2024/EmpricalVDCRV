<?php
declare(strict_types=1);
namespace App;
use PDO;
final class ShopRepository
{
    use Support;
    public function __construct(public readonly PDO $db) {}
    public function product(int $id,bool $publicOnly=false):array
    {
        $sql='SELECT p.*,c.name category,c.slug category_slug,sp.storefront_name seller_name FROM products p JOIN categories c ON c.id=p.category_id JOIN seller_profiles sp ON sp.user_id=p.seller_id WHERE p.id=?'.($publicOnly?" AND p.status='active'":'');
        $p=self::one($this->db,$sql,[$id],'product_not_found');
        return $this->productProjection($p);
    }
    public function productProjection(array $p):array
    {
        $p['price']=((int)$p['price_cents'])/100; unset($p['price_cents']);
        $p['variants']=self::all($this->db,'SELECT id,sku,name,price_cents/100.0 price,stock FROM product_variants WHERE product_id=? ORDER BY id',[$p['id']]);
        $p['images']=self::all($this->db,"SELECT id,original_name,mime_type,size_bytes,'/api/product-images/' || id url FROM product_images WHERE product_id=? ORDER BY id",[$p['id']]);
        return$p;
    }
    public function requireProductManager(array $u,array $p):void
    { if($u['role']!=='admin'&&(int)$p['seller_id']!==(int)$u['id'])throw new ApiException(403,'product_access_denied','You cannot manage this product.'); }
    public function cart(int $customerId):array
    {
        $this->db->prepare('INSERT OR IGNORE INTO carts(customer_id) VALUES(?)')->execute([$customerId]);
        $cart=self::one($this->db,'SELECT * FROM carts WHERE customer_id=?',[$customerId]);
        $items=self::all($this->db,"SELECT ci.id cart_item_id,ci.variant_id,ci.quantity,p.id product_id,p.name product_name,p.status product_status,pv.name variant_name,pv.stock,pv.price_cents,(pv.price_cents*ci.quantity) line_total_cents FROM cart_items ci JOIN product_variants pv ON pv.id=ci.variant_id JOIN products p ON p.id=pv.product_id WHERE ci.cart_id=? ORDER BY ci.id",[$cart['id']]);
        $subtotal=0;foreach($items as &$i){$subtotal+=(int)$i['line_total_cents'];$i['unit_price']=((int)$i['price_cents'])/100;$i['line_total']=((int)$i['line_total_cents'])/100;unset($i['price_cents'],$i['line_total_cents']);}
        $discount=$this->promotionDiscount($cart['promotion_id']? (int)$cart['promotion_id']:null,$subtotal);
        return['id'=>(int)$cart['id'],'items'=>$items,'subtotal'=>$subtotal/100,'discount'=>$discount/100,'total'=>($subtotal-$discount)/100,'promotion'=>$cart['promotion_id']?self::one($this->db,'SELECT id,code,discount_type,discount_value,minimum_cents,starts_at,ends_at FROM promotions WHERE id=?',[$cart['promotion_id']]):null];
    }
    public function promotionDiscount(?int $promotionId,int $subtotal):int
    {
        if(!$promotionId)return 0;$p=self::one($this->db,"SELECT * FROM promotions WHERE id=? AND active=1 AND starts_at<=datetime('now') AND ends_at>=datetime('now')",[$promotionId],'promotion_not_available');
        if($subtotal<(int)$p['minimum_cents'])return 0;
        return min($subtotal,$p['discount_type']==='percent'?(int)floor($subtotal*(int)$p['discount_value']/100):(int)$p['discount_value']);
    }
    public function order(int $id,?array $actor=null):array
    {
        $o=self::one($this->db,'SELECT o.*,a.recipient,a.street,a.city,a.region,a.postal_code,a.country FROM orders o JOIN addresses a ON a.id=o.address_id WHERE o.id=?',[$id],'order_not_found');
        $sql='SELECT oi.id,oi.variant_id,oi.seller_id,oi.product_name,oi.variant_name,oi.quantity,oi.unit_price_cents,u.name seller_name FROM order_items oi JOIN users u ON u.id=oi.seller_id WHERE oi.order_id=?';$params=[$id];
        if($actor&&$actor['role']==='seller'){$sql.=' AND oi.seller_id=?';$params[]=$actor['id'];}
        $items=self::all($this->db,$sql.' ORDER BY oi.id',$params);foreach($items as &$i){$i['unit_price']=((int)$i['unit_price_cents'])/100;unset($i['unit_price_cents']);}
        $o['subtotal']=((int)$o['subtotal_cents'])/100;$o['discount']=((int)$o['discount_cents'])/100;$o['total']=((int)$o['total_cents'])/100;unset($o['subtotal_cents'],$o['discount_cents'],$o['total_cents'],$o['idempotency_key']);$o['items']=$items;
        $o['shipments']=self::all($this->db,'SELECT seller_id,carrier,tracking_number,created_at FROM shipments WHERE order_id=? ORDER BY id',[$id]);
        return$o;
    }
    public function requireOrderAccess(array $u,array $order):void
    {
        if($u['role']==='admin')return;
        if($u['role']==='customer'&&(int)$order['customer_id']===(int)$u['id'])return;
        if($u['role']==='seller'){$s=$this->db->prepare('SELECT 1 FROM order_items WHERE order_id=? AND seller_id=?');$s->execute([$order['id'],$u['id']]);if($s->fetchColumn())return;}
        throw new ApiException(403,'order_access_denied','You cannot access this order.');
    }
}
