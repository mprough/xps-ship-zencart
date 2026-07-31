<?php
/**
 * XPS Ship endpoint for Zen Cart 2.2.2 / PHP 8.5.
 *
 * @license https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GPL-2.0
 */

declare(strict_types=1);

require __DIR__ . '/includes/application_top.php';

header('Content-Type: application/json; charset=utf-8');

function xpsRespond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    exit;
}

function xpsPostString(string $name, bool $trim = true): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return $trim ? trim($value) : $value;
}

function xpsAuthenticate($db): void
{
    $username = xpsPostString('admin_name');
    $password = xpsPostString('admin_pass', false);

    if ($username === '' || $password === '') {
        xpsRespond(['Error' => 'Invalid User Name and/or Password!'], 401);
    }

    $admin = $db->Execute(
        "SELECT admin_id, admin_pass FROM " . TABLE_ADMIN .
        " WHERE admin_name = '" . zen_db_input($username) . "' LIMIT 1"
    );

    if ($admin->EOF || !zen_validate_password($password, (string)$admin->fields['admin_pass'])) {
        xpsRespond(['Error' => 'Invalid User Name and/or Password!'], 401);
    }
}

function xpsGetOrders($db, ?int $orderId, string $status)
{
    if ($orderId !== null) {
        return $db->Execute(
            "SELECT * FROM " . TABLE_ORDERS . " WHERE orders_id = " . $orderId
        );
    }

    return $db->Execute(
        "SELECT orders.* FROM " . TABLE_ORDERS . " orders " .
        "INNER JOIN " . TABLE_ORDERS_STATUS . " os " .
        "ON orders.orders_status = os.orders_status_id " .
        "WHERE os.language_id = " . (int)$_SESSION['languages_id'] .
        " AND LOWER(os.orders_status_name) = LOWER('" . zen_db_input($status) . "')"
    );
}

function xpsGetOrderAction($db): never
{
    $status = xpsPostString('status');
    $orderIdText = xpsPostString('order_id');
    $orderId = $orderIdText === '' ? null : filter_var($orderIdText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (($orderIdText !== '' && $orderId === false) || ($orderId === null && $status === '')) {
        xpsRespond(['Error' => 'A valid order_id or status is required.'], 400);
    }

    $orders = xpsGetOrders($db, $orderId === false ? null : $orderId, $status);
    $result = ['Orders' => []];

    while (!$orders->EOF) {
        $order = $orders->fields;
        $orderId = (int)$order['orders_id'];

        $statusResult = $db->Execute(
            "SELECT orders_status_id, orders_status_name FROM " . TABLE_ORDERS_STATUS .
            " WHERE language_id = " . (int)$_SESSION['languages_id'] .
            " AND orders_status_id = " . (int)$order['orders_status'] . " LIMIT 1"
        );

        $history = $db->Execute(
            "SELECT date_added FROM " . TABLE_ORDERS_STATUS_HISTORY .
            " WHERE orders_id = " . $orderId .
            " AND orders_status_id = " . (int)$order['orders_status'] .
            " ORDER BY date_added DESC LIMIT 1"
        );

        $comments = $db->Execute(
            "SELECT comments FROM " . TABLE_ORDERS_STATUS_HISTORY .
            " WHERE orders_id = " . $orderId .
            " AND comments IS NOT NULL AND comments <> ''" .
            " ORDER BY date_added DESC LIMIT 1"
        );

        $country = $db->Execute(
            "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES .
            " WHERE countries_name = '" . zen_db_input((string)$order['delivery_country']) . "' LIMIT 1"
        );

        $order['OrderStatusName'] = $statusResult->EOF ? '' : (string)$statusResult->fields['orders_status_name'];
        $order['last_modified'] = $history->EOF ? '' : (string)$history->fields['date_added'];
        $order['shipping_comments'] = $comments->EOF ? '' : (string)$comments->fields['comments'];
        $order['delivery_country'] = $country->EOF ? '' : (string)$country->fields['countries_iso_code_2'];
        $order['Items'] = [];

        $items = $db->Execute(
            "SELECT op.*, p.products_image, p.products_weight AS catalog_weight " .
            "FROM " . TABLE_ORDERS_PRODUCTS . " op " .
            "LEFT JOIN " . TABLE_PRODUCTS . " p ON p.products_id = op.products_id " .
            "WHERE op.orders_id = " . $orderId
        );

        while (!$items->EOF) {
            $item = $items->fields;
            $image = (string)($item['products_image'] ?? '');
            $order['Items'][] = [
                'products_id' => $item['products_id'],
                'sku' => $item['products_model'],
                'products_tax' => $item['products_tax'],
                'products_quantity' => $item['products_quantity'],
                'products_name' => $item['products_name'],
                'products_weight' => $item['products_weight'] ?: ($item['catalog_weight'] ?? 0),
                'final_price' => $item['final_price'],
                'image_url' => $image === '' ? '' : HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_IMAGES . $image,
            ];
            $items->MoveNext();
        }

        $result['Orders'][] = $order;
        $orders->MoveNext();
    }

    xpsRespond($result);
}

function xpsUpdateAction($db): never
{
    $orderNumber = filter_var($_GET['order_number'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $statusName = strtolower(xpsPostString('status'));
    $trackingCompany = xpsPostString('tracking_company');
    $comment = xpsPostString('comment');

    if ($orderNumber === false || $orderNumber === null) {
        xpsRespond(['Error' => 'Order Number Does not Exist'], 400);
    }
    if ($statusName === '') {
        xpsRespond(['Error' => 'Order Status Update Unsuccessful'], 400);
    }

    $record = $db->Execute(
        "SELECT orders_id FROM " . TABLE_ORDERS . " WHERE orders_id = " . (int)$orderNumber . " LIMIT 1"
    );
    if ($record->EOF) {
        xpsRespond(['Error' => 'Order Number Does not Exist'], 404);
    }

    $status = $db->Execute(
        "SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS .
        " WHERE language_id = " . (int)$_SESSION['languages_id'] .
        " AND LOWER(orders_status_name) = '" . zen_db_input($statusName) . "' LIMIT 1"
    );
    if ($status->EOF) {
        xpsRespond(['Error' => 'Order Status Update Unsuccessful'], 400);
    }

    $statusId = (int)$status->fields['orders_status_id'];
    $comments = trim($trackingCompany . ' (tracking code) - ' . $comment);

    $db->Execute(
        "UPDATE " . TABLE_ORDERS . " SET orders_status = " . $statusId .
        ", shipping_method = '" . zen_db_input($trackingCompany) . "', last_modified = NOW()" .
        " WHERE orders_id = " . (int)$orderNumber
    );
    $db->Execute(
        "INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY .
        " (orders_id, orders_status_id, date_added, customer_notified, comments) VALUES (" .
        (int)$orderNumber . ", " . $statusId . ", NOW(), 0, '" . zen_db_input($comments) . "')"
    );

    xpsRespond(['Success' => 'Order Updated Successfully']);
}

xpsAuthenticate($db);

$action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';
match ($action) {
    'getOrder' => xpsGetOrderAction($db),
    'update' => xpsUpdateAction($db),
    default => xpsRespond(['Error' => 'Unsupported action.'], 400),
};
