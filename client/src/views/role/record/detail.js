

Espo.define('views/role/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        tableView: 'views/role/record/table',

        sideView: false,

        isWide: true,

        editModeDisabled: true,

        columnCount: 3,

        setup: function () {
            Dep.prototype.setup.call(this);
            this.createView('extra', this.tableView, {
                el: this.options.el + ' .extra',
                model: this.model
            });
        },
    });
});


