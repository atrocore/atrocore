/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/panels/related-records', ['views/record/panels/associated-records'], (Dep) => Dep.extend({

        rowActionsView: 'views/record/row-actions/relationship-view-only',

        unlinkGroup: false,

        disableDeleteAll: true,

        createDisabled: true,

        getLayoutLink() {
            return `${this.scope}.related${this.scope}s`
        },

        fetchCollectionGroups(callback) {
            const data = {
                where: [
                    {
                        type: 'bool',
                        value: ['relatedAssociations'],
                        data: {
                            relatedAssociations: {
                                scope: this.scope,
                                relatedRecordId: this.model.id
                            }
                        }
                    }
                ]
            }
            this.ajaxGetRequest('Association', data).then(data => {
                this.groups = data.list.map(row => ({ id: row.id, key: row.id, label: row.name }));
                callback();
            });
        }
    })
);