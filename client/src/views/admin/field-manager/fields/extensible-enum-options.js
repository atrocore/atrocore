/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/extensible-enum-options', ['views/fields/base', 'view-record-helper'], (Dep, ViewRecordHelper) => {

    return Dep.extend({

        listTemplate: 'fields/field-value-container',
        detailTemplate: 'fields/field-value-container',
        editTemplate: 'fields/field-value-container',

        setup() {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:extensibleEnumId', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.hide();
            this.$el.parent().find('label[data-name="extensibleEnumOptions"]').remove();

            if (this.model.get('extensibleEnumId')) {
                let scope = 'ExtensibleEnum';
                this.getModelFactory().create(scope, model => {
                    model.set('id', this.model.get('extensibleEnumId'));
                    model.fetch().success(() => {
                        let options = {
                            el: `${this.options.el} > .field[data-name="valueField"]`,
                            model: model,
                            scope: scope,
                            staticAllowedPanelNames: ["extensibleEnumOptions"],
                            recordHelper: new ViewRecordHelper(),
                            canClose: false
                        };

                        this.createView('valueField', 'views/record/detail-bottom', options, view => {
                            view.render();
                            this.listenTo(view.model,'prepareAttributesForCreateRelated', function(params, link, prepareAttributeCallback){
                                  prepareAttributeCallback({
                                      "listMultilingual": view.model.get('multilingual')
                                  })
                            })
                            this.show();
                        });
                    });
                });
            }
        },

    });
});