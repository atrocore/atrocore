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
    
        massActionList              : ['remove', 'update'],

        checkAllResultMassActionList: ['remove', 'update'] ,

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
            }
        }, Dep.prototype.events),

        data() {
            return _.extend({
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

        getToolbarControls: function () {
            const controls = Dep.prototype.getToolbarControls.call(this);
            controls.unshift(this.getItemsInRowToolbarControl());

            return controls;
        },

        getItemsInRowToolbarControl: function () {
            return {
                key: 'itemsInRow',
                iconClass: 'ph ph-squares-four',
                iconTitle: this.translate('itemsInRow', 'labels'),
                value: this.itemsInRow,
                options: this.itemsInRowOptions.map(function (count) {
                    return {value: count, label: String(count)};
                }),
                onSelect: function (count) {
                    this.setItemsInRow(parseInt(count, 10));
                }.bind(this)
            };
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

