<?php
session_start();
require_once('../connection.php');
require_once('../telegram.php');

// Проверяем авторизацию пользователя
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser() {
    global $connect;
    if (!isUserLoggedIn()) {
        return null;
    }
    
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT id, firstname, lastname, surname, email, phone, avatar FROM user WHERE id = $user_id";
    $result = mysqli_query($connect, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $user['name'] = $user['firstname'] . ' ' . $user['lastname'];
        return $user;
    }
    
    return null;
}
$currentUser = getCurrentUser();

// Обработка AJAX запроса на сохранение заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Не авторизован']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Нет данных']);
        exit;
    }
    
    try {
        // Получаем данные пользователя
        $userQuery = "SELECT firstname, lastname, email, phone FROM user WHERE id = '$user_id'";
        $userResult = mysqli_query($connect, $userQuery);
        $user = mysqli_fetch_assoc($userResult);
        
        if (!$user) {
            throw new Exception('Пользователь не найден');
        }
        
        mysqli_begin_transaction($connect);
        
        // Сохраняем заказ
        $count_goods = 1; // Для одного товара
        $total_sum = floatval($data['price']);
        $delivery_address = mysqli_real_escape_string($connect, $data['address']);
        
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
        
        // Сохраняем товар
        $product_id = intval($data['product_id']);
        $product_name = mysqli_real_escape_string($connect, $data['product_name']);
        $quantity = 1;
        $price = floatval($data['price']);
        $size = mysqli_real_escape_string($connect, $data['size']);
        
        $sql = "INSERT INTO order_items (order_id, product_id, product_name, quantity, price, size) 
                VALUES ('$order_id', '$product_id', '$product_name', '$quantity', '$price', '$size')";
        
        if (!mysqli_query($connect, $sql)) {
            throw new Exception('Ошибка сохранения товара: ' . mysqli_error($connect));
        }
        
        mysqli_commit($connect);
        
        // Отправляем уведомление в Telegram
        $telegram = new TelegramBot('8643566646:AAE83RsIEQat0opLOR3_qZIG4xk2mzd9Gdo', '1414721913');
        
        $fullName = $user['firstname'] . ' ' . $user['lastname'];
        
        $telegram_message = "<b>🆕 НОВЫЙ ЗАКАЗ #{$order_id}</b>\n\n";
        $telegram_message .= "<b>👤 Клиент:</b> {$fullName}\n";
        $telegram_message .= "<b>📞 Телефон:</b> {$user['phone']}\n";
        $telegram_message .= "<b>📧 Email:</b> {$user['email']}\n\n";
        $telegram_message .= "<b>🛍 Товар:</b> {$product_name}\n";
        $telegram_message .= "<b>📏 Размер:</b> " . strtoupper($size) . "\n";
        $telegram_message .= "<b>💰 Сумма:</b> {$price} BYN\n";
        $telegram_message .= "<b>📍 Адрес:</b> {$delivery_address}\n\n";
        $telegram_message .= "<i>" . date('d.m.Y H:i') . "</i>";
        
        $telegram->sendMessage($telegram_message);
        
        echo json_encode([
            'success' => true,
            'order_id' => $order_id
        ]);
        
    } catch (Exception $e) {
        if (isset($connect)) mysqli_rollback($connect);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Получаем параметры поиска и фильтрации
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Получаем товары по категориям
$sql_piece = "SELECT * FROM goods WHERE category_id = 1"; // Цветы поштучно
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($connect, $search);
    $sql_piece .= " AND title LIKE '%$search_escaped%'";
}
$sql_piece .= " ORDER BY id DESC";
$result_piece = mysqli_query($connect, $sql_piece);
$goods_piece = mysqli_fetch_all($result_piece, MYSQLI_ASSOC);

$sql_bouquets = "SELECT * FROM goods WHERE category_id = 2"; // Авторские букеты
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($connect, $search);
    $sql_bouquets .= " AND title LIKE '%$search_escaped%'";
}
$sql_bouquets .= " ORDER BY id DESC";
$result_bouquets = mysqli_query($connect, $sql_bouquets);
$goods_bouquets = mysqli_fetch_all($result_bouquets, MYSQLI_ASSOC);

