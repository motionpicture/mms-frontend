<?php
namespace MvtkService;

/**
 * MvtkService Alert
 *
 * @package MvtkService
 */
class Alert extends MvtkServiceAbstract
{
    /**
     * アラートメール送信先
     */
    const TO = 'yamamoto@motionpicture.jp';

    /**
     * 件名
     */
    const SUBJECT = 'ERROR';

    /**
     * アラートメール送信
     */
    public function SendAlertMail(array $params)
    {
        $params['to'] = self::TO;
        $params['subject'] = self::SUBJECT;

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
