/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/link-multiple-dropdown', ['views/fields/colored-multi-enum', 'views/fields/link-dropdown'], function (Dep, Link) {
    return Dep.extend({

        type: 'linkMultiple',

        namesName: null,

        idsName: null,

        foreignScope: null,

        foreignName: null,

        originalName: null,

        setup: function () {
            if (this.namesName === null) {
                this.namesName = this.name + 'Names';
            }
            if (this.idsName === null) {
                this.idsName = this.name + 'Ids';
            }

            this.prepareDefaultValue();

            this.foreignScope = this.options.foreignScope || this.foreignScope || this.model.getFieldParam(this.name, 'entity') || this.model.getLinkParam(this.name, 'entity');
            this.foreignName = this.foreignName || this.params.foreignName || this.model.getFieldParam(this.name, 'foreignName') || 'name';
            this.originalName = this.name;
            this.name = this.idsName;

            this.prepareOptionsList();

            Dep.prototype.setup.call(this);
        },

        prepareDefaultValue: function () {
            this.params.options = [];
            this.translatedOptions = {};

            const names = this.model.get(this.namesName);

            if (this.model.has(this.idsName)) {
                this.params.options = Espo.utils.clone(this.model.get(this.idsName));
                this.translatedOptions = typeof names === 'object' ? Espo.utils.clone(names) : {};
                this.params.default = Espo.utils.clone(this.model.get(this.idsName));
            }
        },

        prepareOptionsList: function () {
            Link.prototype.prepareOptionsList.call(this);
        },

        getLabelText: function () {
            Link.prototype.getLabelText.call(this);
        }
    });
});