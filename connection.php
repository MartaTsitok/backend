<?php
$connect = mysqli_connect('localhost', 'root', '', 'annsgarden');

if (!$connect) {
    die('Ошибка подключения к БД: ' . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8');
?>