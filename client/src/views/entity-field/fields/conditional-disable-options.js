/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity-field/fields/conditional-disable-options', ['views/fields/base', 'model'], (Dep, Model) => {

    return Dep.extend({

        detailTemplate: 'entity-field/fields/conditional-disable-options/detail',

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

        data() {
            return {
                itemDataList: this.itemDataList
            };
        },

        setup() {
            this.optionsDefsList = Espo.Utils.cloneDeep(this.model.get(this.name)) || []
            this.scope = this.model.get('entityId');

            this.setupItems();
            this.setupItemViews();
        },

        setupItems() {
            this.itemDataList = [];
            this.optionsDefsList.forEach((item, i) => {
                this.itemDataList.push({
                    conditionGroupViewKey: 'conditionGroup' + i.toString(),
                    optionsViewKey: 'options' + i.toString(),
                    index: i
                });
            });
        },

        setupItemViews() {
            this.optionsDefsList.forEach((item, i) => {
                this.createStringView(i);
                this.createOptionsView(i);
            });
        },

        createOptionsView(num) {
            const key = 'options' + num.toString();
            if (!this.optionsDefsList[num]) return;

            let model = new Model();
            model.set('options', this.optionsDefsList[num].options || []);

            this.createView(key, 'views/fields/multi-enum', {
                el: this.getSelector() + ' .options-container[data-key="' + key + '"]',
                model: model,
                name: 'options',
                mode: this.mode,
                params: {
                    options: this.model.get('options'),
                    translatedOptions: this.model.get('translatedOptions')
                }
            }, view => {
                if (this.isRendered()) {
                    view.render();
                }

                this.listenTo(this.model, 'change:options', () => {
                    view.setTranslatedOptions(this.getTranslatedOptions());
                    view.setOptionList(this.model.get('options'));
                });

                this.listenTo(model, 'change', () => {
                    this.optionsDefsList[num].options = model.get('options') || [];
                });
            });
        },

        getTranslatedOptions() {
            if (this.model.get('translatedOptions')) {
                return this.model.get('translatedOptions');
            }
            let translatedOptions = {};
            let list = this.model.get('options') || [];
            list.forEach(value => {
                translatedOptions[value] = this.getLanguage().translateOption(value, this.options.field, this.options.scope);
            });

            return translatedOptions;
        },

        createStringView(num) {
            let key = 'conditionGroup' + num.toString();
            if (!this.optionsDefsList[num]) return;

            this.createView(key, 'views/admin/dynamic-logic/conditions-string/group-base', {
                el: this.getSelector() + ' .string-container[data-key="' + key + '"]',
                itemData: {
                    value: this.optionsDefsList[num].conditionGroup
                },
                operator: 'and',
                scope: this.scope
            }, view => {
                if (this.isRendered()) {
                    view.render();
                }
            });
        },

        edit(num) {
            this.createView('modal', 'views/admin/dynamic-logic/modals/edit', {
                conditionGroup: this.optionsDefsList[num].conditionGroup,
                scope: this.options.scope
            }, view => {
                view.render();

                this.listenTo(view, 'apply', conditionGroup => {
                    this.optionsDefsList[num].conditionGroup = conditionGroup;
                    this.createStringView(num);
                });
            });
        },

        addOptionList() {
            this.optionsDefsList.push({
                options: [],
                conditionGroup: null
            });

            this.setupItems();
            this.reRender();
            this.setupItemViews();
        },

        removeItem(num) {
            this.optionsDefsList.splice(num, 1);

            this.setupItems();
            this.reRender();
            this.setupItemViews();
        },

        fetch() {
            let data = {};

            data[this.name] = this.optionsDefsList;

            if (!this.optionsDefsList.length) {
                data[this.name] = null;
            }

            return data;
        },

        setMode(mode) {
            Dep.prototype.setMode.call(this, mode);

            (this.optionsDefsList || []).forEach((item, i) => {
                const key = 'options' + i.toString();
                const optionsView = this.getView(key);
                if (optionsView) {
                    optionsView.setMode(mode);
                }
            });
        },

        initSaveAfterOutsideClick() {
        },

        validate() {
            (this.model.get(this.name) || []).forEach(item => {
                if (!item.options || !item.options.length || !item.conditionGroup) {
                    this.trigger('invalid');
                    return true;
                }
            })

            return false;
        },

    });

});
