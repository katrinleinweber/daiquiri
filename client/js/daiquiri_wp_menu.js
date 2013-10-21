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
 * jquery plugin to insert a code mirror in a given jquery selection
 */
(function($){
    $.fn.extend({
        /**
         * Insert the wordpress navigation.
         */ 
        daiquiri_wpMenu: function(opt) {
            return this.each(function() {
                var self = $(this);
                $.ajax({
                    url: opt.url, 
                    type: 'GET',
                    dataType: 'text',
                    error: daiquiri.common.ajaxError,
                    success: function (html) {
                        $(self).addClass('dropdown');
                        $('a',self).addClass('dropdown-toggle').attr('data-toggle','dropdown');

                        // thou shalt not put a div in you nav!
                        var begin = html.indexOf('<!-- begin -->') + '<!-- begin -->'.length;
                        var end   = html.indexOf('<!-- end -->');
                        ul = html.substring(begin,end);

                        // workaround for wp page menu
                        if (ul.indexOf('<div') == 0) {
                            ul = ul.substring('<div class="menu">'.length, ul.length - '</div>'.length - 1);
                        }
                        self.append(ul);

                        $('ul:not(.sub-menu):not(.children)',self).addClass('dropdown-menu');
                        $('li',self).attr('class','nav-item').removeAttr('id');

                        // i want to apologize for this code ...
                    }
                });
            });
        },
    });
})(jQuery);