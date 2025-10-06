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
            this.matchedRecordsList = [];

            console.log(this.name)
            

            // this.matchesList.push({
            //     name: 'qq11',
            //     label: 'Test Matching 1',
            //     matchedRecordsList: [
            //         {
            //             label: 'Record 1',
            //             link: `/#${this.model.name}/view/1`
            //         },
            //         {
            //             label: 'Record 2',
            //             link: `/#${this.model.name}/view/2`
            //         }
            //     ]
            // });

            // const data = {
            //     entityName: this.model.name,
            //     entityId: this.model.id
            // };
            // this.ajaxGetRequest('App/action/findRecordDuplicates', data)
            //     .success(list => {
            //         (list || []).forEach(item => {
            //             this.duplicatesList.push({
            //                 label: item.name,
            //                 link: `/#${this.model.name}/view/${item.id}`
            //             })
            //         })

            //         this.reRender();
            //     });
        },

    });
}
);