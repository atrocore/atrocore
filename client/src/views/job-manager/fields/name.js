/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/job-manager/fields/name', 'views/fields/varchar',
    Dep => Dep.extend({

        listTemplate: 'job-manager/fields/name/list',

        listLinkTemplate: 'job-manager/fields/name/list-link',

        detailTemplate: 'job-manager/fields/name/detail',

        data() {
            return _.extend({mutedText: ['Success', 'Failed'].includes(this.model.get('status'))}, Dep.prototype.data.call(this));
        },

        afterRender() {

        }

    })
);

