/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/export/fields/field-list', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            this.prepareListOptions();

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, `change:${this.name}`, () => {
                const option = (this.model.get(this.name) || []).find(item => item in this.addActions);
                if (option) {
                    this.runAddAction(option);
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.applyOptionGroups();
        },

        // the groups of the items adding fields (attributes, groups of modules) go first, the fields of the entity go last
        applyOptionGroups() {
            const selectize = this.selectizeEl;
            if (this.mode !== 'edit' || !selectize) {
                return;
            }

            selectize.settings.lockOptgroupOrder = true;
            $.each(this.optionGroups, (group, label) => {
                selectize.addOptionGroup(group, { label: label });
            });
            $.each(selectize.options, (value, data) => {
                data.optgroup = this.params.groupOptions[value] || 'fields';
            });
        },

        prepareListOptions() {
            this.params.options = ['id'];
            this.translatedOptions = { 'id': this.translate('id', 'fields', 'Global') };
            this.optionGroups = {};
            this.params.groupOptions = {};

            let entity = this.options.scope;

            let notAvailableTypes = [
                'address',
                'attachmentMultiple',
                'currencyConverted',
                'linkParent',
                'personName'
            ];

            let notAvailableFieldsList = [
                'createdAt',
                'modifiedAt'
            ];

            $.each(this.getMetadata().get(['entityDefs', entity, 'fields'], {}), (field, fieldDefs) => {
                if (!fieldDefs.disabled && !notAvailableFieldsList.includes(field) && !notAvailableTypes.includes(fieldDefs.type) && !fieldDefs.exportDisabled && !fieldDefs.attributeId) {
                    this.params.options.push(field);
                    this.translatedOptions[field] = this.translate(field, 'fields', entity);
                }
            })

            this.params.options.sort((a, b) => {
                return this.translatedOptions[a].localeCompare(this.translatedOptions[b])
            });

            this.addActions = {};
            if (this.getMetadata().get(`scopes.${entity}.hasAttribute`)) {
                this.addActions['_addAttribute'] = { label: this.translate('addAttribute') };
                this.optionGroups['_addAttribute'] = this.translate('Attribute', 'scopeNamesPlural');
            }

            $.each(this.getMetadata().get(['clientDefs', entity, 'directExportFieldsActions']) || {}, (key, defs) => {
                const option = '_add' + Espo.utils.upperCaseFirst(key);
                this.addActions[option] = { label: this.translate(defs.label), view: defs.view };
                this.optionGroups[option] = this.translate(defs.groupLabel || defs.label);
            });

            Object.keys(this.addActions).reverse().forEach(option => {
                this.params.options.unshift(option);
                this.translatedOptions[option] = `[ ${this.addActions[option].label} ]`;
                this.params.groupOptions[option] = option;
            });

            this.optionGroups.fields = this.translate('Fields');
        },

        runAddAction(option) {
            this.model.set(this.name, (this.model.get(this.name) || []).filter(item => !(item in this.addActions)));
            this.reRender();

            if (option === '_addAttribute') {
                this.actionSelectAttribute();
                return;
            }

            this.notify('Loading...');
            this.createView('dialog', this.addActions[option].view, {
                entityName: this.options.scope,
                multiple: false
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('add', items => {
                    items.forEach(item => this.addSelectedOption(item.name, item.label, option));
                    this.clearView('dialog');
                    this.reRender();
                });
            });
        },

        addSelectedOption(field, label, group) {
            [this.params.options, this.originalOptionList].forEach(list => {
                if (list && !list.includes(field)) {
                    list.push(field);
                }
            });
            this.translatedOptions[field] = label;
            this.params.groupOptions[field] = group;

            const selected = Espo.Utils.clone(this.model.get(this.name) || []);
            if (!selected.includes(field)) {
                selected.push(field);
            }
            this.model.set(this.name, selected);
        },

        actionSelectAttribute() {
            const scope = 'Attribute';
            const viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', 'select']) || 'views/modals/select-records';

            let entity = this.options.scope;

            this.notify('Loading...');
            this.createView('dialog', viewName, {
                scope: scope,
                multiple: false,
                createButton: false,
                massRelateEnabled: false,
                boolFilterList: ['onlyForEntity'],
                boolFilterData: {
                    onlyForEntity: entity
                },
                allowSelectAllResult: false,
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('select', model => {
                    this.wait(true);
                    this.notify('Loading...');
                    this.ajaxGetRequest('Attribute/attributesDefs', {
                        entityName: entity,
                        attributesIds: [model.id]
                    }, { async: false }).success(res => {
                        $.each(res, (field, fieldDefs) => {
                            if (!fieldDefs.exportDisabled) {
                                let label = fieldDefs.label;
                                if (fieldDefs.channelName) {
                                    label = `${fieldDefs.label} / ${fieldDefs.channelName}`;
                                }
                                this.addSelectedOption(field, label, '_addAttribute');

                                this.getMetadata().data.entityDefs[entity].fields[field] = fieldDefs;

                                if (fieldDefs.combinedField && ['int', 'float', 'varchar'].includes(fieldDefs.type)) {
                                    const parts = fieldDefs.label.split(' ');
                                    parts.pop();

                                    label = parts.join(' ');
                                    if (fieldDefs.channelName) {
                                        label = `${label} / ${fieldDefs.channelName}`;
                                    }
                                    this.addSelectedOption('combined' + Espo.utils.upperCaseFirst(field), label, '_addAttribute');
                                }
                            }
                        });

                        this.wait(false);
                        this.notify(false);

                        this.clearView('dialog');

                        this.reRender();
                    })
                });
            });
        },

    })
);