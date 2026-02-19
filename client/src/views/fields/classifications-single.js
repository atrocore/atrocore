/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/classifications-single', ['views/fields/link', 'views/fields/classifications'],
    (Dep, Classifications) => Dep.extend({

        idName: 'classificationsId',

        nameName: 'classificationsName',

        originalIdName: 'classificationsIds',

        originalNameName: 'classificationsNames',

        setup() {
            this.setupTempFields();
            this.selectBoolFilterList = Classifications.prototype.selectBoolFilterList;
            this.boolFilterData = Classifications.prototype.boolFilterData;

            Dep.prototype.setup.call(this);

            this.setupModel();
        },

        setupModel() {
            this.listenTo(this.model, 'change:' + this.idName, () => {
                let name = {}
                if (this.model.get(this.idName)) {
                    name[this.model.get(this.idName)] = this.model.get(this.nameName);
                }

                this.model.set(this.originalIdName, this.model.get(this.idName) ? [this.model.get(this.idName)] : []);
                this.model.set(this.originalNameName, name);
            });

            this.listenTo(this.model, 'change:' + this.originalIdName, this.setupTempFields);
        },

        setupTempFields: function () {
            const classificationId = this.model.get(this.originalIdName)?.at(-1);
            this.model.set(this.idName, classificationId ?? null, { silent: true });
            this.model.set(this.nameName, (this.model.get(this.originalNameName) ?? [])[classificationId] ?? null, { silent: true });
        },

        onModelChanged() {
            this.setupTempFields()
            this.setupModel()
        },

        clearLink: function () {
            if (this.mode === 'search') {
                this.searchData.idValue = null;
                this.searchData.nameValue = null;
            }

            Dep.prototype.clearLink.call(this);
        },

        fetch: function () {
            const data = Dep.prototype.fetch.call(this);
            const ids = [];
            const names = {};

            if (data[this.idName]) {
                ids.push(data[this.idName]);
                names[data[this.idName]] = data[this.nameName]
            }

            data[this.originalIdName] = ids;
            data[this.originalNameName] = names;

            return data;
        },

        inlineEditSave: function () {
            Classifications.prototype.inlineEditSave.call(this);
        },

        createFilterView: function (rule, inputName, type, delay = true) {
            Classifications.prototype.createFilterView.call(this, rule, inputName, type, delay);
        },

        createQueryBuilderFilter(type = null) {
            return Classifications.prototype.createQueryBuilderFilter.call(this, type);
        },

        hasLockedControls() {
            return false;
        }
    })
);
