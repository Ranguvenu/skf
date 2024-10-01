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
 * Describe different types of charts.
 *
 * @module     block_learnerscript/chart
 * @copyright  2023 Moodle India Information Solutions Private Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
        'core/ajax',
        'block_learnerscript/charts/chart',
        'block_learnerscript/smartfilter',
        'block_learnerscript/report',
        'core/str'
    ],
    function($, Ajax, Chart, smartfilter, report, Str) {
        /**
         * Get highchart report for report with Ajax request
         * @param object reportid and reportdata
         * @return Generate highchart report
         */
        var chart = {
            HighchartsAjax: function(args) {
                args.cols = JSON.stringify(args.cols);
                args.instanceid = args.reportid;
                args.filters = args.filters || smartfilter.FilterData(args.instanceid);
                args.basicparams = JSON.stringify(smartfilter.BasicparamsData(args.instanceid));
                args.filters['lsfstartdate'] = $('#lsfstartdate').val();
                args.filters['lsfenddate'] = $('#lsfenddate').val();
                if (typeof args.filters['filter_courses'] == 'undefined') {
                    var filter_courses = $('.dashboardcourses').val();
                    if (filter_courses != 1) {
                        args.filters['filter_courses'] = filter_courses;
                    }
                }
                args.filters = JSON.stringify(args.filters);

                // Request
                var promise = Ajax.call([{
                    methodname: 'block_learnerscript_generate_plotgraph',
                    args: args,
                }]);

                // Preload
                $('#report_plottabs').show();
                $("#plotreportcontainer" +
                args.instanceid).html('<img src="' +
                M.util.image_url('loading', 'block_learnerscript') + '" id="reportloadingimage" />');

                // Response process
                   promise[0].done(function(response) {
                        response = JSON.parse(response);
                        if (response.plot) {
                            if (response.plot.error === true) {
                                Str.get_string('nodataavailable', 'block_learnerscript').then(function(s) {
                                    $('#plotreportcontainer' +
                                    args.instanceid).html("<div class='alert alert-warning'>" + s + "</div>");
                                });
                            } else {
                                response.plot.reportid = args.reportid;
                                response.plot.reportinstance = args.reportid;
                                if (response.plot.data && response.plot.data.length > 0) {
                                    require(['block_learnerscript/report'], function(report) {
                                        report.generate_plotgraph(response.plot);
                                    });
                                } else {
                                    Str.get_string('nodataavailable', 'block_learnerscript').then(function(s) {
                                        $('#plotreportcontainer' +
                                        args.instanceid).html("<div class='alert alert-warning'>" + s + "</div>");
                                    });
                                }
                            }
                            $(document).ajaxStop(function() {
                                $("#reportloadingimage").remove();
                            });
                        }
                    });
            },
            combinationchart: function(chartdata) {
                var canvas = document.createElement('canvas');
                var random = Math.random();
                canvas.id = "mixedcanvas" + random;
                var body = document.getElementById(chartdata.containerid);
                body.appendChild(canvas);
                var cursorlayer = document.getElementById("mixedcanvas" + random).getContext("2d");
                new Chart(cursorlayer, {
                    type: 'bar',
                    data: {
                        datasets: chartdata.data,
                        labels: chartdata.categorydata
                    }
                });
            },
            lbchart: function(chartdata) {
                if (chartdata.type == 'bar') {
                    var canvas = document.createElement('canvas');
                    var random = Math.random();
                    canvas.id = "canvaschart" + random;
                    var body = document.getElementById(chartdata.containerid);
                    body.appendChild(canvas);
                    var cursorlayer = document.getElementById("canvaschart" + random).getContext("2d");
                    new Chart(cursorlayer, {
                        type: 'bar',
                        data: {
                            datasets: chartdata.data,
                            labels: chartdata.categorydata
                        },
                        options: {
                            indexAxis: 'y',
                        }
                    });
                } else {
                    var canvas = document.createElement('canvas');
                    var random = Math.random();
                    canvas.id = "canvaschart" + random;
                    var body = document.getElementById(chartdata.containerid);
                    body.appendChild(canvas);
                    var cursorlayer = document.getElementById("canvaschart" + random).getContext("2d");
                    new Chart(cursorlayer, {
                        type: 'bar',
                        data: {
                            datasets: chartdata.data,
                            labels: chartdata.categorydata
                        }
                    });
                }
            },
            radarchart: function(chartdata) {
                var canvas = document.createElement('canvas');
                var random = Math.random();
                canvas.id = "radarcanvas" + random;
                var body = document.getElementById('testaccess');
                body.appendChild(canvas);
                var cursorlayer = document.getElementById("radarcanvas" + random).getContext("2d");
                new Chart(cursorlayer, {
                    type: 'radar',
                    data: {
                        datasets: chartdata.data,
                        labels: chartdata.xAxis
                    },
                    options: {
                        plugins: {
                        legend: {
                            position: 'top',
                        },
                        filler: {
                            propagate: false
                        },
                        'samples-filler-analyser': {
                            target: 'chart-analyser'
                        }
                        },
                        interaction: {
                            intersect: false
                        }
                    }
                });
            },
            piechart: function(chartdata) {
                var canvas = document.createElement('canvas');
                var random = Math.random();
                canvas.id = "myPieChart" + random;
                var body = document.getElementById(chartdata.containerid);
                body.appendChild(canvas);
                var ctx = document.getElementById('myPieChart' + random).getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: chartdata.data,
                        datasets: [{
                            data: chartdata.datalabels,
                            backgroundColor: ['gold', 'yellowgreen', 'lightcoral', 'lightskyblue', 'violet']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: chartdata.title
                            }
                        }
                    }
                });
            },
        };
        return chart;
    });
