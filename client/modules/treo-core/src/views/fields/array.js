/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/fields/array', 'class-replace!treo-core:views/fields/array',
    Dep => Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'inline-edit-off', () => {
                this.selected = Espo.Utils.clone(this.model.get(this.name)) || [];
            });
        },

        getItemHtml(value) {
            if (this.translatedOptions != null) {
                for (let item in this.translatedOptions) {
                    if (this.translatedOptions[item] === value) {
                        value = item;
                        break;
                    }
                }
            }

            value = (value || '').toString();

            let valueSanitized = this.getHelper().stripTags(value);
            let valueInternal = valueSanitized.replace(/"/g, '-quote-').replace(/\\/g, '-backslash-');

            let label = valueSanitized.replace(/"/g, '&quot;').replace(/\\/g, '&bsol;');
            if (this.translatedOptions) {
                label = ((value in this.translatedOptions) ? this.translatedOptions[value] : label);
                label = label.toString();
                label = this.getHelper().stripTags(label);
                label = label.replace(/"/g, '&quot;').replace(/\\/g, '&bsol;');
            }

            return `
                <div class="list-group-item" data-value="${valueInternal}" style="cursor: default;">
                    ${label}&nbsp;
                    <a href="javascript:" class="pull-right" data-value="${valueInternal}" data-action="removeValue"><span class="fas fa-times"></a>
                </div>`;
        },

        removeValue(value) {
            let valueSanitized = this.getHelper().stripTags(value);
            let valueInternal = valueSanitized.replace(/"/g, '-quote-').replace(/\\/g, '-backslash-');

            this.$list.children(`[data-value="${valueInternal}"]`).remove();
            let index = this.selected.indexOf(value);
            this.selected.splice(index, 1);
            this.trigger('change');
        }

    })
);