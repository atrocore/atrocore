/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/conditional-properties', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: "fields/condition-properties/list",

        detailTemplate: "fields/condition-properties/list",

        inlineEditDisabled: true,

        data: function () {
            const data = Dep.prototype.data.call(this) || {};

            data.conditionTypes = this.getConditionTypes()

            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.onModelReady(() => {
                this.listenTo(this, 'after:render', () => {
                    (this.getConditionTypes() || []).forEach(conditionType => {
                        const viewName = this.getMetadata().get(['entityDefs', this.model.name, 'fields', conditionType, 'view'])
                            || 'views/admin/field-manager/fields/dynamic-logic-conditions';

                        this.createView(`conditions-${this.model.id}-${conditionType}`, viewName, {
                            el: this.options.el + ` .conditions-container[data-name="${conditionType}"] .conditions`,
                            model: this.model,
                            name: conditionType,
                            mode: 'list',
                            inlineEditDisabled: true,
                        }, view => {
                            view.render();
                        });
                    });
                });
            });
        },

        getConditionTypes: function () {
            if (this.model.get('code') === 'id') {
                return [];
            }

            return ['conditionalVisible', 'conditionalRequired', 'conditionalReadOnly', 'conditionalProtected', 'conditionalDisableOptions']
                .filter(conditionType => !!this.model.get(conditionType));
        }

    })
);
