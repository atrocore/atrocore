/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity-field/fields/conditional-disable-options', ['views/fields/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        editTemplate: 'entity-field/fields/conditional-disable-options/edit',

        events: {
            'click [data-action="editConditions"]': function (e) {
                var index = parseInt($(e.currentTarget).data('index'));
                this.edit(index);
            },
            'click [data-action="addOptionList"]': function (e) {
                this.addOptionList();
            },
            'click [data-action="removeOptionList"]': function (e) {
                var index = parseInt($(e.currentTarget).data('index'));
                this.removeItem(index);
            }
        },

        data: function () {
            return {
                itemDataList: this.itemDataList
            };
        },

        setup: function () {
            this.optionsDefsList = Espo.Utils.cloneDeep(this.model.get(this.name)) || []
            this.scope = this.options.scope;

            this.setupItems();
            this.setupItemViews();
        },

        setupItems: function () {
            this.itemDataList = [];
            this.optionsDefsList.forEach(function (item, i) {
                this.itemDataList.push({
                    conditionGroupViewKey: 'conditionGroup' + i.toString(),
                    optionsViewKey: 'options' + i.toString(),
                    index: i
                });
            }, this);
        },

        setupItemViews: function () {
            this.optionsDefsList.forEach(function (item, i) {
                this.createStringView(i);
                this.createOptionsView(i);
            }, this);
        },

        createOptionsView: function (num) {
            var key = 'options' + num.toString();
            if (!this.optionsDefsList[num]) return;

            var model = new Model();
            model.set('options', this.optionsDefsList[num].optionList || []);

            this.createView(key, 'views/fields/multi-enum', {
                el: this.getSelector() + ' .options-container[data-key="'+key+'"]',
                model: model,
                name: 'options',
                mode: 'edit',
                params: {
                    options: this.model.get('options'),
                    translatedOptions: this.model.get('translatedOptions')
                }
            }, function (view) {
                if (this.isRendered()) {
                    view.render();
                }

                this.listenTo(this.model, 'change:options', function () {
                    view.setTranslatedOptions(this.getTranslatedOptions());
                    view.setOptionList(this.model.get('options'));
                }, this);

                this.listenTo(model, 'change', function () {
                    this.optionsDefsList[num].optionList = model.get('options') || [];
                }, this);
            }, this);
        },

        getTranslatedOptions: function () {
            if (this.model.get('translatedOptions')) {
                return this.model.get('translatedOptions');
            }
            var translatedOptions = {};
            var list = this.model.get('options') || [];
            list.forEach(function (value) {
                translatedOptions[value] = this.getLanguage().translateOption(value, this.options.field, this.options.scope);
            }, this);

            return translatedOptions;
        },

        createStringView: function (num) {
            var key = 'conditionGroup' + num.toString();
            if (!this.optionsDefsList[num]) return;

            this.createView(key, 'views/admin/dynamic-logic/conditions-string/group-base', {
                el: this.getSelector() + ' .string-container[data-key="'+key+'"]',
                itemData: {
                    value: this.optionsDefsList[num].conditionGroup
                },
                operator: 'and',
                scope: this.scope
            }, function (view) {
                if (this.isRendered()) {
                    view.render();
                }
            }, this);
        },

        edit: function (num) {
            this.createView('modal', 'views/admin/dynamic-logic/modals/edit', {
                conditionGroup: this.optionsDefsList[num].conditionGroup,
                scope: this.options.scope
            }, function (view) {
                view.render();

                this.listenTo(view, 'apply', function (conditionGroup) {
                    this.optionsDefsList[num].conditionGroup = conditionGroup;
                    this.createStringView(num);
                }, this);
            }, this);
        },

        addOptionList: function () {
            var i = this.itemDataList.length;

            this.optionsDefsList.push({
                optionList: this.model.get('options') || [],
                conditionGroup: null
            });

            this.setupItems();
            this.reRender();
            this.setupItemViews();
        },

        removeItem: function (num) {
            this.optionsDefsList.splice(num, 1);

            this.setupItems();
            this.reRender();
            this.setupItemViews();
        },

        fetch: function () {
            var data = {};

            data[this.name] = this.optionsDefsList;

            if (!this.optionsDefsList.length) {
                data[this.name] = null;
            }

            return data;
        }
    });

});
