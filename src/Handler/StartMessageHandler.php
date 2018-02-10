<?php

namespace alexshadie\FnsReceiptBot\Handler;

use alexshadie\TelegramBot\Bot\BotApi;
use alexshadie\TelegramBot\MessageDispatcher\MessageHandler;
use alexshadie\TelegramBot\Query\Message;

class StartMessageHandler implements MessageHandler
{
    public function isSuitable(Message $message): bool
    {
        if ($message->getText() === '/start'){
            return true;
        }
        return false;
    }

    /**
     * @param Message $message
     * @param BotApi $botApi
     * @throws \ErrorException
     */
    public function handle(Message $message, BotApi $botApi): void
    {
        $botApi->message(
            $message->getChat()->getId(),
            "Привет!\n" .
                "Я с радостью помогу тебе проверить чек. Для этого просто скинь мне фото QR-кода с чека и немного подожди.\n" .
                "Меня написал @alexshadie, в случае вопросов можешь задать их ему в telegram, или на мыло alex@astra.ws\n" .
                "Мои исходники лежат тут (https://github.com/alexshadie/fns-receipt-bot), если вдруг захочешь следать меня лучше - тебе сюда, инфа сотка."
        );
    }

    public function isTerminator(): bool
    {
        return true;
    }

}