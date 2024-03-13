/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/admin/auth-token/list', 'class-replace!treo-core:views/admin/auth-token/list',
    Dep => Dep.extend({

        getHeader: function () {
            return  `<div class="header-breadcrumbs fixed-header-breadcrumbs"><div class="breadcrumbs-wrapper"><a href="#Admin">${this.translate('Administration')}</a>` +
                    this.getLanguage().translate('Auth Tokens', 'labels', 'Admin')
                    + `</div></div><div class="header-title">${this.getLanguage().translate('Auth Tokens', 'labels', 'Admin')}</div>`
        }

    })
);

