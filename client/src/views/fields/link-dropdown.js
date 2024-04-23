/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore GmbH.
 *
 * This Software is the property of AtroCore GmbH and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
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