/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/detail/compare-entities', ['views', 'views/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        template: 'selection/record/compare-entities',

        models: [],

        hidePanelNavigation: true,

        setup() {
            this.models = [];
            this.selectionModel = this.options.model;
            this.selectionId = this.selectionModel.id;
            this.models = this.options.models || this.models;
            this.model = this.getModels().length ? this.getModels()[0] : null;
            this.scope = this.name = this.options.scope || this.model?.name;
        },

        data() {
            let columns = this.getColumns();
            return {
                title: '',
                columns: columns,
                columnLength: column.length,
                scope: this.scope,
                showOverlay: this.showOverlay
            };
        },

        getRecordButtons() {
            return this.getParentView().getCompareButtons();
        },

        canLoadActivities(){
            return true;
        },

        getSvelteSideViewProps(parentView) {
            let thisClone = Espo.utils.clone(this);

            thisClone.scope = 'Selection';
            thisClone.model = this.selectionModel;
            thisClone.mode = 'detail';

            let option = Detail.prototype.getSvelteSideViewProps.call(thisClone, parentView);

            option.showSummary = true;
            option.isCollapsed = false;

            return option;
        },

        getColumns() {
            let columns = [];

            columns.push({
                name: this.translate('Name'),
                isFirst: true,
            });

            this.getModels().forEach((model) => {
                let hasName = !!this.getMetadata().get(['entityDefs', model.name, 'fields', 'name', 'type'])
                return columns.push({
                    id: model.id,
                    entityType: model.name,
                    selectionRecordId: model.get('_selectionRecordId'),
                    label: model.get('name') ?? model.get('id'),
                    name: `<a href="#/${model.name}/view/${model.id}"  target="_blank"> ${hasName ? (model.get('name') ?? 'None') : model.get('id')} </a>`,
                });
            });

            return columns;
        },
    });
});
