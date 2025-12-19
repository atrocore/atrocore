/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('conditions-checker', [], function () {

    var ConditionsChecker = function (view) {
        this.view = view;
    }

    _.extend(ConditionsChecker.prototype, {

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

            var setValue = this.view.model.get(attribute);

            if (attribute === '__currentUserId') {
                setValue = this.view.getUser().get('id');
            }

            if (defs.attributeId) {
                if (type === 'isLinked') {
                    return !!this.view.model.get('attributesDefs')?.[defs.data?.field || defs.attribute]
                } else if (type === 'isNotLinked') {
                    return !this.view.model.get('attributesDefs')?.[defs.data?.field || defs.attribute]
                }
            }

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
                return this.checkIn(value, setValue);
            } else if (type === 'notIn') {
                return !this.checkIn(value, setValue);
            } else if (type === 'isToday') {
                var dateTime = this.view.getDateTime();
                if (!setValue) return;
                if (setValue) {
                    if (setValue.length > 10) {
                        return dateTime.toMoment(setValue).isSame(dateTime.getNowMoment(), 'day');
                    } else {
                        return dateTime.toMomentDate(setValue).isSame(dateTime.getNowMoment(), 'day');
                    }
                }
            } else if (type === 'inFuture') {
                var dateTime = this.view.getDateTime();
                if (!setValue) return;
                if (setValue) {
                    if (setValue.length > 10) {
                        return dateTime.toMoment(setValue).isAfter(dateTime.getNowMoment(), 'day');
                    } else {
                        return dateTime.toMomentDate(setValue).isAfter(dateTime.getNowMoment(), 'day');
                    }
                }
            } else if (type === 'inPast') {
                var dateTime = this.view.getDateTime();
                if (!setValue) return;
                if (setValue) {
                    if (setValue.length > 10) {
                        return dateTime.toMoment(setValue).isBefore(dateTime.getNowMoment(), 'day');
                    } else {
                        return dateTime.toMomentDate(setValue).isBefore(dateTime.getNowMoment(), 'day');
                    }
                }
            } else if (type === 'inTeams') {
                return this.checkInTeams(value, setValue);
            } else if (type === 'notInTeams') {
                return !this.checkInTeams(value, setValue);
            }
            return false;
        },

        checkIn(value, setValue) {
            if (Array.isArray(setValue)) {
                for (let v of setValue) {
                    if (value.includes(v)) {
                        return true
                    }
                }
                return false
            }

            return value.includes(setValue);
        },

        checkInTeams(value, setValue) {
            if (!Array.isArray(value) || !value.length) {
                return false
            }

            const key = 'user-team-' + JSON.stringify(value)

            if (Espo[key] == null) {
                const res = Espo.ajax.getRequest('TeamUser', {
                    collectionOnly: true,
                    where: [{
                        type: 'in',
                        attribute: 'teamId',
                        value: value
                    }],
                    maxSize: 500
                }, { async: false })

                Espo[key] = (res.responseJSON?.list || []).map(d => d.userId)
            }

            const usersIds = Espo[key]

            return this.checkIn(usersIds, setValue)
        },

        getConditionGroupFields(data, type) {
            let fieldList = [];
            type = type || 'and';

            if(['and', 'or', 'not'].includes(type)) {
                list = data || [];
                for (var i in list) {
                    for (const field of this.getConditionFields(list[i])) {
                        if(!fieldList.includes(field)) {
                            fieldList.push(field);
                        }
                    }
                }
            }

            return fieldList;
        },

        getConditionFields(defs) {
            defs = defs || {};
            var type = defs.type || 'equals';

            if (['or', 'and', 'not'].includes(type)) {
                return this.getConditionGroupFields(defs.value, type);
            }

            if (!defs.attribute) return [];

            return [{name: defs.data?.field || defs.attribute, attributeId: defs.attributeId}];
        }
    });

    return ConditionsChecker;
});

