<?php
$token = '8643566646:AAE83RsIEQat0opLOR3_qZIG4xk2mzd9Gdo';
$url = "https://api.telegram.org/bot{$token}/getUpdates";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

$data = json_decode($result, true);

echo "<pre>";
print_r($data);
echo "</pre>";
?>