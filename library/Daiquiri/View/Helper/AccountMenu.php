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

class Daiquiri_View_Helper_AccountMenu extends Zend_View_Helper_Abstract {

    public $view;

    public function setView(Zend_View_Interface $view) {
        $this->view = $view;
    }

    public function accountMenu($listOnly = true) {
        $html = '';
        if (Daiquiri_Auth::getInstance()->checkAcl('Auth_Model_User', 'edit')
                || Daiquiri_Auth::getInstance()->checkAcl('Auth_Model_Password', 'change')) {
            if ($listOnly === true) {
                $html .= '<li class="dropdown">';
                $html .= '<a class="dropdown-toggle" data-toggle="dropdown" href="#">My Account</a>';
                $html .= '<ul class = "dropdown-menu">';
            }
            $html .= $this->view->internalLink(array(
                'href' => '/auth/account/update?redirect=' . $this->view->path(),
                'text' => 'Update Profile',
                'resource' => 'Auth_Model_Account',
                'permission' => 'update',
                'prepend' => '<li class="nav-item">',
                'append' => '</li>'));
            $html .= $this->view->internalLink(array(
                'href' => '/auth/password/change?redirect=' . $this->view->path(),
                'text' => 'Change Password',
                'resource' => 'Auth_Model_Password',
                'permission' => 'change',
                'prepend' => '<li class="nav-item">',
                'append' => '</li>'));
            if ($listOnly === true) {
                $html .= '</ul></li>';
            }
        }
            
        return $html;
    }

}
