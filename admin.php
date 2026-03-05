<?php
session_start();
require_once('connection.php');

// Проверяем, авторизован ли пользователь и является ли он админом
if (!isset($_SESSION['user_id'])) {
    header('Location: registration/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$checkAdmin = "SELECT role FROM user WHERE id = $user_id";
$result = mysqli_query($connect, $checkAdmin);
$user = mysqli_fetch_assoc($result);

if ($user['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';

// Обработка действий администратора
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Добавление товара
    if (isset($_POST['add_product'])) {
        $title = mysqli_real_escape_string($connect, $_POST['title']);
        $price_s = floatval($_POST['price_s']);
        $price_m = floatval($_POST['price_m']);
        $price_l = floatval($_POST['price_l']);
        $image = mysqli_real_escape_string($connect, $_POST['image']);
        $count_seeds = intval($_POST['count_seeds']);
        $category_id = intval($_POST['category_id']);
        
        $sql = "INSERT INTO goods (title, price_s, price_m, price_l, image, count_seeds, category_id) 
                VALUES ('$title', $price_s, $price_m, $price_l, '$image', $count_seeds, $category_id)";
        
        if (mysqli_query($connect, $sql)) {
            $message = "Товар успешно добавлен!";
            $message_type = "success";
        } else {
            $message = "Ошибка: " . mysqli_error($connect);
            $message_type = "error";
        }
    }
    
    // Обновление товара
    if (isset($_POST['update_product'])) {
        $id = intval($_POST['id']);
        $title = mysqli_real_escape_string($connect, $_POST['title']);
        $price_s = floatval($_POST['price_s']);
        $price_m = floatval($_POST['price_m']);
        $price_l = floatval($_POST['price_l']);
        $image = mysqli_real_escape_string($connect, $_POST['image']);
        $count_seeds = intval($_POST['count_seeds']);
        $category_id = intval($_POST['category_id']);
        
        $sql = "UPDATE goods SET 
                title = '$title',
                price_s = $price_s,
                price_m = $price_m,
                price_l = $price_l,
                image = '$image',
                count_seeds = $count_seeds,
                category_id = $category_id
                WHERE id = $id";
        
        if (mysqli_query($connect, $sql)) {
            $message = "Товар успешно обновлен!";
            $message_type = "success";
        } else {
            $message = "Ошибка: " . mysqli_error($connect);
            $message_type = "error";
        }
    }
    
    // Удаление товара
    if (isset($_POST['delete_product'])) {
        $id = intval($_POST['id']);
        $sql = "DELETE FROM goods WHERE id = $id";
        
        if (mysqli_query($connect, $sql)) {
            $message = "Товар удален";
            $message_type = "success";
        } else {
            $message = "Ошибка: " . mysqli_error($connect);
            $message_type = "error";
        }
    }
    
    // Добавление вопроса FAQ
    if (isset($_POST['add_faq'])) {
        $question = mysqli_real_escape_string($connect, $_POST['question']);
        $answer = mysqli_real_escape_string($connect, $_POST['answer']);
        
        $sql_question = "INSERT INTO questions (question) VALUES ('$question')";
        mysqli_query($connect, $sql_question);
        $question_id = mysqli_insert_id($connect);
        
        $sql_answer = "INSERT INTO answers (answer, question_id) VALUES ('$answer', $question_id)";
        
        if (mysqli_query($connect, $sql_answer)) {
            $message = "Вопрос FAQ добавлен!";
            $message_type = "success";
        } else {
            $message = "Ошибка: " . mysqli_error($connect);
            $message_type = "error";
        }
    }
    
    // Обновление FAQ
    if (isset($_POST['update_faq'])) {
        $question_id = intval($_POST['question_id']);
        $answer_id = intval($_POST['answer_id']);
        $question = mysqli_real_escape_string($connect, $_POST['question']);
        $answer = mysqli_real_escape_string($connect, $_POST['answer']);
        
        mysqli_query($connect, "UPDATE questions SET question = '$question' WHERE id = $question_id");
        mysqli_query($connect, "UPDATE answers SET answer = '$answer' WHERE id = $answer_id");
        
        $message = "FAQ обновлен";
        $message_type = "success";
    }
    
    // Удаление FAQ
    if (isset($_POST['delete_faq'])) {
        $question_id = intval($_POST['question_id']);
        mysqli_query($connect, "DELETE FROM questions WHERE id = $question_id");
        
        $message = "FAQ удален";
        $message_type = "success";
    }
    
    // Обновление статуса заказа
    if (isset($_POST['update_order_status'])) {
        $order_id = intval($_POST['order_id']);
        $status = mysqli_real_escape_string($connect, $_POST['status']);
        
        mysqli_query($connect, "UPDATE orders SET status = '$status' WHERE id = $order_id");
        $message = "Статус заказа обновлен";
        $message_type = "success";
    }

    // Публикация сообщения в FAQ
    if (isset($_POST['publish_to_faq'])) {
        $message_id = intval($_POST['message_id']);
        $question = mysqli_real_escape_string($connect, $_POST['question']);
        $answer = mysqli_real_escape_string($connect, $_POST['answer']);
        $keep_message = isset($_POST['keep_message']) ? 1 : 0;
        
        // Добавляем вопрос в таблицу questions
        $sql_question = "INSERT INTO questions (question) VALUES ('$question')";
        if (mysqli_query($connect, $sql_question)) {
            $question_id = mysqli_insert_id($connect);
            
            // Добавляем ответ в таблицу answers
            $sql_answer = "INSERT INTO answers (answer, question_id) VALUES ('$answer', $question_id)";
            
            if (mysqli_query($connect, $sql_answer)) {
                // Обновляем статус сообщения
                if (!$keep_message) {
                    // Удаляем сообщение
                    mysqli_query($connect, "DELETE FROM contact_messages WHERE id = $message_id");
                } else {
                    // Отмечаем как опубликованное
                    mysqli_query($connect, "UPDATE contact_messages SET status = 'published' WHERE id = $message_id");
                }
                
                $message = "Вопрос успешно опубликован в FAQ!";
                $message_type = "success";
            } else {
                $message = "Ошибка при сохранении ответа: " . mysqli_error($connect);
                $message_type = "error";
            }
        } else {
            $message = "Ошибка при сохранении вопроса: " . mysqli_error($connect);
            $message_type = "error";
        }
    }
    
    // Удаление сообщения
    if (isset($_POST['delete_message'])) {
        $message_id = intval($_POST['delete_message']);
        mysqli_query($connect, "DELETE FROM contact_messages WHERE id = $message_id");
        $message = "Сообщение удалено";
        $message_type = "success";
    }
    
    // Отметить как прочитанное
    if (isset($_POST['mark_as_read'])) {
        $message_id = intval($_POST['message_id']);
        mysqli_query($connect, "UPDATE contact_messages SET status = 'read' WHERE id = $message_id");
        $message = "Сообщение отмечено как прочитанное";
        $message_type = "success";
    }
}

// Получаем данные для отображения
$products = mysqli_query($connect, "SELECT * FROM goods ORDER BY id DESC");
$faq = mysqli_query($connect, 
    "SELECT q.id as q_id, q.question, a.id as a_id, a.answer 
     FROM questions q 
     LEFT JOIN answers a ON q.id = a.question_id 
     ORDER BY q.id DESC");
$orders = mysqli_query($connect, 
    "SELECT o.*, u.firstname, u.lastname 
     FROM orders o 
     JOIN user u ON o.user_id = u.id 
     ORDER BY o.id DESC LIMIT 20");
$users = mysqli_query($connect, "SELECT id, firstname, lastname, email, phone, role FROM user ORDER BY id DESC");

// Получаем сообщения
$faq_tab = isset($_GET['faq_tab']) ? $_GET['faq_tab'] : 'all';

if ($faq_tab == 'from_messages') {
    $messages = mysqli_query($connect, 
        "SELECT cm.*, 
                'from_message' as source
         FROM contact_messages cm
         WHERE cm.status IN ('new', 'read')
         ORDER BY 
             CASE cm.status 
                 WHEN 'new' THEN 1 
                 WHEN 'read' THEN 2 
             END,
             cm.created_at DESC"
    );
} else {
    $messages = mysqli_query($connect, 
        "SELECT cm.*, 
                'from_message' as source
         FROM contact_messages cm
         ORDER BY 
             CASE cm.status 
                 WHEN 'new' THEN 1 
                 WHEN 'read' THEN 2 
                 WHEN 'published' THEN 3 
             END,
             cm.created_at DESC
         LIMIT 50"
    );
}

// Статистика для дашборда
$products_count = mysqli_num_rows(mysqli_query($connect, "SELECT id FROM goods"));
$users_count = mysqli_num_rows(mysqli_query($connect, "SELECT id FROM user"));
$orders_count = mysqli_num_rows(mysqli_query($connect, "SELECT id FROM orders"));
$faq_count = mysqli_num_rows(mysqli_query($connect, "SELECT id FROM questions"));
$messages_count = mysqli_num_rows(mysqli_query($connect, "SELECT id FROM contact_messages WHERE status = 'new'"));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - Анютин сад</title>
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
        }
        
        .admin-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: white;
            padding: 30px 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-logo {
            font-family: 'Vetrino', sans-serif;
            font-size: 24px;
            font-weight: 500;
            text-align: center;
            padding-bottom: 30px;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 30px;
            color: #333;
        }
        
        .sidebar-logo span {
            color: #858C88;
            font-size: 14px;
            display: block;
            margin-top: 5px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 10px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 12px 15px;
            color: #666;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            font-size: 15px;
            font-weight: 500;
            position: relative;
        }
        
        .sidebar-menu a:hover {
            background: #f5f7fa;
            color: #858C88;
        }
        
        .sidebar-menu a.active {
            background: #858C88;
            color: white;
        }
        
        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
        }
        
        .badge {
            position: absolute;
            right: 15px;
            top: 10px;
            background: #dc3545;
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: normal;
        }
        
        .main-content {
            padding: 30px;
            background: #f5f7fa;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .header-title h1 {
            font-family: 'Vetrino', sans-serif;
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .header-title p {
            color: #858C88;
            font-size: 14px;
        }
        
        .header-btn {
            background: #858C88;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .header-btn:hover {
            background: #6a736f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(133, 140, 136, 0.3);
        }
        
        /* Секции админ-панели */
        .admin-section {
            display: none;
        }
        
        .admin-section.active {
            display: block;
        }
        
        .section-title {
            font-family: 'Vetrino', sans-serif;
            font-size: 28px;
            color: #333;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: #858C88;
        }
        
        /* Карточки статистики */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(133, 140, 136, 0.1);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #858C88;
            margin-bottom: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 600;
            color: #333;
            font-family: 'Vetrino', sans-serif;
        }
        
        /* Таблицы */
        .table-responsive {
            overflow-x: auto;
            background: white;
            border-radius: 15px;
            padding: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        th {
            text-align: left;
            padding: 15px;
            background: #f5f7fa;
            color: #333;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #666;
        }
        
        tr:hover {
            background: #fafafa;
        }
        
        /* Кнопки */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 30px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
        }
        
        .btn-primary {
            background: #858C88;
            color: white;
        }
        
        .btn-primary:hover {
            background: #6a736f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(133, 140, 136, 0.3);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            margin: 0 2px;
        }
        
        /* Формы */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #858C88;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #858C88;
            outline: none;
            box-shadow: 0 0 0 2px rgba(133, 140, 136, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        /* Модальное окно */
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
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            font-family: 'Vetrino', sans-serif;
            font-size: 22px;
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
        
        /* Сообщения */
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Статусы */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-new { background: #cce5ff; color: #004085; }
        .status-processing { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-read { background: #e2e3e5; color: #383d41; }
        .status-published { background: #d1ecf1; color: #0c5460; }
        
        /* Вкладки */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        /* Адаптивность */
        @media (max-width: 992px) {
            .admin-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .section-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Сайдбар -->
        <div class="sidebar">
            <div class="sidebar-logo">
                Анютин сад
                <span>Админ-панель</span>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="#" onclick="showSection('dashboard')" class="active" id="menu-dashboard"><i class="fas fa-tachometer-alt"></i> Дашборд</a></li>
                <li><a href="#" onclick="showSection('products')" id="menu-products"><i class="fas fa-box"></i> Товары</a></li>
                <li><a href="#" onclick="showSection('faq')" id="menu-faq"><i class="fas fa-question-circle"></i> FAQ <?php if ($messages_count > 0): ?><span class="badge"><?= $messages_count ?></span><?php endif; ?></a></li>
                <li><a href="#" onclick="showSection('orders')" id="menu-orders"><i class="fas fa-shopping-cart"></i> Заказы</a></li>
                <li><a href="#" onclick="showSection('users')" id="menu-users"><i class="fas fa-users"></i> Пользователи</a></li>
                <li><a href="index.php"><i class="fas fa-arrow-left"></i> На сайт</a></li>
            </ul>
        </div>
        
        <!-- Основной контент -->
        <div class="main-content">
            <div class="header">
                <div class="header-title">
                    <h1>Добро пожаловать, Администратор!</h1>
                    <p>Управляйте своим магазином</p>
                </div>
                <a href="logout.php" class="header-btn"><i class="fas fa-sign-out-alt"></i> Выйти</a>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?= $message_type ?>"><?= $message ?></div>
            <?php endif; ?>
            
            <!-- Дашборд -->
            <div id="dashboard-section" class="admin-section active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Товаров</h3>
                        <div class="number"><?= $products_count ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Пользователей</h3>
                        <div class="number"><?= $users_count ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Заказов</h3>
                        <div class="number"><?= $orders_count ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>FAQ</h3>
                        <div class="number"><?= $faq_count ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Новых сообщений</h3>
                        <div class="number"><?= $messages_count ?></div>
                    </div>
                </div>
                
                <h2 class="section-title">Последние заказы</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Клиент</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recent_orders = mysqli_query($connect, "SELECT o.*, u.firstname, u.lastname FROM orders o JOIN user u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 5");
                            while ($order = mysqli_fetch_assoc($recent_orders)): 
                            ?>
                            <tr>
                                <td>#<?= $order['id'] ?></td>
                                <td><?= $order['firstname'] . ' ' . $order['lastname'] ?></td>
                                <td><?= $order['total_sum'] ?> BYN</td>
                                <td>
                                    <span class="status-badge status-<?= $order['status'] ?? 'new' ?>">
                                        <?= $order['status'] ?? 'new' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editOrder(<?= $order['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Управление товарами -->
            <div id="products-section" class="admin-section">
                <h2 class="section-title">Управление товарами</h2>
                
                <button class="btn btn-primary" onclick="showAddProductForm()" style="margin-bottom: 20px;">
                    <i class="fas fa-plus"></i> Добавить товар
                </button>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Цена S</th>
                                <th>Цена M</th>
                                <th>Цена L</th>
                                <th>Изображение</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = mysqli_fetch_assoc($products)): ?>
                            <tr>
                                <td><?= $product['id'] ?></td>
                                <td><?= htmlspecialchars($product['title']) ?></td>
                                <td><?= $product['price_s'] ?> BYN</td>
                                <td><?= $product['price_m'] ?> BYN</td>
                                <td><?= $product['price_l'] ?> BYN</td>
                                <td><?= $product['image'] ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editProduct(<?= $product['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?= $product['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Управление FAQ и сообщениями -->
            <div id="faq-section" class="admin-section">
                <h2 class="section-title">Управление FAQ и сообщениями</h2>
                
                <!-- Вкладки -->
                <div class="tabs">
                    <a href="admin.php?faq_tab=all" class="btn <?= $faq_tab == 'all' ? 'btn-primary' : 'btn-secondary' ?>">
                        Все вопросы
                    </a>
                    <a href="admin.php?faq_tab=from_messages" class="btn <?= $faq_tab == 'from_messages' ? 'btn-primary' : 'btn-secondary' ?>">
                        Из сообщений <?php if ($messages_count > 0): ?><span class="badge" style="position: static; margin-left: 5px;"><?= $messages_count ?></span><?php endif; ?>
                    </a>
                </div>
                
                <button class="btn btn-primary" onclick="showAddFaqForm()" style="margin-bottom: 20px;">
                    <i class="fas fa-plus"></i> Добавить вопрос
                </button>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Источник</th>
                                <th>Вопрос</th>
                                <th>Ответ/Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($faq_tab == 'from_messages'): ?>
                                <?php if ($messages && mysqli_num_rows($messages) > 0): ?>
                                    <?php while ($msg = mysqli_fetch_assoc($messages)): ?>
                                        <tr>
                                            <td>#MSG-<?= $msg['id'] ?></td>
                                            <td>
                                                <span class="status-badge" style="background: #fff3cd; color: #856404;">
                                                    <i class="fas fa-envelope"></i> Сообщение
                                                </span>
                                                <div style="font-size: 12px; margin-top: 5px;">
                                                    <strong><?= htmlspecialchars($msg['name']) ?></strong><br>
                                                    <?= htmlspecialchars($msg['email']) ?><br>
                                                    <small><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></small>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars(mb_substr($msg['message'], 0, 100)) ?>...</td>
                                            <td>
                                                <span class="status-badge status-<?= $msg['status'] ?>">
                                                    <?= $msg['status'] == 'new' ? 'Новый' : ($msg['status'] == 'read' ? 'Прочитан' : 'Опубликован') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="viewMessage(<?= $msg['id'] ?>, '<?= htmlspecialchars(addslashes($msg['name'])) ?>', '<?= htmlspecialchars(addslashes($msg['email'])) ?>', '<?= htmlspecialchars(addslashes($msg['message'])) ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="publishMessageToFaq(<?= $msg['id'] ?>, '<?= htmlspecialchars(addslashes($msg['message'])) ?>')">
                                                    <i class="fas fa-globe"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteMessage(<?= $msg['id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 40px;">
                                            <i class="fas fa-inbox" style="font-size: 40px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                            Нет новых сообщений
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (mysqli_num_rows($faq) > 0): ?>
                                    <?php while ($item = mysqli_fetch_assoc($faq)): ?>
                                        <tr>
                                            <td>#<?= $item['q_id'] ?></td>
                                            <td>
                                                <span class="status-badge" style="background: #d4edda; color: #155724;">
                                                    <i class="fas fa-check-circle"></i> В FAQ
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars(mb_substr($item['question'], 0, 100)) ?>...</td>
                                            <td><?= htmlspecialchars(mb_substr($item['answer'] ?? '', 0, 100)) ?>...</td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editFaq(<?= $item['q_id'] ?>, <?= $item['a_id'] ?? 0 ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteFaq(<?= $item['q_id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 40px;">
                                            <i class="fas fa-question-circle" style="font-size: 40px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                            Нет вопросов в FAQ
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Управление заказами -->
            <div id="orders-section" class="admin-section">
                <h2 class="section-title">Управление заказами</h2>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Клиент</th>
                                <th>Сумма</th>
                                <th>Товаров</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                            <tr>
                                <td>#<?= $order['id'] ?></td>
                                <td><?= $order['firstname'] . ' ' . $order['lastname'] ?></td>
                                <td><?= $order['total_sum'] ?> BYN</td>
                                <td><?= $order['count_goods'] ?></td>
                                <td>
                                    <select class="status-select" onchange="updateOrderStatus(<?= $order['id'] ?>, this.value)" style="padding: 5px; border-radius: 5px; border: 1px solid #e0e0e0;">
                                        <option value="new" <?= ($order['status'] ?? 'new') == 'new' ? 'selected' : '' ?>>Новый</option>
                                        <option value="processing" <?= ($order['status'] ?? '') == 'processing' ? 'selected' : '' ?>>В обработке</option>
                                        <option value="completed" <?= ($order['status'] ?? '') == 'completed' ? 'selected' : '' ?>>Выполнен</option>
                                        <option value="cancelled" <?= ($order['status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>Отменен</option>
                                    </select>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="viewOrder(<?= $order['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Управление пользователями -->
            <div id="users-section" class="admin-section">
                <h2 class="section-title">Управление пользователями</h2>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Имя</th>
                                <th>Email</th>
                                <th>Телефон</th>
                                <th>Роль</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $users = mysqli_query($connect, "SELECT id, firstname, lastname, email, phone, role FROM user ORDER BY id DESC");
                            while ($user_item = mysqli_fetch_assoc($users)): 
                            ?>
                            <tr>
                                <td><?= $user_item['id'] ?></td>
                                <td><?= htmlspecialchars($user_item['firstname'] . ' ' . $user_item['lastname']) ?></td>
                                <td><?= htmlspecialchars($user_item['email']) ?></td>
                                <td><?= htmlspecialchars($user_item['phone']) ?></td>
                                <td>
                                    <span class="status-badge" style="background: <?= $user_item['role'] == 'admin' ? '#d4edda' : '#f5f7fa' ?>; color: <?= $user_item['role'] == 'admin' ? '#155724' : '#666' ?>;">
                                        <?= $user_item['role'] ?? 'user' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user_item['id'] != $user_id): ?>
                                        <button class="btn btn-sm btn-warning" onclick="changeUserRole(<?= $user_item['id'] ?>, '<?= $user_item['role'] ?? 'user' ?>')">
                                            <i class="fas fa-user-cog"></i> Изменить
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;">(это вы)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Действие</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modal-content"></div>
        </div>
    </div>
    
    <script>
        // Переключение секций
        function showSection(section) {
            document.querySelectorAll('.admin-section').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.sidebar-menu a').forEach(el => el.classList.remove('active'));
            
            document.getElementById(section + '-section').classList.add('active');
            document.getElementById('menu-' + section).classList.add('active');
        }
        
        // Модальное окно
        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
        
        // Товары
        function showAddProductForm() {
            document.getElementById('modal-title').textContent = 'Добавить товар';
            const modalContent = `
                <form method="POST">
                    <div class="form-group">
                        <label>Название</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group">
                        <label>Цена S</label>
                        <input type="number" step="0.01" name="price_s" required>
                    </div>
                    <div class="form-group">
                        <label>Цена M</label>
                        <input type="number" step="0.01" name="price_m" required>
                    </div>
                    <div class="form-group">
                        <label>Цена L</label>
                        <input type="number" step="0.01" name="price_l" required>
                    </div>
                    <div class="form-group">
                        <label>Изображение (имя файла)</label>
                        <input type="text" name="image" placeholder="ammi.png">
                    </div>
                    <div class="form-group">
                        <label>Количество семян</label>
                        <input type="number" name="count_seeds" value="0">
                    </div>
                    <div class="form-group">
                        <label>Категория</label>
                        <input type="number" name="category_id" value="1">
                    </div>
                    <button type="submit" name="add_product" class="btn btn-primary" style="width: 100%;">Добавить</button>
                </form>
            `;
            document.getElementById('modal-content').innerHTML = modalContent;
            document.getElementById('modal').style.display = 'block';
        }
        
        function editProduct(id) {
            alert('Редактирование товара ' + id);
        }
        
        function deleteProduct(id) {
            if (confirm('Удалить товар?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id';
                input.value = id;
                form.appendChild(input);
                
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_product';
                deleteInput.value = '1';
                form.appendChild(deleteInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // FAQ
        function showAddFaqForm() {
            document.getElementById('modal-title').textContent = 'Добавить вопрос FAQ';
            const modalContent = `
                <form method="POST">
                    <div class="form-group">
                        <label>Вопрос</label>
                        <input type="text" name="question" required>
                    </div>
                    <div class="form-group">
                        <label>Ответ</label>
                        <textarea name="answer" required></textarea>
                    </div>
                    <button type="submit" name="add_faq" class="btn btn-primary" style="width: 100%;">Добавить</button>
                </form>
            `;
            document.getElementById('modal-content').innerHTML = modalContent;
            document.getElementById('modal').style.display = 'block';
        }
        
        function editFaq(questionId, answerId) {
            const row = event.target.closest('tr');
            const question = row.cells[2].textContent.replace('...', '');
            const answer = row.cells[3].textContent.replace('...', '');
            
            document.getElementById('modal-title').textContent = 'Редактировать FAQ';
            
            const modalContent = `
                <form method="POST">
                    <input type="hidden" name="question_id" value="${questionId}">
                    <input type="hidden" name="answer_id" value="${answerId}">
                    
                    <div class="form-group">
                        <label>Вопрос</label>
                        <input type="text" name="question" value="${question.replace(/&quot;/g, '"')}" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Ответ</label>
                        <textarea name="answer" rows="5" required>${answer.replace(/&quot;/g, '"')}</textarea>
                    </div>
                    
                    <button type="submit" name="update_faq" class="btn btn-primary" style="width: 100%;">Сохранить изменения</button>
                </form>
            `;
            
            document.getElementById('modal-content').innerHTML = modalContent;
            document.getElementById('modal').style.display = 'block';
        }
        
        function deleteFaq(id) {
            if (confirm('Удалить вопрос?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'question_id';
                input.value = id;
                form.appendChild(input);
                
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_faq';
                deleteInput.value = '1';
                form.appendChild(deleteInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Сообщения
        function viewMessage(id, name, email, message) {
            document.getElementById('modal-title').textContent = 'Просмотр сообщения';
            
            const modalContent = `
                <div style="padding: 10px;">
                    <p><strong>От:</strong> ${name} (${email})</p>
                    <p><strong>Сообщение:</strong></p>
                    <div style="background: #f5f7fa; padding: 15px; border-radius: 10px; margin: 10px 0;">
                        ${message.replace(/\n/g, '<br>')}
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button class="btn btn-sm btn-success" onclick="publishMessageToFaq(${id}, '${message.replace(/'/g, "\\'")}')">Опубликовать в FAQ</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteMessage(${id})">Удалить</button>
                    </div>
                </div>
            `;
            
            document.getElementById('modal-content').innerHTML = modalContent;
            document.getElementById('modal').style.display = 'block';
        }
        
        function publishMessageToFaq(messageId, question) {
            document.getElementById('modal-title').textContent = 'Опубликовать в FAQ';
            
            const modalContent = `
                <form method="POST" id="publishFaqForm">
                    <input type="hidden" name="message_id" value="${messageId}">
                    
                    <div class="form-group">
                        <label>Вопрос</label>
                        <textarea name="question" rows="3" required>${question}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Ответ</label>
                        <textarea name="answer" rows="5" required placeholder="Введите ответ на вопрос..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="keep_message" value="1" checked>
                            Сохранить сообщение в истории
                        </label>
                    </div>
                    
                    <button type="submit" name="publish_to_faq" class="btn btn-primary" style="width: 100%;">
                        Опубликовать в FAQ
                    </button>
                </form>
            `;
            
            document.getElementById('modal-content').innerHTML = modalContent;
            document.getElementById('modal').style.display = 'block';
        }
        
        function deleteMessage(id) {
            if (confirm('Удалить сообщение?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_message';
                input.value = id;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Заказы
        function updateOrderStatus(orderId, status) {
            const form = document.createElement('form');
            form.method = 'POST';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'order_id';
            idInput.value = orderId;
            form.appendChild(idInput);
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = status;
            form.appendChild(statusInput);
            
            const updateInput = document.createElement('input');
            updateInput.type = 'hidden';
            updateInput.name = 'update_order_status';
            updateInput.value = '1';
            form.appendChild(updateInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function viewOrder(id) {
            alert('Просмотр заказа ' + id);
        }
        
        function editOrder(id) {
            alert('Редактирование заказа ' + id);
        }
        
        // Пользователи
        function changeUserRole(userId, currentRole) {
            document.getElementById('modal-title').textContent = 'Изменение роли пользователя';
            
            const modalContent = `
                <form method="POST">
                    <input type="hidden" name="user_id" value="${userId}">
                    
                    <div class="form-group">
                        <label>Выберите новую роль</label>
                        <select name="role" class="role-select" required>
                            <option value="user" ${currentRole === 'user' ? 'selected' : ''}>Обычный пользователь</option>
                            <option value="admin" ${currentRole === 'admin' ? 'selected' : ''}>Администратор</option>
                        </select>
                    </div>
                    
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">
                        <i class="fas fa-info-circle"></i> 
                        Администраторы имеют доступ к управлению товарами, заказами и пользователями.
                    </p>
                    
                    <button type="submit" name="update_user_role" class="btn btn-primary" style="width: 100%;">
                        Сохранить изменения
                    </button>
                </form>
            `;
            
            document.getElementById('modal-content').innerHTML = modalContent;
            document.getElementById('modal').style.display = 'block';
        }
        
        // Закрытие модального окна при клике вне его
        window.onclick = function(event) {
            const modal = document.getElementById('modal');
            if (event.target === modal) {
                closeModal();
            }
        };
    </script>
</body>
</html>