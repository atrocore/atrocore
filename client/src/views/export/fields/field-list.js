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
                if ((this.model.get(this.name) || []).includes('_addAttribute')) {
                    this.actionSelectAttribute();
                }
            });
        },

        prepareListOptions() {
            this.params.options = ['id'];
            this.translatedOptions = {'id': this.translate('id', 'fields', 'Global')};

            let entity = this.options.scope;
            let hasAttribute = this.getMetadata().get(`scopes.${entity}.hasAttribute`);

            let notAvailableTypes = [
                'address',
                'attachmentMultiple',
                'currencyConverted',
                'linkParent',
                'personName',
                'autoincrement'
            ];

            let notAvailableFieldsList = [
                'createdAt',
                'modifiedAt'
            ];

            if (hasAttribute) {
                this.params.options.push('_addAttribute');
                this.translatedOptions['_addAttribute'] = this.translate('_addAttribute');
            }

            $.each(this.getMetadata().get(['entityDefs', entity, 'fields'], {}), (field, fieldDefs) => {
                if (!fieldDefs.disabled && !notAvailableFieldsList.includes(field) && !notAvailableTypes.includes(fieldDefs.type) && !fieldDefs.importDisabled && !fieldDefs.attributeId) {
                    this.params.options.push(field);
                    this.translatedOptions[field] = this.translate(field, 'fields', entity);
                }
            })

            this.params.options.sort((a, b) => {
                return this.translatedOptions[a].localeCompare(this.translatedOptions[b])
            });
        },

        actionSelectAttribute() {
            // remove _addAttribute from selection
            let fields = [];
            (this.model.get(this.name) || []).forEach(item => {
                if (item !== '_addAttribute') {
                    fields.push(item);
                }
            })
            this.model.set(this.name, fields);
            this.reRender();

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
                    this.ajaxGetRequest('Attribute/action/attributesDefs', {
                        entityName: entity,
                        attributesIds: [model.id]
                    }, {async: false}).success(res => {
                        $.each(res, (field, fieldDefs) => {
                            if (!fieldDefs.importDisabled) {
                                this.params.options.push(field);
                                this.translatedOptions[field] = fieldDefs.label;
                                if (fieldDefs.channelName) {
                                    this.translatedOptions[field] = `${fieldDefs.label} / ${fieldDefs.channelName}`;
                                }

                                this.model.get(this.name).push(field);
                                this.selected.push(field);

                                this.getMetadata().data.entityDefs[entity].fields[field] = fieldDefs;
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