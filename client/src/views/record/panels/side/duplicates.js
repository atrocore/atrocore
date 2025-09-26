/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/panels/side/duplicates', 'view', Dep => {
        return Dep.extend({

            template: "record/panels/side/duplicates",

            events: _.extend({
                'click [data-action="findDuplicates"]': function (e) {
                    this.duplicatesList = [];
                    this.reRender();
                    this.getDuplicates();
                },
            }, Dep.prototype.events),

            setup() {
                Dep.prototype.setup.call(this);

                this.duplicatesList = [];
                if (this.model.get("id")) {
                    this.getDuplicates();
                } else {
                    this.listenToOnce(this.model, "sync", () => {
                        if (this.model.get("id")) {
                            this.getDuplicates();
                        }
                    });
                }
            },

            data() {
                return {
                    duplicatesList: this.duplicatesList
                };
            },

            getDuplicates() {
                const data = {
                    entityName: this.model.name,
                    entityId: this.model.id
                };
                this.ajaxGetRequest('App/action/findRecordDuplicates', data)
                    .success(list => {
                        (list || []).forEach(item => {
                            this.duplicatesList.push({
                                label: item.name,
                                link: `/#${this.model.name}/view/${item.id}`
                            })
                        })

                        this.reRender();
                    });
            },

        });
    }
);