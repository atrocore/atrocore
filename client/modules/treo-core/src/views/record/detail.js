

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
 */

Espo.define('treo-core:views/record/detail', 'class-replace!treo-core:views/record/detail', function (Dep) {

    return Dep.extend({

        template: 'treo-core:record/detail',

        panelNavigationView: 'treo-core:views/record/panel-navigation',

        events: _.extend({
            'click a[data-action="collapseAllPanels"]': function (e) {
                this.collapseAllPanels('hide');
            },
            'click a[data-action="expandAllPanels"]': function (e) {
                this.collapseAllPanels('show');
            }
        }, Dep.prototype.events),

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.collection) {
                this.stopListening(this.model, 'destroy');
                this.listenTo(this.model, 'destroy', function () {
                    this.collection.fetch();
                }, this);
            }

            $(window).on('keydown', e => {
                if (e.keyCode === 69 && e.ctrlKey && !$('body').hasClass('modal-open')) {
                    this.hotKeyEdit(e);
                }
                if (e.keyCode === 83 && e.ctrlKey && !$('body').hasClass('modal-open')) {
                    this.hotKeySave(e);
                }
            });

            if (!this.model.isNew() && (this.type === 'detail' || this.type === 'edit') && this.getMetadata().get(['scopes', this.scope, 'advancedFilters'])) {
                this.listenTo(this, 'after:render', () => {
                    this.applyOverviewFilters();
                });
                this.listenTo(this.model, 'sync overview-filters-changed', () => {
                    this.applyOverviewFilters();
                });
            }
        },

        actionSave: function () {
            let savingCanceled = false;

            this.listenToOnce(this, 'cancel:save', () => savingCanceled = true);

            const setDetailAndScroll = () => {
                this.setDetailMode();
                $(window).scrollTop(0)
            };

            if (this.save(setDetailAndScroll, true) && savingCanceled) {
                setDetailAndScroll();
            }
        },

        afterSaveError: function () {
            Dep.prototype.afterSaveError.call(this);

            this.model.fetch();
        },

        applyOverviewFilters() {
            let currentFieldFilter = (this.model.advancedEntityView || {}).fieldsFilter;
            let currentLocaleFilter = (this.model.advancedEntityView || {}).localesFilter;
            let showGenericFields = (this.model.advancedEntityView || {}).showGenericFields;

            let fields = this.getFieldViews();
            Object.keys(fields).forEach(name => {
                let fieldView = fields[name];
                if (!fieldView.model.getFieldParam(name, 'advancedFilterDisabled')) {
                    if (
                        currentLocaleFilter !== null && typeof currentLocaleFilter !== 'undefined'
                        &&
                        this.getConfig().get('isMultilangActive')
                        &&
                        fieldView.model.getFieldParam(name, 'isMultilang')
                        &&
                        (this.getConfig().get('inputLanguageList') || []).length
                    ) {
                        let hiddenLocales = currentLocaleFilter ? this.getConfig().get('inputLanguageList').filter(lang => lang !== currentLocaleFilter) : [];
                        fieldView.setHiddenLocales(hiddenLocales);
                        let langFieldNameList = fieldView.getLangFieldNameList();
                        langFieldNameList = langFieldNameList.filter(field => this.checkFieldValue(currentFieldFilter, fieldView.model.get(field), fieldView.isRequired()));
                        fieldView.langFieldNameList = langFieldNameList;
                        fieldView.hideMainOption = (showGenericFields !== null && typeof showGenericFields !== 'undefined' && !showGenericFields)
                            || !this.checkFieldValue(currentFieldFilter, fieldView.model.get(name), fieldView.isRequired());
                        fieldView.expandLocales = fieldView.hideMainOption || !!(hiddenLocales.length || currentLocaleFilter);
                        this.controlFieldVisibility(fieldView, !fieldView.langFieldNameList.length && fieldView.hideMainOption);
                        fieldView.reRender();
                    } else {
                        let actualFields = this.getFieldManager().getActualAttributeList(fieldView.model.getFieldType(name), name);
                        let actualFieldValues = actualFields.map(field => fieldView.model.get(field));
                        actualFieldValues = actualFieldValues.concat(this.getAlternativeValues(fieldView));
                        let hide = !actualFieldValues.every(value => this.checkFieldValue(currentFieldFilter, value, fieldView.isRequired()));
                        this.controlFieldVisibility(fieldView, hide);
                    }
                }
            });

            this.model.trigger('overview-filters-applied');
        },

        getAlternativeValues(fieldView) {
            let values = [];
            if (fieldView.name === 'image') {
                values.push(fieldView.urlImage);
            }
            return values;
        },

        controlFieldVisibility(field, condition) {
            if (condition) {
                field.hide();
                field.overviewFiltersHidden = true;
            } else if (field.overviewFiltersHidden) {
                field.show();
            }
        },

        checkFieldValue(currentFieldFilter, value, required) {
            let check = !currentFieldFilter;
            if (currentFieldFilter === 'empty') {
                check = value === null || value === '' || (Array.isArray(value) && !value.length);
            }
            if (currentFieldFilter === 'emptyAndRequired') {
                check = (value === null || value === '' || (Array.isArray(value) && !value.length)) && required;
            }
            return check;
        },

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            const dropDownItems = this.getMetadata().get(['clientDefs', this.scope, 'additionalDropdownItems']) || {};
            Object.keys(dropDownItems).forEach(item => {
                const check = (dropDownItems[item].conditions || []).every(condition => {
                    let check;
                    switch (condition.type) {
                        case 'type':
                            check = this.type === condition.value;
                            break;
                        default:
                            check = true;
                            break;
                    }
                    return check;
                });

                if (check) {
                    let dropdownItem = {
                        name: dropDownItems[item].name,
                        label: dropDownItems[item].label
                    };
                    if (dropDownItems[item].iconClass) {
                        let htmlLogo = `<span class="additional-action-icon ${dropDownItems[item].iconClass}"></span>`;
                        dropdownItem.html = `${this.translate(dropDownItems[item].label, 'labels', this.scope)} ${htmlLogo}`;
                    }
                    this.dropdownItemList.push(dropdownItem);

                    let method = 'action' + Espo.Utils.upperCaseFirst(dropDownItems[item].name);
                    this[method] = function () {
                        let path = dropDownItems[item].actionViewPath;

                        let o = {};
                        (dropDownItems[item].optionsToPass || []).forEach((option) => {
                            if (option in this) {
                                o[option] = this[option];
                            }
                        });

                        this.createView(item, path, o, (view) => {
                            if (typeof view[dropDownItems[item].action] === 'function') {
                                view[dropDownItems[item].action]();
                            }
                        });
                    };
                }
            }, this);
        },

        collapseAllPanels(type) {
            let bottom = this.getView('bottom');
            if (bottom) {
                (bottom.panelList || []).forEach(panel => {
                    bottom.trigger('collapsePanel', panel.name, type);
                });
            }
        },

        setupFinal: function () {
            this.build(this.addCollapsingButtonsToMiddleView);
        },

        addCollapsingButtonsToMiddleView(view) {
            view.listenTo(view, 'after:render', view => {
                let bottom = this.getView('bottom');
                if (bottom && bottom.panelList.length) {
                    let html = `` +
                        `<a class="btn btn-link collapsing-button" data-action="collapseAllPanels">` +
                            `<span class="fas fa-chevron-up"></span>` +
                            `${this.getLanguage().translate('collapseAllPanels', 'labels', 'Global')}` +
                        `</a>` +
                        `<a class="btn btn-link collapsing-button" data-action="expandAllPanels">` +
                            `<span class="fas fa-chevron-down"></span>` +
                            `${this.getLanguage().translate('expandAllPanels', 'labels', 'Global')}` +
                        `</a>`;
                    view.$el.find('.panel-heading:first').append(html);
                }
            });
        },

        hotKeyEdit: function (e) {
            e.preventDefault();
            if (this.mode !== 'edit') {
                this.actionEdit();
            }
        },

        hotKeySave: function (e) {
            e.preventDefault();
            if (this.mode === 'edit') {
                this.actionSave();
            } else {
                let viewsFields = this.getFieldViews();
                Object.keys(viewsFields).forEach(item => {
                    if (viewsFields[item].mode === "edit") {
                        viewsFields[item].inlineEditSave();
                    }
                });
            }
        },

        createBottomView: function () {
            var el = this.options.el || '#' + (this.id);
            this.createView('bottom', this.bottomView, {
                model: this.model,
                scope: this.scope,
                el: el + ' .bottom',
                readOnly: this.readOnly,
                type: this.type,
                inlineEditDisabled: this.inlineEditDisabled,
                recordHelper: this.recordHelper,
                recordViewObject: this,
                portalLayoutDisabled: this.portalLayoutDisabled
            }, view => {
                this.listenToOnce(view, 'after:render', () => {
                    this.createPanelNavigationView(view.panelList);
                })
            });
        },

        createPanelNavigationView(panelList) {
            let el = this.options.el || '#' + (this.id);
            this.createView('panelNavigation', this.panelNavigationView, {
                panelList: panelList,
                model: this.model,
                scope: this.scope,
                el: el + ' .panel-navigation',
            }, function (view) {
                this.listenTo(this, 'after:set-detail-mode', () => {
                    view.reRender();
                });
                view.render();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            var $container = this.$el.find('.detail-button-container');

            var stickTop = this.getThemeManager().getParam('stickTop') || 62;
            var blockHeight = this.getThemeManager().getParam('blockHeight') || 21;

            var $block = this.$el.find('.detail-button-container + div');
            var $window = $(window);
            var screenWidthXs = this.getThemeManager().getParam('screenWidthXs');

            $window.off('scroll.detail-' + this.numId);
            $window.on('scroll.detail-' + this.numId, function (e) {
                if ($(window.document).width() < screenWidthXs) {
                    $container.removeClass('stick-sub');
                    $block.hide();
                    $container.show();
                    return;
                }

                var edge = this.$el.position().top + this.$el.outerHeight(true);
                var scrollTop = $window.scrollTop();

                if (scrollTop < edge) {
                    if (scrollTop > stickTop) {
                        if (!$container.hasClass('stick-sub') && this.mode !== 'edit') {
                            var $p = $('.popover:not(.note-popover)');
                            $p.each(function (i, el) {
                                var $el = $(el);
                                $el.css('top', ($el.position().top - ($container.height() - blockHeight * 2 + 10)) + 'px');
                            }.bind(this));
                        }
                        $container.addClass('stick-sub');
                        $block.show();
                    } else {
                        if ($container.hasClass('stick-sub') && this.mode !== 'edit') {
                            var $p = $('.popover:not(.note-popover)');
                            $p.each(function (i, el) {
                                var $el = $(el);
                                $el.css('top', ($el.position().top + ($container.height() - blockHeight * 2 + 10)) + 'px');
                            }.bind(this));
                        }
                        $container.removeClass('stick-sub');
                        $block.hide();
                    }
                    var $p = $('.popover');
                    $p.each(function (i, el) {
                        var $el = $(el);
                        let top = $el.css('top').slice(0, -2);
                        if (top > 0 && scrollTop > 0 && top > scrollTop) {
                            if (stickTop > $container.height()) {
                                if (top - scrollTop > stickTop) {
                                    $el.removeClass('hidden');
                                } else {
                                    $el.addClass('hidden');
                                }
                            } else {
                                if (top - scrollTop > ($container.height() + blockHeight * 2 + 10)) {
                                    $el.removeClass('hidden');
                                } else {
                                    $el.addClass('hidden');
                                }
                            }
                        }
                    }.bind(this));
                }
            }.bind(this));
        },

        setEditMode: function () {
            this.trigger('before:set-edit-mode');
            this.$el.find('.record-buttons').addClass('hidden');
            this.$el.find('.edit-buttons').removeClass('hidden');
            this.disableButtons();

            var fields = this.getFieldViews(true);
            var count = Object.keys(fields || {}).length;
            for (var field in fields) {
                var fieldView = fields[field];
                if (!fieldView.readOnly) {
                    if (fieldView.mode == 'edit') {
                        fieldView.fetchToModel();
                        fieldView.removeInlineEditLinks();
                    }
                    fieldView.setMode('edit');
                    fieldView.render(() => {
                        count--;
                        if (count === 0) {
                            this.enableButtons();
                        }
                    });
                } else {
                    count--;
                    if (count === 0) {
                        this.enableButtons();
                    }
                }
            }
            this.mode = 'edit';
            this.trigger('after:set-edit-mode');
        },

        convertDetailLayout(simplifiedLayout) {
            let layout = Dep.prototype.convertDetailLayout.call(this, simplifiedLayout);

            return this.prepareLayoutAfterConverting(layout);
        },

        prepareLayoutAfterConverting(layout) {
            return layout;
        }

    });

});