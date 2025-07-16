/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/associated-record/record/edit-small', 'views/record/edit-small',
    Dep => Dep.extend({
        getAssociationScope() {
            return this.getMetadata().get(`scopes.${this.model.name}.associatesForEntity`)
        },

        prepareLayoutData(data) {
            const scope = this.getAssociationScope()
            if (!this.model.id && !this.model.get(`related${scope}Id`)) {
                data.layout = JSON.parse(JSON.stringify(data.layout).replace(`"related${scope}"`, `"related${scope}s"`));
            }

            Dep.prototype.prepareLayoutData.call(this, data);
        }
    })
);

