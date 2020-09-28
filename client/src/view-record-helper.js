
Espo.define('view-record-helper', [], function () {

    var ViewRecordHelper = function (defaultFieldStates, defaultPanelStates) {
        if (defaultFieldStates) {
            this.defaultFieldStates = defaultFieldStates;
        }
        if (defaultPanelStates) {
            this.defaultPanelStates = defaultPanelStates;
        }
        this.fieldStateMap = {};
        this.panelStateMap = {};

        this.hiddenFields = {};
        this.hiddenPanels = {};

        this.fieldOptionListMap = {};
    };

    _.extend(ViewRecordHelper.prototype, {

        defaultFieldStates: {},

        defaultPanelStates: {},

        getHiddenFields: function () {
            return this.hiddenFields;
        },

        getHiddenPanels: function () {
            return this.hiddenPanels;
        },

        setFieldStateParam: function (field, name, value) {
            switch (name) {
                case 'hidden':
                    if (value) {
                        this.hiddenFields[field] = true;
                    } else {
                        delete this.hiddenFields[field];
                    }
                    break;
            }
            this.fieldStateMap[field] = this.fieldStateMap[field] || {};
            this.fieldStateMap[field][name] = value;
        },

        getFieldStateParam: function (field, name) {
            if (field in this.fieldStateMap) {
                if (name in this.fieldStateMap[field]) {
                    return this.fieldStateMap[field][name];
                }
            }
            if (name in this.defaultFieldStates) {
                return this.defaultFieldStates[name];
            }
            return null;
        },

        setPanelStateParam: function (panel, name, value) {
            switch (name) {
                case 'hidden':
                    if (value) {
                        this.hiddenPanels[panel] = true;
                    } else {
                        delete this.hiddenPanels[panel];
                    }
                    break;
            }
            this.panelStateMap[panel] = this.panelStateMap[panel] || {};
            this.panelStateMap[panel][name] = value;
        },

        getPanelStateParam: function (panel, name) {
            if (panel in this.panelStateMap) {
                if (name in this.panelStateMap[panel]) {
                    return this.panelStateMap[panel][name];
                }
            }
            if (name in this.defaultPanelStates) {
                return this.defaultPanelStates[name];
            }
            return null;
        },

        setFieldOptionList: function (field, list) {
            this.fieldOptionListMap[field] = list;
        },

        clearFieldOptionList: function (field) {
            delete this.fieldOptionListMap[field];
        },

        getFieldOptionList: function (field) {
            return this.fieldOptionListMap[field] || null;
        },

        hasFieldOptionList: function (field) {
            return (field in this.fieldOptionListMap);
        }

    });

    return ViewRecordHelper;

});
