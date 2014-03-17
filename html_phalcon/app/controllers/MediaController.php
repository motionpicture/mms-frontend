<?php

use WindowsAzure\MediaServices\Models\Job;

class MediaController extends ControllerBase
{

    /**
     * The start action, it shows the "search" view
     */
    public function indexAction()
    {
        $categories = Category::find();

        $searchParams = [
            'word'      => '',
            'job_state' => JobState::getAll(),
            'category'  => []
        ];

        foreach ($categories as $category) {
            $searchParams['category'][] = $category->id;
        }

        $conditions = 'id IS NOT NULL';
        // 検索条件を追加
        if (isset($_GET['word']) && $_GET['word'] != '') {
            $searchParams['word'] = $_GET['word'];
            $conditions .= sprintf(' AND id LIKE \'%%%s%%\'', $searchParams['word']);
        }

        if (isset($_GET['job_state']) && count($_GET['job_state']) > 0) {
            $searchParams['job_state'] = $_GET['job_state'];
            $conditions .= sprintf(' AND job_state IN (\'%s\')', implode('\',\'', $searchParams['job_state']));
        }

        if (isset($_GET['category']) && count($_GET['category']) > 0) {
            $searchParams['category'] = $_GET['category'];
            $conditions .= sprintf(' AND category_id IN (\'%s\')', implode('\',\'', $searchParams['category']));
        }

        $this->view->medias = Media::find([
            'conditions' => $conditions,
            'order' => 'updated_at DESC',
            'limit' => 100
        ]);

        $this->view->setVar('categories', $categories);
        $this->view->setVar('searchParams', $searchParams);
        $this->view->setVar('jobState', new JobState);
    }

    /**
     * Execute the "search" based on the criteria sent from the "index"
     * Returning a paginator for the results
     */
    public function showAction($id)
    {
        $urls = [
            'smooth_streaming' => ''
        ];

        $media = Media::findFirst('id="' . $id . '"');
        if (!$media) {
            $this->flash->error("media was not found");
            return $this->forward("media/index");
        }

        $task = Task::findFirst([
            'conditions' => 'media_id="' . $id . '" AND name = \'smooth_streaming\'',
            'columns' => 'url'
        ]);
        if ($task) {
            $urls['smooth_streaming'] = $task->url;
        }

        $this->view->setVar('media', $media);
        $this->view->setVar('urls', $urls);
        $this->view->setVar('jobState', new JobState);
    }

    /**
     * Shows the view to create a "new" product
     */
    public function newAction()
    {
        //...
    }

    /**
     * Creates a product based on the data entered in the "new" action
     */
    public function createAction()
    {
        //...
    }

    /**
     * Updates a product based on the data entered in the "edit" action
     */
    public function saveAction()
    {
        //...
    }

    /**
     * Deletes an existing product
     */
    public function deleteAction($id)
    {
        //...
    }

}