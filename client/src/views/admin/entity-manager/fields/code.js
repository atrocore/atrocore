/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/fields/code', 'views/fields/varchar', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:code', () => {
                if (!this.model.get('name')) {
                    this.model.set('name', this.model.get('code'));
                }
                if (!this.model.get('namePlural')) {
                    this.model.set('namePlural', this.model.get('code') + 's');
                }
                this.model.set('code', this.sanitize(this.model.get('code')));
            });

            this.listenTo(this.model, 'change:name', () => {
                if (!this.model.get('code')) {
                    this.model.set('code', this.sanitize(this.model.get('name')));
                }
            });
        },

        sanitize(input) {
            const alphanumericOnly = input.replace(/[^a-zA-Z0-9]/g, "");

            const noLeadingNumber = alphanumericOnly.replace(/^[0-9]+/, "");

            if (noLeadingNumber.length === 0) {
                return "";
            }

            return noLeadingNumber.charAt(0).toUpperCase() + noLeadingNumber.slice(1);
        },

    });
});
