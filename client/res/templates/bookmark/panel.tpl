<div class="panel panel-default">
    <div class="panel-heading">
        <div class="pull-right">
            <a href="javascript:" class="close" data-action="close"><span aria-hidden="true">×</span></a>
        </div>
        <span class="panel-heading-title">{{translate 'Bookmark' category='scopeNames' scope="Global"}}</span>
    </div>
    <div class="panel-body">
        {{#if loadingGroups}}
        <div style="padding: 8px 14px">{{translate 'Loading...'}}</div>
        {{else}}
        {{#if groups.length}}

        <div class="group-container">
            {{#each groups}}
            <div class="group" data-name="{{key}}">
                <div class="group-name">
                    <strong>{{key}}</strong>
                </div>
                <div class="list-container"><div style="padding: 8px 14px">{{translate 'Loading...'}}</div></div>
            </div>
            {{/each}}
        </div>
        {{else}}
        <div class="list-container" style="padding: 8px 14px">{{translate 'No Data'}}</div>
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
    .bookmark-panel-container .group .group-name{
        padding: 10px 5px 0 10px ;
    }
    .bookmark-panel-container .panel-body {
        padding: 0;
    }
</style>