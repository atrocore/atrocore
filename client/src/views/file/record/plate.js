/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/plate', 'views/record/list',
    Dep => Dep.extend({

        template: 'file/record/plate',

        type: 'plate',

        name: 'plate',

        listContainerEl: '.list > div > .plate > .row',
    
        massActionList              : ['remove', 'massUpdate'],
    
        checkAllResultMassActionList: ['remove', 'massUpdate'] ,

        itemsInRow: null,

        itemsInRowOptions: [],
        
        events: _.extend({
            'click .item-container': function (e) {
                const id = $(e.currentTarget).data('id');
                if (id
                    && !$.contains(this.$el.find(`.item-container[data-id="${id}"] .actions`).get(0), e.target)
                    && !$.contains(this.$el.find(`.item-container[data-id="${id}"] .field-name`).get(0), e.target)
                ) {
                    e.stopPropagation();
                    e.preventDefault();

                    this.actionQuickView({id});
                }
            },
            'click [data-action="sortByDirection"]': function (e) {
                e.stopPropagation();
                e.preventDefault();
                this.sortByDirection();
            },
            'click [data-action="sortByField"]': function (e) {
                e.stopPropagation();
                e.preventDefault();
                this.sortByField($(e.currentTarget).data('name'));
            }
        }, Dep.prototype.events),

        data() {
            return _.extend({
                sortFields: this.getSortFieldsList(),
                itemsInRowOptions: this.itemsInRowOptions,
                itemsInRow: this.itemsInRow,
                itemContainerWidth: 100 / this.itemsInRow
            }, Dep.prototype.data.call(this));
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.initItemsInRow();
        },

        hasExternalSelectAllCheckbox: function() {
            return true;
        },

        getActionsComponent: function () {
            return Svelte.PlateActionsContainer;
        },

        getActionsProperties: function (component) {
            const props = Dep.prototype.getActionsProperties.call(this, component);

            props.itemsInRow = this.itemsInRow;
            props.itemsInRowOptions = this.itemsInRowOptions;
            props.changeItemsInRow = (number) => {
                this.setItemsInRow(number);
            };

            props.sortBy = this.collection.sortBy;
            props.sortDirection = this.collection.asc ? 'asc' : 'desc';
            props.sortByOptions = this.getSortFieldsList();
            props.changeSortField = (field) => {
                this.sortByField(field);
                component.$set({sortBy: field});
            };
            props.changeSortDirection = (asc) => {
                this.toggleSort(this.collection.sortBy);
                component.$set({sortDirection: this.collection.asc ? 'asc' : 'desc'});
            }

            return props;
        },

        getSortFieldsList() {
            const fields = [];
            const fieldDefs = this.getMetadata().get(['entityDefs', this.scope, 'fields']);
            for (let field in fieldDefs) {
                if (!fieldDefs[field].disabled
                    && !fieldDefs[field].layoutListDisabled
                    && !this.getMetadata().get(['fields', fieldDefs[field].type, 'notSortable'])
                    && ['varchar', 'text', 'int', 'float', 'date', 'datetime'].includes(fieldDefs[field].type)
                ) {
                    fields.push(field);
                }
            }

            fields.sort(function (v1, v2) {
                return this.translate(v1, 'fields', this.scope).localeCompare(this.translate(v2, 'fields', this.scope));
            }.bind(this));

            return fields;
        },

        buildRow(i, model, callback) {
            const key = model.id;

            this.rowList.push(key);

            const acl =  {
                edit: this.getAcl().checkModel(model, 'edit'),
                delete: this.getAcl().checkModel(model, 'delete')
            };
            this.createView(key, this.getItemView(), {
                model: model,
                acl: acl,
                el: this.options.el + ' .item-container[data-id="' + key +'"]',
                optionsToPass: ['acl'],
                noCache: true,
                name: this.type + '-' + model.name,
                setViewBeforeCallback: this.options.skipBuildRows && !this.isRendered(),
                rowActionsView: this.rowActionsView
            }, callback);
        },

        getItemView() {
            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.plateItem') || 'views/file/record/plate-item';
        },

        getRowSelector(id) {
            return `.item-container[data-id="${id}"]`;
        },

        getSelectAttributeList(callback) {
            callback(['name', 'type', 'modifiedAt']);
        },

        getRowContainerHtml(id) {
            return `<div class="col-xs-6 col-sm-3 item-container" data-id="${id}"></div>`;
        },

        checkRecord(id, $target, isSilent) {
            Dep.prototype.checkRecord.call(this, id, $target, isSilent);

            $target = $target || this.$el.find('.record-checkbox[data-id="' + id + '"]');
            if ($target.get(0)) {
                $target.closest('.plate-item').addClass('active');
            }
        },

        uncheckRecord(id, $target, isSilent) {
            Dep.prototype.uncheckRecord.call(this, id, $target, isSilent);

            $target = $target || this.$el.find('.record-checkbox[data-id="' + id + '"]');
            if ($target.get(0)) {
                $target.closest('.plate-item').removeClass('active');
            }
        },

        selectAllHandler(isChecked) {
            Dep.prototype.selectAllHandler.call(this, isChecked);

            var plateItems = this.$el.find('.list .plate-item');
            if (isChecked) {
                plateItems.addClass('active');
            } else {
                plateItems.removeClass('active');
            }
        },

        selectAllResult() {
            Dep.prototype.selectAllResult.call(this);

            this.$el.find('.list .plate-item').removeClass('active');
        },

        sortByDirection() {
            this.toggleSort(this.collection.sortBy);
        },

        sortByField(field) {
            var asc = this.collection.asc;

            this.notify('Please wait...');
            this.collection.once('sync', function () {
                this.notify(false);
                this.trigger('sort', {sortBy: field, asc: asc});
            }, this);
            var maxSizeLimit = this.getConfig().get('recordListMaxSizeLimit') || 200;
            while (this.collection.length > maxSizeLimit) {
                this.collection.pop();
            }
            this.collection.sort(field, asc);
            this.deactivate();
        },


        initItemsInRow() {
            const $window = $(window);
            this.itemsInRow = this.getStorage().get('itemsInRow', `plate-${this.scope}`) || this.getItemsInRowToWindowWidth($window.width());
            this.itemsInRowOptions = [1, 2, 3, 4, 5, 6];
        },

        setItemsInRow(count) {
            if (this.itemsInRow !== count) {
                this.itemsInRow = count;
                this.reRender();

                this.getStorage().set('itemsInRow', `plate-${this.scope}`, this.itemsInRow);
            }
        },

        getItemsInRowToWindowWidth(windowWidth) {
            let count = this.itemsInRow;
            if (windowWidth < 768) {
                count = 2;
            } else if (windowWidth < 992) {
                count = 3;
            } else if (windowWidth < 1200) {
                count = 4;
            } else {
                count = 5;
            }
            return count;
        }

    })
);

