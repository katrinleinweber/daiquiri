<?php
/*
 *  Copyright (c) 2012-2015  Jochen S. Klar <jklar@aip.de>,
 *                           Adrian M. Partl <apartl@aip.de>,
 *                           AIP E-Science (www.aip.de)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class Query_Model_Uws extends Uws_Model_UwsAbstract {

    // status = array('PENDING', 'QUEUED', 'EXECUTING', 'COMPLETED', 'ERROR', 'ABORTED', 'UNKNOWN', 'HELD', 'SUSPENDED', 'ARCHIVED');
    private static $statusQueue = array(
        'queued' => 1,
        'running' => 2,
        'removed' => 9,
        'error' => 4,
        'success' => 3,
        'timeout' => 5,
        'killed' => 5
    );

    public function __construct() {
        parent::__construct();
    }

    public function getJobList($params) {
        // get jobs
        $this->setResource(Query_Model_Resource_AbstractQuery::factory());

        // Filter job list, introduced with UWS version 1.1
        // parse here and now for LAST/AFTER parameter and status (= phase)!
        // map uws phase names to internal status names:
        $status_uws = array(
            'QUEUED' => 'queued',
            'EXECUTING' => 'running',
            'ARCHIVED' => 'removed',
            'ERROR' => 'error',
            'COMPLETED' => 'success',
            'ABORTED' => 'killed' // or 'timeout'
        );
        // NOTE: held, suspended and unknown-filter should return nothing for daiquiri,
        // for DirectQuery, only 'success', 'error' and 'removed' exist + pending

        // NOTE: request query string may contain repeated PHASE key,
        // which is not recognized as PHASE-array by standard Zend-functions,
        // thus parse manually:
        $queryparams = Daiquiri_Config::getInstance()->getMultiQuery();

        $statuslist = array();
        $wherestatus = array();
        $phases = array();
        if (array_key_exists('PHASE', $queryparams)) {
            // NOTE: UWS1.1 allows to give more than one PHASE!
            $phases = $queryparams['PHASE'];

            // store internal status_ids for possible uws-phases
            if (!is_array($phases)) {
                $phases = array($phases);
            }

            foreach ($phases as $phase) {
                if (array_key_exists($phase, $status_uws)) {
                    if ($status_id = $this->getResource()->getStatusId($status_uws[$phase])) {
                        $statuslist[] = $status_id;
                    }
                }
            }

            $wherestatus = '';
            foreach ($statuslist as $status_id) {
                $wherestatus .=  ' status_id = ' . $status_id . ' OR';
            }
            // remove trailing OR
            $wherestatus = array(substr($wherestatus, 0, -2));
        }

        if (empty($statuslist)) {
            $wherestatus = array('status_id != ?' => $this->getResource()->getStatusId('removed'));
        }

        $limit = ''; // default limit of returned rows, '' = no limit
        // check LAST keyword and set $limit accordingly
        if (array_key_exists('LAST', $params)) {
            $limit  = $params['LAST'];
            // check if string contains only digits (positive integer!),
            // if so, convert to integer
            if (isset($limit) && ctype_digit($limit)) {
                $limit = intval($limit);
            }
        }

        $whereafter = array();
        if (array_key_exists('AFTER', $params)) {
            $after = $params['AFTER'];
            $after = strtotime($after);
            // TODO: check, if it is a timestamp, in Iso 8601 format (UTC)
            $whereafter = array('time >= ?' => date('Y-m-d H:i:s', $after));
        }

        // get the userid
        $userId = Daiquiri_Auth::getInstance()->getCurrentId();

        $jobs = new Uws_Model_Resource_Jobs();
        // If only PENDING jobs are requested, do not need to query this db table,
        // pending jobs are stored only in the db table queried below.
        // NOTE: use here $phases, or only "valid" phases (those in $statuslist)?
        if ( ! (count($phases) == 1 && $phases[0] == "PENDING") ) {
            $whereuser = array('user_id = ?' => $userId);
            $wherelist = array_merge($whereuser, $wherestatus, $whereafter);

            // get rows for this user
            $rows = $this->getResource()->fetchRows(array(
                'where' => $wherelist,
                'limit' => $limit,
                'order' => array('time DESC'),
            ));

            foreach ($rows as $job) {
                $href = Daiquiri_Config::getInstance()->getSiteUrl() . "/uws/" . urlencode($params['moduleName']) . "/" . urlencode($job['id']);
                $status = Query_Model_Uws::$status[Query_Model_Uws::$statusQueue[$job['status']]];
                $jobs->addJob($job['table'], $href, array($status));
            }
        }

        // add pending/error jobs, but only if no PHASE-filter was given
        // or phase-filter was PENDING, ERROR or ABORTED
        // NOTE: Not sure about SUSPENDED, HELD or UNKNOWN
        $jobs2 = new Uws_Model_Resource_Jobs();

        if ( empty($phases) || in_array("PENDING", $phases) || in_array("ERROR", $phases) || in_array("ABORTED", $phases) ) {
            $resUWSJobs = new Uws_Model_Resource_UWSJobs();

            $pendingJobList = $resUWSJobs->fetchRows(); //where is the check for the userId?? --> inside UWSJobs

            foreach ($pendingJobList as $job) {
                $href = Daiquiri_Config::getInstance()->getSiteUrl() . "/uws/" . urlencode($params['moduleName']) . "/" . urlencode($job['jobId']);
                $status = $job['phase'];
                if ( empty($phase) || in_array($status, $phases) ) {
                    $jobs2->addJob($job['jobId'], $href, array($status));
                }
            }
        }

        // Now append pending jobs or sort the jobs by their time
        // and apply a final cut to given $limit if it was required.
        // (Could also do this sorting even if LAST/AFTER was not given ...)
        if (array_key_exists('LAST', $params) || array_key_exists('AFTER', $params)) {
            // If LAST or AFTER parameter had been used, then ordering requested by
            // standard is by *ascending* startTimes:
            $jobs->jobref = array_reverse($jobs->jobref);
            $jobs2->jobref = array_reverse($jobs2->jobref);

            // save first job of $jobs for AFTER-requests:
            if (count($jobs->jobref) > 0) {
                $firstjob = $jobs->jobref[0];
            }

            // Either append pending and error jobs
            // (which may have NULL startTimes) just at the end ...
            $jobs->jobref = array_merge($jobs->jobref, $jobs2->jobref);

            // or assume that creation time is correlated to the jobIds
            // (the ones appended at href), so sort by href.
            // This may not be the best approach, but it works with the current
            // setup.
            $sortcolumn = array();
            foreach ($jobs->jobref as $key => $row) {
                $sortcolumn[$key] = $row->reference->href;
            }
            array_multisort($sortcolumn, SORT_ASC, $jobs->jobref);

            // Special treatment for AFTER:
            // there is no time information for pendingJobs, i.e. jobs2-jobs,
            // so use the times from filtered jobs-list (which contains timestamp), 
            // and ignore all those jobs from jobs2-list that are listed
            // before the first job from jobs-list in our merged-filtered list.
            // This is quite ugly, but it kind of works.
            if (isset($firstjob)) {
                $sortid = $firstjob->reference->href;
                $firstjob_index = 0;
                foreach ($jobs->jobref as $index => $row) {
                    if ($row->reference->href === $sortid) {
                        $firstjob_index = $index;
                        break;
                    }
                }
                $jobs->jobref = array_slice($jobs->jobref, $firstjob_index);
            }
            // If $firstjob is not set, then just ignore this and do not cut.

            // Cut, only keep number of jobs required by LAST
            $jobs->jobref = array_slice($jobs->jobref, -$limit, $limit);

        } else {
            $jobs->jobref = array_merge($jobs->jobref, $jobs2->jobref);
        }

        return $jobs;
    }

    public function getJob($requestParams) {
        // get the job id
        $id = $requestParams['wild0'];

        // set resource
        $this->setResource(Query_Model_Resource_AbstractQuery::factory());

        // get the job
        $row = $this->getResource()->fetchRow($id);
        if (empty($row)) {
            throw new Daiquiri_Exception_NotFound();
        }
        if ($row['user_id'] !== Daiquiri_Auth::getInstance()->getCurrentId()) {
            throw new Daiquiri_Exception_Forbidden();
        }

        // fill UWS object with information
        $jobUWS = new Uws_Model_Resource_JobSummaryType("job");
        $jobUWS->jobId = $row['id'];
        $jobUWS->ownerId = Daiquiri_Auth::getInstance()->getCurrentUsername();
        $jobUWS->phase = Query_Model_Uws::$status[Query_Model_Uws::$statusQueue[$row['status']]];

        // convert timestamps to ISO 8601
        if (get_class($this->getResource()) == 'Query_Model_Resource_QQueueQuery') {
            if ($row['timeExecute'] !== "0000-00-00 00:00:00") {
                $datetimeStart = new DateTime($row['timeExecute']);
                $jobUWS->startTime = $datetimeStart->format('c');
            }

            if ($row['timeFinish'] !== "0000-00-00 00:00:00") {
                $datetimeEnd = new DateTime($row['timeFinish']);
                $jobUWS->endTime = $datetimeEnd->format('c');
            }
        } else {
            // for simple queue
            $datetime = new DateTime($row['time']);
            $jobUWS->startTime = $datetime->format('c');
            $jobUWS->endTime = $datetime->format('c');
        }

        if ($this->getResource()->hasQueues()) {
            $config = $this->getResource()->fetchConfig();
            $jobUWS->executionDuration = $config["userQueues"][$row['queue']]['timeout'];
        } else {
            // no queue information - execution infinite
            $jobUWS->executionDuration = 0;
        }

        // no destruction time supported, so return hillariously high number
        $datetime = new DateTime('31 Dec 2999');
        $jobUWS->destruction = $datetime->format('c');

        // fill the parameter part of the UWS with the original information stored in the queue
        foreach ($row as $key => $value) {
            // allowed parameters
            switch ($key) {
                case 'database':
                case 'table':
                case 'query':
                case 'actualQuery':
                case 'queue':
                    $jobUWS->addParameter($key, $value);
                    break;
                default:
                    break;
            }
        }

        // add link to results if needed
        if ($jobUWS->phase === "COMPLETED") {
            foreach (Daiquiri_Config::getInstance()->getQueryDownloadAdapter() as $adapter) {

                $id = $adapter['suffix'];
                $href = Daiquiri_Config::getInstance()->getSiteUrl() . '/query/download/stream/table/' .urlencode($row['table']) . '/format/' . $adapter['format'];

                $jobUWS->addResult($id, $href);
            }
        } else if ($jobUWS->phase === "ERROR") {
            $jobUWS->addError($row['error']);
        }

        return $jobUWS;
    }

    public function getError(Uws_Model_Resource_JobSummaryType $job) {
        if (empty($job->errorSummary->messages)) {
            return "";
        } else {
            return implode("\n", $job->errorSummary->messages);
        }
    }

    public function getQuote() {
        return NULL;
    }

    public function setDestructTimeImpl(Uws_Model_Resource_JobSummaryType &$job, $newDestructTime) {
        //no destruction time supported, so return hillariously high number
        $datetime = new DateTime('31 Dec 2999');
        $jobUWS->destruction = $datetime->format('c');

        //this model does not support destruction time, so we don't need to store anything and just return
        return $jobUWS->destruction;
    }

    public function setExecutionDurationImpl(Uws_Model_Resource_JobSummaryType &$job, $newExecutionDuration) {
        //no dynamic execution duration update supported, so don't change anything and return the already
        //saved value

        return $jobUWS->executionDuration;
    }

    public function deleteJobImpl(Uws_Model_Resource_JobSummaryType &$job) {
        // set job resource
        $this->setResource(Query_Model_Resource_AbstractQuery::factory());

        // get job and check permissions
        $row = $this->getResource()->fetchRow($job->jobId);

        if ($row['user_id'] !== Daiquiri_Auth::getInstance()->getCurrentId()) {
            throw new Daiquiri_Exception_Forbidden();
        }

        // remove job
        $this->getResource()->removeJob($job->jobId);
        return true;
    }

    public function abortJobImpl(Uws_Model_Resource_JobSummaryType &$job) {
        // set job resource
        $this->setResource(Query_Model_Resource_AbstractQuery::factory());

        // get job and check permissions
        $row = $this->getResource()->fetchRow($job->jobId);
        if ($row['user_id'] !== Daiquiri_Auth::getInstance()->getCurrentId()) {
            throw new Daiquiri_Exception_Forbidden();
        }

        // kill job
        $this->getResource()->killJob($id);
        return true;
    }

    public function runJob(Uws_Model_Resource_JobSummaryType &$job) {
        // obtain queue information
        $this->setResource(Query_Model_Resource_AbstractQuery::factory());
        $config = $this->getResource()->fetchConfig();

        if ($this->getResource()->hasQueues()) {

            if (isset($job->parameters['queue'])) {
                $jobUWS->executionDuration = $config["userQueues"][$job->parameters['queue']->value]['timeout'];
            } else {
                // no queue has been specified, but we support queues - if executionDuration is 0, use default queue
                // otherwise find the desired queue
                if ($job->executionDuration === 0) {
                    // use default queue here
                    $queue = Daiquiri_Config::getInstance()->query->query->qqueue->defaultQueue;
                } else {
                    // find a queue that matches the request (i.e. is nearest to the request)
                    $queue = $this->_findQueue($job->executionDuration,$config["userQueues"]);
                }

                $jobUWS->executionDuration = $config["userQueues"][$queue]['timeout'];
                $job->addParameter("queue", $queue);
            }
        }

        // now check if everything is there that we need...
        $tablename = null;
        $sql = null;
        $queue = null;
        $errors = array();

        if (!isset($job->parameters['query']) || ($this->getResource()->hasQueues() && !isset($job->parameters['queue']))) {
            // throw error
            $job->addError("Incomplete job");
            $resource = new Uws_Model_Resource_UWSJobs();
            $resource->updateRow($job->jobId, array("phase" => "ERROR", "errorSummary" => Zend_Json::encode($job->errorSummary)));
            return;
        }

        if (isset($job->parameters['table'])) {
            $tablename = $job->parameters['table']->value;
        }

        $sql = $job->parameters['query']->value;

        if ($this->getResource()->hasQueues()) {
            $queue = $job->parameters['queue']->value;
        }

        // submit job

        // prepare sources array
        $sources = array();

        // set startTime here, since job starts now
        // (validation is part of the job)
        $now = new DateTime('now'); // should actually use UTC time!!
        $job->startTime = $now->format('Y-m-d H:i:s');
        $resource = new Uws_Model_Resource_UWSJobs();
        $resource->updateRow($job->jobId, array("startTime" => $job->startTime));


        // validate query
        $job->resetErrors();
        $model = new Query_Model_Query();
        try {
            if ($model->validate($sql, false, $tablename, $errors, $sources) !== true) {
                //throw error
                foreach ($errors as $error) {
                    $job->addError($error);
                }

                $resource = new Uws_Model_Resource_UWSJobs();
                $resource->updateRow($job->jobId, array("phase" => "ERROR", "errorSummary" => Zend_Json::encode($job->errorSummary)));
                $resource->updateRow($job->jobId, array("endTime" => $job->startTime));
                return;
            }
        } catch (Exception $e) {
            // throw error
            $job->addError($e->getMessage());
            $resource = new Uws_Model_Resource_UWSJobs();
            $resource->updateRow($job->jobId, array("phase" => "ERROR", "errorSummary" => Zend_Json::encode($job->errorSummary)));
            $resource->updateRow($job->jobId, array("endTime" => $job->startTime));
            return;
        }

        // submit query
        if ($this->getResource()->hasQueues()) {
            $response = $model->query($sql, false, $tablename, $sources, array("queue" => $queue, "jobId" => $job->jobId),'uws');
        } else {
            $response = $model->query($sql, false, $tablename, $sources, array("jobId" => $job->jobId),'uws');
        }

        if ($response['status'] !== 'ok') {
            // throw error
            foreach ($response['errors'] as $error) {
                $job->addError($error);
            }
            $resource = new Uws_Model_Resource_UWSJobs();
            $resource->updateRow($job->jobId, array("phase" => "ERROR", "errorSummary" => Zend_Json::encode($job->errorSummary)));
            return;
        }

        // clean up stuff (basically just remove the job in the temorary UWS job store - if we are here
        // everything has been handeled by the queue)
        $resource = new Uws_Model_Resource_UWSJobs();
        $resource->deleteRow($job->jobId);
    }

    /**
     * Finds a queue that matches the requested execution time (i.e. is nearest to it)
     * @param  int    $executionDuration requested execution time
     * @param  array  $queues            user queues from config
     * @return string $queue
     */
    private function _findQueue($executionDuration,$queues) {
        $maxQueueTimeout = 0;
        $maxQueueName = false;

        $deltaQueue = PHP_INT_MAX;
        $queue = false;

        foreach ($queues as $currQueueName => $currQueue) {
            if ($currQueue['timeout'] > $maxQueueTimeout) {
                $maxQueueTimeout = $currQueue['timeout'];
                $maxQueueName = $currQueueName;
            }

            if ($currQueue['timeout'] >= $executionDuration) {
                $currDelta = $currQueue['timeout'] - $executionDuration;
                if ($currDelta < $deltaQueue) {
                    $queue = $currQueueName;
                    $deltaQueue = $currDelta;
                }
            }
        }

        if ($queue === false) {
            $queue = $maxQueueName;
        }

        return $queue;
    }
}
