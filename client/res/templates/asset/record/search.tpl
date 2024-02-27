
<div class="row search-row">
    <div class="form-group col-md-6 col-sm-7">
        <div class="input-group">
            <div class="input-group-btn left-dropdown{{#unless leftDropdown}} hidden{{/unless}}">
                <button type="button" class="btn btn-default dropdown-toggle filters-button" title="{{translate 'Filter'}}" data-toggle="dropdown" tabindex="-1">
                    <span class="filters-label"></span>
                    <span class="caret"></span>
                </button>
                <ul class="dropdown-menu pull-left filter-menu">
                    <li class="filter-menu-closer"></li>
                    <li><a class="preset" tabindex="-1" href="javascript:" data-name="" data-action="selectPreset"><div>{{translate 'All'}}</div></a></li>
                    {{#each presetFilterList}}
                        <li><a class="preset" tabindex="-1" href="javascript:" data-name="{{name}}" data-action="selectPreset"><div>{{#if label}}{{label}}{{else}}{{translate name category='presetFilters' scope=../../entityType}}{{/if}}</div></a></li>
                    {{/each}}
                    <li class="divider preset-control hidden"></li>


                    <li class="preset-control remove-preset hidden"><a tabindex="-1" href="javascript:" data-action="removePreset">{{translate 'Remove Filter'}}</a></li>
                    <li class="preset-control save-preset hidden"><a tabindex="-1" href="javascript:" data-action="savePreset">{{translate 'Save Filter'}}</a></li>

                    {{#if advancedFields.length}}
                    <li class="divider"></li>

                    <li class="dropdown-submenu">
                        <a href="javascript:" class="add-filter-button" tabindex="-1">
                            {{translate 'Add Field'}}
                        </a>
                        <ul class="dropdown-menu show-list filter-list">
                            {{#each advancedFields}}
                            <li data-name="{{name}}" class="{{#if checked}}hide{{/if}}"><a href="javascript:" class="add-filter" data-action="addFilter" data-name="{{name}}">{{translate name scope=../entityType category='fields'}}</a></li>
                            {{/each}}
                        </ul>
                    </li>
                    {{/if}}
                    {{#if showFamiliesAttributes}}
                    <li class="divider"></li>
                    <li class="dropdown-submenu">
                        <a href="javascript:" class=" add-attribute-filter-button" tabindex="-1">
                            {{translate 'Add Attribute Filter' category="labels" scope=scope}}
                        </a>
                        <ul class="dropdown-menu show-list family-list">
                            {{#if familiesAttributes}}
                            <li>
                                <input type="text" class="form-control attribute-text-filter" tabindex="1">
                                <span class="fa fa-search form-control-feedback"></span>
                            </li>
                            {{else}}
                            <li><div class="no-family-data">{{translate 'noData'}}</div></li>
                            {{/if}}
                            {{#each familiesAttributes}}
                            <li data-name="{{name}}" class="dropdown-submenu">
                                {{#if rows}}
                                <a href="javascript:" class="expand-list" tabindex="-1">
                                    {{#ifEqual name 'No family'}}{{translate 'All'}}{{else}}{{name}}{{/ifEqual}}
                                    <span class="caret"></span>
                                </a>
                                {{/if}}
                                <ul class="dropdown-menu attribute-filter-list">
                                    {{#each rows}}
                                    <li data-family="{{../name}}" data-name="{{name}}">
                                        <a href="javascript:" class="add-filter" data-action="addAttributeFilter" data-id="{{attributeId}}" data-name="{{name}}" data-type="{{type}}">{{name}}</a>
                                    </li>
                                    {{/each}}
                                </ul>
                            </li>
                            {{/each}}
                        </ul>
                    </li>
                    {{/if}}
                    {{#if boolFilterListLength}}
                        <li class="divider"></li>
                    {{/if}}

                    {{#each boolFilterListComplex}}
                        <li class="checkbox{{#if hidden}} hidden{{/if}}"><label><input type="checkbox" data-role="boolFilterCheckbox" name="{{name}}" {{#ifPropEquals ../bool name true}}checked{{/ifPropEquals}}> {{translate name scope=../entityType category='boolFilters'}}</label></li>
                    {{/each}}
                </ul>
            </div>
            {{#unless textFilterDisabled}}<input type="text" class="form-control text-filter" name="textFilter" value="{{textFilter}}" tabindex="1">{{/unless}}
            <div class="input-group-btn">
                <button type="button" class="btn btn-primary search btn-icon btn-icon-x-wide" data-action="search">
                    <span class="fa fa-search"></span>
                    <span>{{translate 'Search'}}</span>
                </button>
                <button type="button" class="btn btn-default reset" data-action="reset">
                    <span class="fa fa-redo-alt"></span>&nbsp;{{translate 'Reset'}}
                </button>
            </div>
        </div>
    </div>
    {{#if hasViewModeSwitcher}}
    <div class="form-group col-md-6 col-sm-5">
        <div class="btn-group view-mode-switcher-buttons-group">
            {{#each viewModeDataList}}
            <button type="button" data-name="{{name}}" data-action="switchViewMode" class="btn btn-sm btn-icon btn-default{{#ifEqual name ../viewMode}} active{{/ifEqual}}" title="{{title}}"><span class="{{iconClass}}"></span></button>
            {{/each}}
        </div>
    </div>
    {{/if}}
</div>

<div class="advanced-filters-bar" style="margin-bottom: 12px;"></div>
<div class="row advanced-filters grid-auto-fill-sm">
    {{#each filterDataList}}
        <div class="filter filter-{{name}} col-sm-4 col-md-3" data-name="{{name}}">
            {{{var key ../this}}}
        </div>
    {{/each}}
</div>