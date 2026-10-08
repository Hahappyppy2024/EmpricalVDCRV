<?php

declare(strict_types=1);

namespace Shop;

use PDO;

final class Database
{
    public const SCHEMA = <<<'SQL'
CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    name TEXT NOT NULL,
    role_id INTEGER NOT NULL REFERENCES roles(id),
    active INTEGER NOT NULL DEFAULT 1,
    phone TEXT NOT NULL DEFAULT '',
    newsletter INTEGER NOT NULL DEFAULT 0,
    preferences TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS sessions (
    id INTEGER PRIMARY KEY,
    token TEXT NOT NULL UNIQUE,
    user_id INTEGER NOT NULL REFERENCES users(id),
    csrf_token TEXT NOT NULL,
    ip_address TEXT NOT NULL DEFAULT '',
    user_agent TEXT NOT NULL DEFAULT '',
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY,
    email TEXT NOT NULL,
    ip_address TEXT NOT NULL DEFAULT '',
    attempts INTEGER NOT NULL DEFAULT 0,
    last_attempt_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (email, ip_address)
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    token TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    used INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS mail_logs (
    id INTEGER PRIMARY KEY,
    recipient TEXT NOT NULL,
    subject TEXT NOT NULL,
    body TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    slug TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY,
    seller_id INTEGER NOT NULL REFERENCES users(id),
    category_id INTEGER NOT NULL REFERENCES categories(id),
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    price REAL NOT NULL DEFAULT 0,
    stock INTEGER NOT NULL DEFAULT 0,
    image TEXT NOT NULL DEFAULT '',
    active INTEGER NOT NULL DEFAULT 1,
    featured INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS saved_searches (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    name TEXT NOT NULL,
    query TEXT NOT NULL DEFAULT '',
    filters TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS reviews (
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id),
    user_id INTEGER NOT NULL REFERENCES users(id),
    rating INTEGER NOT NULL,
    title TEXT NOT NULL DEFAULT '',
    text TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    moderated_by INTEGER REFERENCES users(id),
    moderated_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (product_id, user_id)
);

CREATE TABLE IF NOT EXISTS cart_items (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    product_id INTEGER NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (user_id, product_id)
);

CREATE TABLE IF NOT EXISTS addresses (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    label TEXT NOT NULL DEFAULT 'Home',
    line1 TEXT NOT NULL,
    line2 TEXT NOT NULL DEFAULT '',
    city TEXT NOT NULL,
    postal_code TEXT NOT NULL,
    country TEXT NOT NULL DEFAULT 'US',
    phone TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS promotions (
    id INTEGER PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    discount_type TEXT NOT NULL DEFAULT 'percent',
    amount REAL NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1,
    starts_at TEXT,
    ends_at TEXT,
    max_uses INTEGER,
    uses INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY,
    key TEXT NOT NULL UNIQUE,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY,
    number TEXT NOT NULL UNIQUE,
    user_id INTEGER NOT NULL REFERENCES users(id),
    address_id INTEGER REFERENCES addresses(id),
    status TEXT NOT NULL DEFAULT 'pending',
    subtotal REAL NOT NULL DEFAULT 0,
    shipping REAL NOT NULL DEFAULT 0,
    tax REAL NOT NULL DEFAULT 0,
    discount REAL NOT NULL DEFAULT 0,
    total REAL NOT NULL DEFAULT 0,
    payment_method TEXT NOT NULL DEFAULT 'card',
    payment_reference TEXT NOT NULL DEFAULT '',
    promotion_id INTEGER REFERENCES promotions(id),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id),
    product_id INTEGER REFERENCES products(id),
    product_name TEXT NOT NULL,
    unit_price REAL NOT NULL,
    quantity INTEGER NOT NULL,
    line_total REAL NOT NULL
);

CREATE TABLE IF NOT EXISTS order_status_history (
    id INTEGER PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id),
    from_status TEXT,
    to_status TEXT NOT NULL,
    changed_by INTEGER REFERENCES users(id),
    comment TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id),
    delta INTEGER NOT NULL,
    reason TEXT NOT NULL,
    user_id INTEGER REFERENCES users(id),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audit_events (
    id INTEGER PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL DEFAULT '',
    entity_id INTEGER,
    details TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS broadcasts (
    id INTEGER PRIMARY KEY,
    channel TEXT NOT NULL DEFAULT 'orders',
    event TEXT NOT NULL,
    payload TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS api_clients (
    id INTEGER PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    name TEXT NOT NULL,
    token TEXT NOT NULL UNIQUE,
    prefs TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_products_active ON products(active);
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);
CREATE INDEX IF NOT EXISTS idx_orders_user ON orders(user_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_reviews_product ON reviews(product_id);
CREATE INDEX IF NOT EXISTS idx_history_order ON order_status_history(order_id);
SQL;

    public static function connect(): PDO
    {
        $path = Config::absolute((string) Config::get('DB_PATH', 'data/shop.db'));
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(self::SCHEMA);
    }

    public static function hasUsers(PDO $pdo): bool
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    }

    /**
     * Insert deterministic seed fixtures for every actor, role, product,
     * order state, promotion, and relationship required by the use cases.
     */
    public static function seed(PDO $pdo): void
    {
        if (self::hasUsers($pdo)) {
            throw new \RuntimeException('Database is already seeded. Run "php bin/reset_db.php" to reset and re-seed.');
        }

        $stmt = static fn (string $sql, array $params = []) => $pdo->prepare($sql)->execute($params);

        $stmt('INSERT INTO roles (id, code, label) VALUES (1, ?, ?), (2, ?, ?), (3, ?, ?), (4, ?, ?)', [
            'customer', 'Customer', 'seller', 'Seller', 'moderator', 'Moderator', 'admin', 'Administrator',
        ]);

        $users = [
            [1, 'admin@example.com', 'admin123', 'Admin User', 4],
            [2, 'seller1@example.com', 'seller123', 'Alice Seller', 2],
            [3, 'seller2@example.com', 'seller123', 'Bob Seller', 2],
            [4, 'customer1@example.com', 'customer123', 'Customer One', 1],
            [5, 'customer2@example.com', 'customer123', 'Customer Two', 1],
            [6, 'moderator1@example.com', 'moderator123', 'Moderator One', 3],
        ];
        foreach ($users as [$id, $email, $plain, $name, $roleId]) {
            $stmt(
                'INSERT INTO users (id, email, password_hash, name, role_id, active, phone, newsletter) VALUES (?, ?, ?, ?, ?, 1, ?, 1)',
                [$id, $email, password_hash($plain, PASSWORD_DEFAULT), $name, $roleId, '555-0' . $id . '0-0100']
            );
        }

        $stmt('INSERT INTO categories (id, name, slug) VALUES (1, ?, ?), (2, ?, ?), (3, ?, ?), (4, ?, ?)', [
            'Electronics', 'electronics', 'Books', 'books', 'Clothing', 'clothing', 'Home & Garden', 'home-garden',
        ]);

        $products = [
            [1, 2, 1, 'Wireless Headphones', 'wireless-headphones', 'Over-ear wireless headphones with 30h battery life.', 89.99, 24, 'electronics.svg', 1, 1],
            [2, 2, 1, 'Mechanical Keyboard', 'mechanical-keyboard', 'TKL mechanical keyboard with hot-swappable switches.', 129.50, 11, 'electronics.svg', 1, 0],
            [3, 2, 2, 'PHP Handbook', 'php-handbook', 'A practical guide to modern PHP development.', 39.90, 48, 'books.svg', 1, 1],
            [4, 2, 2, 'The Silent Sea', 'the-silent-sea', 'A mystery novel about a disappearing lighthouse town.', 19.99, 2, 'books.svg', 1, 0],
            [5, 2, 3, 'Cotton T-Shirt', 'cotton-t-shirt', '100% organic cotton t-shirt, unisex fit.', 24.00, 99, 'clothing.svg', 1, 0],
            [6, 2, 3, 'Denim Jacket', 'denim-jacket', 'Classic denim jacket with a soft wash finish.', 79.00, 8, 'clothing.svg', 1, 0],
            [7, 2, 4, 'Ceramic Mug Set', 'ceramic-mug-set', 'Set of four hand-glazed ceramic mugs.', 34.50, 38, 'home.svg', 1, 1],
            [8, 2, 4, 'Desk Lamp', 'desk-lamp', 'Minimal LED desk lamp with three brightness levels.', 45.00, 0, 'home.svg', 1, 0],
            [9, 3, 1, 'Smart Watch', 'smart-watch', 'Fitness smart watch with heart-rate monitoring.', 199.00, 14, 'electronics.svg', 1, 0],
            [10, 3, 2, 'Cookbook Collection', 'cookbook-collection', 'Five regional cookbooks bound as a set.', 59.90, 20, 'books.svg', 1, 0],
            [11, 3, 4, 'Linen Tablecloth', 'linen-tablecloth', 'Stone-washed linen tablecloth, 160x240 cm.', 28.00, 60, 'home.svg', 1, 0],
        ];
        foreach ($products as [$id, $seller, $cat, $name, $slug, $desc, $price, $stock, $image, $active, $featured]) {
            $stmt(
                'INSERT INTO products (id, seller_id, category_id, name, slug, description, price, stock, image, active, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $seller, $cat, $name, $slug, $desc, $price, $stock, $image, $active, $featured]
            );
        }

        $reviews = [
            [1, 1, 4, 5, 'Excellent sound', 'Crisp audio and a comfortable fit. Highly recommended.', 'approved', 6],
            [2, 1, 5, 4, 'Good value', 'Great battery life for the price.', 'approved', 6],
            [3, 3, 4, 5, 'Clear and practical', 'The examples made PHP concepts easy to follow.', 'approved', 6],
            [4, 5, 5, 3, 'Decent shirt', 'Comfortable but the sizing runs large.', 'pending', null],
        ];
        foreach ($reviews as [$id, $productId, $userId, $rating, $title, $text, $status, $moderatedBy]) {
            $stmt(
                'INSERT INTO reviews (id, product_id, user_id, rating, title, text, status, moderated_by, moderated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))',
                [$id, $productId, $userId, $rating, $title, $text, $status, $moderatedBy]
            );
        }

        $addresses = [
            [1, 4, 'Home', '100 Main Street', 'Apt 4B', 'Springfield', '62701', 'US', '555-0400-0100'],
            [2, 5, 'Home', '42 Market Avenue', '', 'Riverton', '82001', 'US', '555-0500-0100'],
        ];
        foreach ($addresses as [$id, $userId, $label, $line1, $line2, $city, $zip, $country, $phone]) {
            $stmt(
                'INSERT INTO addresses (id, user_id, label, line1, line2, city, postal_code, country, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $userId, $label, $line1, $line2, $city, $zip, $country, $phone]
            );
        }

        $promotions = [
            [1, 'WELCOME10', '10% off your order', 'percent', 10, 1, null, null, 1000],
            [2, 'FLAT5', 'Flat $5 discount', 'fixed', 5, 1, null, null, 500],
        ];
        foreach ($promotions as [$id, $code, $desc, $type, $amount, $active, $start, $end, $maxUses]) {
            $stmt(
                'INSERT INTO promotions (id, code, description, discount_type, amount, active, starts_at, ends_at, max_uses) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $code, $desc, $type, $amount, $active, $start, $end, $maxUses]
            );
        }

        // Orders in every lifecycle state (pending, paid, shipped, delivered, cancelled, refunded).
        $orders = [
            [1, 'ORD-20260001', 4, 1, 'paid', 219.49, 0.00, 17.56, 0.00, 237.05, 'card', 'PAY-000001', null],
            [2, 'ORD-20260002', 4, 1, 'shipped', 79.80, 0.00, 6.38, 0.00, 86.18, 'card', 'PAY-000002', null],
            [3, 'ORD-20260003', 5, 2, 'delivered', 199.00, 0.00, 15.92, 0.00, 214.92, 'card', 'PAY-000003', null],
            [4, 'ORD-20260004', 4, 1, 'cancelled', 24.00, 5.00, 1.92, 0.00, 30.92, 'card', 'PAY-000004', null],
            [5, 'ORD-20260005', 5, 2, 'refunded', 69.00, 5.00, 5.52, 0.00, 79.52, 'card', 'PAY-000005', null],
            [6, 'ORD-20260006', 4, 1, 'pending', 19.99, 5.00, 1.60, 0.00, 26.59, 'card', '', null],
        ];
        foreach ($orders as [$id, $number, $userId, $addrId, $status, $sub, $ship, $tax, $disc, $total, $method, $ref, $promoId]) {
            $stmt(
                'INSERT INTO orders (id, number, user_id, address_id, status, subtotal, shipping, tax, discount, total, payment_method, payment_reference, promotion_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $number, $userId, $addrId, $status, $sub, $ship, $tax, $disc, $total, $method, $ref, $promoId]
            );
        }

        $orderItems = [
            [1, 1, 1, 'Wireless Headphones', 89.99, 1, 89.99],
            [2, 1, 2, 'Mechanical Keyboard', 129.50, 1, 129.50],
            [3, 2, 3, 'PHP Handbook', 39.90, 2, 79.80],
            [4, 3, 9, 'Smart Watch', 199.00, 1, 199.00],
            [5, 4, 5, 'Cotton T-Shirt', 24.00, 1, 24.00],
            [6, 5, 7, 'Ceramic Mug Set', 34.50, 2, 69.00],
            [7, 6, 4, 'The Silent Sea', 19.99, 1, 19.99],
        ];
        foreach ($orderItems as [$id, $orderId, $productId, $name, $price, $qty, $total]) {
            $stmt(
                'INSERT INTO order_items (id, order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $orderId, $productId, $name, $price, $qty, $total]
            );
        }

        $history = [
            [1, 1, null, 'paid', 4, 'Payment approved', '2026-06-01 10:00:00'],
            [2, 2, null, 'paid', 4, 'Payment approved', '2026-06-03 11:30:00'],
            [3, 2, 'paid', 'shipped', 2, 'Dispatched via courier', '2026-06-04 09:00:00'],
            [4, 3, null, 'paid', 5, 'Payment approved', '2026-06-05 14:00:00'],
            [5, 3, 'paid', 'shipped', 3, 'Dispatched', '2026-06-06 10:00:00'],
            [6, 3, 'shipped', 'delivered', 2, 'Delivered', '2026-06-08 16:00:00'],
            [7, 4, null, 'cancelled', 4, 'Changed my mind', '2026-06-07 12:00:00'],
            [8, 5, null, 'paid', 5, 'Payment approved', '2026-06-02 09:30:00'],
            [9, 5, 'paid', 'shipped', 3, 'Dispatched', '2026-06-03 10:00:00'],
            [10, 5, 'shipped', 'delivered', 2, 'Delivered', '2026-06-05 11:00:00'],
            [11, 5, 'delivered', 'refunded', 5, 'Damaged item refunded', '2026-06-09 15:00:00'],
        ];
        foreach ($history as [$id, $orderId, $from, $to, $changedBy, $comment, $createdAt]) {
            $stmt(
                'INSERT INTO order_status_history (id, order_id, from_status, to_status, changed_by, comment, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $orderId, $from, $to, $changedBy, $comment, $createdAt]
            );
        }

        $movements = [
            [1, 1, -1, 'order', 4], [2, 2, -1, 'order', 4], [3, 3, -2, 'order', 4], [4, 9, -1, 'order', 5],
            [5, 5, -1, 'order', 4], [6, 7, -2, 'order', 5], [7, 4, -1, 'order', 4], [8, 5, 1, 'order_cancelled', 4],
        ];
        foreach ($movements as [$id, $productId, $delta, $reason, $userId]) {
            $stmt(
                'INSERT INTO stock_movements (id, product_id, delta, reason, user_id, created_at) VALUES (?, ?, ?, ?, ?, datetime(\'now\'))',
                [$id, $productId, $delta, $reason, $userId]
            );
        }

        $settings = [
            ['shop_name', 'P03 E-commerce System'],
            ['currency', 'USD'],
            ['shipping_flat', '5.00'],
            ['free_shipping_threshold', '100.00'],
            ['tax_rate', '0.08'],
        ];
        foreach ($settings as [$key, $value]) {
            $stmt('INSERT INTO settings (key, value) VALUES (?, ?)', [$key, $value]);
        }

        $audit = [
            [1, 'seed.products', 'Product', 0, 'Seeded 11 catalog products'],
            [2, 'seed.orders', 'Order', 0, 'Seeded 6 orders across lifecycle states'],
        ];
        foreach ($audit as [$id, $action, $entity, $entityId, $details]) {
            $stmt(
                'INSERT INTO audit_events (id, user_id, action, entity_type, entity_id, details, created_at) VALUES (?, 1, ?, ?, ?, ?, datetime(\'now\'))',
                [$id, $action, $entity, $entityId, $details]
            );
        }

        $stmt(
            'INSERT INTO saved_searches (id, user_id, name, query, filters) VALUES (1, 4, ?, ?, ?)',
            ['Electronics under $150', 'electronics', json_encode(['max_price' => 150], JSON_UNESCAPED_SLASHES)]
        );

        $stmt(
            'INSERT INTO api_clients (id, user_id, name, token, prefs) VALUES (1, 4, ?, ?, ?)',
            ['Storefront demo client', 'seed-client-0001', json_encode(['channel' => 'orders'])]
        );
    }
}
