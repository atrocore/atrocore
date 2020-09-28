

Espo.define('views/modals/password-change-request', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'password-change-request',

        template: 'modals/password-change-request',

        setup: function () {

            this.buttonList = [
                {
                    name: 'submit',
                    label: 'Submit',
                    style: 'danger'
                },
                {
                    name: 'cancel',
                    label: 'Close'
                }
            ];

            this.header = this.translate('Password Change Request', 'labels', 'User');
        },

        actionSubmit: function () {
            var $userName = this.$el.find('input[name="userName"]');
            var $emailAddress = this.$el.find('input[name="emailAddress"]');

            $userName.popover('destroy');
            $emailAddress.popover('destroy');

            var userName = $userName.val();
            var emailAddress = $emailAddress.val();

            var isValid = true;
            if (userName == '') {
                isValid = false;

                var message = this.getLanguage().translate('userCantBeEmpty', 'messages', 'User');

                $userName.popover({
                    placement: 'bottom',
                    content: message,
                    trigger: 'manual',
                }).popover('show');

                var $cellUserName = $userName.closest('.form-group');
                $cellUserName.addClass('has-error');

                $userName.one('mousedown click', function () {
                    $cellUserName.removeClass('has-error');
                    $userName.popover('destroy');
                });
            }

            var isValid = true;
            if (emailAddress == '') {
                isValid = false;

                var message = this.getLanguage().translate('emailAddressCantBeEmpty', 'messages', 'User');

                $emailAddress.popover({
                    placement: 'bottom',
                    content: message,
                    trigger: 'manual',
                }).popover('show');

                var $cellEmailAddress = $emailAddress.closest('.form-group');
                $cellEmailAddress.addClass('has-error');

                $emailAddress.one('mousedown click', function () {
                    $cellEmailAddress.removeClass('has-error');
                    $emailAddress.popover('destroy');
                });
            }

            if (!isValid) return;

            $submit = this.$el.find('button[data-name="submit"]');
            $submit.addClass('disabled');
            this.notify('Please wait...');

            $.ajax({
                url: 'User/passwordChangeRequest',
                type: 'POST',
                data: JSON.stringify({
                    userName: userName,
                    emailAddress: emailAddress,
                    url: this.options.url
                }),
                error: function (xhr) {
                    if (xhr.status == 404) {
                        this.notify(this.translate('userNameEmailAddressNotFound', 'messages', 'User'), 'error');
                        xhr.errorIsHandled = true;
                    }
                    if (xhr.status == 403) {
                        this.notify(this.translate('forbidden', 'messages', 'User'), 'error');
                        xhr.errorIsHandled = true;
                    }
                    $submit.removeClass('disabled');
                }.bind(this)
            }).done(function () {
                this.notify(false);

                var msg = this.translate('uniqueLinkHasBeenSent', 'messages', 'User');

                this.$el.find('.cell-userName').addClass('hidden');
                this.$el.find('.cell-emailAddress').addClass('hidden');

                $submit.addClass('hidden');

                this.$el.find('.msg-box').removeClass('hidden');

                this.$el.find('.msg-box').html('<span class="text-success">' + msg + '</span>');
            }.bind(this));
        }

    });
});

