/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

Espo.define('treo-core:views/record/list', 'class-replace!treo-core:views/record/list', function (Dep) {

    return Dep.extend({

        template: 'treo-core:record/list',

        enabledFixedHeader: false,

        checkedAll: false,

        dragndropEventName: null,

        massRelationView: 'treo-core:views/modals/select-entity-and-records',

        baseWidth: [],

        setup() {
            this.setupDraggableParams();

            Dep.prototype.setup.call(this);

            this.enabledFixedHeader = this.options.enabledFixedHeader || this.enabledFixedHeader;
            this.baseWidth = [];

            this.listenTo(this, 'after:save', () => {
                this.collection.fetch();
            });

            $(window).on(`keydown.${this.cid} keyup.${this.cid}`, e => {
                document.onselectstart = function() {
                    return !e.shiftKey;
                }
            });

            this.dragndropEventName = `resize.drag-n-drop-table-${this.cid}`;
            this.listenToOnce(this, 'remove', () => {
                $(window).off(this.dragndropEventName);
                $(window).off(`keydown.${this.cid} keyup.${this.cid}`);
            });
        },

        checkIntervalRecords(e, $target) {
            const checked = this.$el.find('.record-checkbox:checked');
            const allCheckboxes = this.$el.find('.record-checkbox');
            if (e.shiftKey && checked.length > 1) {
                const elementIndexInArray = allCheckboxes.index($target);
                const firstCheckedElementIndexInArray = allCheckboxes.index(checked.eq(0));
                const lastCheckedElementIndexInArray = allCheckboxes.index(checked.eq(checked.length - 1));

                //set cycle indexes for checking interval records
                let startIndex = elementIndexInArray;
                let endIndex = lastCheckedElementIndexInArray;
                if (elementIndexInArray !== firstCheckedElementIndexInArray) {
                    startIndex = firstCheckedElementIndexInArray;
                    endIndex = elementIndexInArray;
                }

                for (let i = startIndex; i <= endIndex; i++) {
                    let checkbox = allCheckboxes.eq(i);
                    this.checkRecord(checkbox.data('id'), checkbox);
                }
            }
        },

        setupDraggableParams() {
            this.dragableListRows = this.dragableListRows || this.options.dragableListRows;
            this.listRowsOrderSaveUrl = this.listRowsOrderSaveUrl || this.options.listRowsOrderSaveUrl;

            const urlParts = (this.collection.url || '').split('/');
            const mainScope = urlParts[0];
            this.relationName = urlParts[2];
            if (mainScope && this.relationName) {
                const dragDropDefs = this.getMetadata().get(['clientDefs', mainScope, 'relationshipPanels', this.relationName, 'dragDrop']);
                if (dragDropDefs && (this.dragableListRows || typeof this.dragableListRows === 'undefined')) {
                    this.dragableListRows = dragDropDefs.isActive;
                    this.dragableSortField = dragDropDefs.sortField;
                    if (this.dragableSortField) {
                        this.collection.sortBy = this.dragableSortField;
                    }
                }
            }

            if (this.dragableListRows) {
                // this.listenTo(this.collection, 'listSorted', () => this.collection.fetch());
            }
        },

        setupMassActionItems() {
            Dep.prototype.setupMassActionItems.call(this);

            if (this.getMetadata().get(['scopes', this.scope, 'addRelationEnabled'])) {
                let foreignEntities = this.getForeignEntities();
                if (foreignEntities.length) {
                    this.massActionList = Espo.Utils.clone(this.massActionList);
                    this.massActionList.push('addRelation');
                    this.massActionList.push('removeRelation');
                }
            }
        },

        getForeignEntities() {
            let foreignEntities = [];
            if (this.scope && this.getAcl().check(this.scope, 'edit')) {
                let links = this.getMetadata().get(['entityDefs', this.scope, 'links']) || {};
                let linkList = Object.keys(links).sort(function (v1, v2) {
                    return v1.localeCompare(v2);
                }.bind(this));

                linkList.forEach(link => {
                    let defs = links[link];

                    if (defs.foreign && defs.entity && this.getAcl().check(defs.entity, 'edit')) {
                        let foreignType = this.getMetadata().get(['entityDefs', defs.entity, 'links', defs.foreign, 'type']);
                        if (this.checkRelationshipType(defs.type, foreignType)
                            && (this.getMetadata().get(['scopes', defs.entity, 'entity']) || defs.addRelationCustomDefs)
                            && !this.getMetadata().get(['scopes', defs.entity, 'disableMassRelation'])
                            && !defs.disableMassRelation) {
                            foreignEntities.push({
                                link: link,
                                entity: defs.entity,
                                addRelationCustomDefs: defs.addRelationCustomDefs
                            });
                        }
                    }
                });
            }
            return foreignEntities;
        },

        checkRelationshipType: function (type, foreignType) {
            if (type === 'hasMany') {
                if (foreignType === 'hasMany') {
                    return 'manyToMany';
                } else if (foreignType === 'belongsTo') {
                    return 'oneToMany';
                }
            }
        },

        massActionUpdateRelation(type) {
            let foreignEntities = this.getForeignEntities();
            if (!foreignEntities.length) {
                return;
            }

            this.notify('Loading...');
            this.getModelFactory().create(null, model => {
                model.set({
                    mainEntity: this.scope,
                    selectedLink: foreignEntities[0].link,
                    foreignEntities: foreignEntities
                });

                let view = this.getMetadata().get(['clientDefs', this.scope, 'massRelationView']) || this.massRelationView;
                this.createView('dialog', view, {
                    model: model,
                    mainCollection: this.collection,
                    multiple: true,
                    createButton: false,
                    scope: (foreignEntities[0].addRelationCustomDefs || {}).entity || foreignEntities[0].entity,
                    type: type,
                    checkedList: this.checkedList
                }, view => {
                    view.render(() => {
                        this.notify(false);
                    });
                });
            });
        },

        massActionAddRelation() {
            this.massActionUpdateRelation('addRelation');
        },

        massActionRemoveRelation() {
            this.massActionUpdateRelation('removeRelation');
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.fullTableScroll();

            if (this.enabledFixedHeader) {
                this.fixedTableHead()
            }

            let list = $('#main > .list-container > .list');
            if (list && this.hasHorizontalScroll()) {
                var $bar = $('<div class="fixed-scrollbar"><div></div></div>').appendTo(list).css({
                    width: list.outerWidth()
                });
                $bar.scroll(function() {
                    list.scrollLeft($bar.scrollLeft());
                });
                $bar.data("status", "off");

                var fixSize = function() {
                    var $container = $bar.parent();

                    $bar.children('div').height(1).width($container[0].scrollWidth);
                    $bar.width($container.width()).scrollLeft($container.scrollLeft());
                };

                fixSize();
                $(window).on("resize.fixed-scrollbar", function() {
                    fixSize();
                });

                var scrollTimeout = null;

                $(window).on("scroll.fixed-scrollbar", function() {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(function() {
                        list.each(function() {
                            var $container = $(this);
                            var $bar = $container.children('.fixed-scrollbar');

                            if ($bar.length) {
                                var containerOffset = {
                                    top: $container.offset().top,
                                    bottom: $container.offset().top + $container.height()
                                };
                                var windowOffset = {
                                    top: $(window).scrollTop(),
                                    bottom: $(window).scrollTop() + $(window).height()
                                };

                                if ((containerOffset.top > windowOffset.bottom) || (windowOffset.bottom > containerOffset.bottom)) {
                                    if ($bar.data("status") == "on") {
                                        $bar.hide().data("status", "off");
                                    }
                                } else {
                                    if ($bar.data("status") == "off") {
                                        $bar.show().data("status", "on");
                                        $bar.scrollLeft($container.scrollLeft());
                                    }
                                }
                            }
                        });
                    }, 50);
                });

                $(window).trigger("scroll.fixed-scrollbar");
            }

            this.changeDropDownPosition();

            if (this.dragableListRows && !((this.getParentView() || {}).defs || {}).readOnly) {
                let allowed = true;
                (this.collection.models || []).forEach(model => {
                    if (this.getAcl().checkModel(model, 'edit') === false) {
                        allowed = false;
                    }
                });

                if (!allowed) {
                    $("td[data-name='draggableIcon'] span").remove();
                }

                if (allowed) {
                    this.initDraggableList();
                    $(window).off(this.dragndropEventName).on(this.dragndropEventName, () => {
                        this.initDraggableList();
                    });
                }
            }
        },

        fullTableScroll() {
            let list = $('#main > .list-container > .list');
            if (list) {
                let fixedTableHeader = $('#main > .list-container > .list > .fixed-header-table');
                let fullTable = $('#main > .list-container > .list > .full-table');

                if (fixedTableHeader && fullTable) {
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

                    if (this.hasHorizontalScroll()) {
                        fixedTableHeader.addClass('table-scrolled');
                        fullTable.addClass('table-scrolled');

                        let rowsButtons = $('td[data-name="buttons"]');
                        let rowsButtonsPosition = list.width() - 35;
                        if ($(window).outerWidth() > 768 && rowsButtons.length) {
                            rowsButtons.addClass('fixed-button');

                            rowsButtons.css('left', rowsButtonsPosition);
                        }

                        let prevScrollLeft = 0;

                        list.on('scroll', () => {
                            if (prevScrollLeft !== list.scrollLeft()) {
                                let fixedTableHeaderBasePosition = list.offset().left + 1 || 0;
                                fixedTableHeader.css('left', fixedTableHeaderBasePosition - list.scrollLeft());

                                if ($(window).outerWidth() > 768 && rowsButtons.hasClass('fixed-button')) {
                                    rowsButtons.css('left', rowsButtonsPosition + list.scrollLeft());
                                }
                            }
                            prevScrollLeft = list.scrollLeft();
                        });
                    }
                }
            }
        },

        initDraggableList() {
            if (this.getAcl().check(this.scope, 'edit')) {
                this.setCellWidth();

                this.$el.find(this.listContainerEl).sortable({
                    handle: window.innerWidth < 768 ? '.cell[data-name="draggableIcon"]' : false,
                    delay: 150,
                    update: function (e, ui) {
                        this.saveListItemOrder(e, ui);
                    }.bind(this)
                });
            }
        },

        setCellWidth() {
            let el = this.$el.find(this.listContainerEl);

            el.find('td').each(function (i) {
                $(this).css('width', $(this).outerWidth());
            });
        },

        saveListItemOrder(e, ui) {
            let url;
            let data;
            if (this.dragableSortField) {
                const itemId = this.getItemId(ui);
                if (itemId) {
                    const sortFieldValue = this.getSortFieldValue(itemId);
                    url = `${this.scope}/${itemId}`;
                    const parent = this.getParentView();
                    data = {
                        _id: parent ? parent.model ? parent.model.id : null : null,
                        _sortedIds: this.getIdsFromDom(),
                        [this.dragableSortField]: sortFieldValue
                    };
                }
            } else if (this.listRowsOrderSaveUrl) {
                url = this.listRowsOrderSaveUrl;
                data = {
                    ids: this.getIdsFromDom()
                };
            }
            if (url) {
                this.ajaxPutRequest(url, data)
                    .then(response => {
                        let statusMsg = 'Error occurred';
                        let type = 'error';
                        if (response) {
                            statusMsg = 'Saved';
                            type = 'success';
                        }
                        this.notify(statusMsg, type, 3000);
                    }, error => {
                        this.collection.fetch();
                    })
                    .always(() => this.collection.trigger('listSorted'));
            } else {
                this.collection.trigger('listSorted', this.getIdsFromDom());
            }
        },

        getItemId(ui) {
            let id;
            if (ui && ui.item) {
                id = ui.item.data('id');
            }
            return id;
        },

        getSortFieldValue(id) {
            let value;
            const ids = this.getIdsFromDom();
            const currIndex = ids.indexOf(id);
            if (currIndex > 0) {
                value = this.collection.get(ids[currIndex - 1]).get(this.dragableSortField) + 10;
            } else {
                value = this.collection.get(ids[currIndex + 1]).get(this.dragableSortField) - 10;
            }
            return value;
        },

        getIdsFromDom() {
            return $.map(this.$el.find(`${this.listContainerEl} tr`), function (item) {
                return $(item).data('id');
            });
        },

        filterListLayout: function (listLayout) {
            if (this.dragableListRows && !((this.getParentView() || {}).defs || {}).readOnly && listLayout
                && Array.isArray(listLayout) && !listLayout.find(item => item.name === 'draggableIcon')) {
                listLayout = Espo.Utils.cloneDeep(listLayout);
                listLayout.unshift({
                    widthPx: '40',
                    align: 'center',
                    notSortable: true,
                    customLabel: '',
                    name: 'draggableIcon',
                    view: 'treo-core:views/fields/draggable-list-icon'
                });
            }

            return Dep.prototype.filterListLayout.call(this, listLayout)
        },

        _getHeaderDefs() {
            let defs = Dep.prototype._getHeaderDefs.call(this);
            let model = this.collection.model.prototype;
            defs.forEach(item => {
                if (item.name && ['currency', 'wysiwyg', 'wysiwygMultiLang'].includes(model.getFieldType(item.name))) {
                    item.sortable = false;
                }
            });
            return defs;
        },

        fixedTableHead() {

            let $window = $(window),
                fixedTable = this.$el.find('.fixed-header-table'),
                fullTable = this.$el.find('.full-table'),
                navBarRight = $('.navbar-right'),
                posLeftTable = 0,
                navBarHeight = 0,

                setPosition = () => {
                    posLeftTable = fullTable.offset().left;
                    navBarHeight = navBarRight.outerHeight();

                    fixedTable.css({
                        'position': 'fixed',
                        'left': posLeftTable,
                        'top': navBarHeight - 1,
                        'right': 0,
                        'z-index': 1
                    });
                },
                setWidth = () => {
                    let widthTable = fullTable.outerWidth();

                    fixedTable.css('width', widthTable);

                    fullTable.find('thead').find('th').each(function (i, elem) {
                        let width = $(this).outerWidth();

                        if (!width) {
                            $(this).attr('width', 100);
                        }

                        fixedTable.find('th').eq(i).css('width', width);
                    });
                },
                toggleClass = () => {
                    let showPosition = fullTable.offset().top;

                    if ($window.scrollTop() > showPosition && $window.width() >= 768) {
                        fixedTable.removeClass('hidden');
                    } else {
                        fixedTable.addClass('hidden');
                    }
                };

            if (fullTable.length) {
                setPosition();
                setWidth();
                toggleClass();

                $window.on('scroll', toggleClass);
                $window.on('resize', function () {
                    this.fullTableScroll();
                    setPosition();
                    setWidth();
                }.bind(this));
            }
        },

        changeDropDownPosition() {
            let el = this.$el;

            el.on('show.bs.dropdown', function (e) {
                let target = e.relatedTarget;
                let menu = $(target).siblings('.dropdown-menu');
                if (target && menu) {
                    let menuHeight = menu.height();
                    let positionTop = $(target).offset().top + $(target).outerHeight(true);

                    if ((positionTop + menuHeight) > this.getHeightParentPosition()) {
                        menu.css({
                            'top': `-${menuHeight}px`
                        });
                    }
                }
            }.bind(this));

            el.on('hide.bs.dropdown', function (e) {
                $(e.relatedTarget).next('.dropdown-menu').removeAttr('style');
            });
        },

        getHeightParentPosition() {
            let position;
            let parent = this.getParentView();

            if (parent && parent.viewMode === 'list' && this.$el.find('.list')) {
                let list = this.$el.find('.list');
                position = list.offset().top + list.get(0).clientHeight;
            } else {
                position = $(document).height();
            }

            return position;
        },

        hasHorizontalScroll() {
            let list = this.$el.find('.list').get(0);
            let table = this.$el.find('.full-table').get(0);

            if (list && table) {
                if (list.clientWidth < table.clientWidth) {
                    return true;
                }
            }

            return false;
        },

        fetchAttributeListFromLayout() {
            let selectList = [];
            if (this.scope && !this.getMetadata().get(['clientDefs', this.scope, 'disabledSelectList'])) {
                selectList = Dep.prototype.fetchAttributeListFromLayout.call(this);
                selectList = this.modifyAttributeList(selectList);
            }
            return selectList;
        },

        modifyAttributeList(attributeList) {
            return _.union(attributeList, this.getMetadata().get(['clientDefs', this.scope, 'additionalSelectAttributes']));
        },

        massActionMassUpdate: function () {
            if (!this.getAcl().check(this.entityType, 'edit')) {
                this.notify('Access denied', 'error');
                return false;
            }

            Espo.Ui.notify(this.translate('loading', 'messages'));
            var ids = false;
            var allResultIsChecked = this.allResultIsChecked;
            if (!allResultIsChecked) {
                ids = this.checkedList;
            }

            this.createView('massUpdate', 'views/modals/mass-update', {
                scope: this.entityType,
                ids: ids,
                where: this.collection.getWhere(),
                selectData: this.collection.data,
                byWhere: this.allResultIsChecked
            }, function (view) {
                view.render();
                view.notify(false);
                view.once('after:update', function () {
                    view.close();
                    this.listenToOnce(this.collection, 'sync', function () {
                        if (this.entityType === 'QueueItem') {
                            Espo.Ui.success(this.translate('Done'));
                        } else {
                            Espo.Ui.success(this.translate('byQueueManager', 'messages', 'QueueItem'));
                        }
                        if (allResultIsChecked) {
                            this.selectAllResult();
                        } else {
                            ids.forEach(function (id) {
                                this.checkRecord(id);
                            }, this);
                        }
                    }.bind(this));
                    this.collection.fetch();
                }, this);
            }.bind(this));
        },

        massActionRemove: function () {
            if (!this.getAcl().check(this.entityType, 'delete')) {
                this.notify('Access denied', 'error');
                return false;
            }

            var count = this.checkedList.length;
            var deletedCount = 0;

            var self = this;

            this.confirm({
                message: this.translate('removeSelectedRecordsConfirmation', 'messages'),
                confirmText: this.translate('Remove')
            }, function () {
                this.notify('Removing...');

                var ids = [];
                var data = {};
                if (this.allResultIsChecked) {
                    data.where = this.collection.getWhere();
                    data.selectData = this.collection.data || {};
                    data.byWhere = true;
                } else {
                    data.ids = ids;
                }

                for (var i in this.checkedList) {
                    ids.push(this.checkedList[i]);
                }

                $.ajax({
                    url: this.entityType + '/action/massDelete',
                    type: 'POST',
                    data: JSON.stringify(data)
                }).done(function (result) {
                    if (this.entityType === 'QueueItem') {
                        Espo.Ui.success(this.translate('Done'));
                        this.collection.fetch();
                    } else {
                        Espo.Ui.success(this.translate('byQueueManager', 'messages', 'QueueItem'));
                    }
                }.bind(this));
            }, this);
        },

    });
});
