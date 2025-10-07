/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/panels/side/matched-records', 'view', Dep => {
    return Dep.extend({

        template: "record/panels/side/matched-records",

        matchedRecordsList: [],

        setup() {
            Dep.prototype.setup.call(this);

            if (this.model.get("id")) {
                this.getMatchedRecords();
            } else {
                this.listenToOnce(this.model, "sync", () => {
                    if (this.model.get("id")) {
                        this.getMatchedRecords();
                    }
                });
            }
        },

        data() {
            return {
                matchedRecordsList: this.matchedRecordsList
            };
        },

        getMatchedRecords() {
            this.ajaxGetRequest('Matching/action/matchedRecords', { code: this.name, entityName: this.model.name, entityId: this.model.id })
                .success(res => {
                    this.matchedRecordsList = [];
                    (res.list || []).forEach(item => {
                        this.matchedRecordsList.push({
                            label: item.name,
                            link: `/#${res.entityName}/view/${item.id}`
                        })
                    })
                    this.reRender();
                });
        },

    });
}
);