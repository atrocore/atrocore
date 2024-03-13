/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/settings/record/unit-configuration-list', 'views/record/list',
    Dep => Dep.extend({

        actionQuickEditCustom(data) {
            data = data || {};
            let id = data.id;
            if (!id) return;

            let model = null;
            if (this.collection) {
                model = this.collection.get(id);
            }
            if (!data.scope && !model) {
                return;
            }

            Espo.Ui.notify(this.translate('loading', 'messages'));
            this.createView('modal', 'treo-core:views/settings/modals/unit-edit', {
                model: model,
                id: id
            }, view => {
                view.once('after:render', function () {
                    Espo.Ui.notify(false);
                });

                this.listenToOnce(view, 'remove', () => {
                    this.clearView('modal');
                });

                this.listenToOnce(view, 'after:save', m => {
                    let model = this.collection.get(m.id);
                    if (model) {
                        model.set(m.getClonedAttributes());
                    }
                    this.trigger('update-configuration');
                });
                view.render();
            });
        },

    })
);

