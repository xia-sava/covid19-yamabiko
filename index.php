<?php

use Abraham\TwitterOAuth\TwitterOAuth;
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
        Sentry\init(['dsn' => $_ENV['SENTRY_DSN'] ?? '' ]);
    }

    public function main(): void
    {
        // JSON 空き情報を取得
        $start = date(DateTimeInterface::RFC3339_EXTENDED);
        $end = date(DateTimeInterface::RFC3339_EXTENDED, strtotime($_ENV['LIMIT_DATE'] ?? '+2 week'));
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

        if ($_ENV['TEST'] ?? '') {
            $availables[] = date('Y-m-d H:i');
        }
        if (count($availables)) {
            $now = date('H:i');
            $availables_str = implode(" / ", $availables);
            $body = <<<END
({$now}) 新横浜整形外科リウマチ科のワクチン予約に空きがありますよ！
予約ページ -> https://stores-reserve.com/yamabiko/booking_pages
カレンダー -> https://stores-reserve.com/yamabiko/services

{$availables_str}
END;
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
        foreach (explode(',', $_ENV['NOTIFY'] ?? '') as $method) {
            switch ($method) {
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
                    $channel = $_ENV['SLACK_NOTIFY_CHANNEL'] ?? 'covid-yamabiko';
                    $json = json_encode([
                        'text' => $body,
                        'channel' => $channel,
                    ], JSON_THROW_ON_ERROR);
                    $this->client->request('POST', $slack, content: $json);
                    break;
                case 'line':
                    $httpClient = new CurlHTTPClient($_ENV['LINE_ACCESS_TOKEN']);
                    $bot = new LINEBot($httpClient, ['channelSecret' => $_ENV['LINE_CHANNEL_SECRET']]);
                    $message = new TextMessageBuilder($body);
                    $bot->broadcast($message);
                    break;
                case 'twitter':
                    $twitter = new TwitterOAuth(
                        $_ENV['TWITTER_API_KEY'],
                        $_ENV['TWITTER_API_SECRET_KEY'],
                        $_ENV['TWITTER_ACCESS_TOKEN'],
                        $_ENV['TWITTER_ACCESS_TOKEN_SECRET'],
                    );
                    $shorten = mb_strimwidth($body, 0, 320, '...');
                    $twitter->post('statuses/update', ['status' => $shorten]);
            }
        }
    }
}

(new Main())->main();
