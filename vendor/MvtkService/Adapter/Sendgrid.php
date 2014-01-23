<?php
namespace MvtkService\Adapter;

/**
 * MvtkService Sendgrid
 *
 * @package MvtkService
 */
class Sendgrid
{
    private $option;

    /**
     * __construct
     */
    public function __construct($option)
    {
        $this->option = $option;
    }


    /**
     * メール送信
     */
    public function send(array $params)
    {
        $params = array_merge($params, $this->option);

        $session = curl_init('http://sendgrid.com/api/mail.send.json');

        curl_setopt($session, CURLOPT_POST, true);
        curl_setopt($session, CURLOPT_POSTFIELDS, $params);
        curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

        $responseOrig = json_decode(curl_exec($session));
        curl_close($session);

        $response = new \stdClass();
        $response->Result = new \stdClass();
        $response->Result->RESULT_INFO = new \stdClass();

        switch ($responseOrig->message) {
            case 'success':
                $response->Result->RESULT_INFO->STATUS = 'N000';
                break;

            case 'error':
                $response->Result->RESULT_INFO->STATUS = 'L003';
                $response->Result->RESULT_INFO->MESSAGE = implode("\n", $responseOrig->errors);
                break;

            default:
                $response->Result->RESULT_INFO->STATUS = 'L003';
                $response->Result->RESULT_INFO->MESSAGE = print_r($responseOrig, true);
                break;
        }

        return $response;
    }
}
