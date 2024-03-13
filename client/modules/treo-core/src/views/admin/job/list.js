/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/admin/job/list', 'class-replace!treo-core:views/admin/job/list',
    Dep => Dep.extend({

        getHeader: function () {
            return this.buildHeaderHtml([
                '<a href="#ScheduledJob">' + this.translate('Scheduled Jobs', 'labels', 'Admin') + '</a>',
                this.getLanguage().translate('Jobs', 'labels', 'Admin')
            ], true);
        }

    })
);

