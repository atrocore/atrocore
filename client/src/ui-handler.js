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
    }

    _.extend(UiHandler.prototype, {

        process: function (model, type, field) {
            let preparedTriggerType = type === 'onLoad' ? 'onChange' : type;

            // console.log(this.defs, type, preparedTriggerType, field)

            this.defs.forEach(rule => {
                if (rule.triggerAction === preparedTriggerType && (rule.triggerFields.length === 0 || rule.triggerFields.includes(field))) {
                    let execute = false;
                    if (rule.conditions.type === 'basic') {
                        execute = this.checkConditionGroup(rule.conditions.conditionGroup);
                    } else if (rule.conditions.type === 'script') {
                        var contents = 'false';
                        try {
                            contents = this.twig.twig({data: rule.conditions.script}).render({entity: this.recordView.model.attributes});
                        } catch (error) {
                        }
                        execute = ['true', '1'].includes(contents.trim());
                    }

                    if (['visible', 'required', 'readOnly'].includes(rule.type) && rule.targetFields) {
                        let methodName;
                        if (execute) {
                            methodName = 'makeField' + Espo.Utils.upperCaseFirst(rule.type) + 'True';
                        } else {
                            methodName = 'makeField' + Espo.Utils.upperCaseFirst(rule.type) + 'False';
                        }
                        rule.targetFields.forEach(field => {
                            this[methodName](field);
                        });
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

                    if (rule.type === 'setValue' && execute) {
                        if (rule.updateType === 'basic') {
                            model.set(rule.updateData);
                        } else if (rule.updateType === 'script') {
                            let updateDate = null;
                            try {
                                let contents = this.twig.twig({data: rule.updateData}).render({entity: this.recordView.model.attributes});
                                updateDate = jQuery.parseJSON(contents);
                            } catch (error) {
                            }
                            if (updateDate) {
                                model.set(updateDate);
                            }
                        }
                    }
                }
            });
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

        makePanelVisibleTrue: function (field) {
            this.recordView.showPanel(field);
        },

        makePanelVisibleFalse: function (field) {
            this.recordView.hidePanel(field);
        }

    });

    return UiHandler;
});

