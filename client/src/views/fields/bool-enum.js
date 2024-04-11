/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/bool-enum', 'views/fields/enum',
    Dep => Dep.extend({
        prohibitedEmptyValue: true,
        fieldName: '',
        init(){
            this.fieldName = this.options.name ?? this.options.defs.name
            this.options.name = '_'+this.fieldName
            this.options.defs.name = '_'+this.fieldName
            Dep.prototype.init.call(this);
        },
        setup(){
            this.model.set(this.options.name, (this.model.get(this.fieldName) ?? null) + '')
            this.listenTo(this.model, 'change:'+this.options.name, function(){
                let value = this.model.get(this.options.name);
                if(value === "null"){
                    value = null;
                }else {
                    value = value === "true";
                }
                this.model.set(this.fieldName, value)
            })

            this.listenTo(this.model, 'change:'+this.fieldName, function(){
                let value = this.model.get(this.fieldName);
                if(this.model.get(this.options.name) === value + ''){
                    return
                }
                this.model.set(this.options.name, value + '')
            })

            Dep.prototype.setup.call(this);
        },
        setupOptions(){
            this.params.options = ["null", "true", "false"]
            this.translatedOptions = {
                "null":"NULL",
                "true":"☑",
                "false":"☐"
            };
        }
    })
);