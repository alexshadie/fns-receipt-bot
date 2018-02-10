<?php

namespace alexshadie\FnsReceiptBot\Handler;

use alexshadie\FnsIntegration\Data\Receipt;
use alexshadie\FnsIntegration\Data\ReceiptItem;
use alexshadie\FnsIntegration\FnsIntegration;
use alexshadie\FnsReceiptBot\Util\QrDetector;
use alexshadie\TelegramBot\Bot\BotApi;
use alexshadie\TelegramBot\MessageDispatcher\MessageHandler;
use alexshadie\TelegramBot\Query\Message;
use Monolog\Logger;

class QRCodeImageHandler implements MessageHandler
{
    public function isSuitable(Message $message): bool
    {
        if (!is_null($message->getPhoto())) {
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
        $photos = $message->getPhoto();
        $photo = array_pop($photos);
        $file = $botApi->getFile($photo->getFileId());
        $filename = tempnam(TMP_PATH, "");
        $botApi->downloadFile($file, $filename);
        $botApi->message(
            $message->getChat()->getId(),
            "Отлично! Проверяю фото"
        );
        $detector = new QrDetector($filename);
        $result = $detector->processImage();

        if (!$result) {
            $botApi->message(
                $message->getChat()->getId(),
                "Прости, но я не увидел тут QR-код =(\nМожет я лошара, а может, кто-то другой рукожоп =)"
            );
        } else {
            echo "It's that: " . $result;

            $vars = [];
            parse_str($result, $vars);


            $config = require(SRC_PATH . '/auth.php');

            $integration = new FnsIntegration(
                $config['fns']['username'],
                $config['fns']['password'],
                $config['fns']['device_id'],
                new Logger(__CLASS__)
            );

            if ($integration->validateReceipt($vars['t'], $vars['s'], $vars['fn'], $vars['i'], $vars['fp'], $vars['n'])) {
                $botApi->message(
                    $message->getChat()->getId(),
                    "Чек валидный, сейчас получу инфу по нему"
                );
            } else {
                $botApi->message(
                    $message->getChat()->getId(),
                    "Ты скинул мне кривой чек. Налоговая про него не в курсе. Можешь на них пожаловаться."
                );
                return;
            }

            $iter = 0;
            do {
                $receipt = $integration->queryReceipt($vars['fn'], $vars['i'], $vars['fp']);
                $iter++;
                sleep($iter);
            } while ($iter < 10 && !$receipt instanceof Receipt);


            if ($receipt instanceof Receipt) {
                $botApi->message(
                    $message->getChat()->getId(),
                    "Ура, вот твой чек!"
                );

                $receiptDescription = "";
                $receiptDescription .= date('d.m.Y H:i:s', $receipt->getDateTime()) . " " . $receipt->getUserInn() . " " . $receipt->getUser() . "\n";
                $receiptDescription .= "Сумма: " . $receipt->getTotalSum() . "\n";
                $receiptDescription .= "\n";

                /** @var ReceiptItem $item */
                foreach ($receipt->getItems() as $item) {
                    $receiptDescription .= $item->getName() . " " . $item->getPrice() . " x " . $item->getQuantity() . " = " . $item->getSum() . "\n";
                }

                $botApi->message(
                    $message->getChat()->getId(),
                    $receiptDescription
                );
            } else {
                $botApi->message(
                    $message->getChat()->getId(),
                    "Да, чек валиден, но ФНС его не отдает. Педорасы, сэр!"
                );
            }

        }
    }

    public function isTerminator(): bool
    {
        return true;
    }

}
