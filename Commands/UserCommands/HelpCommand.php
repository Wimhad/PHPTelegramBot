<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

class HelpCommand extends UserCommand
{
    protected $name = 'help';
    protected $description = 'Help command';
    protected $usage = '/help';

    public function execute(): ServerResponse
    {
      $message = $this->getMessage();
      $chat_id = $message->getChat()->getId();

      $text = "🆘 <b>Справка по Image Editor Bot</b>\n\n"
        . "Этот бот позволяет обрабатывать изображения прямо в Telegram.\n\n"
        . "<b>Возможности:</b>\n"
        . "• Кадрирование: 1:1, 16:9, 3:4, 4:3, 9:16\n"
        . "• Преобразование в ч/б\n"
        . "• Сохранение в PNG или JPG или TIFF\n\n"
        . "<b>Как пользоваться:</b>\n"
        . "1. Отправьте фото боту\n"
        . "2. Выберите нужное действие с помощью кнопок\n"
        . "3. Получите обработанное изображение\n\n"
        . "/about — информация о боте";

        return Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }

}