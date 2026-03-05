<?php
// Функции для работы с корзиной
// Получить корзину пользователя
function getUserCart($user_id) {
    global $connect;
    
    $sql = "SELECT c.*, g.title, g.image, 
            CASE 
                WHEN c.size = 's' THEN g.price_s
                WHEN c.size = 'm' THEN g.price_m
                WHEN c.size = 'l' THEN g.price_l
            END as price
            FROM cart c
            JOIN goods g ON c.product_id = g.id
            WHERE c.user_id = $user_id
            ORDER BY c.id DESC";
    
    $result = mysqli_query($connect, $sql);
    
    $items = [];
    $total = 0;
    $totalCount = 0;
    
    if ($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $row['item_total'] = $row['price'] * $row['quantity'];
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

// Добавить товар в корзину
function addToCart($user_id, $product_id, $size, $quantity = 1) {
    global $connect;
    
    // Проверяем, есть ли уже такой товар в корзине
    $checkQuery = "SELECT * FROM cart WHERE user_id = $user_id AND product_id = $product_id AND size = '$size'";
    $checkResult = mysqli_query($connect, $checkQuery);
    
    if (mysqli_num_rows($checkResult) > 0) {
        // Если есть - обновляем количество
        $updateQuery = "UPDATE cart SET quantity = quantity + $quantity WHERE user_id = $user_id AND product_id = $product_id AND size = '$size'";
        return mysqli_query($connect, $updateQuery);
    } else {
        // Если нет - добавляем новый
        $insertQuery = "INSERT INTO cart (user_id, product_id, quantity, size) VALUES ($user_id, $product_id, $quantity, '$size')";
        return mysqli_query($connect, $insertQuery);
    }
}

// Обновить количество товара
function updateCartItem($cart_id, $user_id, $quantity) {
    global $connect;
    
    if ($quantity > 0) {
        $sql = "UPDATE cart SET quantity = $quantity WHERE id = $cart_id AND user_id = $user_id";
    } else {
        $sql = "DELETE FROM cart WHERE id = $cart_id AND user_id = $user_id";
    }
    
    return mysqli_query($connect, $sql);
}

// Удалить товар из корзины
function removeFromCart($cart_id, $user_id) {
    global $connect;
    $sql = "DELETE FROM cart WHERE id = $cart_id AND user_id = $user_id";
    return mysqli_query($connect, $sql);
}

// Очистить всю корзину
function clearCart($user_id) {
    global $connect;
    $sql = "DELETE FROM cart WHERE user_id = $user_id";
    return mysqli_query($connect, $sql);
}

// Получить количество товаров в корзине
function getCartCount($user_id) {
    global $connect;
    $sql = "SELECT SUM(quantity) as count FROM cart WHERE user_id = $user_id";
    $result = mysqli_query($connect, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}
?>