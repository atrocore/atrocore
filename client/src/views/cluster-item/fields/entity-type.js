/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/cluster-item/fields/entity-type', 'views/fields/entity-type', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
            if(this.model.isNew() && this.model._relateData?.model) {
                this.model.set(this.name, this.model._relateData?.model.get('masterEntity'));
            }
        },

        setupOptions: function () {
            var scopes = this.scopesMetadataDefs = this.getMetadata().get('scopes');
            let masterEntity =  this.model._relateData?.model.get('masterEntity');
            this.params.options = Object.keys(scopes).filter(function (scope) {
                if(scope === masterEntity) {
                    return true;
                }

                if(scopes[scope].primaryEntityId === masterEntity) {
                    return true;
                }

                return false;
            }.bind(this)).sort(function (v1, v2) {
                return this.translate(v1, 'scopeNames').localeCompare(this.translate(v2, 'scopeNames'));
            }.bind(this));
        },

    });
});