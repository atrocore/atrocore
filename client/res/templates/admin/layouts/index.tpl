<div class="page-header"><h3><a href="#Admin">{{translate 'Administration'}}</a>
        &raquo {{translate 'Layout Manager' scope='Admin'}}</h3></div>

<div class="row">
    <div id="layouts-menu" class="col-sm-3">
        <div class="row">
            <div class="col-xs-12 cell form-group">
                <label class="control-label"
                       data-name="entity">{{translate 'entity' scope='Layout' category='fields'}}</label>
                <div class="field" data-name="entity">{{{entity}}}</div>
            </div>
            <div class="col-xs-12 cell form-group">
                <label class="control-label"
                       data-name="viewType">{{translate 'viewType' scope='Layout' category='fields'}}</label>
                <div class="field" data-name="viewType">{{{viewType}}}</div>
            </div>
            <div class="col-xs-12 cell form-group">
                <label class="control-label"
                       data-name="layoutProfile">{{translate 'layoutProfile' scope='Layout' category='fields'}}</label>
                <div class="field" data-name="layoutProfile">{{{layoutProfile}}}</div>
            </div>
        </div>
    </div>

    <div id="layouts-panel" class="col-sm-9">
        <div id="layout-content">
        </div>
    </div>
</div>




