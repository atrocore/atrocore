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
                    }
                }
            });
        }
    }
})