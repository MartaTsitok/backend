<?php
session_start();
require_once('connection.php');

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    header('Location: registration/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Обработка загрузки аватара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $target_dir = "uploads/avatars/";
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
    $new_filename = "avatar_" . $user_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    $check = getimagesize($_FILES["avatar"]["tmp_name"]);
    if ($check === false) {
        $error_message = "Файл не является изображением.";
    } elseif ($_FILES["avatar"]["size"] > 5000000) {
        $error_message = "Файл слишком большой. Максимальный размер 5MB.";
    } elseif (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $error_message = "Разрешены только JPG, JPEG, PNG и GIF файлы.";
    } elseif (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
        $update_avatar = "UPDATE user SET avatar = '$target_file' WHERE id = $user_id";
        if (mysqli_query($connect, $update_avatar)) {
            $success_message = "Аватар успешно обновлен!";
        } else {
            $error_message = "Ошибка при обновлении базы данных.";
        }
    } else {
        $error_message = "Ошибка при загрузке файла.";
    }
}

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstname = mysqli_real_escape_string($connect, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($connect, $_POST['lastname']);
    $surname = mysqli_real_escape_string($connect, $_POST['surname']);
    $email = mysqli_real_escape_string($connect, $_POST['email']);
    $phone = mysqli_real_escape_string($connect, $_POST['phone']);
    
    $update_sql = "UPDATE user SET 
                   firstname = '$firstname', 
                   lastname = '$lastname', 
                   surname = '$surname', 
                   email = '$email', 
                   phone = '$phone' 
                   WHERE id = $user_id";
    
    if (mysqli_query($connect, $update_sql)) {
        $success_message = "Данные успешно обновлены!";
    } else {
        $error_message = "Ошибка обновления: " . mysqli_error($connect);
    }
}

// Получаем данные пользователя
$sql = "SELECT id, firstname, lastname, surname, email, phone, avatar, role FROM user WHERE id = $user_id";
$result = mysqli_query($connect, $sql);
$user = mysqli_fetch_assoc($result);

// Получаем историю заказов
function getOrderHistory($user_id) {
    global $connect;
    $sql = "SELECT id, total_sum, count_goods FROM orders WHERE user_id = $user_id ORDER BY id DESC";
    $result = mysqli_query($connect, $sql);
    $orders = array();
    if ($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $orders[] = $row;
        }
    }
    return $orders;
}

function getSubscriptionHistory($user_id) {
    global $connect;
    
    // Добавляем поле created_at и receipt_path
    $sql = "SELECT id, frequency, size, receipt_path, 
            DATE_FORMAT(created_at, '%d.%m.%Y') as formatted_date 
            FROM subscribe 
            WHERE user_id = $user_id 
            ORDER BY id DESC";
    
    $result = mysqli_query($connect, $sql);
    
    $subscriptions = array();
    if ($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $subscriptions[] = $row;
        }
    }
    return $subscriptions;
}

// Получаем товары из корзины
function getCartItems($user_id) {
    global $connect;
    $sql = "SELECT c.*, g.title, g.image,
            CASE 
                WHEN c.size = 's' THEN g.price_s
                WHEN c.size = 'm' THEN g.price_m
                WHEN c.size = 'l' THEN g.price_l
            END as item_price
            FROM cart c
            LEFT JOIN goods g ON c.product_id = g.id
            WHERE c.user_id = $user_id
            ORDER BY c.id DESC";
    
    $result = mysqli_query($connect, $sql);
    $items = [];
    $total = 0;
    $totalCount = 0;
    
    if ($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $row['item_total'] = $row['item_price'] * $row['quantity'];
            $total += $row['item_total'];
            $totalCount += $row['quantity'];
            $items[] = $row;
        }
    }
    
    return [
        'items' => $items,
        'total' => $total,
        'count' => $totalCount
    ];
}

$orders = getOrderHistory($user_id);
$subscriptions = getSubscriptionHistory($user_id);
$cartData = getCartItems($user_id);
$cartItems = $cartData['items'];
$cartTotal = $cartData['total'];
$cartCount = $cartData['count'];

