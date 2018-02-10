<?php

namespace alexshadie\FnsReceiptBot;

use alexshadie\FnsReceiptBot\Handler\QRCodeImageHandler;
use alexshadie\FnsReceiptBot\Handler\StartMessageHandler;
use alexshadie\TelegramBot\Bot\LongPollingBot;
use alexshadie\TelegramBot\Bot\LongPollingBotApi;
use alexshadie\TelegramBot\MessageDispatcher\EchoMessageHandler;
use alexshadie\TelegramBot\MessageDispatcher\MessageDispatcher;

define("ROOT", realpath(__DIR__ . "/.."));
define("TMP_PATH", ROOT . "/tmp");
define("SRC_PATH", ROOT . "/src");

if (!is_dir(TMP_PATH)) {
    mkdir(TMP_PATH, 0777);
}

require __DIR__ . "/../vendor/autoload.php";

$logger = new \Monolog\Logger("telegram-bot");
$config = include(__DIR__ . '/auth.php');

$botApi = new LongPollingBotApi($config['bot']['name'], $config['bot']['key'], $logger);

$messageDispatcher = new MessageDispatcher($botApi);
$messageDispatcher->addHandler(
    new EchoMessageHandler()
);

$messageDispatcher->addHandler(
    new StartMessageHandler(),
    0
);


$messageDispatcher->addHandler(
    new QRCodeImageHandler(),
    10
);

$bot = new LongPollingBot($botApi,$messageDispatcher, $logger);

$sigHandler = function ($signo) use ($logger, $bot) {
    $logger->info("Caught signal {$signo}, stopping...");
    $bot->stop();
    return true;
};

pcntl_async_signals(true);
pcntl_signal(SIGTERM, $sigHandler);
pcntl_signal(SIGINT, $sigHandler);

echo $bot->getMe()->toString();

$bot->run();
