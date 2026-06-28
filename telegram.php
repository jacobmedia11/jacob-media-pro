<?php
require_once __DIR__ . '/smtp-config.php';

function telegramSend($message) {
  if (!defined('TELEGRAM_BOT_TOKEN') || !TELEGRAM_BOT_TOKEN || !TELEGRAM_CHAT_ID) return false;

  $ch = curl_init('https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 8);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'chat_id'    => TELEGRAM_CHAT_ID,
    'text'       => $message,
    'parse_mode' => 'HTML',
  ]));
  $res = curl_exec($ch);
  curl_close($ch);
  return $res;
}
