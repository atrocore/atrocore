/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

/**
 * On UI a subtype record is shown via its supertype record, so the breadcrumbs lead to the supertype.
 */
Espo.define('views/subtype/breadcrumbs', [], () => ({

    prepare(view, items) {
        const subtype = view.scope;
        const supertype = view.getMetadata().get(['scopes', subtype, 'supertypeId']);
        const subtypeUrl = '#' + subtype;

        return items.map(item => {
            if (!item.url || (item.url !== subtypeUrl && !item.url.startsWith(subtypeUrl + '/'))) {
                return item;
            }

            if (item.url === subtypeUrl) {
                return Object.assign({}, item, {
                    url: '#' + supertype,
                    label: view.getLanguage().translate(supertype, 'scopeNamesPlural')
                });
            }

            if (item.url === `${subtypeUrl}/view/${view.model.id}`) {
                return Object.assign({}, item, {url: `#${supertype}/view/${view.model.get('supertypeId')}`});
            }

            return Object.assign({}, item, {url: '#' + supertype + item.url.slice(subtypeUrl.length)});
        });
    },

}));
