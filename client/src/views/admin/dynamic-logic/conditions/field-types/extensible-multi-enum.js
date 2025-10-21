/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/dynamic-logic/conditions/field-types/extensible-multi-enum', 'views/admin/dynamic-logic/conditions/field-types/base', function (Dep) {

    return Dep.extend({

        getValueViewName: function () {
            const isDropdown = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'dropdown']) ?? false;

            return isDropdown ? 'views/fields/extensible-enum-dropdown' : 'views/fields/extensible-enum';
        },

        createValueViewContains: function () {
            this.createLinkValueField();
        },

        createValueViewNotContains: function () {
            this.createLinkValueField();
        },

        createLinkValueField: function () {
            const ids = this.model.get(this.field);
            const names = this.model.get(this.field + 'Names');
            if (Array.isArray(ids) && ids[0]) {
                this.model.set(this.field, ids[0]);
                if (names && names[ids[0]]) {
                    this.model.set(this.field + 'Name', names[ids[0]]);
                }
            }

            var viewName = this.getValueViewName();
            this.createView('value', viewName, {
                model: this.model,
                name: this.field,
                el: this.getSelector() + ' .value-container',
                mode: 'edit'
            }, function (view) {
                if (this.isRendered()) {
                    view.render();
                }
            }, this);
        },

        createValueViewIn: function () {
            this.createExtensibleMultiEnumValueField();
        },

        createValueViewNotIn: function () {
            this.createExtensibleMultiEnumValueField();
        },

        createExtensibleMultiEnumValueField: function () {

            const id = this.model.get(this.field);
            const name = this.model.get(this.field + 'Name');
            if (id && typeof id == 'string') {
                this.model.set(this.field, [id]);
                if (name) {
                    this.model.set(this.field + 'Names', { [id]: name });
                }
            }

            const viewName = 'views/fields/extensible-multi-enum';

            this.createView('value', viewName, {
                model: this.model,
                name: this.field,
                el: this.getSelector() + ' .value-container',
                mode: 'edit',
                readOnlyDisabled: true,
                disableConditions: true,
                extensibleEnumId: this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'extensibleEnumId'])
            }, function (view) {
                if (this.isRendered()) {
                    view.render();
                }
            }, this);
        },

        fetch: function () {
            var valueView = this.getView('value');

            var item = {
                type: this.type,
                attribute: this.field,
                data: {
                    field: this.field
                }
            };

            if (valueView) {
                valueView.fetchToModel();
                item.value = this.model.get(this.field);

                const values = {};
                if (['in', 'notIn'].includes(item.type)) {
                    values[this.field] = this.model.get(this.field);
                    values[this.field + 'Names'] = this.model.get(this.field + 'Names')
                } else {
                    values[this.field] = [this.model.get(this.field)];
                    values[this.field + 'Names'] = { [this.model.get(this.field)]: this.model.get(this.field + 'Name') }
                }

                item.data.values = values;
            }

            return item;
        },

    });

});

