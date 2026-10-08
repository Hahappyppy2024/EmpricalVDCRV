<?php
declare(strict_types=1);
namespace App;
use PDO;
final class Seeder
{
    public static function reset(string $root,string $dbPath,string $uploadDir):PDO
    {
        $path=str_starts_with($dbPath,'/')?$dbPath:$root.'/'.$dbPath;if(is_file($path))unlink($path);
        $db=Database::connect($path);$db->exec((string)file_get_contents($root.'/database/schema.sql'));
        $hash=password_hash('Password123!',PASSWORD_DEFAULT);
        $users=[
            ['customer@example.test',$hash,'Casey Customer','customer','+1-555-0101'],
            ['customer2@example.test',$hash,'Morgan Customer','customer','+1-555-0102'],
            ['seller@example.test',$hash,'Sam Seller','seller','+1-555-0201'],
            ['seller2@example.test',$hash,'Taylor Seller','seller','+1-555-0202'],
            ['admin@example.test',$hash,'Alex Admin','admin','+1-555-0301'],
            ['moderator@example.test',$hash,'Riley Moderator','moderator','+1-555-0401'],
        ];
        $s=$db->prepare('INSERT INTO users(email,password_hash,name,role,phone) VALUES(?,?,?,?,?)');foreach($users as $u)$s->execute($u);
        $db->exec('INSERT INTO customer_profiles(user_id,marketing_opt_in) VALUES(1,1),(2,0)');
        $db->exec("INSERT INTO seller_profiles(user_id,storefront_name,description,support_email,approval_status,approved_at) VALUES(3,'Northstar Goods','Practical tools for focused work.','support@northstar.test','approved',datetime('now')), (4,'Pending Market','A storefront awaiting approval.','pending@example.test','pending',NULL)");
        $db->exec("INSERT INTO categories(name,slug) VALUES('Office','office'),('Electronics','electronics'),('Home','home')");
        $db->exec("INSERT INTO products(seller_id,category_id,name,description,price_cents,status) VALUES(3,1,'Focus Notebook','A durable dotted notebook.',1299,'active'),(3,2,'Desk Timer','A quiet rechargeable focus timer.',3499,'active'),(4,3,'Canvas Basket','A collapsible storage basket.',2499,'draft')");
        $db->exec("INSERT INTO product_variants(product_id,sku,name,price_cents,stock) VALUES(1,'NOTE-A5-BLUE','A5 Blue',1299,20),(1,'NOTE-A5-GREEN','A5 Green',1299,8),(2,'TIMER-WHITE','White',3499,12),(3,'BASKET-NATURAL','Natural',2499,5)");
        $db->exec("INSERT INTO addresses(user_id,recipient,street,city,region,postal_code,country) VALUES(1,'Casey Customer','100 Market Street','Portland','OR','97205','US'),(2,'Morgan Customer','22 Lake Avenue','Seattle','WA','98101','US')");
        $db->exec('INSERT INTO carts(customer_id) VALUES(1),(2)');
        $db->exec("INSERT INTO promotions(code,discount_type,discount_value,minimum_cents,starts_at,ends_at,created_by) VALUES('WELCOME10','percent',10,1000,'2020-01-01 00:00:00','2035-12-31 23:59:59',5)");
        $db->exec("INSERT INTO orders(customer_id,address_id,status,subtotal_cents,discount_cents,total_cents,idempotency_key,created_at) VALUES(1,1,'shipped',1299,0,1299,'seed-order-1','2026-07-15 10:00:00'),(2,2,'shipped',1299,0,1299,'seed-order-2','2026-07-18 10:00:00')");
        $db->exec("INSERT INTO order_items(order_id,variant_id,seller_id,product_name,variant_name,quantity,unit_price_cents) VALUES(1,1,3,'Focus Notebook','A5 Blue',1,1299),(2,2,3,'Focus Notebook','A5 Green',1,1299)");
        $db->exec("INSERT INTO payment_records(order_id,method_id,provider_reference,status,amount_cents) VALUES(1,'mock-card','PAY-SEED-1','captured',1299),(2,'mock-card','PAY-SEED-2','captured',1299)");
        $db->exec("INSERT INTO shipments(order_id,seller_id,carrier,tracking_number) VALUES(1,3,'LocalPost','TRACK-SEED-1'),(2,3,'LocalPost','TRACK-SEED-2')");
        $db->exec("INSERT INTO reviews(product_id,customer_id,rating,title,body) VALUES(1,2,5,'Reliable everyday notebook','The paper and binding have held up well.')");
        $db->exec('INSERT INTO wishlist_items(customer_id,product_id) VALUES(1,2)');
        $dir=str_starts_with($uploadDir,'/')?$uploadDir:$root.'/'.$uploadDir;if(!is_dir($dir))mkdir($dir,0775,true);
        return$db;
    }
}
