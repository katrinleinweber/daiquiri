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

class Meetings_Model_Init extends Daiquiri_Model_Init {

    public function parseOptions(array $options) {
        if (!isset($this->_input_options['meetings'])) {
            $input = array();
        } else if (!is_array($this->_input_options['meetings'])) {
            $this->_error('Meetings options need to be an array.');
        } else {
            $input = $this->_input_options['meetings'];
        }

        $defaults = array(
            'contributionTypes' => array('Poster','Talk'),
            'participantDetailKeys' => array(),
            'participantStatus' => array('organizer', 'invited', 'registered', 'accepted', 'rejected'),
            'meetings' => array()
        );
        $output = array();

        if (!empty($options['config']['meetings'])) {
            foreach ($defaults as $key => $value) {
                if (array_key_exists($key, $input)) {
                    if (is_array($input[$key])) {
                        $output[$key] = $input[$key];
                    } else {
                        $this->_error("Contact option 'contact.$key' needs to be an array.");
                    }
                } else {
                    $output[$key] = $value;
                }
            }
        }

        $options['meetings'] = $output;
        return $options;
    }

    public function init(array $options) {
        if ($options['config']['meetings'] && !empty($options['meetings'])) {
            // create contribution types
            $meetingsContributionTypeModel = new Meetings_Model_ContributionTypes();
            if (!empty($options['meetings']['contributionTypes'])) {
                $response = $meetingsContributionTypeModel->index();
                if (empty($response['data'])) {
                    foreach ($options['meetings']['contributionTypes'] as $contributionType) {
                        $a = array('contribution_type' => $contributionType);
                        $r = $meetingsContributionTypeModel->create($a);
                        $this->_check($r, $a);
                    }
                }
            }

            // create participant detail keys
            $meetingsParticipantDetailKeyModel = new Meetings_Model_ParticipantDetailKeys();
            if (!empty($options['meetings']['participantDetailKeys'])) {
                $response = $meetingsParticipantDetailKeyModel->index();
                if (empty($response['data'])) {
                    foreach ($options['meetings']['participantDetailKeys'] as $participantDetailKey) {
                        $a = array('key' => $participantDetailKey);
                        $r = $meetingsParticipantDetailKeyModel->create($a);
                        $this->_check($r, $a);
                    }
                }
            }

            // create participant status
            $meetingsParticipantStatusModel = new Meetings_Model_ParticipantStatus();
            if (!empty($options['meetings']['participantStatus'])) {
                $response = $meetingsParticipantStatusModel->index();
                if (empty($response['data'])) {
                    foreach ($options['meetings']['participantStatus'] as $participantStatus) {
                        $a = array('status' => $participantStatus);
                        $r = $meetingsParticipantStatusModel->create($a);
                        $this->_check($r, $a);
                    }
                }
            }

            // create meetings
            $meetingsMeetingModel = new Meetings_Model_Meetings();
            if (!empty($options['meetings']['meetings'])) {
                $response = $meetingsMeetingModel->index();
                if (empty($response['data'])) {
                    foreach ($options['meetings']['meetings'] as $a) {
                        $a['contribution_type_id'] = array();
                        foreach($a['contribution_types'] as $contribution_type) {
                            $id = $meetingsContributionTypeModel->getResource()->fetchId(
                                array('where' => array('`contribution_type` = ?' => $contribution_type))
                            );
                            $a['contribution_type_id'][] = $id;
                        }
                        unset($a['contribution_types']);

                        $a['participant_detail_key_id'] = array();
                        foreach($a['participant_detail_keys'] as $participant_detail_key) {
                            $id = $meetingsParticipantDetailKeyModel->getResource()->fetchId(
                                array('where' => array('`key` = ?' => $participant_detail_key))
                            );
                            $a['participant_detail_key_id'][] = $id;
                        }
                        unset($a['participant_detail_keys']);

                        $r = $meetingsMeetingModel->create($a);
                        $this->_check($r, $a);
                    }
                }
            }       
        }
    }

}

