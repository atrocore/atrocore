

Espo.Exceptions = Espo.Exceptions || {};

Espo.Exceptions.AccessDenied = function (message) {
    this.message = message;
    Error.apply(this, arguments);
}
Espo.Exceptions.AccessDenied.prototype = new Error();
Espo.Exceptions.AccessDenied.prototype.name = 'AccessDenied';

Espo.Exceptions.NotFound = function (message) {
    this.message = message;
    Error.apply(this, arguments);
}
Espo.Exceptions.NotFound.prototype = new Error();
Espo.Exceptions.NotFound.prototype.name = 'NotFound';


