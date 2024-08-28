/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/admin/layouts/base', 'class-replace!treo-core:views/admin/layouts/base',
    Dep => Dep.extend({

        events: _.extend({}, Dep.prototype.events, {
            'click button[data-action="save"]': function () {
                this.disableButtons();
                this.notify('Saving...');
                if (!this.save(this.enableButtons.bind(this))) {
                    this.notify(false)
                }
            }
        }),

        save: function (callback) {
            const layout = this.fetch();

            if (!this.validate(layout)) {
                this.enableButtons();
                return false;
            }

            this.getHelper().layoutManager.set(this.scope, this.type, this.layoutProfileId, layout, function () {
                Espo.Ui.success(this.translate('Saved'));

                if (typeof callback === 'function') {
                    callback();
                }
            }.bind(this));
        },
    })
);
