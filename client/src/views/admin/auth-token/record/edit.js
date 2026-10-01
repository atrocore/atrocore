/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/auth-token/record/edit', 'views/record/edit', function (Dep) {

    return Dep.extend({

        afterSave: function () {
            // the token is stored hashed, so only the response to the creation carries it
            const authToken = this.isNew ? this.model.get('authToken') : null;

            Dep.prototype.afterSave.call(this);

            if (!authToken) {
                return;
            }

            this.model.unset('authToken', {silent: true});

            window.Notifier.notify(this.translate('authTokenCreated', 'messages', 'AuthToken') + '<br><code>' + authToken + '</code>', {
                type: 'success',
                duration: -1,
                closeButton: true,
                actions: [{
                    tooltip: this.translate('Copy', 'labels'),
                    iconClass: 'ph ph-copy',
                    callback: () => {
                        this.copyToClipboard(authToken, copied => {
                            if (copied) {
                                this.notify(this.translate('copiedToClipboard', 'labels'), 'success');
                            } else {
                                this.notify('Error copying text to clipboard', 'danger');
                            }
                        });
                    },
                }],
            });

        },

    });
});
