/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity-field/fields/options', ['views/fields/base', 'model'], (Dep, Model) => {

    return Dep.extend({

        detailTemplate: 'entity-field/fields/options/detail',

        editTemplate: 'entity-field/fields/options/edit',

        dragndropEventName: null,

        events: {
            'click [data-action="addOptionList"]': function (e) {
                this.addOptionList();
            },
            'click [data-action="removeOptionList"]': function (e) {
                var index = parseInt($(e.currentTarget).data('index'));
                this.removeItem(index);
            }
        },

        itemDataList: [],

        data() {
            return {
                isEmpty: this.itemDataList.length === 0,
                itemDataList: this.itemDataList
            };
        },

        setup() {
            this.optionsDefsList = (this.model.get(this.name) || []).map((option, index) => {
                let color = this.model.get('optionColors')[index] ?? null;
                if(color && !color.includes('#')) {
                    color = '#'+ color;
                }
                return {
                    code: option,
                    label: this.getTranslatedOptions()[option] ?? null,
                    color: color
                }
            })
            this.scope = this.model.get('entityId');

            this.setupItems();
            this.setupItemViews();
            this.dragndropEventName = `resize.drag-n-drop-table-${this.cid}`;
            this.listenToOnce(this, 'remove', () => {
                $(window).off(this.dragndropEventName);
                $(window).off(`keydown.${this.cid} keyup.${this.cid}`);
            });

            this.listenTo(this, 'after:render', () => {
                if(this.mode === 'edit') {
                    this.setupDragAndDrop();
                }
            })
        },

        setupItems() {
            this.itemDataList = [];
            this.optionsDefsList.forEach((item, i) => {
                this.itemDataList.push({
                    codeViewKey: 'code' + i.toString(),
                    labelViewKey: 'label' + i.toString(),
                    colorViewKey: 'color' + i.toString(),
                    index: i
                });
            });
        },

        setupDragAndDrop() {
            const initDraggable = () => {
                this.$el.find('.list-container').sortable({
                    handle: window.innerWidth < 768 ? '.div[data-name="draggableIcon"]' : false,
                    delay: 150,
                    update: function (e, ui) {
                        let indexes = $.map(this.$el.find(`.list-container div.list-group-item-fields`), function (item) {
                            return $(item).data('index');
                        });

                        let newOptionsDefs = [];
                        indexes.forEach(index => {
                            newOptionsDefs.push(this.optionsDefsList[index]);
                        })
                        this.optionsDefsList = newOptionsDefs;

                        this.setupItems();
                        this.reRender();
                        this.setupItemViews();

                    }.bind(this),
                    helper: "clone",
                    start: (e, ui) => {
                        const widthData = {};

                        ui.placeholder.children().each(function (i, cell) {
                            widthData[i] = $(this).outerWidth();
                        });
                        ui.helper.children().each(function (i, cell) {
                            let width = widthData[i] ?? $(this).outerWidth();
                            $(this).css('width', width);
                        });
                    },
                    stop: (e, ui) => {
                        ui.item.children().each(function (i, cell) {
                            $(this).css('width', '');
                        });
                    }
                });
            }

            initDraggable();
            $(window).off(this.dragndropEventName).on(this.dragndropEventName, () => {
                initDraggable();
            });
        },

        setupItemViews() {
            this.optionsDefsList.forEach((item, i) => {
                this.createOptionsView(i);
            });
        },

        createOptionsView(num) {
            const codeKey = 'code' + num.toString();
            const labelKey = 'label' + num.toString();
            const colorKey = 'color' + num.toString();
            if (!this.optionsDefsList[num]) return;

            let model = new Model();
            model.set('code', this.optionsDefsList[num].code);
            model.set('label', this.optionsDefsList[num].label);
            model.set('color', this.optionsDefsList[num].color);

            this.createView(codeKey, 'views/fields/varchar', {
                el: this.getSelector() + ' .options-container[data-key="' + codeKey + '"]',
                model: model,
                name: 'code',
                mode: this.mode,
                params: {
                    readOnly: (this.model.get(this.name) ?? []).includes(model.get('code')),
                    required: true
                },
                inlineEditDisabled: true
            }, view => {
                if (this.isRendered()) {
                    view.render();
                }

                this.listenTo(view, 'change', () => {
                    this.optionsDefsList[num].code = model.get('code') || [];
                });
            });

            this.createView(labelKey, 'views/fields/varchar', {
                el: this.getSelector() + ' .options-container[data-key="' + labelKey + '"]',
                model: model,
                name: 'label',
                mode: this.mode,
                inlineEditDisabled: true
            }, view => {
                if (this.isRendered()) {
                    view.render();
                }

                this.listenTo(view, 'change', () => {
                    this.optionsDefsList[num].label = model.get('label') ;
                });
            });

            this.createView(colorKey, 'views/fields/color', {
                el: this.getSelector() + ' .options-container[data-key="' + colorKey + '"]',
                model: model,
                name: 'color',
                mode: this.mode,
                inlineEditDisabled: true
            }, view => {
                if (this.isRendered()) {
                    view.render();
                }

                this.listenTo(view, 'change', () => {
                    this.optionsDefsList[num].color = model.get('color');
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
                let data = this.translate(this.options.field, 'options', this.options.scope);
                if (typeof data != 'object') {
                    data = {};
                }
                translatedOptions[value] = data[value] ?? null;
            });

            return translatedOptions;
        },

        addOptionList() {
            this.optionsDefsList.push({
                code: null,
                label: null,
                color: ""
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
            let options = [];
            let optionColors = []
            let translatedOptions = {};

            (this.optionsDefsList || []).forEach((item, i) => {
                options.push(item.code);
                translatedOptions[item.code] = item.label;
                optionColors.push(item.color ?? "");
            });

            data[this.name] = null;

            if(options.length) {
                data[this.name] = options;
                data['optionColors'] = optionColors;
                data['translatedOptions'] = translatedOptions;
            }

            return data;
        },

        setMode(mode) {
            Dep.prototype.setMode.call(this, mode);
            (this.optionsDefsList || []).forEach((item, i) => {
                const keys = ['code' + i.toString(), 'label' + i.toString(), 'color' + i.toString()];
                keys.forEach((key) => {
                    const optionsView = this.getView(key);
                    if (optionsView && !optionsView.params.readOnly) {
                        optionsView.setMode(mode);
                    }
                })
            });

            if(mode === 'edit') {
                this.setupDragAndDrop();
            }
        },

        initSaveAfterOutsideClick() {
        },

        validate() {
            let res = false;

            (this.optionsDefsList || []).forEach((item, i) => {
                const key = 'code' + i.toString();
                const optionsView = this.getView(key);
                if (optionsView) {
                    res = optionsView.validate() || res;
                    if (res) {
                        optionsView.trigger('invalid');
                    }
                }
            });
            return res;
        },
    });

});
