/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/dashlets/fields/records/entity-filter', 'views/fields/base',
    Dep => Dep.extend({

        editTemplate: 'dashlets/fields/records/entity-filter',

        setupSearchPanel() {
            this.getModelFactory().create(this.model.get('entityType'), model => {
                model.set('data', this.model.get('entityFilter'));
                this.createView('search', 'views/dashlets/panels/search', {
                    el: `${this.options.el} .search-container`,
                    model: model,
                    mainModel: this.model,
                    mode: 'edit'
                }, view => {
                    view.render();
                });
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.setupSearchPanel()
        },

    })
);
