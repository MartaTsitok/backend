<?php
session_start();
require_once('../connection.php');

// Если уже авторизован - перенаправляем на каталог
if (isset($_SESSION['user_id'])) {
    header('Location: ../catalog/catalog.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($connect, $_POST['email']);
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM user WHERE email = '$email'";
    $result = mysqli_query($connect, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['firstname'] . ' ' . $user['lastname'];
            
            $redirect = $_GET['redirect'] ?? '../catalog/catalog.php';
            header("Location: $redirect");
            exit;
        } else {
            $error = 'Неверный пароль';
        }
    } else {
        $error = 'Пользователь не найден';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Анютин сад</title>
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
            min-height: 100vh;
            background: url('../img/login-bg.jpg') center/cover no-repeat fixed;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            pointer-events: none;
        }
        
        .login-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            font-family: 'Vetrino', sans-serif;
            font-size: 36px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 500;
            letter-spacing: 1px;
        }
        
        .login-header p {
            color: #858C88;
            font-size: 15px;
        }
        
        .login-form .form-group {
            margin-bottom: 20px;
        }
        
        .login-form label {
            display: block;
            margin-bottom: 8px;
            font-family: 'Vetrino', sans-serif;
            font-size: 15px;
            color: #333;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .login-form input {
            width: 100%;
            padding: 14px 18px;
            background: #f5f7fa;
            border: 1px solid #e0e0e0;
            border-radius: 15px;
            font-family: 'Montserrat', sans-serif;
            font-size: 15px;
            color: #333;
            transition: all 0.3s;
        }
        
        .login-form input:focus {
            border-color: #858C88;
            outline: none;
            box-shadow: 0 0 0 3px rgba(133, 140, 136, 0.1);
            background: white;
        }
        
        .login-form input::placeholder {
            color: #999;
            font-size: 14px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message i {
            font-size: 18px;
        }
        
        .login-btn {
            background: #858C88;
            color: white;
            padding: 16px 30px;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
            margin-top: 10px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .login-btn:hover {
            background: #6a736f;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(133, 140, 136, 0.3);
        }
        
        .login-btn i {
            font-size: 18px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #666;
        }
        
        .login-footer a {
            color: #858C88;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
            margin-left: 5px;
        }
        
        .login-footer a:hover {
            color: #333;
            text-decoration: underline;
        }
        
        .back-to-site {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-site a {
            color: #858C88;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-to-site a:hover {
            color: #333;
        }
        
        /* Адаптивность */
        @media (max-width: 768px) {
            .login-card {
                padding: 30px;
            }
            
            .login-header h2 {
                font-size: 30px;
            }
            
            .login-form input {
                padding: 12px 15px;
            }
            
            .login-btn {
                padding: 14px 25px;
            }
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 25px;
            }
            
            .login-header h2 {
                font-size: 26px;
            }
            
            .login-header p {
                font-size: 14px;
            }
            
            .login-form label {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2>Вход</h2>
                <p>Добро пожаловать в Анютин сад</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label><i class="fas fa-envelope" style="margin-right: 5px; color: #858C88;"></i> Email</label>
                    <input type="email" name="email" placeholder="ivan@example.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock" style="margin-right: 5px; color: #858C88;"></i> Пароль</label>
                    <input type="password" name="password" placeholder="Введите пароль" required>
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Войти
                </button>
            </form>
            
            <div class="login-footer">
                Нет аккаунта?
                <a href="reg.php">Зарегистрироваться</a>
            </div>
            
            <div class="back-to-site">
                <a href="../index.php">
                    <i class="fas fa-arrow-left"></i>
                    Вернуться на сайт
                </a>
            </div>
        </div>
    </div>
</body>
</html>