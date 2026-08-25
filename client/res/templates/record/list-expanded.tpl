{{#if collection.models.length}}
{{#if topBar}}
<div class="list-buttons-container clearfix">
    {{#if checkboxes}}
    {{#if massActionList}}
    <div class="btn-group actions">
        <button type="button" class="btn btn-default btn-sm dropdown-toggle actions-button" data-toggle="dropdown" disabled>
        {{translate 'Actions'}}
        <span class="caret"></span>
        </button>
        <ul class="dropdown-menu">
            {{#each massActionList}}
            <li><a href="javascript:" data-action="{{./this}}" class='mass-action'>{{translate this category="massActions" scope=../scope}}</a></li>
            {{/each}}
        </ul>
    </div>
    {{/if}}
    {{/if}}

    {{#each buttonList}}
        {{button name scope=../../scope label=label style=style}}
    {{/each}}
</div>
{{/if}}

<div class="list list-expanded">
    <ul class="list-group">
    {{#each rowList}}
        <li data-id="{{./this}}" class="list-group-item list-row">
        {{{var this ../this}}}
        </li>
    {{/each}}
    </ul>
</div>
{{#if showMoreEnabled}}
<div class="table-show-more-container"></div>
{{/if}}

{{else}}
    {{#if collectionLoading }}
        <img className="preloader" style="height: 14px"  src="client/img/atro-loader.svg" alt="Loading"/>
    {{else}}
         <div class="no-data-container">{{translate 'No Data'}}</div>
    {{/if}}
{{/if}}
