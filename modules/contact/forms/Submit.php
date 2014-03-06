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

class Contact_Form_Submit extends Daiquiri_Form_Abstract {

    protected $_categories = array();
    protected $_user = array();

    public function setCategories($categories) {
        $this->_categories = $categories;
    }

    public function setUser($user) {
        $this->_user = $user;
    }

    public function init() {
        $this->setFormDecorators();
        $this->addCsrfElement();

        // add elements
        $this->addElement('text', 'firstname', array(
            'label' => 'Your first name',
            'size' => '30',
            'required' => true,
            'filters' => array('StringTrim'),
            'validators' => array(
                array('validator' => new Daiquiri_Form_Validator_Text()),
            )
        ));
        $this->addElement('text', 'lastname', array(
            'label' => 'Your last name',
            'size' => '30',
            'required' => true,
            'filters' => array('StringTrim'),
            'validators' => array(
                array('validator' => new Daiquiri_Form_Validator_Text()),
            )
        ));
        $this->addElement('text', 'email', array(
            'label' => 'Your email address',
            'size' => '30',
            'required' => true,
            'filters' => array('StringTrim'),
            'validators' => array(
                array('validator' => 'EmailAddress'),
            )
        ));
        $this->addElement('select', 'category_id', array(
            'label' => 'Category',
            'multiOptions' => $this->_categories,
            'cols' => '30',
            'required' => true
        ));
        $this->addElement('text', 'subject', array(
            'label' => 'Subject',
            'class' => 'input-xxlarge',
            'required' => true,
            'filters' => array('StringTrim'),
            'validators' => array(
                array('validator' => 'StringLength', 'options' => array(0, 128)),
                array('validator' => new Daiquiri_Form_Validator_Text())
            )
        ));
        $this->addElement('textarea', 'message', array(
            'label' => 'Message<br/><span class="hint">(max. 2048<br/>characters)',
            'class' => 'input-xxlarge',
            'rows' => '10',
            'required' => true,
            'filters' => array('StringTrim'),
            'validators' => array(
                array('validator' => 'StringLength', 'options' => array(0, 2048)),
                array('validator' => new Daiquiri_Form_Validator_Textarea())
            )
        ));
        if (empty($this->_user)) {
            // display captcha if no user is logged in
            $this->addElement(new Daiquiri_Form_Element_Captcha('captcha'));
        }
        $this->addPrimaryButtonElement('submit', 'Send message');
        $this->addButtonElement('cancel', 'Cancel');

        // add groups
        $this->addHorizontalGroup(array('firstname', 'lastname', 'email'), 'name-group');
        $this->addHorizontalGroup(array('category_id', 'subject', 'message'), 'detail-group');
        if (empty($this->_user)) {
            $this->addCaptchaGroup('captcha');
        }
        $this->addActionGroup(array('submit', 'cancel'));

        // set fields if user is logged in.
        foreach (array('firstname', 'lastname', 'email') as $key) {
            if (isset($this->_user[$key])) {
                $this->setDefault($key, $this->_user[$key]);
                $this->setFieldReadonly($key);
            }
        }
    }

}
