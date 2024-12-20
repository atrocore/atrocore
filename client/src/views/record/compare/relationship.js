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

        shouldHide: false,

        relationFields: [],

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
            this.relationFields = [];
            this.relationModels = {};
            this.isLinkedColumns = 'isLinked58894';
            this.linkedEntities = []
            this.tableRows = [];
            this.relationName = this.relationship.relationName.charAt(0).toUpperCase() + this.relationship.relationName.slice(1);

            this.fetchModelsAndSetup();
        },

        fetchModelsAndSetup() {
            this.wait(true)
            let selectField = ['id'];
            let fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', 'name', 'type']);
            if (fieldType) {
                selectField.push('name')
            }
            this.prepareModels(selectField, () => this.setupRelationship(() => this.wait(false)));
        },

        data() {
            let totalLength = 0;
            let minWidth = 150;
            return {
                name: this.relationship.name,
                scope: this.scope,
                relationScope: this.relationship.scope,
                columns: this.columns,
                tableRows: this.tableRows,
                columnCountCurrent: this.columns,
                totalLength,
                minWidth
            }
        },

        setupRelationship(callback) {
            this.tableRows = [];

            this.linkedEntities.forEach((linkEntity) => {

                let data = [];
                if(this.relationship.scope === 'File') {
                    data.push({
                        field: this.isLinkedColumns,
                        label:'',
                        isField: true,
                        key: linkEntity.id,
                        entityValueKeys: []
                    });
                    this.getModelFactory().create('File', fileModel => {
                        fileModel.set(linkEntity);
                        let viewName = fileModel.getFieldParam('preview', 'view') || this.getFieldManager().getViewName(fileModel.getFieldType('preview'));
                        let viewKey = linkEntity.id;
                        this.createView(viewKey, viewName, {
                            el:  `${this.options.el} [data-key="${viewKey}"] .attachment-preview`,
                            model: fileModel,
                            readOnly: true,
                            defs: {
                                name: 'preview',
                            },
                            mode: 'list',
                            inlineEditDisabled: true,
                        }, view => {
                            view.previewSize = 'small';
                            view.once('after:render', () => {
                                this.$el.find(`[data-key="${viewKey}"]`).append(`<div class="file-link">
<a href="?entryPoint=download&id=${linkEntity.id}" download="" title="Download">
 <span class="glyphicon glyphicon-download-alt small"></span>
 </a> 
 <a href="/#File/view/${linkEntity.id}" title="${linkEntity.name}" class="link" data-id="${linkEntity.id}">${linkEntity.name}</a>
 </div>`);
                            })
                        });
                    });
                }else{
                    data.push({
                        field: this.isLinkedColumns,
                        label: `<a href="#/${this.relationship.scope}/view/${linkEntity.id}"> ${linkEntity.name ?? linkEntity.id} </a>`,
                        entityValueKeys: []
                    });
                }

                this.getRelationAdditionalFields().forEach(field => {
                    data.push({
                        field: field,
                        label: this.translate(field, 'fields', this.relationName),
                        entityValueKeys: []
                    });
                })

                this.relationModels[linkEntity.id].forEach((model, index) => {
                    data.forEach(el => {
                        let field = el.field;
                        let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(model.getFieldType(field));
                        let viewKey = linkEntity.id + field + index + 'Current';
                        el.entityValueKeys.push({key: viewKey})
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
                })

                this.tableRows.push(...data);
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
            if (this.shouldHide) {
                this.$el.parent().parent().hide();
            }
        },

        prepareModels(selectFields, callback) {

            let relationName = this.relationship.relationName.charAt(0).toUpperCase() + this.relationship.relationName.slice(1);

            this.getModelFactory().create(relationName, (relationModel) => {
                let models = {};
                relationModel.defs.fields[this.isLinkedColumns] = {
                    type: 'bool'
                }
                let modelRelationColumnId = this.scope.toLowerCase() + 'Id';
                let relationshipRelationColumnId = this.relationship.scope.toLowerCase() + 'Id';
                Promise.all([
                    this.ajaxGetRequest(this.relationship.scope, {
                        select: selectFields.join(','),
                        maxSize: 500 * this.collection.models.length,
                        where: [
                            {
                                type: 'linkedWith',
                                attribute: this.relationship.foreign,
                                value: this.collection.models.map(m => m.id)
                            }
                        ]
                    }),

                    this.ajaxGetRequest(relationName, {
                        maxSize: 500 * this.collection.models.length,
                        where: [
                            {
                                type: 'in',
                                attribute: modelRelationColumnId,
                                value: this.collection.models.map(m => m.id)
                            }
                        ]
                    })]
                ).then(results => {
                    let relationList = results[1].list;
                    let uniqueList = {};
                    results[0].list.forEach(v => uniqueList[v.id] = v);
                    list = Object.values(uniqueList)
                    this.linkedEntities = list;
                    list.forEach(item => {
                        this.relationModels[item.id] = [];
                        this.collection.models.forEach((model, key) => {
                            let m = relationModel.clone()
                            m.set(this.isLinkedColumns, false);
                            relationList.forEach(relationItem => {
                                if (item.id === relationItem[relationshipRelationColumnId] && model.id === relationItem[modelRelationColumnId]) {
                                    m.set(relationItem);
                                    m.set(this.isLinkedColumns, true);
                                }
                            });

                            this.relationModels[item.id].push(m);
                        })
                    });

                    callback();
                });
            });
        },

        getRelationAdditionalFields() {
            if (this.relationFields.length) {
                return this.relationFields;
            }

            let relationName = this.relationName;

            Object.entries(this.getMetadata().get(['entityDefs', relationName, 'fields'])).forEach(([field, fieldDef]) => {
                if (fieldDef.additionalField) {
                    this.relationFields.push(field);
                }
            });

            return this.relationFields;
        },

        updateBaseUrl() {
        }
    })
})