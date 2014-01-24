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

class Meetings_RegistrationController extends Daiquiri_Controller_Abstract {

    public function init() {
        parent::init();
        $this->_model = Daiquiri_Proxy::factory('Meetings_Model_Registration');
    }

    public function indexAction() {
        $response = $this->_model->index();

        // assign to view
        $this->setViewElements($response);
    }

    public function registerAction() {
        // get params
        $redirect = $this->_getParam('redirect', '/');
        $meetingId = $this->_getParam('meetingId');
        if ($meetingId === null) {
            $response = array('status' => 'error', 'errors' => array('The MeetingId is not specified.'));
        } else {
            // check if POST or GET
            if ($this->_request->isPost()) {
                if ($this->_getParam('cancel')) {
                    // user clicked cancel
                    $this->_redirect($redirect);
                } else {
                    // validate form and do stuff
                    $response = $this->_model->register($meetingId, $this->_request->getPost());
                }
            } else {
                // just display the form
                $response = $this->_model->register($meetingId);
            }

            // set action for form
            $this->setFormAction($response, '/meetings/registration/register?meetingId=' . $meetingId);
        }

        // assign to view
        $this->setViewElements($response, $redirect);
    }

    public function validateAction() {
        // get params from request
        $id = $this->_getParam('id');
        $code = $this->_getParam('code');

        $response = $this->_model->validate($id, $code);

        // assign to view
        $this->setViewElements($response);
    }
}
