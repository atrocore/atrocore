/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/conditional-disable-options', 'views/fields/base', Dep => Dep.extend({
        detailTemplate: 'admin/field-manager/fields/conditional-disable-options/detail',
        // editTemplate: 'fields/field-value-container',

        optionsGroups: [],

        setup() {
            Dep.prototype.setup.call(this);

            this.optionsGroups = [];
            (this.model.get(this.name) || []).forEach((item, k) => {
                const disableOptions = `disableOptions${k}`;
                const conditionGroup = `conditionGroup${k}`;

                this.model.set(disableOptions, item.options);
                this.model.set(conditionGroup, {conditionGroup: item.conditionGroup});

                this.optionsGroups.push({
                    disableOptions: disableOptions,
                    conditionGroup: conditionGroup,
                });
            });
        },

        data() {
            return {
                optionGroups: this.optionsGroups
            };
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            (this.optionsGroups || []).forEach(item => {
                this.createView(item.disableOptions, 'views/fields/array', {
                    el: `${this.options.el} .disable-options[data-name="${item.disableOptions}"]`,
                    name: item.disableOptions,
                    model: this.model,
                    defs: {
                        name: item.disableOptions
                    },
                    mode: this.mode,
                    inlineEditDisabled: true
                }, view => {
                    view.listenTo(view, 'after:render', () => {
                        console.log('11')
                    })
                    view.render();
                });

                this.createView(item.conditionGroup, 'views/admin/field-manager/fields/conditions-container', {
                    el: `${this.options.el} .condition-group[data-name="${item.conditionGroup}"]`,
                    name: item.conditionGroup,
                    model: this.model,
                    defs: {
                        name: item.conditionGroup
                    },
                    mode: this.mode,
                    inlineEditDisabled: true
                }, view => {
                    view.listenTo(view, 'after:render', () => {
                        console.log('11')
                    })
                    view.render();
                });
            })
        },

    })
);