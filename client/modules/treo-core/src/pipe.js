

Espo.define('treo-core:pipe', 'view',
    Dep => Dep.extend({

        _template: '',

        runPipe(data) {
            return data;
        }

    })
);
