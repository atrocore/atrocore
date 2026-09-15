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

        getBreadcrumbsItems: function (isAdmin = false) {
            const result = [
                {
                    url: '#Entity',
                    label: this.getLanguage().translate('Entity', 'scopeNamesPlural')
                }
            ];

            if (this.model.get('entityId')) {
                result.push({
                    url: `#Entity/view/${this.model.get('entityId')}`,
                    label: this.model.get('entityName') || this.model.get('entityId')
                });
            }

            result.push({
                url: `#${this.scope}/view/${this.model.id}`,
                label: this.getLabel(),
                className: 'header-title'
            });

            return result;
        }

    });
});
