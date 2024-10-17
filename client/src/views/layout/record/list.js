/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        actionQuickEdit: function (data) {
            var model = null;
            if (this.collection && data.id) {
                model = this.collection.get(data.id);
            }
            if (!model) {
                return;
            }
            this.createView('dialog', 'views/admin/layouts/modals/edit', {
                scope: model.get('entity'),
                type: model.get('viewType'),
                layoutProfileId: this.getLayoutProfileId(),
                allowSwitch: false,
                el: '[data-view="dialog"]',
            }, view => {
                view.render()
            })
        },

        actionQuickRemove: function (data) {
            var model = null;
            if (this.collection && data.id) {
                model = this.collection.get(data.id);
            }
            if (!model) {
                return;
            }

            this.collection.trigger('model-removing', data.id);
            this.collection.remove(model);
            this.notify('removing');

            this._helper.layoutManager.resetToDefault(model.get('entity'), model.get('viewType'),
                this.getLayoutProfileId(), () => {
                    this.notify('Removed', 'success');
                    this.removeRecordFromList(data.id);
                })
        },

        getLayoutProfileId() {
            return this.getParentView().model.get('id')
        }
    });

});
