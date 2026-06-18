/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/code', 'views/fields/varchar', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, `change:${this.name}`, () => {
                if (!this.model.get('name')) {
                    this.model.set('name', this.model.get(this.name));
                }
                this.model.set(this.name, this.sanitize(this.model.get(this.name)));
            });

            this.listenTo(this.model, 'change:name', () => {
                if (!this.model.get(this.name)) {
                    this.model.set(this.name, this.sanitize(this.model.get('name')));
                }
            });
        },

        sanitize(input) {
            const cleaned = input.replace(/[^a-zA-Z0-9_]/g, "");

            const noLeadingInvalid = cleaned.replace(/^[^a-zA-Z]+/, "");

            if (noLeadingInvalid.length === 0) {
                return "";
            }

            const noTrailingUnderscore = noLeadingInvalid.replace(/_+$/, "");

            if (noTrailingUnderscore.length === 0) {
                return "";
            }

            return noTrailingUnderscore.charAt(0).toLowerCase() + noTrailingUnderscore.slice(1);
        },

        lcfirst(str) {
            if (!str) {
                return str;
            }
            return str.charAt(0).toLowerCase() + str.slice(1);
        }

    });
});
