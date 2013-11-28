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

class Auth_PasswordController extends Daiquiri_Controller_Abstract {

    protected $_model;

    public function init() {
        $this->_model = Daiquiri_Proxy::factory('Auth_Model_Password');
    }

    /**
     * Shows one of the (hashed) passwords for a given user.
     */
    public function showAction() {
        // get params from request
        $id = $this->_getParam('id');
        $type = $this->_getParam('type', 'default');

        // call model method
        $this->view->data = $this->_model->show($id, $type);
        $this->view->status = 'ok';
    }

    public function changeAction() {
        // get redirect url
        $redirect = $this->_getParam('redirect', '/');

        // check if POST or GET
        if ($this->_request->isPost()) {
            if ($this->_getParam('cancel')) {
                // user clicked cancel
                $this->_redirect($redirect);
            } else {
                // validate form and login
                $response = $this->_model->change($this->_request->getPost());
            }
        } else {
            // just display the form
            $response = $this->_model->change();
        }

        // set action for form
        if (array_key_exists('form',$response)) {
            $form = $response['form'];
            $form->setAction(Daiquiri_Config::getInstance()->getBaseUrl() . '/auth/password/change/?redirect=' . $redirect);
        }

        // assign to view
        $this->view->redirect = $redirect;
        foreach ($response as $key => $value) {
            $this->view->$key = $value;
        }
    }

    public function forgotAction() {
        // check if POST or GET
        if ($this->_request->isPost()) {
            if ($this->_getParam('cancel')) {
                // user clicked cancel
                $this->_redirect('/auth/login');
            } else {
                // validate form and login
                $response = $this->_model->forgot($this->_request->getPost());
            }
        } else {
            // just display the form
            $response = $this->_model->forgot();
        }

        // assign to view        
        foreach ($response as $key => $value) {
            $this->view->$key = $value;
        }
    }

    public function resetAction() {
        // get the id and teh code
        $id = $this->_getParam('id');
        $code = $this->_getParam('code');

        // check if POST or GET
        if ($this->_request->isPost()) {
            if ($this->_getParam('cancel')) {
                // user clicked cancel
                $this->_helper->redirector('index', 'user', 'admin');
            } else {
                // validate form and login
                $response = $this->_model->reset($id, $code, $this->_request->getPost());
            }
        } else {
            // just display the form
            $response = $this->_model->reset($id, $code);
        }

        // assign to view        
        foreach ($response as $key => $value) {
            $this->view->$key = $value;
        }
    }

    /**
     * Reset the password of a user in the database to a provided value.
     */
    public function setAction() {
        // get the id of the user to be edited
        $id = $this->_getParam('id');

        // get the password model
        $model = Daiquiri_Proxy::factory('Auth_Model_Password');

        // check if POST or GET
        if ($this->_request->isPost()) {
            if ($this->_getParam('cancel')) {
                // user clicked cancel
                $this->_helper->redirector('index', 'user', 'auth');
            } else {
                // validate form and change password
                $response = $model->set($id, $this->_request->getPost());
            }
        } else {
            // just display the form
            $response = $model->set($id);
        }

        if (array_key_exists('form',$response)) {
            $form = $response['form'];
            $form->setAction(Daiquiri_Config::getInstance()->getBaseUrl() . '/auth/password/set/id/' . $id);
        }

        // assign to view        
        foreach ($response as $key => $value) {
            $this->view->$key = $value;
        }
    }

}
