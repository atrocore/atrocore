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
        currentItemModels: [],
        otherItemModels: [],
        init(){

        },
        setup() {
            this.scope = this.options.scope;
            this.baseModel = this.options.model;
            this.relationship = this.options.relationship;
            this.instances = this.getMetadata().get(['app','comparableInstances']);
            this.checkedList = [];
            this.enabledFixedHeader = false;
            this.dragableListRows = false;
            this.showMore = false
            this.fields = [];

            this.getParentView()

           this.fetchModelsAndSetup();
        },
        fetchModelsAndSetup(){
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
                    this.getModelFactory().create(this.relationship.scope, function (model) {
                        let selectField = [];
                        this.fields.forEach(field => {
                            let type = model.getFieldType(field)
                            if(['file','link'].includes(type)){
                                selectField.push(field + 'Id');
                                selectField.push(field + 'Name');
                                return;
                            }

                            if( type === 'linkMultiple'){
                                selectField.push(field+'Ids');
                                selectField.push(field+'Names');
                                return;
                            }

                            selectField.push(field);
                        })
                        this.ajaxGetRequest(this.scope+'/'+this.model.get('id')+'/'+this.relationship.name, {
                            select: selectField.join(',')
                        }).success(res => {
                            this.currentItemModels = res.list.map( item => {
                                let itemModel = model.clone()
                                itemModel.set(item)
                                return itemModel
                            });
                            this.ajaxGetRequest('Synchronization/action/distantInstanceRequest',{
                                'uri':this.scope+'/' + this.model.get('id')+'/' + this.relationship.name + '?select=' + selectField.join(','),
                                'type':'list'
                            }).success(res => {
                                this.otherItemModels = res.map( (data, index) => (data.list ?? []).map(item => {
                                    for(let key in item){
                                        let el = item[key];
                                        let instanceUrl = this.instances[index].atrocoreUrl;
                                        if(key.includes('PathsData')){
                                            if(el['thumbnails']){
                                                for (let size in el['thumbnails']){
                                                    item[key]['thumbnails'][size] = instanceUrl + '/' + el['thumbnails'][size]
                                                }
                                            }
                                        }
                                    }
                                    let itemModel = model.clone()
                                    itemModel.set(item)
                                    return itemModel
                                }));
                                this.setupRelationship(() => this.wait(false));
                            })
                        });
                    }, this);
                }
            });
        },
        data(){
            return {
                name: this.relationship.name,
                scope: this.relationship.scope,
                instances: this.instances.map((i, key) => {
                    i['columnCount'] = this.otherItemModels[key].length;
                    return i;
                }),
                relationshipsFields: this.relationshipsFields,
                columnCountCurrent: Math.max(this.currentItemModels.length,1),
                currentItemModels: this.currentItemModels
            }
        },
        setupRelationship(callback){
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
                    this.createView(viewKey, viewName,  {
                        el: this.options.el +` [data-field="${viewKey}"]`,
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
                    data.othersModelsKeyPerInstances[index1]= [];
                    instanceModels.forEach((model, index2) => {
                        this.others
                        let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(model.getFieldType(field));
                        let viewKey = this.relationship.name + field + index1 + 'Others' + index2;
                        data.othersModelsKeyPerInstances[index1].push({key: viewKey})
                        this.createView(viewKey, viewName,  {
                            el: this.options.el +` [data-field="${viewKey}"]`,
                            model: model,
                            readOnly: true,
                            defs: {
                                name: field,
                            },
                            mode: 'detail',
                            inlineEditDisabled: true,
                        }, view => {
                            view.render()
                            let instanceUrl = this.instances[index1].atrocoreUrl;
                            this.updateBaseUrl(view, instanceUrl);
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
                let scroll = this.$el.parent().siblings('.panel-scroll');

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
                            $(this).css('left', list.width() - $(this).width() - 5)
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
                                    $(this).css('left', list.scrollLeft() + list.width() - $(this).width() - 5)
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
                                $(this).css('left', scroll.scrollLeft() + list.width() - $(this).width() - 5)
                            });

                            this.listenTo(this.collection, 'sync', function () {
                                if (!this.hasHorizontalScroll()) {
                                    scroll.addClass('hidden');
                                }
                            }.bind(this));

                            scroll.on('scroll', () => {
                                fullTable.css('left', -1 * scroll.scrollLeft());
                                rowsButtons.each(function () {
                                    $(this).css('left', scroll.scrollLeft() + list.width() - $(this).width() - 5)
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
        afterRender(){
            Dep.prototype.afterRender.call(this)
            $('.not-approved-field').hide();
        },
        updateBaseUrl(view, instanceUrl){
            view.listenTo(view, 'after:render', () => {
                setTimeout(() => {
                    let localUrl = this.getConfig().get('siteUrl');
                    view.$el.find('a').each((i, el) => {
                        let href = $(el).attr('href')

                        if(href.includes('http') && localUrl){
                            $(el).attr('href', href.replace(localUrl, instanceUrl))
                        }

                        if((!href.includes('http') && !localUrl) || href.startsWith('/#') || href.startsWith('?')){
                            $(el).attr('href', instanceUrl + href)
                        }
                        $(el).attr('target','_blank')
                    });

                    view.$el.find('img').each((i, el) => {
                        let src = $(el).attr('src')
                        if(src.includes('http') && localUrl){
                            $(el).attr('src', src.replace(localUrl, instanceUrl))
                        }

                        if(!src.includes('http')){
                            $(el).attr('src', instanceUrl + '/' + src)
                        }
                    });
                })
            }, 1000)
        }
    })
})