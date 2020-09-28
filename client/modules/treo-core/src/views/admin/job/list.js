

Espo.define('treo-core:views/admin/job/list', 'class-replace!treo-core:views/admin/job/list',
    Dep => Dep.extend({

        getHeader: function () {
            return `<a href="#Admin">${this.translate('Administration')}</a><span class="subsection">${this.translate('Scheduled Jobs', 'labels', 'Admin')}</span>${this.getLanguage().translate('Jobs', 'labels', 'Admin')}`;
        }

    })
);

