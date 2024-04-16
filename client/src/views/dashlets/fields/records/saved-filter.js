/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('views/dashlets/fields/records/saved-filter', 'views/fields/enum', function (Dep) {

    return Dep.extend({
        presetFilterList: [],
        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entityType', function () {
                this.setupOptions();
                this.reRender();
            }, this);
        },
        setupOptions: function () {
            var entityType = this.model.get('entityType');
            if (!entityType) {
                this.params.options = [];
                return;
            }

            this.presetFilterList = [];

            ((this.getPreferences().get('presetFilters') || {})[entityType] || []).forEach(function (item) {
                this.presetFilterList.push(item);
            }, this);

            this.translatedOptions = {};
            this.params.options = [];
            this.presetFilterList.forEach(function (item) {
                this.params.options.push(item.id)
                this.translatedOptions[item.id] = this.translate(item.label, 'presetFilters', entityType);
            }, this);

            if(this.model.get(this.name)
                && this.model.get(this.name+'Data')
                && !this.params.options.includes(this.model.get(this.name))){
                let item = this.model.get(this.name+'Data')
                this.params.options.push(item.id)
                this.translatedOptions[item.id] = this.translate(item.label, 'presetFilters', entityType);
            }
        },
        fetch(){
            let data = Dep.prototype.fetch.call(this);
            if(data[this.name]){
                data[this.name+"Data"] = this.presetFilterList.filter(item => item.id === data[this.name])?.at(0) ?? null
            }
            return data
        }
    });

});
