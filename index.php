<?php

use Goutte\Client;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use PHPMailer\PHPMailer\PHPMailer;

require 'vendor/autoload.php';

class Main
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setServerParameters([
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        ]);
    }

    public function main(): void
    {
        // JSON 空き情報を取得
        $start = date(DateTimeInterface::RFC3339_EXTENDED);
        $end = date(DateTimeInterface::RFC3339_EXTENDED, strtotime($_ENV['LIMIT_DATE']));
        $this->client->request(
            'GET',
            "https://stores-reserve.com/api/v2/merchants/yamabiko/booking_events?renderer=fullcalendar&start=$start&end=$end",
        );
        $response = $this->client->getResponse()->getContent();
        /** @noinspection JsonEncodingApiUsageInspection */
        $res = json_decode($response, associative: true);
        $availables = [];
        foreach ($res as $entry) {
            $availables[] = $entry['start'];
        }

        if ($_ENV['TEST']) {
            $availables[] = date('Y-m-d H:i');
        }
        if (count($availables)) {
            $body = "新横浜整形外科リウマチ科のワクチン予約に空きがありますよ！\n\n";

            $body .= implode(" / ", $availables);
            $body .= "\n\nサイトへゴー！ -> https://stores-reserve.com/yamabiko/services\n\n";
            try {
                $this->notify($body);
                print($body);
            } catch (Exception $e) {
                echo 'Caught exception: ' . $e->getMessage() . "\n";
            }
        } else {
            print("空きはなかったよ……\n");
//            $this->notify("空きはなかったよ……\n");
        }
    }

    public function notify(string $body): void
    {
        switch ($_ENV['NOTIFY']) {
            case 'mail':
                $mail = new PHPMailer(exceptions: true);
                $mail->CharSet = PHPMailer::CHARSET_UTF8;

                $mail->isSMTP();
                $mail->Host = $_ENV['MAIL_SERVER'];
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['MAIL_USER'];
                $mail->Password = $_ENV['MAIL_PASSWD'];
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('xia@silvia.com', 'ワクチン予約チェッカー for やまびこグループ');
                foreach (explode(',', $_ENV['MAILTO']) as $mailto) {
                    $mail->addAddress($mailto);
                }
                $mail->Sender = 'xia@silvia.com';
                $mail->Subject = 'やまびこグループのワクチン予約に空きがありますよ！';
                $mail->Body = $body;

                $mail->send();
                break;
            case 'slack':
                $slack = $_ENV['SLACK_WEBHOOK_URL'];
                $json = json_encode([
                    'text' => $body,
                ], JSON_THROW_ON_ERROR);
                $this->client->request('POST', $slack, content: $json);
                break;
            case 'line':
                $httpClient = new CurlHTTPClient($_ENV['LINE_ACCESS_TOKEN']);
                $bot = new LINEBot($httpClient, ['channelSecret' => $_ENV['LINE_CHANNEL_SECRET']]);
                $message = new TextMessageBuilder($body);
                $bot->broadcast($message);
                break;
        }
    }
}

(new Main())->main();
