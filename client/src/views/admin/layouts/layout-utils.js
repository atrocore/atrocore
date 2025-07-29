/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/layouts/layout-utils', [], function () {
    return {
        renderComponent(params) {
            if (window.layoutSvelteComponent) {
                try {
                    window.layoutSvelteComponent.$destroy()
                } catch (e) {
                }
            }

            window.layoutSvelteComponent = new Svelte.LayoutComponent({
                target: $('#layout-content').get(0),
                props: {
                    params: {
                        ...params,
                        onEditPanel: (panel, panelDataAttributeList, attributeDefs, callback) => {
                            const attributes = {};
                            const attributeList = []

                            panelDataAttributeList.forEach(function (item) {
                                if (item !== 'id') {
                                    attributeList.push(item)
                                    attributes[item] = panel[item];
                                }
                            }, this);

                            this.createView('dialog', 'views/admin/layouts/modals/panel-attributes', {
                                attributeList: attributeList,
                                attributeDefs: attributeDefs,
                                attributes: attributes
                            }, function (view) {
                                view.render();
                                this.listenTo(view, 'after:save', function (attributes) {
                                    callback(attributes)
                                    view.close();
                                }, this);
                            }, this);
                        },
                        openEditDialog: (attributes, scope, dataAttributeList, dataAttributesDefs, callback) => {
                            this.createView('editModal', 'views/admin/layouts/modals/edit-attributes', {
                                name: attributes.name,
                                scope: scope,
                                attributeList: dataAttributeList.slice(1),
                                attributeDefs: dataAttributesDefs,
                                attributes: attributes,
                                languageCategory: this.languageCategory
                            }, function (view) {
                                view.render();
                                this.listenToOnce(view, 'after:save', function (attributes) {
                                    callback(attributes)
                                    view.close();
                                }, this);
                            }.bind(this));
                        },
                        openAddAttributesDialog: (entity, callback) => {
                            const scope = 'Attribute';
                            const viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', 'select']) || 'views/modals/select-records';

                            this.notify('Loading...');
                            this.createView('dialog', viewName, {
                                scope: scope,
                                multiple: true,
                                createButton: false,
                                massRelateEnabled: false,
                                boolFilterList: ['onlyForEntity'],
                                boolFilterData: {
                                    onlyForEntity: entity
                                },
                                allowSelectAllResult: false,
                            }, dialog => {
                                dialog.render();
                                this.notify(false);
                                dialog.once('select', models => {
                                    let ids = [];
                                    models.forEach(model => {
                                        ids.push(model.id);
                                    })

                                    this.wait(true);
                                    this.notify('Loading...');
                                    this.ajaxGetRequest('Attribute/action/attributesDefs', {
                                        entityName: entity,
                                        attributesIds: ids
                                    }, {async: false}).success(res => {
                                        let fields = [];
                                        $.each(res, (field, fieldDefs) => {
                                            this.getMetadata().data.entityDefs[entity].fields[field] = fieldDefs;
                                            this.getLanguage().data[entity].fields[field] = fieldDefs.label;

                                            fieldDefs.name = field;
                                            fields.push(fieldDefs);
                                        });

                                        this.wait(false);
                                        this.notify(false);

                                        this.clearView('dialog');

                                        this.notify('Added', 'success');

                                        callback(fields);
                                    })
                                });
                            });
                        }

                    }
                }
            });
        }
    }
})