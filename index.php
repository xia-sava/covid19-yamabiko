<?php

use Goutte\Client;
use PHPMailer\PHPMailer\PHPMailer;

require 'vendor/autoload.php';

class Main
{
    private Client $client;

    private const LOCATIONS = [
        '新横浜整形外科リウマチ科' => ['12', '46'],
        '【篠原口】しんよこ駅前整形外科リウマチ科' => ['19', '66'],
    ];

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setServerParameters([
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        ]);
    }

    public function main(): void
    {
        // ログインページで Cookie 等を食う
        $this->client->request('GET', 'https://yamabikovaccine.reserve.ne.jp/sp/index.php');
        sleep(1);

        // ログインする
        $this->client->request('POST', 'https://yamabikovaccine.reserve.ne.jp/mobile2/reserve.php', [
            'auth_flg' => '1',
            'multi_loginid[0]' => $_ENV['USERNAME'],
            'multi_password[0]' => $_ENV['PASSWORD'],
            'upper_mm_id' => '12',
            'mms_id' => '46',
            'mm_id' => '12',
            'json_flg' => '1',
            'set_option_flg' => '1',
            'guest_flg' => '0',
            'next_state' => 'reserve_input',
            'next_unix_date' => '',
            'prior_unix_date' => '',
            'sm_id_only_flg' => '0',
            'mm_id_only_flg' => '0',
            'cate_id_only_flg' => '',
            'ar_r_id_only_flg' => '',
            'datetime_only_flg' => '0',
            'mm_gr_id' => '0',
            'cates_id' => '1',
            'one_status' => 'certification',
            'optvar_6[all]' => '',
            'res_unix_datetime' => '',
        ]);
        sleep(1);

        $availables = [];
        foreach (self::LOCATIONS as $location => [$mm_id, $mms_id]) {
            $next_date = '';
            // 7月いっぱいの予約を探す感じでループ
            while ($next_date < strtotime('2021-08-01')) {
                $res = $this->client->request('POST', 'https://yamabikovaccine.reserve.ne.jp/mobile2/reserve.php', [
                    'upper_mm_id' => $mm_id,
                    'mms_id' => $mms_id,
                    'mm_id' => $mm_id,
                    'json_flg' => '1',
                    'set_option_flg' => '1',
                    'guest_flg' => '0',
                    'next_state' => 'reserve_input',
                    'next_unix_date' => $next_date,
                    'prior_unix_date' => '',
                    'sm_id_only_flg' => '0',
                    'mm_id_only_flg' => '0',
                    'cate_id_only_flg' => '',
                    'ar_r_id_only_flg' => '',
                    'datetime_only_flg' => '0',
                    'mm_gr_id' => '0',
                    'cates_id' => '1',
                    'one_status' => 'choice_option',
                    'optvar_6[all]' => $_ENV['NUMBER'],
                    'optvar_7[all]' => '%E7%A2%BA%E8%AA%8D%E3%81%97%E3%81%BE%E3%81%97%E3%81%9F',
                    'res_unix_datetime' => '',
                ]);
                assert($res !== null);
                $json = json_decode($res->text(), associative: true);
                foreach ($json['operation']['calendar']['ar_empty_reserve'] as $date => $times) {
                    foreach ($times as $time => $slot) {
                        if ($slot > 0) {
                            $availables[] = [$location, $date, $time, $slot];
                        }
                    }
                }

                if ($next_date === '') {
                    $next_date = strtotime('today 0:00');
                }
                $next_date += (7 * 24 * 60 * 60);
                sleep(1);
            }
        }

        if (count($availables)) {
            $body = "やまびこグループのワクチン予約に空きがありますよ！\n\n";

            foreach ($availables as [$location, $date, $time, $slot]) {
                $body .= "{$location} {$date} {$time} -> {$slot}枠分\n";
            }
            $body .= "\n\nサイトへゴー！ -> https://yamabikovaccine.reserve.ne.jp/sp/index.php\n\n";
            try {
                $mail = new PHPMailer(exceptions: true);
                $mail->CharSet = PHPMailer::CHARSET_UTF8;

                $mail->isSMTP();
                $mail->Host       = $_ENV['MAIL_SERVER'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['MAIL_USER'];
                $mail->Password   = $_ENV['MAIL_PASSWD'];
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom('xia@silvia.com', 'ワクチン予約チェッカー for やまびこグループ');
                foreach (explode(',', $_ENV['MAILTO']) as $mailto) {
                    $mail->addAddress($mailto);
                }
                $mail->Sender = 'xia@silvia.com';
                $mail->Subject = 'やまびこグループのワクチン予約に空きがありますよ！';
                $mail->Body    = $body;

                $mail->send();

                print($body);
            } catch (Exception $e) {
                echo 'Caught exception: '. $e->getMessage() ."\n";
            }
        } else {
            print("空きはなかったよ……\n");
        }
    }
}

(new Main())->main();
