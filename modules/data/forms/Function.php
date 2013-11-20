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

class Data_Form_Function extends Daiquiri_Form_Abstract {

    protected $_roles = array();
    protected $_entry = array();
    protected $_submit = null;

    public function setRoles($roles) {
        $this->_roles = $roles;
    }

    public function setEntry($entry) {
        $this->_entry = $entry;
    }

    public function setSubmit($submit) {
        $this->_submit = $submit;
    }

    public function init() {
        $this->setFormDecorators();
        $this->addCsrfElement();
        
        // add elements
        $this->addElement('text', 'name', array(
            'label' => 'Function name:',
            'required' => true,
            'filters' => array('StringTrim'),
            'validators' => array(
                array('validator' => new Daiquiri_Form_Validator_Sql()),
            )
        ));
        $this->addElement('textarea', 'description', array(
            'label' => 'Function description',
            'rows' => '4',
            'required' => false,
            'filters' => array('StringTrim'),
            'validators' => array(
                array('validator' => new Daiquiri_Form_Validator_Textarea()),
            )
        ));
        $this->addElement('select', 'publication_role_id', array(
            'label' => 'Published for: ',
            'required' => true,
            'multiOptions' => $this->_roles,
        ));

        $this->addPrimaryButtonElement('submit', $this->_submit);
        $this->addButtonElement('cancel', 'Cancel');

        // add groups
        $this->addHorizontalGroup(array('name', 'description', 'publication_role_id'));
        $this->addActionGroup(array('submit', 'cancel'));

        // set fields
        foreach (array('name', 'description', 'publication_role_id') as $element) {
            if (isset($this->_entry[$element])) {
                $this->setDefault($element, $this->_entry[$element]);
            }
        }
    }

}
