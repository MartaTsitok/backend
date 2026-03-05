<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once('../connection.php');

// Подключаем PHPMailer
require_once '../PHPMailer/src/Exception.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

try {
    // Проверяем авторизацию
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Необходимо авторизоваться');
    }

    $inputJSON = file_get_contents('php://input');
    $data = json_decode($inputJSON, true);
    
    if (!$data) throw new Exception('Нет данных заказа');
    if (empty($data['delivery_address'])) throw new Exception('Не указан адрес доставки');
    if (empty($data['items']) || !is_array($data['items'])) throw new Exception('Нет товаров в заказе');

    $user_id = $_SESSION['user_id'];
    
    // Получаем пользователя
    $userQuery = "SELECT firstname, lastname, email, phone FROM user WHERE id = '$user_id'";
    $userResult = mysqli_query($connect, $userQuery);
    $user = mysqli_fetch_assoc($userResult);
    if (!$user) throw new Exception('Пользователь не найден');
    
    mysqli_begin_transaction($connect);
    
    // Сохраняем заказ
    $count_goods = count($data['items']);
    $total_sum = floatval($data['total']);
    $delivery_address = mysqli_real_escape_string($connect, $data['delivery_address']);
    
    $sql = "INSERT INTO orders (user_id, count_goods, total_sum, delivery_address) 
            VALUES ('$user_id', '$count_goods', '$total_sum', '$delivery_address')";
    
    if (!mysqli_query($connect, $sql)) {
        throw new Exception('Ошибка сохранения заказа: ' . mysqli_error($connect));
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
    $subject = "Заказ №$order_id оформлен - Анютин сад";
    
    // Формируем HTML письмо
    $email_body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #858C88; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { text-align: center; padding: 20px; color: #666; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #858C88; color: white; padding: 10px; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Ann\'s Garden</h1>
                <p>Заказ оформлен!</p>
            </div>
            <div class="content">
                <p>Здравствуйте, ' . $fullName . '!</p>
                <p>Ваш заказ №' . $order_id . ' успешно оформлен.</p>
                
                <h3>Детали заказа:</h3>
                <table>
                    <tr>
                        <th>Товар</th>
                        <th>Размер</th>
                        <th>Кол-во</th>
                        <th>Цена</th>
                        <th>Сумма</th>
                    </tr>';
    
    foreach ($data['items'] as $item) {
        $item_total = $item['price'] * $item['quantity'];
        $email_body .= '
                    <tr>
                        <td>' . htmlspecialchars($item['name']) . '</td>
                        <td>' . strtoupper($item['size']) . '</td>
                        <td>' . $item['quantity'] . '</td>
                        <td>' . number_format($item['price'], 2) . ' BYN</td>
                        <td>' . number_format($item_total, 2) . ' BYN</td>
                    </tr>';
    }
    
    $email_body .= '
                </table>
                
                <p><strong>Итого:</strong> ' . number_format($total_sum, 2) . ' BYN</p>
                <p><strong>Адрес доставки:</strong> ' . $delivery_address . '</p>
            </div>
            <div class="footer">
                <p>Спасибо за покупку!</p>
                <p>С уважением, команда Ann\'s Garden</p>
            </div>
        </div>
    </body>
    </html>';

    $mail = new PHPMailer(true);
    
    try {
        // Настройки SMTP
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Port       = 2525;
        $mail->Username   = 'api';
        $mail->Password   = 'ffc904b64b7a2adebd3a5417dd1a9831';
        
        $mail->CharSet = 'UTF-8';
        
        // От кого
        $mail->setFrom('shop@annsgarden.com', 'Ann\'s Garden');
        
        // Кому - пользователь, который сделал заказ
        $mail->addAddress($to, $fullName);
        
        // Содержимое письма
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $email_body;
        
        $mail->send();
        
    } catch (Exception $e) {
        // Логируем ошибку, но не прерываем выполнение
        error_log("Ошибка отправки email: " . $mail->ErrorInfo);
    }
    
    // Успешный ответ
    $response['success'] = true;
    $response['order_id'] = $order_id;
    
} catch (Exception $e) {
    if (isset($connect)) mysqli_rollback($connect);
    $response['error'] = $e->getMessage();
}

if (ob_get_length()) ob_clean();
echo json_encode($response);
exit;
?>