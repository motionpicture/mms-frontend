<?php
include_once('WindowsAzureMediaServicesException.php');
include_once('Entities/Asset.php');
include_once('Entities/AccessPolicy.php');
include_once('Entities/Locator.php');
include_once('Entities/Job.php');

class WindowsAzureMediaServicesContext
{
    private $acsEndpoint = 'https://wamsprodglobal001acs.accesscontrol.windows.net/v2/OAuth2-13';
    private $accountName;
    private $accountKey;
    public $accessToken;
    private $accessTokenExpiry;
    public $wamsEndpoint = 'https://media.windows.net/API/';
    private $storageConnStr;

    public function __construct($accountName, $accountKey, $storageAccountName, $storageAccountKey){
        $this->accountName = $accountName;
        $this->accountKey = $accountKey;
        $this->storageConnStr = 'DefaultEndpointsProtocol=https;AccountName='. $storageAccountName .';AccountKey=' . $storageAccountKey;

        $this->checkForRedirection();
    }

    public function getAccessToken(){
        if (strlen($this->accessToken) == 0 || $this->accessTokenExpiry < time()) {
            $this->fetchNewAccessToken();
        }

        return $this->accessToken;
    }

    public function fetchNewAccessToken(){
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Host: wamsprodglobal001acs.accesscontrol.windows.net',
            'Expect: 100-continue',
            'Connection: Keep-Alive',
        );
        $data = array(
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->accountName,
            'client_secret' => $this->accountKey,
            'scope'         => 'urn:WindowsAzureMediaServices'
        );
        $content = http_build_query($data);
        $ch = curl_init();
        $options = array(
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_URL            => $this->acsEndpoint,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        );
        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        if ($result === false) {
            $e = new WindowsAzureMediaServicesException();
            curl_close($ch);
            throw $e;
        }

        if (!curl_errno($ch)) {
            $info = curl_getinfo($ch);

            if ($info['http_code'] == '200') {
                $result = json_decode($result);
                $this->accessToken = $result->access_token;;
                $this->accessTokenExpiry = time() + $result->expires_in;
            }

            curl_close($ch);
        } else {
            $e = new WindowsAzureMediaServicesException();
            curl_close($ch);
            throw $e;
        }
    }

    public function checkForRedirection(){
        $headers = array(
            'Content-Type: application/json;odata=verbose',
            'Accept: application/json;odata=verbose',
            'DataServiceVersion: 3.0',
            'MaxDataServiceVersion: 3.0',
            'x-ms-version: 2.2',
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Host: wamshknclus001rest-hs.cloudapp.net',
            'Expect: 100-continue',
            'Content-Length: 0'
        );
        $ch = curl_init();
        $options = array(
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_URL            => $this->wamsEndpoint,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        );
        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        if ($result === false) {
            $e = new WindowsAzureMediaServicesException();
            curl_close($ch);
            throw $e;
        }

        if (!curl_errno($ch)) {
            $info = curl_getinfo($ch);

            if ($info['http_code'] == '301') {
                $newLocation = $info['redirect_url'];
                if ($newLocation != $this->wamsEndpoint) {
                    $this->wamsEndpoint = $newLocation;
                    $this->accessToken = null;
                }
            }

            curl_close($ch);
        } else {
            $e = new WindowsAzureMediaServicesException();
            curl_close($ch);
            throw $e;
        }
    }

    public function getAssetReference($id = null)
    {
        return new Asset($this, $id);
    }

    public function getAccessPolicyReference($id = null)
    {
        return new AccessPolicy($this, $id);
    }

    public function getLocatorReference($id = null)
    {
        return new Locator($this, $id);
    }

    public function getJobReference($id = null){
        return new Job($this, null, $id);
    }
}
?>