/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/connection/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.additionalButtons = [
                {
                    "action": "testConnection",
                    "label": this.translate('testConnection', 'labels', 'Connection')
                }
            ];

            if (this.model.get('type') === 'smtp') {
                this.additionalButtons = [
                    {
                        action: "sendTestEmail",
                        label: this.translate('sendTestEmail', 'labels', 'Connection')
                    }
                ]
                if (this.model.get('smtpAuthType') === 'oauth') {
                    this.additionalButtons.unshift({
                        "action": "authenticateSmtp",
                        "label": this.translate('authenticate', 'labels', 'Connection'),
                    });
                }
            }
        },

        actionAuthenticateSmtp() {
            if (!this.model.isNew() && this.model.get('smtpOauthAuthorizeUrl') && this.model.get('smtpClientId')) {
                const redirectUri = this.getConfig().get('siteUrl') + '/?entryPoint=OauthSmtpCallback';
                window.open(`${this.model.get('smtpOauthAuthorizeUrl')}?client_id=${this.model.get('smtpClientId')}&response_type=code&redirect_uri=${redirectUri}&response_mode=query&scope=${this.model.get('smtpOauthScope')}&state=${this.model.get('id')}`);
            }
        },

        actionSendTestEmail() {
            this.createView('popup', 'views/outbound-email/modals/test-send', {
                emailAddress: this.getUser().get('emailAddress')
            }, function (view) {
                view.render();

                this.listenToOnce(view, 'send', function (emailAddress) {
                    this.$el.find('button').addClass('disabled');

                    this.notify('Sending...');

                    view.close();

                    $.ajax({
                        url: 'Connection/action/sendTestEmail',
                        type: 'POST',
                        data: JSON.stringify({
                            id: this.model.get('id'),
                            email: emailAddress
                        }),
                        error: function (xhr, status) {
                            var statusReason = xhr.responseText || '';
                            statusReason = statusReason.replace(/ $/, '');
                            statusReason = statusReason.replace(/,$/, '');

                            var msg = this.translate('Error') + ' ' + xhr.status;
                            if (statusReason) {
                                msg += ': ' + statusReason;
                            }
                            Espo.Ui.error(msg);
                            console.error(msg);
                            xhr.errorIsHandled = true;

                            this.$el.find('button').removeClass('disabled');
                        }.bind(this)
                    }).done(function () {
                        this.$el.find('button').removeClass('disabled');
                        Espo.Ui.success(this.translate('testEmailSent', 'messages'));
                    }.bind(this));
                }, this);
            }.bind(this));
        }
    });
});
