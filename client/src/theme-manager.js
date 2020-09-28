
Espo.define('theme-manager', [], function () {

    var ThemeManager = function (config, preferences, metadata) {
        this.config = config;
        this.preferences = preferences;
        this.metadata = metadata;
    };

    _.extend(ThemeManager.prototype, {

        defaultParams: {
            screenWidthXs: 768,
            dashboardCellHeight: 155,
            dashboardCellMargin: 19
        },

        getName: function () {
            if (!this.config.get('userThemesDisabled')) {
                var name = this.preferences.get('theme');
                if (name && name !== '') {
                    return name;
                }
            }
            return this.config.get('theme');
        },

        getAppliedName: function () {
            return window.getComputedStyle(document.body).getPropertyValue('--theme-name');
        },

        isApplied: function () {
            var appliedName = this.getAppliedName();
            if (!appliedName) return true;
            return this.getName() === appliedName;
        },

        getStylesheet: function () {
            var link = this.metadata.get(['themes', this.getName(), 'stylesheet']) || 'client/css/espo/espo.css';
            if (this.config.get('cacheTimestamp')) {
                link += '?r=' + this.config.get('cacheTimestamp').toString();
            }
            return link
        },

        getIframeStylesheet: function () {
            var link = this.metadata.get(['themes', this.getName(), 'stylesheetIframe']) || 'client/css/espo/espo-iframe.css';
            if (this.config.get('cacheTimestamp')) {
                link += '?r=' + this.config.get('cacheTimestamp').toString();
            }
            return link
        },

        getParam: function (name) {
            return this.metadata.get(['themes', this.getName(), name]) || this.defaultParams[name] || null;
        },

        isUserTheme: function () {
            if (!this.config.get('userThemesDisabled')) {
                var name = this.preferences.get('theme');
                if (name && name !== '') {
                    if (name !== this.config.get('theme')) {
                        return true;
                    }
                }
            }
            return false;
        }

    });

    return ThemeManager;

});
