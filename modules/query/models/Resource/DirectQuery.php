<?php

/*
 *  Copyright (c) 2012, 2013 Jochen S. Klar <jklar@aip.de>,
 *                           Adrian M. Partl <apartl@aip.de>, 
 *                           AIP E-Science (www.aip.de)
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  See the NOTICE file distributed with this work for additional
 *  information regarding copyright ownership. You may obtain a copy
 *  of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

class Query_Model_Resource_DirectQuery extends Query_Model_Resource_AbstractQuery {

    /**
     * Flag if the query interface needs a create table statement.
     * @var bool $needsCreateTable
     */
    public static $needsCreateTable = true;

    /**
     * Flag if the query interface has different queues.
     * @var bool $hasQueues
     */
    public static $hasQueues = false;

    /**
     * Array for the status flags user in the jobs table.
     * @var array $status
     */
    protected static $_status = array('success' => 1, 'error' => 2);

    /**
     * Translateion table to convert the database columns of the job table into something readable.
     * @var array $translations
     */
    protected static $_translations = array(
        'id' => 'Job id',
        'user_id' => 'Internal user id',
        'username' => 'User name',
        'database' => 'Database name',
        'table' => 'Table name',
        'time' => 'Job submission time',
        'query' => 'Original query',
        'actualQuery' => 'Actual query',
        'status_id' => 'Job status',
        'status' => 'Job status',
        'tbl_size' => 'Total disk usage (in MB)',
        'tbl_idx_size' => 'Index disk usage (in MB)',
        'tbl_free' => 'Free space in table (in MB)',
        'tbl_row' => 'Approx. row count',
    );

    /**
     * Array for the columns of the jobs tables.
     * @var array $_cols
     */
    protected static $_cols = array(
        'id' => 'id',
        'table' => 'table',
        'database' => 'database',
        'query' => 'query',
        'actualQuery' => 'actualQuery',
        'user_id' => 'user_id',
        'status_id' => 'status_id',
        'time' => 'time'
    );

    /**
     * Creates a new table in the database with the given sql query.
     * SIDE EFFECT: changes $job array and fills in the missing data
     * @param array $job object that hold information about the query
     * @param array $errors holding any error that occurs
     * @param array $options any options that a specific implementation of submitJob needs to get
     * @return int $status
     */
    public function submitJob(&$job, array &$errors, $options = false) {
        // switch to user adapter
        $this->setAdapter(Daiquiri_Config::getInstance()->getUserDbAdapter());

        // get adapter config
        $config = $this->getAdapter()->getConfig();

        // get tablename
        $table = $job['table'];

        // check if the table already exists
        if ($this->_tableExists($table)) {
            $errors['submitError'] = "Table '{$table}' already exists";
            return false;
        }

        // create the actual sql statement
        $actualQuery = $job['fullActualQuery'];
        unset($job['fullActualQuery']);

        // fire up the database
        // determining the DB adapter that is used. if we have thought about that one, use direct querying
        // without using prepared statement (not that fast and uses memory)
        // if not, fall back to prepared statements querying (using adapter->query abstractions of ZEND)

        $adaptType = get_class($this->getAdapter());

        // if query syntax is checked server side without executing query (like using paqu_validateSQL in MySQL), 
        // we just fire up the query. if not, we need to split multiline queries up and check for any exception
        // raised by the server

        if (Daiquiri_Config::getInstance()->query->validate->serverSide) {
            if (strpos(strtolower($adaptType), "pdo") !== false) {
                try {
                    $stmt = $this->getAdapter()->getConnection()->exec($actualQuery);
                } catch (Exception $e) {
                    $errors['submitError'] = $e->getMessage();
                }
            } else {
                // fallback version
                try {
                    $stmt = $this->getAdapter()->query($actualQuery);
                } catch (Exception $e) {
                    $errors['submitError'] = $e->getMessage();
                }

                $stmt->closeCursor();
            }
        } else {
            // split the query into multiple queries...
            $processing = new Query_Model_Resource_Processing();

            $multiLine = $processing->splitQueryIntoMultiline($actualQuery, $errors);

            foreach ($multiLine as $query) {
                if (strpos(strtolower($adaptType), "pdo") !== false) {
                    try {
                        $stmt = $this->getAdapter()->getConnection()->exec($query);
                    } catch (Exception $e) {
                        $errors['submitError'] = $e->getMessage();
                        break;
                    }
                } else {
                    try {
                        $stmt = $this->getAdapter()->query($query);
                    } catch (Exception $e) {
                        $errors['submitError'] = $e->getMessage();
                        break;
                    }
                }
            }

            if (strpos(strtolower($adaptType), "pdo") === false) {
                $stmt->closeCursor();
            }
        }

        // if error has been raised just report it and don't add a job
        if (!empty($errors)) {
            return Query_Model_Resource_DirectQuery::$_status['error'];
        }

        // check if it worked
        if (in_array($table, $this->getAdapter()->listTables())) {
            // set status
            $statusId = Query_Model_Resource_DirectQuery::$_status['success'];
        } else {
            $statusId = Query_Model_Resource_DirectQuery::$_status['error'];
        }

        if (!empty($options) && array_key_exists('jobId', $options)) {
            $job['id'] = "{$options['jobId']}";
        }

        $job['database'] = $config['dbname'];
        $job['user_id'] = Daiquiri_Auth::getInstance()->getCurrentId();
        $job['status_id'] = $statusId;
        $job['host'] = $config['host'];
        $job['time'] = date("Y-m-d\TH:i:s");

        // switch to web adapter
        $this->setAdapter(Daiquiri_Config::getInstance()->getWebAdapter());

        // insert job into jobs table
        $this->getAdapter()->insert('Query_Jobs', $job);

        return $statusId;
    }

    /**
     * Rename table of a job with given id.
     * @param array $id
     * @throws Exception
     * @param string $newTable new name of the job's table
     */
    public function renameJob($id, $newTable) {
        // get job from the database
        $job = $this->fetchRow($id);

        // switch to user adapter
        $this->setAdapter(Daiquiri_Config::getInstance()->getUserDbAdapter());

        // check if the table already exists
        if ($this->_tableExists($newTable)) {
            throw new Exception("Table '{$newTable}' already exists.");
        }

        // rename result table for job
        $this->_renameTable($job['database'], $job['table'], $newTable);

        // switch to web adapter
        $this->setAdapter(Daiquiri_Config::getInstance()->getWebAdapter());

        // Updating the job entry
        $this->getAdapter()->update('Query_Jobs', array('table' => $newTable), array('id = ?' => $id));
    }

    /**
     * Delete job with given id. This will also drop the associated
     * @param array $id
     */
    public function removeJob($id) {
        // get job from the database
        $job = $this->fetchRow($id);

        // switch to user adapter
        $this->setAdapter(Daiquiri_Config::getInstance()->getUserDbAdapter());

        // drop result table for job
        $this->_dropTable($job['database'], $job['table']);

        // switch to web adapter
        $this->setAdapter(Daiquiri_Config::getInstance()->getWebAdapter());

        // remove job from job table
        $this->deleteRow($id);
    }

    /**
     * Kill job with given id.
     * @param array $id
     */
    public function killJob($id) {
        // kill is not supported by this queue... thus do nothing
    }

    /**
     * Returns true if given status is killable and false, if job cannot be killed
     * @param string $status
     * @return bool
     */
    public function isStatusKillable($status) {
        return false;
    }

    /**
     * Return job status.
     * @param type $input id OR name of the job
     */
    public function fetchJobStatus($id) {
        $row = $this->fetchRow($id);
        return $row['status_id'];
    }

    /**
     * Returns the columns of the jobs table.
     * @return array $cols
     */
    public function fetchCols() {
        $cols = Query_Model_Resource_DirectQuery::$_cols;
        $cols[] = 'status';
        $cols[] = 'username';
        return $cols;
    }

    /**
     * Counts the number of rows in the jobs table.
     * Takes where conditions into account.
     * @param array $sqloptions array of sqloptions (start,limit,order,where,from)
     * @return int $count
     */
    public function countRows(array $sqloptions = null) {
        $select = $this->select();
        $select->from('Query_Jobs', 'COUNT(*) as count');

        if ($sqloptions) {
            if (isset($sqloptions['where'])) {
                foreach ($sqloptions['where'] as $w) {
                    $select = $select->where($w);
                }
            }
            if (isset($sqloptions['orWhere'])) {
                foreach ($sqloptions['orWhere'] as $w) {
                    $select = $select->orWhere($w);
                }
            }
        }

        // query database and return
        $row = $this->fetchOne($select);
        return (int) $row['count'];
    }

    /**
     * Fetches a set of rows from the jobs table specified by $sqloptions.
     * @param array $sqloptions array of sqloptions (start,limit,order,where)
     * @return array $rows
     */
    public function fetchRows(array $sqloptions = array()) {
        // get the primary sql select object
        $select = $this->select($sqloptions);
        $select->from('Query_Jobs', Query_Model_Resource_DirectQuery::$_cols);
        $select->join('Auth_User','Query_Jobs.user_id = Auth_User.id','username');

        // get the rowset and return
        $rows = $this->fetchAll($select);

        //go through the result set and replace all instances of status with string
        $statusStrings = array_flip(Query_Model_Resource_DirectQuery::$_status);
        foreach ($rows as &$row) {
            $row['status'] = $statusStrings[$row['status_id']];
        }

        return $rows;
    }

    /**
     * Fetches one row specified by its primary key from the jobs table.
     * @param array $sqloptions
     * @return array $row
     */
    public function fetchRow($id) {
        if (empty($id)) {
            throw new Exception('$id not provided in ' . get_class($this) . '::' . __FUNCTION__ . '()');
        }

        // get the primary sql select object
        $select = $this->select();
        $select->from('Query_Jobs', Query_Model_Resource_DirectQuery::$_cols);
        $select->join('Auth_User','Query_Jobs.user_id = Auth_User.id','username');
        $select->where('Query_Jobs.id = ?', $id);

        // get the rowset and return
        $row = $this->fetchOne($select);
        if (!empty($row)) {
            $statusStrings = array_flip(Query_Model_Resource_DirectQuery::$_status);
            $row['status'] = $statusStrings[$row['status_id']];
        }

        return $row;
    }

    /**
     * Returns statistical information about the database table corresponding to
     * the job id if exists.
     * @param int $id id of the job
     * @return array $stats
     */
    public function fetchTableStats($id) {
        // first obtain information about this job
        $job = $this->fetchRow($id);

        // only get statistics if the job finished
        if ($job['status'] === "success") {
            return array();
        }

        // switch to user adapter
        $this->setAdapter(Daiquiri_Config::getInstance()->getUserDbAdapter());

        // check if this table is locked and if yes, don't query information_schema. This will result in a
        // "waiting for metadata lock" and makes daiquiri hang
        if ($this->_isTableLocked($job['table'])) {
            return array();
        } else {
            // check if table is available
            if (!in_array($job['table'], $this->getAdapter()->listTables())) {
                return array();
            }

            // obtain row count
            // obtain table size in MB
            // obtain index size in MB
            // obtain free space (in table) in MB
            $sql = "SELECT round( (data_length + index_length) / 1024 / 1024, 3 ) AS 'tbl_size', " .
                    "round( index_length / 1024 / 1024, 3) AS 'tbl_idx_size', " .
                    "round( data_free / 1024 / 1024, 3 ) AS 'tbl_free', table_rows AS 'tbl_row' " .
                    "FROM information_schema.tables " .
                    "WHERE table_schema = ? AND table_name = ?;";

            return $this->getAdapter()->fetchAll($sql, array($job['database'], $job['table']));
        }
    }

    /**
     * Given a table name, check if it already exists (true) or not (false).
     * @param string $table name of the table
     * @return bool
     */
    protected function _tableExists($table) {
        $sql = "SHOW TABLES LIKE '{$table}';";

        try {
            $rows = $this->getAdapter()->query($sql)->fetchAll();
        } catch (Exception $e) {
            // check if this is error 1051 Unknown table
            if (strpos($e->getMessage(), "1051") === false) {
                throw $e;
            }
        }

        if (!empty($rows)) {
            return true;
        }

        return false;
    }

}