$isAdmin = isset($user['role']) && $user['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой профиль - Анютин сад</title>
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
            margin-bottom: 40px;
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
        
        .nav a:hover {
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

        .profile-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        /* Боковая панель */
        .profile-sidebar {
            background: white;
            border-radius: 20px;
            padding: 30px 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            height: fit-content;
        }
        
        .profile-avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            cursor: pointer;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #858C88;
            transition: all 0.3s;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(133, 140, 136, 0.3);
        }
        
        .avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #858C88;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: white;
            font-weight: bold;
            margin: 0 auto;
            border: 3px solid #858C88;
        }
        
        .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(133, 140, 136, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            font-family: 'Vetrino', sans-serif;
            font-size: 14px;
        }
        
        .profile-avatar-container:hover .avatar-overlay {
            opacity: 1;
        }
        
        #avatar-upload {
            display: none;
        }
        
        .btn-upload {
            background: #858C88;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .btn-upload:hover {
            background: #6a736f;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(133, 140, 136, 0.3);
        }
        
        .profile-name {
            text-align: center;
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
            font-family: 'Vetrino', sans-serif;
        }
        
        .profile-email {
            text-align: center;
            color: #858C88;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        /* Статистика */
        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            background: #f5f7fa;
            border-radius: 15px;
            padding: 15px 10px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-item:hover {
            background: #e9eef5;
            transform: translateY(-3px);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #858C88;
            font-family: 'Vetrino', sans-serif;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Меню */
        .profile-menu {
            list-style: none;
        }
        
        .profile-menu li {
            margin-bottom: 10px;
        }
        
        .profile-menu a {
            display: block;
            padding: 15px 20px;
            background: #f5f7fa;
            border-radius: 15px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
            font-family: 'Vetrino', sans-serif;
            font-size: 16px;
        }
        
        .profile-menu a:hover {
            background: #858C88;
            color: white;
            transform: translateX(5px);
        }
        
        .profile-menu a.active {
            background: #858C88;
            color: white;
        }
        
        .profile-menu a i {
            margin-right: 10px;
            width: 20px;
        }
        
        .profile-menu a.admin-menu {
            background: #f0f0f0;
            border-left: 4px solid #858C88;
        }
        
        /* Основной контент */
        .profile-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .profile-section {
            display: none;
        }
        
        .profile-section.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        /* Форма */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #858C88;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            background: #f5f7fa;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
            color: #333;
        }
        
        .form-group input:focus {
            border-color: #858C88;
            outline: none;
            box-shadow: 0 0 0 2px rgba(133, 140, 136, 0.1);
        }
        
        .form-group input[readonly] {
            background: #f5f7fa;
            color: #666;
            cursor: not-allowed;
            border-color: #e0e0e0;
        }
        
        /* Кнопки */
        .btn {
            background: #858C88;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 30px;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #6a736f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(133, 140, 136, 0.3);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #666;
        }
        
        .btn-secondary:hover {
            background: #ccc;
            color: #333;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            margin: 0 2px;
        }
        
        .edit-toggle {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        #form-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        /* Сообщения */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Таблицы */
        .table-responsive {
            overflow-x: auto;
            background: #f5f7fa;
            border-radius: 15px;
            padding: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Montserrat', sans-serif;
        }
        
        th {
            text-align: left;
            padding: 15px;
            background: #e9eef5;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #666;
        }
        
        tfoot td {
            border-bottom: none;
            padding-top: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .cart-product {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cart-item-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        @media (max-width: 968px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav {
                flex-wrap: wrap;
                justify-content: center;
                gap: 20px;
            }
            
            .profile-stats {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .profile-stats {
                grid-template-columns: 1fr;
            }
            
            .section-title {
                font-size: 24px;
            }
            
            .profile-name {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <a href="index.php" class="logo">Анютин сад</a>
            
            <nav class="nav">
                <a href="index.php">Главная</a>
                <a href="catalog/catalog.php">Каталог</a>
                <a href="profile.php">Профиль</a>
                <a href="index.php#contacts">Контакты</a>
                <?php if ($isAdmin): ?>
                <a href="admin.php">Админ-панель</a>
                <?php endif; ?>
            </nav>
            
            <div class="user-menu">
                <span class="user-info"><i class="far fa-user-circle"></i> <?= htmlspecialchars($user['firstname']) ?></span>
                <a href="logout.php" class="logout-btn">Выйти</a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= $error_message ?></div>
        <?php endif; ?>
        
        <div class="profile-container">
            <!-- Боковая панель -->
            <div class="profile-sidebar">
                <form id="avatar-form" method="POST" enctype="multipart/form-data">
                    <div class="profile-avatar-container" onclick="document.getElementById('avatar-upload').click();">
                        <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                            <img src="<?= $user['avatar'] ?>" alt="Аватар" class="profile-avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?= strtoupper(substr($user['firstname'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="avatar-overlay">
                            Изменить
                        </div>
                    </div>
                    <input type="file" id="avatar-upload" name="avatar" accept="image/*" onchange="document.getElementById('avatar-form').submit();">
                    <button type="button" class="btn-upload" onclick="document.getElementById('avatar-upload').click();">
                        Загрузить фото
                    </button>
                </form>
                
                <div class="profile-name"><?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?></div>
                <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?= $cartCount ?></div>
                        <div class="stat-label">В корзине</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= count($orders) ?></div>
                        <div class="stat-label">Заказы</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= count($subscriptions) ?></div>
                        <div class="stat-label">Подписки</div>
                    </div>
                </div>
                
                <ul class="profile-menu">
                    <li><a href="#" onclick="showSection('profile')" class="active" id="menu-profile"><i class="fas fa-user-circle"></i> Мои данные</a></li>
                    <li><a href="#" onclick="showSection('cart')" id="menu-cart"><i class="fas fa-shopping-cart"></i> Моя корзина <?= $cartCount > 0 ? "($cartCount)" : '' ?></a></li>
                    <li><a href="#" onclick="showSection('orders')" id="menu-orders"><i class="fas fa-box"></i> История заказов</a></li>
                    <li><a href="#" onclick="showSection('subscriptions')" id="menu-subscriptions"><i class="fas fa-calendar-alt"></i> Мои подписки</a></li>
                    <li><a href="#" onclick="showSection('payments')" id="menu-payments"><i class="fas fa-credit-card"></i> Способы оплаты</a></li>
                </ul>
            </div>
            
            <!-- Основной контент -->
            <div class="profile-content">
                <!-- Секция профиля -->
                <div id="profile-section" class="profile-section active">
                    <h2 class="section-title">Мои данные</h2>
                    
                    <div class="edit-toggle">
                        <button class="btn btn-secondary" onclick="toggleEdit()" id="edit-btn">
                            Редактировать
                        </button>
                    </div>
                    
                    <form method="POST" id="profile-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Фамилия</label>
                                <input type="text" name="lastname" id="lastname" 
                                       value="<?= htmlspecialchars($user['lastname']) ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label>Имя</label>
                                <input type="text" name="firstname" id="firstname" 
                                       value="<?= htmlspecialchars($user['firstname']) ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label>Отчество</label>
                                <input type="text" name="surname" id="surname" 
                                       value="<?= htmlspecialchars($user['surname']) ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" id="email" 
                                       value="<?= htmlspecialchars($user['email']) ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label>Телефон</label>
                                <input type="tel" name="phone" id="phone" 
                                       value="<?= htmlspecialchars($user['phone']) ?>" readonly>
                            </div>
                        </div>
                        
                        <div id="form-actions">
                            <button type="submit" name="update_profile" class="btn">
                                Сохранить изменения
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelEdit()">
                                Отмена
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Секция корзины -->
                <div id="cart-section" class="profile-section">
                    <h2 class="section-title">Моя корзина</h2>
                    
                    <?php if (empty($cartItems)): ?>
                        <div style="text-align: center; padding: 60px 20px;">
                            <i class="fas fa-shopping-cart" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                            <p style="font-size: 16px; color: #666; margin-bottom: 20px;">Ваша корзина пуста</p>
                            <a href="catalog/catalog.php" class="btn">Перейти в каталог</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Товар</th>
                                        <th>Размер</th>
                                        <th>Количество</th>
                                        <th>Цена</th>
                                        <th>Сумма</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cartItems as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="cart-product">
                                                    <?php if (!empty($item['image'])): ?>
                                                        <img src="../img/<?= htmlspecialchars($item['image']) ?>" 
                                                             alt="<?= htmlspecialchars($item['title']) ?>" 
                                                             class="cart-item-image">
                                                    <?php endif; ?>
                                                    <span><?= htmlspecialchars($item['title'] ?? 'Товар') ?></span>
                                                </div>
                                            </td>
                                            <td><?= strtoupper(htmlspecialchars($item['size'])) ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td><?= number_format($item['item_price'], 2) ?> BYN</td>
                                            <td><strong><?= number_format($item['item_total'], 2) ?> BYN</strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" style="text-align: right;">Итого:</td>
                                        <td style="font-weight: bold; color: #858C88;"><?= number_format($cartTotal, 2) ?> BYN</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                            <a href="catalog/catalog.php" class="btn btn-secondary">Продолжить покупки</a>
                            <a href="catalog/cart.php" class="btn">Перейти в корзину</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Секция заказов -->
                <div id="orders-section" class="profile-section">
                    <h2 class="section-title">История заказов</h2>
                    
                    <?php if (empty($orders)): ?>
                        <div style="text-align: center; padding: 60px 20px;">
                            <i class="fas fa-box-open" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                            <p style="font-size: 16px; color: #666;">У вас пока нет заказов</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>№ заказа</th>
                                        <th>Сумма</th>
                                        <th>Количество товаров</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?= $order['id'] ?></td>
                                            <td><?= number_format($order['total_sum'], 2) ?> BYN</td>
                                            <td><?= $order['count_goods'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Секция подписок -->
                <div id="subscriptions-section" class="profile-section">
                    <h2 class="section-title">Мои подписки</h2>
                    
                    <?php if (empty($subscriptions)): ?>
                        <div style="text-align: center; padding: 60px 20px;">
                            <i class="fas fa-calendar-times" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                            <p style="font-size: 16px; color: #666;">У вас нет активных подписок</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Дата</th>
                                        <th>Частота</th>
                                        <th>Размер букета</th>
                                        <th>Чек</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscriptions as $sub): ?>
                                        <tr>
                                            <td>#<?= $sub['id'] ?></td>
                                            <td><?= htmlspecialchars($sub['formatted_date'] ?? date('d.m.Y')) ?></td>
                                            <td><?= htmlspecialchars($sub['frequency']) ?></td>
                                            <td><?= strtoupper(htmlspecialchars($sub['size'])) ?></td>
                                            <td>
                                                <?php if (!empty($sub['receipt_path']) && file_exists($sub['receipt_path'])): ?>
                                                    <a href="<?= $sub['receipt_path'] ?>" class="btn btn-sm" download>
                                                        <i class="fas fa-download"></i> Скачать
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: #999; font-size: 12px;">Нет чека</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Секция оплаты -->
                <div id="payments-section" class="profile-section">
                    <h2 class="section-title">Способы оплаты</h2>
                    <div style="text-align: center; padding: 60px 20px;">
                        <i class="fas fa-tools" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                        <p style="font-size: 16px; color: #666;">Раздел в разработке</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Переключение между секциями
        function showSection(section) {
            document.getElementById('profile-section').classList.remove('active');
            document.getElementById('cart-section').classList.remove('active');
            document.getElementById('orders-section').classList.remove('active');
            document.getElementById('subscriptions-section').classList.remove('active');
            document.getElementById('payments-section').classList.remove('active');
            
            document.getElementById('menu-profile').classList.remove('active');
            document.getElementById('menu-cart').classList.remove('active');
            document.getElementById('menu-orders').classList.remove('active');
            document.getElementById('menu-subscriptions').classList.remove('active');
            document.getElementById('menu-payments').classList.remove('active');
            
            document.getElementById(section + '-section').classList.add('active');
            document.getElementById('menu-' + section).classList.add('active');
            
            if (section !== 'profile') {
                resetEditMode();
            }
        }
        
        // Режим редактирования
        function toggleEdit() {
            const inputs = document.querySelectorAll('#profile-form input');
            const formActions = document.getElementById('form-actions');
            const editBtn = document.getElementById('edit-btn');
            
            inputs.forEach(input => input.removeAttribute('readonly'));
            formActions.style.display = 'flex';
            editBtn.style.display = 'none';
        }
        
        function cancelEdit() {
            resetEditMode();
            location.reload();
        }
        
        function resetEditMode() {
            const inputs = document.querySelectorAll('#profile-form input');
            const formActions = document.getElementById('form-actions');
            const editBtn = document.getElementById('edit-btn');
            
            inputs.forEach(input => input.setAttribute('readonly', true));
            formActions.style.display = 'none';
            editBtn.style.display = 'block';
        }
    </script>
</body>
</html>