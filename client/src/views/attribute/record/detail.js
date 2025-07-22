/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        sideView: "views/attribute/record/detail-side",

        bottomView: 'views/attribute/record/detail-bottom',

        delete: function () {
            Espo.TreoUi.confirmWithBody('', {
                confirmText: this.translate('Remove'),
                cancelText: this.translate('Cancel'),
                body: this.getBodyHtml()
            }, function () {
                this.trigger('before:delete');
                this.trigger('delete');

                this.notify('Removing...');

                var collection = this.model.collection;

                var self = this;
                this.model.destroy({
                    wait: true,
                    error: function () {
                        this.notify('Error occured!', 'error');
                    }.bind(this),
                    success: function () {
                        if (collection) {
                            if (collection.total > 0) {
                                collection.total--;
                            }
                        }

                        this.clearFilters();

                        this.notify('Removed', 'success');
                        this.trigger('after:delete');
                        this.exit('delete');
                    }.bind(this),
                });
            }, this);
        },

        getBodyHtml() {
            return '' +
                '<div class="row">' +
                '<div class="col-xs-12">' +
                '<span class="confirm-message">' + this.translate('removeAttribute(s)', 'messages', 'Attribute') + '</span>' +
                '</div>' +
                '</div>';
        },

        clearFilters() {
            var presetFilters = this.getPreferences().get('presetFilters') || {};
            if (!('Product' in presetFilters)) {
                presetFilters['Product'] = [];
            }

            presetFilters['Product'].forEach(function (item, index, obj) {
                for (let filterField in item.data) {
                    let name = filterField.split('-')[0];

                    if (name === this.model.id) {
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

                    if (name === this.id) {
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