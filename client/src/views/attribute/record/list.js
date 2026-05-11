/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/record/list', 'views/record/list',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            (this.getMetadata().get(['clientDefs', this.scope, 'disabledMassActions']) || []).forEach(item => this.removeMassAction(item));
        },

        prepareRemoveSelectedRecordsConfirmationMessage() {
            return this.translate('removeAttribute(s)', 'messages', 'Attribute');
        },

        clearFilters(id) {
            var presetFilters = this.getPreferences().get('presetFilters') || {};
            if (!('Product' in presetFilters)) {
                presetFilters['Product'] = [];
            }

            presetFilters['Product'].forEach(function (item, index, obj) {
                for (let filterField in item.data) {
                    let name = filterField.split('-')[0];

                    if (name === id) {
                        delete obj[index].data[filterField]
                    }
                }
            }, this);
            presetFilters['Product'] = presetFilters['Product'].filter(item => Object.keys(item.data).length > 0);

            this.getPreferences().set('presetFilters', presetFilters);
            this.getPreferences().save({patch: true});
            this.getPreferences().trigger('update');
            let filters = this.getStorage().get('listSearch', 'Product');
            if (filters && filters.advanced) {
                for (let filter in filters.advanced) {
                    let name = filter.split('-')[0];

                    if (name === id) {
                        delete filters.advanced[filter]
                    }
                }

                if (filters.presetName && !presetFilters['Product'].includes(filters.presetName)) {
                    filters.presetName = null
                }

                this.getStorage().set('listSearch', 'Product', filters);
            }
        }

    })
);

