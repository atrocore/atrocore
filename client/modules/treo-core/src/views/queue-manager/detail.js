/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/queue-manager/detail', 'views/detail', function (Dep) {

    return Dep.extend({

        getHeader() {
            const name = this.model.get('name') || this.model.id;
            const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
            const headerIconHtml = this.getHeaderIconHtml();

            return this.buildHeaderHtml([
                headerIconHtml +
                `<a href="${rootUrl}" class="action" data-action="navigateToRoot">` +
                    this.getLanguage().translate(this.scope, 'scopeNamesPlural') +
                `</a>`,
                name
            ]);
        },
    });
});

