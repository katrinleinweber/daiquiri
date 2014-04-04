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

class Query_AccountController extends Daiquiri_Controller_Abstract {

    protected $_model;

    public function init() {
        $this->_model = Daiquiri_Proxy::factory('Query_Model_Account');
    }

    public function listJobsAction() {
        $response = $this->_model->listJobs();
        $this->view->assign($response);
    }

    public function showJobAction() {
        $id = $this->_getParam('id');
        $response = $this->_model->showJob($id);
        $this->view->assign($response);
    }

    public function renameJobAction() {
        $id = $this->_getParam('id');
        $this->getControllerHelper('form')->renameJob($id);
    }

    public function removeJobAction() {
        $id = $this->_getParam('id');
        $this->getControllerHelper('form')->removeJob($id);
    }

    public function killJobAction() {
        $id = $this->_getParam('id');
        $this->getControllerHelper('form')->killJob($id);
    }

    public function databasesAction() {
        $response = $this->_model->databases();
        $this->view->assign($response);
    }

    public function examplesAction() {
        $response = $this->_model->examples();
        $this->view->assign($response);
    }

    public function functionsAction() {
        $response = $this->_model->functions();
        $this->view->assign($response);
    }

}
