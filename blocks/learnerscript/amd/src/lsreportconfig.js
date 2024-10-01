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
 * Configured the reports on dashboard when we install plugin.
 *
 * @module     block_learnerscript/lsreportconfig
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
    'core/ajax',
    'core/str',
    'jqueryui'
], function($, Ajax, Str) {
    var lsreportconfig = {
        slideIndex: 1,
        currentSlide: function(currentSlideIndex) {
            lsreportconfig.lsreportslideshow(lsreportconfig.slideIndex = currentSlideIndex);
        },

        progressbar: $("#progressbar"),

        lsreportslideshow: function(currentSlideIndex) {
            var i;
            var slides = document.getElementsByClassName("mySlides");
            if (currentSlideIndex > slides.length) {
                lsreportconfig.slideIndex = 1;
            }
            if (currentSlideIndex < 1) {
                lsreportconfig.slideIndex = slides.length;
            }
            for (i = 0; i < slides.length; i++) {
                slides[i].style.display = "none";
            }
            slides[lsreportconfig.slideIndex - 1].style.display = "block";
            setTimeout(function(){
                if (lsreportconfig.slideIndex >= slides.length) {
                    lsreportconfig.currentSlide(1);
                } else {
                    lsreportconfig.lsreportslideshow(lsreportconfig.slideIndex++) ;
                }
            }, 3000);
        },
        lsconfigimportprogress: function(args){
            var total = args.total;
            var current = args.current;
            var errorreportspositiondata = args.errorreportspositiondata;
            var lastreportposition = args.lastreportposition;
            var promise = Ajax.call([{
                methodname: 'block_learnerscript_importreports',
                    args: {
                        total: total,
                        current: current,
                        errorreportspositiondata: errorreportspositiondata,
                        lastreportposition: lastreportposition
                    },
                }], false);
            promise[0].done(function(response){
                var resp = $.parseJSON(response);
                lsreportconfig.progressbar.progressbar("value", resp.percent);
                if (resp.percent < 100) {
                    if (resp.current && resp.current > 0) {
                        args.current = resp.current;
                    } else {
                        args.current = args.current + 1;
                    }
                    setTimeout(function(){
                        lsreportconfig.lsconfigimportprogress(args);
                    }, 500);
                }
            });
        },
        lsconfigresetprogress: function(step) {
            var promise = Ajax.call([{
                methodname: 'block_learnerscript_resetlsconfig',
                    args: {
                        step : step
                    },
                }], false);
            promise[0].done(function(response){
                var resp = $.parseJSON(response);
                lsreportconfig.progressbar.progressbar("value", resp.percent);
                if (resp.percent == 100) {
                    window.location.href = M.cfg.wwwroot + '/blocks/learnerscript/lsconfig.php?import=1';
                }
                if (resp.percent < 100) {
                    if (resp.next && resp.next > 0) {
                        step = resp.next;
                    }
                    setTimeout(function(){
                        lsreportconfig.lsconfigresetprogress(step);
                    }, 500);
                }
            });
        },
        lsreportconfigimport: function (){
            var promise = Ajax.call([{
                methodname: 'block_learnerscript_lsreportconfigimport',
                    args: {
                    },
                }]);
            promise[0].done(function(){
                Str.get_string('messagehandler', 'block_learnerscript').then(function(s) {
                    return s;
                });
            });
        }
    };
    return {
        init: function(args, status) {
            lsreportconfig.progressbar.progressbar({
                    value: false,
                    change: function() {
                    },
                    complete: function() {
                        $('#reportdashboardnav').show(500);
                    }
                });
            if ($('.mySlides').length > 0) {
                lsreportconfig.lsreportslideshow(1);
            }
            if (status == 'import') {
                lsreportconfig.lsconfigimportprogress(args);
            } else if (status == 'reset') {
                lsreportconfig.lsconfigresetprogress(1);
            }
            window.onbeforeunload = lsreportconfig.lsreportconfigimport;
        },
    };
});
