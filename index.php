<?php
session_start();
require_once('connection.php');
require_once('telegram.php');

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
        $frequency = mysqli_real_escape_string($connect, $data['frequency']);
        
        $size = '';
        switch($data['quantity']) {
            case '15-20':
                $size = 's';
                break;
            case '20-40':
                $size = 'm';
                break;
            case '40-70':
                $size = 'l';
                break;
            default:
                $size = 'm';
        }
        
        $receipt_filename = 'receipt_' . $user_id . '_' . time() . '.doc';
        $receipt_path = 'uploads/receipts/' . $receipt_filename;
        if (!file_exists('uploads/receipts/')) {
            mkdir('uploads/receipts/', 0777, true);
        }
        
        $userQuery = "SELECT firstname, lastname, email, phone FROM user WHERE id = '$user_id'";
        $userResult = mysqli_query($connect, $userQuery);
        $user = mysqli_fetch_assoc($userResult);
        
        $sql = "INSERT INTO subscribe (user_id, frequency, size, receipt_path, created_at) 
                VALUES ('$user_id', '$frequency', '$size', '$receipt_path', NOW())";
        
        if (mysqli_query($connect, $sql)) {
            $subscription_id = mysqli_insert_id($connect);
            
            $telegram = new TelegramBot('8643566646:AAE83RsIEQat0opLOR3_qZIG4xk2mzd9Gdo', '1414721913');
            
            $fullName = $user['firstname'] . ' ' . $user['lastname'];
            
            $telegram_message = "<b>🆕 НОВАЯ ПОДПИСКА #{$subscription_id}</b>\n\n";
            $telegram_message .= "<b>👤 Клиент:</b> {$fullName}\n";
            $telegram_message .= "<b>📞 Телефон:</b> {$user['phone']}\n";
            $telegram_message .= "<b>📧 Email:</b> {$user['email']}\n\n";
            $telegram_message .= "<b>📦 Частота:</b> {$frequency}\n";
            $telegram_message .= "<b>🌸 Количество:</b> {$data['quantity']} цветов\n";
            $telegram_message .= "<b>📍 Адрес:</b> {$data['address']}\n";
            
            if (!empty($data['notes'])) {
                $telegram_message .= "<b>📝 Пожелания:</b> {$data['notes']}\n";
            }
            
            $telegram_message .= "\n<i>" . date('d.m.Y H:i') . "</i>";
            
            $telegram->sendMessage($telegram_message);
            
            echo json_encode([
                'success' => true, 
                'subscription_id' => $subscription_id,
                'receipt_path' => $receipt_path,
                'user' => [
                    'name' => $user['firstname'] . ' ' . $user['lastname'],
                    'email' => $user['email'],
                    'phone' => $user['phone']
                ]
            ]);
        } else {
            throw new Exception('Ошибка сохранения: ' . mysqli_error($connect));
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

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

function getFaqQuestions() {
    global $connect;
    $sql = "SELECT q.id, q.question, a.answer 
            FROM questions q 
            LEFT JOIN answers a ON q.id = a.question_id 
            ORDER BY q.id ASC";
    
    $result = mysqli_query($connect, $sql);
    
    $faq = array();
    if ($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $faq[] = $row;
        }
    }
    return $faq;
}

