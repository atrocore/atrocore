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
            Dep.prototype.setup.call(this);

            this.params.options = [];
            this.translatedOptions = {'': ''};

            $.each(this.getMetadata().get('fields'), (type, typeDefs) => {
                if (!typeDefs.notCreatable) {
                    this.params.options.push(type);
                }
                this.translatedOptions[type] = this.translate(type, 'fieldTypes', 'Admin');
            })

            this.params.options.sort((v1, v2) => {
                return this.translate(v1, 'fieldTypes', 'Admin').localeCompare(this.translate(v2, 'fieldTypes', 'Admin'));
            });
        },

    });
});
