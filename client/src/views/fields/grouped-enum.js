/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('views/fields/grouped-enum', ['views/fields/enum'], function (Dep) {

    return Dep.extend({

        editTemplate: 'fields/grouped-enum/edit',

        translatedGroups: null,

        data: function () {
            const data = Dep.prototype.data.call(this);

            data.translatedGroups = this.translatedGroups
            data.prohibitedEmptyValue = this.prohibitedEmptyValue
            data.groups = this.getActiveGroups()

            return data;
        },

        getActiveGroups() {
            const groups = {}
            Object.keys(this.params.groups).forEach(group => {
                const options = (this.params.groups[group] || []).filter(opt => this.params.options.includes(opt))
                if (options.length) {
                    groups[group] = options
                }
            })
            return groups
        },

        setupGroups() {
            this.params.groups = this.params.groups || this.model.getFieldParam(this.name, 'groups') || {}

            const options = []
            Object.keys(this.params.groups).forEach(group => {
                options.push(...this.params.groups[group])
            })
            this.params.options = options
        },

        setup: function () {
            this.setupGroups()

            if ('translatedGroups' in this.params) {
                this.translatedGroups = this.params.translatedGroups;
            }

            Dep.prototype.setup.call(this);
        },

        setupTranslation: function () {
            Dep.prototype.setupTranslation.call(this)

            if (this.params.groupTranslation) {
                this.translatedGroups = this.translate(...this.params.groupTranslation.split('.').reverse())
            }

            if (this.translatedGroups == null || typeof this.translatedGroups != 'object') {
                this.translatedGroups = this.translate(this.name, 'groups', this.model?.name)
            }
        },

        getSearchOptions() {
            const options = [];
            const groups = []
            Object.keys(this.params.groups).forEach((group) => {
                groups.push({value: group, label: this.translatedGroups?.[group] || group});

                (this.params.groups[group] || []).forEach(value => {
                    var label = this.getLanguage().translateOption(value, this.name, this.model?.name);
                    if (this.translatedOptions) {
                        if (value in this.translatedOptions) {
                            label = this.translatedOptions[value];
                        }
                    }
                    options.push({
                        value: value,
                        label: label,
                        group: group
                    });
                })
            }, this);

            return {
                options: options,
                optgroups: groups,
                optgroupLabelField: 'label',
                optgroupValueField: 'value',
                optgroupField: 'group',
            }
        },

    });
});

