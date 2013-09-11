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

/**
 * Class for the form which is used to update a user detail. 
 */
class Auth_Form_UpdateDetail extends Auth_Form_Abstract {

    protected $_key;
    protected $_value;

    public function setKey($key) {
        $this->_key = $key;
    }

    public function setValue($value) {
        $this->_value = $value;
    }

    /**
     * Initializes the form. 
     */
    public function init() {
        $this->setFormDecorators();
        $this->addCsrfElement();

        $this->addElement('text', 'key', array(
            'label' => 'Key:',
            'required' => true,
            'filters' => array('StringTrim'),
            'validators' => array(
                array('validator' => 'alnum'),
            )
        ));
        $this->addElement('text', 'value', array(
            'label' => 'Value:',
            'required' => true,
            'filters' => array('StringTrim'),
            'validators' => array(
                array('validator' => new Daiquiri_Form_Validator_Text()),
            )
        ));

        $this->addPrimaryButtonElement('submit', 'Submit');
        $this->addButtonElement('cancel', 'Cancel');

        // set decorators
        $this->addHorizontalGroup(array('key', 'value'));
        $this->addActionGroup(array('submit', 'cancel'));

        // set fields
        if (isset($this->_key)) {
            $this->setDefault('key', $this->_key);
            $this->setFieldReadonly('key');
        }
        if (isset($this->_value)) {
            $this->setDefault('value', $this->_value);
        }
    }

}