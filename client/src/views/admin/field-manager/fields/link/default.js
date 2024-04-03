/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/link/default', 'views/fields/link', function (Dep) {

    return Dep.extend({

        data: function () {
            var defaultAttributes = this.model.get('defaultAttributes') || {};
            var nameValue = defaultAttributes[this.options.field + 'Name'] || null;
            var idValue = defaultAttributes[this.options.field + 'Id'] || null;

            if (idValue) {
                const id = idValue;

                idValue = null;
                nameValue = null;

                $.ajax({
                    url: this.foreignScope + '/' + id + '?silent=true',
                    type: 'GET',
                    async: false,
                }).done(function (response) {
                    idValue = response.id;
                    nameValue = response.name;
                });
            }

            var data = Dep.prototype.data.call(this);

            data.nameValue = nameValue;
            data.idValue = idValue;

            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.foreignScope = this.getMetadata().get(['entityDefs', this.options.scope, 'links', this.options.field, 'entity']);
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);

            var defaultAttributes = {};
            defaultAttributes[this.options.field + 'Id'] = data[this.idName];
            defaultAttributes[this.options.field + 'Name'] = data[this.nameName];

            if (data[this.idName] === null) {
                defaultAttributes = null;
            }

            return {
                defaultAttributes: defaultAttributes
            };
        }

    });

});
