<div class="page-header">
    <h3>
        <div class="header-breadcrumbs fixed-header-breadcrumbs">
            <div class="breadcrumbs-wrapper">
                <a href="#Admin">{{translate 'Administration'}}</a>{{translate 'Layout Manager' scope='Admin'}}
            </div>
        </div>
        <div class="header-title">{{translate 'Layout Manager' scope='Admin'}}</div>
    </h3>

    <button style="margin: 10px 7px 10px 5px" class="btn btn-default action" data-action="resetAllToDefault" type="button">{{translate 'resetAllToDefault'}}</button>
</div>

<div class="row" style="margin-left: -3px">
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
        <h4 id="layout-header" style="margin-top: 0px;"></h4>
        <div id="layout-content">
            {{{content}}}
        </div>
    </div>
</div>




