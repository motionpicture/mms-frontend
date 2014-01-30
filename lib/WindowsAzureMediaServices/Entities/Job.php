<?php
include_once('Task.php');

class Job
{
    private $context;

    public $id;
    public $name;
    public $created;
    public $lastModified;
    public $endTime;
    public $priority;
    public $runningDuration;
    public $startTime;
    public $state;
    public $templateId;
    public $inputMediaAssets;
    public $outputMediaAssets;
    public $tasks;

    public function __construct($context, $name, $id)
    {
        $this->context = $context;
        $this->id = $id;
        $this->name = $name;
        $this->tasks = array();
        $this->inputMediaAssets = array();
        $this->outputMediaAssets = array();
    }

    public function addNewTask($taskName, $mediaProcessorId, $configuration, $taskOptions = 0)
    {
        $task = new Task($this, $taskName, $mediaProcessorId, $configuration, $taskOptions);
        $this->tasks[count($this->tasks)] = $task;
        return $task;
    }

    public function submit()
    {
        $url = sprintf('%sJobs', $this->context->wamsEndpoint);
        $headers = array(
            'Content-Type: application/json;odata=verbose',
            'Accept: application/json;odata=verbose',
            'DataServiceVersion: 3.0',
            'MaxDataServiceVersion: 3.0',
            'x-ms-version: 2.2',
            'Authorization: Bearer ' . $this->context->getAccessToken()
        );

        $contentArray = array(
            'Name' => $this->name,
            'InputMediaAssets' => array(),
            'Tasks' => array()
        );

        if (count($this->inputMediaAssets) > 0) {
            foreach ($this->inputMediaAssets as $inputMediaAsset) {
                $contentArray['InputMediaAssets'][] = array(
                    '__metadata' => array(
                        'uri' => $inputMediaAsset->__metadata->uri
                    )
                );
            }
        }

        if (count($this->tasks) > 0) {
            foreach ($this->tasks as $task) {
                $contentArray['Tasks'][] =array(
                    'Name'             => $task->name,
                    'Configuration'    => $task->configuration,
                    'MediaProcessorId' => $task->mediaProcessorId,
                    'Options'          => $task->options,
                    'TaskBody'         => $task->getTaskBody()
                );
            }
        }

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

                $this->id = $object->d->Id;
                $this->name = $object->d->Name;
                $this->created = $object->d->Created;
                $this->lastModified = $object->d->LastModified;
                $this->endTime = $object->d->EndTime;
                $this->priority = $object->d->Priority;
                $this->runningDuration = $object->d->RunningDuration;
                $this->startTime = $object->d->StartTime;
                $this->state = $object->d->State;
                $this->templateId = $object->d->TemplateId;
            } else {
                var_dump($result);
            }

            curl_close($ch);
        } else {
            $e = new WindowsAzureMediaServicesException(curl_error($ch));
            curl_close($ch);
            throw $e;
        }
    }

    public function get()
    {
        $url = sprintf('%sJobs(\'%s\')', $this->context->wamsEndpoint, $this->id);
        $headers = array(
            'Accept: application/json;odata=verbose',
            'DataServiceVersion: 3.0',
            'MaxDataServiceVersion: 3.0',
            'x-ms-version: 2.2',
            'Authorization: Bearer ' . $this->context->getAccessToken()
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

            if ($info['http_code'] == '200') {
                $object = json_decode($result);

                $this->id = $object->d->Id;
                $this->name = $object->d->Name;
                $this->created = $object->d->Created;
                $this->lastModified = $object->d->LastModified;
                $this->endTime = $object->d->EndTime;
                $this->priority = $object->d->Priority;
                $this->runningDuration = $object->d->RunningDuration;
                $this->startTime = $object->d->StartTime;
                $this->state = $object->d->State;
                $this->templateId = $object->d->TemplateId;
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

    public function ListTasks()
    {
        $tasks = array();
        $url = sprintf('%sJobs(\'%s\')/Tasks', $this->context->wamsEndpoint, $this->id);
        $headers = array(
            'Accept: application/json;odata=verbose',
            'DataServiceVersion: 3.0',
            'MaxDataServiceVersion: 3.0',
            'x-ms-version: 2.2',
            'Authorization: Bearer ' . $this->context->getAccessToken()
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

            if ($info['http_code'] == '200') {
                $object = json_decode($result);

                $jobTasks = $object->d->results;
                foreach($jobTasks as $jobTask){
                    $task = new Task($this, $jobTask->Name);

                    $task->id = $jobTask->Id;
                    $task->configuration = $jobTask->Configuration;
                    $task->endTime = $jobTask->EndTime;
                    $task->errorDetails = $jobTask->ErrorDetails;
                    $task->mediaProcessorId = $jobTask->MediaProcessorId;
                    $task->name = $jobTask->Name;
                    $task->perfMessage = $jobTask->PerfMessage;
                    $task->priorty = $jobTask->Priorty;
                    $task->progress = $jobTask->Progress;
                    $task->runningDuration = $jobTask->RunningDuration;
                    $task->startTime = $jobTask->StartTime;
                    $task->state = $jobTask->State;
                    $task->progress = $jobTask->Progress;
                    $task->outputMediaAssets = $jobTask->OutputMediaAssets;
                    $task->inputMediaAssets = $jobTask->InputMediaAssets;

                    $tasks[] = $task;
                }
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

        return $tasks;
    }

    public function ListOutputMediaAssets()
    {
        $assets = array();
        $url = sprintf('%sJobs(\'%s\')/OutputMediaAssets', $this->context->wamsEndpoint, $this->id);
        $headers = array(
            'Accept: application/json;odata=verbose',
            'DataServiceVersion: 3.0',
            'MaxDataServiceVersion: 3.0',
            'x-ms-version: 2.2',
            'Authorization: Bearer ' . $this->context->getAccessToken()
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

            if ($info['http_code'] == '200') {
                $object = json_decode($result);

                $outputMediaAssets = $object->d->results;
                foreach($outputMediaAssets as $outputMediaAsset){
                    $asset = $this->context->getAssetReference();

                    $asset->__metadata = $outputMediaAsset->__metadata;
                    $asset->id = $outputMediaAsset->Id;
                    $asset->name = $outputMediaAsset->Name;
                    $asset->state = $outputMediaAsset->State;
                    $asset->options = $outputMediaAsset->Options;
                    $asset->alternateId = $outputMediaAsset->AlternateId;
                    $asset->created = $outputMediaAsset->Created;
                    $asset->lastModified = $outputMediaAsset->LastModified;

                    $assets[] = $asset;
                }
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

        return $assets;
    }
}

class JobState
{
    public static $QUEUED = 0;
    public static $SCHEDULED = 1;
    public static $PROCESSING = 2;
    public static $FINISHED = 3;
    public static $ERROR = 4;
    public static $CANCELED = 5;
    public static $CANCELING = 6;

    public static function GetJobStateString($state)
    {
        if ($state == 0) {
            return 'Queued';
        } else if($state == 1) {
            return 'Scheduled';
        } else if($state == 2) {
            return 'Processing';
        } else if($state == 3) {
            return 'Finished';
        } else if($state == 4) {
            return 'Error';
        } else if($state == 5) {
            return 'Canceled';
        } else if($state == 6) {
            return 'Canceling';
        }
    }
}
?>