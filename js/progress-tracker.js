(function ($) {

    $(document).ready(function () {

        if (!Object.prototype.hasOwnProperty.call(window, 'firebaseConfig')) {
            return;
        }

        var NotificationsManagerGeneric = function (account, project, space, object, record) {
            this.accountUid = account;
            this.projectId = project;
            this.spaceId = space;
            this.objectId = object;
            this.recordId = record;

            var thisContext = this;

            var getObjectPath = function (c) {
                return `accounts/${c.accountUid}/projects/${c.projectId}/${c.spaceId}/${c.objectId}`;
            };

            var getRecordPath = function (c) {
                return `${getObjectPath(c)}/${c.recordId}`;
            }

            this.listenObject = function (event, callback) {
                firebase.database().ref(getObjectPath(thisContext)).on(event, function (snap) {
                    callback(snap, thisContext);
                })
            };

            this.listenRecord = function (event, callback) {
                firebase.database().ref(getRecordPath(thisContext)).on(event, function (snap) {
                    callback(snap, thisContext);
                })
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

        var eventAnimate = function () {
            $('li.smartling-live-menu span.circle')
                .animate({backgroundColor: "#FF3333"}, 1) /* event color */
                .delay(300)
                .animate({backgroundColor: "#888a85"}, 700);
        };

        var objectNotificationWrapperBlock = `<div class="${window.notificationClassNameGeneral} hidden"></div>`;
        $("body").append(objectNotificationWrapperBlock);

        var recordNotificationWrapperBlock = `<div class="${window.notificationClassName}"></div>`;
        $("body").append(recordNotificationWrapperBlock);

        var connectToFirebase = function (data, appName) {
            if (data.config && data.token) {
                firebase.initializeApp(data.config, appName);
                firebase.auth().onAuthStateChanged(function (user) {
                    if (!user) {
                        firebase.auth().signInWithCustomToken(data.token);
                    }
                });
            }
        };

        var setMessage = function (messageBox, severity, message, recordId) {
            var cssClass = `notification-message-box ${severity}`;
            $(`#${messageBox}`).attr("class", cssClass);
            $(`#${messageBox}`).attr("data-record-id", recordId);
            $(`#${messageBox}`).html(message);

            setTimeout(function () {
                try {
                    jQuery(`div.notification-message-box [data-record-id=${recordId}]`).remove();
                } catch (e) {

                }
            }, 10000);

        };

        var validateData = function (data) {
            return Object.prototype.hasOwnProperty.call(data, "severity")
                && Object.prototype.hasOwnProperty.call(data, "message");
        };

        var placeBox = function (wrapper, id, manager) {
            var msgId = `#${id}`;
            if (0 === $(msgId).length) {
                $(`.${wrapper}`).append(`<div id="${id}" class="notification-message-box"></div>`);
                var $message = $(msgId);
                $message.on("click", function () {
                    var recordId = $(this).attr("data-record-id");
                    manager.deleteRecord(recordId);
                    $message.slideUp(100, function () {
                        $message.remove();
                    });
                });
                $message.slideDown(100);
            }
        };

        var addObjectHandlers = function (manager, recordId) {

            var messageBoxSelector = 'smartling_notification_general';

            manager.listenObject("child_changed", function (snap, notificationManager) {
                eventAnimate();
                placeBox(window.notificationClassNameGeneral, messageBoxSelector, notificationManager);
                var messageData = snap.val();
                if (validateData(messageData.data) && recordId !== snap.key) {
                    setMessage(messageBoxSelector, messageData.data.severity, messageData.data.message, snap.key);
                }
            });

            manager.listenObject("child_removed", function (snap) {
                eventAnimate();
                var $target = $(`messageBoxSelector [data-record-id=${snap.key}]`);
                $target.slideUp(100, function () {
                    try {
                        var wrapper = $target.parent();
                        $target.remove();

                        if (0 === jQuery(`.${window.notificationClassNameGeneral} div`).length
                            && !$jQuery(wrapper).hasClass('hidden')) {
                            jQuery(wrapper).toggleClass('hidden');
                        }
                    } catch (e) {
                    }
                });
            })
        };

        var addRecordHandlers = function (manager, recordId) {

            var getBoxId = function (id) {
                return `box_${id}`;
            };

            manager.listenRecord("child_changed", function (snap, notificationManager) {
                placeBox(window.notificationClassName, getBoxId(recordId), notificationManager);
                var messageData = snap.val();
                if (validateData(messageData)) {
                    var messageBox = getBoxId(recordId);
                    setMessage(messageBox, messageData.severity, messageData.message, recordId);
                }
            });

            manager.listenRecord("child_removed", function (snap, notificationManager) {
                var $target = $(`#${getBoxId(recordId)}`);
                $target.slideUp(100, function () {
                    $target.remove();
                });
            });
        };

        var initNotificationManagers = function (data) {
            var recordId = Object.prototype.hasOwnProperty.call(window, 'recordId') ? window.recordId : undefined;

            var manager = new NotificationsManagerGeneric(
                data.accountUid,
                data.projectId,
                window.firebaseIds.space_id,
                window.firebaseIds.object_id,
                recordId
            );

            addObjectHandlers(manager, recordId);

            if (undefined !== recordId) {
                addRecordHandlers(manager, recordId);
            }
        };

        Object.keys(window.firebaseConfig).forEach(function (el) {
            var appName = el == 0 ? "[DEFAULT]" : el;
            var data = window.firebaseConfig[el];

            connectToFirebase(data, appName);
            initNotificationManagers(data);
        });

        $('.smartling-live-menu').on('click', function (e) {
            var identifier = `.${window.notificationClassNameGeneral}`;
            $(identifier).toggleClass('hidden');
        });
    });
})(jQuery);
