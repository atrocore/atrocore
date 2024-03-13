/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/modals/entity-asset-item', 'view', Dep => {
    return Dep.extend({
        template : "asset/modals/entity-asset-item",
        type     : null,

        data() {
            let data = {};

            data.preview = this.model.get('filePathsData').thumbs.small;
            
            return data;
        },
        
        setup() {
            this.type = this.options.assetType;
            
            this.createView("entityAssetEdit", "views/asset/modals/entity-asset-form", {
                model: this.model,
                el   : this.options.el + " .edit-form"
            });
        },
        
        validate() {
            let notValid = false;
            for (let key in this.nestedViews) {
                const view = this.nestedViews[key];
                if (view && typeof view.validate === 'function') {
                    notValid = view.validate() || notValid;
                }
            }
            return notValid;
        },

    });
});