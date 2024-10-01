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
 * @module     block_learnerscript/report
 * @class      report
 * @package
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @since      3.3
 */
define(['jquery',
    'block_learnerscript/select2/select2',
    'block_learnerscript/datatables/jquery.dataTables',
    'block_learnerscript/datatables/responsive.bootstrap',
    'block_learnerscript/reportwidget',
    'block_learnerscript/chart',
    'block_learnerscript/smartfilter',
    'block_learnerscript/schedule',
    'block_learnerscript/helper',
    'block_learnerscript/ajaxforms',
    'block_learnerscript/ajax',
    'block_learnerscript/radioslider/jquery.radios-to-slider',
    'block_learnerscript/flatpickr',
    'core/templates',
    'core/str',
    'jqueryui'
], function($, select2, dataTable, responsive, reportwidget, chart, smartfilter, schedule, helper, AjaxForms, ajax,
    RadiosToSlider, flatpickr, templates, Str) {
    var BasicparamCourse = $('.basicparamsform #id_filter_courses');
    var BasicparamUser = $('.basicparamsform #id_filter_users');
    var BasicparamActivity = $('.basicparamsform #id_filter_activities');

    var FilterCourse = $('.filterform #id_filter_courses');
    var FilterUser = $('.filterform #id_filter_users');
    var FilterActivity = $('.filterform #id_filter_activities');
    var FilterModule = $('.filterform #id_filter_modules');
    var FilterCohort = $('.filterform #id_filter_cohort');

    var NumberOfBasicParams = 0;

    var report = {
        init: function(args) {
            /**
             * Initialization
             */
            $.ui.dialog.prototype._focusTabbable = $.noop;
            $.fn.dataTable.ext.errMode = 'none';

            $('.plotgraphcontainer').on('click', function() {
                var reportid = $(this).data('reportid');
                $('.plotgraphcontainer').removeClass('show').addClass('hide');
                $('#plotreportcontainer' + reportid).html('');

            });
            /**
             * Send message to user from username otion in report
             */
            $(document).on('click', ".sendusermsg", function(e) {
                var userid = $(this).data('userid');
                var reportinstance = $(this).data('reportinstance');
                var userfullname = $(this).data('userfullname');

                helper.sendmessage({userid: userid, reportinstance: reportinstance}, userfullname);
                e.stopImmediatePropagation();
            });

            /**
             * Report graphs display
             */
            $(document).on('click', ".ls-plotgraph_link", function() {
                var reportid = $(this).data('reportid');
                var reporttype = $(this).data('reporttype');
                reportwidget.CreateDashboardwidget({reportid: reportid, reporttype: reporttype});
            });

            /**
             * Report calender filter display
             */
            $(document).on('click', ".calenderdatefilter", function() {
                var activefilter = $(this).data('activefilter');
                var inactivefilter = $(this).data('inactivefilter');
                helper.ViewReportFilters({activefilter: activefilter, inactivefilter: inactivefilter});
            });

            /**
             * Report custom filters display
             */
            $(document).on('click', ".customfiltericon", function() {
                var activefilter = $(this).data('activefilter');
                var inactivefilter = $(this).data('inactivefilter');
                helper.ViewReportFilters({activefilter: activefilter, inactivefilter: inactivefilter});
            });

            /**
             * Statistics report help text
             */
            $(document).on('click', ".statisticshelptext", function() {
                var reportid = $(this).data('reportid');
                report.block_statistics_help(reportid);
            });

            /**
             * Plotform tabs graph edit
             */

            $(document).on('click', ".ls-plotreport_editicon", function() {
                var reportid = $(this).data("reportid");
                var action = $(this).data("action");
                var component = $(this).data("component");
                var title = $(this).data("title");
                var pname = $(this).data("pname");
                var cid = $(this).data("cid");
                var type = $(this).data("type");
                helper.PlotForm({reportid : reportid, action : action, component : component, title : title,
                pname : pname, cid : cid, type : type });
            });

            /**
             * Plotform tabs graph delete
             */

            $(document).on('click', ".ls-plotreport_deleteicon", function() {
                var reportid = $(this).data("reportid");
                var pname = $(this).data("pname");
                var cid = $(this).data("cid");
                var gdelete = $(this).data("delete");
                var comp = $(this).data("comp");
                var action = $(this).data("graphaction");
                helper.deleteConfirm({reportid : reportid, action : action, comp : comp, pname : pname, cid : cid, delete : gdelete});
            });

            /**
             * Plotform tabs add graph
             */

            $(document).on('click', ".plotgraphoptions", function() {alert(34)
                var reportid = $(this).data("reportid");
                var action = $(this).data("action");
                var component = $(this).data("component");
                var title = $(this).data("title");
                var pname = $(this).data("pname");
                var type = $(this).data("type");
                helper.PlotForm({reportid : reportid, action : action, component : component, title : title,
                pname : pname, type : type });
            });
            
            /**
             * Select2 initialization
             */
            $("select[data-select2='1']").select2({
                theme: "classic"
            }).on("select2:selecting", function(e) {
                if ($(this).val() && $(this).data('maximumselectionlength') &&
                    $(this).val().length >= $(this).data('maximumselectionlength')) {
                    e.preventDefault();
                    $(this).select2('close');
                }
            });

            /*
             * Report search
             */
            $("#reportsearch").val(args.reportid).trigger('change.select2');
            $("#reportsearch").change(function() {
                var reportid = $(this).find(":selected").val();
                window.location = M.cfg.wwwroot + '/blocks/learnerscript/viewreport.php?id=' + reportid;
            });
            /**
             * Duration buttons
             */
            RadiosToSlider.init($('#segmented-button'), {
                size: 'medium',
                animation: true,
                reportdashboard: false
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
                    if (selectedDates.length !== 0) {
                        $('#lsfstartdate').val(selectedDates[0].getTime() / 1000);
                        $('#lsfenddate').val((selectedDates[1].getTime() / 1000) + (60 * 60 * 24));
                        require(['block_learnerscript/report'], function(report) {
                            report.CreateReportPage({reportid: args.reportid, instanceid: args.reportid, reportdashboard: false});
                        });
                    }
                }
            });
            /*
             * Get users for selected course
             */
            $('#id_filter_cohort').change(function() {
                var cohortid = $(this).find(":selected").val();
                if (cohortid > 0 && (FilterUser.length > 0 || BasicparamUser.length > 0)) {
                    if (BasicparamUser.length > 0) {
                        FirstElementActive = true;
                    }
                    smartfilter.CohortUsers({cohortid: cohortid, reporttype: args.reporttype, reportid: args.reportid,
                                              firstelementactive: FirstElementActive});
                }
            });

            /*
             * Get Activities and Enrolled users for selected course
             */
            if (typeof BasicparamCourse != 'undefined' || typeof FilterCourse != 'undefined') {
                $('#id_filter_courses').change(function() {
                    args.courseid = $(this).find(":selected").val();
                    smartfilter.CourseData(args);
                });
            }

            /*
             * Get Enrolled courses for selected user
             */
            $('#id_filter_users').change(function() {
                var userid = $(this).find(":selected").val();
                if (userid > 0 && (FilterCourse.length > 0 || BasicparamCourse.length > 0)) {
                    if (BasicparamCourse.length > 0) {
                        FirstElementActive = true;
                    }
                }
            });

            $('#id_filter_coursecategories').change(function() {
                var categoryid = $(this).find(":selected").val();
                smartfilter.categoryCourses({categoryid: categoryid, reporttype: args.reporttype});
            });

            schedule.SelectRoleUsers();

            if (args.basicparams !== null) {
                if (args.basicparams[0].name == 'courses') {
                    $("#id_filter_courses").trigger('change');
                    NumberOfBasicParams++;
                }
            }
            if (args.basicparams !== null) {
                var FirstElementActive = false;
                if (args.basicparams[0].name == 'users') {
                    if (BasicparamCourse.length > 0) {
                        FirstElementActive = true;
                    }
                    var userid = $("#id_filter_users").find(":selected").val();
                    if (userid > 0) {
                        args.courseid = $('#id_filter_courses').find(":selected").val();
                        smartfilter.CourseData(args);
                    }
                }
            }

            // For forms formatting..can't make unique everywhere, so little trick ;)
            $('.filterform' + args.reportid + ' .fitemtitle').hide();
            $('.filterform' + args.reportid + ' .felement').attr('style', 'margin:0');

            $('.basicparamsform' + args.reportid + ' .fitemtitle').hide();
            $('.basicparamsform' + args.reportid + ' .felement').attr('style', 'margin:0');

            /*
             * Filter form submission
             */
            $(".filterform #id_filter_clear").click(function() {
                $(".filterform" + args.reportid).trigger("reset");
                if (FilterUser.length > 0) {
                    if (FilterCourse.length > 0 || BasicparamCourse.length > 0) {
                        if (BasicparamCourse.length > 0) {
                            FirstElementActive = true;
                        }
                    }
                }
                if (FilterCourse.length > 0) {
                    if (FilterUser.length > 0 || BasicparamUser.length > 0) {
                    }

                    if (FilterActivity.length > 0 || BasicparamActivity.length > 0) {
                        smartfilter.CourseActivities({courseid: 0});
                    }
                }
                if (FilterActivity.length > 0 || FilterModule.length > 0) {
                    if ((FilterCourse.length > 0 || BasicparamCourse.length > 0) && BasicparamUser.length == 0) {
                        if (BasicparamCourse.length > 0 && BasicparamUser.length == 0) {
                            FirstElementActive = true;
                        }
                    }
                    if (BasicparamCourse.length > 0 && BasicparamUser.length > 0) {
                            $(".basicparamsform #id_filter_apply").trigger('click', [true]);
                    }
                }
                if (FilterCohort.length > 0) {
                    if (FilterUser.length > 0) {
                        smartfilter.CohortUsers({cohortid: 0});
                    }
                }

                if ($(".basicparamsform #id_filter_apply").length > 0) {
                    $(document).ajaxComplete(function() {
                    });
                    $(".basicparamsform #id_filter_apply").trigger('click', [true]);
                } else {
                    args.reporttype = $('.ls-plotgraphs_listitem.ui-tabs-active').data('cid');
                    report.CreateReportPage({reportid: args.reportid, reporttype: args.reporttype, instanceid: args.reportid,
                        reportdashboard: false,
                    });
                }
                $(".filterform select[data-select2='1']").select2("destroy").select2({theme: "classic"});
                $(".filterform select[data-select2-ajax='1']").val('0').trigger('change');
                $('.filterform')[0].reset();
                $(".filterform #id_filter_clear").attr('disabled', 'disabled');
                $('.plotgraphcontainer').removeClass('show').addClass('hide');
                $('#plotreportcontainer' + args.instanceid).html('');
            });

            /*
             * Basic parameters form submission
             */
            $(".basicparamsform #id_filter_apply,.filterform #id_filter_apply").click(function(e, validate) {
                var getreport = helper.validatebasicform(validate);
                e.preventDefault();
                e.stopImmediatePropagation();
                $(".filterform" + args.reportid).show();
                args.instanceid = args.reportid;
                if (e.currentTarget.value != 'Get Report') {
                    $(".filterform #id_filter_clear").removeAttr('disabled');
                }
                if ($.inArray(0, getreport) != -1) {
                    $("#report_plottabs").hide();
                    Str.get_string('nodataavailable', 'block_learnerscript').then(function(s) {
                        $("#reportcontainer" + args.reportid).html("<div class='alert alert-info'>" + s + "</div>");
                    });
                } else {
                    $("#report_plottabs").show();
                    args.reporttype = $('.ls-plotgraphs_listitem.ui-tabs-active').data('cid');
                    report.CreateReportPage({reportid: args.reportid, reporttype: args.reporttype,
                        instanceid: args.instanceid, reportdashboard: false});
                }
                $('.plotgraphcontainer').removeClass('show').addClass('hide');
                $('#plotreportcontainer' + args.instanceid).html('');
            });
            /*
             * Generate Plotgraph
             */
            if (args.basicparams === null) {
                report.CreateReportPage({reportid: args.reportid, reporttype: args.reporttype,
                    instanceid: args.reportid, reportdashboard: false});
            } else {
                if (args.basicparams.length <= 4) {
                    $(".basicparamsform #id_filter_apply").trigger('click', [true]);
                } else {
                        $(document).ajaxComplete(function(event, xhr, settings) {
                            if (settings.url.indexOf("blocks/learnerscript/ajax.php") > 0) {
                                if (typeof settings.data != 'undefined') {
                                    var ajaxaction = $.parseJSON(settings.data);
                                    if (typeof ajaxaction.basicparam != 'undefined' && ajaxaction.basicparam == true) {
                                        NumberOfBasicParams++;
                                    }
                                }
                                if (args.basicparams.length == NumberOfBasicParams
                                    && ajaxaction.action != 'plotforms' && ajaxaction.action != 'pluginlicence') {
                                    $(".basicparamsform #id_filter_apply").trigger('click', [true]);
                                }
                            }
                        });
                }
            }
        },
        CreateReportPage: function(args) {
            var disabletable = 0;
            if (args.reportdashboard == false) {
                var disabletable = $('#disabletable').val();
                if (disabletable) {
                    args.reporttype = $($('.ls-plotgraphs_listitem')[0]).data('cid');
                }
            }
            if (disabletable == 1 && args.reporttype.length > 0) {
                chart.HighchartsAjax({
                    'reportid': args.reportid,
                    'action': 'generate_plotgraph',
                    'cols': args.cols,
                    'reporttype': args.reporttype
                });
            } else if (disabletable == 0) {
                require(['block_learnerscript/reportwidget'], function(reportwidget) {
                    reportwidget.CreateDashboardwidget({
                        reportid: args.reportid,
                        reporttype: 'table',
                        instanceid: args.instanceid,
                        reportdashboard: args.reportdashboard
                    });
                });
            } else {

            }
        },

        /**
         * Generates graph widget with given Highcharts ajax response
         * @param {object} response Ajax response
         * @return Creates highchart widget with given response based on type of chart
         */
        generate_plotgraph: function(response) {
            if (response) {
                var reportinstance = response.reportinstance || response.reportid;
                response.containerid = 'plotreportcontainer' + reportinstance;
                switch (response.type) {
                    case 'spline':
                    case 'bar':
                    case 'column':
                        chart.lbchart(response);
                        break;
                    case 'radar':
                        chart.radarchart(response);
                        break;
                    case 'combination':
                        chart.combinationchart(response);
                        break;
		    case 'pie':
                    	chart.piechart(response);
                    	break;
                }
            }
        },

        /**
         * Datatable serverside for all table type reports
         * @param {object} args reportid
         * @return Apply serverside datatable to report table
         */
        ReportDatatable: function(args) {
            Str.get_strings([{
                key: 'show',
                component: 'block_learnerscript'
            },{
                key: 'show_all',
                component: 'block_learnerscript'
            },{
                key: 'nodataavailable',
                component: 'block_learnerscript'
            }, {
                key: 'search',
                component: 'block_learnerscript'
            }]).then(function(s){
                var params = {};
                var reportinstance = args.instanceid ? args.instanceid : args.reportid;
                params.filters = args.filters;
                params.basicparams = args.basicparams || JSON.stringify(smartfilter.BasicparamsData(reportinstance));
                params.reportid = args.reportid;
                params.columns = args.columns;
                //
                // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
                //
                $.fn.dataTable.pipeline = function(opts) {
                    // Configuration options
                    var conf = $.extend({
                        url: '', // Script url
                        data: null, // Function or object with parameters to send to the server
                        method: 'POST' // Ajax HTTP method
                    }, opts);

                    return function(request, drawCallback, settings) {
                        var ajax = true;
                        var requestStart = request.start;
                        var requestLength = request.length;
                        var json;
                        var cacheLastJson;
                        var cacheLower;
                        if (typeof args.data != 'undefined' && request.draw == 1) {
                            json = args.data;
                            json.draw = request.draw; // Update the echo for each response
                            json.data.splice(0, requestStart);
                            json.data.splice(requestLength, json.data.length);
                            drawCallback(json);
                        } else if (ajax) {
                            // Need data from the server
                            request.start = requestStart;
                            request.length = requestLength;
                            $.extend(request, conf.data);

                            settings.jqXHR = $.ajax({
                                "type": conf.method,
                                "url": conf.url,
                                "data": request,
                                "dataType": "json",
                                "cache": false,
                                "success": function(json) {
                                    drawCallback(json);
                                }
                            });
                        } else {
                            json = $.extend(true, {}, cacheLastJson);
                            json.draw = request.draw; // Update the echo for each response
                            json.data.splice(0, requestStart - cacheLower);
                            json.data.splice(requestLength, json.data.length);
                            drawCallback(json);
                        }
                    };
                };
                if (args.reportname == 'Users profile' || args.reportname == 'Course profile') {
                    var lengthoptions = [
                        [50, 100, -1],
                        [s[0] + "50", s[0] + "100", s[1]]
                    ];
                } else {
                    var lengthoptions = [
                        [10, 25, 50, 100, -1],
                        [s[0] + "10", s[0] + "25", s[0] + "50", s[0] + "100", s[1]]
                    ];
                }

                $('#reporttable_' + reportinstance).DataTable({
                    'processing': true,
                    'serverSide': true,
                    'destroy': true,
                    'dom': '<"co_report_header"Bf <"report_header_skew"  <"report_header_skew_content" Bl' +
                    '<"report_header_showhide" >' +
                    '<"report_calculation_showhide" >> > > tr <"co_report_footer"ip>',
                    'ajax': $.fn.dataTable.pipeline({
                        "type": "POST",
                        "url": M.cfg.wwwroot + '/blocks/learnerscript/components/datatable/server_processing.php?sesskey=' +
                        M.cfg.sesskey,
                        "data": params
                    }),
                    'columnDefs': args.columnDefs,
                    "fnDrawCallback": function() {
                        helper.DrilldownReport();
                    },
                    "oScroll": {},
                    'responsive': true,
                    "fnInitComplete": function() {
                        if (args.reportname == 'Users profile' || args.reportname == 'Course profile'
                            || args.reportname == 'Statistic') {
                            $("#reporttable_" + reportinstance + "_wrapper .co_report_header").remove();
                            $("#reporttable_" + reportinstance + "_wrapper .co_report_footer").remove();
                        }

                        $('.download_menu' + reportinstance + ' li a').each(function() {
                            var link = $(this).attr('href');
                            if (typeof args.basicparams != 'undefined') {
                                var basicparamsdata = JSON.parse(args.basicparams);
                                $.each(basicparamsdata, function(key, value) {
                                    if (key.indexOf('filter_') == 0) {
                                        link += '&' + key + '=' + value;
                                    }
                                });
                            }
                            if (typeof (args.filters) != 'undefined') {
                                var filters = JSON.parse(args.filters);
                                $.each(filters, function(key, value) {
                                    if (key.indexOf('filter_') == 0) {
                                        link += '&' + key + '=' + value;
                                    }
                                    if (key.indexOf('lsf') == 0) {
                                        link += '&' + key + '=' + value;
                                    }
                                });
                            }
                            $(this).attr('href', link);
                        });
                    },
                    "fnRowCallback": function(nRow) {
                        $(nRow).children().each(function(index, td) {
                            $(td).css("word-break", args.columnDefs[index].wrap);
                            $(td).css("width", args.columnDefs[index].width);
                        });
                        return nRow;
                    },
                    "autoWidth": false,
                    'aaSorting': [],
                    'language': {
                        'paginate': {
                            'previous': '<',
                            'next': '>'
                        },
                        'sProcessing': "<img src='" + M.util.image_url('loading', 'block_learnerscript') + "'>",
                        'search': "_INPUT_",
                        'searchPlaceholder': s[3],
                        'lengthMenu': "_MENU_",
			 "emptyTable": "<div class='alert alert-info'>" + s[2] + "</div>"
                    },
                    "lengthMenu": lengthoptions
                });
                $(".drilldown" + reportinstance + " .ui-dialog-title").html(args.reportname);
                $("#page-blocks-learnerscript-viewreport #reporttable_" + args.reportid + "_wrapper div.report_header_showhide").
                html($('#export_options' + args.reportid).html());
                if ($('.reportcalculation' + args.reportid).length > 0) {
                    $("#page-blocks-learnerscript-viewreport #reporttable_" +
                    args.reportid + "_wrapper div.report_calculation_showhide").
                    html('<img src="' + M.util.image_url('calculationicon', 'block_learnerscript') +
                    '" onclick="(function(e){ require(\'block_learnerscript/helper\').reportCalculations({reportid:' +
                    args.reportid + '}) })(event)" title ="Calculations" />');
                }
            });
        },

        AddExpressions: function(e, value) {
            $(e.target).on('select2:unselecting', function(e) {
                $('#fitem_id_' + e.params.args.data.id + '').remove();
            });
            var columns = $(e.target).val();
            $.each(columns, function(index) {
                if ($('#fitem_id_' + columns[index]).length > 0) {
                    return;
                }
                var column = [];
                 column.name = columns[index];
                 column.conditionsymbols = [];
                 var conditions = ["=", ">", "<", ">=", "<=", "<>"];
                 $.each(conditions, function(index, value) {
                    column.conditionsymbols.push({
                        'value': value
                    });
                 });
                var requestdata = {column: column};
                templates.render('block_learnerscript/plotconditions', requestdata).then(function(html) {
                    if (value == 'yaxisbarvalue') {
                        $('#yaxis_bar1').append(html);
                    } else {
                        $('#yaxis1').append(html);
                    }

                }).fail(function() {});
            });
        },
        block_statistics_help: function(reportid) {
            var promise = ajax.call({
                args: {
                    action: 'learnerscriptdata',
                    reportid: reportid
                },
                url: M.cfg.wwwroot + "/blocks/learnerscript/ajax.php",
            });
            promise.done(function(response) {
                require(['core/modal_factory'], function(ModalFactory) {
                    ModalFactory.create({
                        title: response.name,
                        body: response.summary,
                        footer: '',
                    }).done(function(modal) {
                        var dialogue = modal;
                        var ModalEvents = require('core/modal_events');
                        dialogue.getRoot().on(ModalEvents.hidden, function() {
                        });

                        dialogue.show();
                    });
                });
            });
        },
        CourseprofileReportsdata: function(args) {
            if (args.reporttype == 'table') {
                reportwidget.CreateDashboardwidget({
                    reportid: args.reportid,
                    reporttype: 'table',
                    instanceid: args.instanceid,
                    reportdashboard: args.reportdashboard,
                    filters: args.filters
                });
            } else {
                chart.HighchartsAjax({
                    'reportid': args.reportid,
                    'action': 'generate_plotgraph',
                    'cols': args.cols,
                    'reporttype': args.reporttype,
                    'filters': args.filters
                });
            }
        }

    };
    return report;
});
