<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

class AboutCommand extends UserCommand
{
    protected  $name = 'about';
    protected $description = 'Information about the bot';
    protected $usage = '/about';

    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();

        $text = "🤖 <b>Image Editor Bot</b>\n"
        . "Автор: Виссам Аддахи\n"
        . "Летняя практика 2025\n"
        . "Функции: кадрирование, ч/б, PNG/JPG\n"
        . "Исходный код: локально на планшете (без внешних API)\n";

        return Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }
}