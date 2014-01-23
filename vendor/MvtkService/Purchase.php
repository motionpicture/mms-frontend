<?php
namespace MvtkService;

/**
 * MvtkService Purchase
 *
 * @package MvtkService
 */
class Purchase extends MvtkServiceAbstract
{
    /**
     * アラートメール送信先
     */
    const TO = 'yamamoto@motionpicture.jp';

    /**
     * 件名
     */
    const SUBJECT = 'Purchase error';

    /**
     * 決済方法区分
     */
    const KSSIHH_TYP_CRDTCRD = '00'; // クレジットカード
    const KSSIHH_TYP_AU      = '01'; // au
    const KSSIHH_TYP_GFTCRD  = '04'; // ムビチケオンラインGIFTカード

    /**
     * カード情報入力区分
     */
    const CRDTCRDINPUT_KBN_INPUT      = '01'; // 入力された情報を使用
    const CRDTCRDINPUT_KBN_REGISTERED = '02'; // 登録済みの情報を使用

    /**
     * 本人認証サービス対応区分
     */
    const ACS_KBN_SUPPORTED     = '01'; // 対応している
    const ACS_KBN_UNSUPPORTED = '02'; // 対応していない

    /**
     * 購入デバイス区分
     */
    const KNYDVC_TYP_PC = '01'; // PC
    const KNYDVC_TYP_MB = '02'; // モバイル
    const KNYDVC_TYP_WP = '09'; // WP
    const KNYDVC_TYP_CT = '91'; // 法人券
    const KNYDVC_TYP_MC = '92'; // カード券


    /**
     * __call
     */
    public function __call($name, $arguments)
    {
        $result = parent::__call($name, $arguments);

        if (get_class($result) == 'MvtkService\Result\Kessai' && $result->isKessaiError()) {
            throw new MvtkServiceKessaiException($result->KSSIERRRMSSG_TXT);
        }

        return $result;
    }


    /**
     * 購入管理番号メール送信
     */
    public function SendPurchaseInfoMail(array $params)
    {
        $isError = false;
        if (!isset($params['knyknrNo'])) {
            $isError = true;
        }
        if (!isset($params['mailaddress'])) {
            $isError = true;
        }
        if (!isset($params['address'])) {
            $isError = true;
        }
        if (!isset($params['skhnNm'])) {
            $isError = true;
        }
        if (!isset($params['knshknInfo'])) {
            $isError = true;
        } else {
            foreach ($params['knshknInfo'] as $info) {
                if (!isset($info['KNSHKBN_NM'])) {
                    $isError = true;
                }
                if (!isset($info['KNYMI_NUM'])) {
                    $isError = true;
                }
            }
        }
        if (!isset($params['dvcTyp'])) {
            $isError = true;
        }
        if ($isError) {
            throw new ErrorException('[L003]: Parameter error.');
        }


        $maisu = '';
        foreach ($params['knshknInfo'] as $info) {
            $maisu .= "{$info['KNSHKBN_NM']} {$info['KNYMI_NUM']} 枚 ";
        }


        ob_start();
        include 'templates/SendPurchaseInfoMail.php';
        $body = ob_get_clean();

        $filename = basename($params['qrcdUrl']);
        file_put_contents($filename, file_get_contents($params['qrcdUrl']));
        $filepath = realpath($filename);

        $paramsNew = array(
            'to' => $params['mailaddress'],
            'toname' => $params['address'],
            'subject' => "【ムビチケ】 ご購入チケット情報 「{$params['skhnNm']}」",
            'text' => $body,
            'files['.$filename.']' => '@'.$filepath,
        );


        try {
            $response = $this->sendgrid->send($paramsNew);

            $factory = new Result\Factory();
            $result = $factory->createInstance($response);

            if ($result->isError()) {
                throw new MvtkServiceException("[{$result->RESULT_INFO->STATUS}] {$result->RESULT_INFO->MESSAGE}");
            }
        } catch (\Exception $e) {
            throw $e;
        }

        unlink($filename);

        return $result;
    }


    /**
     * アラートメール送信
     */
    public function SendAlertMail($message)
    {
        $params = [
            'to' => self::TO,
            'subject' => self::SUBJECT,
            'text' => $message,
        ];

        try {
            $response = $this->sendgrid->send($params);

            $factory = new Result\Factory();
            $result = $factory->createInstance($response);

            if ($result->isError()) {
                throw new MvtkServiceException("[{$result->RESULT_INFO->STATUS}] {$result->RESULT_INFO->MESSAGE}");
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $result;
    }
}
