

Espo.define('treo-core:views/record/panels/bottom', 'class-replace!treo-core:views/record/panels/bottom',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            let link = this.link || this.defs.link || this.panelName;
            if (!this.defs.notRefreshable && (link in this.model.defs.links)) {
                this.buttonList.push({
                    title: this.translate('clickToRefresh', 'messages', 'Global'),
                    action: 'refresh',
                    link: this.link,
                    acl: 'read',
                    aclScope: this.scope,
                    html: '<span class="fas fa-sync"></span>'
                });
            }
        }

    })
);