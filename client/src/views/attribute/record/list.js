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

        massActionRemove: function (data, permanently = false) {
            if (!this.getAcl().check(this.entityType, 'delete')) {
                this.notify('Access denied', 'error');
                return false;
            }

            var count = this.checkedList.length;
            var deletedCount = 0;

            var self = this;

            Espo.TreoUi.confirmWithBody('', {
                confirmText: this.translate('Remove'),
                cancelText: this.translate('Cancel'),
                body: this.getBodyHtml(),
            }, function () {
                this.notify('Removing...');

                var ids = [];
                var data = { permanently: permanently };
                if (this.allResultIsChecked) {
                    data.where = this.collection.getWhereForCheckedRecords();
                    data.selectData = this.collection.data || {};
                    data.byWhere = true;
                } else {
                    data.ids = ids;
                }

                for (var i in this.checkedList) {
                    ids.push(this.checkedList[i]);
                }

                $.ajax({
                    url: this.entityType + '/action/massDelete',
                    type: 'POST',
                    data: JSON.stringify(data)
                }).done(function (result) {
                    result = result || {};
                    var count = result.count;
                    if (this.allResultIsChecked) {
                        if (count) {
                            this.unselectAllResult();
                            this.listenToOnce(this.collection, 'sync', function () {
                                var msg = 'massRemoveResult';
                                if (count == 1) {
                                    msg = 'massRemoveResultSingle'
                                }
                                Espo.Ui.success(this.translate(msg, 'messages').replace('{count}', count));
                            }, this);
                            Espo.Ui.notify(false);
                        } else {
                            Espo.Ui.warning(self.translate('noRecordsRemoved', 'messages'));
                        }
                    } else {
                        var idsRemoved = result.ids || [];
                        if (count) {
                            idsRemoved.forEach(function (id) {
                                Espo.Ui.notify(false);
                                this.checkedList = [];

                                this.collection.trigger('model-removing', id);
                                this.removeRecordFromList(id);
                                this.uncheckRecord(id, null, true);

                            }, this);
                            var msg = 'massRemoveResult';
                            if (count == 1) {
                                msg = 'massRemoveResultSingle'
                            }
                            Espo.Ui.success(self.translate(msg, 'messages').replace('{count}', count));
                        } else {
                            Espo.Ui.warning(self.translate('noRecordsRemoved', 'messages'));
                        }
                    }

                    if ('sync' in result && result.sync === true) {
                        this.collection.fetch();
                    }
                }.bind(this));
            }, this);
        },

        actionQuickRemove: function (data) {
            data = data || {};
            var id = data.id;
            if (!id) return;

            var model = this.collection.get(id);
            if (!this.getAcl().checkModel(model, 'delete')) {
                this.notify('Access denied', 'error');
                return false;
            }

            Espo.TreoUi.confirmWithBody('', {
                confirmText: this.translate('Remove'),
                cancelText: this.translate('Cancel'),
                body: this.getBodyHtml()
            }, function () {
                this.collection.trigger('model-removing', id);
                this.collection.remove(model);
                this.notify('Removing...');
                model.destroy({
                    wait: true,
                    data: JSON.stringify({force: $('.force-remove').is(':checked')}),
                    success: function () {
                        this.notify('Removed', 'success');
                        this.removeRecordFromList(id);
                        this.clearFilters(id);
                    }.bind(this),
                    error: function() {
                        this.notify('Error occured', 'error');
                        this.collection.push(model);
                    }.bind(this),
                    complete: function () {
                        this.collection.fetch();
                    }.bind(this)
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

