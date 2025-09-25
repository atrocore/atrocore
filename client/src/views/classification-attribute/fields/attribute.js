/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/classification-attribute/fields/attribute', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList: ['onlyForEntity'],

        boolFilterData: {
            onlyForEntity() {
                let entity = 'NotExistedEntity';
                this.ajaxGetRequest(`Classification/${this.model.get('classificationId')}`, null, {async: false}).success(classification => {
                    entity = classification.entityId;
                });

                return entity;
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:attributeId', () => {
                this.setAttributeData();
            });
        },

        select: function (model) {
            Dep.prototype.select.call(this, model);

            this.model.trigger('change:attribute', model);
            this.model.set('isRequired', model.get('isRequired'))
            this.model.set('isReadOnly', model.get('isReadOnly'))
            this.model.set('isProtected', model.get('isProtected'))
        },

        setAttributeData() {
            this.model.set('attributeType', null);

            if (this.model.get('attributeId')) {
                this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).success(attribute => {
                    this.model.set('attributeType', attribute.type);
                    this.model.set('attributeMeasureId', attribute.measureId);
                    this.model.set('attributeNotNull', attribute.notNull);
                    this.model.set('maxLength', attribute.maxLength);
                    this.model.set('countBytesInsteadOfCharacters', attribute.countBytesInsteadOfCharacters);
                    this.model.set('min', attribute.min);
                    this.model.set('max', attribute.max);
                });
            }
        },

        addCustomDataToView: function(view, rule) {
            view.getSelectFilters = this.getSelectFilters.bind(this);
            view.selectBoolFilterList = [];
            view.boolFilterData = {};

            view.getSelectFilters = () => {
                let bool = {};
                let queryBuilder = {
                    condition: "AND",
                    rules: [],
                    valid: true
                }
                let subQuery = rule.data?.subQuery || [];
                subQuery.forEach(item => {
                    if (item.type === 'bool') {
                        item.value.forEach(v => bool[v] = true);
                    }

                    if (item.condition) {
                        queryBuilder.rules.push(item);
                    }
                });

                if (queryBuilder.rules.length === 1) {
                    queryBuilder = queryBuilder.rules[0];
                }

                return { bool, queryBuilder }
            }

            view.linkMultiple = this.chooseMultipleOnSearch();

            view.getSelectAllByDefault = () => {
                let subQuery = rule.data?.subQuery || [];
                return subQuery.length > 0;
            }
        }

    })
);

