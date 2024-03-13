/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset-type/detail', 'views/detail',
    Dep => Dep.extend({

        getHeader() {
            let name = Handlebars.Utils.escapeExpression(this.model.get('name'));
            if (name === '') {
                name = this.model.id;
            }

            const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;

            return this.buildHeaderHtml([
                `<a href="${rootUrl}" class="action" data-action="navigateToRoot">${this.getLanguage().translate(this.scope, 'scopeNamesPlural')}</a>`,
                name
            ], true);
        },

    })
);