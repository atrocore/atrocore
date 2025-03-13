/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/admin/layouts/index', 'class-replace!treo-core:views/admin/layouts/index',
    Dep => Dep.extend({

        template: 'treo-core:admin/layouts/index',

        events: _.extend({}, Dep.prototype.events, {
            'click button[data-action="resetAllToDefault"]': function () {
                const options = this.getView('layoutProfile')?.params?.linkOptions || []
                const profile = options.find(l => l.id === this.model.get('layoutProfileId'))
                if (profile) {
                    this.confirm(this.translate('resetAllToDefaultConfirm', 'messages').replace(':name', profile.name), function () {
                        this.resetAllToDefault();
                    }, this);
                } else {
                    this.notify('No layout profile selected', 'error')
                }
            },
        }),

        resetAllToDefault: function () {
            this.notify('Saving...');
            this.ajaxPostRequest('Layout/action/resetAllToDefault?layoutProfileId=' + this.model.get('layoutProfileId')).then(() => {
                this.notify('Done', 'success');
                setTimeout(() => {
                    Espo.Ui.notify(this.translate('pleaseReloadPage'), 'info', 1000 * 10, true);
                }, 2000)
            });
        },
    })
);


