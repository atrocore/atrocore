<main>
    <div class="page-header">
        <div id="layout-header"></div>
        <div id="layout-header-buttons" style="display: flex">
            <div id="layout-buttons"></div>
            <button style="margin: 10px 7px 10px 20px" class="btn btn-default action" data-action="resetAllToDefault" type="button">{{translate 'resetAllToDefault'}}</button>
        </div>
    </div>

    <div style="display: flex; gap: 50px;">
        <div id="layouts-menu" class="col-sm-3">
            <div class="well row">
                <div class="col-xs-12 cell form-group">
                    <label class="control-label" data-name="layoutProfile">
                        <span class="label-text">{{translate 'layoutProfile' scope='Layout' category='fields'}}</span>
                    </label>
                    <div class="field" data-name="layoutProfile">{{{layoutProfile}}}</div>
                </div>
                <div class="col-xs-12 cell form-group">
                    <label class="control-label" data-name="entity">
                        <span class="label-text">{{translate 'entity' scope='Layout' category='fields'}}</span>
                    </label>
                    <div class="field" data-name="entity">{{{entity}}}</div>
                </div>
                <div class="col-xs-12 cell form-group">
                    <label class="control-label" data-name="viewType">
                        <span class="label-text">{{translate 'viewType' scope='Layout' category='fields'}}</span>
                    </label>
                    <div class="field" data-name="viewType">{{{viewType}}}</div>
                </div>
                <div class="col-xs-12 cell form-group">
                    <label class="control-label" data-name="relatedEntity">
                        <span class="label-text">{{translate 'relatedEntity' scope='Layout' category='fields'}}</span>
                    </label>
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
    #layout-header-buttons .btn {
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
        border-radius: 3px;
    }

    #layout-buttons .button-container {
        display: flex;
        gap: 10px;
    }

    #layouts-menu .well {
        border: 1px solid #ededed;
        background-color: #fcfcfc;
        border-radius: 3px;
    }
</style>