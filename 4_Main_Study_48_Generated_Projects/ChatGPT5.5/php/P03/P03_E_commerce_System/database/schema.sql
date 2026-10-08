PRAGMA foreign_keys = ON;

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  password_hash TEXT NOT NULL,
  name TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('customer','seller','admin','moderator')),
  phone TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE sessions (
  id TEXT PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  expires_at TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE customer_profiles (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  marketing_opt_in INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE seller_profiles (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  storefront_name TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  support_email TEXT NOT NULL DEFAULT '',
  approval_status TEXT NOT NULL DEFAULT 'pending' CHECK(approval_status IN ('pending','approved','suspended')),
  approved_at TEXT
);
CREATE TABLE categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE COLLATE NOCASE,
  slug TEXT NOT NULL UNIQUE
);
CREATE TABLE products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  seller_id INTEGER NOT NULL REFERENCES users(id),
  category_id INTEGER NOT NULL REFERENCES categories(id),
  name TEXT NOT NULL,
  description TEXT NOT NULL,
  price_cents INTEGER NOT NULL CHECK(price_cents >= 0),
  status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','active','hidden')),
  moderation_reason TEXT NOT NULL DEFAULT '',
  version INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE product_variants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  sku TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  price_cents INTEGER NOT NULL CHECK(price_cents >= 0),
  stock INTEGER NOT NULL DEFAULT 0 CHECK(stock >= 0)
);
CREATE TABLE product_images (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  original_name TEXT NOT NULL,
  stored_name TEXT NOT NULL UNIQUE,
  mime_type TEXT NOT NULL,
  size_bytes INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE addresses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  recipient TEXT NOT NULL,
  street TEXT NOT NULL,
  city TEXT NOT NULL,
  region TEXT NOT NULL,
  postal_code TEXT NOT NULL,
  country TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE carts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  promotion_id INTEGER REFERENCES promotions(id),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE cart_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  cart_id INTEGER NOT NULL REFERENCES carts(id) ON DELETE CASCADE,
  variant_id INTEGER NOT NULL REFERENCES product_variants(id),
  quantity INTEGER NOT NULL CHECK(quantity > 0),
  UNIQUE(cart_id,variant_id)
);
CREATE TABLE promotions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE COLLATE NOCASE,
  discount_type TEXT NOT NULL CHECK(discount_type IN ('percent','fixed')),
  discount_value INTEGER NOT NULL CHECK(discount_value > 0),
  minimum_cents INTEGER NOT NULL DEFAULT 0,
  starts_at TEXT NOT NULL,
  ends_at TEXT NOT NULL,
  active INTEGER NOT NULL DEFAULT 1,
  created_by INTEGER NOT NULL REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES users(id),
  address_id INTEGER NOT NULL REFERENCES addresses(id),
  status TEXT NOT NULL CHECK(status IN ('paid','shipped','cancelled','refund_requested','refunded')),
  subtotal_cents INTEGER NOT NULL,
  discount_cents INTEGER NOT NULL DEFAULT 0,
  total_cents INTEGER NOT NULL,
  promotion_id INTEGER REFERENCES promotions(id),
  idempotency_key TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE(customer_id,idempotency_key)
);
CREATE TABLE order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  variant_id INTEGER NOT NULL REFERENCES product_variants(id),
  seller_id INTEGER NOT NULL REFERENCES users(id),
  product_name TEXT NOT NULL,
  variant_name TEXT NOT NULL,
  quantity INTEGER NOT NULL,
  unit_price_cents INTEGER NOT NULL
);
CREATE TABLE payment_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL UNIQUE REFERENCES orders(id) ON DELETE CASCADE,
  method_id TEXT NOT NULL,
  provider_reference TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL CHECK(status IN ('captured','refunded')),
  amount_cents INTEGER NOT NULL,
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE reviews (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  customer_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  hidden INTEGER NOT NULL DEFAULT 0,
  moderation_reason TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE(product_id,customer_id)
);
CREATE TABLE shipments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL REFERENCES orders(id),
  seller_id INTEGER NOT NULL REFERENCES users(id),
  carrier TEXT NOT NULL,
  tracking_number TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE(order_id,seller_id)
);
CREATE TABLE refund_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL UNIQUE REFERENCES orders(id),
  customer_id INTEGER NOT NULL REFERENCES users(id),
  reason TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE inventory_adjustments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  variant_id INTEGER NOT NULL REFERENCES product_variants(id),
  actor_id INTEGER NOT NULL REFERENCES users(id),
  delta INTEGER NOT NULL,
  reason TEXT NOT NULL,
  reference TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE(variant_id,reference)
);
CREATE TABLE audit_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_id INTEGER NOT NULL REFERENCES users(id),
  action TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  entity_id INTEGER NOT NULL,
  details TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE deletion_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id),
  status TEXT NOT NULL DEFAULT 'pending',
  requested_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE UNIQUE INDEX one_pending_deletion ON deletion_requests(user_id) WHERE status='pending';
CREATE TABLE wishlist_items (
  customer_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  PRIMARY KEY(customer_id,product_id)
);
CREATE INDEX products_catalog ON products(status,category_id,price_cents,id);
CREATE INDEX order_items_seller ON order_items(seller_id,order_id);
CREATE INDEX orders_customer ON orders(customer_id,created_at,id);
CREATE INDEX reviews_product ON reviews(product_id,hidden,created_at,id);
