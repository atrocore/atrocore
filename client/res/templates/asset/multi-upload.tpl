<div class="form-group files">
    <label>Upload Your File </label>
    <input type="file" class="form-control" multiple="" data-name="upload">
</div>

<style>
    .files input {
        outline            : 2px dashed #92b0b3;
        outline-offset     : -10px;
        -webkit-transition : outline-offset .15s ease-in-out, background-color .15s linear;
        transition         : outline-offset .15s ease-in-out, background-color .15s linear;
        padding            : 120px 0px 85px 35%;
        text-align         : center !important;
        margin             : 0;
        width              : 100% !important;
    }
    .files input:focus { outline : 2px dashed #92b0b3; outline-offset : -10px;
        -webkit-transition       : outline-offset .15s ease-in-out, background-color .15s linear;
        transition               : outline-offset .15s ease-in-out, background-color .15s linear; border : 1px solid #92b0b3;
    }
    .files { position : relative}
    .files:after { pointer-events : none;
        position                  : absolute;
        top                       : 60px;
        left                      : 0;
        width                     : 50px;
        right                     : 0;
        height                    : 56px;
        content                   : "";
        display                   : block;
        margin                    : 0 auto;
        background-size           : 100%;
        background-repeat         : no-repeat;
    }
    .color input { background-color : #f1f1f1;}
    .files:before {
        position       : absolute;
        bottom         : 10px;
        left           : 0; pointer-events : none;
        width          : 100%;
        right          : 0;
        height         : 57px;
        content        : " or drag it here. ";
        display        : block;
        margin         : 0 auto;
        color          : #2ea591;
        font-weight    : 600;
        text-transform : capitalize;
        text-align     : center;
    }
</style>