# Bryleigh

Bryleigh is a plain PHP + MySQL secondhand clothing e-commerce site for ITS122P. It uses PDO, prepared statements, and two roles: customer and admin.

## XAMPP setup

1. Put or clone the project in `C:\xampp\htdocs\bryleigh`.
2. Start Apache and MySQL in XAMPP.
3. Create/import the database through phpMyAdmin: import `database/schema.sql`, then `database/seed.sql`.
4. Copy `config/db.sample.php` to `config/db.local.php`.
5. Run `C:\xampp\php\php.exe database/seed_users.php` from the project directory.
6. Open [http://localhost/bryleigh](http://localhost/bryleigh).

Demo accounts: `admin@bryleigh.com` / `Admin123!`; `customer@bryleigh.com` / `Customer123!`.

## Session contract

The session stores `$_SESSION['user_id']`, `$_SESSION['role']`, `$_SESSION['full_name']`, and `$_SESSION['cart'] = [product_id => quantity]`.

## Ownership and workflow

Use a branch per member and pull requests into `main`. Never commit `config/db.local.php`.

| Owner | Files |
|---|---|
| Member 1 | `index.php`, `customer/shop.php`, `customer/product.php`, `admin/products.php`, `admin/product_form.php`, `admin/product_archive.php`, `assets/css/catalog.css` |
| Member 2 | `customer/cart.php`, `customer/cart_add.php`, `customer/cart_update.php`, `customer/cart_remove.php`, `customer/checkout.php`, `customer/order_history.php`, `admin/orders.php`, `admin/order_update.php`, `assets/css/orders.css` |
| Owner | `admin/dashboard.php` |

Only edit files you own. Shared files (`includes/`, `style.css`, `database/`) are changed only by the owner. Ask first.

