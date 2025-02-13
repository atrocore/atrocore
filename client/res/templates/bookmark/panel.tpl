<div class="panel panel-default">
    <div class="panel-heading">
        <div class="pull-right">
            <a href="javascript:" class="close" data-action="close"><span aria-hidden="true">×</span></a>
        </div>
        <span class="panel-heading-title">{{translate 'Bookmark' category='scopeNames' scope="Global"}}</span>
    </div>
    <div class="panel-body">
        {{#if loadingGroups}}
        <div>{{translate 'Loading...'}}</div>
        {{else}}
        {{#if groups.length}}

        <div class="group-container">
            {{#each groups}}
            <div class="group" data-name="{{key}}">
                <div class="entity">
                    <div class="group-name">
                        {{#if icon}}
                            <span class="icon {{icon}} fa-sm"></span>
                        {{/if}}
                        <span>{{translate key category='scopeNamesPlural' scope='Global'}}</span>
                    </div>
                   <div class="action"></div>
                </div>
                <div class="list-container"><div>{{translate 'Loading...'}}</div></div>
            </div>
            {{/each}}
        </div>
        {{else}}
        <div class="list-container">{{translate 'No Data'}}</div>
        {{/if}}
        {{/if}}
    </div>
    <div class="show-more{{#unless showMoreActive}} hide{{/unless}}">
        <a type="button" href="javascript:" class="btn btn-default btn-block" data-action="showMore">
            <span class="more-label">Show more</span>
        </a>
        <img class="preloader" style="display:none;height:12px;margin-top: 5px" src="client/img/atro-loader.svg">
    </div>
</div>
<style>
    .bookmark-panel-container .group .entity {
        display: inline-flex;
        width: 100%;
        justify-content: space-between;
        align-items: center;
        padding-right: 8px;
    }

    .bookmark-panel-container .group .group-name {
        padding-left: 20px;
    }

    .bookmark-panel-container .group .group-name * {
        color: #000;
    }

    .bookmark-panel-container .group .group-name .icon {
        margin-right: 5px;
    }

    .bookmark-panel-container .panel-body {
        padding: 10px 20px;
    }

    .bookmark-panel-container .panel-body .group-container {
        margin: -10px -20px;
    }

    .bookmark-panel-container .group .list-group-item {
        border-left: 0;
        border-right: 0;
    }

    .bookmark-panel-container .group .list-row-buttons span {
        color: var(--action-icon-color);
    }

    .bookmark-panel-container .group .cell[data-name="buttons"] {
        margin-top: 3px;
    }

    .bookmark-panel-container .group .list-group-item .expanded-row {
        white-space: normal;
    }
</style>