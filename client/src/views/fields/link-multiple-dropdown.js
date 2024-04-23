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

Espo.define('views/fields/link-multiple-dropdown', ['views/fields/colored-multi-enum', 'views/fields/link-dropdown'], function (Dep, Link) {
    return Dep.extend({

        type: 'linkMultiple',

        namesName: null,

        idsName: null,

        foreignScope: null,

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