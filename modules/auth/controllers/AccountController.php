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

class Auth_AccountController extends Daiquiri_Controller_Abstract {

    private $_model;

    /**
     * Inititalizes the controller.
     */
    public function init() {
        $this->_model = Daiquiri_Proxy::factory('Auth_Model_Account');
    }

    /**
     * Shows the credentials of the user who is currently logged in
     */
    public function showAction() {
        // get params from request
        $redirect = $this->_getParam('redirect', '/');

        // call model method
        $this->view->redirect = $redirect;
        $this->view->data = $this->_model->show();
        $this->view->status = 'ok';
    }

    /**
     * Updates the user which is currently logged in.
     * Uses different form as Auth_UserContoller::updateAction (without status and role).
     */
    public function updateAction() {
        // get redirect url
        $redirect = $this->_getParam('redirect', '/');

        // check if POST or GET
        if ($this->_request->isPost()) {
            if ($this->_getParam('cancel')) {
                // user clicked cancel
                $this->_redirect($redirect);
            } else {
                // validate form and edit user
                $response = $this->_model->update($this->_request->getPost());
            }
        } else {
            // just display the form
            $response = $this->_model->update();
        }

        // set action for form
        if (array_key_exists('form',$response)) {
            $form = $response['form'];
            $form->setAction(Daiquiri_Config::getInstance()->getBaseUrl() . '/auth/account/update');
        }

        // assign to view
        $this->view->redirect = $redirect;
        foreach ($response as $key => $value) {
            $this->view->$key = $value;
        }
    }
}