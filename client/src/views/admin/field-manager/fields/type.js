/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/type', 'views/fields/grouped-enum', Dep => {

    return Dep.extend({

        setup: function () {
            this.params.groupTranslation = 'EntityField.groups.type'

            Dep.prototype.setup.call(this);

            let scopeType = this.getMetadata().get(`scopes.${this.model.get('entityId')}.type`);

            this.params.options = [''];
            this.params.groups = {};
            this.translatedOptions = {'': ''};

            $.each(this.getMetadata().get('fields'), (type, typeDefs) => {
                if (!typeDefs.notCreatable && !(scopeType === 'ReferenceData' && ['link', 'linkMultiple'].includes(type))) {
                    this.params.options.push(type);

                    const group = typeDefs.group || 'other'
                    if (!this.params.groups[group]) {
                        this.params.groups[group] = []
                    }

                    this.params.groups[group].push(type)
                }
                this.translatedOptions[type] = this.translate(type, 'fieldTypes', 'Admin');
            })

            this.params.groups = Object.fromEntries(
                Object.entries(this.params.groups).sort(([v1], [v2]) => {
                    if (v1 === 'other') return 1;
                    if (v2 === 'other') return -1;
                    const order = {numeric: 1, character: 2, date: 3, reference: 4};
                    return (order[v1] || 999) - (order[v2] || 999) ||
                        (this.translatedGroups[v1] || v1).localeCompare((this.translatedGroups[v2] || v2));
                })
            );

            Object.keys(this.params.groups).forEach(group => {
                this.params.groups[group] = this.params.groups[group].sort((v1, v2) => {
                    return this.translate(v1, 'fieldTypes', 'Admin').localeCompare(this.translate(v2, 'fieldTypes', 'Admin'));
                });
            })

        },

    });
});
