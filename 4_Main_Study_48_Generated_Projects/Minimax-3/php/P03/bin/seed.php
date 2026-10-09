<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Shop\Config;
use Shop\Database;
use Shop\Models\UserRepository;
use Shop\Models\PromotionRepository;
use Shop\Models\ProductRepository;
use Shop\Models\InventoryRepository;
use Shop\Models\CustomerDataRepository;
use Shop\Models\CartRepository;
use Shop\Models\OrderRepository;
use Shop\Models\SettingsRepository;

Config::load();
Database::reset();

echo "Seeding P03 E-commerce System...\n";

$admin = UserRepository::create('admin@example.com', 'Admin#12345', 'Ada Admin', 'admin');
$seller = UserRepository::create('seller@example.com', 'Seller#12345', 'Sam Seller', 'seller');
$moderator = UserRepository::create('moderator@example.com', 'Moderator#12345', 'Mira Moderator', 'moderator');
$customer = UserRepository::create('customer@example.com', 'Customer#12345', 'Chris Customer', 'customer');
$customer2 = UserRepository::create('shopper@example.com', 'Shopper#12345', 'Sunny Shopper', 'customer');

$categories = [
    ['electronics', 'Electronics', 'Consumer electronics.'],
    ['books', 'Books', 'Books and publications.'],
    ['apparel', 'Apparel', 'Clothing and accessories.'],
    ['home', 'Home', 'Home goods.'],
];
foreach ($categories as [$slug, $name, $description]) {
    Database::pdo()->prepare(
        'INSERT INTO categories (slug, name, description) VALUES (:s, :n, :d)'
    )->execute([':s' => $slug, ':n' => $name, ':d' => $description]);
}
$catIds = [];
foreach (Database::pdo()->query('SELECT id, slug FROM categories')->fetchAll() as $row) {
    $catIds[$row['slug']] = (int)$row['id'];
}

$products = [
    ['LAPTOP-001', 'Benchmark Ultrabook', 'A reliable laptop for everyday tasks.', 89900, 'electronics', 12],
    ['LAPTOP-002', 'Benchmark Workstation', 'A workstation laptop for power users.', 159900, 'electronics', 6],
    ['PHONE-100', 'Benchmark Smartphone', 'A modern smartphone with long battery life.', 69900, 'electronics', 25],
    ['BOOK-001', 'Benchmark Web Engineering', 'A reference book on modern web engineering.', 4900, 'books', 100],
    ['BOOK-002', 'Benchmark PHP Cookbook', 'Practical PHP recipes for everyday work.', 3900, 'books', 80],
    ['SHIRT-RED', 'Benchmark Tee (Red)', 'A bright red cotton t-shirt.', 1900, 'apparel', 50],
    ['SHIRT-BLUE', 'Benchmark Tee (Blue)', 'A blue cotton t-shirt.', 1900, 'apparel', 0],
    ['MUG-001', 'Benchmark Mug', 'A ceramic mug for hot drinks.', 1200, 'home', 30],
    ['LAMP-001', 'Benchmark Desk Lamp', 'An LED desk lamp with adjustable brightness.', 4900, 'home', 14],
    ['CHAIR-001', 'Benchmark Office Chair', 'An ergonomic office chair.', 19900, 'home', 4],
];

// Also create one draft product (only visible to privileged users)
$draftId = ProductRepository::create([
    'sku' => 'DRAFT-001',
    'name' => 'Unpublished Draft Item',
    'description' => 'Should not appear in catalog_search.',
    'price_cents' => 9900,
    'currency' => 'USD',
    'category_id' => $catIds['electronics'],
    'seller_id' => (int)$seller['id'],
    'status' => 'draft',
]);
InventoryRepository::adjust($draftId, 10, 'seed', (int)$admin['id']);

foreach ($products as [$sku, $name, $description, $price, $cat, $qty]) {
    $productId = ProductRepository::create([
        'sku' => $sku,
        'name' => $name,
        'description' => $description,
        'price_cents' => $price,
        'currency' => 'USD',
        'category_id' => $catIds[$cat],
        'seller_id' => (int)$seller['id'],
        'status' => 'published',
    ]);
    InventoryRepository::adjust($productId, $qty, 'seed', (int)$admin['id']);
    InventoryRepository::setRestockThreshold($productId, 5);
}

