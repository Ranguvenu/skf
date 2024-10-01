// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Standard Report wrapper for Moodle. It calls the central JS file for Report plugin,
 * Also it includes JS libraries like Select2,Datatables and Highcharts
 * @module     block_learnerscript/track
 * @class      report
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @since      3.3
 */
define(['jquery', 'block_learnerscript/timeme'], function($, TimeMe) {
    document.cookie = "time_timeme = 0 ;path=/";
    console.log('dsssssssssssssssssssssssssssssssshd');
    var trackModule2 = {
        timeme: function() {
            TimeMe.initialize({
                currentPageName: "", // Current page.
                idleTimeoutInSeconds: 10, // Stop recording time due to inactivity.
            });
            setInterval(function() {
                var timeSpentOnPage = TimeMe.getTimeOnCurrentPageInSeconds();
                document.cookie = "time_timeme =" + timeSpentOnPage + ";path=/";
            }, 500);
            document.cookie = "time_timeme = 0 ;path=/;expires=Thu, 01 Jan 1970 00:00:01 GMT;";
        }
    };
    return trackModule2;
});
