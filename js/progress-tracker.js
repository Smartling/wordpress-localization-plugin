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
                $(`#${getBoxId(id)}`).attr("class", `notification-message-box ${severity}`);
                $(`#${getBoxId(id)}`).html(message);
            }

            notificationManager
                .listen("child_changed", function (snap, notificationManager) {
                    console.log(snap.val());
                    placeBox(recordId);
                    var messageData = snap.val();
                    console.log(snap.val());
                    setMessage(recordId, messageData.severity, messageData.message);
                })
                .listen("child_removed", function (snap) {
                var $target = $(`#${getBoxId((recordId))}`);
                $target.slideUp(100, function () {
                    $target.remove();
                });
            });
        });
    });
})(jQuery);
