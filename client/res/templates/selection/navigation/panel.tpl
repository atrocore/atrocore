<div class="panel panel-default">
    <div class="panel-heading">
        <div class="pull-right">
            <a href="javascript:" class="close" data-action="close"><span aria-hidden="true">×</span></a>
        </div>
        <span class="panel-heading-title">{{translate 'currentSelection'}}</span>
    </div>
    <div class="panel-body">
        <div class="current-selection cell" style="padding:10px 20px 20px 20px ;">
           <div class="field">
               {{{currentSelectionField}}}
           </div>
        </div>
        {{#if loadingGroups}}
         <div  style="padding: 10px 20px;">{{translate 'Loading...'}}</div>
        {{else}}
        {{#if groups.length}}
        <div class="group-container">
            {{#each groups}}
            <div class="group" data-name="{{key}}">
                <div class="entity">
                    <div class="group-name">
                        {{#if icon}}
                            <img class="icon" src="{{icon}}">
                        {{/if}}
                        <span>{{translate key category='scopeNamesPlural' scope='Global'}}</span>
                    </div>
                   <div class="action"></div>
                </div>
                <div class="list-container"><div  style="padding: 10px 20px;">{{translate 'Loading...'}}</div></div>
            </div>
            {{/each}}
        </div>
        {{else}}
        <div class="list-container"><div class="no-data-container">{{translate 'No Data'}}</div></div>
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