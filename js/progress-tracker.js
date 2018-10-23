(function ($) {
    $(document).ready(function () {
        var NotificationsManager = function (firebase, data, appName, recordId) {
            this.data = data;
            this.spaceId = window.firebaseIds.space_id;
            this.objectId = window.firebaseIds.object_id;

            if (data.config && data.token) {
                firebase.initializeApp(data.config, appName);
                firebase.auth().onAuthStateChanged(function (user) {
                    if (!user) {
                        firebase.auth().signInWithCustomToken(data.token);
                    }
                });
            }

            var thisContext = this;

            this.listen = function (event, callback) {
                firebase.database().ref()
                    .child("accounts")
                    .child(this.data.accountUid)
                    .child("projects")
                    .child(this.data.projectId)
                    .child(this.spaceId)
                    .child(this.objectId)
                    .child(recordId).on(event, function (snap) {
                    callback(snap, thisContext);
                });

                return this;
            };

            this.deleteRecord = function (recordId) {
                $.post(window.deleteNotificationEndpoint, {
                    project_id: this.data.projectId,
                    space_id: this.spaceId,
                    object_id: this.object_id,
                    record_id: recordId
                }, function (data) {
                    console.log(data);
                });
            }
        };

        if (!Object.prototype.hasOwnProperty.call(window, 'firebaseConfig')
            || !Object.prototype.hasOwnProperty.call(window, 'recordId')) {
            return;
        }

        var wrapperBlock = `<div class="${window.notificationClassName}"></div>`;
        $("body").append(wrapperBlock);

        Object.keys(window.firebaseConfig).forEach(function (e) {
            var notificationManager = new NotificationsManager(
                firebase,
                window.firebaseConfig[e],
                e == 0 ? "[DEFAULT]" : Math.random().toString(),
                recordId
            );

            var getBoxId = function (id) {
                return `box_${id}`;
            }

            var placeBox = function (id) {
                if (0 === $(`#${getBoxId(id)}`).length) {
                    var $wrapper = $(`.${window.notificationClassName}`);
                    $($wrapper).append(`<div id="${getBoxId(id)}" class="notification-message-box"></div>`);
                    $message = $(`#${getBoxId(id)}`);
                    $message.on("click", function () {
                        notificationManager.deleteRecord(recordId);
                        $message.slideUp(100, function () {
                            $message.remove();
                        });
                    });
                    $message.slideDown(100);
                }
            };

            var setMessage = function (id, severity, message) {
                var cssClass = `notification-message-box ${severity}`;
                $(`#${getBoxId(id)}`).attr("class", cssClass);
                $(`#${getBoxId(id)}`).html(message);
            }

            notificationManager
                .listen("child_changed", function (snap, notificationManager) {
                    placeBox(recordId);
                    var messageData = snap.val();
                    if (Object.prototype.hasOwnProperty.call(messageData, "severity")
                        && Object.prototype.hasOwnProperty.call(messageData, "message")) {
                        setMessage(recordId, messageData.severity, messageData.message);
                    }
                })
                .listen("child_removed", function (snap) {
                    var $target = $(`#${getBoxId((recordId))}`);
                    $target.slideUp(100, function () {
                        $target.remove();
                    });
                });
        });
    });

    $(document).ready(function () {
        var NotificationsManagerGeneral = function (firebase, data, appName) {
            this.data = data;
            this.spaceId = window.firebaseIds.space_id;
            this.objectId = window.firebaseIds.object_id;

            if (data.config && data.token) {
                firebase.initializeApp(data.config, appName);
                firebase.auth().onAuthStateChanged(function (user) {
                    if (!user) {
                        firebase.auth().signInWithCustomToken(data.token);
                    }
                });
            }

            var thisContext = this;

            this.listen = function (event, callback) {
                firebase.database().ref()
                    .child("accounts")
                    .child(this.data.accountUid)
                    .child("projects")
                    .child(this.data.projectId)
                    .child(this.spaceId)
                    .child(this.objectId).on(event, function (snap) {
                    callback(snap, thisContext);
                });

                return this;
            };

            this.deleteRecord = function (recordId) {
                $.post(window.deleteNotificationEndpoint, {
                    project_id: this.data.projectId,
                    space_id: this.spaceId,
                    object_id: this.object_id,
                    record_id: recordId
                }, function (data) {
                    console.log(data);
                });
            }
        };

        if (!Object.prototype.hasOwnProperty.call(window, 'firebaseConfig')) {
            return;
        }

        var wrapperBlock = `<div class="${window.notificationClassNameGeneral} hidden"></div>`;

        $("body").append(wrapperBlock);

        Object.keys(window.firebaseConfig).forEach(function (e) {
            var notificationManager = new NotificationsManagerGeneral(
                firebase,
                window.firebaseConfig[e],
                e == 0 ? "[DEFAULT]" : Math.random().toString()
            );

            var messageBoxSelector = 'smartling_notification_general';

            var placeBox = function () {
                if (0 === $(`#${messageBoxSelector}`).length) {
                    var $wrapper = $(`.${window.notificationClassNameGeneral}`);
                    $($wrapper).append(`<div id="${messageBoxSelector}" class="notification-message-box"></div>`);
                    var $message = $(`#${messageBoxSelector}`);
                    $message.on("click", function () {
                        var recordId = $(this).attr("data-record-id");
                        notificationManager.deleteRecord(recordId);
                        $message.slideUp(100, function () {
                            $message.remove();
                        });
                    });
                    $message.slideDown(100);
                }
            };

            var setMessage = function (severity, message, recordId) {
                var cssClass = `notification-message-box ${severity}`;
                $(`#${messageBoxSelector}`).attr("class", cssClass);
                $(`#${messageBoxSelector}`).attr("data-record-id", recordId);
                $(`#${messageBoxSelector}`).html(message);

                setTimeout(function () {
                    clearNotification(recordId);
                }, 10000);

            }

            var clearNotification = function (recordId) {
                var el = $(`#${messageBoxSelector} [data-record-id=${recordId}]`);
                if (0 !== el.length) {
                    $(el[0]).remove();
                }

                if (0 === $(`.${window.notificationClassNameGeneral} div`).length) {
                    $(`.${window.notificationClassNameGeneral}`).toggleClass('hidden');
                }
            };

            notificationManager
                .listen("child_changed", function (snap, notificationManager) {
                    eventAnimate();
                    placeBox();
                    var messageData = snap.val();

                    if (Object.prototype.hasOwnProperty.call(messageData.data, "severity")
                        && Object.prototype.hasOwnProperty.call(messageData.data, "message")) {
                        setMessage(messageData.data.severity, messageData.data.message, snap.key);
                    }
                })
                .listen("child_removed", function (snap) {
                    eventAnimate();
                    var $target = $(`messageBoxSelector [data-record-id=${snap.key}]`);
                    $target.slideUp(100, function () {
                        $target.remove();
                        clearNotification(snap.key);
                    });
                });
        });

        var eventAnimate = function () {
            jQuery('li.smartling-live-menu span.circle')
                .animate({backgroundColor: "#FF3333"}, 1) /* event color */
                .delay(300).animate({backgroundColor: "#888a85"}, 700);
        };

        $('.smartling-live-menu').on('click', function (e) {
            var identifier = `.${window.notificationClassNameGeneral}`;
            $(identifier).toggleClass('hidden');
        });
    });



})(jQuery);
