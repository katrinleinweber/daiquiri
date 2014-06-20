<?php

/*
 *  Copyright (c) 2012-2014 Jochen S. Klar <jklar@aip.de>,
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

class Query_Model_Query extends Daiquiri_Model_Abstract {

    /**
     * Queue resource.
     * @var Query_Model_Resource_AbstractQuery $_processor
     */
    protected $_queue;

    /**
     * Processor resource.
     * @var Query_Model_Resource_AbstractProcessor $_processor
     */
    protected $_processor;

    /**
     * Constructor. Sets queue resource and processor resource.
     */
    public function __construct() {
        $this->_queue = Query_Model_Resource_AbstractQuery::factory();
        $this->_processor = Query_Model_Resource_AbstractProcessor::factory();
    }

    /**
     * Returns whether the Query Interface supports a query plan (true) or not (false).
     * @return bool
     */
    public function canShowPlan() {
        $planType = "QPROC_" . strtoupper(Daiquiri_Config::getInstance()->query->processor->plan);

        if ($this->_processor->supportsPlanType($planType) &&
                ($planType === "QPROC_INFOPLAN" or $planType === "QPROC_ALTERPLAN")) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns whether the Query Interface supports altering the query plan (true) or not (false).
     * @return bool
     */
    public function canAlterPlan() {
        $planType = "QPROC_" . strtoupper(Daiquiri_Config::getInstance()->query->processor->plan);

        if ($this->_processor->supportsPlanType($planType) && ($planType === "QPROC_ALTERPLAN")) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Validates a plain text query. TRUE if valid, FALSE if not. 
     * @param string $sql the sql string
     * @param bool $plan flag for plan creation
     * @param string $table result table
     * @param array &$errors buffer array for errors
     * @return bool 
     */
    public function validate($sql, $plan = false, $table, array &$errors) {
        // init error array
        $errors = array();

        // check if there is any input
        if (empty($sql)) {
            $errors['sqlError'] = 'No SQL input given.';
            return false;
        }

        // process sql string
        if ($plan === false) {
            if ($this->_processor->validateQuery($sql, $table, $errors) !== true) {
                return false;
            }
        }

        if ($plan !== false and $this->_processor->supportsPlanType("QPROC_ALTERPLAN") === true) {
            if ($this->_processor->validatePlan($plan, $table, $errors) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * Querys the database with a plain text query.
     * @param string $sql the sql string
     * @param bool $plan flag for plan creation
     * @param string $table result table
     * @param array $options for further options that are handeled by the queue
     * @return array $response 
     */
    public function query($sql, $plan = false, $table, $options = array()) {
        // init error array
        $errors = array();

        // check if there is a name for the new table
        if (empty($table)) {
            $tablename = false;
        } else {
            $tablename = $table;
        }

        // get group of the user
        $usrGrp = Daiquiri_Auth::getInstance()->getCurrentRole();
        if ($usrGrp !== null) {
            $options['usrGrp'] = $usrGrp;
        } else {
            $options['usrGrp'] = "guest";
        }

        // if plan type direct, obtain query plan
        if ($this->_processor->supportsPlanType("QPROC_SIMPLE") === true and $plan === false) {
            $plan = $this->_processor->getPlan($sql, $errors);

            if (!empty($errors)) {
                return array('status' => 'error', 'errors' => $errors);
            }
        } else {
            // if plan type is AlterPlan and no plan is available, throw error
            if ($this->_processor->supportsPlanType("QPROC_ALTERPLAN") === true and $plan === false) {
                $errors['planError'] = 'Query plan required. If you end up here, something went badly wrong';
                return array('status' => 'error', 'errors' => $errors);
            }

            // split plan into lines
            $processing = new Query_Model_Resource_Processing();
            $noMultilineCommentSQL = $processing->removeMultilineComments($plan);
            $multiLines = $processing->splitQueryIntoMultiline($noMultilineCommentSQL, $errors);
            $plan = $multiLines;
        }

        // process sql string
        $job = $this->_processor->query($sql, $errors, $plan, $tablename);
        if (!empty($errors)) {
            return array('status' => 'error', 'errors' => $errors);
        }

        // before submission, see if user has enough quota
        if ($this->_checkQuota($this->_queue, $usrGrp)) {
            $errors['quotaError'] = 'Your quota has been reached. Drop some tables to free space or contact the administrators';
            return array('status' => 'error', 'errors' => $errors);
        }

        // submit job
        $statusId = $this->_queue->submitJob($job, $errors, $options);
        if (!empty($errors)) {
            return array('status' => 'error', 'errors' => $errors);
        }

        // return with success
        return array(
            'status' => 'ok',
            'job' => $job
        );
    }

    /**
     * Returns the query plan.
     * @param string $sql the sql string
     * @return array $response 
     */
    public function plan($sql, array &$errors) {
        // init error array
        $errors = array();

        $plan = $this->_processor->getPlan($sql, $errors);

        return $plan;
    }

    /**
     * Returns whether the quota is reached (true) or not (false).
     * @param string $resource
     * @param string $usrGrp
     * @return bool
     */
    private function _checkQuota($resource, $usrGrp) {
        $dbStatData = $resource->fetchDatabaseStats();

        $usedSpace = (float) $dbStatData['db_size'];

        $quotaStr = Daiquiri_Config::getInstance()->query->quota->$usrGrp;

        //if no quota given, let them fill the disks!
        if (empty($quotaStr)) {
            return false;
        }

        //parse the quota to resolve KB, MB, GB, TB, PB, EB...
        preg_match("/([0-9.]+)\s*([KMGTPEBkmgtpeb]*)/", $quotaStr, $parse);
        $quota = (float) $parse[1];
        $unit = $parse[2];

        switch (strtoupper($unit)) {
            case 'EB':
                $quota *= 1024;
            case 'PB':
                $quota *= 1024;
            case 'TB':
                $quota *= 1024;
            case 'GB':
                $quota *= 1024;
            case 'MB':
                $quota *= 1024;
            case 'KB':
                $quota *= 1024;
            default:
                break;
        }

        if ($usedSpace > $quota) {
            return true;
        } else {
            return false;
        }
    }

}
