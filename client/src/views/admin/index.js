
Espo.define('views/admin/index', 'view', function (Dep) {

    return Dep.extend({

        template: 'admin/index',

        data: function () {
            return {
                panelDataList: this.panelDataList,
                iframeUrl: this.iframeUrl,
                iframeHeight: this.getConfig().get('adminPanelIframeHeight') || 1023
            };
        },

        setup: function () {
            this.panelDataList = [];

            var panels = this.getMetadata().get('app.adminPanel') || {};
            for (var name in panels) {
                var panelItem = Espo.Utils.cloneDeep(panels[name]);
                panelItem.name = name;
                panelItem.itemList = panelItem.itemList || [];
                if (panelItem.items) {
                    panelItem.items.forEach(function (item) {
                        panelItem.itemList.push(item);
                    }, this);
                }
                this.panelDataList.push(panelItem);
            }

            this.panelDataList.sort(function (v1, v2) {
                if (!('order' in v1) && ('order' in v2)) return 0;
                if (!('order' in v2)) return 0;
                return v1.order - v2.order;
            }.bind(this));

            var iframeParams = [
                'version=' + encodeURIComponent(''),
                'css=' + encodeURIComponent(this.getConfig().get('siteUrl') + '/' + this.getThemeManager().getStylesheet())
            ];
            this.iframeUrl = this.getConfig().get('adminPanelIframeUrl') || 'https://s.espocrm.com/';
            if (~this.iframeUrl.indexOf('?')) {
                this.iframeUrl += '&' + iframeParams.join('&');
            } else {
                this.iframeUrl += '?' + iframeParams.join('&');
            }

        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Administration'));
        }

    });
});
