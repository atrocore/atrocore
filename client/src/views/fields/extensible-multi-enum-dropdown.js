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

Espo.define('views/fields/extensible-multi-enum-dropdown', 'views/fields/link-multiple-dropdown', function (Dep) {
    return Dep.extend({
        setup: function () {
            this.idsName = this.name;
            this.namesName = this.name + 'Names';
            this.foreignScope = 'ExtensibleEnumOption';

            Dep.prototype.setup.call(this);
        },

        prepareOptionsList: function () {
            this.params.options = [];
            this.translatedOptions = {};
            this.params.optionColors = [];

            this.getListOptionsData(this.getExtensibleEnumId()).forEach(option => {
                if (option.id) {
                    this.params.options.push(option.id);
                    this.translatedOptions[option.id] = option.name || option.id;
                    this.params.optionColors.push(option.color || null);
                }
            });
        },

        getExtensibleEnumId() {
            let extensibleEnumId = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'extensibleEnumId']);
            if (this.params.extensibleEnumId) {
                extensibleEnumId = this.params.extensibleEnumId;
            }

            return extensibleEnumId;
        }
    });
});