$faqItems = getFaqQuestions();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анютин сад - Цветочная мастерская</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vetrino&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="nav-left">
                <a href="#about">О нас</a>
                <a href="#subscription">Цветочная подписка</a>
            </div>
            
            <div class="logo">
                <a href="index.php">
                    <img src="img/logo.png" alt="Анютин сад">
                </a>
            </div>
            
            <div class="nav-right">
                <a href="#faq">FAQ</a>
                <a href="#contacts">Контакты</a>
                <a href="catalog/catalog.php">Каталог</a>
                <div class="profile-icon">
                    <?php if ($currentUser): ?>
                        <a href="profile.php" class="profile-link" title="<?= htmlspecialchars($currentUser['name']) ?>">
                            <?php if (!empty($currentUser['avatar']) && file_exists($currentUser['avatar'])): ?>
                                <img src="<?= $currentUser['avatar'] ?>" alt="Профиль" class="profile-img">
                            <?php else: ?>
                                <img src="img/user-logged.png" alt="Профиль" class="profile-img">
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <a href="registration/login.php" class="profile-link">
                            <img src="img/user.png" alt="Войти" class="profile-img">
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <section id="about" class="about-section">
        <div class="container">
            <h2 class="about-title">О нас</h2>
            <div class="about-content">
                <div class="about-text">
                    <p>«Анютин сад» приветствует вас в мире живой красоты и нежных ароматов. На этом сайте вы можете не спеша рассмотреть наш обширный каталог.</p>
                    <p>Выберите отдельные свежие цветы для создания собственной уникальной композиции. Или вдохновитесь готовыми букетами, которые мы собрали с любовью и вкусом. Мы срезаем цветы каждый день, поэтому гарантируем свежесть и качество каждого бутона.</p>
                    <p>Каждая фотография на сайте передает реальную красоту наших растений. Мы бережно доставляем ваши заказы по всему Минску, чтобы свежесть и красота букета сохранились до момента вручения.</p>
                    <p>Если вы ищете что-то особенное, позвоните или напишите нам. Мы поможем подобрать символ любви, благодарности или заботы и расскажем вашу идеальную цветочную историю.</p>
                </div>
            </div>
        </div>
    </section>
    
    <section id="subscription" class="subscription-section">
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

    <section id="faq" class="faq-section">
        <div class="container faq-container">
            <div class="faq-wrapper">
                <h2 class="faq-title">FAQ</h2>
                <div class="faq-grid">
                    <?php if (empty($faqItems)): ?>
                        <p style="text-align: center;">Вопросы скоро появятся</p>
                    <?php else: ?>
                        <?php foreach ($faqItems as $index => $faq): ?>
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFaq(this, <?= $index ?>)">
                                    <?= htmlspecialchars($faq['question']) ?>
                                    <span class="faq-icon">+</span>
                                </div>
                                <div class="faq-answer" id="faq-<?= $index ?>">
                                    <?= nl2br(htmlspecialchars($faq['answer'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="contact-wrapper">
                <div class="contact-title-group">
                    <span class="contact-title-large">ОСТАЛИСЬ</span>
                    <span class="contact-title-large">ВОПРОСЫ</span>
                    <span class="contact-title-small">МЫ ОТВЕТИМ</span>
                </div>
                
                <form id="contactForm" class="contact-form">
                    <div class="form-group">
                        <input type="text" id="contact-name" placeholder="Ваше имя" required>
                    </div>
                    
                    <div class="form-group">
                        <input type="email" id="contact-email" placeholder="Ваша почта" required>
                    </div>
                    
                    <div class="form-group">
                        <textarea id="contact-message" placeholder="Ваш вопрос" rows="5" required></textarea>
                    </div>
                    
                    <button type="submit" class="contact-submit">Отправить</button>
                </form>
            </div>
        </div>
    </section>
    
    <footer id="contacts">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3 class="footer-title">Навигация</h3>
                    <ul class="footer-links">
                        <li><a href="#about">О нас</a></li>
                        <li><a href="#subscription">Цветочная подписка</a></li>
                        <li><a href="#faq">FAQ</a></li>
                        <li><a href="#contacts">Контакты</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3 class="footer-title">Каталог</h3>
                    <ul class="footer-links">
                        <li><a href="catalog/catalog.php?category=1">Цветы поштучно</a></li>
                        <li><a href="catalog/catalog.php?category=2">Авторские букеты</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3 class="footer-title">Контакты</h3>
                    <ul class="footer-links">
                        <li><i class="fas fa-phone"></i> +375 29 650 5145</li>
                        <li><i class="fas fa-envelope"></i> info@annsgarden.by</li>
                    </ul>
                    <div class="footer-social">
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-telegram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-vk"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3 class="footer-title">Время работы</h3>
                    <ul class="footer-links">
                        <li><span class="day">Пн-Пт:</span> <span class="time">9:00 - 21:00</span></li>
                        <li><span class="day">Сб - Вс:</span> <span class="time">10:00 - 18:00</span></li>
                    </ul>
                    <p class="footer-note">Доставка цветов по Минску ежедневно</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="copyright">
                    &copy; 2026 Анютин сад. Все права защищены.
                </div>
                <div class="footer-logo">
                    <img src="img/logo-white.png" alt="Анютин сад" height="40">
                </div>
            </div>
        </div>
    </footer>
    
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Оплата подписки</h2>
                <span class="close" onclick="closePaymentModal()">&times;</span>
            </div>
            
            <div class="modal-body">
                <div class="payment-info">
                    <p class="info-text">В разработке</p>
                    <p class="info-subtext">Здесь будет форма для ввода платежных данных</p>
                    <p class="info-note">Сейчас оплата не требуется</p>
                </div>
                
                <div class="subscription-summary" id="modalSummary">
                </div>
                
                <button class="modal-btn" onclick="completeSubscription()">
                    Завершить оформление
                </button>
            </div>
        </div>
    </div>
    
    <script>
    window.isUserLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
    window.currentUser = {
        name: '<?= $currentUser ? addslashes($currentUser['name']) : '' ?>',
        email: '<?= $currentUser ? addslashes($currentUser['email']) : '' ?>',
        phone: '<?= $currentUser ? addslashes($currentUser['phone']) : '' ?>'
    };
    </script>
    
    <script>
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
                window.location.href = 'registration/login.php?redirect=' + encodeURIComponent(window.location.href);
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

    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
    }
    
    function completeSubscription() {
        const sub = window.subscriptionData;
        
        if (!sub) {
            alert('Ошибка: данные подписки не найдены');
            return;
        }
        
        if (!window.isUserLoggedIn) {
            alert('Необходимо авторизоваться');
            window.location.href = 'registration/login.php';
            return;
        }
        
        const btn = document.querySelector('.modal-btn');
        const originalText = btn.textContent;
        btn.innerHTML = '<span class="loading"></span> Сохранение...';
        btn.disabled = true;
        
        fetch('index.php', {
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
                createSubscriptionWordDocument(data, sub);
                closePaymentModal();
                
                document.getElementById('subscriptionForm').reset();
                document.getElementById('priceDisplay').style.display = 'none';
                
                alert(`Подписка №${data.subscription_id} успешно оформлена! Чек сохранен в формате Word.`);
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

    function createSubscriptionWordDocument(data, sub) {
        const userName = data.user?.name || window.currentUser?.name || '';
        const userEmail = data.user?.email || window.currentUser?.email || '';
        const userPhone = data.user?.phone || window.currentUser?.phone || '';
        
        const content = `
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Подтверждение подписки - Анютин сад</title>
                <style>
                    body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
                    h1 { color: #858C88; text-align: center; }
                    h2 { color: #667eea; border-bottom: 2px solid #858C88; padding-bottom: 10px; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .info { background: #f5f7fa; padding: 20px; border-radius: 10px; margin: 20px 0; }
                    .info p { margin: 10px 0; font-size: 16px; }
                    .label { font-weight: bold; color: #858C88; }
                    .footer { margin-top: 50px; text-align: center; color: #666; border-top: 1px solid #ddd; padding-top: 20px; }
                    .receipt-id { color: #858C88; font-size: 14px; text-align: right; margin-top: 10px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>🌸 Анютин сад</h1>
                    <h2>Подтверждение цветочной подписки</h2>
                </div>
                
                <div class="info">
                    <p><span class="label">Номер подписки:</span> #SUB-${data.subscription_id}</p>
                    <p><span class="label">Дата оформления:</span> ${sub.date}</p>
                    <p><span class="label">Клиент:</span> ${userName}</p>
                    <p><span class="label">Email:</span> ${userEmail}</p>
                    <p><span class="label">Телефон:</span> ${userPhone}</p>
                </div>
                
                <div class="info">
                    <h3>Детали подписки</h3>
                    <p><span class="label">Частота доставки:</span> ${sub.frequency}</p>
                    <p><span class="label">Количество цветов:</span> ${sub.quantity}</p>
                    <p><span class="label">Адрес доставки:</span> ${sub.address}</p>
                    ${sub.notes ? `<p><span class="label">Пожелания:</span> ${sub.notes}</p>` : ''}
                    <p><span class="label">Стоимость:</span> <strong>${sub.price}</strong></p>
                </div>
                
                <div class="info">
                    <h3>Информация об оплате</h3>
                    <p>Подписка активирована</p>
                    <p>Первый платеж будет списан автоматически</p>
                </div>
                
                <div class="footer">
                    <p>Спасибо, что выбрали Анютин сад!</p>
                    <p>По всем вопросам: +375 29 650 5145</p>
                    <p>© ${new Date().getFullYear()} Анютин сад. Все права защищены.</p>
                </div>
                
                <div class="receipt-id">
                    Чек №${data.subscription_id} от ${new Date().toLocaleDateString('ru-RU')}
                </div>
            </body>
            </html>
        `;
        
        const blob = new Blob([content], { type: 'application/msword' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `Ann's_Garden_Подписка_${data.subscription_id}_${new Date().toISOString().slice(0,10)}.doc`;
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function toggleFaq(element, index) {
        const answer = document.getElementById(`faq-${index}`);
        
        if (!answer) return;
        
        const faqItem = element.closest('.faq-item');
        
        const allFaqItems = document.querySelectorAll('.faq-item');
        allFaqItems.forEach((item) => {
            if (item !== faqItem) {
                const otherAnswer = item.querySelector('.faq-answer');
                const otherQuestion = item.querySelector('.faq-question');
                if (otherAnswer && otherAnswer.classList.contains('show')) {
                    otherAnswer.classList.remove('show');
                    if (otherQuestion) otherQuestion.classList.remove('active');
                }
            }
        });
        
        answer.classList.toggle('show');
        element.classList.toggle('active');
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
            const modal = document.getElementById('paymentModal');
            if (event.target === modal) {
                closePaymentModal();
            }
        };
        
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const name = document.getElementById('contact-name').value.trim();
                const email = document.getElementById('contact-email').value.trim();
                const message = document.getElementById('contact-message').value.trim();
                
                if (!name) {
                    alert('Пожалуйста, введите ваше имя');
                    return;
                }
                
                if (!email) {
                    alert('Пожалуйста, введите ваш email');
                    return;
                }
                
                if (!isValidEmail(email)) {
                    alert('Пожалуйста, введите корректный email');
                    return;
                }
                
                if (!message) {
                    alert('Пожалуйста, введите ваш вопрос');
                    return;
                }
                
                const submitBtn = document.querySelector('.contact-submit');
                const originalText = submitBtn.textContent;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';
                submitBtn.disabled = true;
                
                const telegram = new TelegramBot('8643566646:AAE83RsIEQat0opLOR3_qZIG4xk2mzd9Gdo', '1414721913');
                
                const telegram_message = "<b>❓ НОВЫЙ ВОПРОС С САЙТА</b>\n\n";
                telegram_message += "<b>Имя:</b> " + name + "\n";
                telegram_message += "<b>Email:</b> " + email + "\n";
                telegram_message += "<b>Вопрос:</b>\n" + message + "\n\n";
                telegram_message += "<i>" + new Date().toLocaleString('ru-RU') + "</i>";
                
                fetch('telegram_send.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: telegram_message
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Ваш вопрос успешно отправлен!');
                        contactForm.reset();
                    } else {
                        alert('Ошибка при отправке вопроса');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Произошла ошибка при отправке');
                })
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        }
    });

    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    </script>
</body>
</html>