<?php
session_start();
require_once('../connection.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fio = trim($_POST['fio']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $pass = $_POST['password'];
    $repeat = $_POST['repeat'];

    if(empty($fio) || empty($email) || empty($phone) || empty($pass) || empty($repeat)){
        $error = "Заполните все поля";
    } elseif($repeat != $pass){
        $error = "Пароли не совпадают!";
    } elseif(strlen($pass) < 6){
        $error = "Пароль должен быть не менее 6 символов";
    } else {
        $fio = mysqli_real_escape_string($connect, $fio);
        $email = mysqli_real_escape_string($connect, $email);
        $phone = mysqli_real_escape_string($connect, $phone);
        
        $checkQuery = "SELECT * FROM user WHERE email = '$email'";
        $checkResult = mysqli_query($connect, $checkQuery);
        
        if(!$checkResult) {
            $error = "Ошибка запроса: " . mysqli_error($connect);
        } elseif(mysqli_num_rows($checkResult) > 0){
            $error = "Пользователь с таким email уже существует";
        } else {
            $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);
            
            $queryReg = "INSERT INTO user (firstname, lastname, surname, email, phone, password) VALUES ('$fio', '', '', '$email', '$phone', '$hashedPassword')";

            if($connect->query($queryReg) === TRUE){
                $user_id = $connect->insert_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $fio;
                $_SESSION['user_email'] = $email;
                
                header('Location: ../catalog/catalog.php');
                exit;
            } else {
                $error = "Ошибка регистрации: " . $connect->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Анютин сад</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vetrino&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            background: url('../img/reg-bg.jpg') center/cover no-repeat fixed;
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
        
        .reg-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 500px;
            padding: 20px;
        }
        
        .reg-card {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        .reg-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reg-header h2 {
            font-family: 'Vetrino', sans-serif;
            font-size: 36px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .reg-header p {
            font-family: 'Montserrat', sans-serif;
            color: #666;
            font-size: 15px;
        }
        
        .reg-form .form-group {
            margin-bottom: 20px;
        }
        
        .reg-form label {
            display: block;
            margin-bottom: 8px;
            font-family: 'Vetrino', sans-serif;
            font-size: 15px;
            color: #333;
            font-weight: 500;
        }
        
        .reg-form input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 15px;
            font-family: 'Montserrat', sans-serif;
            font-size: 15px;
            color: #333;
            transition: all 0.3s;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }
        
        .reg-form input:focus {
            border-color: #858C88;
            outline: none;
            background: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 3px rgba(133, 140, 136, 0.1);
        }
        
        .reg-form input::placeholder {
            color: rgba(51, 51, 51, 0.5);
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
        }
        
        .error-message {
            background: rgba(220, 53, 69, 0.2);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            color: #721c24;
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            border: 1px solid rgba(220, 53, 69, 0.3);
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            text-align: center;
        }
        
        .reg-btn {
            background: rgba(133, 140, 136, 0.8);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
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
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-top: 10px;
            letter-spacing: 0.5px;
        }
        
        .reg-btn:hover {
            background: rgba(133, 140, 136, 1);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(133, 140, 136, 0.3);
        }
        
        .reg-footer {
            text-align: center;
            margin-top: 25px;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            color: #666;
        }
        
        .reg-footer a {
            color: #858C88;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .reg-footer a:hover {
            color: #333;
            text-decoration: underline;
        }
        
        /* Адаптивность */
        @media (max-width: 768px) {
            .reg-card {
                padding: 30px;
            }
            
            .reg-header h2 {
                font-size: 30px;
            }
            
            .reg-form input {
                padding: 12px 15px;
            }
            
            .reg-btn {
                padding: 14px 25px;
            }
        }
        
        @media (max-width: 480px) {
            .reg-card {
                padding: 25px;
            }
            
            .reg-header h2 {
                font-size: 26px;
            }
            
            .reg-header p {
                font-size: 14px;
            }
            
            .reg-form label {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="reg-container">
        <div class="reg-card">
            <div class="reg-header">
                <h2>Регистрация</h2>
                <p>Присоединяйтесь к Анютин сад</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="reg-form">
                <div class="form-group">
                    <label>ФИО</label>
                    <input type="text" name="fio" placeholder="Иванов Иван Иванович" value="<?= isset($_POST['fio']) ? htmlspecialchars($_POST['fio']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="ivan@example.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Телефон</label>
                    <input type="tel" name="phone" placeholder="+375 29 123-45-67" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="password" placeholder="Минимум 6 символов" required>
                </div>
                
                <div class="form-group">
                    <label>Повторите пароль</label>
                    <input type="password" name="repeat" placeholder="Введите пароль еще раз" required>
                </div>
                
                <button type="submit" class="reg-btn">Зарегистрироваться</button>
            </form>
            
            <div class="reg-footer">
                Уже есть аккаунт? <a href="login.php">Войти</a>
            </div>
        </div>
    </div>
</body>
</html>