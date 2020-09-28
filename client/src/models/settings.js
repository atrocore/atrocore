
Espo.define('models/settings', 'model-offline', function (Dep) {

    return Dep.extend({

        name: 'Settings',

        getByPath: function (arr) {
            if (!arr.length) return null;

            var p;

            for (var i = 0; i < arr.length; i++) {
                var item = arr[i];
                if (i == 0) {
                    p = this.get(item);
                } else {
                    if (item in p) {
                        p = p[item];
                    } else {
                        return null;
                    }
                }
                if (i === arr.length - 1) {
                    return p;
                }
                if (p === null || typeof p !== 'object') {
                    return null;
                }
            }
        }

    });

});
