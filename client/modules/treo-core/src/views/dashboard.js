

Espo.define('treo-core:views/dashboard', 'class-replace!treo-core:views/dashboard',
    Dep => Dep.extend({

        setupCurrentTabLayout() {
            if (!this.dashboardLayout) {
                var defaultLayout = [
                    {
                        "name": "My Espo",
                        "layout": []
                    }
                ];
                if (this.getUser().get('portalId')) {
                    this.dashboardLayout = this.getConfig().get('dashboardLayout') || [];
                } else {
                    this.dashboardLayout = this.getPreferences().get('dashboardLayout') || defaultLayout;
                    var activeDashlets = this.getMetadata().get('dashlets');
                    this.dashboardLayout.forEach(tab => {
                        tab.layout = tab.layout.filter(dashlet => dashlet.name in activeDashlets);
                    });
                }

                if (this.dashboardLayout.length == 0 || Object.prototype.toString.call(this.dashboardLayout) !== '[object Array]') {
                    this.dashboardLayout = defaultLayout;
                }
            }

            var dashboardLayout = this.dashboardLayout || [];

            if (dashboardLayout.length <= this.currentTab) {
                this.currentTab = 0;
            }

            var tabLayout = dashboardLayout[this.currentTab].layout || [];

            tabLayout = GridStackUI.Utils.sort(tabLayout);

            this.currentTabLayout = tabLayout;
        },

    })
);