$promoId = PromotionRepository::create('WELCOME10', 'Welcome discount 10%', 10);
PromotionRepository::create('SUMMER20', 'Summer sale 20%', 20);

SettingsRepository::set('storefront_name', 'P03 Benchmark Store');
SettingsRepository::set('default_currency', 'USD');
SettingsRepository::set('low_stock_email', 'ops@example.com');

$addrId = CustomerDataRepository::addAddress((int)$customer['id'], [
    'label' => 'home',
    'full_name' => 'Chris Customer',
    'line1' => '123 Benchmark Avenue',
    'line2' => 'Apt 4',
    'city' => 'Testville',
    'region' => 'TX',
    'postal_code' => '75001',
    'country' => 'US',
    'is_default' => 1,
]);
CustomerDataRepository::upsertPreferences((int)$customer['id'], [
    'newsletter' => 1,
    'marketing_opt_in' => 0,
    'preferred_currency' => 'USD',
    'notes' => 'Prefers fast shipping.',
]);
$cartId = CartRepository::ensureCart((int)$customer['id']);
$laptop = ProductRepository::bySku('LAPTOP-001');
$mug = ProductRepository::bySku('MUG-001');
CartRepository::add($cartId, (int)$laptop['id'], 1);
CartRepository::add($cartId, (int)$mug['id'], 2);
$totals = CartRepository::totals($cartId);

$result = OrderRepository::create(
    (int)$customer['id'],
    $cartId,
    $totals,
    [
        'shipping_address_id' => $addrId,
        'payment_method' => 'card',
        'payment_token' => 'tok_ok',
    ]
);
if (!empty($result['order_id'])) {
    OrderRepository::transition((int)$result['order_id'], 'shipped', (int)$seller['id'], 'Seeded shipment');
}

$cartId2 = CartRepository::ensureCart((int)$customer2['id']);
$chair = ProductRepository::bySku('CHAIR-001');
CartRepository::add($cartId2, (int)$chair['id'], 1);
$totals2 = CartRepository::totals($cartId2);
$addrId2 = CustomerDataRepository::addAddress((int)$customer2['id'], [
    'label' => 'home',
    'full_name' => 'Sunny Shopper',
    'line1' => '9 Main Street',
    'city' => 'Testville',
    'region' => 'TX',
    'postal_code' => '75002',
    'country' => 'US',
    'is_default' => 1,
]);
$result2 = OrderRepository::create(
    (int)$customer2['id'],
    $cartId2,
    $totals2,
    [
        'shipping_address_id' => $addrId2,
        'payment_method' => 'paypal',
        'payment_token' => 'tok_ok',
    ]
);
if (!empty($result2['order_id'])) {
    OrderRepository::transition((int)$result2['order_id'], 'delivered', (int)$admin['id'], 'Delivered to seed customer');
}

$book = ProductRepository::bySku('BOOK-002');
Database::pdo()->prepare(
    'INSERT INTO reviews (product_id, user_id, rating, title, body, status) VALUES (:p, :u, :r, :t, :b, "approved")'
)->execute([
    ':p' => (int)$book['id'],
    ':u' => (int)$customer['id'],
    ':r' => 5,
    ':t' => 'Great cookbook',
    ':b' => 'The recipes were very practical and well organized.',
]);
Database::pdo()->prepare(
    'INSERT INTO reviews (product_id, user_id, rating, title, body, status) VALUES (:p, :u, :r, :t, :b, "pending")'
)->execute([
    ':p' => (int)$mug['id'],
    ':u' => (int)$customer2['id'],
    ':r' => 4,
    ':t' => 'Solid mug',
    ':b' => 'Holds coffee well; would recommend.',
]);

echo "Seeded " . PHP_EOL;
echo "  admin     : admin@example.com / Admin#12345" . PHP_EOL;
echo "  seller    : seller@example.com / Seller#12345" . PHP_EOL;
echo "  moderator : moderator@example.com / Moderator#12345" . PHP_EOL;
echo "  customer  : customer@example.com / Customer#12345" . PHP_EOL;
echo "  shopper   : shopper@example.com / Shopper#12345" . PHP_EOL;
echo "  promotions: WELCOME10 (10% off), SUMMER20 (20% off)" . PHP_EOL;