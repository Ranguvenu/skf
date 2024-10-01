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
 * Standard Ajax wrapper for LearnerScript Reports. It calls the central Ajax script,
 *
 * @module     block_learnerscript/ajax
 * @class      ajax
 * @package
 * @copyright  2023 Moodle India Information Solutions Private Limited
 */
define(['jquery','core/str', 'core/config', 'core/log', 'core/modal_factory'], function($, Str, config, Log, ModalFactory) {
    // Keeps track of when the user leaves the page so we know not to show an error.
    var unloading = false;
    /**
     * Success handler. Called when the ajax call succeeds. Checks each response and
     * resolves or rejects the deferred from that request.
     *
     * @method requestSuccess
     * @private
     * @param {object} response containing error, exception and data attributes.
     */
    var requestSuccess = function(response) {
        // Call each of the success handlers.
        var request = this;
        if (response === null) {
            return;
        }
        if (response.error) {
            // There was an error with the request as a whole.
            // We need to reject each promise.
            // Unfortunately this may lead to duplicate dialogues, but each Promise must be rejected.
            if (response.cap || response.debuginfo || response.errorcode) {
                var msg = response.msg || response.error;
                ModalFactory.create({
                    title: response.type || response.errorcode,
                    body: '<p>' + msg + '</p>',
                    footer: '',
                }).done(function(modal) {
                    var dialogue = modal;
                    // Display the dialogue.
                    dialogue.show();
                });
            } else {
                Log.error(response.type + ': ' + response.msg);
            }
            request.deferred.reject(response);
            return;
        }
        var exception;
        // We may not have responses for all the requests.
        if (typeof response !== "undefined") {
            // Call the done handler if it was provided.
            request.deferred.resolve(response);
        } else {
            // This is not an expected case.
            Str.get_string('missing_response', 'block_learnerscript').then(function(s){
                exception = new Error(s);
            });
        }
        // Something failed, reject the remaining promises.
        if (typeof(exception) !== 'undefined' && exception !== null) {
            request.deferred.reject(exception);
        }
    };
    /**
     * Fail handler. Called when the ajax call fails. Rejects all deferreds.
     *
     * @method requestFail
     * @private
     * @param {jqXHR} jqXHR The ajax object.
     * @param {string} textStatus The status string.
     * @param {Error|Object} exception The error thrown.
     */
    var requestFail = function(jqXHR, textStatus, exception) {
        // Reject all the promises.
        var request = this;
        if (unloading) {
            // No need to trigger an error because we are already navigating.
            Str.get_string('page_unloaded', 'block_learnerscript').then(function(s) {
                Log.error(s);
            });
            Log.error(exception);
        } else {
            Str.get_string('page_not_responding', 'block_learnerscript').then(function(s) {
                Log.error(s);
            });
            Log.error(exception);
            request.deferred.reject(exception);
        }
    };
    return /** @alias module:core/ajax */ {
        // Public variables and functions.
        /**
         * Make a series of ajax requests and return all the responses.
         *
         * @method call
         * @param {Object[]} request Array of requests with each containing methodname and args properties.
         *                   done and fail callbacks can be set for each element in the array, or the
         *                   can be attached to the promises returned by this function.
         * @param {Boolean} async Optional, defaults to true.
         *                  If false - this function will not return until the promises are resolved.
         * @param {Boolean} loginrequired Optional, defaults to true.
         *                  If false - this function will call the faster nologin ajax script - but
         *                  will fail unless all functions have been marked as 'loginrequired' => false
         *                  in services.php
         * @return {Promise[]} Array of promises that will be resolved when the ajax call returns.
         */
        call: function(request, async, loginrequired) {
            $(window).bind('beforeunload', function() {
                unloading = true;
            });
            var ajaxRequestData,
                promise = {};
            if (typeof loginrequired === "undefined") {
                loginrequired = true;
            }
            if (typeof async === "undefined") {
                async = true;
            }
            ajaxRequestData = request.args;
            request.deferred = $.Deferred();
            promise = request.deferred.promise();
            // Allow setting done and fail handlers as arguments.
            // This is just a shortcut for the calling code.
            if (typeof request.done !== "undefined") {
                request.deferred.done(request.done);
            }
            if (typeof request.fail !== "undefined") {
                request.deferred.fail(request.fail);
            }
            ajaxRequestData = JSON.stringify(ajaxRequestData);
            var settings = {
                type: 'POST',
                data: ajaxRequestData,
                context: request,
                dataType: 'json',
                processData: false,
                global: true,
                async: async,
                contentType: "application/json",
                beforeSend: function() {
                    // Handle the beforeSend event
                    return request.loading && $(request.loading).show();
                },
                success: function() {
                    return request.loading && $(request.loading).hide('fast');
                    // Handle the complete event
                }
            };
            // Jquery deprecated done and fail with async=false so we need to do this 2 ways.
            if (async) {
                $.ajax(request.url + '?sesskey=' + config.sesskey, settings).done(requestSuccess).fail(requestFail);
            } else {
                settings.success = requestSuccess;
                settings.error = requestFail;
                $.ajax(request.url + '?sesskey=' + config.sesskey, settings);
            }
            return promise;
        }
    };
});
