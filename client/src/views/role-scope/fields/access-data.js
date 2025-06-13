/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/role-scope/fields/access-data', 'views/fields/json-object', Dep => {

    return Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get('hasAccess')) {
                let items = [];
                $.each((this.model.get('accessData').scopeData || {}), (action, value) => {
                    let actionLabel = this.translate(action + 'Action', 'fields', 'RoleScope');
                    let valueLabel = this.translate(value, 'labels', 'RoleScope');
                    items.push(`<div class="access-item"><b>${actionLabel}:</b> ${valueLabel}</div>`);
                });

                let html = `<div class="scope-access-data">${items.join('')}</div>`

                if (this.model.get('accessData').fieldsData) {
                    let fields = [];
                    $.each((this.model.get('accessData').fieldsData || {}), (field, actions) => {
                        let fieldLabel = this.translate(field, 'fields', this.model.get('name'));
                        let items = [];
                        $.each(actions, (action, value) => {
                            let actionLabel = this.translate(action + 'Action', 'fields', 'RoleScope');
                            let valueLabel = this.translate(value, 'labels', 'RoleScope');
                            items.push(`<div class="access-item"><b>${actionLabel}:</b> ${valueLabel}</div>`);
                        });

                        fields.push(`<div class="scope-field">${fieldLabel}:<span class="scope-field-access-data">${items.join('')}</span></div>`);
                    });

                    html += `<div class="scope-fields-access-data"><div><b>Fields:</b></div>${fields.join('')}</div>`;
                }

                this.$el.html(html);
            }
        },

    });
});

