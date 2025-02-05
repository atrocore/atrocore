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

Espo.define('views/ui-handler/fields/trigger-action', 'views/fields/extensible-enum-dropdown',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.changeOptions()
            this.listenTo(this.model, 'change:type', () => {
                this.changeOptions()
            });
        },

        getDisabledOptions() {
            return []
        },

        changeOptions() {
            this.disableOptions(this.getDisabledOptions())
        },

    })
);
