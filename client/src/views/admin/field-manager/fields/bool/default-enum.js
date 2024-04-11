/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/bool/default-enum', 'views/fields/bool-enum', function (Dep) {

    return Dep.extend({

        init(){
            this.options.name = 'default';
            this.options.defs.name = 'default'

            Dep.prototype.init.call(this);
        },
        afterRender(){
            Dep.prototype.afterRender.call(this);
            this.toggleField(this.model.get('disableNullValue'))
            this.listenTo(this.model, 'change:disableNullValue', function(){
                if(this.model.get('disableNullValue')){
                    this.model.set('default',false);
                    this.toggleField(this.model.get('disableNullValue'))
                }
            })
        },

        toggleField(hide) {
            const $default = this.$el.parents('.panel-body').find('.cell[data-name=defaultEnum]');

            if (hide) {
                $default.addClass('hidden');
            } else {
                this.model.set('required', false)
                $default.removeClass('hidden');
            }
        },

        fetch(){
            if(!this.model.get('disableNullValue')){
                let data = Dep.prototype.fetch.call(this);
                let value = data['_default']
                delete  data['_default']
                if(value === "null"){
                    value = null;
                }else {
                    value = value === "true";
                }
                data['default'] = value;
                return data;
            }
        }

    });

});
