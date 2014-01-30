<?php

class Asset
{
    private $context;

    public $__metadata;
    public $id;
    // 0 = initialized, 1 = deleted
    public $state;
    public $created;
    public $lastModified;
    public $alternateId;
    public $name;
    // 0 = none, 1 = storage encrypted, 2 = common encryption protected
    public $options;

    public function __construct($context, $id)
    {
        $this->context = $context;
        if(isset($id)){
            $this->id = $id;
        }
    }

    public function create()
    {
        if (strlen($this->name) == 0) {
            $this->name = uniqid('asset_');
        }
        $url = sprintf('%sAssets/', $this->context->wamsEndpoint);
        $headers = array(
            'Content-Type: application/json;odata=verbose',
            'Accept: application/json;odata=verbose',
            'DataServiceVersion: 3.0',
            'MaxDataServiceVersion: 3.0',
            'x-ms-version: 2.2',
            'Authorization: Bearer ' . $this->context->getAccessToken(),
            'Expect: 100-continue'
        );

        $contentArray = array(
            'Name'    => $this->name,
            'Options' => $this->options
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
                $this->state = $object->d->State;
                $this->options = $object->d->Options;
                $this->alternateId = $object->d->AlternateId;
                $this->created = $object->d->Created;
                $this->lastModified = $object->d->LastModified;
            }

            curl_close($ch);
        } else {
            $e = new WindowsAzureMediaServicesException(curl_error($ch));
            curl_close($ch);
            throw $e;
        }
    }

    public function createFileInfos()
    {
        $url = sprintf('%sCreateFileInfos?assetid=\'%s\'', $this->context->wamsEndpoint, $this->id);

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
            CURLOPT_CUSTOMREQUEST  => 'GET',
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
}

class AssetState
{
    public static $INITIALIZED = 0;
    public static $PUBLICHED = 1;
    public static $DELETED = 2;
}

class AssetOptions
{
    public static $NONE = 0;
    public static $STORAGE_ENCRYPTED = 1;
    public static $COMMON_ENCRYPTION_PROTECTED = 2;
}

class BlockListType
{
    public static $ALL = 'all';
    public static $COMMITED = 'commited';
    public static $UNCOMMITED = 'uncommitted';
}

class Block
{
    public $name;
    public $size;
    public $type;
}

?>