(function ($) {
    $(document).ready(function () {
        var NotificationsManager = function (firebase, data, appName) {
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

        var wrapperBlock = `<div class="${window.notificationClassName}"></div>`;
        $("body").append(wrapperBlock);

        Object.keys(window.firebaseConfig).forEach(function (e) {
            var notificationManager = new NotificationsManager(
                firebase,
                window.firebaseConfig[e],
                e == 0 ? "[DEFAULT]" : Math.random().toString()
            );

            notificationManager.listen("child_added", function (snap, notificationManager) {
                var id = snap.key;
                var messageData = snap.val().data;
                var $wrapper = $(`.${window.notificationClassName}`);
                var messageBlock = `<div id="${id}" role="contentinfo" aria-label="Status message" class="notification-message-box ${messageData.severity}">${messageData.message}</div>`;
                var $message = $(messageBlock);
                $message.on("click", function () {
                    notificationManager.deleteRecord(id);
                    $message.slideUp(100, function () {
                        $message.remove();
                    });
                });
                $wrapper.append($message);
                $message.slideDown(100);
            }).listen("child_removed", function (snap) {
                var $target = $(`#${snap.key}`);
                $target.slideUp(100, function () {
                    $target.remove();
                });
            });
        });
    });
})(jQuery);
