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

class Query_Model_Resource_Jobs extends Daiquiri_Model_Resource_Table {

    /**
     * Array of possible query types.
     * @var array
     */
    protected static $_types = array(
        'web' => 1,
        'uws' => 2
    );

    /**
     * Constructor. Sets tablename.
     */
    public function __construct() {
        $this->setTablename('Query_Jobs');
    }

    /**
     * Fetches a set of rows specified by SQL keywords from the jobs table.
     * @param array $sqloptions array of sqloptions (start,limit,order,where)
     * @return array $rows
     */
    public function fetchRows(array $sqloptions = array()) {
        // get select object
        $select = $this->select($sqloptions);
        $select->from($this->getTablename(), array('id','database','table','time','status_id','type_id','complete'));

        // query database and return
        return $this->fetchAll($select);
    }

    /**
     * Fetches one row specified by its primary key or an array of sqloptions
     * from the jobs table.
     * @param mixed $input primary key of the row OR array of sqloptions
     * @throws Exception
     * @return array $row
     */
    public function fetchRow($input) {
        if (empty($input)) {
            throw new Exception('$id or $sqloptions not provided in ' . get_class($this) . '::' . __FUNCTION__ . '()');
        }

        $fields = array('id','database','table','time','status_id','prev_status_id','type_id','complete','user_id','query','actualQuery','nrows','size','ip');

        if (is_array($input)) {
            $select = $this->select($input);
            $select->from($this->getTablename(), $fields);
        } else {
            $select = $this->select();
            $select->from($this->getTablename(), $fields);
            $identifier = $this->quoteIdentifier($this->fetchPrimary());
            $select->where($identifier . '= ?', $input);
        }

        // get the rows an chach that its one and only one
        return $this->fetchOne($select);
    }

    /**
     * Returns the number of rows and the size of a given user database.
     * @param int $userId id of the user
     * @return array $stats
     */
    public function fetchStats($userId) {
        $select = $this->select();
        $select->from($this->getTablename(), 'SUM(nrows) as nrows,SUM(size) as size');
        $select->where('user_id = ?', $userId);
        $row = $this->fetchOne($select);

        if ($row['nrows'] === NULL) $row['nrows'] = 0;
        if ($row['size'] === null) $row['size'] = 0;

        return $row;
    }

    /**
     * Returns the type_id for a given job type.
     * @param string $type
     * @return int $type_id
     */
    public function getTypeId($type) {
        $classname = get_class($this);
        if (isset($classname::$_types[$type])) {
            return $classname::$_types[$type];
        } else {
            return false;
        }
    }

    /**
     * Returns the job type for a given type_id.
     * @param int $typeId
     * @return string $type
     */
    public function getType($typeId) {
        $classname = get_class($this);
        return array_search($typeId, $classname::$_types);
    }
}