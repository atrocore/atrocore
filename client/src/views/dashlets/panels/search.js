/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/dashlets/panels/search', 'views/record/panels/search',
    Dep => Dep.extend({

        setup() {
            this.scope = this.options.mainModel.get('entityType');

            Dep.prototype.setup.call(this);

            this.listenTo(this.options.mainModel, 'before:save', attributes => {
                attributes.entityFilter = this.getFilterData() || {};
            });

            this.listenTo(this.options.mainModel, 'change:entityType', () => {
                if (this.options.mainModel.get('entityType') !== this.scope) {
                    this.scope = this.options.mainModel.get('entityType');
                    this.model.set({data: {}});

                    this.setupSearchPanel();
                }
            });
        },

    })
);
