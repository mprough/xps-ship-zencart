# XPS Ship integration for Zen Cart

An updated XPS Ship endpoint for **Zen Cart 2.2.2** and **PHP 8.5**. It lets XPS Ship download pending orders and update an order after shipment.

This project is based on the [XPS ZenCart integration instructions](https://docs.xpsship.com/en/cart-integrations/cart-integrations-instructions/zencart-integration~7422058046514754200).

## Files

- `webship-zen-cart.php` — upload to the root of the Zen Cart store.
- `includes/init_includes/init_sanitize.php.MERGE-OR-REPLACE` — the required CSRF exception for the two authenticated XPS actions.

## Installation

1. Back up the store files and database.
2. Upload `webship-zen-cart.php` to the store root, beside `index.php`.
3. Open the supplied `init_sanitize.php.MERGE-OR-REPLACE` file. It is the complete Zen Cart 2.2.2 file with the required changes clearly marked `XPS SHIP MERGE`.
4. If your current `includes/init_includes/init_sanitize.php` is an unchanged Zen Cart 2.2.2 file, you can rename the supplied file to `init_sanitize.php` and replace it.
5. If your current file contains custom changes, **merge the two marked XPS changes** into your existing file. Do not overwrite your custom file.
6. In XPS Ship, add a ZenCart integration and enter the store URL without `index.php` or `webship-zen-cart.php`.
7. Enter a Zen Cart administrator username/password and the pending order-status name.

XPS calls:

```text
https://example.com/webship-zen-cart.php?action=getOrder
https://example.com/webship-zen-cart.php?action=update&order_number=123
```

Both are POST requests. Credentials belong in the POST body, never in the URL.

## First Visit reCAPTCHA users

If the First Visit reCAPTCHA add-on is installed, exempt this exact endpoint/action combination from the gate:

```text
webship-zen-cart.php?action=getOrder
```

Also exempt the `update` action. Do not broadly exempt unknown bots or all PHP files.

## Security notes

- Use HTTPS.
- Use a dedicated Zen Cart administrator account for XPS Ship.
- Give that account only the permissions required for order processing.
- Never commit usernames, passwords, database credentials, logs, or a store's `configure.php` files.
- The endpoint deliberately returns a generic authentication error.

## Compatibility

- Zen Cart 2.2.2
- PHP 8.5

## License and trademarks

The Zen Cart merge file retains Zen Cart's GPL-2.0 licensing notice. XPS Ship, Descartes, and Zen Cart are trademarks of their respective owners. This community-maintained project is not official XPS Ship support.
