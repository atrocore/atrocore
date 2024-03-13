/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/admin/auth-log-record/list', 'class-replace!treo-core:views/admin/auth-log-record/list',
    Dep => Dep.extend({

        getHeader: function () {
            return '<a href="#Admin">' + this.translate('Administration') + "</a> &raquo; " + this.getLanguage().translate('Auth Log', 'labels', 'Admin');
        }

    })
);

