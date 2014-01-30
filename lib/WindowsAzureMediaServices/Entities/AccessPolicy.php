<?php

class AccessPolicy
{
    private $context;

    public $__metadata;
    public $id;
    public $name;
    public $durationInMinutes;
    //None = 0, Read = 1, Write = 2, Delete = 4, List = 8
    public $permissions;
    public $created;
    public $lastModified;

    public function __construct($context, $id)
    {
        $this->context = $context;
        $this->id = $id;
    }

    public function create()
    {
        if(strlen($this->name) == 0){
            $this->name = uniqid('accessPolicy_');
        }
        $url = sprintf('%sAccessPolicies/', $this->context->wamsEndpoint);
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
            'Name'              => $this->name,
            'DurationInMinutes' => $this->durationInMinutes,
            'Permissions'       => $this->permissions
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
                $this->durationInMinutes = $object->d->DurationInMinutes;
                $this->permissions = $object->d->Permissions;
                $this->name = $object->d->Name;
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

    public function delete()
    {
        $url = sprintf('%sAccessPolicies(\'%s\')', $this->context->wamsEndpoint, $this->id);
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
}

class AccessPolicyPermission
{
    public static $NONE = 0;
    public static $READ = 1;
    public static $WRITE = 2;
    public static $DELETE = 4;
    public static $LIST = 8;
}
?>