<?php
class TelegramBot {
    private $token;
    private $chatId;
    
    public function __construct($token, $chatId) {
        $this->token = $token;
        $this->chatId = $chatId;
    }
    
    public function sendMessage($message) {
        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        
        $data = [
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
}
?>