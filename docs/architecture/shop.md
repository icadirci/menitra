# QR Stand Shop

> Requested 2026-11-25: businesses can buy physical QR stands from us, at registration and in `/app`; products are managed in `/admin`.

## Flow

```
/admin: shop settings (open, shipping, delivery time) + products
   │
   ├─ /qr-stand, /en/qr-stands (marketing) ─▶ /register?stand={public_id}
   ├─ /register: stands[{public_id}] = quantity ─▶ cart (first QR code) ─▶ /app/{slug}/shop/checkout after email verification
   └─ /app/{slug}/shop: pick QR codes + quantity ─▶ cart
        ▼
/app/{slug}/shop/checkout: cart, delivery address, billing details, terms
        │ ShopCheckoutService::start(): order (awaiting_payment) + payment (pending) → iyzico
        ▼
callback / webhook / reconciliation ─▶ PaymentProcessor ─▶ ShopOrderService::markPaid()
        ▼
/admin/shop-orders: QR files (ZIP) → preparing → shipped (courier, tracking) → delivered; or cancelled → refund recorded
```

With "pay now" at registration the plan is paid first; the stands wait in the cart and the payment result offers to finish that order. Plan and stands are always two payments.

## Data model

| Table | Scope | Notes |
|---|---|---|
| `shop_settings` | platform, single row | `is_enabled` (default closed), `shipping_amount`, `free_shipping_from`, delivery days, `notification_email` |
| `shop_products` + translations | platform | price in kuruş (KDV included), image, `is_personalized`, `stock_quantity` (null = not tracked), `max_per_order`, `is_active`. Never deleted |
| `shop_cart_items` | tenant | product + QR code + quantity; stored so a registration choice survives verification on another device |
| `shop_orders` | tenant | amounts, encrypted `delivery_address`, status timestamps, tracking |
| `shop_order_items` | tenant | one line per product and QR code; name, price, code and label are snapshots (`qr_code_id` becomes null when a code is deleted) |
| `payments.shop_order_id` | tenant | a payment is for a plan period **or** one shop order (CHECK `payments_purpose`); order ↔ payment is 1:1 |

## Rules

- **Open** means the switch is on and at least one product is active. Closed: hidden on the site, at registration and in the panel (the shop page stays reachable for past orders).
- **Owner-only** in `/app` (`TenantPolicy::manageSettings`), checked in the pages, `ShopCart` and `ShopCheckoutService`. No subscription is required to order.
- **Amounts** come only from products and settings. The total the owner saw is passed along; a different total is refused (`shop_price_changed`).
- **Failed or abandoned payment:** the order ends as `payment_failed`, the cart is kept, a retry creates a new order. Money that arrives later still makes the order paid.
- **Stock** is checked at checkout and taken when the payment succeeds, so it may go below zero in a race (logged; the order is never dropped). Cancelling puts it back.
- **Status** changes only through `ShopOrderService`, in the order's own tenant context. `/admin` uses `ShopOrderAdminService`: the scope bypass only reads, writes set the order's tenant first; every change is audited.
- **Roles:** products and settings are super-admin only; platform staff read orders (incl. the delivery address), super admins change them.
- **Delivery address** is personal data: encrypted, hidden from serialization, never in mails or logs; sent to iyzico as the shipping address. Turkey only (81 provinces). Prefilled from the last order, else the billing address.
- **Refunds** are made in the iyzico merchant panel; "record refund" only marks the order.
- **Mails:** order received, shipped, cancelled (billing email, business default locale); new order (platform, no personal data).
- Product images: `platform/shop/{uuid}.{ext}` on the `media` disk (`PlatformMedia`), same checks as tenant images.

## Not built

Courier API, automatic e-Arşiv invoice, refunds from the app, returns, combined plan + stand payment, shipping abroad, print-ready stand artwork (the ZIP holds the plain QR codes).

Tests: `tests/Feature/Shop/ShopTest.php`.
