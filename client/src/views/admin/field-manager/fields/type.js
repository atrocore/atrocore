/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/type', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup: function () {
            this.params.groupTranslation = 'EntityField.groupOptions.type'

            Dep.prototype.setup.call(this);

            let scopeType = this.getMetadata().get(`scopes.${this.model.get('entityId')}.type`);

            this.params.options = [''];
            this.params.groupOptions = [];
            this.translatedOptions = {'': ''};

            $.each(this.getMetadata().get('fields'), (type, typeDefs) => {
                if (!typeDefs.notCreatable && !(scopeType === 'ReferenceData' && ['link', 'linkMultiple'].includes(type))) {
                    this.params.options.push(type);

                    const group = typeDefs.group || 'other'
                    let groupObject = this.params.groupOptions.find(go => go.name === group)
                    if (!groupObject) {
                        groupObject = {name: group, options: []}
                        this.params.groupOptions.push(groupObject)
                    }

                    groupObject.options.push(type)
                }
                this.translatedOptions[type] = this.translate(type, 'fieldTypes', 'Admin');
            })

            this.params.groupOptions = this.params.groupOptions.sort((v1, v2) => {
                if (v1.name === 'other') return 1;
                if (v2.name === 'other') return -1;
                const order = {numeric: 1, character: 2, date: 3, reference: 4};
                return (order[v1.name] || 999) - (order[v2.name] || 999) ||
                    (this.translatedGroups[v1.name] || v1.name).localeCompare((this.translatedGroups[v2.name] || v2.name));
            })

            this.params.groupOptions.forEach(group => {
                group.options = group.options.sort((v1, v2) => {
                    return this.translate(v1, 'fieldTypes', 'Admin').localeCompare(this.translate(v2, 'fieldTypes', 'Admin'));
                });
            })

        },

    });
});
