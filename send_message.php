<?php
session_start();
require_once('connection.php');

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

try {
    // Получаем данные из POST запроса
    $inputJSON = file_get_contents('php://input');
    $data = json_decode($inputJSON, true);
    
    if (!$data) {
        throw new Exception('Нет данных');
    }
    
    // Валидация
    if (empty($data['name'])) {
        throw new Exception('Укажите ваше имя');
    }
    
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Укажите корректный email');
    }
    
    if (empty($data['message'])) {
        throw new Exception('Напишите ваш вопрос');
    }
    
    // Экранируем данные
    $name = mysqli_real_escape_string($connect, $data['name']);
    $email = mysqli_real_escape_string($connect, $data['email']);
    $message = mysqli_real_escape_string($connect, $data['message']);
    
    // Если пользователь авторизован, сохраняем его ID
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 'NULL';
    
    // Сохраняем в базу данных
    $sql = "INSERT INTO contact_messages (name, email, message, user_id, status, created_at) 
            VALUES ('$name', '$email', '$message', $user_id, 'new', NOW())";
    
    if (mysqli_query($connect, $sql)) {
        $response['success'] = true;
        $response['message_id'] = mysqli_insert_id($connect);
    } else {
        throw new Exception('Ошибка сохранения: ' . mysqli_error($connect));
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>