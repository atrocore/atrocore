
Espo.define('controllers/home', 'controller', function (Dep) {

    return Dep.extend({

        index: function () {
            this.main('views/home', null);
        },

        search: function (text) {
            $.ajax({
                url: 'search',
                type: 'GET',
                data: {
                    text: text
                },
            });
        },
    });
});

