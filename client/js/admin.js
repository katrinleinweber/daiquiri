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

var app = angular.module('admin',['table','modal','ngSanitize']);

app.config(['$httpProvider', function($httpProvider) {
    $httpProvider.defaults.headers.common['Accept'] = 'application/json';
    $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded';
}]);

app.factory('AdminService', ['$http','$window','TableService','ModalService',function($http,$window,TableService,ModalService) {

    var values = {};
    var errors = {};
    var activeUrl = null;

    // check if we have a daiquiri table or not
    var table = Boolean(angular.element('[daiquiri-table]').length);

    // enable links in daiquiri table
    if (table) {
        TableService.callback.rows = function(scope) {
            angular.element('.daiquiri-admin-option').on('click', scope.fetchHtml);
        }
    }

    return {
        values: values,
        errors: errors,
        fetchHtml: function (url) {
            $http.get(url,{'headers': {'Accept': 'application/html'}})
                .success(function(html) {
                    for (var value in values) delete values[value];
                    for (var error in errors) delete errors[error];

                    if (ModalService.modal.html != html) {
                        ModalService.modal.html = html;
                    }

                    activeUrl = url;
                    ModalService.open();
                })
                .error(function() {
                    ModalService.modal.html = '<div><h2>Error</h2><p style="margin-bottom: 15px">Please reload the page.</p></div>';
                    ModalService.open();
                });
        },
        submitForm: function(submit) {
            if (submit) {
                var data = {
                    'csrf': angular.element('#csrf').attr('value')
                };

                // merge with form values and take arrays into account
                angular.forEach(values, function(value, key) {
                    if (angular.isObject(value)) {
                        data[key] = [];

                        if (angular.isArray(value)) {
                            // this is an array coming from a multiselect
                            angular.forEach(value, function(v,k) {
                                data[key].push(v);
                            });
                        } else {
                            // this is an object coming from a set of checkboxes
                            angular.forEach(value, function(v,k) {
                                if (v === true) {
                                    // for value from a set of checkboxes use the key
                                    data[key].push(k);
                                }
                            });
                        }
                    } else {
                        data[key] = value;
                    }
                });

                // fire up post request
                $http.post(activeUrl,$.param(data)).success(function(response) {
                    for (var error in errors) delete errors[error];

                    if (response.status === 'ok') {
                        ModalService.close();

                        if (table) {
                            TableService.fetchRows();
                        } else {
                            $window.location.reload();
                        }
                    } else if (response.status === 'error') {
                        angular.forEach(response.errors, function(error, key) {
                            errors[key] = error;
                        });
                    } else {
                        errors['form'] = {'form': 'Error: Unknown response from server.'};
                    }
                }).error(function(response,status) {
                    errors['form'] = {'form': 'Error: The server responded with status ' + status +  '.'};
                    console.log(response);
                });
            } else {
                ModalService.close();
            }
        },
        search: function(string) {
            TableService.search(string);
        }
    };
}]);

app.controller('AdminController', ['$scope','AdminService',function($scope,AdminService) {

    $scope.values = AdminService.values;
    $scope.errors = AdminService.errors;

    $scope.fetchHtml = function(event) {
        AdminService.fetchHtml(event.target.href);
        event.preventDefault();
    };

    $scope.submitForm = function() {
        AdminService.submitForm($scope.submit);
    };

    $scope.search = function(string) {
        AdminService.search(string);
    }
}]);