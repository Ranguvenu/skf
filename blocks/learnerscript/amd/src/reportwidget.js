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
 * Creates the dashbaord widgets for configured widgets on dashboard.
 *
 * @module     block_learnerscript/reportwidget
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
        'core/ajax',
        'block_learnerscript/report',
        'block_learnerscript/smartfilter',
        'core/str',
        'core/templates'
    ], function($, Ajax, report, smartfilter, Str, Templates) {
    var reportwidget = {
        /**
         * Creates dashboard widgets for configured widgets of dashboard depends on type
         * @param {object} args reportcontainer
         */
        DashboardWidgets: function(args) {
            var self = this;
            args = args || {
                container: '.report_dashboard_container'
            };
            var filterdata = smartfilter.FilterData(args.reportid);
            $('.loader').show();
            $('#reportcontainer' + args.instanceid).each(function() {
                var reportinstance;
                var reportid = $(this).data('reportid');
                var blockinstance = $(this).data('blockinstance');
                if ($(this).val() != '') {
                    blockinstance = $("#reportcontainer" + reportinstance).data('blockinstance');
                    args.reporttype = $("#reporttype_" + blockinstance + "  :selected").val();
                    var params = {
                        reportid: reportid,
                        reporttype: args.reporttype
                    };
                    $.extend(params, filterdata);
                    self.CreateDashboardwidget(params);
                    return false;
                } else {
                    args.reporttype = $("#reporttype_" + blockinstance + "  :selected").val();

                    if (typeof (args.reporttype) == 'undefined') {
                        args.reporttype = $(this).data('reporttype');
                    }
                    self.CreateDashboardwidget({
                        reportid: reportid,
                        reporttype: args.reporttype,
                        instanceid: blockinstance
                    });
                }
            });
        },
        DashboardTiles: function() {
            var self = this;
            var lsfstartdate = $('#lsfstartdate').val();
            var lsfenddate = $('#lsfenddate').val();
            var courseid = $('#ls_courseid').val();
            $(".tiles_information").each(function() {
                self.CreateDashboardTile({
                    blockinstanceid: $(this).data('instanceid'),
                    reportid: $(this).data('reportid'),
                    reporttype: $(this).data('reporttype'),
                    lsfstartdate: lsfstartdate,
                    lsfenddate: lsfenddate,
                    courseid: courseid
                });
            });
        },
        CreateDashboardTile: function(args) {
            $("#inst" + args.blockinstanceid + " .tiles_information table tr").html("");
            var reportinstance = args.blockinstanceid;
            var filters = {};
            filters.lsfstartdate = $('#lsfstartdate' + args.blockinstanceid).val();
            filters.lsfenddate = $('#lsfenddate' + args.blockinstanceid).val();

            $.urlParam = function(name) {
                var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
                if (results === null || results == ' ') {
                    return null;
                } else {
                    return results[1] || 0;
                }
            };
            var dashboardurl = $.urlParam('dashboardurl');
            if (typeof filters.filter_courses == 'undefined') {
                var filter_courses = $('.report_courses').val();
                if (dashboardurl == 'Course') {
                    args.courseid = filter_courses;
                }
                if (filter_courses != 1) {
                    filters.filter_courses = filter_courses;
                }
            }

            var promise = Ajax.call([{
                methodname: 'block_learnerscript_generate_plotgraph',
                args: {
                    instanceid: args.blockinstanceid,
                    reportid: args.reportid,
                    courseid: args.courseid,
                    filters: JSON.stringify(filters),
                    reporttype: args.reporttype
                },
                loading: '#reportloading_' + args.blockinstanceid
            }]);
            promise[0].done(function(data) {
                data = JSON.parse(data);
                var tabledata = [];
                if (args.reporttype != 'table') {
                        $.extend(data.plot, args);
                        data.plot.container = "#plotreportcontainer" + reportinstance;
                        if (data.plot.error === true) {
                            $('#plotreportcontainer' + reportinstance).html('<p class="alert alert-warning">' +
                            data.plot.messages + '</p>');
                        } else {
                            if (data.plot.data.length == 0) {
                                $(data.plot.container).html("<div class='alert alert-info'>" +
                                data.plot.messages + "</div>");
                            } else {
                                data.plot.reportinstance = reportinstance;
                                require(['block_learnerscript/report'], function(report) {
                                    report.generate_plotgraph(data.plot);
                                });
                            }
                        }
                } else {
                    if (typeof data.plot != 'undefined') {
                        if (data.plot.data.length > 0) {
                            $(data.plot.data).each(function(key, value) {
                                tabledata = [];
                                tabledata = value.data;
                            });
                            $(data.plot.categorydata).each(function(k, v) {
                                if (data.plot.categorydata.length == 1) {
                                    if (!isNaN(tabledata[0][k])) {
                                        $("#inst" + args.blockinstanceid + " .tiles_information table tr").append('<td><h1 ' +
                                        args.styletilescolour + '> ' + tabledata[0][k] + ' </h1></td>');
                                    } else {
                                        $("#inst" + args.blockinstanceid + " .tiles_information table tr").append('<td><h6 ' +
                                        args.styletilescolour + '> ' + tabledata[0][k] + ' </h6></td>');
                                    }
                                } else {
                                    $("#inst" + args.blockinstanceid + " .tiles_information table tr").append('<td>' +
                                    v + ' : <b> ' + tabledata[k] + ' </b></td>');
                                }
                            });
                        } else {
                            Str.get_string('nodataavailable','block_learnerscript').then(function(s){
                                $("#inst" + args.blockinstanceid + " .tiles_information table tr").html("<div " +
                                "class='alert alert-info'> " + s + "</div>");
                            });
                            $("#inst" + args.blockinstanceid + " .dashboard_tiles").css('color', '#4B4B4B');
                        }
                        $("#reportloading_" + args.blockinstanceid).css('display', 'none');
                    }
                }
            });
        },
        /**
         * Creates single dashboard widget for requested report and type
         * @param {object} args reportid and reporttype
         * @return Creates report widget depends on type table,pie chart etc...
         */
        CreateDashboardwidget: function(args) {
            if (args.reportdashboard == false) {
                args.instanceid = '';
            }
            var reportinstance = args.instanceid || args.reportid;
            args.filters = args.filters || smartfilter.FilterData(reportinstance);
            args.columnDefs = '';
            args.filters['lsfstartdate'] = $('#lsfstartdate' + args.instanceid).val() || $('#lsfstartdate').val();
            args.filters['lsfenddate'] = $('#lsfenddate' + args.instanceid).val() || $('#lsfstartdate').val();
            if (typeof args.filters['filter_courses'] == 'undefined') {
                var filter_courses = $('.report_courses').val();
                if (filter_courses != 1) {
                    args.filters['filter_courses'] = filter_courses;
                }
            }
            if (args.reporttype == 'table') {
            } else {
                $('.plotgraphcontainer').removeClass('hide').addClass('show');
            }
            if (args.selectreport) {
                $("#reportcontainer" + reportinstance).attr('data-reporttype', args.singleplot);
                $("#plotreportcontainer" + reportinstance).attr('data-reporttype', args.singleplot);
                delete args.selectreport;
            }

            args.filters = JSON.stringify(args.filters);
            args.action = 'generate_plotgraph';
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
                    });
                }
                $(this).attr('href', link);
            });
            args.basicparams = args.basicparams || JSON.stringify(smartfilter.BasicparamsData(reportinstance));
            if (typeof args.reportdashboard != 'undefined' && typeof args.reporttype != 'undefined') {
                $("#reportcontainer" + reportinstance).html("");
                $("#plotreportcontainer" + reportinstance).html("");
            } else {
                if (typeof args.reportdashboard != 'undefined' && args.reporttype == 'table') {
                    $("#reportcontainer" + reportinstance).html("");
                } else {
                    $("#plotreportcontainer" + reportinstance).html("");
                }
            }
            var promise = Ajax.call([{
                methodname: 'block_learnerscript_generate_plotgraph',
                args: args,
                loading: '#reportloading_' + args.reportid
            }]);
            if ($("#reportloadingimage").length <= 0) {
                if (args.reporttype == 'table') {
                    $("#reportcontainer" + args.reportid).prepend('<img src="' +
                    M.util.image_url('loading', 'block_learnerscript') + '" id="reportloadingimage" />');
                } else {
                    $("#plotreportcontainer" + args.reportid).prepend('<img src="' +
                    M.util.image_url('loading', 'block_learnerscript') + '" id="reportloadingimage" />');
                }
            }
            promise[0].done(function(chartdata) {
                chartdata = $.parseJSON(chartdata);
                var reporttype = chartdata.reporttype || args.reporttype;
                if (reporttype == 'table') {
                    if (typeof (chartdata.data) == 'undefined' && chartdata.emptydata) {
                        if (!$('#reporttable_' + args.reportid).length) {
                            Str.get_string('nodataavailable', 'block_learnerscript').then(function(s) {
                                $("#reportcontainer" + reportinstance).html('<p class="alert alert-info">' +
                                s + '</p>');
                            });
                        }
                        $(document).ajaxStop(function() {
                            $("#reportloadingimage").remove();
                        });
                    } else {
                        Templates.render('block_learnerscript/reporttable', chartdata.tdata).done(function(html) {
                            if (!$('#reporttable_' + args.reportid).length) {
                                $("#reportcontainer" + reportinstance).html(html);
                            }
                            $(document).ajaxStop(function() {
                                $("#reportloadingimage").remove();
                            });
                            args.columnDefs = chartdata.columnDefs;
                            args.data = chartdata.data;
                            args.reportname = chartdata.reportname;
                            require(['block_learnerscript/report'], function(report) {
                                report.ReportDatatable(args);
                            });
                        });
                    }
                } else {
                    $.extend(chartdata.plot, args);
                    chartdata.plot.container = "#plotreportcontainer" + reportinstance;
                    $(document).ajaxStop(function() {
                        $("#reportloadingimage").remove();
                    });
                    if (chartdata.plot.error === true) {
                        $('#plotreportcontainer' + reportinstance).html('<p class="alert alert-warning">' +
                        chartdata.plot.messages + '</p>');
                    } else {
                        if (chartdata.plot.data && chartdata.plot.data.length > 0) {
                            chartdata.plot.reportinstance = reportinstance;
                            $(".drilldown" + reportinstance + " .ui-dialog-title").html(chartdata.reportname);
                            args.reportname = chartdata.reportname;
                            require(['block_learnerscript/report'], function(report) {
                                report.generate_plotgraph(chartdata.plot);
                            });
                        } else {
                            Str.get_string('nodataavailable','block_learnerscript').then(function(s){
                                $(chartdata.plot.container).html('<p class="alert alert-warning">' +
                                s + '</p>');
                            });
                        }
                    }
                }
            }).fail(function() {
                // Do something with the exception
            });
        }
    };
    return reportwidget;
});
