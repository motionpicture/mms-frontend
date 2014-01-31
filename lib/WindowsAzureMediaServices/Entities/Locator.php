<?php

class Locator
{
    private $context;

    public $__metadata;
    public $id;
    public $expirationDateTime;
    //None = 0, SAS = 1, OnDemandOrigin = 2
    public $type;
    public $path;
    public $baseUri;
    public $contentAccessComponent;
    public $accessPolicyId;
    public $assetId;
    public $startTime;

    public function __construct($context, $id)
    {
        $this->context = $context;
        $this->id = $id;
    }

    public function create()
    {
        $url = sprintf('%sLocators/', $this->context->wamsEndpoint);
        $headers = array(
            'Content-Type: application/json;odata=verbose',
            'Accept: application/json;odata=verbose',
            'DataServiceVersion: 3.0',
            'MaxDataServiceVersion: 3.0',
            'x-ms-version: 2.2',
            'Authorization: Bearer ' . $this->context->getAccessToken(),
            'Expect: 100-continue',
        );
        $contentArray = array(
            'AccessPolicyId' => $this->accessPolicyId,
            'AssetId'        => $this->assetId,
            'StartTime'      => $this->startTime,
            'Type'           => $this->type
        );
        $content = json_encode($contentArray);
        $ch = curl_init();
        $options = array(
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        );
        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        if (!curl_errno($ch)) {
            $info = curl_getinfo($ch);

            if ($info['http_code'] == '201') {
                $object = json_decode($result);

                $this->__metadata = $object->d->__metadata;
                $this->id = $object->d->Id;
                $this->expirationDateTime = $object->d->ExpirationDateTime;
                $this->type = $object->d->Type;
                $this->path = $object->d->Path;
                $this->baseUri = $object->d->BaseUri;
                $this->contentAccessComponent = $object->d->ContentAccessComponent;
                $this->accessPolicyId = $object->d->AccessPolicyId;
                $this->assetId = $object->d->AssetId;
                $this->startTime = $object->d->StartTime;
            } else {
                $object = json_decode($result);
                $e = new WindowsAzureMediaServicesException($object->error->message->value);
                curl_close($ch);
                throw $e;
            }

            curl_close($ch);
        } else {
            $e = new WindowsAzureMediaServicesException(curl_error($ch));
            curl_close($ch);
            throw $e;
        }
    }

    public function delete()
    {
        $url = sprintf('%sLocators(\'%s\')', $this->context->wamsEndpoint, $this->id);
        $headers = array(
            'Content-Type: application/json;odata=verbose',
            'Accept: application/json;odata=verbose',
            'DataServiceVersion: 3.0',
            'MaxDataServiceVersion: 3.0',
            'x-ms-version: 2.2',
            'Authorization: Bearer ' . $this->context->getAccessToken(),
            'Expect: 100-continue',
            'Content-Length: 0'
        );
        $ch = curl_init();
        $options = array(
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        );
        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        if (!curl_errno($ch)) {
            $info = curl_getinfo($ch);

            if ($info['http_code'] != '204') {
                $object = json_decode($result);

                $e = new WindowsAzureMediaServicesException($object->error->message->value);
                curl_close($ch);
                throw $e;
            }

            curl_close($ch);
        } else {
            $e = new WindowsAzureMediaServicesException(curl_error($ch));
            curl_close($ch);
            throw $e;
        }
    }

    public function upload($file_name, $tmp_name)
    {
        set_time_limit(0);

        $url = $this->baseUri . '/' .  $file_name . $this->contentAccessComponent;
        $headers = array(
            'Content-Type: application/octet-stream',
            'x-ms-version: 2011-08-18',
            'x-ms-date: ' . gmdate('Y-m-d'),
            'x-ms-blob-type: BlockBlob',
            'Expect: 100-continue'
        );
        $fp = fopen($tmp_name, 'rb');
        if ($fp === false) {
            $egl = error_get_last();
            $e = new WindowsAzureMediaServicesException('ファイルを開くことができませんでした' . $egl['message']);
            throw $e;
        }
        $ch = curl_init();
        $options = array(
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_PUT            => 1,
            CURLOPT_INFILE         => $fp,
            CURLOPT_INFILESIZE     => filesize($tmp_name),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_BINARYTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CONNECTTIMEOUT => 0,
            CURLOPT_TIMEOUT        => 600
        );
        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        if (!curl_errno($ch)) {
            $info = curl_getinfo($ch);

            if ($info['http_code'] != '201') {
                $object = json_decode($result);

                $e = new WindowsAzureMediaServicesException($object->error->message->value);
                curl_close($ch);
                throw $e;
            }

            curl_close($ch);
        } else {
            $e = new WindowsAzureMediaServicesException(curl_error($ch));
            curl_close($ch);
            throw $e;
        }
    }

}

class LocatorType
{
    //None = 0, SAS = 1, OnDemandOrigin = 2
    public static $NONE = 0;
    public static $SAS = 1;
    public static $ON_DEMAND_ORIGIN = 2;
}
?>