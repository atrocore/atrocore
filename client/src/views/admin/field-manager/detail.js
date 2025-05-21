/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/detail', 'views/detail', Dep => {

    return Dep.extend({
        createLink: function (scope, id, link, data) {
            if (link === 'extensibleEnumOptions') {
                scope = 'ExtensibleEnum'
                id = this.model.get('extensibleEnumId')
            }

            return Dep.prototype.createLink.call(this, scope, id, link, data)
        },

        updateRelationshipPanel: function (name) {
            if (name === 'extensibleEnumOptions') {
                const rel = this.getView('record')?.getView('middle')
                    ?.getView('extensibleEnumOptions')?.getView('valueField')
                    ?.getView('extensibleEnumOptions');

                if (rel) {
                    rel.collection.fetch();
                }
                return
            }
            Dep.prototype.updateRelationshipPanel.call(this, name)
        },
    });
});
