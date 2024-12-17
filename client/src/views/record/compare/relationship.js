/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare/relationship', 'views/record/list', function (Dep) {
    return Dep.extend({
        template: 'record/compare/relationship',

        relationshipsFields: [],

        instances: [],

        columns: [],

        currentItemModels: [],

        otherItemModels: [],

        setup() {
            this.scope = this.options.scope;
            this.baseModel = this.options.model;
            this.relationship = this.options.relationship;
            this.collection = this.options.collection;
            this.instanceComparison = this.options.instanceComparison ?? this.instanceComparison;
            this.columns = this.options.columns
            this.checkedList = [];
            this.enabledFixedHeader = false;
            this.dragableListRows = false;
            this.showMore = false
            this.fields = [];
            this.currentItemModels = [];
            this.otherItemModels = [];
            this.instances = this.getMetadata().get(['app', 'comparableInstances']);

            this.fetchModelsAndSetup();
        },

        fetchModelsAndSetup() {
            this.wait(true)
            let nonComparableFields = this.getMetadata().get('scopes.' + this.relationship.scope + '.nonComparableFields') ?? [];

            this.getHelper().layoutManager.get(this.relationship.scope, 'listSmall', layout => {
                if (layout && layout.length) {
                    let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.relationship.scope, 'read');
                    layout.forEach(item => {
                        if (item.name && !forbiddenFieldList.includes(item.name) && !nonComparableFields.includes(item.name)) {
                            this.fields.push(item.name);
                        }
                    });

                    let selectField = [];

                    this.fields.forEach(field => {
                        let fieldType = this.getMetadata().get(['entityDefs', 'Product', 'fields', field, 'type']);
                        if (fieldType) {
                            this.getFieldManager().getAttributeList(fieldType, field).forEach(attribute => {
                                selectField.push(attribute);
                            });
                        }
                    })

                    this.prepareModels(selectField, () => this.setupRelationship(() => this.wait(false)));
                }
            });
        },

        data() {
            let totalLength = 0;
            let minWidth = 150;
            let columns = [];
            this.columns.forEach((el, key) => {
                let i = Espo.Utils.cloneDeep(el);
                if (key === 0) {
                    totalLength += i.minWidth = minWidth;
                    i.itemColumnCount = 1;
                } else if (key === 1) {
                    i.itemColumnCount = Math.max(this.currentItemModels.length, 1)
                    totalLength += i.minWidth = minWidth;
                } else {
                    i.itemColumnCount = Math.max(this.otherItemModels[key - 2].length, 1);
                    totalLength += i.minWidth = minWidth * i.itemColumnCount;
                }
                columns.push(i)
            });

            return {
                name: this.relationship.name,
                scope: this.scope,
                relationScope: this.relationship.scope,
                columns,
                relationshipsFields: this.relationshipsFields,
                columnCountCurrent: Math.max(this.currentItemModels.length, 1),
                currentItemModels: this.currentItemModels,
                totalLength,
                minWidth
            }
        },

        setupRelationship(callback) {
            this.relationshipsFields = [];
            this.fields.forEach((field) => {
                let data = {
                    field,
                    currentViewKeys: [],
                    othersModelsKeyPerInstances: []
                }

                this.currentItemModels.forEach((model, index) => {
                    let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(model.getFieldType(field));
                    let viewKey = this.relationship.name + field + index + 'Current';
                    data.currentViewKeys.push({key: viewKey})
                    this.createView(viewKey, viewName, {
                        el: this.options.el + ` [data-field="${viewKey}"]`,
                        model: model,
                        readOnly: true,
                        defs: {
                            name: field,
                        },
                        mode: 'detail',
                        inlineEditDisabled: true,
                    });
                })

                this.otherItemModels.forEach((instanceModels, index1) => {
                    data.othersModelsKeyPerInstances[index1] = [];
                    instanceModels.forEach((model, index2) => {
                        let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(model.getFieldType(field));
                        let viewKey = this.relationship.name + field + index1 + 'Others' + index2;
                        data.othersModelsKeyPerInstances[index1].push({key: viewKey})
                        this.createView(viewKey, viewName, {
                            el: this.options.el + ` [data-field="${viewKey}"]`,
                            model: model,
                            readOnly: true,
                            defs: {
                                name: field,
                            },
                            mode: 'detail',
                            inlineEditDisabled: true,
                        }, view => {
                            view.render()
                            this.updateBaseUrl(view, index1);
                        });
                    });
                });
                this.relationshipsFields.push(data);
            });
            callback();
        },

        fullTableScroll() {
            let list = this.$el.find('.list');
            if (list.length) {
                let fixedTableHeader = list.find('.fixed-header-table');
                let fullTable = list.find('.full-table');
                let scroll = this.$el.find('.list > .panel-scroll');

                if (fullTable.length) {
                    if (scroll.length) {
                        scroll.scrollLeft(0);
                        scroll.addClass('hidden');
                    }

                    fullTable.find('thead').find('th').each(function (i, elem) {
                        let width = elem.width;

                        if (width) {
                            if (i in this.baseWidth) {
                                width = this.baseWidth[i];
                            }

                            if (typeof width === 'string' && width.match(/[0-9]*(%)/gm)) {
                                this.baseWidth[i] = width;
                                width = list.outerWidth() * parseInt(width) / 100;

                                if (width < 100) {
                                    width = 100;
                                }
                            }

                            elem.width = width;
                        }
                    }.bind(this));

                    fixedTableHeader.addClass('table-scrolled');
                    fullTable.addClass('table-scrolled');

                    let rowsButtons = this.$el.find('td[data-name="buttons"]');
                    if ($(window).outerWidth() > 768 && rowsButtons.length) {
                        rowsButtons.addClass('fixed-button');
                        rowsButtons.each(function () {
                            let a = $(this).find('.list-row-buttons');

                            if (a) {
                                let width = -1 * (fullTable.width() - list.width() - $(this).width()) - a.width() - 5;
                                a.css('left', width);
                            }
                        });
                    }

                    let prevScrollLeft = 0;

                    list.off('scroll');
                    list.on('scroll', () => {
                        if (prevScrollLeft !== list.scrollLeft()) {
                            let fixedTableHeaderBasePosition = list.offset().left + 1 || 0;
                            fixedTableHeader.css('left', fixedTableHeaderBasePosition - list.scrollLeft());

                            if ($(window).outerWidth() > 768 && rowsButtons.hasClass('fixed-button')) {
                                rowsButtons.each(function () {
                                    let a = $(this).find('.list-row-buttons');

                                    if (a) {
                                        let width = list.scrollLeft() - (fullTable.width() - list.width() - $(this).width()) - a.width() - 5;
                                        a.css('left', width);
                                    }
                                });
                            }
                        }
                        prevScrollLeft = list.scrollLeft();
                    });

                    if (this.hasHorizontalScroll()) {

                        // custom scroll for relationship panels
                        if (scroll.length) {
                            scroll.removeClass('hidden');

                            scroll.css({width: list.width(), display: 'block'});
                            scroll.find('div').css('width', fullTable.width());
                            rowsButtons.each(function () {
                                let a = $(this).find('.list-row-buttons');

                                if (a) {
                                    let width = list.scrollLeft() - (fullTable.width() - list.width() - $(this).width()) - a.width() - 5;
                                    a.css('left', width);
                                }
                            });

                            this.listenTo(this.collection, 'sync', function () {
                                if (!this.hasHorizontalScroll()) {
                                    scroll.addClass('hidden');
                                }
                            }.bind(this));

                            scroll.on('scroll', () => {
                                fullTable.css('left', -1 * scroll.scrollLeft());
                                rowsButtons.each(function () {
                                    let a = $(this).find('.list-row-buttons');

                                    if (a) {
                                        let width = scroll.scrollLeft() - (fullTable.width() - list.width() - $(this).width()) - a.width() - 5;
                                        a.css('left', width);
                                    }
                                });
                            });

                            if ($(window).width() < 768) {
                                let touchStartPosition = 0,
                                    touchFinalPosition = 0,
                                    currentScroll = 0;

                                list.on('touchstart', function (e) {
                                    touchStartPosition = e.originalEvent.targetTouches[0].pageX;
                                    currentScroll = scroll.scrollLeft();
                                }.bind(this));

                                list.on('touchmove', function (e) {
                                    touchFinalPosition = e.originalEvent.targetTouches[0].pageX;

                                    scroll.scrollLeft(currentScroll - (touchFinalPosition - touchStartPosition));
                                }.bind(this));
                            }
                        }
                    }
                }
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this)
            $('.not-approved-field').hide();
            $('.translated-automatically-field').hide();
        },

        prepareModels(selectFields, callback) {
            this.getModelFactory().create(this.relationship.scope, (relationModel) => {
                let models = {};
                let promises = [];
                if (this.relationship.type === 'hasMany' && this.relationship.inverseType === 'hasMany') {
                    let relationName = this.relationship.relationName.charAt(0).toUpperCase() + this.relationship.relationName.slice(1);
                    let modelRelationColumnId = this.scope.toLowerCase() + 'Id';
                    let relationshipRelationColumnId = this.relationship.scope.toLowerCase() + 'Id';
                    promises.push(new Promise(resolve => Promise.all([
                        this.ajaxGetRequest(this.relationship.scope, {
                            select: selectFields.join(','),
                            maxSize: 20 * this.collection.models.length,
                            where: [
                                {
                                    type: 'linkedWith',
                                    attribute: this.relationship.foreign,
                                    value: this.collection.models.map(m => m.id)
                                }
                            ]
                        }),

                        this.ajaxGetRequest(relationName, {
                            maxSize: 20 * this.collection.models.length,
                            where: [
                                {
                                    type: 'in',
                                    attribute: modelRelationColumnId,
                                    value: this.collection.models.map(m => m.id)
                                }
                            ]
                        })]
                    ).then(results => {
                        let list = results[0].list;
                        let relationList = results[1].list
                        this.collection.models.forEach(model => {
                            if (!models[model.id]) {
                                models[model.id] = [];
                            }
                            list.forEach(item => {
                                relationList.forEach(relationItem => {
                                    if (item.id === relationItem[relationshipRelationColumnId] && model.id === relationItem[modelRelationColumnId]) {
                                        // add intermediate columns
                                        for (let key in relationItem) {
                                            item[relationName + '__' + key] = relationItem[key];
                                        }
                                        let m = relationModel.clone();
                                        m.set(item);
                                        models[model.id].push(m);
                                    }
                                });
                            });

                            if (!models[model.id].length) {
                                models[model.id] = [relationModel.clone()]
                            }
                        });
                        resolve()
                    })));

                } else if (this.relationship.type === 'hasMany' && this.relationship.inverseType === 'belongsTo') {
                    let columnName = this.relationship.foreign + 'Id';
                    promises.push(new Promise(resolve => this.ajaxGetRequest(this.relationship.scope, {
                        select: selectFields.join(','),
                        maxSize: 20 * this.collection.models.length,
                        where: [
                            {
                                type: 'in',
                                attribute: this.relationship.foreign + 'Id',
                                value: this.collection.models.map(m => m.id)
                            }
                        ]
                    }).success((res) => {
                        this.collection.models.forEach((model) => {
                            if (!models[model.id]) {
                                models[model.id] = [];
                            }
                            res.list.forEach(item => {
                                if (item[columnName] === model.id) {
                                    let m = relationModel.clone();
                                    m.set(el)
                                    models[model.id].push(relationModel);
                                }
                            });
                            if (!models[model.id].length) {
                                models[model.id] = [relationModel.clone()]
                            }
                        });
                        resolve();
                    })));
                } else {
                    let columnName = this.relationship.name + 'Id';
                    promises.push(new Promise(resolve => this.ajaxGetRequest(this.relationship.scope, {
                        select: selectFields.join(','),
                        where: [
                            {
                                type: 'linkedWith',
                                attribute: this.relationship.foreign,
                                value: this.collection.models.map(m => m.id)
                            }
                        ]
                    }).success(res => {
                        this.collection.models.forEach((model) => {
                            res.list.forEach(item => {
                                if (item.id === model.get(columnName)) {
                                    let m = relationModel.clone();
                                    m.set(item)
                                    models[model.id] = [m];
                                }
                            });
                            if (!models[model.id]) {
                                models[model.id] = [relationModel.clone()]
                            }
                        })
                        resolve();
                    })));
                }

                Promise.all(promises).then(() => {
                    this.currentItemModels = models[this.model.id];
                    delete models[this.model.id];
                    this.collection.models.forEach((model) => {
                        if (models[model.get('id')]) {
                            this.otherItemModels.push(models[model.get('id')])
                        }
                    })
                    callback();
                })
            });
        },

        updateBaseUrl() {
        }
    })
})