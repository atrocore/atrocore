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

        // the prefix the code of a subtype must start with, e.g. "ExportFeedType"
        subtypePrefix: '',

        setup() {
            Dep.prototype.setup.call(this);

            this.subtypePrefix = this.getSubtypePrefix();

            this.listenTo(this.model, 'change:code', () => {
                this.model.set('code', this.prepareCode(this.model.get('code')));

                const name = this.getNameFromCode();
                if (!name) {
                    return;
                }
                if (!this.model.get('name')) {
                    this.model.set('name', name);
                }
                if (!this.model.get('namePlural')) {
                    this.model.set('namePlural', name + 's');
                }
            });

            this.listenTo(this.model, 'change:name', () => {
                if (!this.getNameFromCode()) {
                    this.model.set('code', this.prepareCode(this.subtypePrefix + this.sanitize(this.model.get('name') || '')));
                }
            });

            this.listenTo(this.model, 'change:type change:supertypeId', () => {
                if (this.model.id) {
                    return;
                }

                const suffix = this.getNameFromCode();
                this.subtypePrefix = this.getSubtypePrefix();
                this.model.set('code', this.prepareCode(this.subtypePrefix + suffix));
            });
        },

        getSubtypePrefix() {
            if (this.model.get('type') === 'Subtype' && this.model.get('supertypeId')) {
                return this.model.get('supertypeId') + 'Type';
            }

            return '';
        },

        getNameFromCode() {
            const code = this.model.get('code') || '';

            return this.subtypePrefix && code.startsWith(this.subtypePrefix) ? code.slice(this.subtypePrefix.length) : code;
        },

        prepareCode(code) {
            code = this.sanitize(code || '');

            if (this.model.id || !this.subtypePrefix) {
                return code;
            }

            // keep the prefix of the subtype even if the user removes it
            const suffix = code.startsWith(this.subtypePrefix) ? code.slice(this.subtypePrefix.length) : code;

            return this.subtypePrefix + this.sanitize(suffix);
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
