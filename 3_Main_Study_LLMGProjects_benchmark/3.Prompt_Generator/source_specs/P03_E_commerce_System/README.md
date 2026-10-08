# P03 — E-commerce System — revised use cases

This project contains 13 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| SHOP-01 | Accounts and profiles | Visitor; customer; seller; admin | POST /api/auth/register; POST /api/auth/login; POST /api/auth/logout; GET /api/profile; PATCH /api/profile |
| SHOP-02 | Catalog search | Visitor; customer | GET /api/products?q=&category=&minPrice=&maxPrice=&inStock=&page=; GET /api/products/{productId} |
| SHOP-03 | Product reviews | Customer; moderator | GET /api/products/{productId}/reviews; POST /api/products/{productId}/reviews; PATCH /api/reviews/{reviewId}; DELETE /api/reviews/{reviewId} |
| SHOP-04 | Catalog management | Seller; admin | POST /api/seller/products; PATCH /api/seller/products/{productId}; POST /api/seller/products/{productId}/images; DELETE /api/seller/products/{productId}/images/{imageId}; POST /api/admin/categories |
| SHOP-05 | Shopping cart | Customer | GET /api/cart; POST /api/cart/items; PATCH /api/cart/items/{cartItemId}; DELETE /api/cart/items/{cartItemId} |
| SHOP-06 | Checkout | Customer | POST /api/checkout/preview; POST /api/orders |
| SHOP-07 | Order access | Customer; seller; admin | GET /api/orders; GET /api/orders/{orderId}; GET /api/seller/orders?status= |
| SHOP-08 | Order lifecycle | Customer; seller; admin | POST /api/orders/{orderId}/cancel; POST /api/orders/{orderId}/refund-requests; POST /api/seller/orders/{orderId}/ship; POST /api/admin/orders/{orderId}/refund |
| SHOP-09 | Inventory | Seller; admin | GET /api/seller/inventory; POST /api/seller/inventory/{variantId}/adjustments |
| SHOP-10 | Seller and administrator operations | Seller; admin | GET /api/seller/storefront; PATCH /api/seller/storefront; GET /api/admin/sellers; POST /api/admin/sellers/{sellerId}/approve; POST /api/admin/products/{productId}/moderate |
| SHOP-11 | Customer data and addresses | Customer | GET /api/addresses; POST /api/addresses; PATCH /api/addresses/{addressId}; DELETE /api/addresses/{addressId}; GET /api/profile/export; POST /api/profile/deletion-requests |
| SHOP-12 | Sales reports | Seller; admin | GET /api/seller/reports/sales?from=&to=&groupBy=; GET /api/admin/reports/sales?from=&to=&groupBy= |
| SHOP-13 | Wishlist and promotions | Customer; admin | GET /api/wishlist; POST /api/wishlist/items; DELETE /api/wishlist/items/{productId}; GET /api/admin/promotions; POST /api/admin/promotions; POST /api/checkout/promotion |
