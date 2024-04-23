/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/link-dropdown', 'views/fields/colored-enum', function (Dep) {
    return Dep.extend({

        type: 'link',

        nameName: null,

        idName: null,

        foreignScope: null,

        originalName: null,

        setup: function () {
            if (this.nameName == null) {
                this.nameName = this.name + 'Name';
            }

            if (this.idName == null) {
                this.idName = this.name + 'Id';
            }

            this.prepareDefaultValue();

            this.foreignScope = this.options.foreignScope || this.foreignScope;
            this.foreignScope = this.foreignScope || this.model.getFieldParam(this.name, 'entity') || this.model.getLinkParam(this.name, 'entity');
            this.originalName = this.name;
            this.name = this.idName;

            this.prepareOptionsList();

            Dep.prototype.setup.call(this);
        },

        prepareDefaultValue: function () {
            this.params.options = [];
            this.translatedOptions = {};

            if (this.model.has(this.idName)) {
                this.params.options.push(this.model.get(this.idName));
                this.translatedOptions[this.model.get(this.idName)] = this.model.get(this.nameName) || this.model.get(this.idName);
                this.params.default = this.model.get(this.idName);
            }
        },

        prepareOptionsList: function () {
            this.params.options = [];
            this.translatedOptions = {};

            this.getLinkOptions(this.foreignScope).forEach(option => {
                if (option.id) {
                    this.params.options.push(option.id);
                    this.translatedOptions[option.id] = option.name || option.id;
                }
            });
        },

        getValueForDisplay: function () {
            return this.model.get(this.idName);
        },

        getLabelText: function () {
            return this.options.labelText || this.translate(this.originalName, 'fields', this.model.name);
        }
    });
});