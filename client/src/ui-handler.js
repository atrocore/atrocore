/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('ui-handler', [], function () {

    var UiHandler = function (defs, recordView, twig) {
        this.defs = defs || {};
        this.recordView = recordView;
        this.twig = twig;
        this.twigTemplateData = {
            entity: this.recordView.model.attributes,
            entityFrom: this.recordView.model.attributes._entityFrom || null,
            isNew: function (entity) {
                return !(entity.id)
            },
            getEntity: function (entityName, entityId) {
                let res = null;
                this.ajaxGetRequest(`${entityName}/${entityId}?silent=true`, null, {async: false}).success(entity => {
                    res = entity;
                }).error(response => {
                    console.log('getEntity function failed', response);
                });
                return res;
            }.bind(this.recordView),
            findRecord: function (entityName, where) {
                let res = null;
                if (entityName && where) {
                    this.ajaxGetRequest(entityName, {
                        where: where,
                        offset: 0,
                        maxSize: 1,
                        silent: "true"
                    }, {async: false}).success(response => {
                        if (response.list && response.list[0]) {
                            res = response.list[0];
                        }
                    }).error(response => {
                        console.log('findRecord function failed', response);
                    });
                }
                return res;
            }.bind(this.recordView),
            findRecords: function (entityName, where, offset = 0, limit = 200) {
                let res = null;
                if (entityName && where) {
                    this.ajaxGetRequest(entityName, {
                        where: where,
                        offset: offset,
                        maxSize: limit,
                        silent: "true"
                    }, {async: false}).success(response => {
                        if (response.list) {
                            res = response.list;
                        }
                    }).error(response => {
                        console.log('findRecords function failed', response);
                    });
                }
                return res;
            }.bind(this.recordView)
        };
    }

    _.extend(UiHandler.prototype, {

        process: function (type, field, additionalParams = {}) {
            let preparedTriggerType = type === 'onLoad' ? 'onChange' : type;

            this.twigTemplateData['triggerType'] = type;
            this.twigTemplateData['triggerField'] = field;

            if('currentUserId' in additionalParams){
                this.twigTemplateData['currentUserId'] =  additionalParams.currentUserId;
            }

            this.defs.forEach(rule => {
                let triggerFields = rule.triggerFields || [];
                if (rule.triggerAction === preparedTriggerType && (triggerFields.length === 0 || triggerFields.includes(field))) {
                    let execute = false;
                    if (rule.conditions.type === 'basic') {
                        execute = this.checkConditionGroup(rule.conditions.conditionGroup);
                    } else if (rule.conditions.type === 'script') {
                        var contents = 'false';
                        try {
                            contents = this.twig.twig({data: rule.conditions.script}).render(this.twigTemplateData);
                        } catch (error) {
                            console.log(error)
                        }
                        execute = ['true', '1'].includes(contents.trim());
                    }

                    if (['visible', 'required', 'readOnly', 'disableOptions'].includes(rule.type)) {
                        if (rule.targetFields) {
                            let methodName;
                            if (execute) {
                                methodName = 'makeField' + Espo.Utils.upperCaseFirst(rule.type) + 'True';
                            } else {
                                methodName = 'makeField' + Espo.Utils.upperCaseFirst(rule.type) + 'False';
                            }
                            rule.targetFields.forEach(field => {
                                this[methodName](field, rule);
                            });
                        }
                    } else {
                        let methodName = 'execute' + Espo.Utils.upperCaseFirst(rule.type);
                        if (execute && typeof this[methodName] === "function") {
                            this[methodName](rule);
                        }
                    }

                    if (['visible'].includes(rule.type) && rule.targetPanels) {
                        let methodName;
                        if (execute) {
                            methodName = 'makePanel' + Espo.Utils.upperCaseFirst(rule.type) + 'True';
                        } else {
                            methodName = 'makePanel' + Espo.Utils.upperCaseFirst(rule.type) + 'False';
                        }
                        rule.targetPanels.forEach(panelName => {
                            this[methodName](panelName);
                        });
                    }
                }
            });

            if (preparedTriggerType === 'onChange') {
                Object.keys((this.defs.panels || {})).forEach(panel => {
                    this.processPanel(panel);
                });
            }
        },

        processPanel: function (panel) {
            const type = 'visible';
            var panels = this.defs.panels || {};
            var item = (panels[panel] || {});

            if (!(type in item)) return;
            var typeItem = (item[type] || {});
            if (!typeItem.conditionGroup) return;

            if (this.checkConditionGroup(typeItem.conditionGroup)) {
                this.makePanelVisibleTrue(panel);
            } else {
                this.makePanelVisibleFalse(panel);
            }
        },


        checkConditionGroup: function (data, type) {
            type = type || 'and';

            var list;

            var result = false;
            if (type === 'and') {
                list = data || [];
                result = true;
                for (var i in list) {
                    if (!this.checkCondition(list[i])) {
                        result = false;
                        break;
                    }
                }
            } else if (type === 'or') {
                list = data || [];
                for (var i in list) {
                    if (this.checkCondition(list[i])) {
                        result = true;
                        break;
                    }
                }
            } else if (type === 'not') {
                if (data) {
                    result = !this.checkCondition(data);
                }
            }
            return result;
        },

        checkCondition: function (defs) {
            defs = defs || {};
            var type = defs.type || 'equals';

            if (~['or', 'and', 'not'].indexOf(type)) {
                return this.checkConditionGroup(defs.value, type);
            }

            var attribute = defs.attribute;
            var value = defs.value;

            if (!attribute) return;

            var setValue = this.recordView.model.get(attribute);

            if (type === 'equals') {
                if (!value) return;
                return setValue === value;
            } else if (type === 'notEquals') {
                if (!value) return;
                return setValue !== value;
            } else if (type === 'isEmpty') {
                if (Array.isArray(setValue)) {
                    return !setValue.length;
                }
                return setValue === null || (setValue === '') || typeof setValue === 'undefined';
            } else if (type === 'isNotEmpty') {
                if (Array.isArray(setValue)) {
                    return !!setValue.length;
                }
                return setValue !== null && (setValue !== '') && typeof setValue !== 'undefined';
            } else if (type === 'isTrue') {
                return !!setValue;
            } else if (type === 'isFalse') {
                return !setValue;
            } else if (type === 'contains' || type === 'has') {
                if (!setValue) return false;
                return !!~setValue.indexOf(value);
            } else if (type === 'notContains' || type === 'notHas') {
                if (!setValue) return true;
                return !~setValue.indexOf(value);
            } else if (type === 'greaterThan') {
                return setValue > value;
            } else if (type === 'lessThan') {
                return setValue < value;
            } else if (type === 'greaterThanOrEquals') {
                return setValue >= value;
            } else if (type === 'lessThanOrEquals') {
                return setValue <= value;
            } else if (type === 'in') {
                return ~value.indexOf(setValue);
            } else if (type === 'notIn') {
                return !~value.indexOf(setValue);
            } else if (type === 'isToday') {
                var dateTime = this.recordView.getDateTime();
                if (!setValue) return;
                if (setValue) {
                    if (setValue.length > 10) {
                        return dateTime.toMoment(setValue).isSame(dateTime.getNowMoment(), 'day');
                    } else {
                        return dateTime.toMomentDate(setValue).isSame(dateTime.getNowMoment(), 'day');
                    }
                }
            } else if (type === 'inFuture') {
                var dateTime = this.recordView.getDateTime();
                if (!setValue) return;
                if (setValue) {
                    if (setValue.length > 10) {
                        return dateTime.toMoment(setValue).isAfter(dateTime.getNowMoment(), 'day');
                    } else {
                        return dateTime.toMomentDate(setValue).isAfter(dateTime.getNowMoment(), 'day');
                    }
                }
            } else if (type === 'inPast') {
                var dateTime = this.recordView.getDateTime();
                if (!setValue) return;
                if (setValue) {
                    if (setValue.length > 10) {
                        return dateTime.toMoment(setValue).isBefore(dateTime.getNowMoment(), 'day');
                    } else {
                        return dateTime.toMomentDate(setValue).isBefore(dateTime.getNowMoment(), 'day');
                    }
                }
            }
            return false;
        },

        makeFieldVisibleTrue: function (field) {
            this.recordView.showField(field);
        },

        makeFieldVisibleFalse: function (field) {
            this.recordView.hideField(field);
        },

        makeFieldRequiredTrue: function (field) {
            this.recordView.setFieldRequired(field);
        },

        makeFieldRequiredFalse: function (field) {
            this.recordView.setFieldNotRequired(field);
        },

        makeFieldReadOnlyTrue: function (field) {
            this.recordView.setFieldReadOnly(field);
        },

        makeFieldReadOnlyFalse: function (field) {
            this.recordView.setFieldNotReadOnly(field);
        },

        makeFieldDisableOptionsTrue: function (field, rule) {
            this.recordView.setFieldAddDisabledOptions(field, rule.disabledOptions || []);
        },

        makeFieldDisableOptionsFalse: function (field, rule) {
            this.recordView.setFieldRemoveDisabledOptions(field, rule.disabledOptions || []);
        },

        makePanelVisibleTrue: function (field) {
            this.recordView.showPanel(field);
        },

        makePanelVisibleFalse: function (field) {
            this.recordView.hidePanel(field);
        }

    });

    return UiHandler;
});

