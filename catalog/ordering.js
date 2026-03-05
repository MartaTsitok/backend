// ordering.js - ЧИСТЫЙ JAVASCRIPT

// ============================================
// ФУНКЦИИ ДЛЯ ОФОРМЛЕНИЯ ЗАКАЗА (МОДАЛЬНОЕ ОКНО)
// ============================================

// Функция открытия модального окна с данными товара
function openOrderModal(button) {
    console.log('openOrderModal вызвана', button);
    
    // Получаем данные товара из data-атрибутов кнопки
    const productId = button.getAttribute('data-product-id');
    const productName = button.getAttribute('data-product-name');
    const productPrice = button.getAttribute('data-product-price');
    const productSize = button.getAttribute('data-product-size');
    
    console.log('Товар:', {productId, productName, productPrice, productSize});
    
    // Сохраняем товар в переменную для оформления
    window.currentOrderProduct = {
        id: parseInt(productId),
        name: productName,
        price: parseFloat(productPrice),
        size: productSize || 's',
        quantity: 1
    };
    
    // Отображаем товар в модальном окне
    displayOrderSummary();
    
    // Открываем модальное окно
    const modal = document.getElementById('orderModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

// Функция отображения информации о товаре
function displayOrderSummary() {
    const orderSummary = document.getElementById('orderSummary');
    if (!orderSummary || !window.currentOrderProduct) return;
    
    const product = window.currentOrderProduct;
    const total = product.price * product.quantity;
    
    let summaryHtml = `
        <div class="cart-item">
            <p><strong>${product.name}</strong></p>
            <p>Размер: ${product.size.toUpperCase()}</p>
            <p>Количество: ${product.quantity}</p>
            <p>Цена: ${product.price} BYN</p>
            <p style="font-size: 18px; margin-top: 10px;"><strong>Итого: ${total} BYN</strong></p>
        </div>
    `;
    
    orderSummary.innerHTML = summaryHtml;
}

// Функция закрытия модального окна
function closeModal() {
    const modal = document.getElementById('orderModal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Очищаем поле адреса
    const addressField = document.getElementById('delivery_address');
    if (addressField) {
        addressField.value = '';
    }
    
    // Очищаем данные товара
    window.currentOrderProduct = null;
}

// Функция отправки заказа
function submitOrder() {
    // Получаем только адрес доставки
    const deliveryAddress = document.getElementById('delivery_address')?.value;
    
    // Валидация - проверяем только адрес
    if (!deliveryAddress || deliveryAddress.trim() === '') {
        alert('Пожалуйста, введите адрес доставки');
        return;
    }
    
    // Получаем товар из заказа
    const product = window.currentOrderProduct;
    
    if (!product) {
        alert('Ошибка: товар не выбран');
        return;
    }
    
    // Формируем данные для отправки
    const orderData = {
        delivery_address: deliveryAddress,
        items: [product],
        total: product.price * product.quantity
    };
    
    console.log('Отправляем заказ:', orderData);
    
    // Показываем индикатор загрузки
    const submitBtn = document.querySelector('.modal-buttons button:first-child');
    const originalText = submitBtn ? submitBtn.textContent : '';
    if (submitBtn) {
        submitBtn.textContent = 'Отправка...';
        submitBtn.disabled = true;
    }
    
    // Отправляем на сервер
    fetch('process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(orderData)
    })
    .then(async response => {
        const text = await response.text();
        console.log('Сырой ответ сервера:', text);
        
        try {
            const data = JSON.parse(text);
            return data;
        } catch (e) {
            throw new Error('Сервер вернул не JSON: ' + text.substring(0, 200));
        }
    })
    .then(data => {
        if (data.success) {
            alert(`Заказ №${data.order_id} успешно оформлен! Подтверждение отправлено на вашу почту.`);
            closeModal();
        } else {
            alert('Ошибка при оформлении заказа: ' + (data.error || 'Неизвестная ошибка'));
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        alert('Произошла ошибка при отправке заказа. Проверьте консоль (F12) для деталей.');
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    });
}

// ============================================
// ФУНКЦИИ ДЛЯ КОРЗИНЫ (БЕЗ AJAX)
// ============================================

// Функция добавления товара в корзину
function addToCart(productId, size) {
    console.log('Добавляем в корзину:', {productId, size});
    
    // Создаем форму для отправки POST запроса
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'cart.php';
    form.style.display = 'none';
    
    // Добавляем все необходимые поля
    const fields = {
        'add_to_cart': '1',
        'product_id': productId,
        'size': size,
        'quantity': '1'
    };
    
    for (const [name, value] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }
    
    // Добавляем форму на страницу и отправляем
    document.body.appendChild(form);
    form.submit();
}

// ============================================
// ФУНКЦИЯ ДЛЯ КОРЗИНЫ (ЗАГЛУШКА ДЛЯ СТАРОЙ ФУНКЦИИ)
// ============================================

function addCart(id) {
    console.log('Используется устаревшая функция addCart. ID:', id);
    // Перенаправляем на новую функцию с размером по умолчанию
    addToCart(id, 's');
}

// ============================================
// ОБЩИЕ ФУНКЦИИ
// ============================================

// Закрытие модального окна при клике вне его
window.onclick = function(event) {
    const modal = document.getElementById('orderModal');
    if (event.target === modal) {
        closeModal();
    }
};

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    console.log('Скрипт оформления заказа загружен');
    
    // Добавляем обработчик на клавишу Enter в поле адреса
    const addressField = document.getElementById('delivery_address');
    if (addressField) {
        addressField.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                submitOrder();
            }
        });
    }
});