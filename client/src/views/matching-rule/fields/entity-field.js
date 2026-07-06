/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/matching-rule/fields/entity-field', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup() {
            this.prepareListOptions();
            Dep.prototype.setup.call(this);
            this.onModelReady(() => {
                this.listenTo(this.model, 'change:type change:matchingId', () => {
                    this.model.set(this.name, null);
                    this.model.set('attributeId', null);
                    this.model.set('attributeName', null);
                    this.prepareListOptions();
                    this.reRender();
                });

                this.listenTo(this.model, `change:${this.name}`, () => {
                    const field = this.model.get(this.name);

                    if (field === '_addAttribute') {
                        this.actionSelectAttribute();
                        return;
                    }

                    const entityName = this._getEntityName();
                    if (entityName) {
                        const attrId = this.getMetadata().get(`entityDefs.${entityName}.fields.${field}.attributeId`) || null;
                        if (!attrId) {
                            this.model.set('attributeId', null);
                            this.model.set('attributeName', null);
                        }
                    }
                });
            })
        },

        findMatchingId(matchingRuleSetId) {
            let res = null;

            (this.getConfig().get('matchingRules') || []).forEach(item => {
                if (item.id === matchingRuleSetId) {
                    if (item.matchingRuleSetId) {
                        res = this.findMatchingId(item.matchingRuleSetId);
                    } else {
                        res = item.matchingId;
                    }
                }
            })

            return res;
        },

        _getEntityName() {
            let matchingId = this.model.get('matchingId');
            if (this.model.get('matchingRuleSetId')) {
                matchingId = this.findMatchingId(this.model.get('matchingRuleSetId'));
            }

            if (!matchingId) {
                return null;
            }

            return this.getMetadata().get(['app', 'matchings', matchingId, 'masterEntity']) || null;
        },

        prepareListOptions() {
            this.translatedOptions = {};
            this.originalOptionList = this.params.options = [];

            delete this.params.groupOptions;

            if (this.model.get('type') === 'set') {
                return;
            }

            const entityName = this._getEntityName();
            if (!entityName) {
                return;
            }

            const hasAttribute = this.getMetadata().get(`scopes.${entityName}.hasAttribute`);
            const availableTypes = this.getMetadata().get(`app.matchingRules.${this.model.get('type')}.fieldTypes`) || [];

            if (hasAttribute) {
                this.params.groupOptions = [
                    { name: 'attributes', options: ['_addAttribute'] },
                    { name: 'fields', options: [] }
                ];
                this.params.options.push('_addAttribute');
                this.translatedOptions['_addAttribute'] = this.translate('_addAttribute', 'labels', 'MatchingRule');

                if (this.model.get('attributeId')) {
                    let field = this.model.get('field');
                    let fieldDefs = this.model.get('fieldDefs');

                    this.getMetadata().data.entityDefs[entityName].fields[field] = fieldDefs;
                    this.getLanguage().data[entityName] = this.getLanguage().data[entityName] || {};
                    this.getLanguage().data[entityName].fields = this.getLanguage().data[entityName].fields || {};
                    this.getLanguage().data[entityName].fields[field] = fieldDefs.label;
                }
            }

            $.each(this.getMetadata().get(['entityDefs', entityName, 'fields'], {}), (field, fieldDefs) => {
                if (
                    !fieldDefs.disabled
                    && availableTypes.includes(fieldDefs.type)
                    && !fieldDefs.importDisabled
                    && !fieldDefs.combinedField
                    && !fieldDefs.notStorable
                ) {
                    this.params.options.push(field);
                    this.translatedOptions[field] = this.translate(field, 'fields', entityName);

                    if (hasAttribute) {
                        if (fieldDefs.attributeId) {
                            this.params.groupOptions[0].options.push(field);
                        } else {
                            this.params.groupOptions[1].options.push(field);
                        }
                    }
                }
            });

            this.params.options.sort((a, b) => {
                if (a === '_addAttribute') return -1;
                if (b === '_addAttribute') return 1;
                return this.translatedOptions[a].localeCompare(this.translatedOptions[b]);
            });

            this.originalOptionList = this.params.options;
        },

        actionSelectAttribute() {
            const entityName   = this._getEntityName();
            if (!entityName) {
                return;
            }

            const availableTypes = this.getMetadata().get(`app.matchingRules.${this.model.get('type')}.fieldTypes`) || [];

            const scope    = 'Attribute';
            const viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', 'select']) || 'views/modals/select-records';

            this.notify('Loading...');
            this.createView('dialog', viewName, {
                scope:                scope,
                multiple:             false,
                createButton:         false,
                massRelateEnabled:    false,
                boolFilterList:       ['onlyForEntity', 'onlyAttributeTypes'],
                boolFilterData:       { onlyForEntity: entityName, onlyAttributeTypes: availableTypes },
                allowSelectAllResult: false,
            }, dialog => {
                dialog.render();
                this.notify(false);

                dialog.once('select', attributeModel => {
                    this.wait(true);
                    this.notify('Loading...');

                    this.ajaxGetRequest('Attribute/attributesDefs', {
                        entityName:    entityName,
                        attributesIds: [attributeModel.id]
                    }, { async: false }).success(res => {

                        let value = null;
                        $.each(res, (field, fieldDefs) => {
                            if (!availableTypes.includes(fieldDefs.type)) {
                                return;
                            }

                            if (!value) {
                                value = field;
                            }

                            if (!this.params.options.includes(field)) {
                                this.params.options.push(field);
                                this.originalOptionList.push(field);
                                this.translatedOptions[field] = fieldDefs.label;

                                this.getMetadata().data.entityDefs[entityName].fields[field] = fieldDefs;
                                this.getLanguage().data[entityName] = this.getLanguage().data[entityName] || {};
                                this.getLanguage().data[entityName].fields = this.getLanguage().data[entityName].fields || {};
                                this.getLanguage().data[entityName].fields[field] = fieldDefs.label;

                                if (this.params.groupOptions) {
                                    this.params.groupOptions[0].options.push(field);
                                }
                            }
                        });

                        this.model.set('attributeId', attributeModel.id);
                        this.model.set('attributeName', attributeModel.get('name'));

                        this.wait(false);
                        this.notify(false);
                        this.clearView('dialog');

                        if (this.model.get(this.name) === '_addAttribute' && value !== null) {
                            this.model.set(this.name, value);
                        }
                        this.reRender();
                    });
                });
            });
        },

    });
});
