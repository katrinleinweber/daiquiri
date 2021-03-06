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

class Data_Model_Init extends Daiquiri_Model_Init {

    /**
     * Returns the acl resources for the data module.
     * @return array $resources
     */
    public function getResources() {
        return array(
            'Data_Model_Viewer',
            'Data_Model_Files',
            'Data_Model_Functions',
            'Data_Model_Databases',
            'Data_Model_Static',
            'Data_Model_Tables',
            'Data_Model_Columns'
        );
    }

    /**
     * Returns the acl rules for the data module.
     * @return array $rules
     */
    public function getRules() {
        return array(
            'guest' => array(
                'Data_Model_Viewer' => array('rows','cols','plot'),
                'Data_Model_Static'=>  array('file'),
                'Data_Model_Files' => array('index','single','singleSize','multi','multiSize','row','rowSize')
            ),
            'admin' => array(
                'Data_Model_Databases' => array('index','create','show','update','delete','export'),
                'Data_Model_Tables' => array('create','show','update','delete','export'),
                'Data_Model_Columns' => array('create','show','update','delete','export'),
                'Data_Model_Functions' => array('index','create','show','update','delete','export'),
                'Data_Model_Static' => array('index','create','show','update','delete','export')
            )
        );
    }

    /**
     * Processes the 'data' part of $options['config'].
     */
    public function processConfig() {
        if (!isset($this->_init->input['config']['data'])) {
            $input = array();
        } else if (!is_array($this->_init->input['config']['data'])) {
            $this->_error('Auth config options needs to be an array.');
        } else {
            $input = $this->_init->input['config']['data'];
        }

        // create default entries
        $defaults = array(
            'writeToDB' => 0,
            'viewer' => array(
                'removeNewline' => false,
                'columnWidth' => 100
            )
        );

        // create config array
        $output = array();
        $this->_buildConfig_r($input, $output, $defaults);

        // set options
        $this->_init->options['config']['data'] = $output;
    }

    /**
     * Processes the 'data' part of $options['init'].
     */
    public function processInit() {
        if (!isset($this->_init->input['init']['data'])) {
            $input = array();
        } else if (!is_array($this->_init->input['init']['data'])) {
            $this->_error('Data options needs to be an array.');
        } else {
            $input = $this->_init->input['init']['data'];
        }

        // just pass through
        $this->_init->options['init']['data'] = $input;
    }

    /**
     * Initializes the database with the init data for the data module.
     */
    public function init() {
        // create database entries in the data module
        if (isset($this->_init->options['init']['data']['databases'])
            && is_array($this->_init->options['init']['data']['databases'])) {
            $dataDatabasesModel = new Data_Model_Databases();
            if ($dataDatabasesModel->getResource()->countRows() == 0) {
                echo '    Initialising Data_Databases' . PHP_EOL;
                foreach ($this->_init->options['init']['data']['databases'] as $a) {
                    echo '        Generating metadata for database: ' . $a['name'] . PHP_EOL;

                    $a['publication_role_id'] = Daiquiri_Auth::getInstance()->getRoleId($a['publication_role']);
                    unset($a['publication_role']);

                    try {
                        $r = $dataDatabasesModel->create($a);
                    } catch (Exception $e) {
                        $this->_error("Error in creating database metadata:\n" . $e->getMessage());
                    }
                    $this->_check($r, $a);
                }
            }


            // create table entries in the data module
            if (isset($this->_init->options['init']['data']['tables'])
                && is_array($this->_init->options['init']['data']['tables'])) {
                // get the ids of the databases
                $database_ids = array_flip($dataDatabasesModel->getResource()->fetchValues('name'));

                $dataTablesModel = new Data_Model_Tables();
                if ($dataTablesModel->getResource()->countRows() == 0) {
                    echo '    Initialising Data_Tables' . PHP_EOL;
                    foreach ($this->_init->options['init']['data']['tables'] as $a) {
                        echo '        Generating metadata for table: ' . $a['name'] . PHP_EOL;

                        $a['database_id'] = $database_ids[$a['database']];
                        unset($a['database']);

                        $a['publication_role_id'] = Daiquiri_Auth::getInstance()->getRoleId($a['publication_role']);
                        unset($a['publication_role']);

                        try {
                            $r = $dataTablesModel->create(null, $a);
                        } catch (Exception $e) {
                            $this->_error("Error in creating tables metadata:\n" . $e->getMessage());
                        }
                        $this->_check($r, $a);
                    }
                }

                // create column entries in the data module
                if (isset($this->_init->options['init']['data']['columns'])
                    && is_array($this->_init->options['init']['data']['columns'])) {
                    // get the ids of the databases
                    $table_ids = array_flip($dataTablesModel->getResource()->fetchValues('name'));

                    $dataColumnsModel = new Data_Model_Columns();
                    if ($dataColumnsModel->getResource()->countRows() == 0) {
                        echo '    Initialising Data_Columns' . PHP_EOL;
                        foreach ($this->_init->options['init']['data']['columns'] as $a) {

                            $a['table_id'] = $table_ids[$a['table']];
                            unset($a['table']);

                            // $a['publication_role_id'] = Daiquiri_Auth::getInstance()->getRoleId($a['publication_role']);
                            // unset($a['publication_role']);

                            try {
                                $r = $dataColumnsModel->create(null, $a);
                            } catch (Exception $e) {
                                $this->_error("Error in creating columns metadata:\n" . $e->getMessage());
                            }
                            $this->_check($r, $a);
                        }
                    }
                }
            }
        }

        // // create function entries in the tables module
        if (isset($this->_init->options['init']['data']['functions'])
            && is_array($this->_init->options['init']['data']['functions'])) {
            $dataFunctionsModel = new Data_Model_Functions();
            if ($dataFunctionsModel->getResource()->countRows() == 0) {
                echo '    Initialising Data_Functions' . PHP_EOL;
                foreach ($this->_init->options['init']['data']['functions'] as $a) {

                    $a['publication_role_id'] = Daiquiri_Auth::getInstance()->getRoleId($a['publication_role']);
                    unset($a['publication_role']);

                    try {
                        $r = $dataFunctionsModel->create($a);
                    } catch (Exception $e) {
                        $this->_error("Error in creating function metadata:\n" . $e->getMessage());
                    }
                    $this->_check($r, $a);
                }
            }
        }

        // create function entries in the tables module
        if (isset($this->_init->options['init']['data']['static'])
            && is_array($this->_init->options['init']['data']['static'])) {
            $dataStaticModel = new Data_Model_Static();
            if ($dataStaticModel->getResource()->countRows() == 0) {
                echo '    Initialising Data_Static' . PHP_EOL;
                foreach ($this->_init->options['init']['data']['static'] as $a) {

                    $a['publication_role_id'] = Daiquiri_Auth::getInstance()->getRoleId($a['publication_role']);
                    unset($a['publication_role']);

                    try {
                        $r = $dataStaticModel->create($a);
                    } catch (Exception $e) {
                        $this->_error("Error in creating function metadata:\n" . $e->getMessage());
                    }
                    $this->_check($r, $a);
                }
            }
        }
    }
}
