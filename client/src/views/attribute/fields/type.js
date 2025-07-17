/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/fields/type', 'views/fields/enum',
    (Dep) => Dep.extend({

        inlineEditDisabled: true,

        setup: function () {
            this.params.groupTranslation = 'EntityField.groupOptions.type'
            Dep.prototype.setup.call(this);

            this.updateOptions();
            this.listenTo(this.model, 'after:save', () => {
                this.updateOptions();
                this.reRender();
            });
        },

        updateOptions() {
            this.params.options = ['']
            this.params.groupOptions = [];
            this.translatedOptions = {'': ''};

            if (this.model.isNew()) {
                $.each(this.getMetadata().get(['attributes']), (attributeType, attributeDefs) => {
                    this.params.options.push(attributeType);
                    this.translatedOptions[attributeType] = this.getLanguage().translateOption(attributeType, 'type', 'Attribute');
                });
            } else {
                this.params.options.push(this.model.get(this.name));
                this.translatedOptions[this.model.get(this.name)] = this.getLanguage().translateOption(this.model.get(this.name), 'type', 'Attribute');
                $.each(this.getMetadata().get(['attributes', this.model.get(this.name), 'convert'], {}), (attributeType, attributeDefs) => {
                    this.params.options.push(attributeType);
                    this.translatedOptions[attributeType] = this.getLanguage().translateOption(attributeType, 'type', 'Attribute');
                });
            }

            this.params.options.forEach(attributeType => {
                if (!attributeType) {
                    return
                }
                const group = this.getMetadata().get(['fields', attributeType])?.group || 'other'
                let groupObject = this.params.groupOptions.find(go => go.name === group)
                if (!groupObject) {
                    groupObject = {name: group, options: []}
                    this.params.groupOptions.push(groupObject)
                }

                groupObject.options.push(attributeType)
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

    })
);