// Получаем выбранный товар для отображения цены
$selectedProductId = $_GET['product_id'] ?? 0;
$selectedSize = $_GET['size'] ?? 's';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Каталог товаров - Анютин сад</title>
    <!-- Подключаем шрифты -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vetrino&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-bottom: 30px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            text-decoration: none;
            color: #333;
            font-family: 'Vetrino', sans-serif;
        }
        
        .nav {
            display: flex;
            gap: 40px;
        }
        
        .nav a {
            color: #858C88;
            text-decoration: none;
            font-family: 'Vetrino', sans-serif;
            font-size: 16px;
            font-weight: 500;
            transition: color 0.3s;
            letter-spacing: 0.5px;
        }
        
        .nav a:hover,
        .nav a.active {
            color: #6a736f;
        }
        
        .user-menu {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .user-menu .user-info {
            background: #f5f7fa;
            padding: 8px 16px;
            border-radius: 30px;
            color: #858C88;
            font-family: 'Vetrino', sans-serif;
            font-size: 14px;
        }
        
        .user-menu a {
            color: #858C88;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .logout-btn {
            background: #858C88;
            color: white !important;
            padding: 8px 16px;
            border-radius: 30px;
            transition: all 0.3s;
            font-family: 'Vetrino', sans-serif;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background: #6a736f;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(133, 140, 136, 0.3);
        }

        .catalog-header {
            margin-bottom: 30px;
        }
        
        .catalog-title {
            font-family: 'Vetrino', sans-serif;
            font-size: 36px;
            color: #333;
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .catalog-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 2px;
            background: #858C88;
        }

        .subscription-section {
            padding: 60px 0;
            position: relative;
            background-image: url('../img/back.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            margin-bottom: 40px;
            border-radius: 30px;
            overflow: hidden;
        }
        
        .subscription-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.1);
            pointer-events: none;
        }
        
        .subscription-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
        }
        
        .subscription-title {
            font-family: 'Vetrino', sans-serif;
            font-size: 36px;
            color: #333;
            margin-bottom: 30px;
            font-weight: 500;
            letter-spacing: 0.5px;
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .subscription-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 2px;
            background: #858C88;
        }
        
        .subscription-content {
            display: flex;
            justify-content: flex-end;
            width: 100%;
        }
        
        .subscription-form-wrapper {
            width: 650px;
            max-width: 100%;
        }
        
        .subscription-form {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-family: 'Vetrino', sans-serif;
            font-size: 16px;
        }
        
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 20px;
            font-size: 16px;
            transition: all 0.3s;
            font-family: 'Vetrino', sans-serif;
            color: #333;
            backdrop-filter: blur(5px);
        }
        
        .form-group select {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px;
        }
        
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #858C88;
            outline: none;
            background: rgba(255, 255, 255, 0.4);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .price-info {
            background: rgba(133, 140, 136, 0.3);
            backdrop-filter: blur(5px);
            color: #333;
            padding: 15px 20px;
            border-radius: 20px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Vetrino', sans-serif;
            font-size: 18px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .price-info .price {
            font-weight: bold;
            font-size: 24px;
            color: #000;
        }
        
        .btn-submit {
            background: rgba(133, 140, 136, 0.8);
            backdrop-filter: blur(5px);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 20px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-submit:hover {
            background: rgba(133, 140, 136, 1);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(133, 140, 136, 0.3);
        }

        .category-section {
            margin-bottom: 50px;
        }
        
        .category-title {
            font-family: 'Vetrino', sans-serif;
            font-size: 28px;
            color: #333;
            margin-bottom: 25px;
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .category-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: #858C88;
        }
        
        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(133, 140, 136, 0.15);
        }
        
        .card-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .card-content {
            padding: 20px;
            flex: 1;
        }
        
        .card-title {
            font-family: 'Vetrino', sans-serif;
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .sizes {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .size-btn {
            flex: 1;
            padding: 8px 0;
            text-align: center;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            text-decoration: none;
            color: #666;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .size-btn:hover {
            background: #f5f7fa;
            border-color: #858C88;
            color: #333;
        }
        
        .size-btn.active {
            background: #858C88;
            border-color: #858C88;
            color: white;
        }
        
        .price {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            font-family: 'Vetrino', sans-serif;
        }
        
        .price span {
            color: #858C88;
            font-size: 14px;
            font-weight: normal;
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        
        .order-btn {
            flex: 2;
            background: #858C88;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
        }
        
        .order-btn:hover {
            background: #6a736f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(133, 140, 136, 0.3);
        }
        
        .cart-btn {
            flex: 1;
            background: #f5f7fa;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
        }
        
        .cart-btn:hover {
            background: #e9eef5;
            border-color: #858C88;
            transform: translateY(-2px);
        }
        
        .cart-btn img {
            width: 20px;
            height: 20px;
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        
        .cart-btn:hover img {
            opacity: 1;
        }
        
        .empty-result {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
            color: #666;
        }
        
        .empty-result i {
            font-size: 60px;
            color: #ccc;
            margin-bottom: 20px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: slideDown 0.3s ease;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .modal-header h2 {
            font-family: 'Vetrino', sans-serif;
            font-size: 24px;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close:hover {
            color: #333;
        }
        
        .user-info {
            background: #f5f7fa;
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .user-info p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }
        
        .user-info strong {
            color: #333;
            display: block;
            margin-bottom: 10px;
        }
        
        .order-summary {
            background: #f5f7fa;
            padding: 15px;
            border-radius: 15px;
            margin: 15px 0;
        }
        
        .order-summary h3 {
            font-family: 'Vetrino', sans-serif;
            font-size: 18px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .order-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .order-detail .label {
            color: #666;
        }
        
        .order-detail .value {
            font-weight: 600;
            color: #333;
        }
        
        .total-price {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #ddd;
            font-weight: bold;
            color: #858C88;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 15px;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            resize: vertical;
            transition: all 0.3s;
        }
        
        .form-group textarea:focus {
            border-color: #858C88;
            outline: none;
            box-shadow: 0 0 0 2px rgba(133, 140, 136, 0.1);
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .modal-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
        }
        
        .modal-btn-primary {
            background: #858C88;
            color: white;
        }
        
        .modal-btn-primary:hover {
            background: #6a736f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(133, 140, 136, 0.3);
        }
        
        .modal-btn-secondary {
            background: #f5f7fa;
            color: #666;
        }
        
        .modal-btn-secondary:hover {
            background: #e9eef5;
            color: #333;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav {
                flex-wrap: wrap;
                justify-content: center;
                gap: 20px;
            }
            
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-group {
                min-width: 100%;
            }
            
            .catalog-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .subscription-container {
                padding: 0 20px;
            }
            
            .subscription-form {
                padding: 30px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .catalog-title {
                font-size: 28px;
            }
            
            .card-image {
                height: 200px;
            }
            
            .subscription-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <a href="../index.php" class="logo">Анютин сад</a>
            
            <nav class="nav">
                <a href="../index.php#about">О нас</a>
                <a href="../index.php#subscription">Подписка</a>
                <a href="catalog.php" class="active">Каталог</a>
                <a href="../index.php#faq">FAQ</a>
                <a href="../index.php#contacts">Контакты</a>
            </nav>
            
            <div class="user-menu">
                <?php if ($currentUser): ?>
                    <span class="user-info"><i class="far fa-user-circle"></i> <?= htmlspecialchars($currentUser['firstname']) ?></span>
                    <a href="../profile.php">Профиль</a>
                    <a href="../logout.php" class="logout-btn">Выйти</a>
                <?php else: ?>
                    <a href="../registration/login.php">Войти</a>
                    <a href="../registration/reg.php">Регистрация</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <div class="container">
        <div class="catalog-header">
            <h1 class="catalog-title">Каталог цветов</h1>
        </div>
        <!-- Секция подписки -->
        <section class="subscription-section">
            <div class="subscription-container">
                <h2 class="subscription-title">Цветочная подписка</h2>
                <div class="subscription-content">
                    <div class="subscription-form-wrapper">
                        <form id="subscriptionForm" class="subscription-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Частота доставки</label>
                                    <select id="frequency" required>
                                        <option value="">Выберите частоту</option>
                                        <option value="1 раз в месяц">1 раз в месяц</option>
                                        <option value="2 раза в месяц">2 раза в месяц</option>
                                        <option value="4 раза в месяц">4 раза в месяц</option>
                                        <option value="Каждую неделю">Каждую неделю</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Количество цветов</label>
                                    <select id="quantity" required>
                                        <option value="">Выберите количество</option>
                                        <option value="15-20">15-20 цветов</option>
                                        <option value="20-40">20-40 цветов</option>
                                        <option value="40-70">40-70 цветов</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Адрес доставки</label>
                                <textarea id="address" placeholder="Введите полный адрес доставки" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Дополнительные пожелания</label>
                                <textarea id="notes" placeholder="Ваши пожелания по цветам (необязательно)"></textarea>
                            </div>
                            
                            <div id="priceDisplay" class="price-info" style="display: none;">
                                <span>Стоимость подписки:</span>
                                <span class="price" id="totalPrice">0 BYN</span>
                            </div>
                            
                            <button type="submit" class="btn-submit" id="submitBtn">Оформить подписку</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Авторские букеты-->
        <section class="category-section">
            <h2 class="category-title">Авторские букеты</h2>
            
            <?php if (empty($goods_bouquets)): ?>
                <div class="empty-result">
                    <i class="fas fa-searching"></i>
                    <p>В этой категории пока нет товаров</p>
                </div>
            <?php else: ?>
                <div class="catalog-grid">
                    <?php foreach ($goods_bouquets as $item): 
                        $displayPrice = $item['price_s'];                  
                        if ($selectedProductId == $item['id']) {
                            if ($selectedSize == 's') $displayPrice = $item['price_s'];
                            elseif ($selectedSize == 'm') $displayPrice = $item['price_m'];
                            elseif ($selectedSize == 'l') $displayPrice = $item['price_l'];
                        }
                    ?>
                        <div class="card">
                            <img src="<?= htmlspecialchars($item['image'] ? '../img/' . $item['image'] : '../img/no-photo.png') ?>" 
                                 alt="<?= htmlspecialchars($item['title']) ?>" 
                                 class="card-image">
                            
                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($item['title']) ?></h3>
                                
                                <div class="sizes">
                                    <a href="?product_id=<?= $item['id'] ?>&size=s<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" 
                                       class="size-btn <?= ($selectedProductId == $item['id'] && $selectedSize == 's') ? 'active' : '' ?>">S</a>
                                    <a href="?product_id=<?= $item['id'] ?>&size=m<?= !empty($search) ? '&search='.urlencode($search) : '' ?>"
                                       class="size-btn <?= ($selectedProductId == $item['id'] && $selectedSize == 'm') ? 'active' : '' ?>">M</a>
                                    <a href="?product_id=<?= $item['id'] ?>&size=l<?= !empty($search) ? '&search='.urlencode($search) : '' ?>"
                                       class="size-btn <?= ($selectedProductId == $item['id'] && $selectedSize == 'l') ? 'active' : '' ?>">L</a>
                                </div>
                                
                                <div class="price">
                                    <?= htmlspecialchars($displayPrice) ?> <span>BYN</span>
                                </div>
                                
                                <div class="card-actions">
                                    <button class="order-btn" 
                                            data-product-id="<?= $item['id'] ?>"
                                            data-product-name="<?= htmlspecialchars($item['title']) ?>"
                                            data-product-price="<?= $displayPrice ?>"
                                            data-product-size="<?= $selectedSize ?>"
                                            onclick="openOrderModal(this); return false;">
                                        Оформить заказ
                                    </button>
                                    
                                    <button class="cart-btn" onclick="addToCart(<?= $item['id'] ?>, '<?= $selectedSize ?>')">
                                        <img src="../img/cart.png" alt="В корзину">
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <!-- Цветы поштучно-->
        <section class="category-section">
            <h2 class="category-title">Цветы поштучно</h2>
            
            <?php if (empty($goods_piece)): ?>
                <div class="empty-result">
                    <i class="fas fa-searching"></i>
                    <p>В этой категории пока нет товаров</p>
                </div>
            <?php else: ?>
                <div class="catalog-grid">
                    <?php foreach ($goods_piece as $item): 
                        $displayPrice = $item['price_s'];                  
                        if ($selectedProductId == $item['id']) {
                            if ($selectedSize == 's') $displayPrice = $item['price_s'];
                            elseif ($selectedSize == 'm') $displayPrice = $item['price_m'];
                            elseif ($selectedSize == 'l') $displayPrice = $item['price_l'];
                        }
                    ?>
                        <div class="card">
                            <img src="<?= htmlspecialchars($item['image'] ? '../img/' . $item['image'] : '../img/no-photo.png') ?>" 
                                 alt="<?= htmlspecialchars($item['title']) ?>" 
                                 class="card-image">
                            
                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($item['title']) ?></h3>
                                
                                <div class="sizes">
                                    <a href="?product_id=<?= $item['id'] ?>&size=s<?= !empty($search) ? '&search='.urlencode($search) : '' ?>" 
                                       class="size-btn <?= ($selectedProductId == $item['id'] && $selectedSize == 's') ? 'active' : '' ?>">S</a>
                                    <a href="?product_id=<?= $item['id'] ?>&size=m<?= !empty($search) ? '&search='.urlencode($search) : '' ?>"
                                       class="size-btn <?= ($selectedProductId == $item['id'] && $selectedSize == 'm') ? 'active' : '' ?>">M</a>
                                    <a href="?product_id=<?= $item['id'] ?>&size=l<?= !empty($search) ? '&search='.urlencode($search) : '' ?>"
                                       class="size-btn <?= ($selectedProductId == $item['id'] && $selectedSize == 'l') ? 'active' : '' ?>">L</a>
                                </div>
                                
                                <div class="price">
                                    <?= htmlspecialchars($displayPrice) ?> <span>BYN</span>
                                </div>
                                
                                <div class="card-actions">
                                    <button class="order-btn" 
                                            data-product-id="<?= $item['id'] ?>"
                                            data-product-name="<?= htmlspecialchars($item['title']) ?>"
                                            data-product-price="<?= $displayPrice ?>"
                                            data-product-size="<?= $selectedSize ?>"
                                            onclick="openOrderModal(this); return false;">
                                        Оформить заказ
                                    </button>
                                    
                                    <button class="cart-btn" onclick="addToCart(<?= $item['id'] ?>, '<?= $selectedSize ?>')">
                                        <img src="../img/cart.png" alt="В корзину">
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <!-- Модальное окно для заказа -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Подтверждение заказа</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <?php if ($currentUser): ?>
            <div class="user-info">
                <strong>Вы оформляете заказ как:</strong>
                <p><i class="far fa-user"></i> <?= htmlspecialchars($currentUser['name']) ?></p>
                <p><i class="far fa-envelope"></i> <?= htmlspecialchars($currentUser['email']) ?></p>
                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($currentUser['phone'] ?? 'Не указан') ?></p>
            </div>
            <?php endif; ?>
            
            <div id="orderSummary"></div>
            
            <div class="form-group">
                <label for="delivery_address">Адрес доставки</label>
                <textarea id="delivery_address" placeholder="Введите адрес доставки (улица, дом, квартира)" required></textarea>
            </div>
            
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-primary" onclick="submitOrder()">Подтвердить заказ</button>
                <button class="modal-btn modal-btn-secondary" onclick="closeModal()">Отмена</button>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно для подписки -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Оплата подписки</h2>
                <span class="close" onclick="closePaymentModal()">&times;</span>
            </div>
            
            <div class="card-info" style="text-align: center; padding: 20px;">
                <p>В разработке</p>
                <p>Сейчас оплата не требуется</p>
            </div>
            
            <button class="modal-btn modal-btn-primary" onclick="completeSubscription()" style="width: 100%;">
                Завершить оформление
            </button>
        </div>
    </div>
    
    <script>
    window.isUserLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
    window.currentUser = {
        name: '<?= $currentUser ? addslashes($currentUser['name']) : '' ?>',
        email: '<?= $currentUser ? addslashes($currentUser['email']) : '' ?>',
        phone: '<?= $currentUser ? addslashes($currentUser['phone']) : '' ?>'
    };
    
    let currentOrder = null;
    
    function openOrderModal(button) {
        if (!window.isUserLoggedIn) {
            if (confirm('Для оформления заказа необходимо войти в систему')) {
                window.location.href = '../registration/login.php?redirect=' + encodeURIComponent(window.location.href);
            }
            return;
        }
        
        const productId = button.getAttribute('data-product-id');
        const productName = button.getAttribute('data-product-name');
        const productPrice = button.getAttribute('data-product-price');
        const productSize = button.getAttribute('data-product-size');
        
        currentOrder = {
            id: productId,
            name: productName,
            price: parseFloat(productPrice),
            size: productSize
        };
        
        const orderSummary = document.getElementById('orderSummary');
        orderSummary.innerHTML = `
            <div class="order-summary">
                <h3>Ваш заказ:</h3>
                <div class="order-detail">
                    <span class="label">Товар:</span>
                    <span class="value">${productName}</span>
                </div>
                <div class="order-detail">
                    <span class="label">Размер:</span>
                    <span class="value">${productSize.toUpperCase()}</span>
                </div>
                <div class="order-detail">
                    <span class="label">Цена:</span>
                    <span class="value">${productPrice} BYN</span>
                </div>
                <div class="order-detail total-price">
                    <span class="label">Итого:</span>
                    <span class="value">${productPrice} BYN</span>
                </div>
            </div>
        `;
        
        document.getElementById('orderModal').style.display = 'block';
    }
    
    function closeModal() {
        document.getElementById('orderModal').style.display = 'none';
        document.getElementById('delivery_address').value = '';
    }
    
    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
    }
    
    function submitOrder() {
        const address = document.getElementById('delivery_address').value.trim();
        
        if (!address) {
            alert('Пожалуйста, введите адрес доставки');
            return;
        }
        
        const btn = document.querySelector('#orderModal .modal-btn-primary');
        const originalText = btn.textContent;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Оформление...';
        btn.disabled = true;
        
        fetch('catalog.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                product_id: currentOrder.id,
                product_name: currentOrder.name,
                price: currentOrder.price,
                size: currentOrder.size,
                address: address
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal();
                alert(`Заказ №${data.order_id} успешно оформлен!`);
            } else {
                alert('Ошибка при оформлении заказа: ' + (data.error || 'Неизвестная ошибка'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Произошла ошибка при оформлении заказа');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
    
    function calculatePrice() {
        const frequency = document.getElementById('frequency').value;
        const quantity = document.getElementById('quantity').value;
        
        if (!frequency || !quantity) {
            document.getElementById('priceDisplay').style.display = 'none';
            return;
        }
        
        let basePrice = 0;
        switch(frequency) {
            case '1 раз в месяц': basePrice = 29.99; break;
            case '2 раза в месяц': basePrice = 49.99; break;
            case '4 раза в месяц': basePrice = 89.99; break;
            case 'Каждую неделю': basePrice = 129.99; break;
        }
        
        let multiplier = 1;
        switch(quantity) {
            case '15-20': multiplier = 1; break;
            case '20-40': multiplier = 1.5; break;
            case '40-70': multiplier = 2; break;
        }
        
        const totalPrice = (basePrice * multiplier).toFixed(2);
        document.getElementById('totalPrice').textContent = totalPrice + ' BYN';
        document.getElementById('priceDisplay').style.display = 'flex';
    }

    function handleSubscriptionSubmit(event) {
        event.preventDefault();
        
        if (!window.isUserLoggedIn) {
            if (confirm('Для оформления подписки необходимо войти в систему')) {
                window.location.href = '../registration/login.php?redirect=' + encodeURIComponent(window.location.href);
            }
            return;
        }
        
        const frequency = document.getElementById('frequency').value;
        const quantity = document.getElementById('quantity').value;
        const address = document.getElementById('address').value;
        const notes = document.getElementById('notes').value;
        const price = document.getElementById('totalPrice').textContent;
        
        if (!frequency) {
            alert('Пожалуйста, выберите частоту доставки');
            return;
        }
        
        if (!quantity) {
            alert('Пожалуйста, выберите количество цветов');
            return;
        }
        
        if (!address || address.trim() === '') {
            alert('Пожалуйста, введите адрес доставки');
            return;
        }
        
        window.subscriptionData = {
            frequency: frequency,
            quantity: quantity,
            address: address,
            notes: notes,
            price: price,
            date: new Date().toLocaleString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })
        };
        
        document.getElementById('paymentModal').style.display = 'block';
    }
    
    function completeSubscription() {
        const sub = window.subscriptionData;
        
        if (!sub) {
            alert('Ошибка: данные подписки не найдены');
            return;
        }
        
        if (!window.isUserLoggedIn) {
            alert('Необходимо авторизоваться');
            window.location.href = '../registration/login.php';
            return;
        }
        
        const btn = document.querySelector('#paymentModal .modal-btn-primary');
        const originalText = btn.textContent;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';
        btn.disabled = true;
        
        fetch('../index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                frequency: sub.frequency,
                quantity: sub.quantity,
                address: sub.address,
                notes: sub.notes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closePaymentModal();
                
                document.getElementById('subscriptionForm').reset();
                document.getElementById('priceDisplay').style.display = 'none';
                
                alert(`Подписка №${data.subscription_id} успешно оформлена!`);
            } else {
                alert('Ошибка при сохранении подписки: ' + (data.error || 'Неизвестная ошибка'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Произошла ошибка при сохранении подписки');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }

    function addToCart(productId, size) {
        alert('Функция корзины в разработке');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const frequencySelect = document.getElementById('frequency');
        const quantitySelect = document.getElementById('quantity');
        const subscriptionForm = document.getElementById('subscriptionForm');
        
        if (frequencySelect) {
            frequencySelect.addEventListener('change', calculatePrice);
        }
        
        if (quantitySelect) {
            quantitySelect.addEventListener('change', calculatePrice);
        }
        
        if (subscriptionForm) {
            subscriptionForm.addEventListener('submit', handleSubscriptionSubmit);
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            const paymentModal = document.getElementById('paymentModal');
            if (event.target === modal) {
                closeModal();
            }
            if (event.target === paymentModal) {
                closePaymentModal();
            }
        };
    });
    </script>
</body>
</html>