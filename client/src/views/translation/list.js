/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/translation/list', 'views/list', function (Dep) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.menu.dropdown = [];

            this.menu.dropdown.push({
                acl: 'create',
                aclScope: 'Label',
                action: 'reset',
                label: this.translate('reset', 'labels', 'Translation'),
                iconHtml: ''
            });
        },

        actionReset() {
            this.confirm({
                message: this.translate('resetConfirm', 'messages', 'Translation'),
                confirmText: this.translate('Apply')
            }, () => {
                this.ajaxPostRequest(`Translation/action/reset`).then(response => {
                    this.notify(this.translate('resetSuccessfully', 'messages', 'Translation'), 'success');
                });
            });
        },

    });
});

