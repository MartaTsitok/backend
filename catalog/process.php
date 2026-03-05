<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once('../connection.php');

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

try {
    // Проверяем авторизацию
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Необходимо авторизоваться');
    }
    // Получаем данные из POST запроса
    $inputJSON = file_get_contents('php://input');
    $data = json_decode($inputJSON, true);
    if (!$data) {
        throw new Exception('Нет данных заказа');
    }
    if (empty($data['delivery_address'])) {
        throw new Exception('Не указан адрес доставки');
    }
    if (empty($data['items']) || !is_array($data['items'])) {
        throw new Exception('Нет товаров в заказе');
    }
    $user_id = $_SESSION['user_id'];
    // Получаем пользователя
    $userQuery = "SELECT firstname, lastname, email, phone FROM user WHERE id = '$user_id'";
    $userResult = mysqli_query($connect, $userQuery);
    
    if (!$userResult || mysqli_num_rows($userResult) == 0) {
        throw new Exception('Пользователь не найден');
    }
    
    $user = mysqli_fetch_assoc($userResult);
    
    // Начинаем транзакцию
    mysqli_begin_transaction($connect);
    
    $count_goods = count($data['items']);
    $total_sum = floatval($data['total']);
    
    $sql = "INSERT INTO orders (user_id, count_goods, total_sum) VALUES ('$user_id', '$count_goods', '$total_sum')";
    
    if (!mysqli_query($connect, $sql)) {
        throw new Exception('Ошибка вставки заказа: ' . mysqli_error($connect));
    }
    
    $order_id = mysqli_insert_id($connect);
    
    // Создаем таблицу для товаров, если её нет
    $createItemsTableSQL = "CREATE TABLE IF NOT EXISTS `order_items` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `order_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        `product_name` VARCHAR(255) NOT NULL,
        `quantity` INT NOT NULL,
        `price` DECIMAL(10,2) NOT NULL,
        `size` VARCHAR(10)
    )";
    mysqli_query($connect, $createItemsTableSQL);
    
    // Сохраняем товары
    foreach ($data['items'] as $item) {
        $product_id = intval($item['id']);
        $product_name = mysqli_real_escape_string($connect, $item['name']);
        $quantity = intval($item['quantity'] ?? 1);
        $price = floatval($item['price']);
        $size = mysqli_real_escape_string($connect, $item['size'] ?? 's');
        
        $sql = "INSERT INTO order_items (order_id, product_id, product_name, quantity, price, size) 
                VALUES ('$order_id', '$product_id', '$product_name', '$quantity', '$price', '$size')";
        
        if (!mysqli_query($connect, $sql)) {
            throw new Exception('Ошибка сохранения товара: ' . mysqli_error($connect));
        }
    }
    
    mysqli_commit($connect);
    
    $fullName = $user['firstname'] . ' ' . $user['lastname'];
    $to = $user['email'];
    $subject = "Подтверждение заказа №$order_id - Анютин сад";
    
    $message = "Здравствуйте, $fullName!\n\n";
    $message .= "Ваш заказ №$order_id успешно оформлен.\n\n";
    $message .= "Состав заказа:\n";
    foreach ($data['items'] as $item) {
        $message .= "- {$item['name']} (Размер: {$item['size']}, Количество: {$item['quantity']}, Цена: {$item['price']} BYN)\n";
    }
    $message .= "\nОбщая сумма: {$data['total']} BYN\n";
    $message .= "Адрес доставки: {$data['delivery_address']}\n";
    $message .= "Телефон: {$user['phone']}\n\n";
    $message .= "Спасибо за покупку!\n";
    
    $headers = "From: Анютин сад <shop@annsgarden.com>\r\n";
    $headers .= "Reply-To: shop@annsgarden.com\r\n";
    
    // Подавляем ошибки почты символом @
    @mail($to, $subject, $message, $headers);
    
    // Успешный ответ
    $response['success'] = true;
    $response['order_id'] = $order_id;
    
} catch (Exception $e) {
    if (isset($connect)) {
        mysqli_rollback($connect);
    }
    $response['error'] = $e->getMessage();
}

// Очищаем буфер вывода и отправляем JSON
if (ob_get_length()) ob_clean();
echo json_encode($response);
exit;
?>