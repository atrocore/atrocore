<main>
    <div class="page-header">
        <h3>
            <div class="header-breadcrumbs">
                <div class="breadcrumbs-wrapper">
                    <a href="#Admin">{{translate 'Administration'}}</a>{{translate 'Layout Manager' scope='Admin'}}
                </div>
            </div>
            <div class="header-title">{{translate 'Layout Manager' scope='Admin'}}</div>
        </h3>

        <div style="display: flex">
            <div id="layout-buttons"></div>
            <button style="margin: 10px 7px 10px 20px" class="btn btn-default action" data-action="resetAllToDefault" type="button">{{translate 'resetAllToDefault'}}</button>
        </div>
    </div>

    <div style="display: flex; gap: 50px;">
        <div id="layouts-menu" class="col-sm-3">
            <div class="well row">
                <div class="col-xs-12 cell form-group">
                    <label class="control-label"
                           data-name="layoutProfile">{{translate 'layoutProfile' scope='Layout' category='fields'}}</label>
                    <div class="field" data-name="layoutProfile">{{{layoutProfile}}}</div>
                </div>
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
                           data-name="relatedEntity">{{translate 'relatedEntity' scope='Layout' category='fields'}}</label>
                    <div class="field" data-name="relatedEntity">{{{relatedEntity}}}</div>
                </div>
            </div>
        </div>

        <div id="layouts-panel" style="padding: 0 20px" class="col-sm-9">
            <div id="layout-content"></div>
        </div>
    </div>
</main>

<style>
    #layouts-menu .well {
        border: 1px solid #ededed;
        background-color: #fcfcfc;
        border-radius: 3px;
    }
</style>