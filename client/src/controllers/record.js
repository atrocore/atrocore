/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('controllers/record', ['controller', 'view'], function (Dep, View) {

    return Dep.extend({

        viewMap: null,

        defaultAction: 'list',

        checkAccess: function (action) {
            if (this.getAcl().check(this.name, action)) {
                return true;
            }
            return false;
        },

        initialize: function () {
            this.viewMap = this.viewMap || {};
            this.viewsMap = this.viewsMap || {};
            this.collectionMap = {};
        },

        getViewName: function (type) {
            return this.viewMap[type] || this.getMetadata().get(['clientDefs', this.name, 'views', type]) || 'views/' + Espo.Utils.camelCaseToHyphen(type);
        },

        doAction(action, options) {
            action = action ? action : this.getStorage().get('list-view', this.name);

            Dep.prototype.doAction.call(this, action, options);
        },

        getTabIcon() {
            let icon = View.prototype.getTabIcon.call(this, this.name),
                defaultIcon = View.prototype.getDefaultTabIcon.call(this, this.name);

            let result = icon || defaultIcon;
            if (result && result.endsWith('.svg')) {
                const darkThemeMq = window.matchMedia("(prefers-color-scheme: dark)");
                let encodedResult = null;

                try {
                    const xhr = new XMLHttpRequest();

                    xhr.open('GET', result, false);
                    xhr.setRequestHeader('Cache-Control', 'no-cache');
                    xhr.onload = () => {
                        if (xhr.status === 200) {
                            const parser = new DOMParser();
                            const svgDoc = parser.parseFromString(xhr.responseText, 'image/svg+xml');
                            const svgEl = svgDoc.documentElement;

                            if (darkThemeMq.matches) {
                                svgEl.setAttribute('fill', '#ffffff');
                                svgEl.style.color = '#ffffff';
                            } else {
                                svgEl.setAttribute('fill', '#000000');
                                svgEl.style.color = '#000000';
                            }

                            const serializer = new XMLSerializer();
                            const svgStr = serializer.serializeToString(svgEl);
                            const svgBlob = new Blob([svgStr], {type: 'image/svg+xml'});

                            encodedResult = URL.createObjectURL(svgBlob);
                        }
                    };
                    xhr.send();

                    return encodedResult || result;
                } catch (e) {
                    return result;
                }
            } else if (result) {
                return result;
            }

            return Dep.prototype.getTabIcon.call(this);
        },

        beforeKanban: function () {
            this.handleCheckAccess('read');
        },

        kanban: function () {
            this.getCollection(function (collection) {
                this.main(this.getViewName('list'), {
                    scope: this.name,
                    collection: collection,
                    params: {
                        viewMode: 'kanban'
                    }
                });
            });
        },

        beforeList: function () {
            this.handleCheckAccess('read');
        },

        list: function (options) {
            var isReturn = options.isReturn;
            if (this.getRouter().backProcessed) {
                isReturn = true;
            }
            if (this.getMetadata().get('clientDefs', this.name, 'listViewCacheDisabled')) {
                isReturn = false;
            }

            var key = this.name + 'List';
            if (!isReturn) {
                var stored = this.getStoredMainView(key);
                if (stored) {
                    this.clearStoredMainView(key);
                }
            }

            this.getCollection(function (collection) {
                this.listenToOnce(this.baseController, 'action', function () {
                    collection.abortLastFetch();
                }, this);

                this.main(this.getViewName('list'), {
                    scope: this.name,
                    collection: collection,
                    params: options
                }, null, isReturn, key);
            }, this, false);
        },

        beforeView: function () {
            this.handleCheckAccess('read');
        },

        createViewView: function (options, model) {
            this.main(this.getViewName('detail'), {
                scope: this.name,
                model: model,
                returnUrl: options.returnUrl,
                returnDispatchParams: options.returnDispatchParams,
                params: options
            });
        },

        prepareModelView: function (model, options) {
        },

        view: function (options) {
            var id = options.id;

            var createView = function (model) {
                this.prepareModelView(model, options);
                this.createViewView.call(this, options, model);
            }.bind(this);

            if ('model' in options) {
                var model = options.model;
                createView(model);

                this.listenToOnce(model, 'sync', function () {
                    this.hideLoadingNotification();
                }, this);
                this.showLoadingNotification();
                model.fetch({
                    headers: {
                        'Entity-History': 'true',
                    }
                });

                this.listenToOnce(this.baseController, 'action', function () {
                    model.abortLastFetch();
                }, this);
            } else {
                this.getModel(function (model) {
                    model.id = id;

                    this.showLoadingNotification();
                    createView(model);
                    model.fetch({
                        main: true,
                        headers: {
                            'Entity-History': 'true',
                        }
                    });

                    this.listenToOnce(this.baseController, 'action', function () {
                        model.abortLastFetch();
                    }, this);
                });
            }
        },

        beforeCreate: function () {
            this.handleCheckAccess('create');
        },

        prepareModelCreate: function (model, options) {
            this.listenToOnce(model, 'before:save', function () {
                var key = this.name + 'List';
                var stored = this.getStoredMainView(key);
                if (stored && !stored.storeViewAfterCreate) {
                    this.clearStoredMainView(key);
                }
            }, this);

            this.listenToOnce(model, 'after:save', function () {
                var key = this.name + 'List';
                var stored = this.getStoredMainView(key);
                if (stored && stored.storeViewAfterCreate && stored.collection) {
                    this.listenToOnce(stored, 'after:render', function () {
                        stored.collection.fetch();
                    });
                }
            }, this);
        },

        create: function (options) {
            options = options || {};
            this.getModel(function (model) {
                if (options.relate) {
                    model.setRelate(options.relate);
                }

                var o = {
                    scope: this.name,
                    model: model,
                    returnUrl: options.returnUrl,
                    returnDispatchParams: options.returnDispatchParams,
                    params: options
                };

                if (options.attributes) {
                    model.set(options.attributes);
                }

                this.prepareModelCreate(model, options);

                this.main(this.getViewName('edit'), o);
            });
        },

        beforeEdit: function () {
            this.handleCheckAccess('edit');
        },

        prepareModelEdit: function (model, options) {
            this.listenToOnce(model, 'before:save', function () {
                var key = this.name + 'List';
                var stored = this.getStoredMainView(key);
                if (stored && !stored.storeViewAfterUpdate) {
                    this.clearStoredMainView(key);
                }
            }, this);
        },

        edit: function (options) {
            var id = options.id;

            this.getModel(function (model) {
                model.id = id;
                if (options.model) {
                    model = options.model;
                }

                this.prepareModelEdit(model, options);

                this.showLoadingNotification();
                this.listenToOnce(model, 'sync', function () {
                    var o = {
                        scope: this.name,
                        model: model,
                        returnUrl: options.returnUrl,
                        returnDispatchParams: options.returnDispatchParams,
                        params: options
                    };

                    if (options.attributes) {
                        o.attributes = options.attributes;
                    }

                    this.main(this.getViewName('edit'), o);
                }, this);
                model.fetch({main: true});

                this.listenToOnce(this.baseController, 'action', function () {
                    model.abortLastFetch();
                }, this);
            });
        },

        beforeMerge: function () {
            this.handleCheckAccess('edit');
        },

        merge: function (options) {
            var ids = options.ids.split(',');

            this.getModel(function (model) {
                var models = [];

                var proceed = function () {
                    this.main('views/merge', {
                        models: models,
                        scope: this.name,
                        collection: options.collection
                    });
                }.bind(this);

                var i = 0;
                ids.forEach(function (id) {
                    var current = model.clone();
                    current.id = id;
                    models.push(current);
                    this.listenToOnce(current, 'sync', function () {
                        i++;
                        if (i == ids.length) {
                            proceed();
                        }
                    });
                    current.fetch();
                }.bind(this));
            }.bind(this));
        },
        beforeCompare: function () {
            this.handleCheckAccess('edit');
        },

        compare: function (options) {
            let id = options.id
            let createView = function (model) {
                this.main('views/compare-instance', {
                    model: model,
                    scope: this.name,
                });
            }.bind(this);

            this.getModel(function (model) {
                model.id = id;
                if (options.model) {
                    model = options.model;
                }

                this.showLoadingNotification();
                this.listenToOnce(model, 'sync', function () {
                    createView(model);
                    this.hideLoadingNotification();
                }, this);
                model.fetch({main: true});

                this.listenToOnce(this.baseController, 'action', function () {
                    model.abortLastFetch();
                }, this);
            });
        },

        /**
         * Get collection for the current controller.
         * @param {collection}.
         */
        getCollection: function (callback, context, usePreviouslyFetched) {
            context = context || this;

            if (!this.name) {
                throw new Error('No collection for unnamed controller');
            }
            var collectionName = this.entityType || this.name;
            if (usePreviouslyFetched) {
                if (collectionName in this.collectionMap) {
                    var collection = this.collectionMap[collectionName];// = this.collectionMap[collectionName].clone();
                    callback.call(context, collection);
                    return;
                }
            }
            this.collectionFactory.create(collectionName, function (collection) {
                this.collectionMap[collectionName] = collection;
                this.listenTo(collection, 'sync', function () {
                    collection.isFetched = true;
                }, this);
                callback.call(context, collection);
            }, context);
        }, /**
         * Get model for the current controller.
         * @param {model}.
         */
        getModel: function (callback, context) {
            context = context || this;

            if (!this.name) {
                throw new Error('No collection for unnamed controller');
            }
            var modelName = this.entityType || this.name;
            this.modelFactory.create(modelName, function (model) {
                callback.call(context, model);
            }, context);
        },
    });

});
