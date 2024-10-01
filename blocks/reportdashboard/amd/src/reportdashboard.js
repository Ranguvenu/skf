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
 * Describe the module reportdashboard information
 *
 * @module     block_reportdashboard/reportdashboard
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
        'core/ajax',
        'block_learnerscript/report',
        'block_learnerscript/reportwidget',
        'block_learnerscript/schedule',
        'block_learnerscript/helper',
        'block_learnerscript/ajax',
        'block_learnerscript/select2/select2',
        'block_learnerscript/datatables/jquery.dataTables',
        'block_learnerscript/smartfilter',
        'block_learnerscript/flatpickr',
        'core/str',
        'jqueryui',
        'block_learnerscript/bootstrapnotify/bootstrapnotify',
    ],
    function($, Ajax, report, reportwidget, schedule, helper, ajax, select2, dataTable,
        smartfilter, flatpickr, Str) {
        return {
            init: function() {
                    $(document).ajaxStop(function() {
                         $(".loader").fadeOut("slow");
                    });
                    helper.Select2Ajax({});
                    $(".dashboardcourses").change(function() {
                        var courseid = $(this).val();
                        $(".report_courses").val(courseid);
                        reportwidget.DashboardTiles();
                        reportwidget.DashboardWidgets();
                        $(".viewmore").each(function() {
                            $(this).attr('href');
                        });
                    });
                    
                    $( "#createdashbaord_form" ).submit(function( event ) {
                        var dashboardname = $( "#id_dashboard" ).val();
                        var name = dashboardname.trim();
                        if(name === '' || name === null){
                            $( "#id_error_dashboard" ).css('display', 'block');
                            $( "#id_error_dashboard_nospaces" ).css('display', 'none');
                            event.preventDefault();
                        }
                        var spaceexist = name.indexOf(" ");
                        if(spaceexist > 0 && spaceexist != ''){
                            $( "#id_error_dashboard" ).css('display', 'none');
                            $( "#id_error_dashboard_nospaces" ).css('display', 'block');
                            event.preventDefault();
                        }
                    });

                $.ui.dialog.prototype._focusTabbable = $.noop;
                $(".sidenav").offset({top: 0});
                /**
                 * Select2 Options
                 */
                $("select[data-select2='1']").select2();
                helper.Select2Ajax({
                    action: 'reportlist',
                    multiple: true
                });

                /**
                 * Add widget to dashboard
                 */
                $(document).on('click', ".addwidgettodashboard", function() {
                    require(['block_reportdashboard/reportdashboard'], function(reportdashboard) {
                        reportdashboard.addblocks_to_dashboard();
                    });
                });

                /**
                 * Add tiles to dashboard
                 */
                $(document).on('click', ".addtilestodashboard", function() {
                    require(['block_reportdashboard/reportdashboard'], function(reportdashboard) {
                        reportdashboard.addtiles_to_dashboard();
                    });
                });

                /**
                 * Update dashboard title
                 */
                $(document).on('click', ".updatedashboardtitle", function() {
                    var name = $(this).data("name");
                    var dashboardrole = $(this).data("dashboardrole");
                    require(['block_reportdashboard/reportdashboard'], function(reportdashboard) {
                        reportdashboard.updatedashboard(name, dashboardrole);
                    });
                });

                /**
                 * Delete dashboard
                 */
                $(document).on('click', ".deletedashboard", function() {
                    var instance = $(this).data("instance");
                    var random = $(this).data("random");
                    require(['block_reportdashboard/reportdashboard'], function(reportdashboard) {
                        reportdashboard.Deletedashboard({instance: instance, random: random});
                    });
                });

                /**
                 * Delete popup
                 */
                $(document).on('click', "#nobutton", function(e) {
                    var random = $(this).data("random");
                    require(['jquery', 'jqueryui'], function($) {
                        $('#dashboard_delete_popup_'+random).dialog('close');
                    });
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                });

                /**
                 * Delete design dialog
                 */
                $(document).on('click', "#designdeletecanceldialog", function(e) {
                    var reportinstance = $(this).data("reportinstance");
                    require(['jquery', 'jqueryui'], function($) {
                        $('#delete_dialog'+reportinstance).dialog('close');
                    });
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                });

                /**
                 * Delete widget
                 */
                $(document).on('click', "#roledeleteconfirm", function(e) {
                    var reportid = $(this).data("reportid");
                    var instanceid = $(this).data("instanceid");
                    require(['block_reportdashboard/reportdashboard'], function(reportdashboard) {
                        reportdashboard.DeleteWidget({reportid: reportid, instanceid: instanceid});
                    });
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                });

                /**
                 * Add new dashboard
                 */
                $(document).on('click', ".addnewdashboard", function() {
                    require(['block_reportdashboard/reportdashboard'], function(reportdashboard) {
                        reportdashboard.addnewdashboard();
                    });
                });

                /**
                 * Custom report report help text
                 */
                $(document).on('click', ".reportshelptext", function(e) {
                    var reportid = $(this).data('reportid');
                    report.block_statistics_help(reportid);
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                });

                $(document).on('change', ".schuserroleslist", function() {
                    var reportid = $(this).data('reportid');
                    var reportinstance = $(this).data('reportinstance');
                    schedule.rolewiseusers({reportid: reportid, reportinstance: reportinstance});
                });

                /**
                 * Add schedule form users for widget
                 */

                $(document).on('change', ".schusers_data", function() {
                    var reportid = $(this).data('reportid');
                    var reportinstance = $(this).data('reportinstance');
                    schedule.addschusers({reportid: reportid, reportinstance: reportinstance});
                });

                $(document).on('change', "select[name='frequency']", function() {
                    var reportid = $(this).data('reportid');
                    var reportinstance = $(this).data('reportinstance');
                    schedule.frequency_schedule({reportid: reportid, reportinstance: reportinstance});
                });

                /**
                 * Menu option for report widget on dashboard
                 */

            $(document).on('click', ".report_schedule", function(e) {
                var reportid = $(this).data('reportid');
                var instanceid = $(this).data('instanceid');
                var method = $(this).data('method');
                require(['block_reportdashboard/reportdashboard'], function(reportdashboard) {
                    if (typeof reportdashboard[method] === 'function') {
                        reportdashboard[method]({
                            reportid: reportid,
                            instanceid: instanceid
                        });
                    }
                });
                e.preventDefault();
                e.stopImmediatePropagation();
                e.stopPropagation();
                $('.menupopupdropdown').dropdown('hide');
            });

               /**
                * Filter area
                */
                $(document).on('click', ".filterform #id_filter_clear", function() {
                    $(this).parents('.mform').trigger("reset");
                    var activityelement = $(this).parents('.mform').find('#id_filter_activities');
                    var instancelement = $(this).parents('.block_reportdashboard').find('.report_dashboard_container');
                    var reportid = instancelement.data('reportid');
                    var reporttype = instancelement.data('reporttype');
                    var instanceid = instancelement.data('blockinstance');
                    smartfilter.CourseActivities({courseid: 0, element: activityelement});
                    $(".filterform select[data-select2-ajax='1']").val('0').trigger('change');
                    $('.filterform')[0].reset();
                    $(".filterform #id_filter_clear").attr('disabled', 'disabled');
                    reportwidget.CreateDashboardwidget({reportid: reportid, reporttype: reporttype, instanceid: instanceid});
                });
                $(document).on('change', "select[name='filter_coursecategories']", function() {
                    var categoryid = this.value;
                    var courseelement = $(this).closest('.mform').find('#id_filter_courses');
                    if (courseelement.length != 0) {
                        smartfilter.categoryCourses({categoryid: categoryid, element: courseelement});
                    }
                });
                $(document).on('change', "select[name='filter_courses']", function() {
                    var courseid = this.value;
                    var activityelement = $(this).closest('.mform').find('#id_filter_activities');
                    if (activityelement.length != 0) {
                        smartfilter.CourseActivities({courseid: courseid, element: activityelement});
                    }
                });
                /**
                 * Duration Filter
                 */
                flatpickr('#customrange', {
                    mode: 'range',
                    onOpen: function(selectedDates, dateStr, instance) {
                        instance.clear();
                    },
                    onClose: function(selectedDates) {
                        $('#lsfstartdate').val(selectedDates[0].getTime() / 1000);
                        $('#lsfenddate').val((selectedDates[1].getTime() / 1000) + (60 * 60 * 24));
                        require(['block_learnerscript/reportwidget'], function() {
                            reportwidget.DashboardTiles();
                            reportwidget.DashboardWidgets();
                        });
                    }
                });
                /**
                 * Escape dropdown on click of window
                 * @param {object} event
                 */
                window.onclick = function(event) {
                    if (!event.target.matches('.dropbtn')) {
                        var dropdowns = document.getElementsByClassName("dropdown-content");
                        var i;
                        for (i = 0; i < dropdowns.length; i++) {
                            var openDropdown = dropdowns[i];
                            if ($(openDropdown).hasClass('show')) {
                                $(openDropdown).toggleClass('show');
                            }
                        }
                    }
                };
            },
            /**
             * Add reports as blocks to dashboard
             * @return {[type]} [description]
             */
            addblocks_to_dashboard: function() {
                Str.get_string('addblockstodashboard','block_reportdashboard'
                ).then(function(s) {
                    if($('.reportslist').html().length > 0){
                        $('.reportslist').dialog();
                    } else{

                    $.urlParam = function(name){
                    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
                    if (results === null || results == ' ' ){
                       return null;
                    } else{
                       return results[1] || 0;
                    }
                };
                var role=$.urlParam('role');
                var dashboardurl=$.urlParam('dashboardurl');
                var contextlevel=$.urlParam('contextlevel');
                var promise = Ajax.call([{
                    methodname: 'block_reportdashboard_addwidget_to_dashboard',
                    args: {
                        role: role,
                        dashboardurl: dashboardurl,
                        contextlevel: contextlevel,
                    },
                }]);
                promise[0].done(function(response) {
                    var widget_title_img = "<img class='dialog_title_icon' alt='Add Widgets' src='" +
                        M.util.image_url("add_widgets_icon", "block_reportdashboard") + "'/>";
                    $('.reportslist').dialog({
                        title: s,
                        modal: true,
                        minWidth: 700,
                        maxHeight: 600
                    });
                    $('.reportslist').closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                        .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                    $('.reportslist').closest(".ui-dialog").find('.ui-dialog-title')
                        .html(widget_title_img + s);
                    var resp = JSON.parse(response);
                    $('.reportslist').html(resp);
                }).fail(function() {
                    // do something with the exception
                });
            }
                });
            },
            addtiles_to_dashboard: function() {
                Str.get_string('addtilestodashboard','block_reportdashboard'
                ).then(function(s) {
                    if($('.statistics_reportslist').html().length > 0) {
                        $('.statistics_reportslist').dialog();
                    } else {
                     $.urlParam = function(name){
                    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
                    if (results === null || results == ' ' ){
                       return null;
                    } else{
                       return results[1] || 0;
                    }
                };

                var role=$.urlParam('role');
                var dashboardurl=$.urlParam('dashboardurl');
                var contextlevel=$.urlParam('contextlevel');
                var promise = Ajax.call([{
                    methodname: 'block_reportdashboard_addtiles_to_dashboard',
                     args: {
                        role: role,
                        dashboardurl: dashboardurl,
                        contextlevel: contextlevel,
                    },
                }]);
                 promise[0].done(function(response) {
                    var tile_title_img = "<img class='dialog_title_icon' alt='Add Tiles' src='" +
                        M.util.image_url("add_tiles_icon", "block_reportdashboard") + "'/>";
                    $('.statistics_reportslist').dialog({
                        title: s,
                        modal: true,
                        minWidth: 600,
                        maxHeight: 600
                    });
                    $('.statistics_reportslist').closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                        .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                    $('.statistics_reportslist').closest(".ui-dialog").find('.ui-dialog-title')
                        .html(tile_title_img + s);
                    var resp = JSON.parse(response);
                    $('.statistics_reportslist').html(resp);
                    }).fail(function() {
                    // do something with the exception
                });
                }
                });
            },
            addnewdashboard: function() {
                Str.get_string('addnewdashboard','block_reportdashboard'
                ).then(function(s) {
                    document.getElementById("id_dashboard").value = '';
                    $("#id_error_dashboard").css('display', 'none');
                    var tile_title_img = "<img class='dialog_title_icon' alt='Add new dashboard' src='" +
                        M.util.image_url("add_tiles_icon", "block_reportdashboard") + "'/>";
                    $('.newreport_dashboard').dialog({
                        title: s,
                        modal: true,
                        minWidth: 450,
                        maxHeight: 600
                    });
                    $('.newreport_dashboard').closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                        .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                        var Closebutton = $('.ui-icon-closethick').parent();
                        $(Closebutton).attr({
                            "title" : "Close"
                        });
                    $('.newreport_dashboard').closest(".ui-dialog").find('.ui-dialog-title')
                        .html(tile_title_img + s);
                });
            },
            updatedashboard: function(oldname, role){
                Str.get_string('updatedashboard','block_reportdashboard'
                ).then(function(s) {
                    $( "#id_error_dashboard" ).css('display', 'none');
                    $( "#id_error_dashboard_nospaces" ).css('display', 'none');
                    var tile_title_img = "<img class='dialog_title_icon' alt='Add new dashboard' src='" +
                        M.util.image_url("add_tiles_icon", "block_reportdashboard") + "'/>";
                    $('.newreport_dashboard').dialog({
                        title: s,
                        modal: true,
                        minWidth: 450,
                        maxHeight: 600
                    });
                    $('.newreport_dashboard').closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                        .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                        var Closebutton = $('.ui-icon-closethick').parent();
                        $(Closebutton).attr({
                            "title" : "Close"
                        });
                    $('.newreport_dashboard').closest(".ui-dialog").find('.ui-dialog-title')
                        .html(tile_title_img + s);
                    document.getElementById("id_dashboard").value = oldname;
                    $("#createdashbaord_form").submit(function(event){

                        var dashboardname = $( "#id_dashboard" ).val();
                        var name = dashboardname.trim();
                        if (name === '' || name === null) {
                            $( "#id_error_dashboard" ).css('display', 'block');
                            $( "#id_error_dashboard_nospaces" ).css('display', 'none');
                            event.preventDefault();
                            return false;
                        }
                        var spaceexist = name.indexOf(" ");
                        if (spaceexist > 0 && spaceexist != '') {
                            $( "#id_error_dashboard" ).css('display', 'none');
                            $( "#id_error_dashboard_nospaces" ).css('display', 'block');
                            event.preventDefault();
                            return false;
                        }

                        var args = {};
                        args.action = 'updatedashboard';
                        args.role = role;
                        args.oldname = oldname;
                        args.newname = document.getElementById("id_dashboard").value ;
                        ajax.call({
                            args:args,
                            url: M.cfg.wwwroot + "/blocks/reportdashboard/ajax.php"
                        });
                    });
                });
            },
            sendreportemail: function(args) {
                Str.get_strings([{
                    key: 'sendemail',
                    component: 'block_reportdashboard'
                }]).then(function(s) {
                    var url = M.cfg.wwwroot + '/blocks/learnerscript/ajax.php';
                    args.nodeContent = 'sendreportemail' + args.instanceid;
                    args.action = 'sendreportemail';
                    args.title = s;
                    var AjaxForms = require('block_learnerscript/ajaxforms');
                    AjaxForms.init(args, url);
                });
            },
            reportfilter: function(args) {
                var self = this;
                if ($('.report_filter_' + args.instanceid).length < 1) {
                    var promise = Ajax.call([{
                        methodname: 'block_learnerscript_reportfilter',
                        args: {
                            action: 'reportfilter',
                            reportid: args.reportid,
                            instance: args.instanceid
                        }
                    }]);
                    promise[0].done(function(resp) {
                        $('body').append("<div class='report_filter_" + args.instanceid +
                        "' style='display:none;'>" + resp + "</div>");
                        $("select[data-select2-ajax='1']").each(function() {
                            if (!$(this).hasClass('select2-hidden-accessible')) {
                                helper.Select2Ajax({});
                            }
                        });
                        self.reportFilterFormModal(args);
                         $('.filterform' + args.instanceid + ' .fitemtitle').hide();
                          $('.filterform' + args.instanceid + ' .felement').attr('style', 'margin:0');
                    });
                } else {
                    self.reportFilterFormModal(args);
                }
            },
            reportFilterFormModal: function(args) {
                Str.get_string('reportfilters', 'block_reportdashboard'
                ).then(function(s) {
                    var titleimg = "<img class='dialog_title_icon' alt='Filter' src='" +
                        M.util.image_url("reportfilter", "block_reportdashboard") + "'/>";
                    $(".report_filter_" + args.instanceid).dialog({
                        title: s,
                        dialogClass: 'reportfilter-popup',
                        modal: true,
                        resizable: true,
                        autoOpen: true,
                        draggable: false,
                        width: 420,
                        height: 'auto',
                        appendTo: "#inst" + args.instanceid,
                        position: {
                            my: "center",
                            at: "center",
                            of: "#inst" + args.instanceid,
                            within: "#inst" + args.instanceid
                        },
                        open: function() {
                        $(this).closest(".ui-dialog")
                            .find(".ui-dialog-titlebar-close")
                            .removeClass("ui-dialog-titlebar-close")
                            .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                            var Closebutton = $('.ui-icon-closethick').parent();
                            $(Closebutton).attr({
                                "title": "Close"
                            });

                        $(this).closest(".ui-dialog")
                            .find('.ui-dialog-title').html(titleimg + s);

                        /* Submit button */
                        $(".report_filter_" + args.instanceid + " form  #id_filter_apply").click(function(e) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            if ($("#reportcontainer" + args.instanceid).html().length > 0) {
                                args.reporttype = $("#reportcontainer" + args.instanceid).data('reporttype');
                            } else {
                                args.reporttype = $("#plotreportcontainer" + args.instanceid).data('reporttype');
                            }
                            args.container = '#reporttype_' + args.reportid;

                            require(['block_learnerscript/reportwidget'], function(reportwidget) {
                                reportwidget.CreateDashboardwidget({reportid: args.reportid,
                                                             reporttype: args.reporttype,
                                                             instanceid: args.instanceid});
                                $(".report_filter_" + args.instanceid).dialog('close');
                            });
                            $(".report_filter_" + args.instanceid + " form #id_filter_clear").removeAttr('disabled');
                        });
                    }
                });
                $(".report_filter_" + args.instanceid + " form #id_filter_clear").click(function(e) {
                    e.preventDefault();
                    $(".filterform" + args.instanceid + " select[data-select2-ajax='1']").val('0').trigger('change');
                    $('.filterform' + args.instanceid).trigger("reset");
                    require(['block_learnerscript/reportwidget'], function(reportwidget) {
                        reportwidget.DashboardWidgets(args);
                        $(".report_filter_" + args.instanceid).dialog('close');
                    });
                    $(".report_filter_" + args.instanceid).dialog('close');
                });
            });
            },
            DeleteWidget: function(args) {
                Str.get_string('deletewidget', 'block_reportdashboard'
                ).then(function(s) {
                    $("#delete_dialog" + args.instanceid).dialog({
                        resizable: true,
                        autoOpen: true,
                        width: 460,
                        height: 210,
                        title: s,
                        modal: true,
                        appendTo: "#inst" + args.instanceid,
                        position: {
                            my: "center",
                            at: "center",
                            of: "#inst" + args.instanceid,
                            within: "#inst" + args.instanceid
                        },
                        open: function() {
                            $(this).closest(".ui-dialog")
                                .find(".ui-dialog-titlebar-close")
                                .removeClass("ui-dialog-titlebar-close")
                                .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                                var Closebutton = $('.ui-icon-closethick').parent();
                                $(Closebutton).attr({
                                    "title": "Close"
                                });
                        }
                    });
                });
            },
            Deletedashboard: function(args) {
                Str.get_string('deletedashboard','block_reportdashboard'
                ).then(function(s) {
                    $( "#dashboard_delete_popup_"+args.random).dialog({
                        resizable: false,
                        height: 150,
                        width: 375,
                        modal: true,
                        title : s,
                        open: function() {
                        $(this).closest(".ui-dialog")
                            .find(".ui-dialog-titlebar-close")
                            .removeClass("ui-dialog-titlebar-close")
                            .html("<span class='ui-button-icon-primary ui-icon ui-icon-closethick'></span>");
                            var Closebutton = $('.ui-icon-closethick').parent();
                            $(Closebutton).attr({
                                "title" : "Close"
                            });
                        },
                        close: function() {
                            $(this).dialog('destroy').hide();
                        }
                    });
                });
            },
            /**
             * Schedule report form in popup in dashboard
             * @param  {object} args reportid
             */
            schreportform: function(args) {
                Str.get_string('schedulereport', 'block_reportdashboard'
                ).then(function(s) {
                    var url = M.cfg.wwwroot + '/blocks/learnerscript/ajax.php';
                    args.title = s;
                    args.nodeContent = 'schreportform' + args.instanceid;
                    args.action = 'schreportform';
                    args.courseid = $('[name="filter_courses"]').val();
                    var AjaxForms = require('block_learnerscript/ajaxforms');
                    AjaxForms.init(args, url);
                });
            },

            profileuserfunction: function() {
                /** User profile user on changes  function*/
                $(document).on('change', "#dashboardusers", function() {
                    let queryString = window.location.search;
                    // Step 2: Create a URLSearchParams object
                    let params = new URLSearchParams(queryString);

                    // Step 3: Get specific parameter values
                    let role = params.get('role');
                    let contextlevel = params.get('contextlevel');
                    var url = $(this).val(); // get selected value
                    if (url) { // require a URL
                        window.location = 'profilepage.php?filter_users=' + url
                                    + '&role=' + role + '&contextlevel=' + contextlevel; // redirect
                    }
                    return false;
                });
                $(document).on('change', "#dashboardcourses", function() {
                    var url = $(this).val(); // get selected value
                    if (url) { // require a URL
                        window.location = 'courseprofile.php?filter_courses=' + url; // redirect
                    }
                    return false;
                });
            }
        };
    });
